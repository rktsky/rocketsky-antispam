<?php
/**
 * Observes original Divi 5 delivery boundaries in explicitly disposable sites.
 *
 * Install this owned file through prepare-divi5.php observer. The configured
 * private log contains synthetic case labels and verdicts, never contact values.
 * Actual mail delivery and all WordPress HTTP requests remain blocked.
 *
 * @package RktskyAntispamTests
 * @since 0.3.1
 */

if ( ! defined( 'WP_ANTISPAM_TEST_FIXTURE' ) || true !== WP_ANTISPAM_TEST_FIXTURE || 'local' !== wp_get_environment_type() ) {
	return;
}
if ( ! defined( 'RKTSKY_DIVI5_FIXTURE_LOG' ) || ! is_string( RKTSKY_DIVI5_FIXTURE_LOG ) || '' === RKTSKY_DIVI5_FIXTURE_LOG || ! is_file( ABSPATH . '.rktsky-antispam-fixture' ) || 'rktsky-antispam-disposable' !== trim( file_get_contents( ABSPATH . '.rktsky-antispam-fixture' ) ) ) {
	throw new RuntimeException( 'The Divi 5 test observer requires the exact fixture marker and private log configuration.' );
}
$rktsky_divi5_log_parent  = realpath( dirname( RKTSKY_DIVI5_FIXTURE_LOG ) );
$rktsky_divi5_public_root = realpath( ABSPATH );
if ( false === $rktsky_divi5_log_parent || false === $rktsky_divi5_public_root || is_link( RKTSKY_DIVI5_FIXTURE_LOG ) || $rktsky_divi5_log_parent === $rktsky_divi5_public_root || str_starts_with( $rktsky_divi5_log_parent, $rktsky_divi5_public_root . DIRECTORY_SEPARATOR ) ) {
	throw new RuntimeException( 'The Divi 5 test log must be a regular private path outside the public site root.' );
}

/**
 * Appends only one synthetic event and its optional native boolean verdict.
 *
 * @since 0.3.1
 * @param string $event Fixed native observation name.
 * @param array  $detail Optional native error flag; other values are discarded.
 * @return void
 */
function rktsky_divi5_event( string $event, array $detail = array() ): void {
	if ( ! in_array( $event, array( 'mail_attempt', 'native_submit', 'native_recipient' ), true ) ) {
		return;
	}
	$case = $_SERVER['HTTP_X_RK_CASE'] ?? 'unlabelled';
	$case = is_string( $case ) && 1 === preg_match( '/^[a-z0-9_-]{1,80}$/D', $case ) ? $case : 'invalid';
	$row  = array(
		'case'  => $case,
		'event' => $event,
	);
	if ( 'native_submit' === $event ) {
		$row['error'] = ! empty( $detail['error'] );
	}
	file_put_contents( RKTSKY_DIVI5_FIXTURE_LOG, wp_json_encode( $row ) . "\n", FILE_APPEND | LOCK_EX );
}

/**
 * Observes a genuine wp_mail call before the final native delivery short circuit.
 *
 * @since 0.3.1
 * @param mixed $previous Earlier mail interception result, left unchanged.
 * @return mixed Unmodified earlier result; the final blocker returns true.
 */
function rktsky_divi5_fixture_mail( $previous ) {
	rktsky_divi5_event( 'mail_attempt' );
	return $previous;
}
add_filter( 'pre_wp_mail', 'rktsky_divi5_fixture_mail', -PHP_INT_MAX );
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );

/**
 * Prevents every WordPress HTTP request from reaching a remote service.
 *
 * @since 0.3.1
 * @param mixed $previous Earlier interception result, superseded by the block.
 * @return WP_Error Disposable-fixture transport failure.
 */
function rktsky_divi5_fixture_http( $previous ): WP_Error {
	return new WP_Error( 'rktsky_fixture_no_http', 'WordPress HTTP is disabled in this disposable fixture.' );
}
add_filter( 'pre_http_request', 'rktsky_divi5_fixture_http', PHP_INT_MAX );

/**
 * Counts the original contact-submit action without reading native field values.
 *
 * @since 0.3.1
 * @param mixed $fields Original fields, never read or recorded.
 * @param mixed $error Original validation verdict, reduced to a boolean.
 * @param mixed $info Original provider context, never read or recorded.
 * @return void
 */
function rktsky_divi5_fixture_native( $fields, $error, $info ): void {
	rktsky_divi5_event( 'native_submit', array( 'error' => (bool) $error ) );
}
add_action( 'et_pb_contact_form_submit', 'rktsky_divi5_fixture_native', 10, 3 );

/**
 * Counts the original recipient-selection boundary without recording addresses.
 *
 * @since 0.3.1
 * @param mixed $recipient Original recipient setting, passed through untouched.
 * @return mixed Unmodified native recipient setting.
 */
function rktsky_divi5_fixture_recipient( $recipient ) {
	rktsky_divi5_event( 'native_recipient' );
	return $recipient;
}
add_filter( 'et_contact_page_email_to', 'rktsky_divi5_fixture_recipient' );
