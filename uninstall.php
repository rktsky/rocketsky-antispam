<?php
/**
 * Removes only this plugin's per-site data when the administrator selected deletion.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Drops owned tables and options, preserving sites that opted to retain their data.
 *
 * @since 0.1.0
 * @return void
 */
function rktsky_antispam_uninstall_site(): void {
	global $wpdb;
	$settings = get_option( 'rktsky_antispam_settings', array() );
	wp_clear_scheduled_hook( 'rktsky_antispam_cleanup' );
	if ( is_array( $settings ) && isset( $settings['delete_on_uninstall'] ) && ! $settings['delete_on_uninstall'] ) {
		return;
	}
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rktsky_antispam_tokens' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall removes only an owned table after the administrator's configured deletion choice.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rktsky_antispam_stats' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall removes only an owned table after the administrator's configured deletion choice.
	delete_option( 'rktsky_antispam_settings' );
	delete_option( 'rktsky_antispam_schema' );
}

if ( is_multisite() ) {
	$rktsky_antispam_offset = 0;
	do {
		$rktsky_antispam_sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $rktsky_antispam_offset,
			)
		);
		$rktsky_antispam_count = count( $rktsky_antispam_sites );
		foreach ( $rktsky_antispam_sites as $rktsky_antispam_site ) {
			switch_to_blog( (int) $rktsky_antispam_site );
			rktsky_antispam_uninstall_site();
			restore_current_blog();
		}
		$rktsky_antispam_offset += 100;
	} while ( 100 === $rktsky_antispam_count );
} else {
	rktsky_antispam_uninstall_site();
}
