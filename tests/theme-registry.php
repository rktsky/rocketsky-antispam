<?php
/**
 * Verifies delayed theme detection and idempotent native hook registration.
 *
 * Only a version constant is simulated; no Divi rendering or submission engine
 * is claimed. Original provider/browser contracts are verified separately.
 *
 * @package RktskyAntispamTests
 * @since 0.3.0
 */
require __DIR__ . '/bootstrap.php';

/**
 * Counts unique owned Divi objects attached to native hooks.
 *
 * @since 0.3.0
 * @return int Number of request-scoped adapter instances.
 */
function rktsky_antispam_theme_adapters(): int {
	$objects = array();
	foreach ( array( 'template_redirect', 'et_module_shortcode_output', 'divi_module_wrapper_render' ) as $hook ) {
		foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array() as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) && 'Rktsky\\Antispam\\Integrations\\Divi' === get_class( $callback['function'][0] ) ) {
					$objects[ spl_object_id( $callback['function'][0] ) ] = true;
				}
			}
		}
	}
	return count( $objects );
}

$effective         = array_fill_keys( \Rktsky\Antispam\Providers::ids(), false );
$effective['divi'] = true;
$override          = static function () use ( &$effective ) {
	return $effective;
};
add_filter( 'pre_option_' . \Rktsky\Antispam\Settings::OPTION, $override );
try {
	if ( defined( 'ET_BUILDER_VERSION' ) || class_exists( '\ET\Builder\Packages\ModuleLibrary\ContactForm\ContactFormModule' ) ) {
		throw new RuntimeException( 'The late-theme fixture must start without Divi loaded.' );
	}
	$settings = new \Rktsky\Antispam\Settings();
	$tokens   = new \Rktsky\Antispam\Token();
	$registry = new \Rktsky\Antispam\Integrations( $settings, new \Rktsky\Antispam\Guard( $settings, $tokens, new \Rktsky\Antispam\Store() ), new \Rktsky\Antispam\Fields( $settings, $tokens ) );
	$registry->register();
	$registry->register_theme();
	if ( 0 !== rktsky_antispam_theme_adapters() ) {
		throw new RuntimeException( 'An unavailable theme must not load a validation adapter.' );
	}
	define( 'ET_BUILDER_VERSION', '4.27.9' );
	$registry->register_theme();
	if ( 1 !== rktsky_antispam_theme_adapters() ) {
		throw new RuntimeException( 'A theme loaded after plugins_loaded must register its provider.' );
	}
	$registry->register_theme();
	$registry->register();
	$registry->register_theme();
	if ( 1 !== rktsky_antispam_theme_adapters() ) {
		throw new RuntimeException( 'Repeated lifecycle detection must not duplicate native hooks.' );
	}
	$effective['divi'] = false;
	$disabled          = new \Rktsky\Antispam\Integrations( $settings, new \Rktsky\Antispam\Guard( $settings, $tokens, new \Rktsky\Antispam\Store() ), new \Rktsky\Antispam\Fields( $settings, $tokens ) );
	$disabled->register_theme();
	if ( 1 !== rktsky_antispam_theme_adapters() ) {
		throw new RuntimeException( 'A disabled integration must not register another adapter.' );
	}
	echo "PASS: 4 delayed theme-registration assertions.\n";
} finally {
	remove_filter( 'pre_option_' . \Rktsky\Antispam\Settings::OPTION, $override );
}
