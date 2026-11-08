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
	 * Offers accurate, editable disclosure through WordPress's privacy-policy guide.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function privacy(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( 'Rocketsky Antispam', wpautop( esc_html__( 'Rocketsky Antispam checks comments and supported forms on this website. Messages matched by content rules may be stored in WordPress or the form plugin\'s spam queue, under that provider\'s storage and retention settings. Failed verification checks stop a submission before a comment or form entry is saved. If the form allows storage and quarantine is enabled, selected fields from rejected submissions may be kept in Rocketsky Antispam quarantine. Forms that prohibit message storage are respected. Account, password, order and payment fields are excluded from quarantine.', 'rocketsky-antispam' ) ) );
			wp_add_privacy_policy_content( 'Rocketsky Antispam', wpautop( esc_html__( 'The plugin sets no cookies and uses no browser storage, fingerprints, external spam service or external IP lookup. It stores digests of used random tokens for up to two hours; scheduled cleanup removes expired digests. Daily aggregate counts are kept for the configured period (30 days by default). The total rejected-comment count stays until the statistics are reset. Optional quarantine encrypts selected names, email addresses and message fields on this website, for seven days by default, up to 30 days and 500 records. Password, hidden, payment and file fields are excluded. Privacy-conscious mode prevents new quarantine capture, country filters, administrator summary emails and pingbacks/trackbacks; it does not delete existing records. If pingbacks are allowed, WordPress requests the source page and may send it the sender\'s IP address. Optional country filters use local GeoIP data or metadata from explicitly trusted infrastructure; Rocketsky Antispam does not store the IP address. Optional daily administrator emails contain counts only. Optional cleanup permanently deletes WordPress spam comments older than the configured age. Scheduled cleanup depends on WordPress cron and can run late. WordPress privacy tools export or erase quarantine records matching the contact email address. WordPress, form plugins, hosting, mail delivery and backups have their own data handling. Adapt this notice to your enabled settings, retention, infrastructure and legal basis.', 'rocketsky-antispam' ) ) );
			wp_add_privacy_policy_content( 'Rocketsky Antispam', wpautop( esc_html__( 'Review statistics store the original decision, a later moderator assessment and timestamps for up to 90 days, including in privacy-conscious mode. References to comments, form entries, quarantine records and manually reported cases are stored as site-keyed HMAC values. Their contents are not copied. These references remain locally linkable while the source exists. For supported sources, deletion and WordPress privacy workflows remove or detach related references. Reviewed totals may remain until the original expiry. Only reviewed cases contribute to precision, recall and accuracy; unreviewed cases are not assumed to be correct. No review data is sent to a third party.', 'rocketsky-antispam' ) ) );
		}
	}
}
