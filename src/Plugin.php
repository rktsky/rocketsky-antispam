<?php
/**
 * Composes the plugin lifecycle, local services and native privacy-policy notice.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Registers request-scoped services while keeping activation and cleanup explicit.
 *
 * @since 0.1.0
 */
final class Plugin {
	/**
	 * Connects only native hooks; no network calls or visitor inspection occur at boot.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function boot(): void {
		$settings = new Settings();
		$token    = new Token();
		$store    = new Store();
		$guard    = new Guard( $settings, $token, $store );
		$fields   = new Fields( $settings, $token );
		( new Integrations( $settings, $guard, $fields ) )->register();
		( new Admin( $settings, $store ) )->register();
		add_action( 'admin_init', array( $settings, 'register' ) );
		add_action( 'admin_init', array( self::class, 'privacy' ) );
		add_action( 'init', array( self::class, 'translations' ) );
		add_action( 'rest_api_init', array( $fields, 'register' ) );
		add_action( 'rktsky_antispam_cleanup', array( $store, 'cleanup' ) );
	}

	/**
	 * Creates isolated per-site storage; network-wide activation is intentionally unsupported.
	 *
	 * @since 0.1.0
	 * @param bool $network_wide Whether WordPress requested network activation.
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide ) {
			wp_die( esc_html__( 'Please activate WP Antispam separately on each site. Network activation is not supported.', 'wp-antispam' ) );
		}
		( new Store() )->install();
		add_option( Settings::OPTION, ( new Settings() )->defaults(), '', false );
		if ( ! wp_next_scheduled( 'rktsky_antispam_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'rktsky_antispam_cleanup' );
		}
	}

	/**
	 * Stops this site's scheduled task while preserving configuration and replay claims.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'rktsky_antispam_cleanup' );
	}

	/**
	 * Registers the local translation directory after WordPress locale initialization.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function translations(): void {
		load_plugin_textdomain( 'wp-antispam', false, dirname( plugin_basename( RKTSKY_ANTISPAM_FILE ) ) . '/languages' );
	}

	/**
	 * Offers accurate, editable disclosure through WordPress's privacy-policy guide.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function privacy(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( 'WP Antispam', wpautop( esc_html__( 'Our forms use WP Antispam for local spam protection. It checks random signed form tokens and an empty hidden field. No data is sent to an external spam service. WP Antispam does not store IP addresses, email addresses, form content, user agents, cookies or browser fingerprints. One-time token digests remain until expiry (two hours) and scheduled cleanup; cleanup can be delayed by site traffic or cron configuration. Anonymous daily counters are retained for the period configured by the site administrator (30 days by default). Form providers, WordPress and hosting may process submission data independently. Adapt this notice to your actual settings and privacy policy.', 'wp-antispam' ) ) );
		}
	}
}
