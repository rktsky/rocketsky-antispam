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
			wp_add_dashboard_widget( 'rktsky_antispam_dashboard', __( 'WP Antispam', 'wp-antispam' ), array( self::class, 'render' ) );
		}
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
		$store = new Store();
		$summary = $store->summary( 7 );
		$total = $store->blocked_comments();
		$quality = ( new Feedback() )->metrics();
		$indexed = array_column( $summary['daily'], null, 'day' );
		$days = array();
		for ( $offset = 6; $offset >= 0; --$offset ) {
			$day = gmdate( 'Y-m-d', time() - $offset * DAY_IN_SECONDS );
			$days[] = $indexed[ $day ] ?? array( 'day' => $day, 'accepted' => 0, 'blocked' => 0 );
		}
		include dirname( __DIR__ ) . '/templates/dashboard.php';
	}
}
