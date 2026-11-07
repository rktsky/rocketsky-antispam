<?php
/**
 * Parses owned PHP sources without loading WordPress.
 *
 * @package RktskyAntispamTests
 * @since 0.1.0
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$root   = dirname( __DIR__ );
$files  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
$failed = false;
$count  = 0;

foreach ( $files as $file ) {
	$relative = substr( $file->getPathname(), strlen( $root ) + 1 );
	if ( ! $file->isFile() || 'php' !== $file->getExtension() || preg_match( '#^(vendor|\.git|node_modules)/#', $relative ) ) {
		continue;
	}
	++$count;
	$output = array();
	$status = 0;
	exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file->getPathname() ) . ' 2>&1', $output, $status );
	if ( 0 !== $status ) {
		$failed = true;
		fwrite( STDERR, implode( "\n", $output ) . "\n" );
	}
}

echo 'Parsed ' . $count . ' owned PHP files.' . "\n";
exit( $failed ? 1 : 0 );
