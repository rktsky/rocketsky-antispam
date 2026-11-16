<?php
/**
 * Defines stable provider scopes and their independently loaded adapters.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps protocol, persistence and interface provider identifiers consistent.
 *
 * @since 0.3.0
 */
final class Providers {
	/**
	 * Explicit owned class map; request values never become filesystem paths.
	 *
	 * @since 0.3.0
	 * @var array<string,string>
	 */
	public const ADAPTERS = array(
		'comments'        => 'Comments',
		'registration'    => 'Registration',
		'cf7'             => 'ContactForm7',
		'gravityforms'    => 'GravityForms',
		'wpforms'         => 'WpForms',
		'formidable'      => 'Formidable',
		'fluentforms'     => 'Fluent',
		'wsform'          => 'WsForm',
		'elementor'       => 'Elementor',
		'divi'            => 'Divi',
		'wc_checkout'     => 'WooCommerceCheckout',
		'wc_registration' => 'WooCommerceRegistration',
	);

	/**
	 * Returns bounded identifiers shared by storage and signed challenge scopes.
	 *
	 * @since 0.3.0
	 * @return string[] Known provider IDs.
	 */
	public static function ids(): array {
		return array_keys( self::ADAPTERS );
	}

	/**
	 * Excludes account and payment scopes from content retention at the store boundary.
	 *
	 * @since 0.3.0
	 * @return string[] Providers allowed to retain selected contact fields.
	 */
	public static function content_ids(): array {
		return array_values( array_diff( self::ids(), array( 'registration', 'wc_checkout', 'wc_registration', 'divi' ) ) );
	}

	/**
	 * Detects public provider contracts without loading owned integration modules.
	 *
	 * Call after plugins_loaded. Version floors refer to reviewed hook contracts,
	 * not a claim that every commercial add-on or checkout gateway was tested.
	 *
	 * @since 0.3.0
	 * @return array<string,bool> Availability by stable provider ID.
	 */
	public static function detected(): array {
		$woo = defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '9.9', '>=' ) && class_exists( '\WooCommerce' );
		return array(
			'comments'        => true,
			'registration'    => true,
			'cf7'             => class_exists( '\WPCF7_ContactForm' ) && class_exists( '\WPCF7_Submission' ),
			'gravityforms'    => class_exists( '\GFForms' ) && class_exists( '\GFFormDisplay' ) && isset( \GFForms::$version ) && version_compare( \GFForms::$version, '2.7', '>=' ),
			'wpforms'         => function_exists( 'wpforms' ),
			'formidable'      => class_exists( '\FrmAppHelper' ) && class_exists( '\FrmEntry' ) && is_callable( array( '\FrmAppHelper', 'plugin_version' ) ) && version_compare( \FrmAppHelper::plugin_version(), '6.2', '>=' ),
			'fluentforms'     => defined( 'FLUENTFORM_VERSION' ) && version_compare( FLUENTFORM_VERSION, '6.0', '>=' ) && function_exists( 'wpFluentForm' ),
			'wsform'          => defined( 'WS_FORM_VERSION' ) && version_compare( WS_FORM_VERSION, '1.12.19', '>=' ) && class_exists( '\WS_Form_Submit' ) && class_exists( '\WS_Form_Common' ),
			'elementor'       => defined( 'ELEMENTOR_PRO_VERSION' ) && class_exists( '\ElementorPro\Modules\Forms\Module' ),
			'divi'            => ( defined( 'ET_BUILDER_VERSION' ) && version_compare( ET_BUILDER_VERSION, '4.27.9', '>=' ) ) || class_exists( '\ET\Builder\Packages\ModuleLibrary\ContactForm\ContactFormModule' ),
			'wc_checkout'     => $woo,
			'wc_registration' => $woo,
		);
	}

	/**
	 * Returns display labels only when the interface needs translations.
	 *
	 * @since 0.3.0
	 * @return array<string,string> Localized names by provider ID.
	 */
	public static function labels(): array {
		return array(
			'comments'        => __( 'WordPress comments', 'rocketsky-antispam' ),
			'registration'    => __( 'WordPress registration', 'rocketsky-antispam' ),
			'cf7'             => 'Contact Form 7',
			'gravityforms'    => 'Gravity Forms',
			'wpforms'         => 'WPForms',
			'formidable'      => 'Formidable Forms',
			'fluentforms'     => 'Fluent Forms',
			'wsform'          => 'WS Form',
			'elementor'       => 'Elementor Forms',
			'divi'            => __( 'Divi contact form', 'rocketsky-antispam' ),
			'wc_checkout'     => __( 'WooCommerce checkout', 'rocketsky-antispam' ),
			'wc_registration' => __( 'WooCommerce registration', 'rocketsky-antispam' ),
		);
	}
}
