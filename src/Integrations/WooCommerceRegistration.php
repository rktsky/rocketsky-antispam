<?php
/**
 * Protects WooCommerce's native My Account registration before customer creation.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps standalone registration separate from accounts created during checkout.
 *
 * @since 0.3.0
 */
final class WooCommerceRegistration extends Adapter {
	/**
	 * Registers the native renderer and the pre-creation form-validation filter.
	 *
	 * This filter belongs to WC_Form_Handler::process_registration(), rather than
	 * wc_create_new_customer(), which also serves checkout and administrative APIs.
	 * No password, email, address or other registration content is inspected here.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_register_form', array( $this, 'render' ) );
		add_filter( 'woocommerce_process_registration_errors', array( $this, 'validate' ) );
	}

	/**
	 * Prints escaped local controls inside the native registration form.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function render(): void {
		echo $this->fields->markup( 'wc_registration', 'register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fields::markup() escapes its own complete output.
	}

	/**
	 * Rejects a native registration before WooCommerce can create the account.
	 *
	 * WooCommerce has already checked the registration nonce at this boundary.
	 * Technical evidence is checked once here; shared customer-creation hooks are
	 * deliberately not registered, so checkout account creation is not checked twice.
	 *
	 * @since 0.3.0
	 * @param \WP_Error $errors Existing native registration errors.
	 * @return \WP_Error Preserved native errors with a neutral local rejection if needed.
	 */
	public function validate( \WP_Error $errors ): \WP_Error {
		if ( '' !== $this->guard->check( 'wc_registration', 'register', $this->protection_controls(), array(), true ) ) {
			$errors->add( 'rktsky_antispam_rejected', $this->guard->message() );
		}
		return $errors;
	}
}
