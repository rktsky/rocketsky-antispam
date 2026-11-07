<?php
/**
 * Loads an explicitly marked disposable WordPress site for native regression tests.
 *
 * @package RktskyAntispamTests
 * @since 0.1.0
 */

if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'WP_ANTISPAM_ALLOW_TESTS' ) ) {
	throw new RuntimeException( 'Explicit CLI test authorization is required.' );
}

$rktsky_test_root = realpath( (string) getenv( 'WP_ANTISPAM_WP_ROOT' ) );
if ( false === $rktsky_test_root || ! is_file( $rktsky_test_root . '/.rktsky-antispam-fixture' ) || 'rktsky-antispam-disposable' !== trim( file_get_contents( $rktsky_test_root . '/.rktsky-antispam-fixture' ) ) ) {
	throw new RuntimeException( 'A disposable WordPress fixture marker is required.' );
}

$_SERVER['HTTP_HOST']      = '127.0.0.1:8877';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $rktsky_test_root . '/wp-load.php';
if ( ! defined( 'WP_ANTISPAM_TEST_FIXTURE' ) || true !== WP_ANTISPAM_TEST_FIXTURE || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Refusing to modify a site without the explicit local fixture configuration.' );
}

if ( ! class_exists( '\Rktsky\Antispam\Guard' ) ) {
	throw new RuntimeException( 'Activate WP Antispam on the disposable site before running native tests.' );
}
