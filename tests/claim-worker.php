<?php
/**
 * Attempts one replay claim in a separate native database connection.
 *
 * @package RktskyAntispamTests
 * @since 0.1.0
 */

require __DIR__ . '/bootstrap.php';

$digest = $argv[1] ?? '';
$expiry = isset( $argv[2] ) ? (int) $argv[2] : 0;
if ( ! preg_match( '/^[a-f0-9]{64}$/D', $digest ) || $expiry < time() || $expiry > time() + 7200 ) {
	exit( 9 );
}

exit( ( new \Rktsky\Antispam\Store() )->claim( $digest, $expiry ) ? 0 : 3 );
