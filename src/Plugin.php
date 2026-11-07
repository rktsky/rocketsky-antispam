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
	 * Latest owned per-site database schema.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	public const SCHEMA = '4';

	/**
	 * Complete owned table inventory for installation and native site deletion.
	 *
	 * @since 0.3.0
	 * @var string[]
	 */
	public const TABLES = array( 'rktsky_antispam_tokens', 'rktsky_antispam_stats', 'rktsky_antispam_totals', 'rktsky_antispam_quarantine', 'rktsky_antispam_feedback', 'rktsky_antispam_jobs' );

	/**
	 * Connects only native hooks; no network calls or visitor inspection occur at boot.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function boot(): void {
		Network::register();
		self::upgrade();
		$settings      = new Settings();
		$token         = new Token();
		$store         = new Store();
		$quarantine    = new Quarantine( $settings );
		$feedback      = new Feedback();
		$spam_handling = new SpamHandling( $settings, $quarantine );
		$guard         = new Guard( $settings, $token, $store, new ContentRules( $settings, new Language(), new Country( $settings ) ) );
		$fields        = new Fields( $settings, $token );
		$integrations  = new Integrations( $settings, $guard, $fields, $quarantine, $feedback );
		$integrations->register();
		add_action( 'after_setup_theme', array( $integrations, 'register_theme' ), PHP_INT_MAX );
		add_action( 'init', array( $integrations, 'register_theme' ), PHP_INT_MAX );
		add_action( 'wp_loaded', array( $integrations, 'register_theme' ), 0 );
		( new Admin( $settings, $store, $quarantine, $feedback, $spam_handling ) )->register();
		( new ContextNotices( $settings, $spam_handling ) )->register();
		$quarantine->register();
		$feedback->register();
		( new NativeFeedback( $feedback ) )->register();
		( new WpFormsFeedback( $feedback ) )->register();
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
	 * Activates local storage or starts bounded network installation work.
	 *
	 * @since 0.1.0
	 * @since 0.3.0 Supports native network activation with per-site configuration.
	 * @param bool $network_wide Whether WordPress requested network activation.
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			self::install_site( true );
			Network::start( get_current_network_id() );
			return;
		}
		self::install_site();
	}

	/**
	 * Creates only the current site's owned tables and preserves existing settings.
	 *
	 * The final schema marker is written only after the complete owned inventory
	 * exists. Failed installation remains eligible for a later trusted retry.
	 * Network mode uses the central dispatcher instead of recurring site events.
	 *
	 * @since 0.3.0
	 * @param bool $network Whether central network scheduling owns this site.
	 * @return bool Whether all owned tables are present after installation.
	 */
	public static function install_site( bool $network = false ): bool {
		global $wpdb;
		( new Store() )->install();
		( new Quarantine( new Settings() ) )->install();
		( new Feedback() )->install();
		add_option( Settings::OPTION, ( new Settings() )->defaults(), '', false );
		$complete = true;
		foreach ( self::TABLES as $suffix ) {
			$name     = $wpdb->prefix . $suffix;
			$found    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Confirms only the bounded owned schema before recording successful installation.
			$complete = $complete && $name === $found;
		}
		if ( $complete ) {
			update_option( 'rktsky_antispam_schema', self::SCHEMA, false );
		}
		if ( $network ) {
			wp_clear_scheduled_hook( 'rktsky_antispam_cleanup' );
		} else {
			self::schedule_cleanup();
		}
		return $complete;
	}

	/**
	 * Migrates the current site's trusted plugin schema before any public guard runs.
	 *
	 * Schema migration is driven by installed code, never a submitted option or
	 * visitor argument. Network backfill can therefore remain bounded while a
	 * first request on an unprocessed site still obtains its own working storage.
	 *
	 * @since 0.2.0
	 * @since 0.3.0 Repairs current-site storage and local schedules without an admin visit.
	 * @return void
	 */
	public static function upgrade(): void {
		$network = Network::active();
		if ( self::SCHEMA !== get_option( 'rktsky_antispam_schema' ) ) {
			self::install_site( $network );
		} elseif ( ! $network ) {
			self::schedule_cleanup();
		}
	}

	/**
	 * Stops current-site or central network events while retaining configured data.
	 *
	 * @since 0.1.0
	 * @since 0.3.0 Removes both network dispatcher/continuation event types.
	 * @param bool $network_wide Whether WordPress deactivates the network installation.
	 * @return void
	 */
	public static function deactivate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			Network::deactivate( get_current_network_id() );
		}
		wp_clear_scheduled_hook( 'rktsky_antispam_cleanup' );
	}

	/**
	 * Repairs only the current site's local schedule after individual activation.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	private static function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( 'rktsky_antispam_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'rktsky_antispam_cleanup' );
		}
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
			wp_add_privacy_policy_content( 'WP Antispam', wpautop( esc_html__( 'Verified content matches can be retained in WordPress or the form provider\'s native spam queue. Native retention and privacy settings apply independently of WP Antispam quarantine and privacy-conscious mode. Invalid technical proofs stop before native entry creation; selected rejected message fields may instead be retained in the optional WP Antispam quarantine. Forms that explicitly prohibit message storage are respected. Account, password, order and payment contents are not quarantined.', 'wp-antispam' ) ) );
			wp_add_privacy_policy_content( 'WP Antispam', wpautop( esc_html__( 'Our forms use WP Antispam for local spam protection. Signed tokens, hidden fields and configured content rules are checked on our server. The plugin uses no external spam or IP-lookup service, cookies, browser storage or fingerprints. Used random-token digests expire after two hours and are removed by scheduled cleanup. Daily aggregate counters are kept for the configured period (30 days by default); the total rejected-comment counter remains until statistics are reset. Optional quarantine stores selected rejected submission fields, including names, email addresses and messages, encrypted locally for the configured period (7 days by default, at most 30 days and 500 records). Password, hidden, payment and file fields are excluded. The privacy-conscious mode disables quarantine, country checks, administrator email digests and native pingbacks/trackbacks. If native pingbacks are explicitly allowed, WordPress requests the remote source page and may forward the sender IP address; native WordPress moderation still applies. Optional country checks use a local GeoIP source or explicitly trusted infrastructure metadata and do not store IP addresses. Optional daily administrator emails contain aggregate counts only. Optional cleanup permanently deletes WordPress spam comments after the configured age. Scheduled deletion may run late if WordPress cron is delayed. WordPress privacy export and erasure tools cover quarantine records associated with the submitted contact email. WordPress, form providers, hosting, email delivery and backups process data independently. Adapt this notice to the features actually enabled, retention, legal basis and infrastructure of your website; this mode is not a legal compliance certification.', 'wp-antispam' ) ) );
			wp_add_privacy_policy_content( 'WP Antispam', wpautop( esc_html__( 'Feedback statistics use local metadata about the original decision and a later administrator classification for up to 90 days. References to native comments, quarantined entries and manually reported case identifiers are stored as site-keyed HMAC values, without copying their content into feedback records. These references remain locally linkable while the source exists. Reviewed records can be detached from deleted sources while preserving the 90-day evaluation window. Metrics apply to reviewed examples only; unreviewed submissions and unavailable external inbox classifications are not treated as confirmed outcomes. WordPress privacy requests and source deletion must also remove or detach related feedback references. No feedback metadata is sent to a third party.', 'wp-antispam' ) ) );
		}
	}
}
