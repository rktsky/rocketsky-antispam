<?php
/**
 * Protects native public account registration before persistence or activation mail.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Covers wp-login registration and the anonymous Multisite signup stages.
 *
 * Account creation through authorized administrative APIs remains Core-owned.
 * No submitted account fields, passwords or activation keys enter quarantine.
 *
 * @since 0.3.0
 */
final class Registration extends Adapter {
	/**
	 * Actual Core wp-login registration processing, distinct from generic user APIs.
	 *
	 * @since 0.3.0
	 * @var bool
	 */
	private bool $registration = false;

	/**
	 * Native wp-signup.php execution, never inferred from submitted route controls.
	 *
	 * @since 0.3.0
	 * @var bool
	 */
	private bool $signup = false;

	/**
	 * Registers public rendering and pre-creation validation hooks.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'login_form_register', array( $this, 'begin_registration' ) );
		add_action( 'register_form', array( $this, 'fields' ) );
		add_filter( 'registration_errors', array( $this, 'validate' ), PHP_INT_MAX, 3 );
		add_action( 'before_signup_header', array( $this, 'begin_signup' ) );
		add_action( 'signup_hidden_fields', array( $this, 'signup_fields' ) );
		add_filter( 'wpmu_validate_user_signup', array( $this, 'validate_signup' ), PHP_INT_MAX );
	}

	/**
	 * Marks Core's registration action before its native request handler executes.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function begin_registration(): void {
		$this->registration = true;
	}

	/**
	 * Prints controls inside Core's wp-login.php registration form.
	 *
	 * Core prints enqueued footer scripts through its login_footer action.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function fields(): void {
		echo $this->fields->markup( 'registration', 'register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fields renders escaped fixed markup.
	}

	/**
	 * Appends a generic failure before register_new_user inserts the account.
	 *
	 * Generic register_new_user calls from other native form integrations remain
	 * their adapter’s responsibility; they do not render Core login controls.
	 *
	 * @since 0.3.0
	 * @param \WP_Error $errors Existing native validation errors.
	 * @param string $login Native sanitized login, deliberately not inspected.
	 * @param string $email Native email, deliberately not inspected.
	 * @return \WP_Error Existing errors with an optional protection failure.
	 */
	public function validate( \WP_Error $errors, string $login, string $email ): \WP_Error {
		if ( $this->registration && ! $errors->has_errors() && '' !== $this->guard->check( 'registration', 'register', $this->protection_controls(), array(), true ) ) {
			$errors->add( 'rktsky_antispam', $this->guard->message() );
		}
		return $errors;
	}

	/**
	 * Marks native Multisite signup processing before its form handlers execute.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function begin_signup(): void {
		$this->signup = true;
	}

	/**
	 * Adds a fresh proof to both anonymous account and account-with-site stages.
	 *
	 * @since 0.3.0
	 * @param string $stage Native rendering stage, not a posted authorization value.
	 * @return void
	 */
	public function signup_fields( string $stage ): void {
		if ( in_array( $stage, array( 'validate-user', 'validate-site' ), true ) && ! is_user_logged_in() ) {
			echo $this->fields->markup( 'registration', 'signup' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fields renders escaped fixed markup.
		}
	}

	/**
	 * Rejects anonymous native network signup before signup rows and mail exist.
	 *
	 * Other plugins also use the generic validator for their own administrative
	 * workflows; only the actual wp-signup lifecycle belongs to this adapter.
	 *
	 * @since 0.3.0
	 * @param array $result Core username, email and WP_Error result.
	 * @return array Native result with an optional generic error.
	 */
	public function validate_signup( array $result ): array {
		if ( $this->signup && ! is_user_logged_in() && isset( $result['errors'] ) && $result['errors'] instanceof \WP_Error && ! $result['errors']->has_errors() && '' !== $this->guard->check( 'registration', 'signup', $this->protection_controls(), array(), true ) ) {
			$result['errors']->add( 'generic', $this->guard->message() );
		}
		return $result;
	}
}
