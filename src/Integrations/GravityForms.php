<?php
/**
 * Connects local protection to the native GravityForms lifecycle.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

use Rktsky\Antispam\{Settings, Guard, Fields, Quarantine, Feedback};

defined( 'ABSPATH' ) || exit;

/**
 * Owns only the GravityForms integration and its request-local state.
 *
 * @since 0.3.0
 */
final class GravityForms extends Adapter {

	/**
	 * Final native GF spam observations already handled in this lifecycle.
	 *
	 * @since 0.2.0
	 * @var array<string,bool>
	 */
	private array $gravity_native_captured = array();

	/**
	 * Exact proof linking a processed GF submission to its newly persisted entry.
	 *
	 * @since 0.2.0
	 * @var array<string,string>
	 */
	private array $gravity_evidence = array();

	/**
	 * Gravity Forms rejected during the current request.
	 *
	 * @since 0.1.0
	 * @var array<string, bool>
	 */
	private $gravity_errors = array();

	/**
	 * One-use permits from a GF submission validation to its next abort hook.
	 *
	 * Each digest binds the exact form and token; API-only validation grants none.
	 *
	 * @since 0.1.0
	 * @var array<string, string>
	 */
	private $gravity_permits = array();

	/**
	 * Gravity Forms aborted before draft or final entry persistence.
	 *
	 * @since 0.1.0
	 * @var array<string, bool>
	 */
	private $gravity_aborted = array();

	/**
	 * Registers native hooks after provider detection and explicit enablement.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_filter( 'gform_form_tag', array( $this, 'gravity_fields' ), 10, 2 );
		add_filter( 'gform_validation', array( $this, 'check_gravity' ), 5, 2 );
		add_filter( 'gform_validation_message', array( $this, 'gravity_message' ), 10, 2 );
		add_filter( 'gform_abort_submission_with_confirmation', array( $this, 'abort_gravity' ), 20, 2 );
		add_filter( 'gform_confirmation', array( $this, 'gravity_confirmation' ), 20, 2 );
		add_filter( 'gform_entry_is_spam', array( $this, 'observe_gravity_spam' ), PHP_INT_MAX, 3 );
		add_action( 'gform_entry_created', array( $this, 'track_gravity_entry' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Adds protection immediately after Gravity Forms' native opening form tag.
	 *
	 * @since 0.1.0
	 * @param string $tag  Original opening form tag.
	 * @param array  $form Native form definition.
	 * @return string Opening tag with protection, or the original absent tag.
	 */
	public function gravity_fields( string $tag, array $form ): string {
		if ( '' === $tag || empty( $form['id'] ) ) {
			return $tag;
		}

		return $tag . $this->fields->markup( 'gravityforms', (string) absint( $form['id'] ) );
	}

	/**
	 * Rejects Gravity Forms submissions with a visible validation error.
	 *
	 * Page navigation, final submissions and Save and Continue require protection
	 * in every context, including GFAPI submissions. Client page numbers cannot
	 * opt a request out of verification. Each invocation resets the form's prior
	 * state; only a real submission context grants one token-bound abort permit.
	 *
	 * @since 0.1.0
	 * @param array  $result  Native validation result and form definition.
	 * @param string $context Native form-submit, api-submit or api-validate context.
	 * @return array Validation result preserving existing provider failures.
	 */
	public function check_gravity( array $result, $context = '' ): array {
		if ( empty( $result['form']['id'] ) ) {
			return $result;
		}

		$form_id = (string) absint( $result['form']['id'] );
		unset( $this->gravity_permits[ $form_id ], $this->gravity_errors[ $form_id ], $this->gravity_aborted[ $form_id ], $this->gravity_native_captured[ $form_id ], $this->gravity_evidence[ $form_id ] );

		if ( empty( $result['is_valid'] ) ) {
			return $result;
		}

		$posted = $this->posted();
		$data   = $this->gravity_content( $result['form'], $posted );
		$reason = $this->guard->check( 'gravityforms', $form_id, $posted, $data['fields'] );

		if ( '' !== $reason ) {
			$result['is_valid']               = false;
			$this->gravity_errors[ $form_id ] = true;
			$this->retain( 'gravityforms', $form_id, $reason, $data );
		} elseif ( in_array( $context, array( 'form-submit', 'api-submit' ), true ) ) {
			$this->gravity_permits[ $form_id ]  = $this->gravity_fingerprint( $form_id, $posted );
			$this->gravity_evidence[ $form_id ] = $this->gravity_permits[ $form_id ];
		}

		return $result;
	}

	/**
	 * Adds the guard's retry instruction without removing native validation text.
	 *
	 * @since 0.1.0
	 * @param string $message Native validation summary.
	 * @param array  $form    Native form definition.
	 * @return string Validation summary including any local rejection.
	 */
	public function gravity_message( string $message, array $form ): string {
		$form_id = isset( $form['id'] ) ? (string) absint( $form['id'] ) : '0';

		if ( ! empty( $this->gravity_errors[ $form_id ] ) ) {
			$message .= '<p class="wp-antispam-form-error" role="alert">' . esc_html( $this->guard->message() ) . '</p>';
		}

		return $message;
	}

	/**
	 * Covers GF Save and Continue and paths that do not execute field validation.
	 *
	 * Exactly one token-bound permit can connect submission validation to its
	 * following abort hook. A new save/submission performs a fresh guard check.
	 * The GF hook runs before persistence and never reverses an earlier abort.
	 *
	 * @since 0.1.0
	 * @param bool  $abort Existing provider abort decision.
	 * @param array $form Native form definition.
	 * @return bool Whether GF must abort before creating a draft or final entry.
	 */
	public function abort_gravity( $abort, array $form ): bool {
		$form_id = isset( $form['id'] ) ? (string) absint( $form['id'] ) : '0';
		$permit  = $this->gravity_permits[ $form_id ] ?? '';
		unset( $this->gravity_permits[ $form_id ], $this->gravity_aborted[ $form_id ] );

		if ( $abort ) {
			unset( $this->gravity_evidence[ $form_id ] );
			if ( empty( $this->gravity_errors[ $form_id ] ) && empty( $this->gravity_native_captured[ $form_id ] ) ) {
				$this->gravity_native_captured[ $form_id ] = true;
				$this->guard->observe_spam( 'gravityforms', $form_id, $this->posted() );
				if ( $this->retention_enabled() ) {
					$this->retain( 'gravityforms', $form_id, 'native_spam', $this->gravity_content( $form, $this->posted() ) );
				}
			}
			return true;
		}

		$posted      = $this->posted();
		$fingerprint = $this->gravity_fingerprint( $form_id, $posted );

		if ( '' !== $permit && '' !== $fingerprint && hash_equals( $permit, $fingerprint ) ) {
			return false;
		}

		$data   = $this->gravity_content( $form, $posted );
		$reason = $this->guard->check( 'gravityforms', $form_id, $posted, $data['fields'] );
		if ( '' !== $reason ) {
			$this->gravity_aborted[ $form_id ] = true;
			$this->retain( 'gravityforms', $form_id, $reason, $data );

			return true;
		}

		$this->gravity_evidence[ $form_id ] = $fingerprint;
		return false;
	}

	/**
	 * Replaces GF's successful-looking abort confirmation only for local failures.
	 *
	 * @since 0.1.0
	 * @param string|array $confirmation Native confirmation markup or redirect.
	 * @param array        $form         Native form definition.
	 * @return string|array Original confirmation or an explicit rejection message.
	 */
	public function gravity_confirmation( $confirmation, array $form ) {
		$form_id = isset( $form['id'] ) ? (string) absint( $form['id'] ) : '0';

		if ( ! empty( $this->gravity_aborted[ $form_id ] ) ) {
			return '<p class="wp-antispam-form-error" role="alert">' . esc_html( $this->guard->message() ) . '</p>';
		}

		return $confirmation;
	}

	/**
	 * Binds a GF lifecycle permit to an exact native form and bounded token.
	 *
	 * This digest is request-local bookkeeping, never a stored visitor identifier
	 * or a replacement for Guard verification.
	 *
	 * @since 0.1.0
	 * @param string $form_id Native form ID.
	 * @param array  $posted Unslashed form controls.
	 * @return string Token-bound digest, or empty when no bounded token exists.
	 */
	private function gravity_fingerprint( string $form_id, array $posted ): string {
		$token = $posted['rktsky_antispam_token'] ?? '';

		return is_string( $token ) && '' !== $token && strlen( $token ) <= 400 ? hash( 'sha256', $form_id . '|' . $token ) : '';
	}

	/**
	 * Observes GF's existing spam verdict after native and add-on spam filters.
	 *
	 * @since 0.2.0
	 * @param bool  $spam  Existing provider spam verdict.
	 * @param array $form  Native form definition.
	 * @param array $entry Provider-created current entry values.
	 * @return bool Unmodified provider verdict.
	 */
	public function observe_gravity_spam( $spam, array $form, array $entry ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Native filter supplies an entry payload; this observer deliberately records no copied content.
		$form_id = isset( $form['id'] ) ? (string) absint( $form['id'] ) : '0';
		if ( $spam ) {
			$this->guard->observe_spam( 'gravityforms', $form_id, $this->posted() );
		}

		return (bool) $spam;
	}

	/**
	 * Tracks final native GF predictions only for the observed incoming lifecycle.
	 *
	 * Native GF already retains persisted spam. No duplicate quarantine record or
	 * copied entry payload is created for this quality observation.
	 *
	 * @since 0.2.0
	 * @param array $entry Newly persisted native entry including final spam status.
	 * @param array $form  Native form definition.
	 * @return void
	 */
	public function track_gravity_entry( array $entry, array $form ): void {
		$form_id = isset( $form['id'] ) ? (string) absint( $form['id'] ) : '0';
		$proof   = $this->gravity_evidence[ $form_id ] ?? '';
		unset( $this->gravity_evidence[ $form_id ] );
		$current = $this->gravity_fingerprint( $form_id, $this->posted() );
		if ( null === $this->feedback || empty( $entry['id'] ) || '' === $proof || '' === $current || ! hash_equals( $proof, $current ) ) {
			return;
		}
		$spam = isset( $entry['status'] ) && 'spam' === $entry['status'];
		$this->feedback->track( 'gravityforms', $entry['id'], $spam );
	}

	/**
	 * Selects GF content through native field types and their declared input IDs.
	 *
	 * Administrative, payment, hidden, password and upload types are excluded.
	 * Entry values are already unslashed; POST controls must be supplied unslashed.
	 *
	 * @since 0.2.0
	 * @param array $form        Native form definition and field objects.
	 * @param array $values      Native entry or unslashed POST controls.
	 * @param bool  $entry_values Whether keys are entry IDs rather than POST names.
	 * @return array{fields:array<string,string>,email:string} Bounded selected data.
	 */
	private function gravity_content( array $form, array $values, bool $entry_values = false ): array {
		$data    = array(
			'fields' => array(),
			'email'  => '',
		);
		$allowed = array( 'text', 'textarea', 'email', 'website', 'phone', 'number', 'date', 'name', 'select', 'multiselect', 'checkbox', 'radio' );
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			if ( ! is_object( $field ) || ! is_callable( array( $field, 'get_input_type' ) ) || ! isset( $field->id ) ) {
				continue;
			}
			$type = $field->get_input_type();
			if ( ! in_array( $type, $allowed, true ) || ( is_callable( array( $field, 'is_administrative' ) ) && $field->is_administrative() ) ) {
				continue;
			}
			$inputs = is_callable( array( $field, 'get_entry_inputs' ) ) ? $field->get_entry_inputs() : null;
			$inputs = is_array( $inputs ) && ! empty( $inputs ) ? $inputs : array( array( 'id' => $field->id ) );
			$label  = isset( $field->label ) && is_string( $field->label ) ? $field->label : 'Field';
			foreach ( array_slice( $inputs, 0, 30 ) as $input ) {
				$id = is_array( $input ) && isset( $input['id'] ) && is_scalar( $input['id'] ) ? (string) $input['id'] : '';
				if ( 1 !== preg_match( '/^\d+(?:\.\d+)?$/D', $id ) ) {
					continue;
				}
				$key = $entry_values ? $id : 'input_' . str_replace( '.', '_', $id );
				if ( isset( $values[ $key ] ) ) {
					$this->add_content( $data, $id . ': ' . $label, $values[ $key ], 'email' === $type );
				}
				if ( count( $data['fields'] ) >= 30 ) {
					break 2;
				}
			}
		}

		return $data;
	}

}
