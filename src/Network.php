<?php
/**
 * Coordinates bounded native multisite lifecycle work without sharing site records.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps network scheduling central while storage, settings and cleanup remain per site.
 *
 * @since 0.3.0
 */
final class Network {
	/**
	 * Recurring dispatcher, scheduled only on the network's main site.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	public const HOURLY = 'rktsky_antispam_network_hourly';

	/**
	 * One-off continuation for the next bounded batch.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	public const BATCH = 'rktsky_antispam_network_batch';

	/**
	 * Network-only migration marker; no site configuration is stored here.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	public const VERSION = 'rktsky_antispam_network_schema';

	/**
	 * Resume marker for the current native-metadata maintenance pass.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	public const CURSOR = 'rktsky_antispam_network_cursor';

	/**
	 * One non-personal housekeeping value per site, managed through native metadata APIs.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	public const SITE_PASS = 'rktsky_antispam_network_cycle';

	/**
	 * Maximum number of sites handled in one request.
	 *
	 * @since 0.3.0
	 * @var int
	 */
	public const LIMIT = 20;

	/**
	 * Prevents duplicate hook registration within one PHP request.
	 *
	 * @since 0.3.0
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Registers native new-site, deletion and central scheduling boundaries.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function register(): void {
		if ( ! is_multisite() || self::$registered ) {
			return;
		}
		self::$registered = true;
		add_action( 'wp_initialize_site', array( self::class, 'initialize_site' ), 200 );
		add_filter( 'wpmu_drop_tables', array( self::class, 'drop_tables' ), 10, 2 );
		add_action( self::HOURLY, array( self::class, 'hourly' ) );
		add_action( self::BATCH, array( self::class, 'batch' ) );
		$network_id = get_current_network_id();
		if ( self::active( $network_id ) && Plugin::SCHEMA !== get_network_option( $network_id, self::VERSION ) ) {
			self::start( $network_id, false );
		}
	}

	/**
	 * Checks actual native network activation for an explicit network.
	 *
	 * @since 0.3.0
	 * @param int $network_id Native network ID; zero selects the current network.
	 * @return bool Whether WordPress has the plugin enabled network-wide.
	 */
	public static function active( int $network_id = 0 ): bool {
		if ( ! is_multisite() ) {
			return false;
		}
		$plugins = get_network_option( $network_id > 0 ? $network_id : get_current_network_id(), 'active_sitewide_plugins', array() );
		return is_array( $plugins ) && isset( $plugins[ plugin_basename( RKTSKY_ANTISPAM_FILE ) ] );
	}

	/**
	 * Starts a bounded network activation or an asynchronous schema backfill.
	 *
	 * Activation's own hook precedes WordPress's activation-option update, so its
	 * first trusted batch explicitly permits that narrow transitional state.
	 * Ordinary public boot only queues migration and never loops over the network.
	 *
	 * @since 0.3.0
	 * @param int  $network_id Native network being activated/upgraded.
	 * @param bool $activation Whether this is the native activation callback.
	 * @return void
	 */
	public static function start( int $network_id, bool $activation = true ): void {
		if ( $network_id <= 0 ) {
			return;
		}
		if ( ! is_multisite() || ( ! $activation && ! self::active( $network_id ) ) ) {
			return;
		}
		update_network_option( $network_id, self::CURSOR, wp_generate_uuid4() );
		update_network_option( $network_id, self::VERSION, Plugin::SCHEMA );
		self::on_main(
			$network_id,
			static function () use ( $network_id ): void {
				if ( ! wp_next_scheduled( self::HOURLY, array( $network_id ) ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOURLY, array( $network_id ) );
				}
			}
		);
		if ( $activation ) {
			self::work( $network_id, true );
		} else {
			self::queue( $network_id );
		}
	}

	/**
	 * Starts a cleanup cycle without resetting an unfinished large-network cursor.
	 *
	 * @since 0.3.0
	 * @param int $network_id Native network ID from the scheduled event.
	 * @return void
	 */
	public static function hourly( int $network_id ): void {
		if ( $network_id <= 0 || ! self::active( $network_id ) ) {
			return;
		}
		if ( false === get_network_option( $network_id, self::CURSOR, false ) ) {
			update_network_option( $network_id, self::CURSOR, wp_generate_uuid4() );
		}
		self::work( $network_id );
	}

	/**
	 * Continues an existing cycle; inactive networks never process retained events.
	 *
	 * @since 0.3.0
	 * @param int $network_id Native network ID from the one-off event.
	 * @return void
	 */
	public static function batch( int $network_id ): void {
		if ( $network_id > 0 && self::active( $network_id ) && false !== get_network_option( $network_id, self::CURSOR, false ) ) {
			self::work( $network_id );
		}
	}

	/**
	 * Installs a new site's own tables only after Core created its native tables.
	 *
	 * @since 0.3.0
	 * @param \WP_Site $site Native newly initialized site.
	 * @return void
	 */
	public static function initialize_site( \WP_Site $site ): void {
		if ( ! self::active( (int) $site->network_id ) || ! wp_is_site_initialized( $site ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		try {
			Plugin::install_site( true );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Adds only the deleted site's owned tables to Core's authorized site removal.
	 *
	 * Plugin uninstall retention does not retain data after the entire site is
	 * explicitly destroyed. Core owns the actual DROP and native upload removal.
	 *
	 * @since 0.3.0
	 * @param string[] $tables Existing native tables selected for deletion.
	 * @param int      $site_id Native site being deleted.
	 * @return string[] Existing tables plus this site's bounded owned inventory.
	 */
	public static function drop_tables( array $tables, int $site_id ): array {
		global $wpdb;
		if ( $site_id <= 0 ) {
			return $tables;
		}
		foreach ( Plugin::TABLES as $suffix ) {
			$tables[] = $wpdb->get_blog_prefix( $site_id ) . $suffix;
		}
		return array_values( array_unique( $tables ) );
	}

	/**
	 * Removes both central event types immediately without a network-sized loop.
	 *
	 * Independently site-active installations repair their local schedule on boot.
	 * Network-created sites have no per-site recurring event left behind.
	 *
	 * @since 0.3.0
	 * @param int $network_id Native network being deactivated.
	 * @return void
	 */
	public static function deactivate( int $network_id ): void {
		self::on_main(
			$network_id,
			static function () use ( $network_id ): void {
				wp_clear_scheduled_hook( self::HOURLY, array( $network_id ) );
				wp_clear_scheduled_hook( self::BATCH, array( $network_id ) );
			}
		);
		delete_network_option( $network_id, self::CURSOR );
		delete_network_option( $network_id, self::VERSION );
	}

	/**
	 * Handles at most twenty sites and yields after a five-second soft time budget.
	 *
	 * Every site switch is restored even when an extension throws. Repeated batches
	 * are safe because schema creation, expiry work and scheduling are idempotent.
	 * Native per-site pass markers avoid offset skips when earlier sites disappear.
	 *
	 * @since 0.3.0
	 * @param int  $network_id Native network whose site records are selected.
	 * @param bool $activation Whether network activation is still being finalized.
	 * @return void
	 */
	private static function work( int $network_id, bool $activation = false ): void {
		if ( ! $activation && ! self::active( $network_id ) ) {
			return;
		}
		$pass = get_network_option( $network_id, self::CURSOR, false );
		if ( ! is_string( $pass ) || 1 !== preg_match( '/^[a-f0-9-]{36}$/D', $pass ) ) {
			$pass = wp_generate_uuid4();
			update_network_option( $network_id, self::CURSOR, $pass );
		}
		$sites   = self::sites( $network_id, $pass );
		$more    = count( $sites ) === self::LIMIT;
		$started = microtime( true );
		foreach ( $sites as $site_id ) {
			if ( wp_is_site_initialized( $site_id ) ) {
				switch_to_blog( $site_id );
				try {
					if ( Plugin::SCHEMA !== get_option( 'rktsky_antispam_schema' ) && ! Plugin::install_site( true ) ) {
						self::report_failure( $network_id, $site_id );
					}
					wp_clear_scheduled_hook( 'rktsky_antispam_cleanup' );
					if ( Plugin::SCHEMA === get_option( 'rktsky_antispam_schema' ) ) {
						do_action( 'rktsky_antispam_cleanup' );
					}
				} catch ( \Throwable $error ) {
					self::report_failure( $network_id, $site_id );
				} finally {
					restore_current_blog();
				}
			}
			if ( get_site( $site_id ) ) {
				if ( false === update_site_meta( $site_id, self::SITE_PASS, $pass ) && get_site_meta( $site_id, self::SITE_PASS, true ) !== $pass ) {
					self::report_failure( $network_id, $site_id );
				}
			}
			if ( microtime( true ) - $started >= 5 ) {
				$more = true;
				break;
			}
		}
		if ( $more ) {
			self::queue( $network_id );
		} else {
			delete_network_option( $network_id, self::CURSOR );
		}
	}

	/**
	 * Reports an operational failure without exception text, visitor data or payloads.
	 *
	 * @since 0.3.0
	 * @param int $network_id Native network containing the failed work.
	 * @param int $site_id Native site whose next maintenance cycle will retry.
	 * @return void
	 */
	private static function report_failure( int $network_id, int $site_id ): void {
		error_log( sprintf( 'WP Antispam network maintenance failed for network %d, site %d; the next cycle will retry.', $network_id, $site_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational diagnostics contain only native IDs; swallowing a failure would hide missed retention work.
	}

	/**
	 * Selects at most twenty sites not yet marked for this native maintenance pass.
	 *
	 * Native metadata queries avoid handwritten Core-table SQL and offset skips.
	 * Metadata changes normally invalidate Core's site-query cache; the explicit
	 * native invalidation also guarantees a fresh housekeeping selection per batch.
	 *
	 * @since 0.3.0
	 * @param int    $network_id Native network restriction.
	 * @param string $pass Current non-personal maintenance pass identifier.
	 * @return int[] Ordered unprocessed native site IDs, at most LIMIT.
	 */
	private static function sites( int $network_id, string $pass ): array {
		wp_cache_set_sites_last_changed();
		return array_map(
			'intval',
			get_sites(
				array(
					'fields'                 => 'ids',
					'network_id'             => $network_id,
					'number'                 => self::LIMIT,
					'orderby'                => 'id',
					'order'                  => 'ASC',
					'update_site_cache'      => false,
					'update_site_meta_cache' => false,
					'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Native network-scoped metadata selection returns at most twenty sites per cron batch and avoids handwritten Core-table SQL or deletion-prone offsets.
						'relation' => 'OR',
						array(
							'key'     => self::SITE_PASS,
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => self::SITE_PASS,
							'value'   => $pass,
							'compare' => '!=',
						),
					),
				)
			)
		);
	}

	/**
	 * Schedules one near-term continuation on the network's main site.
	 *
	 * @since 0.3.0
	 * @param int $network_id Native network ID.
	 * @return void
	 */
	private static function queue( int $network_id ): void {
		self::on_main(
			$network_id,
			static function () use ( $network_id ): void {
				if ( ! wp_next_scheduled( self::BATCH, array( $network_id ) ) ) {
					wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::BATCH, array( $network_id ) );
				}
			}
		);
	}

	/**
	 * Executes a central scheduling operation and restores the caller's site context.
	 *
	 * @since 0.3.0
	 * @param int      $network_id Native network ID.
	 * @param callable $operation Bounded trusted scheduling operation.
	 * @return void
	 */
	private static function on_main( int $network_id, callable $operation ): void {
		$main = (int) get_main_site_id( $network_id );
		if ( $main <= 0 ) {
			return;
		}
		switch_to_blog( $main );
		try {
			$operation();
		} finally {
			restore_current_blog();
		}
	}
}
