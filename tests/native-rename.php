<?php
/**
 * Verifies a real 0.5.0-to-current basename change without uninstalling shared data.
 *
 * Run only against a separate marked disposable WordPress installation. Supply
 * RKTSKY_ANTISPAM_RENAME_OLD_ROOT and RKTSKY_ANTISPAM_RENAME_NEW_ROOT with the
 * respective owned packages, RKTSKY_ANTISPAM_WP_ROOT with the fixture, and
 * RKTSKY_ANTISPAM_RENAME_STATE with a private JSON path outside its public root.
 * Fresh PHP stages use Core activation/deactivation APIs. The old identifiers
 * below are intentional upgrade inputs, never a second runtime implementation.
 *
 * @package RktskyAntispamTests
 * @since 0.5.1
 */

use Rktsky\Antispam\{Plugin, Network, Settings, Token, Store, Guard, Quarantine, Feedback, Fields};

if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'RKTSKY_ANTISPAM_ALLOW_TESTS' ) ) {
	throw new RuntimeException( 'Explicit disposable fixture authorization is required.' );
}
$root = realpath( (string) getenv( 'RKTSKY_ANTISPAM_WP_ROOT' ) );
if ( ! $root || ! is_file( $root . '/.rktsky-antispam-fixture' ) || 'rktsky-antispam-disposable' !== trim( file_get_contents( $root . '/.rktsky-antispam-fixture' ) ) ) {
	throw new RuntimeException( 'A separately marked disposable installation is required.' );
}
$state_file = (string) getenv( 'RKTSKY_ANTISPAM_RENAME_STATE' );
$private    = realpath( dirname( $state_file ) );
if ( ! $private || is_link( $state_file ) || $private === $root || str_starts_with( $private, $root . DIRECTORY_SEPARATOR ) ) {
	throw new RuntimeException( 'The private state directory must exist outside the public fixture.' );
}
$state_file                          = $private . DIRECTORY_SEPARATOR . basename( $state_file );
$stage                               = $argv[1] ?? '';
$old                                 = 'wp-antispam/wp-antispam.php';
$new                                 = 'rocketsky-antispam/rocketsky-antispam.php';
$GLOBALS['rktsky_rename_assertions'] = 0;
set_exception_handler(
	static function ( Throwable $error ): void {
		fwrite( STDERR, $error->getMessage() . "\n" );
		exit( 1 );
	}
);

/**
 * Fails on an actual upgrade boundary without printing stored submission content.
 *
 * @since 0.5.1
 * @param bool   $condition Required observed native behavior.
 * @param string $message Non-personal failure context.
 * @return void
 */
function rktsky_rename_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_rename_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
}

/**
 * Copies only our owned distribution folders, rejecting nested symbolic links.
 *
 * Neither WordPress nor any provider package is rewritten by this test.
 *
 * @since 0.5.1
 * @param string $source Existing owned package source.
 * @param string $target New disposable plugin directory, which must not exist.
 * @param string $entry Canonical main filename in this particular version.
 * @return void
 */
function rktsky_rename_package( string $source, string $target, string $entry ): void {
	$source = realpath( $source );
	if ( ! $source || ! is_file( $source . '/' . $entry ) || file_exists( $target ) || is_link( $target ) ) {
		throw new RuntimeException( 'Refusing an unknown or already installed package destination.' ); }
	if ( ! mkdir( $target, 0700, true ) ) {
		throw new RuntimeException( 'The disposable owned package could not be created.' ); }
	foreach ( array( $entry, 'uninstall.php', 'readme.txt', 'src', 'assets', 'templates', 'languages' ) as $name ) {
		$path = $source . '/' . $name;
		if ( ! file_exists( $path ) ) {
			continue; }
		$entries = is_dir( $path ) ? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST ) : array( new SplFileInfo( $path ) );
		if ( is_dir( $path ) && ! mkdir( $target . '/' . $name, 0700 ) ) {
			throw new RuntimeException( 'The owned distribution directory could not be copied.' ); }
		foreach ( $entries as $file ) {
			if ( $file->isLink() ) {
				throw new RuntimeException( 'Owned distribution copies must not follow nested symbolic links.' ); }
			$destination = $target . substr( $file->getPathname(), strlen( $source ) );
			if ( $file->isDir() ? ! mkdir( $destination, 0700 ) : ! copy( $file->getPathname(), $destination ) ) {
				throw new RuntimeException( 'The owned distribution copy failed.' ); }
		}
	}
}

/**
 * Executes a fresh PHP process because old and new classes share stable names.
 *
 * @since 0.5.1
 * @param string $stage Fixed test stage, not a shell fragment.
 * @return array Structured native result for this stage.
 */
function rktsky_rename_child( string $stage ): array {
	$command = array( PHP_BINARY );
	foreach ( array( 'RKTSKY_ANTISPAM_PHP_ICONV', 'RKTSKY_ANTISPAM_PHP_SIMPLEXML' ) as $key ) {
		if ( getenv( $key ) ) {
			$command[] = '-d';
			$command[] = 'extension=' . getenv( $key ); }
	}
	$command[] = __FILE__;
	$command[] = $stage;
	$pipes     = array();
	$process   = proc_open(
		$command,
		array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes
	);
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'The native upgrade process could not start.' ); }
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$status = proc_close( $process );
	if ( 0 !== $status || ! preg_match( '/^RESULT: (.+)$/m', $output, $match ) ) {
		throw new RuntimeException( 'Native rename stage failed: ' . $stage . "\n" . $output . "\n" . $error ); }
	$result = json_decode( $match[1], true, 32, JSON_THROW_ON_ERROR );
	if ( '' !== trim( $error ) ) {
		throw new RuntimeException( 'The upgrade stage emitted unexpected PHP diagnostics: ' . $stage . "\n" . $error ); }
	return $result;
}

if ( '' === $stage ) {
	if ( file_exists( $state_file ) ) {
		throw new RuntimeException( 'Use a fresh private state path for each independent upgrade run.' ); }
	rktsky_rename_package( (string) getenv( 'RKTSKY_ANTISPAM_RENAME_OLD_ROOT' ), $root . '/wp-content/plugins/wp-antispam', 'wp-antispam.php' );
	rktsky_rename_package( (string) getenv( 'RKTSKY_ANTISPAM_RENAME_NEW_ROOT' ), $root . '/wp-content/plugins/rocketsky-antispam', 'rocketsky-antispam.php' );
	$total = 0;
	foreach ( array( 'activate-old', 'seed', 'activation-conflict', 'uninstall-new-while-old', 'deactivate-old', 'activate-new', 'verify', 'reverse-activation-conflict', 'uninstall-guard', 'subsite-uninstall-guard' ) as $step ) {
		$result = rktsky_rename_child( $step );
		$total += $result['assertions'];
		echo ( $result['skipped'] ? 'SKIP: ' : 'PASS: ' ) . $step . ' (' . $result['assertions'] . " native fixture assertions)\n";
	}
	echo 'PASS: ' . $total . " native rename assertions.\n";
	return;
}

$url = parse_url( getenv( 'RKTSKY_ANTISPAM_TEST_URL' ) ?: 'http://127.0.0.1:8877' );
if ( ! in_array( $url['host'] ?? '', array( '127.0.0.1', 'localhost', '[::1]' ), true ) ) {
	throw new RuntimeException( 'Upgrade fixtures must use loopback origins.' ); }
$_SERVER['HTTP_HOST']      = $url['host'] . ( isset( $url['port'] ) ? ':' . $url['port'] : '' );
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$booted = false;
register_shutdown_function(
	static function () use ( &$booted ): void {
		if ( ! $booted ) {
			fwrite( STDERR, "Native upgrade bootstrap terminated prematurely.\n" );
			exit( 1 );
		} }
);
require $root . '/wp-load.php';
$booted = true;
require_once ABSPATH . 'wp-admin/includes/admin.php';
$target_header = get_file_data( WP_PLUGIN_DIR . '/' . $new, array( 'Version' => 'Version' ) );
$target_version = $target_header['Version'];
rktsky_rename_expect( defined( 'RKTSKY_ANTISPAM_TEST_FIXTURE' ) && true === RKTSKY_ANTISPAM_TEST_FIXTURE && 'local' === wp_get_environment_type(), 'The configured site must remain an explicitly marked local fixture.' );
rktsky_rename_expect( false === apply_filters( 'pre_wp_mail', null, array() ) && is_wp_error( apply_filters( 'pre_http_request', false, array(), 'https://example.invalid/' ) ), 'Actual outgoing mail and every external WordPress HTTP request must be blocked.' );
wp_set_current_user( 1 );
$network_mode = is_multisite();
$state        = is_file( $state_file ) ? json_decode( file_get_contents( $state_file ), true, 64, JSON_THROW_ON_ERROR ) : array();

/**
 * Reads only the six previously approved owned tables in a stable bounded order.
 *
 * @since 0.5.1
 * @return array<string,array> Exact per-site synthetic stored rows.
 */
function rktsky_rename_rows(): array {
	global $wpdb;
	$result = array();
	foreach ( array( 'tokens', 'stats', 'totals', 'quarantine', 'feedback', 'jobs' ) as $suffix ) {
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i LIMIT 10001', $wpdb->prefix . 'rktsky_antispam_' . $suffix ), ARRAY_A );
		if ( ! is_array( $rows ) || count( $rows ) > 10000 ) {
			throw new RuntimeException( 'Owned upgrade state exceeds the bounded snapshot.' ); }
		usort( $rows, static fn( array $a, array $b ): int => strcmp( wp_json_encode( $a ), wp_json_encode( $b ) ) );
		$result[ $suffix ] = $rows;
	}
	return $result;
}

/**
 * Reads owned cron event details through WordPress's native Options API.
 *
 * @since 0.5.1
 * @return array Stable due times, schedules, arguments and event identities.
 */
function rktsky_rename_cron(): array {
	$result = array();
	foreach ( (array) get_option( 'cron', array() ) as $timestamp => $hooks ) {
		foreach ( is_array( $hooks ) ? $hooks : array() as $hook => $events ) {
			if ( str_starts_with( $hook, 'rktsky_antispam_' ) ) {
				$result[ $timestamp ][ $hook ] = $events; }
		}
	}
	return $result;
}

/**
 * Compares native cron identities and multiplicity after normal reactivation.
 *
 * Normal deactivation removes recurring events; activation may choose new due
 * times. Keeping each event in the list also detects duplicate schedules.
 *
 * @since 0.5.1
 * @param array $cron Owned events previously read through the Options API.
 * @return string[] Sorted event identities without their due timestamps.
 */
function rktsky_rename_cron_shape( array $cron ): array {
	$result = array();
	foreach ( $cron as $hooks ) {
		foreach ( $hooks as $hook => $events ) {
			foreach ( $events as $key => $event ) {
				$result[] = wp_json_encode( array( $hook, $key, $event ) );
			}
		}
	}
	sort( $result );
	return $result;
}

/**
 * Runs a protected operation in one native site and restores the current context.
 *
 * @since 0.5.1
 * @param int      $site Native site ID.
 * @param callable $operation Site-local test work.
 * @return mixed Result of the native operation.
 */
function rktsky_rename_in( int $site, callable $operation ) {
	if ( ! is_multisite() ) {
		return $operation(); }
	switch_to_blog( $site );
	try {
		return $operation();
	} finally {
		restore_current_blog(); }
}

/**
 * Converts native wp_die into an observable failure boundary in this CLI test.
 *
 * @since 0.5.1
 * @return void
 */
function rktsky_rename_die_handler(): void {
	add_filter(
		'wp_die_handler',
		static function () {
			return static function ( $message ): void {
				throw new RuntimeException( 'rename-protected-stop: ' . wp_strip_all_tags( (string) $message ), 409 );
			};
		}
	);
}

if ( 'activate-old' === $stage || 'activate-new' === $stage ) {
	$target = 'activate-old' === $stage ? $old : $new;
	$calls  = 0;
	add_action(
		'activate_' . $target,
		static function () use ( &$calls ): void {
			++$calls;
		},
		PHP_INT_MAX
	);
	$activated = activate_plugin( $target, '', $network_mode );
	rktsky_rename_expect( ! is_wp_error( $activated ) && 1 === $calls, 'Native activation must succeed and execute once.' );
	rktsky_rename_expect( $network_mode ? is_plugin_active_for_network( $target ) : is_plugin_active( $target ), 'The selected basename must be active in its original site/network scope.' );
} elseif ( 'seed' === $stage ) {
	rktsky_rename_expect( '0.5.0' === RKTSKY_ANTISPAM_VERSION && plugin_basename( RKTSKY_ANTISPAM_FILE ) === $old, 'Seed data must come from the actual old 0.5.0 package.' );
	$sites = array( get_current_blog_id() );
	if ( $network_mode ) {
		$native_network = get_network();
		$site           = wp_insert_site(
			array(
				'network_id' => get_current_network_id(),
				'domain'     => $native_network->domain,
				'path'       => '/rename-' . wp_generate_password( 10, false, false ) . '/',
				'title'      => 'Synthetic rename site',
				'user_id'    => 1,
			)
		);
		rktsky_rename_expect( is_int( $site ) && $site > 0, 'Core must initialize a second independent network site.' );
		$sites[] = $site;
	}
	foreach ( $sites as $site ) {
		$state['sites'][ $site ] = rktsky_rename_in(
			$site,
			static function () use ( $site ): array {
				$settings                       = new Settings();
				$options                        = $settings->defaults();
				$options['comments']            = true;
				$options['minimum_seconds']     = 0;
				$options['privacy_mode']        = false;
				$options['quarantine_enabled']  = true;
				$options['quarantine_days']     = 9;
				$options['retention_days']      = 41;
				$options['delete_on_uninstall'] = true;
				$options['spam_keywords']       = 'synthetic-site-' . $site;
				update_option( Settings::OPTION, $options, false );
				$token  = new Token();
				$store  = new Store();
				$guard  = new Guard( $settings, $token, $store );
				$used   = $token->issue( 'comments', 'rename_upgrade' );
				$unused = $token->issue( 'comments', 'rename_upgrade' );
				rktsky_rename_expect(
					'' === $guard->check(
						'comments',
						'rename_upgrade',
						array(
							'rktsky_antispam_token' => $used['token'],
							$used['trap']           => '',
						)
					),
					'The old native guard must consume the seeded replay proof.'
				);
				$store->record( 'comments', 'honeypot' );
				$store->claim_job( 'rename-fixture-' . $site, time() + DAY_IN_SECONDS );
				$repository = new Quarantine( $settings );
				$repository->capture( 'comments', 'rename_upgrade', 'honeypot', array( 'Message' => 'Synthetic encrypted rename message for site ' . $site ), 'rename@example.invalid' );
				$items = $repository->listing()['items'];
				rktsky_rename_expect( 1 === count( $items ), 'The separate old fixture must hold exactly one synthetic encrypted record.' );
				$id       = (int) $items[0]['id'];
				$feedback = new Feedback();
				rktsky_rename_expect( $feedback->review( 'quarantine', $id, false ), 'The old source-linked feedback decision must be reviewable.' );
				return array(
					'option'        => get_option( Settings::OPTION ),
					'schema'        => get_option( 'rktsky_antispam_schema' ),
					'rows'          => rktsky_rename_rows(),
					'cron'          => rktsky_rename_cron(),
					'used'          => $used,
					'unused'        => $unused,
					'intent'        => $token->intent( 'comments', 'rename_upgrade' ),
					'quarantine_id' => $id,
					'plain'         => $repository->read( $id ),
					'feedback'      => $feedback->status( 'quarantine', $id ),
				);
			}
		);
	}
	$state['network']         = $network_mode;
	$state['table_inventory'] = Plugin::TABLES;
	if ( false === file_put_contents( $state_file, wp_json_encode( $state ), LOCK_EX ) || ! chmod( $state_file, 0600 ) ) {
		throw new RuntimeException( 'The private synthetic upgrade state could not be saved.' ); }
} elseif ( 'activation-conflict' === $stage ) {
	$before        = rktsky_rename_rows();
	$active_before = $network_mode ? get_site_option( 'active_sitewide_plugins' ) : get_option( 'active_plugins' );
	rktsky_rename_die_handler();
	$stopped = false;
	try {
		activate_plugin( $new, '', $network_mode );
	} catch ( RuntimeException $error ) {
		$stopped = 409 === $error->getCode() && str_starts_with( $error->getMessage(), 'rename-protected-stop:' ); }
	rktsky_rename_expect( $stopped, 'Activating the renamed copy beside active legacy code must stop visibly before duplicate includes.' );
	rktsky_rename_expect( $active_before === ( $network_mode ? get_site_option( 'active_sitewide_plugins' ) : get_option( 'active_plugins' ) ) && $before === rktsky_rename_rows(), 'A refused duplicate activation must preserve activation and all stored data.' );
} elseif ( 'uninstall-new-while-old' === $stage ) {
	$before = rktsky_rename_rows();
	$option = get_option( Settings::OPTION );
	rktsky_rename_die_handler();
	$stopped = false;
	try {
		uninstall_plugin( $new );
	} catch ( RuntimeException $error ) {
		$stopped = 409 === $error->getCode() && str_starts_with( $error->getMessage(), 'rename-protected-stop:' ); }
	rktsky_rename_expect( $stopped && $before === rktsky_rename_rows() && $option === get_option( Settings::OPTION ), 'The new inactive copy must refuse uninstall while old code still owns shared data.' );
} elseif ( 'reverse-activation-conflict' === $stage ) {
	$before        = rktsky_rename_rows();
	$active_before = $network_mode ? get_site_option( 'active_sitewide_plugins' ) : get_option( 'active_plugins' );
	rktsky_rename_expect( ! current_user_can( 'activate_plugin', $old ), 'Native administrator and REST activation capability must refuse the obsolete loader.' );
	global $wp_version;
	if ( version_compare( $wp_version, '6.9', '>=' ) ) {
		$result = activate_plugin( $old, '', $network_mode );
		rktsky_rename_expect( is_wp_error( $result ) && 'rktsky_antispam_installation_conflict' === $result->get_error_code(), 'The native requirements check must refuse legacy activation before including its unguarded classes.' );
	} else {
		echo "SKIP: Core before 6.9 has no direct-activation requirements filter; its native UI/REST capability denial was verified above.\n";
	}
	rktsky_rename_expect( $before === rktsky_rename_rows() && $active_before === ( $network_mode ? get_site_option( 'active_sitewide_plugins' ) : get_option( 'active_plugins' ) ), 'Reverse activation refusal must preserve data and existing activation.' );
} elseif ( 'deactivate-old' === $stage ) {
	// Default to the documented administrator handover. Silent mode separately
	// covers the official upgrader's preservation of existing event due times.
	deactivate_plugins( $old, '1' === getenv( 'RKTSKY_ANTISPAM_RENAME_SILENT' ), $network_mode );
	rktsky_rename_expect( ! is_plugin_active( $old ), 'Old code must be deactivated through Core without invoking uninstall.' );
} elseif ( 'verify' === $stage ) {
	rktsky_rename_expect( $target_version === RKTSKY_ANTISPAM_VERSION && plugin_basename( RKTSKY_ANTISPAM_FILE ) === $new, 'A fresh request must boot the canonical renamed entry point at its declared release version.' );
	rktsky_rename_expect( Settings::OPTION === 'rktsky_antispam_settings' && Plugin::TABLES === $state['table_inventory'], 'Existing settings and all six approved physical table names must remain stable.' );
	$header = get_plugin_data( WP_PLUGIN_DIR . '/' . $new, false, false );
	rktsky_rename_expect( 'Rocketsky Antispam' === $header['Name'] && 'rocketsky-antispam' === $header['TextDomain'] && $target_version === $header['Version'], 'Native plugin metadata must expose the new name, version and text domain.' );
	foreach ( array( 'de_CH', 'de_DE', 'es_ES', 'fr_FR', 'it_IT' ) as $locale ) {
		rktsky_rename_expect( is_file( WP_PLUGIN_DIR . '/rocketsky-antispam/languages/rocketsky-antispam-' . $locale . '.mo' ), 'Every shipped native translation catalogue must use the renamed text domain.' ); }
	rktsky_rename_expect( array() === glob( WP_PLUGIN_DIR . '/rocketsky-antispam/languages/wp-antispam*' ), 'The renamed package must not ship old-domain catalogues.' );
	rktsky_rename_expect( load_textdomain( 'rocketsky-antispam', WP_PLUGIN_DIR . '/rocketsky-antispam/languages/rocketsky-antispam-de_CH.mo', 'de_CH' ) && 'Einstellungen' === __( 'Settings', 'rocketsky-antispam' ), 'WordPress must actually parse and use the renamed Swiss German catalogue.' );
	foreach ( $state['sites'] as $site => $before ) {
		rktsky_rename_in(
			(int) $site,
			static function () use ( $before ): void {
				rktsky_rename_expect( get_option( Settings::OPTION ) === $before['option'] && get_option( 'rktsky_antispam_schema' ) === $before['schema'], 'Native upgrade must preserve each site\'s complete settings and schema marker.' );
				rktsky_rename_expect( rktsky_rename_rows() === $before['rows'], 'All replay/counter/ciphertext/feedback/job rows must survive without remapping identifiers.' );
				$cron           = rktsky_rename_cron();
				$preserved_cron = '1' === getenv( 'RKTSKY_ANTISPAM_RENAME_SILENT' ) ? $cron === $before['cron'] : rktsky_rename_cron_shape( $cron ) === rktsky_rename_cron_shape( $before['cron'] );
				rktsky_rename_expect( $preserved_cron, 'The native handover must retain exactly one matching maintenance schedule per original event; silent upgrades also preserve due times.' );
				$settings   = new Settings();
				$token      = new Token();
				$store      = new Store();
				$repository = new Quarantine( $settings );
				rktsky_rename_expect( $before['plain'] === $repository->read( $before['quarantine_id'] ), 'The existing encrypted record must decrypt with the unchanged per-site key.' );
				rktsky_rename_expect( $before['feedback'] === ( new Feedback() )->status( 'quarantine', $before['quarantine_id'] ), 'An existing source-derived HMAC feedback reference must retain its human classification.' );
				rktsky_rename_expect( $token->valid_intent( $before['intent'], 'comments', 'rename_upgrade' ) && false !== $token->verify( $before['unused']['token'], 'comments', 'rename_upgrade' ), 'Cached old descriptors and unspent signed proof must remain valid.' );
				$guard = new Guard( $settings, $token, $store );
				rktsky_rename_expect(
					'replay' === $guard->check(
						'comments',
						'rename_upgrade',
						array(
							'rktsky_antispam_token' => $before['used']['token'],
							$before['used']['trap'] => '',
						)
					),
					'Consumed old proof must remain a replay after the rename.'
				);
				rktsky_rename_expect(
					'' === $guard->check(
						'comments',
						'rename_upgrade',
						array(
							'rktsky_antispam_token'   => $before['unused']['token'],
							$before['unused']['trap'] => '',
						)
					),
					'Unspent old proof must pass once through the new guard.'
				);
				( new Fields( $settings, $token ) )->attributes( 'comments', 'rename_upgrade' );
				$script = wp_scripts()->registered['rktsky-antispam'];
				rktsky_rename_expect( str_contains( $script->src, '/rocketsky-antispam/assets/protection.js' ) && RKTSKY_ANTISPAM_VERSION === $script->ver, 'The stable script handle must point to the new package and refresh its cache version.' );
			}
		);
	}
	if ( $network_mode ) {
		rktsky_rename_expect( Network::active(), 'Renamed code must retain native network-wide activation.' );
		$ids   = array_keys( $state['sites'] );
		$other = $state['sites'][ $ids[1] ]['unused']['token'];
		rktsky_rename_expect( false === ( new Token() )->verify( $other, 'comments', 'rename_upgrade' ), 'The rename must not weaken cross-site signed-proof separation.' );
	}
	rest_get_server();
	$request = new WP_REST_Request( 'POST', '/rktsky-antispam/v1/challenge' );
	$request->set_body_params(
		array(
			'provider' => 'comments',
			'form'     => 'rename_upgrade',
			'intent'   => $state['sites'][ get_current_blog_id() ]['intent'],
		)
	);
	$response = rest_do_request( $request );
	rktsky_rename_expect( 200 === $response->get_status() && ! empty( $response->get_data()['token'] ), 'The existing REST namespace must accept a cached old descriptor and issue new proof.' );
} elseif ( 'uninstall-guard' === $stage ) {
	$before = rktsky_rename_rows();
	$option = get_option( Settings::OPTION );
	rktsky_rename_die_handler();
	$stopped = false;
	try {
		uninstall_plugin( $old );
	} catch ( RuntimeException $error ) {
		$stopped = 409 === $error->getCode() && str_starts_with( $error->getMessage(), 'rename-protected-stop:' ); }
	rktsky_rename_expect( $stopped && $before === rktsky_rename_rows() && $option === get_option( Settings::OPTION ), 'Core uninstall of the inactive legacy copy must be stopped before shared data cleanup.' );
} elseif ( 'subsite-uninstall-guard' === $stage ) {
	if ( $network_mode ) {
		$ids         = array_keys( $state['sites'] );
		$site        = (int) $ids[1];
		$site_active = get_blog_option( $site, 'active_plugins', array() );
		$before      = rktsky_rename_rows();
		deactivate_plugins( $new, true, true );
		update_blog_option( $site, 'active_plugins', array_merge( $site_active, array( $new ) ) );
		rktsky_rename_die_handler();
		$stopped = false;
		try {
			uninstall_plugin( $new );
		} catch ( RuntimeException $error ) {
			$stopped = 409 === $error->getCode() && str_starts_with( $error->getMessage(), 'rename-protected-stop:' ); } finally {
			update_blog_option( $site, 'active_plugins', $site_active );
			$restored = activate_plugin( $new, '', true );
			if ( is_wp_error( $restored ) ) {
				throw new RuntimeException( 'Native network activation could not be restored.' ); }
			}
			rktsky_rename_expect( $stopped && $before === rktsky_rename_rows(), 'Uninstall must protect the same basename active on another subsite, even when the current site and network are inactive.' );
			rktsky_rename_expect( Network::active(), 'The native network activation must be restored after the isolated subsite conflict.' );
	}
} else {
	throw new RuntimeException( 'Unknown native rename stage.' ); }

echo 'RESULT: ' . wp_json_encode(
	array(
		'stage'      => $stage,
		'network'    => $network_mode,
		'skipped'    => 'subsite-uninstall-guard' === $stage && ! $network_mode,
		'assertions' => $GLOBALS['rktsky_rename_assertions'],
	)
) . "\n";
