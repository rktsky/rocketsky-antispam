<?php
/**
 * Verifies bounded fail-closed evaluation of a pathological local PCRE rule.
 *
 * @package RktskyAntispamTests
 * @since 0.2.0
 */
require __DIR__ . '/bootstrap.php';
$settings = new \Rktsky\Antispam\Settings();
$original = $settings->all();
try {
	$options = $settings->defaults();
	$options['spam_patterns'] = '^(a+)+$';
	update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
	$rules = new \Rktsky\Antispam\ContentRules( $settings, new \Rktsky\Antispam\Language(), new \Rktsky\Antispam\Country( $settings ) );
	$started = hrtime( true );
	$reason = $rules->check( 'cf7', array( 'Message' => str_repeat( 'a', 4000 ) . '!' . str_repeat( 'a', 10000 ) ) );
	$elapsed = ( hrtime( true ) - $started ) / 1000000000;
	if ( 'pattern_limit' !== $reason || $elapsed > 5 ) {
		throw new RuntimeException( 'A pathological rule must fail closed as a technical limit within its bounded evaluation budget.' );
	}
	if ( null !== \Rktsky\Antispam\Settings::regex( str_repeat( 'a', 257 ) ) || null !== \Rktsky\Antispam\Settings::regex( '[' ) ) {
		throw new RuntimeException( 'Oversized or invalid administrator patterns must be rejected before evaluation.' );
	}
	echo 'PASS: pathological PCRE input (14001 bytes) rejected as pattern_limit in ' . number_format( $elapsed, 6, '.', '' ) . " seconds; invalid/oversized patterns omitted.\n";
} finally {
	update_option( \Rktsky\Antispam\Settings::OPTION, $original, false );
}
