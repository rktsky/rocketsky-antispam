<?php
/**
 * Exercises security boundaries and SQL persistence against a disposable native WordPress site.
 *
 * @package RktskyAntispamTests
 * @since 0.1.0
 */

use Rktsky\Antispam\Fields;
use Rktsky\Antispam\Guard;
use Rktsky\Antispam\Settings;
use Rktsky\Antispam\Store;
use Rktsky\Antispam\Token;

require __DIR__ . '/bootstrap.php';

$rktsky_test_count = 0;

/**
 * Fails immediately when an observable native contract is broken.
 *
 * @since 0.1.0
 * @param bool   $condition Expected invariant.
 * @param string $message Failure context without secrets or submitted contents.
 * @return void
 */
function rktsky_antispam_expect( bool $condition, string $message ): void {
	global $rktsky_test_count;
	++$rktsky_test_count;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Creates an aged signed token to exercise expiry without wall-clock sleeps.
 *
 * This fixture knows its local site key; real submitters do not.
 *
 * @since 0.1.0
 * @param int $issued Synthetic issue timestamp.
 * @return string Authentic token using the documented version-one framing.
 */
function rktsky_antispam_aged_token( int $issued ): string {
	$payload = '1|comments|42|' . $issued . '|' . bin2hex( random_bytes( 16 ) );
	return base64_encode( $payload ) . '.' . hash_hmac( 'sha256', get_current_blog_id() . '|token|' . $payload, wp_salt( 'auth' ) );
}

$settings = new Settings();
$store    = new Store();
$token    = new Token();
$original = get_option( Settings::OPTION );
update_option( Settings::OPTION, $settings->defaults(), false );
$store->install();
$store->clear();

try {
	$valid = $token->issue( 'comments', '42' );
	$proof = $token->verify( $valid['token'], 'comments', '42' );
	rktsky_antispam_expect( is_array( $proof ), 'A freshly issued token must authenticate.' );
	rktsky_antispam_expect( $valid !== $token->issue( 'comments', '42' ), 'Independent form instances must receive independent tokens.' );
	rktsky_antispam_expect( false === $token->verify( $valid['token'], 'comments', '43' ), 'A token must not cross a form boundary.' );
	rktsky_antispam_expect( false === $token->verify( $valid['token'], 'cf7', '42' ), 'A token must not cross a provider boundary.' );
	rktsky_antispam_expect( false === $token->verify( substr( $valid['token'], 0, -1 ) . ( str_ends_with( $valid['token'], 'a' ) ? 'b' : 'a' ), 'comments', '42' ), 'Signature tampering must fail.' );
	rktsky_antispam_expect( false === $token->verify( str_repeat( 'a', 10000 ), 'comments', '42' ), 'Oversized tokens must fail before expensive parsing.' );
	rktsky_antispam_expect( false === $token->verify( rktsky_antispam_aged_token( time() - 7201 ), 'comments', '42' ), 'Expired authentic tokens must fail.' );
	rktsky_antispam_expect( false === $token->verify( rktsky_antispam_aged_token( time() + 60 ), 'comments', '42' ), 'Future-dated authentic tokens must fail.' );
	$parts = explode( '.', $valid['token'] );
	rktsky_antispam_expect( false === $token->verify( $parts[0] . "\n." . $parts[1], 'comments', '42' ), 'Alternative Base64 framing must not bypass replay protection.' );
	rktsky_antispam_expect( ! $token->valid_intent( $token->intent( 'comments', '42' ), 'comments', '43' ), 'Rendered intents must bind to the exact form.' );
	rktsky_antispam_expect( '' === $token->intent( 'unknown', '42' ), 'Unknown providers must not receive signed intents.' );
	rktsky_antispam_expect( '' === $token->intent( 'comments', '../42' ), 'Path-like form identifiers must be rejected.' );

	$guard = new Guard( $settings, $token, $store );
	rktsky_antispam_expect( 'missing' === $guard->check( 'comments', '42', array() ), 'Missing controls must not pass.' );
	rktsky_antispam_expect( 'missing' === $guard->check( 'comments', '43', array( 'rktsky_antispam_token' => array( 'bad' ) ) ), 'Array-shaped token pollution must not pass.' );
	$filled = $token->issue( 'comments', '42' );
	rktsky_antispam_expect( 'honeypot' === $guard->check( 'comments', '42', array( 'rktsky_antispam_token' => $filled['token'], $filled['trap'] => 'synthetic-bot' ) ), 'A filled honeypot must fail.' );
	$nested = $token->issue( 'comments', '42' );
	rktsky_antispam_expect( 'honeypot' === $guard->check( 'comments', '42', array( 'rktsky_antispam_token' => $nested['token'], $nested['trap'] => array() ) ), 'Array-shaped honeypot pollution must fail.' );
	$missing_trap = $token->issue( 'comments', '42' );
	rktsky_antispam_expect( 'honeypot' === $guard->check( 'comments', '42', array( 'rktsky_antispam_token' => $missing_trap['token'] ) ), 'Removing the dynamic honeypot must fail.' );
	$controls = array( 'rktsky_antispam_token' => $valid['token'], $valid['trap'] => '', 'email' => 'synthetic-sensitive@example.invalid', 'message' => 'Synthetic sensitive content.' );
	rktsky_antispam_expect( '' === $guard->check( 'comments', '42', $controls ), 'Authentic empty-honeypot controls must pass.' );
	rktsky_antispam_expect( 'replay' === $guard->check( 'comments', '42', $controls ), 'A second independent guard invocation must not replay a consumed token.' );
	rktsky_antispam_expect( 'replay' === ( new Guard( $settings, $token, $store ) )->check( 'comments', '42', $controls ), 'A subsequent request must not replay a consumed token.' );

	$timed = $settings->defaults();
	$timed['minimum_seconds'] = 10;
	update_option( Settings::OPTION, $timed, false );
	$fresh = $token->issue( 'comments', '42' );
	rktsky_antispam_expect( 'too_fast' === ( new Guard( $settings, $token, $store ) )->check( 'comments', '42', array( 'rktsky_antispam_token' => $fresh['token'], $fresh['trap'] => '' ) ), 'Explicit minimum completion time must be enforced on the server.' );
	update_option( Settings::OPTION, $settings->defaults(), false );

	$normalized = $settings->sanitize( array( 'comments' => array( 1 ), 'cf7' => '1', 'minimum_seconds' => '999', 'retention_days' => '0', 'token_ttl' => 999999, 'injected' => true ) );
	rktsky_antispam_expect( false === $normalized['comments'] && true === $normalized['cf7'] && false === $normalized['wpforms'], 'Settings must reject malformed booleans and preserve native checkbox semantics.' );
	rktsky_antispam_expect( 10 === $normalized['minimum_seconds'] && 1 === $normalized['retention_days'] && 7200 === $normalized['token_ttl'] && ! isset( $normalized['injected'] ), 'Numeric settings must be bounded and protocol lifetime immutable.' );

	$fields  = new Fields( $settings, $token );
	$request = new WP_REST_Request( 'POST', '/rktsky-antispam/v1/challenge' );
	$request->set_param( 'provider', 'comments' );
	$request->set_param( 'form', '42' );
	$request->set_param( 'intent', 'forged' );
	rktsky_antispam_expect( is_wp_error( $fields->challenge( $request ) ), 'Forged public scope descriptors must fail.' );
	$request->set_param( 'intent', $token->intent( 'comments', '42' ) );
	$response = $fields->challenge( $request );
	rktsky_antispam_expect( $response instanceof WP_REST_Response && 200 === $response->get_status(), 'An enabled rendered form must obtain a challenge.' );
	rktsky_antispam_expect( str_contains( $response->get_headers()['Cache-Control'] ?? '', 'no-store' ), 'Challenge responses must prevent browser and intermediary caching.' );

	global $wpdb;
	$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $wpdb->prefix . 'rktsky_antispam_tokens' ), 0 );
	rktsky_antispam_expect( array( 'digest', 'expires' ) === $columns, 'Replay storage must have no visitor or content columns.' );
	$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $wpdb->prefix . 'rktsky_antispam_stats' ), 0 );
	rktsky_antispam_expect( array( 'day', 'provider', 'reason', 'total' ) === $columns, 'Statistics must contain only fixed aggregate dimensions.' );
	$summary_before = $store->summary();
	$store->record( 'malicious-provider', 'custom-content' );
	rktsky_antispam_expect( $summary_before === $store->summary(), 'Unbounded provider/reason strings must never enter statistics.' );
	$store->clear();
	rktsky_antispam_expect( array( 'accepted' => 0, 'blocked' => 0, 'reasons' => array(), 'daily' => array() ) === $store->summary(), 'Clearing statistics must produce a real empty state.' );
	rktsky_antispam_expect( ! $store->claim( $proof['digest'], $proof['expires'] ), 'Clearing statistics must preserve replay claims.' );

	$digest    = hash( 'sha256', random_bytes( 32 ) );
	$expiry    = time() + 600;
	$workers   = array();
	$resources = array();
	for ( $i = 0; $i < 4; ++$i ) {
		$pipes = array();
		$workers[] = proc_open( array( PHP_BINARY, __DIR__ . '/claim-worker.php', $digest, (string) $expiry ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		$resources[] = $pipes;
	}
	$claimed = 0;
	foreach ( $workers as $index => $worker ) {
		foreach ( $resources[ $index ] as $pipe ) {
			fclose( $pipe );
		}
		$status = proc_close( $worker );
		rktsky_antispam_expect( in_array( $status, array( 0, 3 ), true ), 'Concurrent claim workers must finish without runtime errors.' );
		$claimed += 0 === $status ? 1 : 0;
	}
	rktsky_antispam_expect( 1 === $claimed, 'Exactly one independent database connection may claim the same token.' );

	$old_digest = hash( 'sha256', random_bytes( 32 ) );
	$wpdb->insert( $wpdb->prefix . 'rktsky_antispam_tokens', array( 'digest' => $old_digest, 'expires' => time() - 60 ), array( '%s', '%d' ) );
	$wpdb->insert( $wpdb->prefix . 'rktsky_antispam_stats', array( 'day' => gmdate( 'Y-m-d', time() - 40 * DAY_IN_SECONDS ), 'provider' => 'comments', 'reason' => 'accepted', 'total' => 1 ), array( '%s', '%s', '%s', '%d' ) );
	$store->cleanup();
	rktsky_antispam_expect( null === $wpdb->get_var( $wpdb->prepare( 'SELECT digest FROM %i WHERE digest = %s', $wpdb->prefix . 'rktsky_antispam_tokens', $old_digest ) ), 'Scheduled cleanup must remove expired security state.' );
	rktsky_antispam_expect( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE day < %s', $wpdb->prefix . 'rktsky_antispam_stats', gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ) ) ), 'Scheduled cleanup must enforce the configured retention window.' );

	$database_engine = defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ? 'SQLite integration (MySQL compatibility ' . $wpdb->db_version() . ')' : 'MySQL/MariaDB ' . $wpdb->db_version();
	echo 'PASS: ' . $rktsky_test_count . ' native assertions on WordPress ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . ', ' . $database_engine . ".\n";
} finally {
	if ( false === $original ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original, false );
	}
}
