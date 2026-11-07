<?php
/**
 * Native comment and form-provider integrations.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Connects the local guard through public provider hooks.
 *
 * @since 0.1.0
 */
final class Integrations {

	/**
	 * Native WPForms text types whose content can be reviewed locally.
	 *
	 * @since 0.2.0
	 * @var string[]
	 */
	private const WPFORMS_CONTENT_TYPES = array( 'text', 'textarea', 'email', 'url', 'phone', 'number', 'name', 'select', 'checkbox', 'radio', 'date-time' );

	/**
	 * Plugin settings.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Submission guard.
	 *
	 * @since 0.1.0
	 * @var Guard
	 */
	private $guard;

	/**
	 * Protection-field renderer.
	 *
	 * @since 0.1.0
	 * @var Fields
	 */
	private $fields;

	/**
	 * Optional encrypted quarantine; its own opt-in remains authoritative.
	 *
	 * @since 0.2.0
	 * @var Quarantine|null
	 */
	private ?Quarantine $quarantine;

	/**
	 * Optional metadata-only human feedback ledger.
	 *
	 * @since 0.2.0
	 * @var Feedback|null
	 */
	private ?Feedback $feedback;

	/**
	 * WPForms native captures already handled in the current form lifecycle.
	 *
	 * @since 0.2.0
	 * @var array<int,bool>
	 */
	private array $wpforms_captured = array();

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
	 * Own token values linking native/REST comments to their final insert status.
	 *
	 * @since 0.2.0
	 * @var array<string,array<string,string>>
	 */
	private array $comment_evidence = array();

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
	 * Contact Form 7 forms rejected during the current request.
	 *
	 * @since 0.1.0
	 * @var array<string, bool>
	 */
	private $cf7_errors = array();

	/**
	 * Supplies the integration dependencies without changing provider settings.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Added the optional encrypted quarantine dependency.
	 * @param Settings        $settings   Plugin settings.
	 * @param Guard           $guard      Local submission guard.
	 * @param Fields          $fields     Protection-field renderer.
	 * @param Quarantine|null $quarantine Optional encrypted, opt-in retention.
	 * @param Feedback|null   $feedback   Optional reviewed-prediction ledger.
	 * @return void
	 */
	public function __construct( Settings $settings, Guard $guard, Fields $fields, ?Quarantine $quarantine = null, ?Feedback $feedback = null ) {
		$this->settings   = $settings;
		$this->guard      = $guard;
		$this->fields     = $fields;
		$this->quarantine = $quarantine;
		$this->feedback   = $feedback;
	}

	/**
	 * Reports providers whose public integration entry points are loaded.
	 *
	 * Call after plugins_loaded so plugin loading order cannot hide a provider.
	 *
	 * @since 0.1.0
	 * @return array<string, bool> Availability indexed by provider ID.
	 */
	public static function detected(): array {
		return array(
			'comments'     => true,
			'cf7'          => class_exists( '\WPCF7_ContactForm' ) && class_exists( '\WPCF7_Submission' ),
			'gravityforms' => class_exists( '\GFForms' ) && class_exists( '\GFFormDisplay' ) && isset( \GFForms::$version ) && version_compare( \GFForms::$version, '2.7', '>=' ),
			'wpforms'      => function_exists( 'wpforms' ),
		);
	}

	/**
	 * Registers only detected integrations selected in the interface.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register(): void {
		$available = self::detected();

		if ( $available['comments'] && $this->settings->enabled( 'comments' ) ) {
			add_action( 'comment_form', array( $this, 'comment_fields' ) );
			add_filter( 'preprocess_comment', array( $this, 'check_comment' ) );
			add_filter( 'rest_pre_insert_comment', array( $this, 'check_rest_comment' ), 10, 2 );
			add_action( 'wp_insert_comment', array( $this, 'observe_comment_spam' ), PHP_INT_MAX, 2 );
		}

		if ( $available['cf7'] && $this->settings->enabled( 'cf7' ) ) {
			add_filter( 'wpcf7_form_elements', array( $this, 'cf7_fields' ) );
			add_filter( 'wpcf7_posted_data', array( $this, 'cf7_processed_data' ) );
			add_filter( 'wpcf7_spam', array( $this, 'check_cf7' ), 20, 2 );
			add_filter( 'wpcf7_display_message', array( $this, 'cf7_message' ), 20, 2 );
		}

		if ( $available['gravityforms'] && $this->settings->enabled( 'gravityforms' ) ) {
			add_filter( 'gform_form_tag', array( $this, 'gravity_fields' ), 10, 2 );
			add_filter( 'gform_validation', array( $this, 'check_gravity' ), 5, 2 );
			add_filter( 'gform_validation_message', array( $this, 'gravity_message' ), 10, 2 );
			add_filter( 'gform_abort_submission_with_confirmation', array( $this, 'abort_gravity' ), 20, 2 );
			add_filter( 'gform_confirmation', array( $this, 'gravity_confirmation' ), 20, 2 );
			add_filter( 'gform_entry_is_spam', array( $this, 'observe_gravity_spam' ), PHP_INT_MAX, 3 );
			add_action( 'gform_entry_created', array( $this, 'track_gravity_entry' ), PHP_INT_MAX, 2 );
		}

		if ( $available['wpforms'] && $this->settings->enabled( 'wpforms' ) ) {
			add_action( 'wpforms_frontend_output', array( $this, 'wpforms_fields' ), 30 );
			add_action( 'wpforms_process', array( $this, 'check_wpforms' ), 5, 3 );
			add_filter( 'wpforms_process_before_form_data', array( $this, 'begin_wpforms' ), 1, 2 );
			add_filter( 'wpforms_process_honeypot', array( $this, 'observe_wpforms_honeypot' ), PHP_INT_MAX, 4 );
			add_filter( 'wpforms_process_antispam', array( $this, 'observe_wpforms_antispam' ), PHP_INT_MAX, 4 );
			add_action( 'wpforms_process_after', array( $this, 'observe_wpforms_spam' ), PHP_INT_MAX, 3 );
		}
	}

	/**
	 * Renders protection inside the native comment form for its actual post ID.
	 *
	 * @since 0.1.0
	 * @param int $post_id Post receiving comments.
	 * @return void
	 */
	public function comment_fields( $post_id ): void {
		// Fields escapes its own fixed markup and does not include user content.
		echo $this->fields->markup( 'comments', (string) absint( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by Fields::markup().
	}

	/**
	 * Rejects unprotected native submissions before WordPress inserts a comment.
	 *
	 * @since 0.1.0
	 * @param array $comment Native comment data.
	 * @return array Unmodified accepted comment data.
	 */
	public function check_comment( array $comment ): array {
		$type = isset( $comment['comment_type'] ) && is_string( $comment['comment_type'] ) ? $comment['comment_type'] : '';
		$post = isset( $comment['comment_post_ID'] ) ? absint( $comment['comment_post_ID'] ) : 0;
		if ( $this->authorized_backend_reply( $comment, $post ) ) {
			unset( $this->comment_evidence[ (string) $post ] );
			return $comment;
		}
		$data                                     = $this->comment_content( $comment, true );
		$posted                                   = $this->posted();
		$this->comment_evidence[ (string) $post ] = $this->token_evidence( $posted );
		$reason                                   = in_array( $type, array( 'pingback', 'trackback' ), true ) ? 'pingback' : $this->guard->check( 'comments', (string) $post, $posted, $data['fields'] );

		if ( '' !== $reason ) {
			if ( 'pingback' === $reason ) {
				$this->guard->reject( 'comments', 'pingback' );
			} else {
				$this->retain( 'comments', (string) $post, $reason, $data );
			}

			wp_die(
				esc_html( $this->guard->message() ),
				esc_html__( 'Comment not submitted', 'wp-antispam' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		return $comment;
	}

	/**
	 * Protects REST comment creation, which bypasses preprocess_comment in Core.
	 *
	 * Existing-comment edits retain Core's independent authorization path.
	 * Request parameters are already unslashed by the REST server.
	 *
	 * @since 0.1.0
	 * @param array|\WP_Error  $comment Prepared comment or an earlier rejection.
	 * @param \WP_REST_Request $request REST request being processed.
	 * @return array|\WP_Error Accepted data or a neutral creation error.
	 */
	public function check_rest_comment( $comment, $request ) {
		if ( is_wp_error( $comment ) || ! empty( $request['id'] ) ) {
			return $comment;
		}

		$post                                     = isset( $comment['comment_post_ID'] ) ? absint( $comment['comment_post_ID'] ) : 0;
		$type                                     = isset( $comment['comment_type'] ) && is_string( $comment['comment_type'] ) ? $comment['comment_type'] : '';
		$params                                   = $request->get_params();
		$data                                     = $this->comment_content( $comment, false );
		$this->comment_evidence[ (string) $post ] = $this->token_evidence( is_array( $params ) ? $params : array() );
		$reason                                   = in_array( $type, array( 'pingback', 'trackback' ), true ) ? 'pingback' : $this->guard->check( 'comments', (string) $post, is_array( $params ) ? $params : array(), $data['fields'] );

		if ( '' !== $reason ) {
			if ( 'pingback' === $reason ) {
				$this->guard->reject( 'comments', 'pingback' );
			} else {
				$this->retain( 'comments', (string) $post, $reason, $data );
			}

			return new \WP_Error( 'wp_antispam_rejected', $this->guard->message(), array( 'status' => 403 ) );
		}

		return $comment;
	}

	/**
	 * Appends protection through Contact Form 7's native form-content filter.
	 *
	 * @since 0.1.0
	 * @param string $html Original rendered field markup.
	 * @return string Original markup followed by local protection fields.
	 */
	public function cf7_fields( string $html ): string {
		$form = \WPCF7_ContactForm::get_current();

		return $form ? $html . $this->fields->markup( 'cf7', (string) $form->id() ) : $html;
	}

	/**
	 * Removes only local technical controls from CF7's processed data collection.
	 *
	 * CF7 copies unknown top-level fields into posted_data. Removing this plugin's
	 * reserved names prevents token retention in mail/storage consumers while
	 * leaving raw POST intact for the independent guard check.
	 *
	 * @since 0.1.0
	 * @param array $posted Provider-sanitized submission data.
	 * @return array Processed data without local protection metadata.
	 */
	public function cf7_processed_data( array $posted ): array {
		unset( $posted['rktsky_antispam_token'] );

		foreach ( array_keys( $posted ) as $name ) {
			if ( is_string( $name ) && 1 === preg_match( '/^rktsky_as_[a-f0-9]{20}$/D', $name ) ) {
				unset( $posted[ $name ] );
			}
		}

		return $posted;
	}

	/**
	 * Preserves earlier spam decisions and blocks local proof failures.
	 *
	 * Raw POST is intentional: Contact Form 7 excludes technical metadata from
	 * its processed posted_data, making that collection unsuitable for this check.
	 *
	 * @since 0.1.0
	 * @param bool                   $spam       Existing provider spam decision.
	 * @param \WPCF7_Submission|null $submission Native submission object.
	 * @return bool Combined spam decision.
	 */
	public function check_cf7( $spam, $submission = null ): bool {
		if ( ! $submission ) {
			$submission = \WPCF7_Submission::get_instance();
		}
		$form    = $submission ? $submission->get_contact_form() : null;
		$form_id = $form ? (string) $form->id() : '0';
		unset( $this->cf7_errors[ $form_id ] );

		if ( $spam ) {
			$this->guard->observe_spam( 'cf7', $form_id, $this->posted() );
			if ( $this->retention_enabled() ) {
				$this->retain( 'cf7', $form_id, 'native_spam', $this->cf7_content( $form, $submission ) );
			}
			return true;
		}

		$data   = $this->cf7_content( $form, $submission );
		$reason = $this->guard->check( 'cf7', $form_id, $this->posted(), $data['fields'] );

		if ( '' === $reason ) {
			return false;
		}

		$this->cf7_errors[ $form_id ] = true;
		$this->retain( 'cf7', $form_id, $reason, $data );

		if ( $submission ) {
			$submission->add_spam_log(
				array(
					'agent'  => 'wp-antispam',
					'reason' => 'Local protection check failed.',
				)
			);
		}

		return true;
	}

	/**
	 * Offers a retry message only for Contact Form 7 submissions rejected here.
	 *
	 * @since 0.1.0
	 * @param string $message Native provider message.
	 * @param string $status  Provider message key.
	 * @return string Native message or a neutral local retry instruction.
	 */
	public function cf7_message( string $message, string $status ): string {
		$form = \WPCF7_ContactForm::get_current();

		if ( 'spam' === $status && $form && ! empty( $this->cf7_errors[ (string) $form->id() ] ) ) {
			return $this->guard->message();
		}

		return $message;
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
	 * Renders protection inside WPForms' native form in both Lite and Pro.
	 *
	 * @since 0.1.0
	 * @param array $form Native form definition.
	 * @return void
	 */
	public function wpforms_fields( array $form ): void {
		if ( ! empty( $form['id'] ) ) {
			echo $this->fields->markup( 'wpforms', (string) absint( $form['id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by Fields::markup().
		}
	}

	/**
	 * Stops WPForms before entry persistence and notifications using native errors.
	 *
	 * @since 0.1.0
	 * @param array $fields Provider-sanitized fields, limited by native type below.
	 * @param array $entry  Native submitted entry, deliberately not inspected.
	 * @param array $form   Native form definition.
	 * @return void
	 */
	public function check_wpforms( array $fields, array $entry, array $form ): void {
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		$data    = $this->wpforms_content( $fields, $form );
		$reason  = $this->guard->check( 'wpforms', (string) $form_id, $this->posted(), $data['fields'] );

		if ( '' === $reason ) {
			return;
		}

		$this->retain( 'wpforms', (string) $form_id, $reason, $data );
		$this->wpforms_captured[ $form_id ] = true;
		$process                            = wpforms()->process;

		// Preserve an earlier provider or integration error at the same location.
		if ( empty( $process->errors[ $form_id ]['header'] ) ) {
			$process->errors[ $form_id ]['header'] = $this->guard->message();
		}
	}

	/**
	 * Observes actual native spam/trash insertion without changing its status.
	 *
	 * The supplied comment is already stored by Core. No historical query or
	 * manual trash transition is treated as a newly received spam submission.
	 *
	 * @since 0.2.0
	 * @param int         $comment_id Newly inserted native comment ID.
	 * @param \WP_Comment $comment    Native inserted comment object.
	 * @return void
	 */
	public function observe_comment_spam( $comment_id, $comment ): void {
		$post_id = (string) absint( $comment->comment_post_ID );
		if ( ! array_key_exists( $post_id, $this->comment_evidence ) ) {
			return;
		}
		$evidence = $this->comment_evidence[ $post_id ];
		unset( $this->comment_evidence[ $post_id ] );

		if ( null !== $this->feedback && ! in_array( $comment->comment_type, array( 'pingback', 'trackback' ), true ) ) {
			$this->feedback->track( 'comments', $comment_id, in_array( $comment->comment_approved, array( 'spam', 'trash' ), true ) );
		}
		if ( ! in_array( $comment->comment_approved, array( 'spam', 'trash' ), true ) || in_array( $comment->comment_type, array( 'pingback', 'trackback' ), true ) ) {
			return;
		}

		$this->guard->observe_spam( 'comments', $post_id, $evidence );
		// Core already retains native spam; a second quarantine copy would duplicate quality data.
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
	 * Starts a WPForms lifecycle before its modern early spam check can return.
	 *
	 * @since 0.2.0
	 * @param array $form  Native form definition.
	 * @param array $entry Unvalidated provider entry, deliberately not inspected.
	 * @return array Unmodified native form definition.
	 */
	public function begin_wpforms( array $form, array $entry ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Raw entry is part of the native filter contract and is intentionally not inspected.
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		unset( $this->wpforms_captured[ $form_id ] );

		return $form;
	}

	/**
	 * Observes WPForms' original honeypot verdict before its early return.
	 *
	 * @since 0.2.0
	 * @param bool|string $result Native honeypot rejection or false.
	 * @param array       $fields Provider-sanitized field metadata and values.
	 * @param array       $entry  Raw entry, deliberately not inspected.
	 * @param array       $form   Native form definition.
	 * @return bool|string Unmodified native verdict.
	 */
	public function observe_wpforms_honeypot( $result, array $fields, array $entry, array $form ) {
		if ( $result ) {
			$this->capture_wpforms_native( $fields, $form );
		}

		return $result;
	}

	/**
	 * Observes WPForms' token-filter result without changing its boolean meaning.
	 *
	 * @since 0.2.0
	 * @param bool  $valid  Existing provider proof verdict; false rejects.
	 * @param array $fields Provider-sanitized field metadata and values.
	 * @param array $entry  Raw entry, deliberately not inspected.
	 * @param array $form   Native form definition.
	 * @return bool Unmodified provider verdict.
	 */
	public function observe_wpforms_antispam( $valid, array $fields, array $entry, array $form ): bool {
		if ( ! $valid ) {
			$this->capture_wpforms_native( $fields, $form );
		}

		return (bool) $valid;
	}

	/**
	 * Observes modern WPForms spam errors after processing, including early exits.
	 *
	 * @since 0.2.0
	 * @param array $fields   Provider-sanitized field metadata and values.
	 * @param int   $entry_id Native entry ID, unused for content extraction.
	 * @param array $form     Native form definition.
	 * @return void
	 */
	public function observe_wpforms_spam( array $fields, $entry_id, array $form ): void {
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		$process = wpforms()->process;
		if ( ! empty( $process->spam_errors[ $form_id ] ) ) {
			$this->capture_wpforms_native( $fields, $form );
		}
	}

	/**
	 * Handles one native WPForms rejection per lifecycle using selected fields.
	 *
	 * @since 0.2.0
	 * @param array $fields Provider-sanitized field metadata and values.
	 * @param array $form   Native form definition.
	 * @return void
	 */
	private function capture_wpforms_native( array $fields, array $form ): void {
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		if ( ! empty( $this->wpforms_captured[ $form_id ] ) ) {
			return;
		}
		$this->wpforms_captured[ $form_id ] = true;
		$this->guard->observe_spam( 'wpforms', (string) $form_id, $this->posted() );
		if ( $this->retention_enabled() ) {
			$data = ! empty( $fields ) ? $this->wpforms_content( $fields, $form ) : $this->wpforms_raw_content( $form );
			$this->retain( 'wpforms', (string) $form_id, 'native_spam', $data );
		}
	}

	/**
	 * Selects only the public comment fields needed to review a rejected message.
	 *
	 * @since 0.2.0
	 * @param array $comment  Native comment data.
	 * @param bool  $slashed  Whether the native caller supplies WordPress slashes.
	 * @return array{fields:array<string,string>,email:string} Bounded selected data.
	 */
	private function comment_content( array $comment, bool $slashed ): array {
		$data = array(
			'fields' => array(),
			'email'  => '',
		);
		foreach ( array(
			'comment_content'      => 'content',
			'comment_author'       => 'name',
			'comment_author_email' => 'email',
			'comment_author_url'   => 'url',
		) as $key => $name ) {
			if ( isset( $comment[ $key ] ) && is_string( $comment[ $key ] ) ) {
				$value = $slashed ? wp_unslash( $comment[ $key ] ) : $comment[ $key ];
				$this->add_content( $data, $name, $value, 'email' === $name );
			}
		}

		return $data;
	}

	/**
	 * Selects CF7 content using declared native form tags and processed values.
	 *
	 * Only text, email, URL, telephone, textarea, selection, number and date tags
	 * participate. Unknown POST keys and all technical fields remain excluded.
	 *
	 * @since 0.2.0
	 * @param \WPCF7_ContactForm|null $form       Native form definition.
	 * @param \WPCF7_Submission|null  $submission Current native submission.
	 * @return array{fields:array<string,string>,email:string} Bounded selected data.
	 */
	private function cf7_content( $form, $submission ): array {
		$data = array(
			'fields' => array(),
			'email'  => '',
		);
		if ( ! $form || ! $submission ) {
			return $data;
		}
		$posted = $submission->get_posted_data();
		if ( ! is_array( $posted ) ) {
			return $data;
		}
		$allowed = array( 'text', 'email', 'url', 'tel', 'textarea', 'select', 'checkbox', 'radio', 'number', 'date' );
		foreach ( $form->scan_form_tags() as $tag ) {
			if ( ! is_object( $tag ) || ! isset( $tag->basetype, $tag->name ) || ! in_array( $tag->basetype, $allowed, true ) || ! is_string( $tag->name ) ) {
				continue;
			}
			$name = $tag->name;
			if ( isset( $posted[ $name ] ) ) {
				$this->add_content( $data, $name, $posted[ $name ], 'email' === $tag->basetype );
			}
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}

		return $data;
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

	/**
	 * Selects WPForms values only when both native definitions and metadata agree.
	 *
	 * The value property alone is copied; raw entry data, provider metadata and
	 * hidden/password/payment/upload fields never enter the selected collection.
	 *
	 * @since 0.2.0
	 * @param array $fields Provider-sanitized field metadata and values.
	 * @param array $form   Stored native field definitions.
	 * @return array{fields:array<string,string>,email:string} Bounded selected data.
	 */
	private function wpforms_content( array $fields, array $form ): array {
		$data = array(
			'fields' => array(),
			'email'  => '',
		);
		foreach ( $fields as $id => $field ) {
			$definition = $form['fields'][ $id ] ?? null;
			if ( ! is_array( $field ) || ! is_array( $definition ) || ! isset( $definition['type'], $field['type'], $field['value'] ) || $definition['type'] !== $field['type'] || ! in_array( $definition['type'], self::WPFORMS_CONTENT_TYPES, true ) ) {
				continue;
			}
			$label = isset( $definition['label'] ) && is_string( $definition['label'] ) ? $definition['label'] : 'Field';
			$this->add_content( $data, (string) $id . ': ' . $label, $field['value'], 'email' === $definition['type'] );
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}

		return $data;
	}

	/**
	 * Selects and sanitizes declared WPForms values rejected before formatting.
	 *
	 * Modern native spam protection can return before sanitized fields exist.
	 * This fallback never changes provider validation: only declared text field
	 * IDs are read. Compound values accept known flat parts; objects, nested
	 * values, unknown IDs and hidden/password/payment/file types are excluded.
	 *
	 * @since 0.2.0
	 * @param array $form Stored native field definitions.
	 * @return array{fields:array<string,string>,email:string} Bounded sanitized data.
	 */
	private function wpforms_raw_content( array $form ): array {
		$data = array(
			'fields' => array(),
			'email'  => '',
		);
		// Selection must precede unslashing/sanitizing: only declared scalar controls below are read.
		$raw = isset( $_POST['wpforms']['fields'] ) && is_array( $_POST['wpforms']['fields'] ) ? $_POST['wpforms']['fields'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Observes a native rejection; selected scalar fields are explicitly unslashed and sanitized below.
		foreach ( (array) ( $form['fields'] ?? array() ) as $id => $definition ) {
			if ( ! is_array( $definition ) || ! isset( $definition['type'], $raw[ $id ] ) || ! in_array( $definition['type'], self::WPFORMS_CONTENT_TYPES, true ) ) {
				continue;
			}
			$type  = $definition['type'];
			$value = $raw[ $id ];
			if ( is_array( $value ) ) {
				if ( ! in_array( $type, array( 'name', 'checkbox', 'select', 'date-time' ), true ) ) {
					continue;
				}
				$parts = array();
				foreach ( array_slice( $value, 0, 30, true ) as $part_name => $part ) {
					$known = ( 'name' === $type && in_array( (string) $part_name, array( 'first', 'middle', 'last' ), true ) )
						|| ( 'date-time' === $type && in_array( (string) $part_name, array( 'date', 'time' ), true ) )
						|| ( in_array( $type, array( 'checkbox', 'select' ), true ) && ctype_digit( (string) $part_name ) );
					if ( $known && is_scalar( $part ) ) {
						$parts[] = sanitize_text_field( wp_unslash( substr( (string) $part, 0, 4096 ) ) );
					}
				}
				$value = implode( ', ', $parts );
			} elseif ( is_scalar( $value ) ) {
				$value = wp_unslash( substr( (string) $value, 0, 4096 ) );
				if ( 'textarea' === $type ) {
					$value = sanitize_textarea_field( $value );
				} elseif ( 'email' === $type ) {
					$value = is_email( $value ) ? sanitize_email( $value ) : sanitize_text_field( $value );
				} else {
					$value = sanitize_text_field( $value );
				}
			} else {
				continue;
			}
			$label = isset( $definition['label'] ) && is_string( $definition['label'] ) ? $definition['label'] : 'Field';
			$this->add_content( $data, (string) $id . ': ' . $label, $value, 'email' === $type );
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}

		return $data;
	}

	/**
	 * Adds a bounded plain value while excluding reserved protection names.
	 *
	 * Flat multi-choice arrays are supported; nested data and objects are ignored.
	 * Only a declared email field can populate the email export/erasure index.
	 *
	 * @since 0.2.0
	 * @param array  $data  Selected values and optional email index, updated in place.
	 * @param string $name  Native field name or label.
	 * @param mixed  $value Provider value for an explicitly allowed field.
	 * @param bool   $email Whether the native type is email.
	 * @return void
	 */
	private function add_content( array &$data, string $name, $value, bool $email = false ): void {
		if ( count( $data['fields'] ) >= 30 || str_contains( $name, 'rktsky_antispam' ) || str_contains( $name, 'rktsky_as_' ) ) {
			return;
		}
		$name = wp_check_invalid_utf8( substr( wp_strip_all_tags( $name ), 0, 160 ), true );
		if ( '' === $name ) {
			return;
		}
		if ( is_array( $value ) ) {
			$parts = array();
			foreach ( array_slice( $value, 0, 30 ) as $part ) {
				if ( is_scalar( $part ) ) {
					$parts[] = substr( (string) $part, 0, 4096 );
				}
			}
			$value = implode( ', ', $parts );
		}
		if ( ! is_scalar( $value ) ) {
			return;
		}
		$value                   = wp_check_invalid_utf8( substr( (string) $value, 0, 4096 ), true );
		$data['fields'][ $name ] = $value;
		if ( $email && '' === $data['email'] ) {
			if ( is_email( $value ) ) {
				$data['email'] = sanitize_email( $value );
			}
		}
	}

	/**
	 * Defers the privacy-mode, opt-in and cryptographic availability decision.
	 *
	 * @since 0.2.0
	 * @return bool Whether the quarantine service permits local retention.
	 */
	private function retention_enabled(): bool {
		return null !== $this->quarantine && $this->quarantine->enabled();
	}

	/**
	 * Hands only selected values to the independently bounded encrypted store.
	 *
	 * @since 0.2.0
	 * @param string $provider Native provider ID.
	 * @param string $form_id  Native form ID.
	 * @param string $reason   Fixed local or native rejection reason.
	 * @param array  $data     Selected field map and optional declared email value.
	 * @return void
	 */
	private function retain( string $provider, string $form_id, string $reason, array $data ): void {
		if ( $this->retention_enabled() ) {
			$this->quarantine->capture( $provider, $form_id, $reason, $data['fields'], $data['email'] );
		}
	}

	/**
	 * Retains only the plugin's own bounded token for final-status bookkeeping.
	 *
	 * @since 0.2.0
	 * @param array $posted Unslashed native or REST controls.
	 * @return array<string,string> Own technical token only; never form content.
	 */
	private function token_evidence( array $posted ): array {
		$token = $posted['rktsky_antispam_token'] ?? '';

		return is_string( $token ) && strlen( $token ) <= 400 ? array( 'rktsky_antispam_token' => $token ) : array();
	}

	/**
	 * Recognizes Core's authenticated backend reply intent without a frontend bypass.
	 *
	 * Core binds this AJAX action to the actual actor, post-edit capability and
	 * its reply nonce. No other admin request, role or frontend form is exempted.
	 *
	 * @since 0.2.0
	 * @param array $comment Native comment data including the actor set by Core.
	 * @param int   $post_id Target native post.
	 * @return bool Whether this is the narrowly authenticated Core backend reply.
	 */
	private function authorized_backend_reply( array $comment, int $post_id ): bool {
		$actor = get_current_user_id();
		if ( ! wp_doing_ajax() || ! doing_action( 'wp_ajax_replyto-comment' ) || $actor <= 0 || ! isset( $comment['user_id'] ) || (int) $comment['user_id'] !== $actor || ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		$nonce = isset( $_POST['_ajax_nonce-replyto-comment'] ) && is_string( $_POST['_ajax_nonce-replyto-comment'] ) ? sanitize_text_field( wp_unslash( $_POST['_ajax_nonce-replyto-comment'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The exact native reply intent is verified on the following line.
		return '' !== $nonce && false !== wp_verify_nonce( $nonce, 'replyto-comment' );
	}

	/**
	 * Returns WordPress-unslashed raw POST for guard-side shape validation.
	 *
	 * Only own protection fields and separately selected native content fields
	 * reach Guard. Raw provider controls remain untouched and are never dumped.
	 *
	 * @since 0.1.0
	 * @return array Submitted form controls or an empty collection.
	 */
	private function posted(): array {
		// Public anti-spam proof is verified by Guard; a WP nonce is not a bot check.
		return isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Guard validates a site-local protection token.
	}
}
