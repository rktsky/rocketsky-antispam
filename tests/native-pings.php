<?php
/**
 * Exercises original WordPress pingback/trackback flows in disposable requests.
 *
 * Only the exact synthetic pingback source gets a mocked HTTP response. The
 * fixture's existing blanket HTTP/mail blocks remain installed throughout.
 *
 * @package RktskyAntispamTests
 * @since 0.3.0
 */

$case = $argv[1] ?? '';
if ( str_starts_with( $case, 'pingback_' ) ) {
	define( 'XMLRPC_REQUEST', true );
}
require __DIR__ . '/bootstrap.php';
ini_set( 'display_errors', 'stderr' );

/**
 * Stops a disposable regression on a concrete failed protocol invariant.
 *
 * @since 0.3.0
 * @param bool   $condition Required condition.
 * @param string $message Failure context.
 * @return void
 */
function rktsky_ping_expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

if ( '' === $case ) {
	$total = 0;
	foreach ( array( 'settings', 'pingback_off', 'pingback_privacy', 'pingback_on', 'pingback_query', 'pingback_spam', 'pingback_closed', 'pingback_reuse', 'pingback_reset', 'trackback_off', 'trackback_privacy', 'trackback_on', 'trackback_query', 'trackback_source', 'trackback_target', 'trackback_reuse', 'trackback_environment', 'frontend_pingback', 'frontend_trackback', 'rest_pingback', 'rest_trackback', 'source_without_native' ) as $test ) {
		$command = array( PHP_BINARY );
		if ( ! extension_loaded( 'mbstring' ) && is_file( '/tmp/rktsky-antispam-runtime/iconv.so' ) ) {
			$command = array_merge( $command, array( '-d', 'extension=/tmp/rktsky-antispam-runtime/iconv.so' ) );
		}
		$command = array_merge( $command, array( __FILE__, $test ) );
		$pipes   = array();
		$process = proc_open(
			$command,
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes
		);
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error  = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$status = proc_close( $process );
		rktsky_ping_expect( 0 === $status && str_contains( $output, 'PASS: ' . $test ), $test . " failed\n" . $output . "\n" . $error );
		echo 'PASS: ' . $test . "\n";
		++$total;
	}
	echo 'PASS: ' . $total . " original WordPress protocol cases.\n";
	return;
}

$settings                      = new \Rktsky\Antispam\Settings();
$original                      = get_option( \Rktsky\Antispam\Settings::OPTION, array() );
$options                       = $settings->all();
$options['comments']           = true;
$options['allow_pingbacks']    = ! str_ends_with( $case, '_off' );
$options['privacy_mode']       = str_ends_with( $case, '_privacy' );
$options['quarantine_enabled'] = true;
update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
register_shutdown_function(
	static function () use ( $original ): void {
		update_option( \Rktsky\Antispam\Settings::OPTION, $original, false );
	}
);

if ( 'settings' === $case ) {
	rktsky_ping_expect( false === $settings->defaults()['allow_pingbacks'], 'Protocols must remain disabled by default.' );
	rktsky_ping_expect( true === $settings->all()['allow_pingbacks'], 'Explicit enablement outside privacy mode must normalize as boolean.' );
	$options['privacy_mode'] = true;
	update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
	rktsky_ping_expect( false === $settings->all()['allow_pingbacks'], 'Privacy mode must override a stored true protocol option.' );
	$options['privacy_mode']    = false;
	$options['allow_pingbacks'] = array( 1 );
	update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
	rktsky_ping_expect( false === $settings->all()['allow_pingbacks'], 'Array-shaped options must not enable protocol traffic.' );
	echo 'PASS: settings' . "\n";
	return;
}

rktsky_ping_expect( false !== has_filter( 'pre_wp_mail', '__return_false' ) && false !== has_filter( 'pre_http_request' ), 'Fixture mail and HTTP blocks are required.' );
wp_set_current_user( 0 );
$_SERVER['REMOTE_ADDR']    = '192.0.2.90';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST                     = array();
$post_id                   = wp_insert_post(
	array(
		'post_title'     => 'Synthetic protocol ' . wp_generate_uuid4(),
		'post_status'    => 'publish',
		'post_type'      => 'post',
		'ping_status'    => 'pingback_closed' === $case ? 'closed' : 'open',
		'comment_status' => 'open',
	)
);
rktsky_ping_expect( is_int( $post_id ) && $post_id > 0, 'A synthetic published target is required.' );
$target = get_permalink( $post_id );
// A literal public address avoids DNS in Core's unchanged source-URL validator.
// No connection reaches it: only this exact URL receives the synthetic response.
$source = 'https://93.184.215.14/rktsky-ping/' . wp_generate_uuid4();
if ( str_ends_with( $case, '_query' ) ) {
	$source .= '?a=1&b=2'; }
$http_calls     = 0;
$reused_blocked = false;
$blocked        = false;
$adapter        = null;
global $wp_filter, $wpdb;
foreach ( $wp_filter['preprocess_comment']->callbacks as $callbacks ) {
	foreach ( $callbacks as $callback ) {
		if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof \Rktsky\Antispam\Integrations\Comments ) {
			$adapter = $callback['function'][0];
		}
	}
}
rktsky_ping_expect( $adapter instanceof \Rktsky\Antispam\Integrations\Comments, 'The actual active Comments adapter must own the native hooks.' );
$stats_table       = $wpdb->prefix . 'rktsky_antispam_stats';
$total_table       = $wpdb->prefix . 'rktsky_antispam_totals';
$stats_before      = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE provider = %s', $stats_table, 'comments' ), ARRAY_A );
$totals_before     = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE provider = %s', $total_table, 'comments' ), ARRAY_A );
$quarantine_before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'rktsky_antispam_quarantine' ) );
$feedback_before   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'rktsky_antispam_feedback' ) );
$ping_before       = array_sum( array_column( array_filter( $stats_before, static fn( array $row ): bool => 'pingback' === $row['reason'] ), 'total' ) );
$accepted_before   = array_sum( array_column( array_filter( $stats_before, static fn( array $row ): bool => 'accepted' === $row['reason'] ), 'total' ) );
register_shutdown_function(
	static function () use ( $post_id, $stats_table, $total_table, $stats_before, $totals_before ): void {
		global $wpdb;
		wp_delete_post( $post_id, true );
		$wpdb->delete( $stats_table, array( 'provider' => 'comments' ) );
		$wpdb->delete( $total_table, array( 'provider' => 'comments' ) );
		foreach ( $stats_before as $row ) {
			$wpdb->insert( $stats_table, $row ); }
		foreach ( $totals_before as $row ) {
			$wpdb->insert( $total_table, $row ); }
	}
);
$die = static function (): void {
	throw new RuntimeException( 'Synthetic protocol rejection', 403 );
};
foreach ( array( 'wp_die_handler', 'wp_die_xmlrpc_handler', 'wp_die_xml_handler' ) as $hook ) {
	add_filter( $hook, static fn() => $die, PHP_INT_MAX );
}
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( $source, $target, &$http_calls ) {
		if ( $source !== $url ) {
			return $pre; }
		++$http_calls;
		rktsky_ping_expect( '192.0.2.90' === ( $args['headers']['X-Pingback-Forwarded-For'] ?? '' ), 'The enabled native path must expose the documented Core forwarding behavior in the synthetic request.' );
		return array(
			'headers'  => array( 'content-type' => 'text/html' ),
			'body'     => '<html><head><title>Synthetic source</title></head><body><p>Context <a href="' . esc_url( $target ) . '">Target</a> context.</p></body></html>',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	PHP_INT_MAX,
	3
);
if ( str_ends_with( $case, '_reuse' ) ) {
	add_filter(
		'preprocess_comment',
		static function ( array $comment ) use ( $adapter, &$reused_blocked ): array {
			try {
				$adapter->check_comment( $comment );
			} catch ( RuntimeException $error ) {
				$reused_blocked = 403 === $error->getCode(); }
			return $comment;
		},
		PHP_INT_MAX
	);
}
if ( 'pingback_spam' === $case ) {
	add_filter( 'pre_comment_approved', static fn() => 'spam', PHP_INT_MAX );
}

$protocol_output = '';
ob_start(
	static function ( string $chunk ) use ( &$protocol_output ): string {
		// WordPress flushes buffers from its own earlier shutdown callback.
		$protocol_output .= $chunk;
		return $chunk;
	}
);
try {
	if ( str_starts_with( $case, 'pingback_' ) ) {
		require_once ABSPATH . WPINC . '/class-IXR.php';
		require_once ABSPATH . WPINC . '/class-wp-xmlrpc-server.php';
		$server = new wp_xmlrpc_server();
		if ( 'pingback_reset' === $case ) {
			// The original methods publish their own reset boundaries; no fake hook arms the permit.
			$server->pingback_ping( array( $source, 'https://example.invalid/no-local-target' ) );
			$server->mt_supportedMethods();
			$adapter->check_comment(
				array(
					'comment_type'       => 'pingback',
					'comment_post_ID'    => $post_id,
					'comment_author_url' => $source,
				)
			);
		} else {
			$result = $server->pingback_ping( array( $source, $target ) );
		}
	} elseif ( str_starts_with( $case, 'trackback_' ) ) {
		// Core 7.1 disables trackbacks on local sites. Its documented opt-in
		// filter models production while preserving the fixture's local marker.
		if ( 'trackback_environment' !== $case ) {
			add_filter( 'wp_should_disable_pings_for_environment', '__return_false' );
		} elseif ( function_exists( 'wp_should_disable_pings_for_environment' ) ) {
			$preprocess_before = did_filter( 'preprocess_comment' );
			register_shutdown_function(
				static function () use ( &$http_calls, $preprocess_before, &$protocol_output ): void {
					rktsky_ping_expect( 0 === $http_calls && did_filter( 'preprocess_comment' ) === $preprocess_before && str_contains( $protocol_output, 'Trackbacks are disabled in non-production environments.' ), 'Native local-environment rejection must remain effective before comment processing.' );
					echo 'PASS: trackback_environment' . "\n";
				}
			);
		} else {
			// Older supported Core versions have no separate environment gate.
			echo 'PASS: trackback_environment (Core has no environment gate)' . "\n";
			return;
		}
		if ( in_array( $case, array( 'trackback_source', 'trackback_target' ), true ) ) {
			add_filter(
				'preprocess_comment',
				static function ( array $comment ) use ( $case ): array {
					$comment[ 'trackback_source' === $case ? 'comment_author_url' : 'comment_post_ID' ] = 'trackback_source' === $case ? 'https://example.invalid/wrong-source' : 987654321;
					return $comment;
				},
				1
			);
		}
		$_GET['tb_id'] = $post_id;
		$_POST         = wp_slash(
			array(
				'url'       => $source,
				'title'     => 'Synthetic title',
				'excerpt'   => 'Synthetic excerpt',
				'blog_name' => 'Synthetic source',
			)
		);
		// This original endpoint declares trackback_response and emits the native boundary itself.
		require ABSPATH . 'wp-trackback.php';
	} elseif ( str_starts_with( $case, 'frontend_' ) ) {
		$type = substr( $case, strlen( 'frontend_' ) );
		// Ordinary HTTP cannot define Core's trackback function or publish its server-side action.
		$adapter->check_comment(
			array(
				'comment_type'       => $type,
				'comment_post_ID'    => $post_id,
				'comment_author_url' => $source,
				'comment_content'    => 'Synthetic content',
			)
		);
	} elseif ( 'source_without_native' === $case ) {
		rktsky_ping_expect( '' === apply_filters( 'pingback_ping_source_uri', $source, $target ), 'A source filter outside a native XML-RPC intent must fail closed.' );
	} else {
		$type    = substr( $case, strlen( 'rest_' ) );
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$result  = $adapter->check_rest_comment(
			array(
				'comment_type'       => $type,
				'comment_post_ID'    => $post_id,
				'comment_author_url' => $source,
			),
			$request
		);
		rktsky_ping_expect( is_wp_error( $result ) && 403 === $result->get_error_data()['status'], 'REST protocol-type spoofing must stay rejected.' );
	}
} catch ( RuntimeException $error ) {
	if ( 403 !== $error->getCode() ) {
		throw $error; }
	$blocked = true;
}
$output   = ob_get_clean();
$comments = get_comments(
	array(
		'post_id' => $post_id,
		'status'  => array( 'all', 'spam', 'trash', 'post-trashed' ),
		'type'    => 'all',
	)
);
$accepted = in_array( $case, array( 'pingback_on', 'pingback_query', 'pingback_spam', 'pingback_reuse', 'trackback_on', 'trackback_query', 'trackback_reuse' ), true );
rktsky_ping_expect( ( $accepted ? 1 : 0 ) === count( $comments ), 'Only the permitted native protocol should create one Core-owned comment: ' . $case . ' ' . print_r( $result ?? $output, true ) );
if ( $accepted ) {
	rktsky_ping_expect( ! $blocked, 'The first permitted native attempt must pass unchanged.' );
	rktsky_ping_expect( str_starts_with( $case, 'pingback_' ) ? 1 === $http_calls : 0 === $http_calls, 'Only enabled pingbacks may use the exact mocked source request.' );
	if ( 'pingback_spam' === $case ) {
		rktsky_ping_expect( 'spam' === $comments[0]->comment_approved, 'Native moderation must remain authoritative.' ); }
} else {
	rktsky_ping_expect( 0 === $http_calls, 'Disabled, closed, reset and spoofed flows must not fetch a source.' );
}
if ( str_ends_with( $case, '_reuse' ) ) {
	rktsky_ping_expect( $reused_blocked, 'A consumed protocol permit must not authorize another submission.' ); }
rktsky_ping_expect( $quarantine_before === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'rktsky_antispam_quarantine' ) ), 'Protocol content must never be copied to optional quarantine.' );
rktsky_ping_expect( $feedback_before === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'rktsky_antispam_feedback' ) ), 'Protocol entries must not enter the quality ledger.' );
$stats_after    = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE provider = %s', $stats_table, 'comments' ), ARRAY_A );
$ping_after     = array_sum( array_column( array_filter( $stats_after, static fn( array $row ): bool => 'pingback' === $row['reason'] ), 'total' ) );
$accepted_after = array_sum( array_column( array_filter( $stats_after, static fn( array $row ): bool => 'accepted' === $row['reason'] ), 'total' ) );
$rejection      = ! $accepted && ! in_array( $case, array( 'pingback_closed', 'source_without_native' ), true ) || str_ends_with( $case, '_reuse' );
rktsky_ping_expect( ( $rejection ? 1 : 0 ) === $ping_after - $ping_before, 'Only local protocol rejections should increment the fixed policy counter once.' );
rktsky_ping_expect( $accepted_after === $accepted_before, 'Permitted protocols must not invent successful token checks.' );
echo 'PASS: ' . $case . "\n";
