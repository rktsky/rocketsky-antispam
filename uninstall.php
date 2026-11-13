<?php
/**
 * Removes only this plugin's per-site data when the administrator selected deletion.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! class_exists( Rktsky\Antispam\Installation::class, false ) ) {
	require_once __DIR__ . '/src/Installation.php';
}
( new Rktsky\Antispam\Installation( __DIR__ . '/rocketsky-antispam.php' ) )->uninstall();

/**
 * Drops owned tables, options and notice preferences unless the site opted to retain data.
 *
 * @since 0.1.0
 * @since 0.5.5 Deletes this site's native user-option metadata when data removal is configured.
 * @return void
 */
function rktsky_antispam_uninstall_site(): void {
	global $wpdb;
	if ( is_multisite() ) {
		delete_site_meta( get_current_blog_id(), 'rktsky_antispam_network_cycle' );
	}
	$settings = get_option( 'rktsky_antispam_settings', array() );
	wp_clear_scheduled_hook( 'rktsky_antispam_cleanup' );
	if ( is_array( $settings ) && isset( $settings['delete_on_uninstall'] ) && ! $settings['delete_on_uninstall'] ) {
		return;
	}
	// Native user options carry a site prefix; never remove another site's choices.
	delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . 'rktsky_antispam_hidden_notices', '', true );
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rktsky_antispam_tokens' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall removes only an owned table after the administrator's configured deletion choice.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rktsky_antispam_stats' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall removes only an owned table after the administrator's configured deletion choice.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rktsky_antispam_totals' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes only the owned anonymous lifetime counter.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rktsky_antispam_quarantine' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes only explicitly opted-in owned quarantine data on uninstall.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rktsky_antispam_feedback' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes only owned feedback references and classifications.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rktsky_antispam_jobs' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes only owned task metadata through the official WordPress database API.
	delete_option( 'rktsky_antispam_settings' );
	delete_option( 'rktsky_antispam_schema' );
	delete_option( 'rktsky_antispam_digest_claim' );
	delete_option( 'rktsky_antispam_digest_status' );
}

if ( is_multisite() ) {
	$rktsky_antispam_network_offset = 0;
	do {
		$rktsky_antispam_networks = get_networks(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $rktsky_antispam_network_offset,
			)
		);
		foreach ( $rktsky_antispam_networks as $rktsky_antispam_network_id ) {
			$rktsky_antispam_main_id = (int) get_main_site_id( $rktsky_antispam_network_id );
			if ( $rktsky_antispam_main_id > 0 ) {
				switch_to_blog( $rktsky_antispam_main_id );
				try {
					wp_clear_scheduled_hook( 'rktsky_antispam_network_hourly', array( (int) $rktsky_antispam_network_id ) );
					wp_clear_scheduled_hook( 'rktsky_antispam_network_batch', array( (int) $rktsky_antispam_network_id ) );
				} finally {
					restore_current_blog();
				}
			}
			delete_network_option( $rktsky_antispam_network_id, 'rktsky_antispam_network_schema' );
			delete_network_option( $rktsky_antispam_network_id, 'rktsky_antispam_network_cursor' );
		}
		$rktsky_antispam_network_count   = count( $rktsky_antispam_networks );
		$rktsky_antispam_network_offset += 100;
	} while ( 100 === $rktsky_antispam_network_count );
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
