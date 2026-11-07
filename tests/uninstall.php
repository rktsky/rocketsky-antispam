<?php
/**
 * Verifies actual retain/delete uninstall paths on the marked disposable database.
 *
 * @package RktskyAntispamTests
 * @since 0.1.0
 */

require __DIR__ . '/bootstrap.php';

/**
 * Stops on an uninstall boundary violation.
 *
 * @since 0.1.0
 * @param bool $condition Required storage behavior.
 * @param string $message Failure label.
 * @return void
 */
function rktsky_antispam_uninstall_expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
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
try {
	$retain = $original;
	$retain['delete_on_uninstall'] = false;
	update_option( \Rktsky\Antispam\Settings::OPTION, $retain, false );
	define( 'WP_UNINSTALL_PLUGIN', 'wp-antispam/wp-antispam.php' );
	require dirname( __DIR__ ) . '/uninstall.php';
	$after_retain = $wpdb->get_col( 'SHOW TABLES' );
	rktsky_antispam_uninstall_expect( count( array_intersect( $owned_tables, $after_retain ) ) === count( $owned_tables ), 'Retain mode must preserve every owned table.' );
	rktsky_antispam_uninstall_expect( false !== get_option( \Rktsky\Antispam\Settings::OPTION ), 'Retain mode must preserve settings.' );
	rktsky_antispam_uninstall_expect( false === wp_next_scheduled( 'rktsky_antispam_cleanup' ), 'Uninstall must remove its own scheduled task.' );

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
	echo "PASS: 6 native uninstall assertions (retain, delete, cron, provider isolation).\n";
} finally {
	\Rktsky\Antispam\Plugin::activate();
	update_option( \Rktsky\Antispam\Settings::OPTION, $original, false );
}
