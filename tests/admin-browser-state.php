<?php
/**
 * Provides bounded native settings IPC for the disposable administrator browser test.
 *
 * browser-admin.mjs keeps the original value in process memory and restores it
 * through the Options API. No credentials, filesystem paths or submission values
 * are recorded by this helper; it never changes Core/provider data directly.
 *
 * @package RktskyAntispamTests
 * @since 0.4.0
 */

use Rktsky\Antispam\Settings;

require __DIR__ . '/bootstrap.php';

$rktsky_admin_origin = untrailingslashit( home_url() );
$rktsky_admin_host   = wp_parse_url( $rktsky_admin_origin, PHP_URL_HOST );
if ( ! in_array( $rktsky_admin_host, array( '127.0.0.1', '::1', '[::1]' ), true ) ) {
	throw new RuntimeException( 'Administrator browser tests require an explicit loopback site.' );
}
$rktsky_admin_mode = $argv[1] ?? 'state';
if ( 'identity' === $rktsky_admin_mode ) {
	echo wp_json_encode(
		array(
			'origin'       => $rktsky_admin_origin,
			'admin'        => admin_url( 'options-general.php?page=rocketsky-antispam' ),
			'login'        => wp_login_url(),
			'wordpress'    => get_bloginfo( 'version' ),
			'mail_blocked' => false !== has_filter( 'pre_wp_mail' ),
			'http_blocked' => is_wp_error( apply_filters( 'pre_http_request', false, array(), 'https://example.invalid/rktsky-fixture-check' ) ),
		)
	);
} elseif ( 'state' === $rktsky_admin_mode ) {
	echo wp_json_encode( get_option( Settings::OPTION, null ) );
} elseif ( 'seed' === $rktsky_admin_mode ) {
	$rktsky_admin_options                       = ( new Settings() )->all();
	$rktsky_admin_options['privacy_mode']       = false;
	$rktsky_admin_options['quarantine_enabled'] = false;
	$rktsky_admin_options['notify_admin']       = false;
	$rktsky_admin_options['spam_keywords']      = 'synthetic-admin-browser-preserved';
	$rktsky_admin_options['bbcode_filter']      = false;
	$rktsky_admin_options['allowed_languages']  = array( 'de' );
	$rktsky_admin_options['minimum_seconds']    = 0;
	$rktsky_admin_options['retention_days']     = 30;
	$rktsky_admin_options['comments']           = true;
	update_option( Settings::OPTION, $rktsky_admin_options, false );
	echo wp_json_encode( array( 'prepared' => true ) );
} elseif ( 'restore' === $rktsky_admin_mode ) {
	$rktsky_admin_json = $argv[2] ?? '';
	if ( strlen( $rktsky_admin_json ) > 131072 ) {
		throw new RuntimeException( 'The disposable settings snapshot exceeds its input bound.' );
	}
	$rktsky_admin_original = json_decode( $rktsky_admin_json, true, 32, JSON_THROW_ON_ERROR );
	if ( null !== $rktsky_admin_original && ! is_array( $rktsky_admin_original ) ) {
		throw new RuntimeException( 'The original settings snapshot must be an array or null.' );
	}
	if ( null === $rktsky_admin_original ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $rktsky_admin_original, false );
	}
	echo wp_json_encode( array( 'restored' => $rktsky_admin_original === get_option( Settings::OPTION, null ) ) );
} else {
	throw new RuntimeException( 'Unknown disposable administrator fixture operation.' );
}
