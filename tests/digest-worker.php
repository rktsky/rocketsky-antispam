<?php
/**
 * Attempts one daily digest in a separate connection while intercepting all mail.
 *
 * @package RktskyAntispamTests
 * @since 0.2.0
 */
require __DIR__ . '/bootstrap.php';

$rktsky_digest_directory = realpath( (string) getenv( 'RKTSKY_ANTISPAM_MAIL_DIR' ) );
if ( false === $rktsky_digest_directory || ! preg_match( '/^rktsky-antispam-mail-[a-f0-9]{32}$/D', basename( $rktsky_digest_directory ) ) || ! preg_match( '/^[1-4]$/D', $argv[1] ?? '' ) ) {
	throw new RuntimeException( 'Explicit synthetic mail capture directory is required.' );
}
$rktsky_digest_file = $rktsky_digest_directory . '/worker-' . $argv[1] . '.jsonl';

/**
 * Writes aggregate-only mail attempts locally and preserves the fixture's hard mail block.
 *
 * @since 0.2.0
 * @param mixed $previous Earlier native mail interception decision.
 * @param array $attributes Native wp_mail arguments.
 * @return bool False; no real mailer is called.
 */
function rktsky_antispam_digest_worker_mail( $previous, array $attributes ): bool {
	global $rktsky_digest_file;
	file_put_contents( $rktsky_digest_file, wp_json_encode( $attributes ) . "\n", FILE_APPEND | LOCK_EX );
	return false;
}

add_filter( 'pre_wp_mail', 'rktsky_antispam_digest_worker_mail', PHP_INT_MAX, 2 );
\Rktsky\Antispam\Maintenance::digest();
