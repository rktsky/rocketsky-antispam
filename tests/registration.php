<?php
/**
 * Tests original account creation and the native network-signup error contract.
 *
 * @package RktskyAntispamTests
 * @since 0.3.0
 */
require __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/src/Integrations/Registration.php';

/**
 * Requires an observable pre-creation boundary without dumping account content.
 *
 * @since 0.3.0
 * @param bool $condition Required outcome.
 * @param string $message Failed invariant.
 * @return void
 */
function rktsky_antispam_registration_expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$GLOBALS['rktsky_registration_assertions'];
}

$GLOBALS['rktsky_registration_assertions'] = 0;
$options = get_option( \Rktsky\Antispam\Settings::OPTION );
$created = array();
$settings = new \Rktsky\Antispam\Settings();
$token = new \Rktsky\Antispam\Token();
$store = new \Rktsky\Antispam\Store();
$guard = new \Rktsky\Antispam\Guard( $settings, $token, $store, new \Rktsky\Antispam\ContentRules( $settings, new \Rktsky\Antispam\Language(), new \Rktsky\Antispam\Country( $settings ) ) );
$adapter = new \Rktsky\Antispam\Integrations\Registration( $settings, $guard, new \Rktsky\Antispam\Fields( $settings, $token ) );
wp_set_current_user( 0 );
add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
try {
	update_option( \Rktsky\Antispam\Settings::OPTION, array_merge( $settings->all(), array( 'registration' => true, 'minimum_seconds' => 0, 'spam_keywords' => 'fixture', 'privacy_mode' => false, 'country_mode' => 'block', 'countries' => array( 'CH' ), 'country_unknown_allowed' => false ) ) );
	$adapter->register();
	$outside = new WP_Error();
	rktsky_antispam_registration_expect( $adapter->validate( $outside, 'fixture', 'fixture@example.invalid' ) === $outside && ! $outside->has_errors(), 'Other providers using Core registration APIs must retain their own form protection scope.' );
	do_action( 'login_form_register' );
	ob_start();
	do_action( 'register_form' );
	$html = ob_get_clean();
	rktsky_antispam_registration_expect( str_contains( $html, 'data-provider="registration"' ) && wp_script_is( 'rktsky-antispam', 'enqueued' ), 'Native registration must render and enqueue protection.' );
	foreach ( array( 'missing', 'valid', 'replay', 'honeypot', 'wrong_scope', 'array', 'trap_missing' ) as $case ) {
		$login = 'fixture_' . bin2hex( random_bytes( 8 ) );
		$_POST = array();
		if ( 'missing' !== $case ) {
			if ( 'replay' !== $case ) {
				$proof = $token->issue( 'registration', 'wrong_scope' === $case ? 'signup' : 'register' );
			}
			$_POST['rktsky_antispam_token'] = 'array' === $case ? array( $proof['token'] ) : $proof['token'];
			if ( 'trap_missing' !== $case ) {
				$_POST[ $proof['trap'] ] = 'honeypot' === $case ? 'filled' : '';
			}
		}
		// Test the original public Core creation function, not just our callback.
		$result = register_new_user( $login, $login . '@example.invalid' );
		if ( 'valid' === $case ) {
			rktsky_antispam_registration_expect( is_int( $result ) && $result > 0, 'Native registration rejected fresh valid technical proof or applied content/country rules.' );
			$created[] = $result;
		} else {
			rktsky_antispam_registration_expect( is_wp_error( $result ) && in_array( 'rktsky_antispam', $result->get_error_codes(), true ) && ! username_exists( $login ), 'Rejected account registration created a user or lost its native visible error: ' . $case );
		}
	}
	$existing = new WP_Error( 'native', 'Original native error' );
	rktsky_antispam_registration_expect( $adapter->validate( $existing, '', '' ) === $existing && array( 'native' ) === $existing->get_error_codes(), 'An earlier native registration error must remain intact.' );
	$_POST = array();
	$login = 'fixture_' . bin2hex( random_bytes( 8 ) );
	$id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'user_email' => $login . '@example.invalid' ) );
	rktsky_antispam_registration_expect( is_int( $id ) && $id > 0, 'Authorized server-side Core user creation must retain its own separate API.' );
	$created[] = $id;
	$base = array( 'user_name' => 'synthetic', 'user_email' => 'fixture@example.invalid', 'errors' => new WP_Error() );
	rktsky_antispam_registration_expect( ! $adapter->validate_signup( $base )['errors']->has_errors(), 'Generic network validation outside wp-signup must remain available to provider APIs.' );
	$adapter->begin_signup();
	$rejected = $adapter->validate_signup( $base );
	rktsky_antispam_registration_expect( in_array( 'generic', $rejected['errors']->get_error_codes(), true ), 'Anonymous native signup must expose the protection failure in Core generic errors.' );
	$proof = $token->issue( 'registration', 'signup' );
	$_POST = array( 'rktsky_antispam_token' => $proof['token'], $proof['trap'] => '' );
	$base['errors'] = new WP_Error();
	rktsky_antispam_registration_expect( ! $adapter->validate_signup( $base )['errors']->has_errors(), 'Fresh network-stage proof must pass the shared guard.' );
	$base['errors'] = new WP_Error();
	rktsky_antispam_registration_expect( $adapter->validate_signup( $base )['errors']->has_errors(), 'Network signup must not reuse consumed proof.' );
	foreach ( \Rktsky\Antispam\Providers::ids() as $provider ) {
		rktsky_antispam_registration_expect( strlen( $provider ) <= 20 && '' !== $token->intent( $provider, '42' ), 'Every provider must fit its persisted scope and signed protocol.' );
	}
	foreach ( array( 'registration', 'wc_checkout', 'wc_registration' ) as $provider ) {
		rktsky_antispam_registration_expect( ! in_array( $provider, \Rktsky\Antispam\Providers::content_ids(), true ), 'Account and checkout scopes must be excluded by the quarantine store boundary.' );
	}
	$default = $settings->defaults();
	foreach ( array( 'registration', 'wc_checkout', 'wc_registration', 'formidable', 'fluentforms', 'elementor', 'wsform', 'divi' ) as $provider ) {
		rktsky_antispam_registration_expect( false === $default[ $provider ], 'New adapters must require explicit enablement.' );
	}
} finally {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $created as $id ) {
		wp_delete_user( $id );
	}
	update_option( \Rktsky\Antispam\Settings::OPTION, $options );
	$_POST = array();
}
echo 'PASS: ' . $GLOBALS['rktsky_registration_assertions'] . " account and provider-scope assertions.\n";
