<?php
/**
 * Tokenizes supplied PHP source as data for the development-only exception guard.
 *
 * This helper never includes or evaluates the inspected plugin. Its fixed source
 * can be passed with PHP's -r option when PHP runs in an isolated container.
 *
 * @package RktskyAntispamTools
 * @since 0.5.6
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

try {
	if ( ! function_exists( 'token_get_all' ) ) {
		throw new RuntimeException( 'The quality guard needs PHP with its native Tokenizer extension.' );
	}
	$input = json_decode( stream_get_contents( STDIN ), true, 512, JSON_THROW_ON_ERROR );
	if ( ! is_array( $input ) || count( $input ) > 1000 ) {
		throw new RuntimeException( 'Expected a bounded map of source names to PHP text.' );
	}
	$output = array();
	foreach ( $input as $name => $source ) {
		if ( ! is_string( $name ) || ! is_string( $source ) || strlen( $source ) > 4194304 ) {
			throw new RuntimeException( 'Source names and bounded text must be strings.' );
		}
		$line   = 1;
		$tokens = array();
		try {
			$native = token_get_all( $source, TOKEN_PARSE );
		} catch ( ParseError $error ) {
			throw new RuntimeException( $name . ': ' . $error->getMessage(), 0, $error );
		}
		foreach ( $native as $token ) {
			$kind     = is_array( $token ) ? token_name( $token[0] ) : 'character';
			$text     = is_array( $token ) ? $token[1] : $token;
			$end      = $line + substr_count( $text, "\n" );
			$tokens[] = array( $kind, $text, $line, $end );
			$line     = $end;
		}
		$output[ $name ] = $tokens;
	}
	fwrite( STDOUT, json_encode( array( 'php_version' => PHP_VERSION, 'files' => $output ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
} catch ( Throwable $error ) {
	fwrite( STDERR, 'Quality tokenizer: ' . $error->getMessage() . "\n" );
	exit( 1 );
}
