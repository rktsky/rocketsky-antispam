<?php
/**
 * Loads an explicitly marked disposable WordPress site for native regression tests.
 *
 * @package RktskyAntispamTests
 * @since 0.1.0
 */

if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'RKTSKY_ANTISPAM_ALLOW_TESTS' ) ) {
	throw new RuntimeException( 'Explicit CLI test authorization is required.' );
}

$rktsky_test_root = realpath( (string) getenv( 'RKTSKY_ANTISPAM_WP_ROOT' ) );
if ( false === $rktsky_test_root || ! is_file( $rktsky_test_root . '/.rktsky-antispam-fixture' ) || 'rktsky-antispam-disposable' !== trim( file_get_contents( $rktsky_test_root . '/.rktsky-antispam-fixture' ) ) ) {
	throw new RuntimeException( 'A disposable WordPress fixture marker is required.' );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1:8877';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
// A native redirect/exit before wp-load returns must fail instead of passing CI.
$rktsky_test_booted = false;
register_shutdown_function(
	static function () use ( &$rktsky_test_booted ): void {
		if ( ! $rktsky_test_booted ) {
			fwrite( STDERR, "Disposable WordPress bootstrap terminated before the native tests could run.\n" );
			exit( 1 );
		}
	}
);
require $rktsky_test_root . '/wp-load.php';
$rktsky_test_booted = true;
if ( ! defined( 'RKTSKY_ANTISPAM_TEST_FIXTURE' ) || true !== RKTSKY_ANTISPAM_TEST_FIXTURE || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Refusing to modify a site without the explicit local fixture configuration.' );
}

if ( ! class_exists( '\Rktsky\Antispam\Guard' ) ) {
	throw new RuntimeException( 'Activate Rocketsky Antispam on the disposable site before running native tests.' );
}
