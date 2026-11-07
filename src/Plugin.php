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
		$quarantine = new Quarantine( $settings );
		$feedback = new Feedback();
		$guard    = new Guard( $settings, $token, $store, new ContentRules( $settings, new Language(), new Country( $settings ) ) );
		$fields   = new Fields( $settings, $token );
		( new Integrations( $settings, $guard, $fields, $quarantine, $feedback ) )->register();
		( new Admin( $settings, $store, $quarantine, $feedback ) )->register();
		$quarantine->register();
		$feedback->register();
		add_action( 'wp_dashboard_setup', array( Dashboard::class, 'register' ) );
		add_action( 'admin_init', array( self::class, 'upgrade' ) );
		add_action( 'admin_init', array( $settings, 'register' ) );
		add_action( 'admin_init', array( self::class, 'privacy' ) );
		add_action( 'init', array( self::class, 'translations' ) );
		add_action( 'rest_api_init', array( $fields, 'register' ) );
		add_action( 'rktsky_antispam_cleanup', array( $store, 'cleanup' ) );
		add_action( 'rktsky_antispam_cleanup', array( Maintenance::class, 'run' ), 5 );
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
		( new Quarantine( new Settings() ) )->install();
		( new Feedback() )->install();
		update_option( 'rktsky_antispam_schema', '3', false );
		add_option( Settings::OPTION, ( new Settings() )->defaults(), '', false );
		if ( ! wp_next_scheduled( 'rktsky_antispam_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'rktsky_antispam_cleanup' );
		}
	}

	/**
	 * Migrates only owned tables after an update, without changing provider data.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public static function upgrade(): void {
		if ( current_user_can( 'manage_options' ) && '3' !== get_option( 'rktsky_antispam_schema' ) ) {
			( new Store() )->install();
			( new Quarantine( new Settings() ) )->install();
			( new Feedback() )->install();
			update_option( 'rktsky_antispam_schema', '3', false );
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
			wp_add_privacy_policy_content( 'WP Antispam', wpautop( esc_html__( 'Our forms use WP Antispam for local spam protection. Signed tokens, hidden fields and configured content rules are checked on our server. The plugin uses no external spam or IP-lookup service, cookies, browser storage or fingerprints. Used random-token digests expire after two hours and are removed by scheduled cleanup. Daily aggregate counters are kept for the configured period (30 days by default); the total rejected-comment counter remains until statistics are reset. Optional quarantine stores selected rejected submission fields, including names, email addresses and messages, encrypted locally for the configured period (7 days by default, at most 30 days and 500 records). Password, hidden, payment and file fields are excluded. The privacy-conscious mode disables quarantine, country checks and administrator email digests. Optional country checks use a local GeoIP source or explicitly trusted infrastructure metadata and do not store IP addresses. Optional daily administrator emails contain aggregate counts only. Optional cleanup permanently deletes WordPress spam comments after the configured age. Scheduled deletion may run late if WordPress cron is delayed. WordPress privacy export and erasure tools cover quarantine records associated with the submitted contact email. WordPress, form providers, hosting, email delivery and backups process data independently. Adapt this notice to the features actually enabled, retention, legal basis and infrastructure of your website; this mode is not a legal compliance certification.', 'wp-antispam' ) ) );
			wp_add_privacy_policy_content( 'WP Antispam', wpautop( esc_html__( 'Feedback statistics use local metadata about the original decision and a later administrator classification for up to 90 days. References to native comments, quarantined entries and manually reported case identifiers are stored as site-keyed HMAC values, without copying their content into feedback records. These references remain locally linkable while the source exists. Reviewed records can be detached from deleted sources while preserving the 90-day evaluation window. Metrics apply to reviewed examples only; unreviewed submissions and unavailable external inbox classifications are not treated as confirmed outcomes. WordPress privacy requests and source deletion must also remove or detach related feedback references. No feedback metadata is sent to a third party.', 'wp-antispam' ) ) );
		}
	}
}
