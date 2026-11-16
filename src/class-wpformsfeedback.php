<?php
/**
 * Connects observed WPForms Pro entries to native moderation and privacy tools.
 *
 * @package RktskyAntispam
 * @since 0.5.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Uses provider entry APIs without copying messages or importing old observations.
 *
 * @since 0.5.0
 */
final class WpFormsFeedback {
	/**
	 * Shared owned ledger, separate from native WPForms entry storage.
	 *
	 * @since 0.5.0
	 * @var Feedback
	 */
	private Feedback $feedback;

	/**
	 * Request-local changed entries awaiting the corresponding completed update.
	 *
	 * @since 0.5.0
	 * @var array<int,bool>
	 */
	private array $changed = array();

	/**
	 * Retains native privacy pagination independently of sparse ledger matches.
	 *
	 * @since 0.5.0
	 * @var bool
	 */
	private bool $more = false;

	/**
	 * Shares the existing feedback service without accessing provider data at boot.
	 *
	 * @since 0.5.0
	 * @param Feedback $feedback Owned prediction metadata service.
	 * @return void
	 */
	public function __construct( Feedback $feedback ) {
		$this->feedback = $feedback;
	}

	/**
	 * Registers native entry lifecycles and extends the existing privacy callbacks.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'wpforms_pro_anti_spam_entry_marked_as_spam', array( $this, 'spam' ), 10, 2 );
		add_action( 'wpforms_pro_anti_spam_entry_set_as_not_spam', array( $this, 'normal' ), 10, 2 );
		foreach ( array( 'entries', 'edit_entry' ) as $context ) {
			add_action( 'wpforms_pre_update_' . $context, array( $this, 'before_update' ), PHP_INT_MAX, 2 );
			add_action( 'wpforms_post_update_' . $context, array( $this, 'after_update' ), PHP_INT_MAX, 2 );
		}
		add_action( 'wpforms_pre_update_', array( $this, 'before_generic_update' ), PHP_INT_MAX, 2 );
		add_action( 'wpforms_post_update_', array( $this, 'after_generic_update' ), PHP_INT_MAX, 2 );
		add_action( 'wpforms_pre_delete_entries', array( $this, 'before_delete' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'before_form_delete' ), 10, 2 );
		add_filter( 'rktsky_antispam_feedback_can_review', array( $this, 'can_review' ), 10, 3 );
		add_filter( 'rktsky_antispam_feedback_subjects', array( $this, 'subjects' ), 10, 3 );
		add_filter( 'rktsky_antispam_feedback_subjects_done', array( $this, 'subjects_done' ) );
	}

	/**
	 * Labels a tracked observation after a successful native spam decision.
	 *
	 * The original helper can emit its hook after a failed update; the saved
	 * native status and form are therefore verified before recording a label.
	 *
	 * @since 0.5.0
	 * @param int $id Native entry selected by the moderator.
	 * @param int $form_id Native form owning the selected entry.
	 * @return void
	 */
	public function spam( $id, $form_id ): void {
		$this->moderate( $id, $form_id, true );
	}

	/**
	 * Corrects a tracked native spam observation after the provider restores it.
	 *
	 * @since 0.5.0
	 * @param int $id Native restored entry ID.
	 * @param int $form_id Native form owning that entry.
	 * @return void
	 */
	public function normal( $id, $form_id ): void {
		$this->moderate( $id, $form_id, false );
	}

	/**
	 * Confirms native state without changing entry content or provider transactions.
	 *
	 * @since 0.5.0
	 * @param int  $id Native entry ID.
	 * @param int  $form_id Expected native form.
	 * @param bool $spam Explicit human spam/not-spam decision.
	 * @return void
	 */
	private function moderate( $id, $form_id, bool $spam ): void {
		$api = $this->api();
		if ( ! $api || ! function_exists( 'wpforms_current_user_can' ) || ! wpforms_current_user_can( 'edit_entry_single', (int) $id ) ) {
			return;
		}
		$entry = $api->get( (int) $id );
		if ( is_object( $entry ) && (int) $entry->form_id === (int) $form_id && ( $spam ? 'spam' === $entry->status : '' === $entry->status ) ) {
			$this->feedback->review( 'wpforms', (int) $id, $spam );
		}
	}

	/**
	 * Keeps native object-level edit permissions for delegated entry moderators.
	 *
	 * @since 0.5.0
	 * @param bool       $allowed Existing site-administrator authorization.
	 * @param string     $source Fixed provider category.
	 * @param int|string $id Native entry ID.
	 * @return bool Existing authorization or native WPForms edit permission.
	 */
	public function can_review( bool $allowed, string $source, $id ): bool {
		return $allowed || ( 'wpforms' === $source && function_exists( 'wpforms_current_user_can' ) && wpforms_current_user_can( 'edit_entry_single', (int) $id ) );
	}

	/**
	 * Compares original content before the native editor or explicit entry update.
	 *
	 * Status, viewed/starred flags and timestamps do not invalidate a prediction.
	 * Values remain request-local and are never copied into metadata storage.
	 * The provider emits this hook after authorizing its update; its internal read
	 * option also covers legitimate background anonymization without a logged-in actor.
	 *
	 * @since 0.5.0
	 * @param array $data Proposed native columns.
	 * @param int   $id Original native row identifier.
	 * @return void
	 */
	public function before_update( array $data, $id ): void {
		$data = array_change_key_case( $data, CASE_LOWER );
		$keys = array_intersect( array_keys( $data ), array( 'entry_id', 'form_id', 'fields', 'meta', 'user_id', 'post_id', 'user_uuid', 'ip_address', 'user_agent' ) );
		$api  = $this->api();
		if ( ! $keys || ! $api ) {
			return;
		}
		$entry = $api->get( (int) $id, array( 'cap' => false ) );
		if ( ! is_object( $entry ) ) {
			return;
		}
		foreach ( $keys as $key ) {
			if ( ! is_scalar( $data[ $key ] ) || (string) ( $entry->{$key} ?? '' ) !== (string) $data[ $key ] ) {
				$this->changed[ (int) $id ] = true;
				break;
			}
		}
	}

	/**
	 * Detaches only after a native content update actually completed.
	 *
	 * @since 0.5.0
	 * @param array $data Completed native values, deliberately not retained.
	 * @param int   $id Original updated row ID.
	 * @return void
	 */
	public function after_update( array $data, $id ): void {
		if ( isset( $this->changed[ (int) $id ] ) ) {
			unset( $this->changed[ (int) $id ] );
			$this->feedback->forget( 'wpforms', (int) $id );
		}
	}

	/**
	 * Recognizes fields updates from the provider's empty default context only.
	 *
	 * Other provider tables share this generic hook but have no fields column.
	 *
	 * @since 0.5.0
	 * @param array $data Proposed native columns.
	 * @param int   $id Native row identifier.
	 * @return void
	 */
	public function before_generic_update( array $data, $id ): void {
		if ( array_key_exists( 'fields', array_change_key_case( $data, CASE_LOWER ) ) ) {
			$this->before_update( $data, $id );
		}
	}

	/**
	 * Completes only the matching generic fields-update lifecycle.
	 *
	 * @since 0.5.0
	 * @param array $data Completed native columns.
	 * @param int   $id Native row identifier.
	 * @return void
	 */
	public function after_generic_update( array $data, $id ): void {
		if ( array_key_exists( 'fields', array_change_key_case( $data, CASE_LOWER ) ) ) {
			$this->after_update( $data, $id );
		}
	}

	/**
	 * Detaches an exact entry or pages through a form-owned native bulk deletion.
	 *
	 * The post-delete hook omits its column name, so a form ID could otherwise be
	 * mistaken for an unrelated entry ID. A later failed native deletion may
	 * conservatively remove the link, as with the native GF pre-deletion contract.
	 *
	 * @since 0.5.0
	 * @param int|string $value Native column value selected for deletion.
	 * @param string     $column Actual native selector, entry_id or form_id.
	 * @return void
	 */
	public function before_delete( $value, string $column ): void {
		if ( 'entry_id' === $column ) {
			$this->feedback->forget( 'wpforms', (int) $value );
		} elseif ( 'form_id' === $column ) {
			$this->forget_form( (int) $value );
		}
	}

	/**
	 * Covers direct native WordPress deletion of a WPForms form post as well.
	 *
	 * @since 0.5.0
	 * @param int      $id Native form-post ID about to be deleted.
	 * @param \WP_Post $post Original WordPress post.
	 * @return void
	 */
	public function before_form_delete( $id, $post ): void {
		if ( $post instanceof \WP_Post && 'wpforms' === $post->post_type ) {
			$this->forget_form( (int) $id );
		}
	}

	/**
	 * Removes references in native bounded ID pages before their form disappears.
	 *
	 * @since 0.5.0
	 * @param int $form_id Existing native form ID.
	 * @return void
	 */
	private function forget_form( int $form_id ): void {
		$api = $this->api();
		if ( ! $api ) {
			return;
		}
		$offset = 0;
		do {
			$rows = $api->get_entries(
				array(
					'form_id' => $form_id,
					'status'  => 'any',
					'select'  => 'entry_ids',
					'number'  => 50,
					'offset'  => $offset,
					'order'   => 'ASC',
				)
			);
			foreach ( $rows as $entry ) {
				$this->feedback->forget( 'wpforms', (int) $entry->entry_id );
			}
			$more    = count( $rows ) >= 50;
			$offset += 50;
		} while ( $more );
	}

	/**
	 * Finds native IDs whose processed email field matches the privacy subject.
	 *
	 * The public entry API returns at most fifty approximate candidates, including
	 * native spam/trash. Native processed field types exclude an email merely
	 * mentioned in a text field. Only matching IDs leave this request-local lookup.
	 *
	 * @since 0.5.0
	 * @param array<string,int[]> $sources Existing native subject pages.
	 * @param string              $email Confirmed subject email from WordPress privacy tools.
	 * @param int                 $page Positive privacy page number.
	 * @return array<string,int[]> Existing sources and native WPForms subject IDs.
	 */
	public function subjects( array $sources, string $email, int $page ): array {
		$this->more = false;
		$api        = $this->api();
		if ( ! $api || ! is_email( $email ) || $page < 1 || ! ( current_user_can( 'export_others_personal_data' ) || current_user_can( 'erase_others_personal_data' ) ) ) {
			return $sources;
		}
		$rows               = $api->get_entries(
			array(
				'status'        => 'any',
				'field_id'      => 'any',
				'value'         => $email,
				'value_compare' => 'is',
				'number'        => 50,
				'offset'        => ( $page - 1 ) * 50,
				'order'         => 'ASC',
			)
		);
		$this->more         = count( $rows ) >= 50;
		$sources['wpforms'] = array();
		foreach ( $rows as $entry ) {
			$fields = is_string( $entry->fields ?? null ) ? json_decode( $entry->fields, true ) : array();
			foreach ( is_array( $fields ) ? $fields : array() as $field ) {
				if ( is_array( $field ) && 'email' === ( $field['type'] ?? '' ) && is_string( $field['value'] ?? null ) && 0 === strcasecmp( trim( $field['value'] ), $email ) ) {
					$sources['wpforms'][] = (int) $entry->entry_id;
					break;
				}
			}
		}
		return $sources;
	}

	/**
	 * Preserves the provider's result-page completion alongside all other sources.
	 *
	 * @since 0.5.0
	 * @param bool $done Whether all other subject mappings have completed.
	 * @return bool Whether this native subject page is also complete.
	 */
	public function subjects_done( bool $done ): bool {
		return $done && ! $this->more;
	}

	/**
	 * Returns the public Pro entry service without requiring an enabled adapter.
	 *
	 * Existing references stay accessible to privacy tools when protection is off.
	 * Lite has no corresponding native persisted-entry service.
	 *
	 * @since 0.5.0
	 * @return object|null Native entry API or null when unavailable.
	 */
	private function api(): ?object {
		if ( ! function_exists( 'wpforms' ) || ! class_exists( '\WPForms_Entry_Handler' ) ) {
			return null;
		}
		$entry = wpforms()->obj( 'entry' );
		return is_object( $entry ) && is_callable( array( $entry, 'get_entries' ) ) && is_callable( array( $entry, 'get' ) ) ? $entry : null;
	}
}
