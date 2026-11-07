<?php
/**
 * Exercises native menu/screen authorization and shared read-only review metadata.
 *
 * Provider menu contracts use real WordPress menu APIs with synthetic capabilities;
 * this is not a claim that every provider's actual administration was browsed.
 * Settings and capabilities are overridden within this PHP process only. No user,
 * option, submission or count is written, and original packages remain unchanged.
 *
 * @package RktskyAntispamTests
 * @since 0.5.0
 */

use Rktsky\Antispam\{ContextNotices, Providers, Quarantine, Settings, SpamHandling};

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$rktsky_notice_assertions = 0;

/**
 * Fails on an observed screen, permission or destination regression.
 *
 * @since 0.5.0
 * @param bool   $condition Required behavior.
 * @param string $message Failure context without visitor data.
 * @return void
 */
function rktsky_notice_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_notice_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Captures the real native notice API output for an initialized screen.
 *
 * @since 0.5.0
 * @param ContextNotices $notices Owned read-only notice service.
 * @param string         $screen Native screen identifier or registered hook.
 * @return string Escaped HTML, or an empty string when the context is ineligible.
 */
function rktsky_notice_html( ContextNotices $notices, string $screen ): string {
	set_current_screen( $screen );
	$notices->prepare( get_current_screen() );
	ob_start();
	$notices->render();
	return (string) ob_get_clean();
}

$rktsky_notice_user = get_current_user_id();
$rktsky_notice_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
rktsky_notice_expect( ! empty( $rktsky_notice_admins ), 'A disposable fixture administrator is required.' );
wp_set_current_user( (int) $rktsky_notice_admins[0] );
$rktsky_notice_options = ( new Settings() )->all();
foreach ( Providers::ids() as $rktsky_notice_provider ) {
	$rktsky_notice_options[ $rktsky_notice_provider ] = true;
}
$rktsky_notice_options['privacy_mode'] = true;
$rktsky_notice_options['quarantine_enabled'] = false;
$rktsky_notice_grants = array( 'read' => true, 'manage_options' => true, 'rk_fixture_forms' => true, 'rk_fixture_entries' => true );

/**
 * Supplies only process-local settings through the documented option filter.
 *
 * @since 0.5.0
 * @return array Process-local validated test configuration.
 */
$rktsky_notice_option_filter = static function () use ( &$rktsky_notice_options ): array { return $rktsky_notice_options; };
/**
 * Models a custom role without persisting roles or changing user metadata.
 *
 * @since 0.5.0
 * @return array<string,bool> Exact native capability grants for this case.
 */
$rktsky_notice_cap_filter = static function () use ( &$rktsky_notice_grants ): array { return $rktsky_notice_grants; };
add_filter( 'pre_option_' . Settings::OPTION, $rktsky_notice_option_filter );
add_filter( 'user_has_cap', $rktsky_notice_cap_filter, PHP_INT_MAX );

try {
	$rktsky_notice_settings = new Settings();
	$rktsky_notice_handling = new SpamHandling( $rktsky_notice_settings, new Quarantine( $rktsky_notice_settings ) );
	$rktsky_notice_service = new ContextNotices( $rktsky_notice_settings, $rktsky_notice_handling );
	$rktsky_notice_native = $rktsky_notice_handling->describe( 'comments' );
	rktsky_notice_expect( 'native' === $rktsky_notice_native['mode'] && str_contains( $rktsky_notice_native['url'], 'comment_status=spam' ), 'Comments must retain their native spam destination.' );
	foreach ( array( 'elementor' ) as $rktsky_notice_provider ) {
		$rktsky_notice_route = $rktsky_notice_handling->describe( $rktsky_notice_provider );
		rktsky_notice_expect( 'discard' === $rktsky_notice_route['mode'] && str_contains( $rktsky_notice_route['description'], 'does not retain' ), 'Disabled quarantine must explicitly disclose no WP Antispam content storage.' );
	}
	foreach ( array( 'divi', 'registration', 'wc_checkout', 'wc_registration' ) as $rktsky_notice_provider ) {
		rktsky_notice_expect( 'blocked' === $rktsky_notice_handling->describe( $rktsky_notice_provider )['mode'], 'Technical-only scopes must never promise a content quarantine.' );
	}
	$rktsky_notice_wpforms = $rktsky_notice_handling->describe( 'wpforms' );
	$rktsky_notice_pro = is_callable( array( \Rktsky\Antispam\Integrations\WpForms::class, 'native_available' ) ) && \Rktsky\Antispam\Integrations\WpForms::native_available();
	rktsky_notice_expect( ( $rktsky_notice_pro ? 'conditional' : 'discard' ) === $rktsky_notice_wpforms['mode'], 'WPForms must distinguish Pro native capability from the Lite retention fallback.' );
	$rktsky_notice_options['privacy_mode'] = false;
	$rktsky_notice_options['quarantine_enabled'] = true;
	rktsky_notice_expect( 'quarantine' === $rktsky_notice_handling->local_retention()['mode'], 'Actual enabled encryption capability must expose local quarantine.' );
	$rktsky_notice_options['privacy_mode'] = true;
	rktsky_notice_expect( 'discard' === $rktsky_notice_handling->local_retention()['mode'], 'Privacy mode must prevent a local capture claim even if capture was previously selected.' );
	$rktsky_notice_options['cf7'] = false;
	rktsky_notice_expect( ! isset( $rktsky_notice_handling->directory()['cf7'] ), 'Disabled integrations must not appear as protected review destinations.' );
	$rktsky_notice_options['cf7'] = true;
	foreach ( $rktsky_notice_handling->directory() as $rktsky_notice_provider => $rktsky_notice_route ) {
		rktsky_notice_expect( ! empty( Providers::detected()[ $rktsky_notice_provider ] ), 'Undetected integrations must not appear in the review directory.' );
	}

	$rktsky_notice_inventory = array(
		array( 'cf7', 'wpcf7', 'wpcf7-dashboard' ),
		array( 'cf7', 'flamingo_inbound', 'flamingo' ),
		array( 'gravityforms', 'gf_entries', 'gf_edit_forms' ),
		array( 'wpforms', 'wpforms-overview', 'wpforms-overview' ),
		array( 'formidable', 'formidable-entries', 'formidable' ),
		array( 'fluentforms', 'fluent_forms_all_entries', 'fluent_forms' ),
		array( 'wsform', 'ws-form-submit', 'ws-form' ),
		array( 'elementor', 'e-form-submissions', 'elementor' ),
		array( 'divi', 'et_divi_options', 'et_divi_options' ),
		array( 'wc_checkout', 'wc-orders', 'woocommerce' ),
	);
	$rktsky_notice_hooks = array();
	$rktsky_notice_match = new ReflectionMethod( ContextNotices::class, 'screen_providers' );
	foreach ( $rktsky_notice_inventory as $rktsky_notice_page ) {
		list( $rktsky_notice_provider, $rktsky_notice_slug, $rktsky_notice_parent ) = $rktsky_notice_page;
		add_menu_page( 'Synthetic menu', 'Translated synthetic ' . $rktsky_notice_parent, 'rk_fixture_forms', $rktsky_notice_parent, '__return_null' );
		$rktsky_notice_hooks[ $rktsky_notice_slug ] = add_submenu_page( $rktsky_notice_parent, 'Synthetic page', 'Synthetic page', 'rk_fixture_entries', $rktsky_notice_slug, '__return_null' );
		set_current_screen( $rktsky_notice_hooks[ $rktsky_notice_slug ] );
		rktsky_notice_expect( array( $rktsky_notice_provider ) === $rktsky_notice_match->invoke( $rktsky_notice_service, get_current_screen() ), 'Registered translated native menu hooks must match their fixed integration.' );
	}

	$rktsky_notice_grants = array( 'read' => true, 'moderate_comments' => true );
	$rktsky_notice_html = rktsky_notice_html( $rktsky_notice_service, 'edit-comments' );
	rktsky_notice_expect( str_contains( $rktsky_notice_html, 'comment_status=spam' ) && ! str_contains( $rktsky_notice_html, 'page=wp-antispam' ), 'A comment moderator must receive the native queue without inaccessible WP Antispam links.' );
	$rktsky_notice_grants = array( 'read' => true );
	rktsky_notice_expect( '' === rktsky_notice_html( $rktsky_notice_service, 'edit-comments' ), 'A read-only subscriber must receive no protection notice on a forged comment screen.' );
	$rktsky_notice_grants = array( 'read' => true, 'rk_fixture_entries' => true );
	if ( ! empty( Providers::detected()['wpforms'] ) ) {
		$rktsky_notice_html = rktsky_notice_html( $rktsky_notice_service, $rktsky_notice_hooks['wpforms-overview'] );
		rktsky_notice_expect( str_contains( $rktsky_notice_html, 'WP Antispam is enabled' ) && ! str_contains( $rktsky_notice_html, '<a ' ), 'An authorized WPForms manager must receive text without privileged local links.' );
	}
	if ( ! empty( Providers::detected()['gravityforms'] ) ) {
		$rktsky_notice_html = rktsky_notice_html( $rktsky_notice_service, $rktsky_notice_hooks['gf_entries'] );
		rktsky_notice_expect( str_contains( $rktsky_notice_html, 'page=gf_entries' ) && ! str_contains( $rktsky_notice_html, 'page=wp-antispam' ), 'A native Gravity Forms entry manager must receive only the authorized native destination.' );
		$rktsky_notice_options['gravityforms'] = false;
		rktsky_notice_expect( '' === rktsky_notice_html( $rktsky_notice_service, $rktsky_notice_hooks['gf_entries'] ), 'An available but disabled provider must make no protection claim.' );
		$rktsky_notice_options['gravityforms'] = true;
		$rktsky_notice_grants['rk_fixture_forms'] = true;
		remove_submenu_page( 'gf_edit_forms', 'gf_entries' );
		remove_menu_page( 'gf_edit_forms' );
		$rktsky_notice_entry_hook = add_menu_page( 'Synthetic entries only', 'Translated entries only', 'rk_fixture_entries', 'gf_entries', '__return_null' );
		add_submenu_page( 'gf_entries', 'Synthetic entries', 'Synthetic entries', 'rk_fixture_entries', 'gf_entries', '__return_null' );
		unset( $rktsky_notice_grants['rk_fixture_forms'] );
		$rktsky_notice_html = rktsky_notice_html( $rktsky_notice_service, $rktsky_notice_entry_hook );
		rktsky_notice_expect( str_contains( $rktsky_notice_html, 'page=gf_entries' ) && str_contains( $rktsky_notice_html, 'gf-notice' ), 'An entry-only native role must retain its alternate top-level hook, inbox link and GF notice classification.' );
	}
	$rktsky_notice_grants = array( 'read' => true, 'manage_options' => true, 'moderate_comments' => true );
	foreach ( array( 'dashboard', 'plugins', 'edit-comments-network', 'profile-user', 'front' ) as $rktsky_notice_screen ) {
		rktsky_notice_expect( '' === rktsky_notice_html( $rktsky_notice_service, $rktsky_notice_screen ), 'Unrelated, network, user and frontend screens must receive no notice.' );
	}
	$rktsky_notice_options['comments'] = false;
	rktsky_notice_expect( '' === rktsky_notice_html( $rktsky_notice_service, 'edit-comments' ), 'Disabled comment protection must not emit a notice.' );
	$rktsky_notice_options['comments'] = true;
	$rktsky_notice_html = rktsky_notice_html( $rktsky_notice_service, 'edit-comments' );
	rktsky_notice_expect( str_contains( $rktsky_notice_html, 'notice-info' ) && ! str_contains( $rktsky_notice_html, 'is-dismissible' ), 'Guidance must use a native information notice without a global dismissal workflow.' );
	$rktsky_notice_grants = array( 'read' => true );
	ob_start(); $rktsky_notice_service->render(); $rktsky_notice_late = (string) ob_get_clean();
	rktsky_notice_expect( '' === $rktsky_notice_late, 'Capability revocation after screen preparation must suppress output.' );
	echo 'PASS: ' . $rktsky_notice_assertions . " native menu, role and review-metadata assertions; no persistent fixture writes.\n";
} finally {
	remove_filter( 'pre_option_' . Settings::OPTION, $rktsky_notice_option_filter );
	remove_filter( 'user_has_cap', $rktsky_notice_cap_filter, PHP_INT_MAX );
	wp_set_current_user( $rktsky_notice_user );
}
