<?php
/**
 * Verifies actual retain/delete uninstall paths on the marked disposable database.
 *
 * @package RktskyAntispamTests
 * @since 0.1.0
 */

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$rktsky_uninstall_assertions = 0;

/**
 * Stops on an uninstall boundary violation.
 *
 * @since 0.1.0
 * @param bool $condition Required storage behavior.
 * @param string $message Failure label.
 * @return void
 * @throws RuntimeException When an uninstall boundary is violated.
 */
function rktsky_antispam_uninstall_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_uninstall_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

global $wpdb;
$settings = new \Rktsky\Antispam\Settings();
$original = $settings->all();
\Rktsky\Antispam\Plugin::activate();
$before_tables = $wpdb->get_col( 'SHOW TABLES' );
$owned_tables = array( $wpdb->prefix . 'rktsky_antispam_tokens', $wpdb->prefix . 'rktsky_antispam_stats', $wpdb->prefix . 'rktsky_antispam_totals', $wpdb->prefix . 'rktsky_antispam_quarantine', $wpdb->prefix . 'rktsky_antispam_feedback', $wpdb->prefix . 'rktsky_antispam_jobs' );
$foreign_before = array_values( array_diff( $before_tables, $owned_tables ) );
sort( $foreign_before );
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugin = plugin_basename( RKTSKY_ANTISPAM_FILE );

// Preferences use native user metadata; no Core/provider table is queried directly.
$rktsky_uninstall_pref_key = $wpdb->get_blog_prefix() . \Rktsky\Antispam\ContextNotices::USER_OPTION;
$rktsky_uninstall_pref_before = array();
$rktsky_uninstall_existing = get_users(
	array(
		'meta_key' => $rktsky_uninstall_pref_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Exact-key bounded snapshot preserves existing disposable preferences.
		'fields'   => 'ID',
		'number'   => 201,
	)
);
rktsky_antispam_uninstall_expect( count( $rktsky_uninstall_existing ) <= 200, 'The bounded disposable preference snapshot must be complete before uninstall.' );
foreach ( $rktsky_uninstall_existing as $rktsky_uninstall_user ) {
	$rktsky_uninstall_pref_before[ (int) $rktsky_uninstall_user ] = get_user_meta( (int) $rktsky_uninstall_user, $rktsky_uninstall_pref_key, false );
}
$rktsky_uninstall_users = array();
for ( $rktsky_uninstall_index = 0; $rktsky_uninstall_index < 2; ++$rktsky_uninstall_index ) {
	$rktsky_uninstall_login = 'rk-uninstall-' . wp_generate_uuid4();
	$rktsky_uninstall_user = wp_insert_user(
		array(
			'user_login' => $rktsky_uninstall_login,
			'user_email' => $rktsky_uninstall_login . '@example.invalid',
			'user_pass'  => wp_generate_password( 32, true, true ),
			'role'       => 'subscriber',
		)
	);
	rktsky_antispam_uninstall_expect( is_int( $rktsky_uninstall_user ) && $rktsky_uninstall_user > 0, 'A native temporary preference owner must be created.' );
	$rktsky_uninstall_users[] = $rktsky_uninstall_user;
	update_user_option(
		$rktsky_uninstall_user,
		\Rktsky\Antispam\ContextNotices::USER_OPTION,
		array(
			'site'      => get_current_blog_id(),
			'providers' => array( 'comments' => true ),
		),
		false
	);
	update_user_meta( $rktsky_uninstall_user, 'rktsky_fixture_unrelated_preference', 'Preserve unrelated metadata.' );
}

// Core only uninstalls inactive plugins; exercise that native lifecycle boundary.
deactivate_plugins( $plugin );
try {
	$retain = $original;
	$retain['delete_on_uninstall'] = false;
	update_option( \Rktsky\Antispam\Settings::OPTION, $retain, false );
	define( 'WP_UNINSTALL_PLUGIN', 'rocketsky-antispam/rocketsky-antispam.php' );
	require dirname( __DIR__ ) . '/uninstall.php';
	$after_retain = $wpdb->get_col( 'SHOW TABLES' );
	rktsky_antispam_uninstall_expect( count( array_intersect( $owned_tables, $after_retain ) ) === count( $owned_tables ), 'Retain mode must preserve every owned table.' );
	rktsky_antispam_uninstall_expect( false !== get_option( \Rktsky\Antispam\Settings::OPTION ), 'Retain mode must preserve settings.' );
	rktsky_antispam_uninstall_expect( false === wp_next_scheduled( 'rktsky_antispam_cleanup' ), 'Uninstall must remove its own scheduled task.' );

	foreach ( $rktsky_uninstall_users as $rktsky_uninstall_user ) {
		$rktsky_uninstall_preference = get_user_option( \Rktsky\Antispam\ContextNotices::USER_OPTION, $rktsky_uninstall_user );
		rktsky_antispam_uninstall_expect( get_current_blog_id() === $rktsky_uninstall_preference['site'] && array( 'comments' => true ) === $rktsky_uninstall_preference['providers'], 'Retain mode must preserve each account\'s own per-site display preference.' );
	}

	$delete = $original;
	$delete['delete_on_uninstall'] = true;
	update_option( \Rktsky\Antispam\Settings::OPTION, $delete, false );
	rktsky_antispam_uninstall_site();
	$after_delete = $wpdb->get_col( 'SHOW TABLES' );
	rktsky_antispam_uninstall_expect( array() === array_values( array_intersect( $owned_tables, $after_delete ) ), 'Delete mode must drop every owned table.' );
	rktsky_antispam_uninstall_expect( false === get_option( \Rktsky\Antispam\Settings::OPTION ) && false === get_option( 'rktsky_antispam_schema' ), 'Delete mode must remove only owned settings and schema state.' );
	$foreign_after = array_values( array_diff( $after_delete, $owned_tables ) );
	sort( $foreign_after );
	rktsky_antispam_uninstall_expect( $foreign_before === $foreign_after, 'Core and original provider table inventories must remain unchanged.' );
	foreach ( $rktsky_uninstall_users as $rktsky_uninstall_user ) {
		rktsky_antispam_uninstall_expect( ! metadata_exists( 'user', $rktsky_uninstall_user, $rktsky_uninstall_pref_key ) && 'Preserve unrelated metadata.' === get_user_meta( $rktsky_uninstall_user, 'rktsky_fixture_unrelated_preference', true ), 'Delete mode must remove every account\'s site preference while preserving unrelated native metadata.' );
	}
	echo 'PASS: ' . esc_html( (string) $rktsky_uninstall_assertions ) . " native uninstall assertions (retain, delete, cron, provider and preference isolation).\n";
} finally {
	activate_plugin( $plugin );
	update_option( \Rktsky\Antispam\Settings::OPTION, $original, false );
	foreach ( $rktsky_uninstall_pref_before as $rktsky_uninstall_user => $rktsky_uninstall_values ) {
		delete_user_meta( $rktsky_uninstall_user, $rktsky_uninstall_pref_key );
		foreach ( $rktsky_uninstall_values as $rktsky_uninstall_value ) {
			add_user_meta( $rktsky_uninstall_user, $rktsky_uninstall_pref_key, $rktsky_uninstall_value );
		}
	}
	foreach ( $rktsky_uninstall_users as $rktsky_uninstall_user ) {
		wp_delete_user( $rktsky_uninstall_user );
	}
}
