<?php
/**
 * Attempts one optional quarantine capture through a separate database connection.
 *
 * @package RktskyAntispamTests
 * @since 0.2.0
 */
require __DIR__ . '/bootstrap.php';
if ( ! class_exists( '\Rktsky\Antispam\Quarantine' ) ) {
	require dirname( __DIR__ ) . '/src/Quarantine.php';
}
( new \Rktsky\Antispam\Quarantine( new \Rktsky\Antispam\Settings() ) )->capture( 'comments', '42', 'honeypot', array( 'Message' => 'Synthetic concurrent quarantine test.' ), 'fixture@example.invalid' );
