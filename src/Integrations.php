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
	 * @param Settings $settings Plugin settings.
	 * @param Guard    $guard    Local submission guard.
	 * @param Fields   $fields   Protection-field renderer.
	 * @return void
	 */
	public function __construct( Settings $settings, Guard $guard, Fields $fields ) {
		$this->settings = $settings;
		$this->guard    = $guard;
		$this->fields   = $fields;
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
		}

		if ( $available['wpforms'] && $this->settings->enabled( 'wpforms' ) ) {
			add_action( 'wpforms_frontend_output', array( $this, 'wpforms_fields' ), 30 );
			add_action( 'wpforms_process', array( $this, 'check_wpforms' ), 5, 3 );
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
		$type   = isset( $comment['comment_type'] ) && is_string( $comment['comment_type'] ) ? $comment['comment_type'] : '';
		$post   = isset( $comment['comment_post_ID'] ) ? absint( $comment['comment_post_ID'] ) : 0;
		$reason = in_array( $type, array( 'pingback', 'trackback' ), true ) ? 'pingback' : $this->guard->check( 'comments', (string) $post, $this->posted() );

		if ( '' !== $reason ) {
			if ( 'pingback' === $reason ) {
				$this->guard->reject( 'comments', 'pingback' );
			}

			wp_die(
				esc_html( $this->guard->message() ),
				esc_html__( 'Comment not submitted', 'wp-antispam' ),
				array( 'response' => 403, 'back_link' => true )
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
	 * @param array|\WP_Error $comment Prepared comment or an earlier rejection.
	 * @param \WP_REST_Request $request REST request being processed.
	 * @return array|\WP_Error Accepted data or a neutral creation error.
	 */
	public function check_rest_comment( $comment, $request ) {
		if ( is_wp_error( $comment ) || ! empty( $request['id'] ) ) {
			return $comment;
		}

		$post   = isset( $comment['comment_post_ID'] ) ? absint( $comment['comment_post_ID'] ) : 0;
		$type   = isset( $comment['comment_type'] ) && is_string( $comment['comment_type'] ) ? $comment['comment_type'] : '';
		$params = $request->get_params();
		$reason = in_array( $type, array( 'pingback', 'trackback' ), true ) ? 'pingback' : $this->guard->check( 'comments', (string) $post, is_array( $params ) ? $params : array() );

		if ( '' !== $reason ) {
			if ( 'pingback' === $reason ) {
				$this->guard->reject( 'comments', 'pingback' );
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
	 * @param bool                    $spam       Existing provider spam decision.
	 * @param \WPCF7_Submission|null $submission Native submission object.
	 * @return bool Combined spam decision.
	 */
	public function check_cf7( $spam, $submission = null ): bool {
		if ( $spam ) {
			return true;
		}

		if ( ! $submission ) {
			$submission = \WPCF7_Submission::get_instance();
		}
		$form       = $submission ? $submission->get_contact_form() : null;
		$form_id    = $form ? (string) $form->id() : '0';
		$reason     = $this->guard->check( 'cf7', $form_id, $this->posted() );

		if ( '' === $reason ) {
			return false;
		}

		$this->cf7_errors[ $form_id ] = true;

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
		unset( $this->gravity_permits[ $form_id ], $this->gravity_errors[ $form_id ], $this->gravity_aborted[ $form_id ] );

		if ( empty( $result['is_valid'] ) ) {
			return $result;
		}

		$posted = $this->posted();

		if ( '' !== $this->guard->check( 'gravityforms', $form_id, $posted ) ) {
			$result['is_valid']               = false;
			$this->gravity_errors[ $form_id ] = true;
		} elseif ( in_array( $context, array( 'form-submit', 'api-submit' ), true ) ) {
			$this->gravity_permits[ $form_id ] = $this->gravity_fingerprint( $form_id, $posted );
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
			return true;
		}

		$posted      = $this->posted();
		$fingerprint = $this->gravity_fingerprint( $form_id, $posted );

		if ( '' !== $permit && '' !== $fingerprint && hash_equals( $permit, $fingerprint ) ) {
			return false;
		}

		if ( '' !== $this->guard->check( 'gravityforms', $form_id, $posted ) ) {
			$this->gravity_aborted[ $form_id ] = true;

			return true;
		}

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
	 * @param array $fields Provider-sanitized fields, deliberately not inspected.
	 * @param array $entry  Native submitted entry, deliberately not inspected.
	 * @param array $form   Native form definition.
	 * @return void
	 */
	public function check_wpforms( array $fields, array $entry, array $form ): void {
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;

		if ( '' === $this->guard->check( 'wpforms', (string) $form_id, $this->posted() ) ) {
			return;
		}

		$process = wpforms()->process;

		// Preserve an earlier provider or integration error at the same location.
		if ( empty( $process->errors[ $form_id ]['header'] ) ) {
			$process->errors[ $form_id ]['header'] = $this->guard->message();
		}
	}

	/**
	 * Returns WordPress-unslashed raw POST for guard-side shape validation.
	 *
	 * The guard validates only its own protection fields. Provider fields remain
	 * untouched and are never retained by this integration.
	 *
	 * @since 0.1.0
	 * @return array Submitted form controls or an empty collection.
	 */
	private function posted(): array {
		// Public anti-spam proof is verified by Guard; a WP nonce is not a bot check.
		return isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Guard validates a site-local protection token.
	}
}
