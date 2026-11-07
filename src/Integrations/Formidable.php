<?php
/**
 * Protects final Formidable Forms submissions through native extension points.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

use Rktsky\Antispam\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Adds local proof without consuming it during Formidable's preliminary validation.
 *
 * @since 0.3.0
 */
final class Formidable extends Adapter {
	/**
	 * Request-local proof and persisted native IDs for this adapter's decisions.
	 *
	 * @since 0.5.0
	 * @var array<int,array{proof:string,entry:int,values:array,blocked:bool}>
	 */
	private array $native_spam = array();

	/**
	 * Checks the reviewed native queue API and its active add-on compatibility.
	 *
	 * @since 0.5.0
	 * @return bool Whether Formidable can safely store its native spam status.
	 */
	public static function native_available(): bool {
		return class_exists( '\FrmSpamEntriesHelper' ) && is_callable( array( '\FrmSpamEntriesHelper', 'maybe_flag_submission' ) ) && is_callable( array( '\FrmSpamEntriesHelper', 'can_store_spam' ) ) && \FrmSpamEntriesHelper::can_store_spam();
	}
	/**
	 * Registers inside-form rendering and the final native pre-creation gate.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'frm_entry_form', array( $this, 'render' ) );
		add_filter( 'frm_entries_before_create', array( $this, 'validate' ), PHP_INT_MAX, 2 );
		add_filter( 'frm_spam_entry_sources', array( $this, 'spam_source' ) );
		add_action( 'frm_after_create_entry', array( $this, 'created' ), PHP_INT_MAX, 2 );
		add_filter( 'frm_pre_create_entry', array( $this, 'native_values' ), PHP_INT_MAX );
		add_filter( 'frm_success_filter', array( $this, 'confirmation_method' ), PHP_INT_MAX, 3 );
		add_filter( 'frm_main_feedback', array( $this, 'confirmation_message' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Adds a documented native source while leaving the provider's handling choice editable.
	 *
	 * @since 0.5.0
	 * @param array $sources Existing native source definitions.
	 * @return array Sources including this adapter's content decisions.
	 */
	public function spam_source( array $sources ): array {
		$sources['rktsky_antispam'] = array(
			'label'   => 'WP Antispam',
			'default' => 'save',
		);
		return $sources;
	}

	/**
	 * Renders signed controls inside the native form, including AJAX replacements.
	 *
	 * @since 0.3.0
	 * @param object $form Native stored form definition.
	 * @return void
	 */
	public function render( $form ): void {
		if ( is_object( $form ) && isset( $form->id ) && is_numeric( $form->id ) && (int) $form->id > 0 ) {
			echo $this->fields->markup( 'formidable', (string) (int) $form->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shared renderer escapes its complete inside-form controls.
		}
	}

	/**
	 * Adds a native visible error before final persistence and entry actions.
	 *
	 * Earlier errors stay intact. Preliminary AJAX and multipage validation do
	 * not consume the token; the native processing gate performs the one check.
	 * Direct server-side FrmEntry::create calls are outside this browser adapter.
	 *
	 * @since 0.3.0
	 * @param mixed  $errors Earlier native errors, sometimes an empty legacy string.
	 * @param object $form Native stored form selected by the provider.
	 * @return mixed Earlier errors or a native array containing the local retry error.
	 */
	public function validate( $errors, $form ) {
		if ( ! empty( $errors ) || ! is_object( $form ) || ! isset( $form->id ) || ! is_numeric( $form->id ) || (int) $form->id < 1 ) {
			return $errors;
		}
		$id = (string) (int) $form->id;
		unset( $this->native_spam[ (int) $id ] );
		$posted = $this->posted();
		$data   = $this->content( $id, $posted );
		$reason = $this->guard->check( 'formidable', $id, $posted, $data['fields'] );
		if ( '' === $reason ) {
			if ( $this->settings->enabled( 'formidable' ) ) {
				$this->native_spam[ (int) $id ] = array(
					'proof'   => $this->fingerprint( $id, $posted ),
					'entry'   => 0,
					'values'  => array(),
					'blocked' => false,
				);
			}
			return $errors;
		}
		if ( Guard::is_content_reason( $reason ) && empty( $posted['id'] ) && 'update' !== ( $posted['frm_action'] ?? '' ) && self::native_available() && \FrmSpamEntriesHelper::maybe_flag_submission( (int) $id, 'rktsky_antispam', $reason ) ) {
			$this->native_spam[ (int) $id ] = array(
				'proof'   => $this->fingerprint( $id, $posted ),
				'entry'   => 0,
				'values'  => $data['values'],
				'blocked' => true,
			);
			return $errors;
		}
		$this->retain( 'formidable', $id, $reason, $data );
		$errors                    = is_array( $errors ) ? $errors : array();
		$errors['rktsky_antispam'] = Guard::is_content_reason( $reason ) ? $this->guard->spam_message() : $this->guard->message();
		return $errors;
	}

	/**
	 * Keeps undeclared and sensitive submitted metadata out of our native spam record.
	 *
	 * Native create accepts arbitrary item_meta keys, so this protected lifecycle
	 * supplies only the already selected declared text/choice fields. Ordinary
	 * submissions and native provider decisions retain their original collection.
	 *
	 * @since 0.5.0
	 * @param array $values Native pending create values.
	 * @return array Original values or bounded selected item_meta for this rejection.
	 */
	public function native_values( array $values ): array {
		$id = isset( $values['form_id'] ) ? (int) $values['form_id'] : 0;
		if ( ! empty( $this->native_spam[ $id ]['blocked'] ) && hash_equals( $this->native_spam[ $id ]['proof'], $this->fingerprint( (string) $id, $this->posted() ) ) ) {
			$values['item_meta'] = $this->native_spam[ $id ]['values'];
			unset( $values['unique_id'], $values['parent_form_id'], $values['parent_nonce'], $values['parent_item_id'], $values['post_id'] );
		}
		return $values;
	}

	/**
	 * Tracks only the spam entry produced by the exact protected incoming lifecycle.
	 *
	 * @since 0.5.0
	 * @param int $entry_id Newly persisted native entry ID.
	 * @param int $form_id Native form ID.
	 * @return void
	 */
	public function created( $entry_id, $form_id ): void {
		$id = (int) $form_id;
		if ( ! isset( $this->native_spam[ $id ] ) || ! hash_equals( $this->native_spam[ $id ]['proof'], $this->fingerprint( (string) $id, $this->posted() ) ) ) {
			return;
		}
		$entry = \FrmEntry::getOne( (int) $entry_id );
		if ( ! is_object( $entry ) ) {
			return;
		}
		$spam                              = class_exists( '\FrmSpamEntriesHelper' ) && \FrmSpamEntriesHelper::is_spam( $entry );
		$this->native_spam[ $id ]['entry'] = (int) $entry_id;
		if ( $spam && ! $this->native_spam[ $id ]['blocked'] ) {
			$this->guard->observe_spam( 'formidable', (string) $id, $this->token_evidence( $this->posted() ) );
		}
		if ( $this->feedback ) {
			$this->feedback->track( 'formidable', (int) $entry_id, $spam );
		}
	}

	/**
	 * Suppresses success redirects/pages only for this request's persisted spam.
	 *
	 * @since 0.5.0
	 * @param mixed  $method Native confirmation method or conditional actions.
	 * @param object $form Native form.
	 * @param string $action Native create/update event.
	 * @return mixed Message confirmation for this rejection; otherwise unchanged.
	 */
	public function confirmation_method( $method, $form, $action ) {
		return is_object( $form ) && ! empty( $this->native_spam[ (int) $form->id ]['entry'] ) && ! empty( $this->native_spam[ (int) $form->id ]['blocked'] ) ? 'message' : $method;
	}

	/**
	 * Replaces the native success text with a visible, neutral content rejection.
	 *
	 * @since 0.5.0
	 * @param string $message Existing escaped confirmation HTML.
	 * @param object $form Native form.
	 * @param int    $entry_id Native persisted ID.
	 * @return string Own escaped rejection or the unrelated native confirmation.
	 */
	public function confirmation_message( $message, $form, $entry_id ): string {
		if ( ! is_object( $form ) || (int) $entry_id < 1 || empty( $this->native_spam[ (int) $form->id ]['blocked'] ) || ( $this->native_spam[ (int) $form->id ]['entry'] ?? 0 ) !== (int) $entry_id ) {
			return $message;
		}
		return '<div class="frm_error_style" role="alert">' . esc_html( $this->guard->spam_message() ) . '</div>';
	}

	/**
	 * Binds a native entry observation to the exact bounded technical token.
	 *
	 * @since 0.5.0
	 * @param string $id Native form ID.
	 * @param array  $posted Unslashed native controls.
	 * @return string Request-local digest, or empty without a valid token shape.
	 */
	private function fingerprint( string $id, array $posted ): string {
		$token = $posted['rktsky_antispam_token'] ?? '';
		return is_string( $token ) && '' !== $token && strlen( $token ) <= 400 ? hash( 'sha256', $id . '|' . $token ) : '';
	}

	/**
	 * Selects declared text inputs from the native form instead of copying item_meta.
	 *
	 * Embedded/repeating forms and arbitrary compound keys are excluded. Name
	 * parts and flat choice indexes are explicit; hidden/password/file/payment
	 * types and administrator-only fields never enter rules or quarantine.
	 *
	 * @since 0.3.0
	 * @param string $id Native positive form ID.
	 * @param array  $posted Unslashed native request.
	 * @return array{fields:array<string,string>,email:string,values:array} Bounded policy and native metadata selection.
	 */
	private function content( string $id, array $posted ): array {
		$data        = array(
			'fields' => array(),
			'email'  => '',
			'values' => array(),
		);
		$values      = isset( $posted['item_meta'] ) && is_array( $posted['item_meta'] ) ? $posted['item_meta'] : array();
		$definitions = \FrmField::get_all_for_form( (int) $id, 200, 'exclude', 'exclude' );
		foreach ( is_array( $definitions ) ? $definitions : array() as $field ) {
			if ( ! is_object( $field ) || ! isset( $field->id, $field->type ) || ! in_array( $field->type, array( 'text', 'textarea', 'email', 'url', 'phone', 'number', 'date', 'time', 'select', 'radio', 'checkbox', 'name' ), true ) || ! empty( $field->field_options['admin_only'] ) || ! isset( $values[ $field->id ] ) ) {
				continue;
			}
			$value = $values[ $field->id ];
			if ( is_array( $value ) ) {
				if ( ! in_array( $field->type, array( 'name', 'checkbox', 'select' ), true ) ) {
					continue;
				}
				$parts = array();
				foreach ( array_slice( $value, 0, 30, true ) as $key => $part ) {
					$known = 'name' === $field->type ? in_array( (string) $key, array( 'first', 'middle', 'last' ), true ) : ctype_digit( (string) $key );
					if ( $known && is_scalar( $part ) ) {
						$parts[] = sanitize_text_field( substr( (string) $part, 0, 4096 ) );
					}
				}
				$value = implode( ', ', $parts );
			} elseif ( is_scalar( $value ) ) {
				$value = 'textarea' === $field->type ? sanitize_textarea_field( substr( (string) $value, 0, 4096 ) ) : sanitize_text_field( substr( (string) $value, 0, 4096 ) );
			} else {
				continue;
			}
			$data['values'][ (int) $field->id ] = $value;
			$label                              = isset( $field->name ) && is_string( $field->name ) ? $field->name : 'Field';
			$this->add_content( $data, (string) $field->id . ': ' . $label, $value, 'email' === $field->type );
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}
		return $data;
	}
}
