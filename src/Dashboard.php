<?php
/**
 * Shows administrator-only daily rejection statistics in the native dashboard.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Presents observed outcomes without claiming a verified spam-detection accuracy.
 *
 * @since 0.2.0
 */
final class Dashboard {
	/**
	 * Adds one widget only for site administrators.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public static function register(): void {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'rktsky_antispam_dashboard', __( 'Rocketsky Antispam', 'rocketsky-antispam' ), array( self::class, 'render' ) );
			add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		}
	}

	/**
	 * Loads the widget stylesheet only on the authorized native site dashboard.
	 *
	 * Registration runs during wp_dashboard_setup, before Core enqueues its admin
	 * assets. The stylesheet is confined to this widget and adds no script or font.
	 *
	 * @since 0.5.2
	 * @param string $hook_suffix Native administration screen filename.
	 * @return void
	 */
	public static function assets( string $hook_suffix ): void {
		$screen = get_current_screen();
		if ( 'index.php' !== $hook_suffix || ! $screen || 'dashboard' !== $screen->id || is_network_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_style( 'rktsky-antispam-dashboard', plugins_url( 'assets/dashboard.css', RKTSKY_ANTISPAM_FILE ), array(), RKTSKY_ANTISPAM_VERSION );
	}

	/**
	 * Prepares a seven-day series including empty days and the lifetime comment total.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$store   = new Store();
		$summary = $store->summary( 7 );
		$total   = $store->blocked_comments();
		$quality = ( new Feedback() )->metrics();
		$indexed = array_column( $summary['daily'], null, 'day' );
		$days    = array();
		$utc     = new \DateTimeZone( 'UTC' );
		$now     = time();
		for ( $offset = 6; $offset >= 0; --$offset ) {
			$day          = gmdate( 'Y-m-d', $now - $offset * DAY_IN_SECONDS );
			$row          = $indexed[ $day ] ?? array(
				'day'      => $day,
				'accepted' => 0,
				'blocked'  => 0,
			);
			$row['label'] = wp_date( 'j M', strtotime( $day . ' 00:00:00 UTC' ), $utc );
			$days[]       = $row;
		}
		include dirname( __DIR__ ) . '/templates/dashboard.php';
	}
}
