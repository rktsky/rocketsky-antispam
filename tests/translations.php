<?php
/**
 * Exercises native just-in-time loading, locale changes and installation notices.
 *
 * Run with the same marked disposable fixture variables as tests/bootstrap.php.
 * Each child process uses a private WP_LANG_DIR containing only synthetic test
 * catalogues. No installed language pack, option, user or provider is changed.
 * Core's MO exporter creates the fixtures; Core's own loader reads every result.
 *
 * @package RktskyAntispamTests
 * @since 0.5.2
 */

use Rktsky\Antispam\Installation;

if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'RKTSKY_ANTISPAM_ALLOW_TESTS' ) ) {
	throw new RuntimeException( 'Explicit CLI test authorization is required.' );
}
$rktsky_i18n_root = realpath( (string) getenv( 'RKTSKY_ANTISPAM_WP_ROOT' ) );
if ( ! $rktsky_i18n_root || ! is_file( $rktsky_i18n_root . '/.rktsky-antispam-fixture' ) || 'rktsky-antispam-disposable' !== trim( file_get_contents( $rktsky_i18n_root . '/.rktsky-antispam-fixture' ) ) ) {
	throw new RuntimeException( 'A separately marked disposable WordPress fixture is required.' );
}
$rktsky_i18n_stage      = $argv[1] ?? '';
$rktsky_i18n_assertions = 0;

/**
 * Fails on an observed native translation or lifecycle regression.
 *
 * @since 0.5.2
 * @param bool   $condition Required native behavior.
 * @param string $message Non-personal failure context.
 * @return void
 */
function rktsky_i18n_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_i18n_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Starts a fresh native loader so previous domain/path caches cannot mask errors.
 *
 * @since 0.5.2
 * @param string $stage Fixed, internal test scenario.
 * @return array{assertions:int,wordpress:string,stage:string} Native result.
 */
function rktsky_i18n_child( string $stage ): array {
	$command = array( PHP_BINARY );
	foreach ( array( 'RKTSKY_ANTISPAM_PHP_ICONV', 'RKTSKY_ANTISPAM_PHP_SIMPLEXML' ) as $key ) {
		if ( getenv( $key ) ) {
			$command[] = '-d';
			$command[] = 'extension=' . getenv( $key );
		}
	}
	$command[] = __FILE__;
	$command[] = $stage;
	$pipes     = array();
	$process   = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'The native translation process could not start.' );
	}
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$status = proc_close( $process );
	if ( 0 !== $status || '' !== trim( $error ) || ! preg_match( '/^RESULT: (.+)$/m', $output, $match ) ) {
		throw new RuntimeException( 'Native translation stage failed: ' . $stage . "\n" . $output . "\n" . $error );
	}
	return json_decode( $match[1], true, 16, JSON_THROW_ON_ERROR );
}

if ( '' === $rktsky_i18n_stage ) {
	$rktsky_i18n_results = array();
	foreach ( array( 'bundled', 'language-pack', 'conflict', 'localized-startup' ) as $rktsky_i18n_scenario ) {
		$rktsky_i18n_result    = rktsky_i18n_child( $rktsky_i18n_scenario );
		$rktsky_i18n_results[] = $rktsky_i18n_result;
		echo 'PASS: ' . $rktsky_i18n_scenario . ' (' . $rktsky_i18n_result['assertions'] . " native translation assertions).\n";
	}
	echo 'RESULT: ' . json_encode( $rktsky_i18n_results, JSON_THROW_ON_ERROR ) . "\n";
	return;
}
if ( ! in_array( $rktsky_i18n_stage, array( 'bundled', 'language-pack', 'conflict', 'localized-startup' ), true ) || defined( 'WP_LANG_DIR' ) ) {
	throw new RuntimeException( 'Use only an internal scenario with an uninitialized native loader.' );
}

$rktsky_i18n_private = sys_get_temp_dir() . '/rocketsky-i18n-' . bin2hex( random_bytes( 12 ) );
if ( ! mkdir( $rktsky_i18n_private, 0700 ) || ! mkdir( $rktsky_i18n_private . '/plugins', 0700 ) ) {
	throw new RuntimeException( 'A private synthetic language directory could not be created.' );
}
$rktsky_i18n_files = array();
register_shutdown_function(
	static function () use ( &$rktsky_i18n_files, $rktsky_i18n_private ): void {
		foreach ( $rktsky_i18n_files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		rmdir( $rktsky_i18n_private . '/plugins' );
		rmdir( $rktsky_i18n_private );
	}
);

// Loading unchanged native utility files permits fixture creation before wp-load.
require_once $rktsky_i18n_root . '/wp-includes/pomo/mo.php';
require_once $rktsky_i18n_root . '/wp-includes/plugin.php';
$rktsky_i18n_expected = array( 'de_CH' => 'Einstellungen', 'de_DE' => 'Einstellungen', 'fr_FR' => 'Réglages', 'it_IT' => 'Impostazioni', 'es_ES' => 'Ajustes' );
$rktsky_i18n_empty    = new MO();
$rktsky_i18n_empty->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
foreach ( array_merge( array_keys( $rktsky_i18n_expected ), array( 'zz_ZZ' ) ) as $rktsky_i18n_locale ) {
	$rktsky_i18n_file    = $rktsky_i18n_private . '/' . $rktsky_i18n_locale . '.mo';
	$rktsky_i18n_files[] = $rktsky_i18n_file;
	rktsky_i18n_expect( $rktsky_i18n_empty->export_to_file( $rktsky_i18n_file ), 'The native exporter must create each synthetic Core locale marker.' );
}
if ( 'language-pack' === $rktsky_i18n_stage ) {
	$rktsky_i18n_pack = new MO();
	$rktsky_i18n_pack->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
	$rktsky_i18n_pack->add_entry( new Translation_Entry( array( 'singular' => 'Settings', 'translations' => array( 'Native fixture language pack' ) ) ) );
	$rktsky_i18n_file    = $rktsky_i18n_private . '/plugins/rocketsky-antispam-de_CH.mo';
	$rktsky_i18n_files[] = $rktsky_i18n_file;
	rktsky_i18n_expect( $rktsky_i18n_pack->export_to_file( $rktsky_i18n_file ), 'The native exporter must create the synthetic language pack.' );
	$rktsky_i18n_expected['de_CH'] = 'Native fixture language pack';
}
define( 'WP_LANG_DIR', $rktsky_i18n_private );
if ( 'conflict' === $rktsky_i18n_stage ) {
	// Model an already defined older loader without including or editing its files.
	define( 'RKTSKY_ANTISPAM_FILE', __FILE__ );
}
$rktsky_i18n_early = array();
$rktsky_i18n_foreign = array();
add_action(
	'doing_it_wrong_run',
	static function ( string $function, string $message ) use ( &$rktsky_i18n_early, &$rktsky_i18n_foreign ): void {
		if ( '_load_textdomain_just_in_time' === $function ) {
			if ( str_contains( $message, 'rocketsky-antispam' ) ) {
				$rktsky_i18n_early[] = $message;
			} else {
				// Providers remain loaded. Report their diagnostics without attributing
				// a different text domain's early lookup to this plugin's fallback.
				preg_match( '~<code>([^<]+)</code>~', $message, $domain );
				$rktsky_i18n_foreign[] = $domain[1] ?? 'other-domain';
			}
		}
	},
	10,
	2
);
add_filter( 'pre_option_WPLANG', static fn(): string => 'localized-startup' === $GLOBALS['rktsky_i18n_stage'] ? 'de_CH' : 'en_US' );
$_SERVER['HTTP_HOST']      = '127.0.0.1:8877';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $rktsky_i18n_root . '/wp-load.php';
if ( ! defined( 'RKTSKY_ANTISPAM_TEST_FIXTURE' ) || true !== RKTSKY_ANTISPAM_TEST_FIXTURE || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Refusing a site without explicit local fixture configuration.' );
}

if ( 'localized-startup' === $rktsky_i18n_stage ) {
	// An English startup can hide an early translated lookup in a provider hook.
	// Exercise a real localized boot while leaving every enabled provider loaded.
	rktsky_i18n_expect( 'de_CH' === determine_locale(), 'The entire native loader must start in Swiss German.' );
	rktsky_i18n_expect( 'Einstellungen' === __( 'Settings', 'rocketsky-antispam' ), 'A localized fresh boot must load the shipped catalogue without an English warmup.' );
	rktsky_i18n_expect( array() === $rktsky_i18n_early, 'A localized startup must emit no owned early-loading diagnostics: ' . wp_json_encode( $rktsky_i18n_early ) );
	echo 'RESULT: ' . wp_json_encode( array( 'stage' => $rktsky_i18n_stage, 'assertions' => $rktsky_i18n_assertions, 'wordpress' => $GLOBALS['wp_version'], 'other_plugin_early_loading' => array_values( array_unique( $rktsky_i18n_foreign ) ) ), JSON_THROW_ON_ERROR ) . "\n";
	return;
}

$rktsky_i18n_entry     = WP_PLUGIN_DIR . '/rocketsky-antispam/rocketsky-antispam.php';
$rktsky_i18n_instance  = new Installation( $rktsky_i18n_entry );
$rktsky_i18n_directory = dirname( $rktsky_i18n_entry ) . '/languages/';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$rktsky_i18n_header = get_plugin_data( $rktsky_i18n_entry, false, false );
rktsky_i18n_expect( ! is_textdomain_loaded( 'rocketsky-antispam' ), 'Registration must not eagerly load any catalogue.' );
rktsky_i18n_expect( 'Settings' === __( 'Settings', 'rocketsky-antispam' ), 'An initial English lookup must use the source message.' );
rktsky_i18n_expect( ! is_textdomain_loaded( 'rocketsky-antispam' ), 'An English miss must not load a foreign catalogue.' );
rktsky_i18n_expect( '/native/path/' === $rktsky_i18n_instance->translation_directory( '/native/path/', 'rocketsky-antispam', 'de_CH' ), 'A previously determined native or custom path must remain unchanged.' );
rktsky_i18n_expect( false === apply_filters( 'lang_dir_for_domain', false, 'rktsky-unrelated-domain', 'de_CH' ), 'An unrelated text domain must receive no local fallback.' );
foreach ( array( '../de_CH', "de_CH\0", str_repeat( 'a', 65 ), '', 'zz_ZZ' ) as $rktsky_i18n_invalid ) {
	rktsky_i18n_expect( false === $rktsky_i18n_instance->translation_directory( false, 'rocketsky-antispam', $rktsky_i18n_invalid ), 'Malformed or unshipped locales must not resolve a bundled file.' );
}
foreach ( $rktsky_i18n_expected as $rktsky_i18n_locale => $rktsky_i18n_translation ) {
	rktsky_i18n_expect( switch_to_locale( $rktsky_i18n_locale ), 'The native API must switch from English to each supported locale.' );
	rktsky_i18n_expect( $rktsky_i18n_translation === __( 'Settings', 'rocketsky-antispam' ), 'Native just-in-time loading must use the expected bundled or language-pack translation: ' . $rktsky_i18n_locale );
	rktsky_i18n_expect( is_textdomain_loaded( 'rocketsky-antispam' ), 'Core must report the requested catalogue as loaded.' );
	$rktsky_i18n_catalogue = new MO();
	rktsky_i18n_expect( $rktsky_i18n_catalogue->import_from_file( $rktsky_i18n_directory . 'rocketsky-antispam-' . $rktsky_i18n_locale . '.mo' ), 'Core must read the complete shipped catalogue for header verification.' );
	$rktsky_i18n_description = $rktsky_i18n_catalogue->translate( $rktsky_i18n_header['Description'] );
	rktsky_i18n_expect( $rktsky_i18n_description !== $rktsky_i18n_header['Description'], 'Each shipped catalogue must translate the human-readable plugin description.' );
	// The synthetic native pack intentionally contains Settings only: Core must
	// keep it authoritative rather than merge a second bundled catalogue into it.
	if ( 'language-pack' === $rktsky_i18n_stage && 'de_CH' === $rktsky_i18n_locale ) {
		$rktsky_i18n_description = $rktsky_i18n_header['Description'];
	}
	$rktsky_i18n_translated_header = get_plugin_data( $rktsky_i18n_entry, false, true );
	rktsky_i18n_expect( $rktsky_i18n_description === $rktsky_i18n_translated_header['Description'] && 'Rocketsky Antispam' === $rktsky_i18n_translated_header['Name'], 'The real Plugins screen header must use the selected catalogue while preserving the product name.' );
	rktsky_i18n_expect( 'en_US' === restore_previous_locale(), 'The native locale stack must restore English.' );
	rktsky_i18n_expect( 'Settings' === __( 'Settings', 'rocketsky-antispam' ), 'Restoring English must remove the previous locale from message selection.' );
}
rktsky_i18n_expect( switch_to_locale( 'zz_ZZ' ), 'The native API must permit the synthetic unsupported locale.' );
rktsky_i18n_expect( 'Settings' === __( 'Settings', 'rocketsky-antispam' ), 'An unsupported locale must retain the English source message.' );
restore_previous_locale();
rktsky_i18n_expect( switch_to_locale( 'de_CH' ), 'A native miss must not poison later supported-locale discovery.' );
rktsky_i18n_expect( $rktsky_i18n_expected['de_CH'] === __( 'Settings', 'rocketsky-antispam' ), 'A bundled locale must remain available after unsupported-locale lookup.' );

if ( 'conflict' === $rktsky_i18n_stage ) {
	rktsky_i18n_expect( ! class_exists( Rktsky\Antispam\Guard::class, false ), 'An installation conflict must stop normal plugin services.' );
	$rktsky_i18n_conflict_source = 'Deactivate the previous plugin copy before activating Rocketsky Antispam. Do not delete it: both copies use the same data. Deactivate network-wide copies in Network Admin.';
	$rktsky_i18n_catalogue = new MO();
	rktsky_i18n_expect( $rktsky_i18n_catalogue->import_from_file( $rktsky_i18n_directory . 'rocketsky-antispam-de_CH.mo' ), 'The conflict scenario must read the shipped Swiss German catalogue.' );
	$rktsky_i18n_conflict = $rktsky_i18n_catalogue->translate( $rktsky_i18n_conflict_source );
	rktsky_i18n_expect( $rktsky_i18n_conflict !== $rktsky_i18n_conflict_source, 'The conflict explanation must have a bundled translation.' );
	add_filter( 'user_has_cap', static fn( array $caps ): array => array_merge( $caps, array( 'activate_plugins' => true ) ) );
	ob_start();
	$rktsky_i18n_instance->notice();
	$rktsky_i18n_notice = (string) ob_get_clean();
	rktsky_i18n_expect( str_contains( $rktsky_i18n_notice, esc_html( $rktsky_i18n_conflict ) ), 'A skipped boot must still produce the translated native conflict notice.' );
	$rktsky_i18n_die = static fn(): Closure => static function ( $message, $title, $args ): void {
		throw new RuntimeException( wp_strip_all_tags( (string) $message ), (int) $args['response'] );
	};
	add_filter( 'wp_die_handler', $rktsky_i18n_die );
	try {
		$rktsky_i18n_instance->activate();
		throw new RuntimeException( 'A conflicting activation must stop before returning.' );
	} catch ( RuntimeException $rktsky_i18n_error ) {
		rktsky_i18n_expect( 409 === $rktsky_i18n_error->getCode() && $rktsky_i18n_conflict === $rktsky_i18n_error->getMessage(), 'The native activation failure must be translated without an explicit textdomain load.' );
	}
	remove_filter( 'wp_die_handler', $rktsky_i18n_die );
}
restore_previous_locale();
rktsky_i18n_expect( array() === $rktsky_i18n_early, 'This plugin must emit no early-loading diagnostics: ' . wp_json_encode( $rktsky_i18n_early ) );
echo 'RESULT: ' . wp_json_encode( array( 'stage' => $rktsky_i18n_stage, 'assertions' => $rktsky_i18n_assertions, 'wordpress' => $GLOBALS['wp_version'], 'other_plugin_early_loading' => array_values( array_unique( $rktsky_i18n_foreign ) ) ), JSON_THROW_ON_ERROR ) . "\n";
