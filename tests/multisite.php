<?php
/**
 * Verifies native multisite lifecycle and storage isolation on a disposable network.
 *
 * This creates/removes synthetic sites through Core APIs. The native network must
 * already have Rocketsky Antispam network activated and block mail/external HTTP.
 *
 * @package RktskyAntispamTests
 * @since 0.3.0
 */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'RKTSKY_ANTISPAM_ALLOW_TESTS' ) ) {
	throw new RuntimeException( 'Explicit CLI test authorization is required.' );
}
$root = realpath( (string) getenv( 'RKTSKY_ANTISPAM_WP_ROOT' ) );
if ( ! $root || ! is_file( $root . '/.rktsky-antispam-fixture' ) || 'rktsky-antispam-disposable' !== trim( file_get_contents( $root . '/.rktsky-antispam-fixture' ) ) ) {
	throw new RuntimeException( 'A disposable native fixture marker is required.' );
}
$url = parse_url( getenv( 'RKTSKY_ANTISPAM_TEST_URL' ) ?: 'http://127.0.0.1:8877' );
if ( ! in_array( $url['host'] ?? '', array( '127.0.0.1', 'localhost', '[::1]' ), true ) ) {
	throw new RuntimeException( 'The native multisite fixture must use a loopback origin.' );
}
$_SERVER['HTTP_HOST']      = $url['host'] . ( isset( $url['port'] ) ? ':' . $url['port'] : '' );
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
$_SERVER['REQUEST_URI']    = '/';
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
// A native redirect/exit before wp-load returns must fail instead of passing CI.
$rktsky_multisite_booted = false;
register_shutdown_function(
	static function () use ( &$rktsky_multisite_booted ): void {
		if ( ! $rktsky_multisite_booted ) {
			fwrite( STDERR, "Disposable WordPress bootstrap terminated before the native tests could run.\n" );
			exit( 1 );
		}
	}
);
require $root . '/wp-load.php';
$rktsky_multisite_booted = true;
if ( ! is_multisite() || ! defined( 'RKTSKY_ANTISPAM_TEST_FIXTURE' ) || true !== RKTSKY_ANTISPAM_TEST_FIXTURE || 'local' !== wp_get_environment_type() || false !== apply_filters( 'pre_wp_mail', null, array() ) || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
	throw new RuntimeException( 'A local disposable network with mail/HTTP disabled is required.' );
}
if ( ! class_exists( '\Rktsky\Antispam\Network' ) ) {
	throw new RuntimeException( 'Network activate Rocketsky Antispam before running this test.' );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use Rktsky\Antispam\{Plugin, Network, Settings, Token, Store, Quarantine};

/**
 * Counts real native assertions and reports the first failed boundary.
 *
 * @since 0.3.0
 * @param bool   $condition Required actual native behaviour.
 * @param string $message Failure context, without any form payload.
 * @return void
 */
function rktsky_antispam_network_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_network_assertions'];
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		throw new RuntimeException( $message );
	}
}

/**
 * Runs trusted fixture work in a site and always restores the caller's context.
 *
 * @since 0.3.0
 * @param int      $id Native fixture site ID.
 * @param callable $operation Isolated fixture operation.
 * @return mixed Operation result.
 */
function rktsky_antispam_network_in( int $id, callable $operation ) {
	switch_to_blog( $id );
	try {
		return $operation();
	} finally {
		restore_current_blog(); }
}

/**
 * Reads only the bounded own-table inventory for the current site.
 *
 * @since 0.3.0
 * @return string[] Existing owned native table names.
 */
function rktsky_antispam_network_tables(): array {
	global $wpdb;
	return $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'rktsky_antispam_' ) . '%' ) );
}

/**
 * Runs individual native continuation events until the bounded test network finishes.
 *
 * @since 0.3.0
 * @param int $network Native fixture network ID.
 * @return void
 */
function rktsky_antispam_network_drain( int $network ): void {
	for ( $attempt = 0; $attempt < 10 && false !== get_network_option( $network, Network::CURSOR, false ); ++$attempt ) {
		wp_clear_scheduled_hook( Network::BATCH, array( $network ) );
		do_action( Network::BATCH, $network );
	}
	rktsky_antispam_network_expect( false === get_network_option( $network, Network::CURSOR, false ), 'Bounded network continuations did not finish.' );
}

$GLOBALS['rktsky_network_assertions'] = 0;
$network                              = get_current_network_id();
$main                                 = get_current_blog_id();
$plugin                               = plugin_basename( RKTSKY_ANTISPAM_FILE );
$original                             = get_option( Settings::OPTION );
$sites                                = array();
$deleted                              = array();
$user                                 = 0;
wp_set_current_user( 1 );

try {
	rktsky_antispam_network_expect( Network::active(), 'The original native network activation is missing.' );
	rktsky_antispam_network_expect( '4' === get_option( 'rktsky_antispam_schema' ) && 6 === count( rktsky_antispam_network_tables() ), 'Native network activation did not install all six own tables.' );
	deactivate_plugins( $plugin, false, true );
	rktsky_antispam_network_expect( ! Network::active() && false === wp_next_scheduled( Network::HOURLY, array( $network ) ) && false === wp_next_scheduled( Network::BATCH, array( $network ) ), 'Network deactivation retained a central dispatcher or continuation.' );

	$native_network = get_network( $network );
	for ( $index = 0; $index < 26; ++$index ) {
		$id = wp_insert_site(
			array(
				'network_id' => $network,
				'domain'     => $native_network->domain,
				'path'       => $native_network->path . 'rktsky-fixture-' . $index . '-' . wp_generate_password( 8, false, false ) . '/',
				'title'      => 'Synthetic disposable network site',
				'user_id'    => 1,
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( 'Native synthetic site creation failed.' ); }
		$sites[] = (int) $id;
	}
	$site_a = $sites[0];
	$site_b = $sites[1];
	rktsky_antispam_network_expect(
		0 === rktsky_antispam_network_in(
			$site_a,
			static function () {
				return count( rktsky_antispam_network_tables() );
			}
		),
		'An inactive network initialized an unrelated new site.'
	);
	$custom                    = ( new Settings() )->defaults();
	$custom['minimum_seconds'] = 3;
	rktsky_antispam_network_in(
		$site_a,
		static function () use ( $custom ) {
			update_option( Settings::OPTION, $custom, false );
		}
	);
	// A pre-existing local activation's cleanup is converted only when this site is processed.
	rktsky_antispam_network_in(
		$site_b,
		static function () {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'rktsky_antispam_cleanup' );
		}
	);
	$result = activate_plugin( $plugin, '', true );
	rktsky_antispam_network_expect( ! is_wp_error( $result ) && Network::active(), 'Native network reactivation failed.' );
	$initialized = 0;
	foreach ( $sites as $id ) {
		$initialized += rktsky_antispam_network_in(
			$id,
			static function () {
				return Plugin::SCHEMA === get_option( 'rktsky_antispam_schema' ) ? 1 : 0;
			}
		); }
	rktsky_antispam_network_expect( $initialized < count( $sites ) && $initialized <= Network::LIMIT, 'Activation looped synchronously over the complete network.' );
	rktsky_antispam_network_expect( false !== wp_next_scheduled( Network::BATCH, array( $network ) ), 'A pending native continuation was not scheduled.' );
	$next = wp_next_scheduled( Network::BATCH, array( $network ) );
	rktsky_antispam_network_expect( $next >= time() + 40 && $next <= time() + 70, 'Continuation did not use the documented near-term delay.' );
	// Deleting an earlier ID must not shift the pending keyset page past a later site.
	$gap = $sites[2];
	wp_delete_site( $gap );
	$deleted[] = $gap;
	rktsky_antispam_network_drain( $network );
	foreach ( array_diff( $sites, $deleted ) as $id ) {
		rktsky_antispam_network_in(
			$id,
			static function () {
				rktsky_antispam_network_expect( Plugin::SCHEMA === get_option( 'rktsky_antispam_schema' ) && 6 === count( rktsky_antispam_network_tables() ), 'A pending site was skipped or incompletely migrated.' );
				rktsky_antispam_network_expect( false === wp_next_scheduled( 'rktsky_antispam_cleanup' ), 'Network mode retained a recurring per-site cleanup event.' );
			}
		);
	}
	rktsky_antispam_network_expect(
		$custom === rktsky_antispam_network_in(
			$site_a,
			static function () {
				return get_option( Settings::OPTION );
			}
		),
		'Network activation replaced a site-specific setting.'
	);

	$new = wp_insert_site(
		array(
			'network_id' => $network,
			'domain'     => $native_network->domain,
			'path'       => $native_network->path . 'rktsky-new-' . wp_generate_password( 8, false, false ) . '/',
			'title'      => 'Synthetic post-activation site',
			'user_id'    => 1,
		)
	);
	if ( is_wp_error( $new ) ) {
		throw new RuntimeException( 'Native post-activation site creation failed.' ); }
	$sites[] = (int) $new;
	rktsky_antispam_network_in(
		(int) $new,
		static function () {
			rktsky_antispam_network_expect( Plugin::SCHEMA === get_option( 'rktsky_antispam_schema' ) && 6 === count( rktsky_antispam_network_tables() ), 'Core new-site lifecycle did not automatically initialize owned storage.' );
			rktsky_antispam_network_expect( 0 === ( new Settings() )->all()['minimum_seconds'], 'A new site inherited another site\'s configuration.' );
		}
	);

	$proof = rktsky_antispam_network_in(
		$site_a,
		static function () {
			return ( new Token() )->issue( 'comments', '1' );
		}
	);
	rktsky_antispam_network_in(
		$site_b,
		static function () use ( $proof ) {
			rktsky_antispam_network_expect( false === ( new Token() )->verify( $proof['token'], 'comments', '1' ), 'A different site accepted a site-bound token.' );
		}
	);
	$digest = hash( 'sha256', 'synthetic-independent-network-ledger' );
	foreach ( array( $site_a, $site_b ) as $id ) {
		rktsky_antispam_network_in(
			$id,
			static function () use ( $digest ) {
				rktsky_antispam_network_expect( ( new Store() )->claim( $digest, time() + 60 ), 'Independent site replay ledgers shared a claim.' );
			}
		); }
	$payload = rktsky_antispam_network_in(
		$site_a,
		static function () {
			global $wpdb;
			$options                       = ( new Settings() )->defaults();
			$options['privacy_mode']       = false;
			$options['quarantine_enabled'] = true;
			update_option( Settings::OPTION, $options );
			( new Store() )->record( 'cf7', 'missing' );
			( new Quarantine( new Settings() ) )->capture( 'cf7', 'network', 'honeypot', array( 'Message' => 'Synthetic private site-A message' ), 'synthetic-network@example.invalid' );
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i LIMIT 1', $wpdb->prefix . 'rktsky_antispam_quarantine' ), ARRAY_A );
		}
	);
	rktsky_antispam_network_expect( is_array( $payload ), 'Site-A native encrypted capture failed.' );
	rktsky_antispam_network_in(
		$site_b,
		static function () use ( $payload ) {
			global $wpdb;
			rktsky_antispam_network_expect( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'rktsky_antispam_stats' ) ), 'A site read another site\'s aggregate records.' );
			rktsky_antispam_network_expect( null === ( new Quarantine( new Settings() ) )->read( (int) $payload['id'] ), 'A site read another site\'s quarantined object.' );
			$copy = $payload;
			unset( $copy['id'] );
			$wpdb->insert( $wpdb->prefix . 'rktsky_antispam_quarantine', $copy );
			$id = (int) $wpdb->insert_id;
			rktsky_antispam_network_expect( ( new Quarantine( new Settings() ) )->read( $id )['unreadable'], 'An encrypted payload moved between sites was decryptable.' );
			( new Quarantine( new Settings() ) )->delete( $id );
		}
	);
	$user = wp_insert_user(
		array(
			'user_login' => 'rktsky-network-' . wp_generate_password( 8, false, false ),
			'user_pass'  => wp_generate_password( 32 ),
			'user_email' => 'synthetic-local-admin@example.invalid',
			'role'       => 'subscriber',
		)
	);
	if ( is_wp_error( $user ) ) {
		throw new RuntimeException( 'Synthetic site admin creation failed.' ); }
	add_user_to_blog( $site_a, $user, 'administrator' );
	wp_set_current_user( $user );
	rktsky_antispam_network_in(
		$site_a,
		static function () use ( $payload ) {
			rktsky_antispam_network_expect( null !== ( new Quarantine( new Settings() ) )->read( (int) $payload['id'] ), 'A native site administrator could not read their own site.' );
		}
	);
	rktsky_antispam_network_in(
		$site_b,
		static function () {
			rktsky_antispam_network_expect( ! current_user_can( 'manage_options' ) && array() === ( new Quarantine( new Settings() ) )->listing()['items'], 'A site administrator obtained another site\'s private listing.' );
		}
	);
	wp_set_current_user( 1 );

	// A missing schema is repaired on this site's own first request, before its guard.
	rktsky_antispam_network_in(
		$site_b,
		static function () {
			wp_set_current_user( 0 );
			try {
				update_option( 'rktsky_antispam_schema', '1' );
				Plugin::upgrade();
				rktsky_antispam_network_expect( Plugin::SCHEMA === get_option( 'rktsky_antispam_schema' ), 'Lazy current-site schema upgrade required an admin page.' );
			} finally {
				wp_set_current_user( 1 ); }
		}
	);
	// Prove context restoration and safe diagnostics when a site's extension throws.
	$old_log = ini_get( 'error_log' );
	$log     = tempnam( sys_get_temp_dir(), 'rktsky-network-log-' );
	ini_set( 'error_log', $log );
	$thrower = static function () use ( $site_a ) {
		if ( get_current_blog_id() === $site_a ) {
			throw new RuntimeException( 'private synthetic exception text must not enter a log' );
		} };
	add_action( 'rktsky_antispam_cleanup', $thrower, 1 );
	try {
		Network::hourly( $network );
		rktsky_antispam_network_drain( $network );
	} finally {
		remove_action( 'rktsky_antispam_cleanup', $thrower, 1 );
		ini_set( 'error_log', $old_log ); }
	$diagnostic = file_get_contents( $log );
	unlink( $log );
	rktsky_antispam_network_expect( str_contains( $diagnostic, 'network maintenance failed' ) && ! str_contains( $diagnostic, 'private synthetic' ), 'Network failure diagnostics leaked exception content or silently discarded a failure.' );
	rktsky_antispam_network_expect( $main === get_current_blog_id() && ! ms_is_switched(), 'A failed network operation leaked the switched-site context.' );
	Network::hourly( $network );
	rktsky_antispam_network_drain( $network );

	// Site deletion delegates all native drops to Core and leaves adjacent sites intact.
	$drop_id     = (int) $new;
	$drop_prefix = $wpdb->get_blog_prefix( $drop_id );
	wp_delete_site( $drop_id );
	$deleted[] = $drop_id;
	foreach ( Plugin::TABLES as $suffix ) {
		rktsky_antispam_network_expect( null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $drop_prefix . $suffix ) ) ), 'Core site deletion left an owned private table behind.' ); }
	rktsky_antispam_network_expect(
		6 === rktsky_antispam_network_in(
			$site_a,
			static function () {
				return count( rktsky_antispam_network_tables() );
			}
		),
		'Deleting a native site changed another site\'s own tables.'
	);

	// Both central event types are removed immediately, including an unfinished cycle.
	update_network_option( $network, Network::CURSOR, 0 );
	wp_schedule_single_event( time() + 60, Network::BATCH, array( $network ) );
	deactivate_plugins( $plugin, false, true );
	rktsky_antispam_network_expect( ! Network::active() && false === wp_next_scheduled( Network::HOURLY, array( $network ) ) && false === wp_next_scheduled( Network::BATCH, array( $network ) ), 'Network deactivation did not immediately remove both central jobs.' );
	Network::batch( $network );
	Network::hourly( $network );
	rktsky_antispam_network_expect( false === get_network_option( $network, Network::CURSOR, false ), 'An inactive network resumed a retained continuation.' );
	rktsky_antispam_network_in(
		$site_b,
		static function () use ( $plugin ) {
			activate_plugin( $plugin );
			Plugin::upgrade();
			rktsky_antispam_network_expect( false !== wp_next_scheduled( 'rktsky_antispam_cleanup' ), 'An individual site activation lost its own native schedule.' );
			deactivate_plugins( $plugin );
			rktsky_antispam_network_expect( false === wp_next_scheduled( 'rktsky_antispam_cleanup' ), 'Individual deactivation left a recurring cleanup task.' );
		}
	);

	// Native uninstall honours each site's own retention choice and removes only own data.
	rktsky_antispam_network_in(
		$site_a,
		static function () {
			$options                        = get_option( Settings::OPTION );
			$options['delete_on_uninstall'] = false;
			update_option( Settings::OPTION, $options );
		}
	);
	rktsky_antispam_network_in(
		$site_b,
		static function () {
			$options                        = get_option( Settings::OPTION );
			$options['delete_on_uninstall'] = true;
			update_option( Settings::OPTION, $options );
			wp_schedule_event( time() + 60, 'hourly', 'rktsky_antispam_cleanup' );
		}
	);
	$foreign_before = array();
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 100,
		)
	) as $id ) {
		$foreign_before[ $id ] = array( get_blog_option( $id, 'home' ), get_blog_option( $id, 'siteurl' ), wp_is_site_initialized( $id ) );
	}
	update_network_option( $network, Network::CURSOR, 1 );
	update_network_option( $network, Network::VERSION, '4' );
	wp_schedule_event( time() + 60, 'hourly', Network::HOURLY, array( $network ) );
	wp_schedule_single_event( time() + 60, Network::BATCH, array( $network ) );
	uninstall_plugin( $plugin );
	rktsky_antispam_network_expect(
		6 === rktsky_antispam_network_in(
			$site_a,
			static function () {
				return count( rktsky_antispam_network_tables() );
			}
		),
		'Network uninstall ignored a site\'s retain preference.'
	);
	rktsky_antispam_network_expect(
		0 === rktsky_antispam_network_in(
			$site_b,
			static function () {
				return count( rktsky_antispam_network_tables() );
			}
		),
		'Network uninstall did not remove the opted-in site\'s own tables.'
	);
	rktsky_antispam_network_expect(
		false === rktsky_antispam_network_in(
			$site_b,
			static function () {
				return wp_next_scheduled( 'rktsky_antispam_cleanup' );
			}
		),
		'Network uninstall retained a site cleanup task.'
	);
	rktsky_antispam_network_expect( false === wp_next_scheduled( Network::HOURLY, array( $network ) ) && false === wp_next_scheduled( Network::BATCH, array( $network ) ) && false === get_network_option( $network, Network::CURSOR, false ) && false === get_network_option( $network, Network::VERSION, false ), 'Network uninstall retained dispatcher metadata or events.' );
	$foreign_after = array();
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 100,
		)
	) as $id ) {
		$foreign_after[ $id ] = array( get_blog_option( $id, 'home' ), get_blog_option( $id, 'siteurl' ), wp_is_site_initialized( $id ) );
	}
	rktsky_antispam_network_expect( $foreign_before === $foreign_after, 'Network uninstall changed native Core site data or initialization.' );
	foreach ( array( $site_a, $site_b ) as $id ) {
		rktsky_antispam_network_expect( '' === get_site_meta( $id, Network::SITE_PASS, true ), 'Network uninstall retained native maintenance metadata.' ); }
	echo 'PASS: ' . $GLOBALS['rktsky_network_assertions'] . " native multisite assertions across 28 sites.\n";
} finally {
	wp_set_current_user( 1 );
	foreach ( array_diff( $sites, $deleted ) as $id ) {
		if ( get_site( $id ) ) {
			wp_delete_site( $id ); }
	}
	if ( is_int( $user ) && $user > 0 ) {
		wpmu_delete_user( $user ); }
	Plugin::install_site( true );
	update_option( Settings::OPTION, $original, false );
	activate_plugin( $plugin, '', true );
}
