<?php
/**
 * Measures reviewed predictions in a bounded local metadata ledger.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Separates immutable predictions, human labels and unreviewed observations.
 *
 * References are keyed local pseudonyms, not anonymous visitor identities.
 * No message, email address, IP address or original object reference is stored.
 * Single-row updates avoid committing or changing a provider's transaction.
 *
 * @since 0.2.0
 */
final class Feedback {

	/**
	 * Maximum age of both linked and detached quality observations.
	 *
	 * @since 0.2.0
	 * @var int
	 */
	private const DAYS = 90;

	/**
	 * Request-local existence hints avoid repeated ledger reads while GF saves fields.
	 *
	 * @since 0.2.0
	 * @var array<int,bool>
	 */
	private array $gravity_links = array();

	/**
	 * Creates only the owned metadata table through WordPress's schema API.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table     = $wpdb->prefix . 'rktsky_antispam_feedback';
		$collation = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			ref char(64) NOT NULL,
			source varchar(32) NOT NULL,
			predicted_spam tinyint(1) unsigned NOT NULL,
			actual_spam tinyint(1) unsigned DEFAULT NULL,
			linked tinyint(1) unsigned NOT NULL DEFAULT 1,
			created bigint(20) unsigned NOT NULL,
			expires bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (ref),
			KEY expires (expires)
		) $collation;"
		);
	}

	/**
	 * Registers internal object lifecycle and native confirmed-privacy callbacks.
	 *
	 * These actions carry object IDs only and expose no public review endpoint.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'rktsky_antispam_quarantined', array( $this, 'track_quarantine' ) );
		add_action( 'rktsky_antispam_quarantine_deleted', array( $this, 'forget_quarantine' ) );
		add_action( 'deleted_comment', array( $this, 'forget_comment' ) );
		add_action( 'transition_comment_status', array( $this, 'comment_review' ), 10, 3 );
		add_action( 'gform_update_status', array( $this, 'gravity_review' ), 10, 3 );
		add_action( 'gform_delete_entry', array( $this, 'forget_gravity' ) );
		add_filter( 'gform_save_field_value', array( $this, 'gravity_field_update' ), PHP_INT_MAX, 5 );
		add_action( 'gform_post_update_entry', array( $this, 'gravity_entry_update' ), PHP_INT_MAX, 2 );
		add_action( 'gform_after_update_entry', array( $this, 'gravity_detail_update' ), PHP_INT_MAX, 3 );
		add_action( 'rktsky_antispam_cleanup', array( $this, 'cleanup' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ), PHP_INT_MAX );
	}

	/**
	 * Records one immutable prediction for an actually observed native object.
	 *
	 * Repeated tracking cannot relabel or extend an existing observation.
	 *
	 * @since 0.2.0
	 * @param string     $source Native source category or quarantine.
	 * @param int|string $id Positive native object ID.
	 * @param bool       $predicted_spam Whether the observed decision rejected as spam.
	 * @return void
	 */
	public function track( string $source, $id, bool $predicted_spam ): void {
		$ref = $this->reference( $source, $id );
		if ( '' === $ref ) {
			return;
		}
		global $wpdb;
		$created  = time();
		$inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (ref,source,predicted_spam,actual_spam,linked,created,expires) VALUES (%s,%s,%d,NULL,1,%d,%d)', $wpdb->prefix . 'rktsky_antispam_feedback', $ref, $source, (int) $predicted_spam, $created, $created + self::DAYS * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One immutable owned metadata observation; no matching Core API exists.
		if ( 'gravityforms' === $source && false !== $inserted ) {
			$this->gravity_links[ (int) $id ] = true;
		}
	}

	/**
	 * Applies an authorized human label to a tracked object without changing prediction.
	 *
	 * One atomic row update makes repeated labels idempotent and concurrent label
	 * changes last-writer-wins. Unknown, detached or expired observations fail.
	 * A caller exposing a POST action must additionally verify its native nonce.
	 *
	 * @since 0.2.0
	 * @param string     $source Tracked source category.
	 * @param int|string $id Native object ID or stable manual case reference.
	 * @param bool       $actual_spam Human judgement of the same observed item.
	 * @return bool Whether that label exists on the still-linked active observation.
	 */
	public function review( string $source, $id, bool $actual_spam ): bool {
		$ref = $this->reference( $source, $id );
		if ( '' === $ref || ! $this->authorized( $source, $id ) ) {
			return false;
		}
		global $wpdb;
		$result = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET actual_spam=%d WHERE ref=%s AND linked=1 AND expires>%d', $wpdb->prefix . 'rktsky_antispam_feedback', (int) $actual_spam, $ref, time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic label replacement prevents duplicate classification counts without touching provider transactions.
		if ( false === $result ) {
			return false;
		}
		$actual = $wpdb->get_var( $wpdb->prepare( 'SELECT actual_spam FROM %i WHERE ref=%s AND linked=1 AND expires>%d', $wpdb->prefix . 'rktsky_antispam_feedback', $ref, time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Readback distinguishes an idempotent update from an untracked or concurrently deleted object.
		return null !== $actual && (int) $actual === (int) $actual_spam;
	}

	/**
	 * Reports an explicitly identified missed case without claiming inbox observation.
	 *
	 * The administrator supplies a stable local case ID, not the message body.
	 * Only its source-separated HMAC is persisted. Repeated reports of the same
	 * reference are idempotent while the ninety-day observation is retained.
	 *
	 * @since 0.2.0
	 * @param string $provider Supported provider category.
	 * @param string $reference Stable local case reference, one to 128 UTF-8 bytes.
	 * @return bool Whether a manually reported false negative is recorded.
	 */
	public function report_missed( string $provider, string $reference ): bool {
		return $this->report_delivered( $provider, $reference, true );
	}

	/**
	 * Classifies an explicitly identified delivered case as normal or missed spam.
	 *
	 * Existing native observations are reviewed directly instead of double-counted.
	 * Unlinked providers use one stable manual reference, whose original text is
	 * never stored. Labels can be corrected idempotently within the active window.
	 *
	 * @since 0.2.0
	 * @param string $provider Supported provider category.
	 * @param string $reference Stable local case reference, one to 128 UTF-8 bytes.
	 * @param bool   $actual_spam Human classification of that delivered case.
	 * @return bool Whether the classification is present on the active observation.
	 */
	public function report_delivered( string $provider, string $reference, bool $actual_spam ): bool {
		if ( ! current_user_can( 'manage_options' ) || ! in_array( $provider, Providers::ids(), true ) || '' === $reference || strlen( $reference ) > 128 || wp_check_invalid_utf8( $reference ) !== $reference ) {
			return false;
		}
		if ( null !== $this->row( $provider, $reference ) ) {
			return $this->review( $provider, $reference, $actual_spam );
		}
		$source = 'manual_' . $provider;
		$this->track( $source, $reference, false );
		return $this->review( $source, $reference, $actual_spam );
	}

	/**
	 * Removes the link to an explicitly identified manual case after authorization.
	 *
	 * Pending data is deleted; reviewed data is detached and expires within the
	 * same bounded metric window. A linked native observation is handled directly.
	 *
	 * @since 0.2.0
	 * @param string $provider Supported provider category.
	 * @param string $reference Original stable local case reference.
	 * @return bool Whether an active matching reference was found and removed.
	 */
	public function forget_manual( string $provider, string $reference ): bool {
		if ( ! current_user_can( 'manage_options' ) || ! in_array( $provider, Providers::ids(), true ) || '' === $reference || strlen( $reference ) > 128 || wp_check_invalid_utf8( $reference ) !== $reference ) {
			return false;
		}
		$source = null !== $this->row( $provider, $reference ) ? $provider : 'manual_' . $provider;
		if ( null === $this->row( $source, $reference ) ) {
			return false;
		}
		$this->forget( $source, $reference );
		return null === $this->row( $source, $reference );
	}

	/**
	 * Returns one consistent grouped snapshot of the active ninety-day dataset.
	 *
	 * Rates are fractions from zero to one; missing denominators return null.
	 * Unreviewed rows are pending and never treated as true positives/negatives.
	 * Manually reported cases are included explicitly and exposed separately.
	 *
	 * @since 0.2.0
	 * @return array<string,int|float|null> Reviewed counts, rates and period metadata.
	 */
	public function metrics(): array {
		$result = array(
			'tp'          => 0,
			'fp'          => 0,
			'fn'          => 0,
			'tn'          => 0,
			'reviewed'    => 0,
			'pending'     => 0,
			'manual_fn'   => 0,
			'precision'   => null,
			'recall'      => null,
			'accuracy'    => null,
			'period_days' => self::DAYS,
		);
		if ( ! current_user_can( 'manage_options' ) ) {
			return $result;
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT source,predicted_spam,actual_spam,COUNT(*) AS total FROM %i WHERE expires>%d GROUP BY source,predicted_spam,actual_spam', $wpdb->prefix . 'rktsky_antispam_feedback', time() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A single grouped snapshot avoids inconsistent independently adjusted bucket counters.
		foreach ( (array) $rows as $row ) {
			$count = (int) $row['total'];
			if ( null === $row['actual_spam'] ) {
				$result['pending'] += $count;
				continue;
			}
			$predicted           = 1 === (int) $row['predicted_spam'];
			$actual              = 1 === (int) $row['actual_spam'];
			$bucket              = $predicted ? ( $actual ? 'tp' : 'fp' ) : ( $actual ? 'fn' : 'tn' );
			$result[ $bucket ]  += $count;
			$result['reviewed'] += $count;
			if ( 'fn' === $bucket && str_starts_with( $row['source'], 'manual_' ) ) {
				$result['manual_fn'] += $count;
			}
		}
		$result['precision'] = $result['tp'] + $result['fp'] > 0 ? $result['tp'] / ( $result['tp'] + $result['fp'] ) : null;
		$result['recall']    = $result['tp'] + $result['fn'] > 0 ? $result['tp'] / ( $result['tp'] + $result['fn'] ) : null;
		$result['accuracy']  = $result['reviewed'] > 0 ? ( $result['tp'] + $result['tn'] ) / $result['reviewed'] : null;
		return $result;
	}

	/**
	 * Reads the label of a linked object for an authorized native moderation view.
	 *
	 * @since 0.2.0
	 * @param string     $source Tracked source category.
	 * @param int|string $id Native object ID or stable manual case reference.
	 * @return array{predicted_spam:bool,actual_spam:?bool,reviewed:bool}|null Active label or null.
	 */
	public function status( string $source, $id ): ?array {
		if ( ! $this->authorized( $source, $id ) ) {
			return null;
		}
		$row = $this->row( $source, $id );
		if ( null === $row ) {
			return null;
		}
		return array(
			'predicted_spam' => 1 === (int) $row['predicted_spam'],
			'actual_spam'    => null === $row['actual_spam'] ? null : 1 === (int) $row['actual_spam'],
			'reviewed'       => null !== $row['actual_spam'],
		);
	}

	/**
	 * Removes pending references and detaches reviewed outcomes from deleted objects.
	 *
	 * Deleting pending rows first makes a concurrent review either win before
	 * detachment or fail after deletion. Reviewed rows keep their original expiry;
	 * their old reference can no longer be looked up or labelled. Precise event
	 * timestamps are reduced to UTC-day buckets without extending retention.
	 *
	 * @since 0.2.0
	 * @param string     $source Tracked source category.
	 * @param int|string $id Native object ID or stable manual case reference.
	 * @return void
	 */
	public function forget( string $source, $id ): void {
		if ( 'gravityforms' === $source ) {
			$this->gravity_links[ (int) $id ] = false;
		}
		$ref = $this->reference( $source, $id );
		if ( '' === $ref ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'rktsky_antispam_feedback';
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE ref=%s AND actual_spam IS NULL', $table, $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes a source link rather than storing personal erasure identifiers.
		try {
			$random = bin2hex( random_bytes( 32 ) );
		} catch ( \Throwable $error ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE ref=%s', $table, $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Privacy takes precedence over retaining a metric when random detachment is unavailable.
			return;
		}
		$changed = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET ref=%s,linked=0,created=created-(created %% %d),expires=expires-(expires %% %d) WHERE ref=%s AND actual_spam IS NOT NULL', $table, $random, DAY_IN_SECONDS, DAY_IN_SECONDS, $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic detachment removes the old lookup and second-level timing without extending retention.
		if ( false === $changed ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE ref=%s', $table, $ref ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A failed detachment must not leave the source reference behind.
		}
	}

	/**
	 * Drops expired linked and detached observations in bounded hourly batches.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function cleanup(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires<=%d LIMIT 1000', $wpdb->prefix . 'rktsky_antispam_feedback', time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded expiry over an owned indexed metadata table.
	}

	/**
	 * Tracks an already persisted, rejected quarantine object without its contents.
	 *
	 * @since 0.2.0
	 * @param int $id New quarantine ID emitted after successful insertion.
	 * @return void
	 */
	public function track_quarantine( $id ): void {
		$this->track( 'quarantine', $id, true );
	}

	/**
	 * Detaches feedback when quarantine deletion, privacy erasure or expiry succeeds.
	 *
	 * @since 0.2.0
	 * @param int $id Deleted quarantine object ID.
	 * @return void
	 */
	public function forget_quarantine( $id ): void {
		$this->forget( 'quarantine', $id );
	}

	/**
	 * Detaches feedback only after native comment deletion actually succeeded.
	 *
	 * @since 0.2.0
	 * @param int $id Deleted native comment ID.
	 * @return void
	 */
	public function forget_comment( $id ): void {
		$this->forget( 'comments', $id );
	}

	/**
	 * Treats explicit native moderator spam/approval changes as human labels.
	 *
	 * Automatic/background decisions and unknown historical comments are not labels.
	 *
	 * @since 0.2.0
	 * @param string      $new_status New native comment status.
	 * @param string      $old_status Previous native comment status.
	 * @param \WP_Comment $comment Native comment object.
	 * @return void
	 */
	public function comment_review( string $new_status, string $old_status, $comment ): void {
		if ( $new_status === $old_status || ! isset( $comment->comment_ID ) || ! current_user_can( 'moderate_comments' ) || ! current_user_can( 'edit_comment', (int) $comment->comment_ID ) ) {
			return;
		}
		if ( 'spam' === $new_status ) {
			$this->review( 'comments', $comment->comment_ID, true );
		} elseif ( 'approved' === $new_status || ( 'spam' === $old_status && 'unapproved' === $new_status ) ) {
			$this->review( 'comments', $comment->comment_ID, false );
		}
	}

	/**
	 * Reviews tracked GF entries after an authorized native status change.
	 *
	 * Initial automatic spam assignment disables this GF hook. Background actors
	 * without entry-moderation capabilities cannot supply a human label here.
	 *
	 * @since 0.2.0
	 * @param int|string $id Native entry ID.
	 * @param string     $new_status Updated native status.
	 * @param string     $old_status Previous native status.
	 * @return void
	 */
	public function gravity_review( $id, string $new_status, string $old_status ): void {
		if ( $new_status === $old_status || ! $this->authorized( 'gravityforms', $id ) ) {
			return;
		}
		if ( 'spam' === $new_status ) {
			$this->review( 'gravityforms', $id, true );
		} elseif ( 'active' === $new_status && 'spam' === $old_status ) {
			$this->review( 'gravityforms', $id, false );
		}
	}

	/**
	 * Conservatively removes GF links before its native deletion attempt.
	 *
	 * GF exposes this event before deleting, so a failed deletion can lose its
	 * feedback reference; no provider payload or database row is changed here.
	 *
	 * @since 0.2.0
	 * @param int|string $id Native entry ID about to be deleted.
	 * @return void
	 */
	public function forget_gravity( $id ): void {
		$this->forget( 'gravityforms', $id );
	}

	/**
	 * Detaches a prediction when GF is about to change a stored field value.
	 *
	 * This also covers native privacy anonymization through update_entry_field.
	 * A later failed save may conservatively discard the reference. The value is
	 * returned unchanged and neither the old nor new content is retained.
	 *
	 * @since 0.2.0
	 * @param mixed       $value Proposed provider field value.
	 * @param array       $entry Current native entry values.
	 * @param object|null $field Native field definition, deliberately not inspected.
	 * @param array       $form Native form definition, deliberately not inspected.
	 * @param string      $input_id Native numeric field or compound-input ID.
	 * @return mixed Unmodified proposed provider value.
	 */
	public function gravity_field_update( $value, array $entry, $field, array $form, $input_id ) {
		$id = isset( $entry['id'] ) ? (int) $entry['id'] : 0;
		if ( $id < 1 || ! is_scalar( $input_id ) || 1 !== preg_match( '/^[1-9][0-9]*(?:\.[0-9]+)?$/D', (string) $input_id ) ) {
			return $value;
		}
		$previous = $entry[ (string) $input_id ] ?? '';
		if ( is_scalar( $previous ) && is_scalar( $value ) && (string) $previous === (string) $value ) {
			return $value;
		}
		if ( ! array_key_exists( $id, $this->gravity_links ) ) {
			$this->gravity_links[ $id ] = null !== $this->row( 'gravityforms', $id );
		}
		if ( $this->gravity_links[ $id ] ) {
			$this->forget( 'gravityforms', $id );
		}
		return $value;
	}

	/**
	 * Handles GFAPI full-entry edits, including removed fields and status changes.
	 *
	 * Content changes detach the original prediction before a status label can
	 * describe a different message. A status-only update keeps the original link.
	 *
	 * @since 0.2.0
	 * @param array $entry Updated native entry.
	 * @param array $original Original native entry before the update.
	 * @return void
	 */
	public function gravity_entry_update( array $entry, array $original ): void {
		$id = $entry['id'] ?? 0;
		foreach ( array_unique( array_merge( array_keys( $entry ), array_keys( $original ) ) ) as $key ) {
			if ( 1 === preg_match( '/^[1-9][0-9]*(?:\.[0-9]+)?$/D', (string) $key ) && ( $entry[ $key ] ?? '' ) !== ( $original[ $key ] ?? '' ) ) {
				$this->forget( 'gravityforms', $id );
				return;
			}
		}
		$this->gravity_review( $id, (string) ( $entry['status'] ?? '' ), (string) ( $original['status'] ?? '' ) );
	}

	/**
	 * Checks native entry-detail edits that do not use the full GFAPI update path.
	 *
	 * @since 0.2.0
	 * @param array      $form Native form definition, retained only by the caller.
	 * @param int|string $id Updated native entry ID.
	 * @param array      $original Entry before the native detail edit.
	 * @return void
	 */
	public function gravity_detail_update( array $form, $id, array $original ): void {
		if ( ! is_callable( array( '\GFAPI', 'get_entry' ) ) ) {
			return;
		}
		$entry = \GFAPI::get_entry( $id );
		if ( is_array( $entry ) ) {
			$this->gravity_entry_update( $entry, $original );
		}
	}

	/**
	 * Appends an exporter to WordPress's confirmed personal-data workflow.
	 *
	 * @since 0.2.0
	 * @param array $exporters Existing native and plugin exporters.
	 * @return array Exporters including linked comment classification metadata.
	 */
	public function exporters( array $exporters ): array {
		$exporters['rktsky-antispam-feedback'] = array(
			'exporter_friendly_name' => __( 'WP Antispam reviewed decisions', 'wp-antispam' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Prepends erasure so native comment email anonymization cannot hide references.
	 *
	 * @since 0.2.0
	 * @param array $erasers Existing native and plugin erasers.
	 * @return array Erasers with this callback before Core's comment anonymization.
	 */
	public function erasers( array $erasers ): array {
		return array(
			'rktsky-antispam-feedback' => array(
				'eraser_friendly_name' => __( 'WP Antispam reviewed decisions', 'wp-antispam' ),
				'callback'             => array( $this, 'erase' ),
			),
		) + $erasers;
	}

	/**
	 * Exports active labels associated through native subject-identification settings.
	 *
	 * The original local HMAC reference is never exported. Quarantine content and
	 * its deletion are handled by its separate encrypted personal-data integration.
	 *
	 * @since 0.2.0
	 * @param string $email_address Confirmed subject email from WordPress.
	 * @param int    $page Native page number, starting at one.
	 * @return array|\WP_Error Native export data or a capability error.
	 */
	public function export( string $email_address, int $page = 1 ) {
		if ( ! current_user_can( 'export_others_personal_data' ) ) {
			return new \WP_Error( 'rktsky_antispam_feedback_permission', __( 'You cannot export this personal data.', 'wp-antispam' ) );
		}
		$sources = array(
			'comments'     => $this->subject_comments( $email_address, $page ),
			'gravityforms' => $this->subject_gravity_entries( $email_address, $page ),
		);
		$data    = array();
		foreach ( $sources as $source => $ids ) {
			foreach ( $ids as $id ) {
				$row = $this->row( $source, $id );
				if ( null === $row ) {
					continue;
				}
				$data[] = array(
					'group_id'    => 'rktsky-antispam-feedback',
					'group_label' => __( 'WP Antispam reviewed decisions', 'wp-antispam' ),
					'item_id'     => $source . '-' . (int) $id,
					'data'        => array(
						array(
							'name'  => __( 'Predicted spam', 'wp-antispam' ),
							'value' => (string) (int) $row['predicted_spam'],
						),
						array(
							'name'  => __( 'Human review', 'wp-antispam' ),
							'value' => null === $row['actual_spam'] ? __( 'Not reviewed', 'wp-antispam' ) : (string) (int) $row['actual_spam'],
						),
					),
				);
			}
		}
		return array(
			'data' => $data,
			'done' => count( $sources['comments'] ) < 50 && count( $sources['gravityforms'] ) < 50,
		);
	}

	/**
	 * Removes or detaches subject-linked feedback before native anonymization.
	 *
	 * Native comments are not deleted here, so an offset over matching comments is
	 * stable while this particular eraser completes all of its pages.
	 *
	 * @since 0.2.0
	 * @param string $email_address Confirmed subject email from WordPress.
	 * @param int    $page Native page number, starting at one.
	 * @return array|\WP_Error Native erasure result or a capability error.
	 */
	public function erase( string $email_address, int $page = 1 ) {
		if ( ! current_user_can( 'erase_others_personal_data' ) ) {
			return new \WP_Error( 'rktsky_antispam_feedback_permission', __( 'You cannot erase this personal data.', 'wp-antispam' ) );
		}
		$sources  = array(
			'comments'     => $this->subject_comments( $email_address, $page ),
			'gravityforms' => $this->subject_gravity_entries( $email_address, $page ),
		);
		$removed  = false;
		$retained = false;
		foreach ( $sources as $source => $ids ) {
			foreach ( $ids as $id ) {
				if ( null !== $this->row( $source, $id ) ) {
					$this->forget( $source, $id );
					$remaining = null !== $this->row( $source, $id );
					$retained  = $retained || $remaining;
					$removed   = $removed || ! $remaining;
				}
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => array(),
			'done'           => count( $sources['comments'] ) < 50 && count( $sources['gravityforms'] ) < 50,
		);
	}

	/**
	 * Reads one metadata row by a source-derived pseudonym.
	 *
	 * @since 0.2.0
	 * @param string     $source Supported source category.
	 * @param int|string $id Original native ID or stable manual case reference.
	 * @return array|null Active linked metadata, or null for missing/expired data.
	 */
	private function row( string $source, $id ): ?array {
		$ref = $this->reference( $source, $id );
		if ( '' === $ref ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT predicted_spam,actual_spam FROM %i WHERE ref=%s AND linked=1 AND expires>%d', $wpdb->prefix . 'rktsky_antispam_feedback', $ref, time() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Direct bounded source lookup must reflect moderation and deletion immediately.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Maps one confirmed email to at most fifty existing native comment IDs.
	 *
	 * @since 0.2.0
	 * @param string $email Confirmed subject email from the native privacy workflow.
	 * @param int    $page Positive native privacy page.
	 * @return int[] Native IDs; no email address is retained by this service.
	 */
	private function subject_comments( string $email, int $page ): array {
		if ( ! is_email( $email ) || $page < 1 ) {
			return array();
		}
		return array_map(
			'intval',
			get_comments(
				array(
					'author_email' => $email,
					'status'       => array( 'all', 'spam', 'trash', 'post-trashed' ),
					'type'         => 'all',
					'number'       => 50,
					'offset'       => ( $page - 1 ) * 50,
					'fields'       => 'ids',
					'orderby'      => 'comment_ID',
					'order'        => 'ASC',
				)
			)
		);
	}

	/**
	 * Finds at most fifty GF IDs through each form's native privacy configuration.
	 *
	 * Unlike GF_Personal_Data::get_entries this fetches paginated IDs instead of
	 * loading every matching entry payload before slicing. Native privacy-enabled
	 * forms identify subjects using an email field or their registered user ID.
	 * The public GFAPI loads active, inactive and trashed form definitions without
	 * depending on GF's lazily loaded personal-data callback implementation.
	 * Forms without that mapping cannot be identified by email here; an authorized
	 * administrator can still detach a known native ID through forget_manual.
	 *
	 * @since 0.2.0
	 * @param string $email Confirmed subject email from WordPress.
	 * @param int    $page Positive privacy page number.
	 * @return int[] Native GF entry IDs without retained field values.
	 */
	private function subject_gravity_entries( string $email, int $page ): array {
		if ( ! is_email( $email ) || $page < 1 || ! is_callable( array( '\GFAPI', 'get_forms' ) ) || ! is_callable( array( '\GFAPI', 'get_entry_ids' ) ) ) {
			return array();
		}
		$forms = \GFAPI::get_forms( null, null );
		if ( ! is_array( $forms ) ) {
			return array();
		}
		$offset = ( $page - 1 ) * 50;
		$ids    = array();
		$user   = get_user_by( 'email', $email );
		ksort( $forms, SORT_NUMERIC );
		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) || ! isset( $form['id'] ) || ( ! is_int( $form['id'] ) && ! is_string( $form['id'] ) ) ) {
				continue;
			}
			$form_id = filter_var( $form['id'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
			$privacy = $form['personalData']['exportingAndErasing'] ?? array();
			if ( false === $form_id || ! is_array( $privacy ) || empty( $privacy['enabled'] ) ) {
				continue;
			}
			$key = $privacy['identificationField'] ?? '';
			if ( ! is_int( $key ) && ! is_string( $key ) ) {
				continue;
			}
			$field = \GFAPI::get_field( $form, $key );
			if ( is_object( $field ) && is_callable( array( $field, 'get_input_type' ) ) && 'email' === $field->get_input_type() ) {
				$value = $email;
			} elseif ( 'created_by' === $key && $user ) {
				$value = (string) $user->ID;
			} else {
				continue;
			}
			$criteria = array(
				'field_filters' => array(
					array(
						'key'      => $key,
						'value'    => $value,
						'operator' => 'is',
					),
				),
			);
			$count    = \GFAPI::count_entries( $form_id, $criteria );
			if ( ( ! is_int( $count ) && ! is_string( $count ) ) || 1 !== preg_match( '/^[0-9]+$/D', (string) $count ) ) {
				continue;
			}
			$count = (int) $count;
			if ( $offset >= $count ) {
				$offset -= $count;
				continue;
			}
			$matches = \GFAPI::get_entry_ids(
				$form_id,
				$criteria,
				array(
					'key'        => 'id',
					'direction'  => 'ASC',
					'is_numeric' => true,
				),
				array(
					'offset'    => $offset,
					'page_size' => 50 - count( $ids ),
				)
			);
			if ( ! is_array( $matches ) ) {
				continue;
			}
			foreach ( array_slice( $matches, 0, 50 - count( $ids ) ) as $match ) {
				if ( ! is_int( $match ) && ! is_string( $match ) ) {
					continue;
				}
				$id = filter_var( $match, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
				if ( false !== $id ) {
					$ids[] = $id;
				}
			}
			$offset = 0;
			if ( count( $ids ) >= 50 ) {
				break;
			}
		}
		return $ids;
	}

	/**
	 * Authorizes labels through native moderator capabilities or site administration.
	 *
	 * @since 0.2.0
	 * @param string     $source Source category.
	 * @param int|string $id Original native object ID.
	 * @return bool Whether the current actor can review this source.
	 */
	private function authorized( string $source, $id ): bool {
		if ( 'comments' === $source ) {
			return current_user_can( 'moderate_comments' ) && current_user_can( 'edit_comment', (int) $id );
		}
		if ( 'gravityforms' === $source ) {
			return current_user_can( 'gravityforms_edit_entries' ) || current_user_can( 'manage_options' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Gravity Forms registers this native capability for editing and moderating stored entries.
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Derives a bounded, site- and source-separated local object reference.
	 *
	 * Key rotation intentionally makes earlier linked rows unaddressable until
	 * expiry; it does not turn arbitrary local references into anonymous data.
	 *
	 * @since 0.2.0
	 * @param string     $source Fixed supported source category.
	 * @param int|string $id Native object ID or stable manual case reference.
	 * @return string Hex HMAC, or empty when source/reference shape is invalid.
	 */
	private function reference( string $source, $id ): string {
		$native = in_array( $source, array_merge( Providers::ids(), array( 'quarantine' ) ), true );
		$manual = in_array( $source, array_map( static fn( $provider ) => 'manual_' . $provider, Providers::ids() ), true );
		if ( ( ! $native && ! $manual ) || ( ! is_int( $id ) && ! is_string( $id ) ) ) {
			return '';
		}
		$id = (string) $id;
		if ( '' === $id || strlen( $id ) > 128 || ( $native && 1 !== preg_match( '/^[1-9][0-9]{0,19}$/D', $id ) ) ) {
			return '';
		}
		return hash_hmac( 'sha256', get_current_blog_id() . '|' . $source . '|' . $id, wp_salt( 'auth' ) . '|rktsky-antispam-feedback|1' );
	}
}
