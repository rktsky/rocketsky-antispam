<?php
/**
 * Stores optional rejected form content in a bounded, encrypted local quarantine.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Separates optional content review from the guard's anonymous security state.
 *
 * Capture never changes an allow/reject decision. Read access is privileged,
 * expiry is enforced at read time, and storage failures do not expose plaintext.
 *
 * @since 0.2.0
 */
final class Quarantine {
	/**
	 * Validated per-site capture and retention settings.
	 *
	 * @since 0.2.0
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Receives settings without inspecting a submission or registering global state.
	 *
	 * @since 0.2.0
	 * @param Settings $settings Validated per-site settings.
	 * @return void
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Reports whether optional capture and native authenticated encryption are available.
	 *
	 * WordPress supplies the original Sodium compatibility layer when necessary.
	 *
	 * @since 0.2.0
	 * @return bool Whether adapters may prepare bounded content for capture.
	 */
	public function enabled(): bool {
		$settings = $this->settings->all();
		return empty( $settings['privacy_mode'] ) && ! empty( $settings['quarantine_enabled'] ) && $this->encryption_ready();
	}

	/**
	 * Reports availability of native or original Core-provided authenticated encryption.
	 *
	 * @since 0.2.0
	 * @return bool Whether both required Sodium operations are available.
	 */
	public function encryption_ready(): bool {
		return function_exists( 'sodium_crypto_secretbox' ) && function_exists( 'sodium_crypto_secretbox_open' );
	}

	/**
	 * Creates only the per-site quarantine table through the native schema API.
	 *
	 * The unique slot index makes concurrent inserts compete for one of exactly
	 * five hundred slots instead of relying on a non-atomic row-count check.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table     = $wpdb->prefix . 'rktsky_antispam_quarantine';
		$collation = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slot smallint(5) unsigned NOT NULL,
			provider varchar(20) NOT NULL,
			form_id varchar(64) NOT NULL,
			reason varchar(32) NOT NULL,
			created bigint(20) unsigned NOT NULL,
			expires bigint(20) unsigned NOT NULL,
			payload longtext NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slot (slot),
			KEY expires (expires)
		) $collation;"
		);
	}

	/**
	 * Registers callbacks for WordPress's confirmed personal-data workflows.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	/**
	 * Captures adapter-approved plaintext only when the site explicitly enabled it.
	 *
	 * At most thirty fields, four KiB per value, and sixteen KiB of combined
	 * labels/values are accepted. Ciphertext carries the authenticated metadata.
	 * IP addresses, uploads, technical controls, and raw request dumps are not inputs.
	 * All failures leave the guard's original rejection unchanged.
	 *
	 * @since 0.2.0
	 * @param string               $provider Known native integration.
	 * @param string               $form_id Bounded native form identifier.
	 * @param string               $reason Fixed local or native-spam reason code.
	 * @param array<string,string> $fields Explicitly allowlisted text fields.
	 * @param string               $email Optional subject email, encrypted with the fields.
	 * @return void
	 */
	public function capture( string $provider, string $form_id, string $reason, array $fields, string $email = '' ): void {
		if ( ! $this->enabled() || ! $this->valid_scope( $provider, $form_id, $reason ) ) {
			return;
		}
		$this->cleanup();
		global $wpdb;
		$occupied = $wpdb->get_col( $wpdb->prepare( 'SELECT slot FROM %i LIMIT 500', $wpdb->prefix . 'rktsky_antispam_quarantine' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read of bounded security storage must reflect concurrent inserts; an object cache would weaken the limit.
		if ( ! is_array( $occupied ) || '' !== $wpdb->last_error ) {
			return;
		}
		$free = array_values( array_diff( range( 0, 499 ), array_map( 'intval', $occupied ) ) );
		if ( empty( $free ) ) {
			return;
		}
		$bounded = $this->bounded_fields( $fields );
		$email   = $this->email( $email );
		$created = time();
		$days    = max( 1, min( 30, (int) ( $this->settings->all()['quarantine_days'] ?? 7 ) ) );
		$expires = $created + $days * DAY_IN_SECONDS;
		$context = array(
			'provider' => $provider,
			'form_id'  => $form_id,
			'reason'   => $reason,
			'created'  => $created,
			'expires'  => $expires,
		);
		$plain   = wp_json_encode(
			array(
				'version' => 1,
				'context' => $context,
				'email'   => $email,
				'fields'  => $bounded,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
		if ( ! is_string( $plain ) || strlen( $plain ) > 34000 ) {
			return;
		}
		try {
			$nonce   = random_bytes( 24 );
			$payload = base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $this->key() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes authenticated ciphertext for a bounded text database column.
		} catch ( \Throwable $error ) {
			return;
		}
		unset( $plain );
		for ( $attempt = 0; $attempt < 3 && ! empty( $free ); ++$attempt ) {
			try {
				$index = random_int( 0, count( $free ) - 1 );
			} catch ( \Throwable $error ) {
				return;
			}
			$slot = $free[ $index ];
			unset( $free[ $index ] );
			$free     = array_values( $free );
			$inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (slot, provider, form_id, reason, created, expires, payload) VALUES (%d, %s, %s, %s, %d, %d, %s)', $wpdb->prefix . 'rktsky_antispam_quarantine', $slot, $provider, $form_id, $reason, $created, $expires, $payload ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The unique fixed slot atomically bounds concurrent captures; no equivalent Core storage API exists.
			if ( 1 === $inserted ) {
				/**
				 * Fires after an encrypted optional quarantine record was stored.
				 *
				 * @since 0.2.0
				 * @param int $id Newly stored local record ID; no submitted fields are passed.
				 */
				do_action( 'rktsky_antispam_quarantined', (int) $wpdb->insert_id );
				return;
			}
			if ( false === $inserted ) {
				return;
			}
		}
	}

	/**
	 * Lists at most twenty unexpired metadata rows for an administrator.
	 *
	 * Ciphertext and submission content are never included in the list query.
	 *
	 * @since 0.2.0
	 * @param int $page Requested metadata page, clamped to the bounded inventory.
	 * @return array{items:array,total:int,page:int,pages:int} Privileged metadata or an empty result.
	 */
	public function listing( int $page = 1 ): array {
		$result = array(
			'items' => array(),
			'total' => 0,
			'page'  => 1,
			'pages' => 0,
		);
		if ( ! current_user_can( 'manage_options' ) ) {
			return $result;
		}
		$total = $this->count();
		$pages = (int) ceil( $total / 20 );
		$page  = max( 1, min( max( 1, $pages ), $page ) );
		global $wpdb;
		$items = $wpdb->get_results( $wpdb->prepare( 'SELECT id, provider, form_id, reason, created, expires FROM %i WHERE expires > %d ORDER BY created DESC, id DESC LIMIT 20 OFFSET %d', $wpdb->prefix . 'rktsky_antispam_quarantine', time(), ( $page - 1 ) * 20 ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Privileged bounded metadata must reflect deletion and expiry immediately.
		return array(
			'items' => array_map( array( $this, 'metadata' ), (array) $items ),
			'total' => $total,
			'page'  => $page,
			'pages' => $pages,
		);
	}

	/**
	 * Reads and decrypts one unexpired row only for a site administrator.
	 *
	 * Key rotation or ciphertext corruption returns unreadable metadata, never
	 * unauthenticated plaintext. Such a row remains deletable by its administrator.
	 *
	 * @since 0.2.0
	 * @param int $id Positive quarantine entry ID.
	 * @return array|null Metadata, fields, encrypted subject readback and unreadable flag; null when inaccessible.
	 */
	public function read( int $id ): ?array {
		if ( $id < 1 || ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND expires > %d LIMIT 1', $wpdb->prefix . 'rktsky_antispam_quarantine', $id, time() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Privileged single-row read requires current expiry and deletion state.
		if ( ! is_array( $row ) ) {
			return null;
		}
		$plain                = $this->decrypt( $row );
		$result               = $this->metadata( $row );
		$result['fields']     = false === $plain ? array() : $plain['fields'];
		$result['email']      = false === $plain ? '' : $plain['email'];
		$result['unreadable'] = false === $plain;
		return $result;
	}

	/**
	 * Deletes exactly one entry after the controller's independent CSRF check.
	 *
	 * This repository also enforces capability, but intentionally accepts no nonce:
	 * Core privacy erasure uses its own confirmed request rather than an admin form.
	 *
	 * @since 0.2.0
	 * @param int $id Positive quarantine entry ID.
	 * @return bool Whether an authorized request deleted exactly one existing row.
	 */
	public function delete( int $id ): bool {
		if ( $id < 1 || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		global $wpdb;
		$deleted = $wpdb->delete( $wpdb->prefix . 'rktsky_antispam_quarantine', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Explicit deletion of one owned record must take effect immediately.
		if ( 1 === $deleted ) {
			$this->notify_deleted( $id );
		}
		return 1 === $deleted;
	}

	/**
	 * Counts the bounded unexpired inventory without exposing content to other roles.
	 *
	 * @since 0.2.0
	 * @return int At most five hundred visible entries, or zero without permission.
	 */
	public function count(): int {
		if ( ! current_user_can( 'manage_options' ) ) {
			return 0;
		}
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE expires > %d', $wpdb->prefix . 'rktsky_antispam_quarantine', time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded inventory must reflect current expiry and deletions.
	}

	/**
	 * Removes expired ciphertext and shortens existing retention after a setting change.
	 *
	 * Physical deletion depends on the site's scheduled cleanup or a later capture;
	 * privileged reads independently hide every expired entry immediately.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function cleanup(): void {
		global $wpdb;
		$days = max( 1, min( 30, (int) ( $this->settings->all()['quarantine_days'] ?? 7 ) ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET expires = LEAST(expires, created + %d) WHERE expires > created + %d', $wpdb->prefix . 'rktsky_antispam_quarantine', $days * DAY_IN_SECONDS, $days * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Shorter local retention must affect existing records without a stale cache.
		$now     = time();
		$expired = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE expires <= %d LIMIT 500', $wpdb->prefix . 'rktsky_antispam_quarantine', $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded IDs let dependent metadata expire with the actual content record.
		$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires <= %d', $wpdb->prefix . 'rktsky_antispam_quarantine', $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes expired ciphertext from a table with at most five hundred rows.
		if ( is_int( $deleted ) && $deleted > 0 ) {
			foreach ( (array) $expired as $id ) {
				$this->notify_deleted( (int) $id );
			}
		}
	}

	/**
	 * Registers the fixed exporter identifier used by the native privacy workflow.
	 *
	 * @since 0.2.0
	 * @param array $exporters Previously registered native/plugin exporters.
	 * @return array Exporters with this plugin's callback appended.
	 */
	public function exporters( array $exporters ): array {
		$exporters['rktsky-antispam-quarantine'] = array(
			'exporter_friendly_name' => __( 'WP Antispam quarantine', 'wp-antispam' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Registers the fixed eraser identifier used by the native privacy workflow.
	 *
	 * @since 0.2.0
	 * @param array $erasers Previously registered native/plugin erasers.
	 * @return array Erasers with this plugin's callback appended.
	 */
	public function erasers( array $erasers ): array {
		$erasers['rktsky-antispam-quarantine'] = array(
			'eraser_friendly_name' => __( 'WP Antispam quarantine', 'wp-antispam' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Exports matching encrypted email subjects from one stable fifty-slot range.
	 *
	 * Stable slot ranges avoid offset skipping when erasure deletes earlier rows.
	 * The ten-page bound follows the fixed storage limit rather than row counts.
	 *
	 * @since 0.2.0
	 * @param string $email_address Email confirmed by WordPress's privacy workflow.
	 * @param int    $page Native page number, starting at one.
	 * @return array|\WP_Error Native export page or a capability error.
	 */
	public function export( string $email_address, int $page = 1 ) {
		if ( ! current_user_can( 'export_others_personal_data' ) ) {
			return new \WP_Error( 'rktsky_antispam_privacy_permission', __( 'You cannot export this personal data.', 'wp-antispam' ) );
		}
		$email = $this->email( $email_address );
		$data  = array();
		if ( '' === $email || $page < 1 || $page > 10 ) {
			return array(
				'data' => $data,
				'done' => true,
			);
		}
		foreach ( $this->privacy_rows( $page ) as $row ) {
			$plain = $this->decrypt( $row );
			if ( false === $plain || '' === $plain['email'] || ! hash_equals( $email, $plain['email'] ) ) {
				continue;
			}
			$items = array(
				array(
					'name'  => __( 'Form provider', 'wp-antispam' ),
					'value' => $row['provider'],
				),
				array(
					'name'  => __( 'Form', 'wp-antispam' ),
					'value' => $row['form_id'],
				),
				array(
					'name'  => __( 'Date', 'wp-antispam' ),
					'value' => gmdate( 'c', (int) $row['created'] ),
				),
				array(
					'name'  => __( 'Email address', 'wp-antispam' ),
					'value' => $plain['email'],
				),
			);
			foreach ( $plain['fields'] as $label => $value ) {
				$items[] = array(
					'name'  => (string) $label,
					'value' => esc_html( $value ),
				);
			}
			$data[] = array(
				'group_id'          => 'rktsky-antispam-quarantine',
				'group_label'       => __( 'WP Antispam quarantine', 'wp-antispam' ),
				'group_description' => __( 'Rejected form submissions temporarily stored by this site.', 'wp-antispam' ),
				'item_id'           => 'rktsky-antispam-quarantine-' . (int) $row['id'],
				'data'              => $items,
			);
		}
		return array(
			'data' => $data,
			'done' => $page >= 10,
		);
	}

	/**
	 * Erases matching email subjects in a stable range without deleting other records.
	 *
	 * Unreadable ciphertext cannot be attributed after key rotation; it is retained
	 * for manual administrator deletion or expiry and reported to the workflow.
	 *
	 * @since 0.2.0
	 * @param string $email_address Email confirmed by WordPress's privacy workflow.
	 * @param int    $page Native page number, starting at one.
	 * @return array|\WP_Error Native erasure result or a capability error.
	 */
	public function erase( string $email_address, int $page = 1 ) {
		if ( ! current_user_can( 'erase_others_personal_data' ) ) {
			return new \WP_Error( 'rktsky_antispam_privacy_permission', __( 'You cannot erase this personal data.', 'wp-antispam' ) );
		}
		$result = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $page >= 10,
		);
		$email  = $this->email( $email_address );
		if ( '' === $email || $page < 1 || $page > 10 ) {
			$result['done'] = true;
			return $result;
		}
		global $wpdb;
		foreach ( $this->privacy_rows( $page ) as $row ) {
			$plain = $this->decrypt( $row );
			if ( false === $plain ) {
				$result['items_retained'] = true;
				continue;
			}
			if ( '' !== $plain['email'] && hash_equals( $email, $plain['email'] ) ) {
				$deleted                  = $wpdb->delete( $wpdb->prefix . 'rktsky_antispam_quarantine', array( 'id' => (int) $row['id'] ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Core-authorized privacy erasure deletes only a matching owned entry.
				$result['items_removed']  = $result['items_removed'] || 1 === $deleted;
				$result['items_retained'] = $result['items_retained'] || 1 !== $deleted;
				if ( 1 === $deleted ) {
					$this->notify_deleted( (int) $row['id'] );
				}
			}
		}
		if ( $result['items_retained'] ) {
			$result['messages'][] = __( 'Some quarantine data could not be erased automatically. An administrator must review unreadable or unavailable records.', 'wp-antispam' );
		}
		return $result;
	}

	/**
	 * Reads ciphertext only for the current fixed privacy slot interval.
	 *
	 * @since 0.2.0
	 * @param int $page Validated privacy page between one and ten.
	 * @return array Native rows, bounded to fifty and excluding expired content.
	 */
	private function privacy_rows( int $page ): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE slot >= %d AND slot < %d AND expires > %d ORDER BY slot ASC LIMIT 50', $wpdb->prefix . 'rktsky_antispam_quarantine', ( $page - 1 ) * 50, $page * 50, time() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Confirmed privacy workflows use stable bounded ranges so deletion never skips an offset page.
	}

	/**
	 * Authenticates ciphertext and its original row context before returning content.
	 *
	 * @since 0.2.0
	 * @param array $row Complete quarantine row from this site's table.
	 * @return array{email:string,fields:array}|false Verified content or false on corruption/key rotation.
	 */
	private function decrypt( array $row ) {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) || ! isset( $row['payload'] ) || ! is_string( $row['payload'] ) || strlen( $row['payload'] ) > 46000 ) {
			return false;
		}
		$binary = base64_decode( $row['payload'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes authenticated ciphertext, not executable code.
		if ( false === $binary || strlen( $binary ) < 40 || base64_encode( $binary ) !== $row['payload'] ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Canonical ciphertext framing rejects alternate encodings.
			return false;
		}
		try {
			$plain = sodium_crypto_secretbox_open( substr( $binary, 24 ), substr( $binary, 0, 24 ), $this->key() );
		} catch ( \Throwable $error ) {
			return false;
		}
		if ( ! is_string( $plain ) || strlen( $plain ) > 34000 ) {
			return false;
		}
		$data = json_decode( $plain, true, 8 );
		unset( $plain );
		if ( ! is_array( $data ) || 1 !== ( $data['version'] ?? null ) || ! is_array( $data['context'] ?? null ) || ! is_array( $data['fields'] ?? null ) || ! is_string( $data['email'] ?? null ) ) {
			return false;
		}
		foreach ( array( 'provider', 'form_id', 'reason', 'created' ) as $key ) {
			if ( ! isset( $row[ $key ], $data['context'][ $key ] ) || (string) $row[ $key ] !== (string) $data['context'][ $key ] ) {
				return false;
			}
		}
		// Retention can legitimately be shortened; it may never extend the authenticated expiry.
		if ( ! isset( $data['context']['expires'] ) || (int) $row['expires'] > (int) $data['context']['expires'] || $this->bounded_fields( $data['fields'] ) !== $data['fields'] || $this->email( $data['email'] ) !== $data['email'] ) {
			return false;
		}
		return array(
			'email'  => $data['email'],
			'fields' => $data['fields'],
		);
	}

	/**
	 * Derives a site- and purpose-separated thirty-two-byte encryption key.
	 *
	 * @since 0.2.0
	 * @return string Binary key; a changed WordPress auth salt invalidates old ciphertext.
	 */
	private function key(): string {
		return hash_hmac( 'sha256', 'rktsky-antispam|quarantine|1|' . get_current_blog_id(), wp_salt( 'auth' ), true );
	}

	/**
	 * Copies only a fixed amount of valid UTF-8 text into the encrypted payload.
	 *
	 * @since 0.2.0
	 * @param array $fields Adapter-approved textual fields.
	 * @return array<string,string> Bounded labels and text values.
	 */
	private function bounded_fields( array $fields ): array {
		$result = array();
		$budget = 16384;
		$seen   = 0;
		foreach ( $fields as $label => $value ) {
			if ( ++$seen > 30 || $budget < 2 ) {
				break;
			}
			if ( ! is_string( $label ) || ! is_string( $value ) ) {
				continue;
			}
			$label = $this->clip( $label, min( 160, $budget - 1 ) );
			if ( '' === $label || isset( $result[ $label ] ) ) {
				continue;
			}
			$value            = $this->clip( $value, min( 4096, $budget - strlen( $label ) ) );
			$result[ $label ] = $value;
			$budget          -= strlen( $label ) + strlen( $value );
		}
		return $result;
	}

	/**
	 * Truncates byte length while preserving complete UTF-8 characters.
	 *
	 * @since 0.2.0
	 * @param string $value Candidate plaintext; markup is preserved for later contextual escaping.
	 * @param int    $bytes Maximum positive byte count.
	 * @return string Valid UTF-8 text or an empty string for invalid source bytes.
	 */
	private function clip( string $value, int $bytes ): string {
		$value = substr( $value, 0, max( 0, $bytes ) );
		for ( $attempt = 0; $attempt < 4; ++$attempt ) {
			if ( wp_check_invalid_utf8( $value ) === $value ) {
				return $value;
			}
			$value = substr( $value, 0, -1 );
		}
		return '';
	}

	/**
	 * Normalizes only a valid bounded email for native subject matching.
	 *
	 * @since 0.2.0
	 * @param string $email Optional adapter-selected email value.
	 * @return string Lowercase valid email or an empty non-matchable subject.
	 */
	private function email( string $email ): string {
		if ( strlen( $email ) > 254 ) {
			return '';
		}
		$email = strtolower( trim( $email ) );
		return is_email( $email ) ? $email : '';
	}

	/**
	 * Restricts stored metadata to fixed protocol identifiers rather than user text.
	 *
	 * @since 0.2.0
	 * @param string $provider Native provider identifier.
	 * @param string $form_id Native form identifier.
	 * @param string $reason Local or provider-spam reason.
	 * @return bool Whether metadata is safe and meaningful for this quarantine.
	 */
	private function valid_scope( string $provider, string $form_id, string $reason ): bool {
		return in_array( $provider, array( 'comments', 'cf7', 'gravityforms', 'wpforms' ), true ) && 1 === preg_match( '/^[a-zA-Z0-9_-]{1,64}$/D', $form_id ) && in_array( $reason, array( 'missing', 'invalid', 'honeypot', 'too_fast', 'replay', 'pingback', 'storage', 'native_spam', 'keyword', 'bbcode', 'pattern', 'pattern_limit', 'language', 'country' ), true );
	}

	/**
	 * Signals actual content deletion without passing personal data to dependents.
	 *
	 * @since 0.2.0
	 * @param int $id Deleted quarantine record ID.
	 * @return void
	 */
	private function notify_deleted( int $id ): void {
		/**
		 * Fires after manual deletion, privacy erasure, or retention removed a record.
		 *
		 * @since 0.2.0
		 * @param int $id Deleted local record ID; dependent anonymous totals may remain.
		 */
		do_action( 'rktsky_antispam_quarantine_deleted', $id );
	}

	/**
	 * Converts public metadata types without including ciphertext or subject data.
	 *
	 * @since 0.2.0
	 * @param array $row Native metadata row.
	 * @return array Typed fixed metadata for administrator templates.
	 */
	public function metadata( array $row ): array {
		return array(
			'id'       => (int) $row['id'],
			'provider' => (string) $row['provider'],
			'form_id'  => (string) $row['form_id'],
			'reason'   => (string) $row['reason'],
			'created'  => (int) $row['created'],
			'expires'  => (int) $row['expires'],
		);
	}
}
