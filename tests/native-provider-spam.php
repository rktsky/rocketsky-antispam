<?php
/**
 * Verifies native spam queues and early technical rejection in free provider engines.
 *
 * Each case boots the unchanged provider in a fresh marked WordPress process.
 * Mail and external HTTP remain blocked. Provider rows are observed through
 * their APIs; no Core/provider SQL or commercial extension simulation is used.
 *
 * @package RktskyAntispamTests
 * @since 0.5.0
 */

require __DIR__ . '/bootstrap.php';

$rktsky_native_settings = new \Rktsky\Antispam\Settings();
$rktsky_native_original = $rktsky_native_settings->all();
$rktsky_native_options = $rktsky_native_original;
$rktsky_native_options['formidable'] = true;
$rktsky_native_options['wsform'] = true;
$rktsky_native_options['fluentforms'] = true;
update_option( \Rktsky\Antispam\Settings::OPTION, $rktsky_native_options, false );
$rktsky_native_count = 0;
try {
	foreach ( array( 'formidable', 'fluentforms', 'wsform' ) as $rktsky_native_provider ) {
		$rktsky_native_cases = array( 'valid', 'keyword', 'quarantine', 'bbcode', 'pattern', 'content_missing', 'content_honeypot', 'content_replay', 'native_block' );
		if ( 'fluentforms' === $rktsky_native_provider ) {
			$rktsky_native_cases = array_diff( $rktsky_native_cases, array( 'native_block' ) );
		}
		if ( 'formidable' === $rktsky_native_provider ) {
			$rktsky_native_cases[] = 'native_redirect';
		}
		foreach ( $rktsky_native_cases as $rktsky_native_case ) {
			$rktsky_native_command = array( PHP_BINARY );
			if ( ! extension_loaded( 'mbstring' ) && is_file( '/tmp/rktsky-antispam-runtime/iconv.so' ) ) {
				$rktsky_native_command = array_merge( $rktsky_native_command, array( '-d', 'extension=/tmp/rktsky-antispam-runtime/iconv.so' ) );
			}
			$rktsky_native_command = array_merge( $rktsky_native_command, array( __DIR__ . '/native-extra-forms.php', $rktsky_native_provider, $rktsky_native_case ) );
			$rktsky_native_pipes = array();
			$rktsky_native_process = proc_open( $rktsky_native_command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $rktsky_native_pipes );
			fclose( $rktsky_native_pipes[0] );
			$rktsky_native_output = stream_get_contents( $rktsky_native_pipes[1] );
			$rktsky_native_error = stream_get_contents( $rktsky_native_pipes[2] );
			fclose( $rktsky_native_pipes[1] );
			fclose( $rktsky_native_pipes[2] );
			$rktsky_native_status = proc_close( $rktsky_native_process );
			if ( 0 !== $rktsky_native_status || ! str_contains( $rktsky_native_output, 'PASS: ' . $rktsky_native_provider . '/' . $rktsky_native_case ) ) {
				throw new RuntimeException( 'Native spam routing failed: ' . $rktsky_native_provider . '/' . $rktsky_native_case . "\n" . $rktsky_native_output . "\n" . $rktsky_native_error );
			}
			echo trim( $rktsky_native_output ) . "\n";
			++$rktsky_native_count;
		}
	}
} finally {
	update_option( \Rktsky\Antispam\Settings::OPTION, $rktsky_native_original, false );
}
echo 'PASS: ' . $rktsky_native_count . " original free-provider native spam-routing cases.\n";
