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
	 * @var array<string,string>
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
	 * @var array<string,string>
	 */
	private $gravity_aborted = array();

	/**
	 * Verified final content decisions awaiting one native spam evaluation.
	 *
	 * @since 0.5.0
	 * @var array<string,array{proof:string,reason:string}>
	 */
	private array $gravity_content_spam = array();

	/**
	 * Exact proof for a locally routed native spam confirmation.
	 *
	 * @since 0.5.0
	 * @var array<string,string>
	 */
	private array $gravity_spam_confirmation = array();

	/**
	 * One resume-mail validation result for its exact form, proof and draft token.
	 *
	 * This authorizes only the notification batch of that native send-link
	 * validation; another field-validation invocation always consumes fresh proof.
	 *
	 * @since 0.5.0
	 * @var array<string,array{proof:string,reason:string}>
	 */
	private array $gravity_resume_checks = array();

	/**
	 * Registers native hooks after provider detection and explicit enablement.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_filter( 'gform_form_tag', array( $this, 'gravity_fields' ), 10, 2 );
		add_filter( 'gform_get_form_save_confirmation_filter', array( $this, 'resume_fields' ), 20, 2 );
		add_filter( 'gform_field_validation', array( $this, 'check_resume_email' ), 20, 5 );
		add_filter( 'gform_pre_send_email', array( $this, 'guard_resume_mail' ), PHP_INT_MAX, 4 );
		add_filter( 'gform_get_form_save_email_confirmation_filter', array( $this, 'resume_confirmation' ), 20, 2 );
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
	 * Routes final content matches to native spam and rejects unverifiable submissions.
	 *
	 * Page navigation, final submissions and Save and Continue require protection
	 * in every context. Only a genuine final submission may retain a verified
	 * content match in GF's native spam queue. Validation-only, draft and navigation
	 * matches remain visible local errors. Each invocation clears earlier state;
	 * only the exact native lifecycle grants one token-bound abort permit.
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
		unset( $this->gravity_permits[ $form_id ], $this->gravity_errors[ $form_id ], $this->gravity_aborted[ $form_id ], $this->gravity_native_captured[ $form_id ], $this->gravity_evidence[ $form_id ], $this->gravity_content_spam[ $form_id ], $this->gravity_spam_confirmation[ $form_id ] );

		if ( empty( $result['is_valid'] ) ) {
			return $result;
		}

		$posted = $this->posted();
		$data   = $this->gravity_content( $result['form'], $posted );
		$reason = $this->guard->check( 'gravityforms', $form_id, $posted, $data['fields'] );

		$proof = $this->gravity_fingerprint( $form_id, $posted );
		if ( '' !== $reason && Guard::is_content_reason( $reason ) && '' !== $proof && $this->can_store_native_spam( $result['form'], $context ) ) {
			$this->gravity_content_spam[ $form_id ] = array(
				'proof'  => $proof,
				'reason' => $reason,
			);
			$this->gravity_permits[ $form_id ]      = $proof;
			$this->gravity_evidence[ $form_id ]     = $proof;
		} elseif ( '' !== $reason ) {
			$result['is_valid']               = false;
			$this->gravity_errors[ $form_id ] = $reason;
			$this->retain( 'gravityforms', $form_id, $reason, $data );
		} elseif ( in_array( $context, array( 'form-submit', 'api-submit' ), true ) ) {
			$this->gravity_permits[ $form_id ]  = $proof;
			$this->gravity_evidence[ $form_id ] = $proof;
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
			$message .= '<p class="wp-antispam-form-error" role="alert">' . esc_html( $this->failure_message( (string) $this->gravity_errors[ $form_id ] ) ) . '</p>';
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
			unset( $this->gravity_evidence[ $form_id ], $this->gravity_content_spam[ $form_id ], $this->gravity_spam_confirmation[ $form_id ] );
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

		// A mismatched or already consumed bridge permit cannot carry an earlier decision.
		unset( $this->gravity_content_spam[ $form_id ], $this->gravity_evidence[ $form_id ], $this->gravity_spam_confirmation[ $form_id ] );
		$data    = $this->gravity_content( $form, $posted );
		$reason  = $this->guard->check( 'gravityforms', $form_id, $posted, $data['fields'] );
		$context = is_callable( array( '\GFFormDisplay', 'get_submission_context' ) ) ? \GFFormDisplay::get_submission_context() : '';
		if ( '' !== $reason && Guard::is_content_reason( $reason ) && '' !== $fingerprint && $this->can_store_native_spam( $form, $context ) ) {
			$this->gravity_content_spam[ $form_id ] = array(
				'proof'  => $fingerprint,
				'reason' => $reason,
			);
		} elseif ( '' !== $reason ) {
			$this->gravity_aborted[ $form_id ] = $reason;
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
	 * @since 0.5.0 Preserves the native confirmation wrapper used by iframe callbacks.
	 * @param string|array $confirmation Native confirmation markup or redirect.
	 * @param array        $form         Native form definition.
	 * @return string|array Original confirmation or an explicit rejection message.
	 */
	public function gravity_confirmation( $confirmation, array $form ) {
		$form_id = isset( $form['id'] ) ? (string) absint( $form['id'] ) : '0';

		$proof      = $this->gravity_fingerprint( $form_id, $this->protection_controls() );
		$spam_proof = $this->gravity_spam_confirmation[ $form_id ] ?? '';
		if ( '' !== $proof && '' !== $spam_proof && hash_equals( $spam_proof, $proof ) ) {
			return $this->confirmation_error( $form, $this->guard->spam_message() );
		}
		if ( ! empty( $this->gravity_aborted[ $form_id ] ) ) {
			return $this->confirmation_error( $form, $this->failure_message( (string) $this->gravity_aborted[ $form_id ] ) );
		}

		return $confirmation;
	}

	/**
	 * Uses GF's native message serializer for anchors, announcements and AJAX IDs.
	 *
	 * The iframe callback requires both the confirmation wrapper and the native
	 * anchor for scrolling; accessibility also uses its message identifier. Only
	 * plain owned copy enters the native serializer. Transport wrapping remains
	 * with the native caller. The fixed fallback supports isolated contract stubs.
	 *
	 * @since 0.5.0
	 * @param array  $form Trusted native form definition, copied for serialization.
	 * @param string $message Plain local rejection message, escaped here.
	 * @return string Escaped local error inside the required native wrapper.
	 */
	private function confirmation_error( array $form, string $message ): string {
		$form_id = isset( $form['id'] ) ? (string) absint( $form['id'] ) : '0';
		$markup  = '<p class="wp-antispam-form-error" role="alert">' . esc_html( $message ) . '</p>';
		if ( is_callable( array( '\\GFFormDisplay', 'get_confirmation_message' ) ) ) {
			$form['id'] = $form_id;
			return \GFFormDisplay::get_confirmation_message(
				array(
					'message'           => $markup,
					'disableAutoformat' => true,
				),
				$form,
				array()
			);
		}
		return '<div id="gf_' . esc_attr( $form_id ) . '"></div><div id="gform_confirmation_wrapper_' . esc_attr( $form_id ) . '" class="gform_confirmation_wrapper">' . $markup . '</div>';
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
	 * Routes one verified content verdict through GF's native spam filter.
	 *
	 * @since 0.2.0
	 * @param bool  $spam  Existing provider spam verdict.
	 * @param array $form  Native form definition.
	 * @param array $entry Provider-created current entry values.
	 * @since 0.5.0 Adds a consumed-proof content decision without overriding earlier spam.
	 * @return bool Earlier native spam or the verified final local content verdict.
	 */
	public function observe_gravity_spam( $spam, array $form, array $entry ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Native filter supplies an entry payload; this observer deliberately records no copied content.
		$form_id = isset( $form['id'] ) ? (string) absint( $form['id'] ) : '0';
		$current = $this->gravity_fingerprint( $form_id, $this->protection_controls() );
		$pending = $this->gravity_content_spam[ $form_id ] ?? null;
		if ( is_array( $pending ) && '' !== $current && hash_equals( $pending['proof'], $current ) ) {
			unset( $this->gravity_content_spam[ $form_id ] );
			$this->gravity_spam_confirmation[ $form_id ] = $current;
			if ( ! $spam ) {
				\GFCommon::set_spam_filter( (int) $form_id, 'WP Antispam', sprintf( /* translators: %s: fixed internal content-policy reason code. */ __( 'Local content policy: %s.', 'wp-antispam' ), $pending['reason'] ) );
			}
			$spam = true;
		}
		if ( $spam ) {
			$this->guard->observe_spam( 'gravityforms', $form_id, $this->protection_controls() );
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
		if ( empty( $entry['id'] ) || '' === $proof || '' === $current || ! hash_equals( $proof, $current ) ) {
			return;
		}
		$spam    = isset( $entry['status'] ) && 'spam' === $entry['status'];
		$pending = $this->gravity_content_spam[ $form_id ] ?? null;
		if ( $spam && is_array( $pending ) && hash_equals( $pending['proof'], $current ) ) {
			$this->gravity_spam_confirmation[ $form_id ] = $current;
			unset( $this->gravity_content_spam[ $form_id ] );
		}

		if ( null !== $this->feedback ) {
			$this->feedback->track( 'gravityforms', $entry['id'], $spam );
		}
	}

	/**
	 * Allows native spam persistence only on a real final submission boundary.
	 *
	 * Drafts, page navigation and validation-only calls have no native spam row.
	 * GF can retain a cached false verdict across API calls in one PHP request;
	 * no public API invalidates it, so that case remains a visible local block.
	 * Original provider state is never rewritten to manufacture a spam result.
	 *
	 * @since 0.5.0
	 * @param array  $form Native form definition.
	 * @param string $context Native validation/submission context.
	 * @return bool Whether the native spam hook can safely own this final attempt.
	 */
	private function can_store_native_spam( array $form, string $context ): bool {
		if ( ! in_array( $context, array( 'form-submit', 'api-submit' ), true ) || ! is_callable( array( '\GFFormDisplay', 'is_last_page' ) ) || ! empty( $_POST['gform_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Native save intent only narrows persistence; Guard has already consumed authentic proof.
			return false;
		}
		$id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		return $id > 0 && \GFFormDisplay::is_last_page( $form ) && false !== ( \GFFormDisplay::$submission[ $id ]['is_spam'] ?? null );
	}

	/**
	 * Selects an accurate public message without exposing the matching policy.
	 *
	 * @since 0.5.0
	 * @param string $reason Fixed local verdict.
	 * @return string Localized content or technical rejection message.
	 */
	private function failure_message( string $reason ): string {
		return Guard::is_content_reason( $reason ) ? $this->guard->spam_message() : $this->guard->message();
	}

	/**
	 * Decorates only the native resume-email form in the documented save output.
	 *
	 * The shared bridge supplies fresh proof after GF replaces the original form.
	 * A separate signed scope prevents using an ordinary submission token for mail.
	 * No native nonce, draft token, field value or provider script is changed.
	 *
	 * @since 0.5.0
	 * @param string $confirmation Fully rendered native save confirmation.
	 * @param array  $form Native saved form definition.
	 * @return string Native HTML with owned technical host attributes only.
	 */
	public function resume_fields( string $confirmation, array $form ): string {
		$id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		if ( $id < 1 ) {
			return $confirmation;
		}
		$processor = new \WP_HTML_Tag_Processor( $confirmation );
		while ( $processor->next_tag( 'FORM' ) ) {
			if ( 'gform_' . $id !== $processor->get_attribute( 'id' ) ) {
				continue;
			}
			foreach ( $this->fields->attributes( 'gravityforms', $id . '_resume' ) as $name => $value ) {
				$processor->set_attribute( $name, $value );
			}
		}
		return $processor->get_updated_html();
	}

	/**
	 * Binds resume-mail evidence to its exact form, own proof and native draft token.
	 *
	 * The draft token is read only as bounded technical state and remains in memory.
	 * Recipient addresses and draft contents never enter rules, hashes or quarantine.
	 *
	 * @since 0.5.0
	 * @param string $form_id Native form ID.
	 * @return string In-memory fingerprint or empty for malformed native context.
	 */
	private function resume_fingerprint( string $form_id ): string {
		$posted_id = $_POST['gform_send_resume_link'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the exact canonical native decimal ID is accepted below; this context grants no authorization.
		$draft     = $_POST['gform_resume_token'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw type and size are checked before unslashing; the ASCII allowlist below excludes all other input.
		if ( ! is_string( $posted_id ) || $posted_id !== $form_id || ! is_string( $draft ) || strlen( $draft ) > 128 ) {
			return '';
		}
		$draft = wp_unslash( $draft );
		if ( 1 !== preg_match( '/^[a-zA-Z0-9_-]{1,128}$/D', $draft ) ) {
			return '';
		}
		$proof = $this->gravity_fingerprint( $form_id, $this->protection_controls() );
		return hash( 'sha256', $proof . '|' . $form_id . '|' . $draft );
	}

	/**
	 * Checks one native resume-mail validation or its bounded notification batch.
	 *
	 * A new field validation never reuses an earlier result, even within one PHP
	 * request. The mail hook may reuse only that exact form/proof/draft decision
	 * while GF processes multiple notifications for the same native send action.
	 *
	 * @since 0.5.0
	 * @param string $form_id Native form ID.
	 * @param bool   $fresh Whether this is a new native field-validation invocation.
	 * @return string Fixed failure reason or empty for technical proof success.
	 */
	private function check_resume_proof( string $form_id, bool $fresh ): string {
		$proof    = $this->resume_fingerprint( $form_id );
		$previous = $this->gravity_resume_checks[ $form_id ] ?? null;
		if ( ! $fresh && is_array( $previous ) && hash_equals( $previous['proof'], $proof ) ) {
			return $previous['reason'];
		}
		if ( '' === $proof ) {
			$reason = 'context';
			$this->guard->reject( 'gravityforms', $reason );
		} else {
			$reason = $this->guard->check( 'gravityforms', $form_id . '_resume', $this->protection_controls(), array(), true );
		}
		$this->gravity_resume_checks[ $form_id ] = array(
			'proof'  => $proof,
			'reason' => $reason,
		);
		return $reason;
	}

	/**
	 * Stops the native send-link field validation before resume-mail processing.
	 *
	 * The original provider supplies this dedicated context and synthetic field.
	 * Unrelated fields and earlier native validation errors remain unchanged.
	 *
	 * @since 0.5.0
	 * @param array  $result Original field-validation result.
	 * @param mixed  $value Native email value, deliberately never read or retained.
	 * @param array  $form Native form definition.
	 * @param mixed  $field Native field object.
	 * @param string $context Provider-supplied validation context.
	 * @return array Original result or a visible technical protection failure.
	 */
	public function check_resume_email( array $result, $value, array $form, $field, string $context = '' ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Recipient content never enters technical verification.
		if ( 'send-link-submit' !== $context || ! is_object( $field ) || 'gform_resume_email' !== ( $field->id ?? null ) || empty( $form['id'] ) ) {
			return $result;
		}
		$form_id = (string) absint( $form['id'] );
		unset( $this->gravity_resume_checks[ $form_id ] );
		if ( empty( $result['is_valid'] ) ) {
			return $result;
		}
		if ( '' !== $this->check_resume_proof( $form_id, true ) ) {
			$result['is_valid'] = false;
			$result['message']  = $this->guard->message();
		}
		return $result;
	}

	/**
	 * Preserves a final documented mail boundary for native resume notifications.
	 *
	 * This also fails closed on provider versions without send-link field context.
	 * Other notification events remain provider-owned. No broad logged-in or
	 * registration exception is introduced for native resume-mail processing.
	 *
	 * @since 0.5.0
	 * @param array       $email Native outgoing arguments, never inspected for content.
	 * @param string      $format Native message format, left unchanged.
	 * @param array|false $notification Native event metadata or a direct-mail sentinel.
	 * @param array|false $entry Native draft context or a direct-mail sentinel.
	 * @return array Original arguments or the native abort-email flag.
	 */
	public function guard_resume_mail( array $email, string $format, $notification, $entry ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Mail format and contents are never processed by protection.
		if ( ! is_array( $notification ) || 'form_save_email_requested' !== ( $notification['event'] ?? '' ) || ! empty( $email['abort_email'] ) ) {
			return $email;
		}
		$form_id = is_array( $entry ) && isset( $entry['form_id'] ) ? (string) absint( $entry['form_id'] ) : '0';
		if ( '0' === $form_id || '' !== $this->check_resume_proof( $form_id, false ) ) {
			$email['abort_email'] = true;
		}
		return $email;
	}

	/**
	 * Avoids a successful-looking native email confirmation after a final mail block.
	 *
	 * @since 0.5.0
	 * @param string $confirmation Native sent-link confirmation markup.
	 * @param array  $form Native form definition.
	 * @return string Native success or an explicit technical failure in its transport.
	 */
	public function resume_confirmation( string $confirmation, array $form ): string {
		$form_id = isset( $form['id'] ) ? (string) absint( $form['id'] ) : '0';
		$check   = $this->gravity_resume_checks[ $form_id ] ?? null;
		if ( ! is_array( $check ) || '' === $check['reason'] || ! hash_equals( $check['proof'], $this->resume_fingerprint( $form_id ) ) ) {
			return $confirmation;
		}
		$message = $this->confirmation_error( $form, $this->guard->message() );
		if ( is_callable( array( '\GFFormDisplay', 'is_iframe_submission_method' ) ) && \GFFormDisplay::is_iframe_submission_method() ) {
			return \GFFormDisplay::get_ajax_postback_html( $message );
		}
		return $message;
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
