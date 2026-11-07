<?php
/**
 * Observes native Divi delivery while blocking mail and HTTP in disposable sites.
 *
 * Install this owned test file as an MU plugin in a marked local fixture only.
 * RKTSKY_DIVI4_FIXTURE_LOG must point to a private file outside its public root.
 * The log contains case IDs and verdicts, never message contents or addresses.
 *
 * @package RktskyAntispamTests
 * @since 0.3.1
 */

if ( ! defined( 'WP_ANTISPAM_TEST_FIXTURE' ) || true !== WP_ANTISPAM_TEST_FIXTURE || 'local' !== wp_get_environment_type() || ! defined( 'RKTSKY_DIVI4_FIXTURE_LOG' ) || ! is_file( ABSPATH . '.rktsky-antispam-fixture' ) || 'rktsky-antispam-disposable' !== trim( file_get_contents( ABSPATH . '.rktsky-antispam-fixture' ) ) ) {
	return;
}

// Invalid fixture configuration must terminate before native delivery can run.
$rktsky_divi4_log_directory = realpath( dirname( RKTSKY_DIVI4_FIXTURE_LOG ) );
$rktsky_divi4_public_root   = realpath( ABSPATH );
if ( false === $rktsky_divi4_log_directory || false === $rktsky_divi4_public_root || $rktsky_divi4_log_directory === $rktsky_divi4_public_root || str_starts_with( $rktsky_divi4_log_directory, $rktsky_divi4_public_root . DIRECTORY_SEPARATOR ) || is_link( RKTSKY_DIVI4_FIXTURE_LOG ) ) {
	throw new RuntimeException( 'The disposable Divi observer requires a private log outside the public root.' );
}

/**
 * Appends a bounded non-personal native observation to the private fixture log.
 *
 * @since 0.3.1
 * @param string $event Fixed observer event name.
 * @param array  $data Native boolean verdict or numeric form index only.
 * @return void
 */
function rktsky_antispam_divi4_fixture_event( string $event, array $data = array() ): void {
	$case = isset( $_SERVER['HTTP_X_RK_FIXTURE_CASE'] ) && is_string( $_SERVER['HTTP_X_RK_FIXTURE_CASE'] ) ? substr( sanitize_key( wp_unslash( $_SERVER['HTTP_X_RK_FIXTURE_CASE'] ) ), 0, 100 ) : 'setup';
	file_put_contents(
		RKTSKY_DIVI4_FIXTURE_LOG,
		wp_json_encode(
			array(
				'event' => $event,
				'case'  => $case,
			) + $data
		) . "\n",
		FILE_APPEND | LOCK_EX
	);
}

/**
 * Stops actual delivery while preserving the native successful-mail result.
 *
 * @since 0.3.1
 * @param mixed $previous Previous native short-circuit value.
 * @return bool True records the genuine call without dispatching any mail.
 */
function rktsky_antispam_divi4_fixture_mail( $previous ): bool {
	rktsky_antispam_divi4_fixture_event( 'mail' );
	return true;
}
add_filter( 'pre_wp_mail', 'rktsky_antispam_divi4_fixture_mail', PHP_INT_MAX );

/**
 * Blocks all outgoing WordPress HTTP before it reaches the network.
 *
 * @since 0.3.1
 * @param mixed  $previous Previous native HTTP interception value.
 * @param array  $arguments Original options, never logged.
 * @param string $url Requested URL, never logged.
 * @return WP_Error Explicit disposable-fixture transport rejection.
 */
function rktsky_antispam_divi4_fixture_http( $previous, array $arguments, string $url ): WP_Error {
	rktsky_antispam_divi4_fixture_event( 'blocked_http' );
	return new WP_Error( 'rktsky_fixture_http_blocked', 'HTTP transport blocked in the disposable fixture.' );
}
add_filter( 'pre_http_request', 'rktsky_antispam_divi4_fixture_http', PHP_INT_MAX, 3 );

/**
 * Observes the original late submit action without treating it as validation.
 *
 * @since 0.3.1
 * @param array $fields Original values, never read or recorded.
 * @param bool  $error Native form-validation verdict.
 * @param array $info Native context containing the numeric form counter.
 * @return void
 */
function rktsky_antispam_divi4_fixture_native( array $fields, bool $error, array $info ): void {
	rktsky_antispam_divi4_fixture_event(
		'native',
		array(
			'error' => $error,
			'form'  => (int) ( $info['contact_form_number'] ?? -1 ),
		)
	);
}
add_action( 'et_pb_contact_form_submit', 'rktsky_antispam_divi4_fixture_native', PHP_INT_MAX, 3 );

/**
 * Restricts the test observation endpoint to native loopback requests.
 *
 * @since 0.3.1
 * @return bool Whether the connecting peer is loopback.
 */
function rktsky_antispam_divi4_fixture_allowed(): bool {
	return in_array( $_SERVER['REMOTE_ADDR'] ?? '', array( '127.0.0.1', '::1' ), true );
}

/**
 * Returns only one synthetic case's native verdicts and exact mail-call count.
 *
 * @since 0.3.1
 * @param WP_REST_Request $request Case-scoped local observation request.
 * @return WP_REST_Response Non-personal observations with relevant settings.
 */
function rktsky_antispam_divi4_fixture_state( WP_REST_Request $request ): WP_REST_Response {
	$case   = substr( sanitize_key( (string) $request->get_param( 'case' ) ), 0, 100 );
	$events = array();
	foreach ( is_file( RKTSKY_DIVI4_FIXTURE_LOG ) ? file( RKTSKY_DIVI4_FIXTURE_LOG, FILE_IGNORE_NEW_LINES ) : array() as $line ) {
		$row = json_decode( $line, true );
		if ( is_array( $row ) && ( $row['case'] ?? '' ) === $case ) {
			$events[] = $row;
		}
	}
	return new WP_REST_Response(
		array(
			'case'     => $case,
			'mail'     => count( array_filter( $events, static fn( $row ) => 'mail' === $row['event'] ) ),
			'native'   => array_values( array_filter( $events, static fn( $row ) => 'native' === $row['event'] ) ),
			'settings' => array_intersect_key( ( new \Rktsky\Antispam\Settings() )->all(), array_flip( array( 'divi', 'minimum_seconds', 'privacy_mode' ) ) ),
		)
	);
}

/**
 * Registers a deliberately fixture-only native REST observation route.
 *
 * @since 0.3.1
 * @return void
 */
function rktsky_antispam_divi4_fixture_routes(): void {
	register_rest_route(
		'rktsky-fixture/v1',
		'/state',
		array(
			'methods'             => 'GET',
			'permission_callback' => 'rktsky_antispam_divi4_fixture_allowed',
			'callback'            => 'rktsky_antispam_divi4_fixture_state',
		)
	);
}
add_action( 'rest_api_init', 'rktsky_antispam_divi4_fixture_routes' );
