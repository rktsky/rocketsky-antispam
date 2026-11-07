<?php
/**
 * Exercises unchanged Divi 4 theme/builder PHP through genuine loopback HTTP.
 *
 * Requires the native fixture MU mail observer and side-effect block. All data
 * is synthetic; Core/provider records use WordPress APIs exclusively.
 *
 * @package RktskyAntispamTests
 * @since 0.3.1
 */

require __DIR__ . '/bootstrap.php';
if ( ! defined( 'RKTSKY_DIVI4_FIXTURE_LOG' ) || ! defined( 'ET_BUILDER_VERSION' ) || '4.27.9' !== ET_BUILDER_VERSION ) {
	throw new RuntimeException( 'The marked Divi 4.27.9 fixture and mail observer are required.' );
}
$rktsky_divi4_origin = untrailingslashit( home_url() );
$origin              = wp_parse_url( $rktsky_divi4_origin );
if ( 'http' !== $origin['scheme'] || '127.0.0.1' !== $origin['host'] || ! in_array( $origin['port'] ?? 0, array( 8884, 8886 ), true ) ) {
	throw new RuntimeException( 'Only the assigned disposable Divi4 loopback origins are allowed.' );
}
$rktsky_divi4_assertions = 0;
$rktsky_divi4_run        = 'native-' . bin2hex( random_bytes( 5 ) );
$forms                   = get_option( 'rktsky_antispam_divi4_forms', array() );
if ( empty( $forms['plain'] ) || empty( $forms['multiple'] ) ) {
	throw new RuntimeException( 'Run prepare-divi4.php on the disposable fixture first.' );
}

/**
 * Allows only this runner's exact HTTP origin through the fixture's HTTP block.
 *
 * @since 0.3.1
 * @param mixed  $previous Existing HTTP interception verdict.
 * @param array  $arguments Native request options, left untouched.
 * @param string $url Requested native URL.
 * @return mixed False for this loopback origin, otherwise the original block.
 */
function rktsky_antispam_divi4_loopback( $previous, array $arguments, string $url ) {
	global $rktsky_divi4_origin;
	$expected = wp_parse_url( $rktsky_divi4_origin );
	$actual   = wp_parse_url( $url );
	return is_array( $actual ) && ( $actual['scheme'] ?? '' ) === $expected['scheme'] && ( $actual['host'] ?? '' ) === $expected['host'] && ( $actual['port'] ?? 80 ) === $expected['port'] ? false : $previous;
}
add_filter( 'pre_http_request', 'rktsky_antispam_divi4_loopback', PHP_INT_MAX, 3 );

/**
 * Counts a native invariant without printing contact contents or signed proof.
 *
 * @since 0.3.1
 * @param bool   $condition Expected original-engine behavior.
 * @param string $message Failure label.
 * @return void
 */
function rktsky_antispam_divi4_expect( bool $condition, string $message ): void {
	global $rktsky_divi4_assertions;
	++$rktsky_divi4_assertions;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Reads an original rendered form and prepares its native declared field list.
 *
 * @since 0.3.1
 * @param int $page Published synthetic WordPress page ID.
 * @param int $number Native form index to select on a multiple-form page.
 * @return array<string,mixed> Native controls and safe synthetic contact values.
 */
function rktsky_antispam_divi4_controls( int $page, int $number = 0 ): array {
	global $rktsky_divi4_origin;
	$response = wp_remote_get(
		$rktsky_divi4_origin . '/?page_id=' . $page,
		array(
			'timeout'     => 15,
			'redirection' => 0,
		)
	);
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		throw new RuntimeException( 'Native fixture page could not be read.' );
	}
	$document = new DOMDocument();
	libxml_use_internal_errors( true );
	$document->loadHTML( wp_remote_retrieve_body( $response ) );
	libxml_clear_errors();
	$xpath = new DOMXPath( $document );
	$form  = $xpath->query( '//form[contains(concat(" ",normalize-space(@class)," ")," et_pb_contact_form ")]' )->item( $number );
	if ( ! $form instanceof DOMElement || 'divi' !== $form->getAttribute( 'data-rk-antispam-provider' ) ) {
		throw new RuntimeException( 'Original native form or production adapter descriptor is missing.' );
	}
	$posted = array();
	$fields = array();
	foreach ( $xpath->query( './/input | .//textarea | .//select', $form ) as $node ) {
		$name = $node->getAttribute( 'name' );
		if ( '' === $name ) {
			continue;
		}
		$posted[ $name ] = $node->getAttribute( 'value' );
		$key             = $node->getAttribute( 'data-original_id' );
		if ( ! in_array( $key, array( 'name', 'email', 'message' ), true ) ) {
			continue;
		}
		$posted[ $name ] = array(
			'name'    => 'Synthetic native visitor',
			'email'   => 'fixture@example.invalid',
			'message' => 'Synthetic native local message.',
		)[ $key ];
		$fields[]        = array(
			'field_id'      => $node->getAttribute( 'id' ),
			'original_id'   => $key,
			'required_mark' => $node->getAttribute( 'data-required_mark' ),
			'field_type'    => $node->getAttribute( 'data-field_type' ),
			'field_label'   => $key,
		);
	}
	$posted[ 'et_pb_contact_email_fields_' . $number ] = wp_json_encode( $fields );
	foreach ( array( 'target', 'page' ) as $technical ) {
		$value = $form->getAttribute( 'data-rk-antispam-' . $technical );
		if ( '' !== $value ) {
			$posted[ 'rktsky_antispam_' . $technical ] = $value;
		}
	}
	return $posted;
}

/**
 * Adds a genuine unused production challenge for the fixed Divi contact scope.
 *
 * @since 0.3.1
 * @param array  $posted Native controls without proof.
 * @param string $scope Challenge scope for positive or wrong-scope cases.
 * @param bool   $filled Whether the trap is deliberately filled.
 * @return array Native controls plus own signed proof and its exact trap.
 */
function rktsky_antispam_divi4_proof( array $posted, string $scope = 'contact', bool $filled = false ): array {
	$proof                           = ( new \Rktsky\Antispam\Token() )->issue( 'divi', $scope );
	$posted['rktsky_antispam_token'] = $proof['token'];
	$posted[ $proof['trap'] ]        = $filled ? 'synthetic-bot' : '';
	return $posted;
}

/**
 * Submits to the real provider engine and reads the isolated mail observer.
 *
 * @since 0.3.1
 * @param string $case Stable non-personal test-case label.
 * @param int    $page Synthetic page ID.
 * @param array  $posted Native URL-encoded form controls.
 * @param bool   $ajax Whether to use the original same-page AJAX presentation.
 * @param string $route Optional native REST route to exercise alternate dispatch.
 * @return array{code:int,body:string,mail:int,native:array} Native observed outcome.
 */
function rktsky_antispam_divi4_submit( string $case, int $page, array $posted, bool $ajax = true, string $route = '' ): array {
	global $rktsky_divi4_origin, $rktsky_divi4_run;
	$tag     = $rktsky_divi4_run . '-' . $case;
	$headers = array( 'X-Rk-Fixture-Case' => $tag );
	if ( $ajax ) {
		$headers['X-Requested-With'] = 'XMLHttpRequest';
	}
	$url      = $rktsky_divi4_origin . ( '' !== $route ? $route : '/?page_id=' . $page );
	$response = wp_remote_post(
		$url,
		array(
			'headers'     => $headers,
			'body'        => $posted,
			'timeout'     => 15,
			'redirection' => 0,
		)
	);
	if ( is_wp_error( $response ) ) {
		throw new RuntimeException( 'Native submission HTTP transport failed: ' . $case );
	}
	$state    = wp_remote_get(
		$rktsky_divi4_origin . '/index.php?rest_route=/rktsky-fixture/v1/state&case=' . rawurlencode( $tag ),
		array(
			'timeout'     => 15,
			'redirection' => 0,
		)
	);
	$observed = is_wp_error( $state ) ? null : json_decode( wp_remote_retrieve_body( $state ), true );
	if ( ! is_array( $observed ) || ! isset( $observed['mail'], $observed['native'] ) ) {
		throw new RuntimeException( 'Native mail observer is unavailable.' );
	}
	return array(
		'code'   => wp_remote_retrieve_response_code( $response ),
		'body'   => wp_remote_retrieve_body( $response ),
		'mail'   => (int) $observed['mail'],
		'native' => $observed['native'],
	);
}

$settings                   = new \Rktsky\Antispam\Settings();
$original                   = get_option( \Rktsky\Antispam\Settings::OPTION );
$options                    = $settings->all();
$options['divi']            = true;
$options['minimum_seconds'] = 0;
update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
$page = (int) $forms['plain'];
try {
	$base   = rktsky_antispam_divi4_controls( $page );
	$used   = rktsky_antispam_divi4_proof( $base );
	$result = rktsky_antispam_divi4_submit( 'valid', $page, $used );
	rktsky_antispam_divi4_expect( 200 === $result['code'] && 1 === $result['mail'] && str_contains( $result['body'], 'Synthetic Divi accepted.' ), 'Genuine proof must reach exactly one original mail call.' );
	rktsky_antispam_divi4_expect( 1 === count( $result['native'] ) && false === $result['native'][0]['error'], 'The original post-mail observer must confirm a legitimate submission.' );
	foreach ( array(
		'no_proof'    => $base,
		'trap'        => rktsky_antispam_divi4_proof( $base, 'contact', true ),
		'replay'      => $used,
		'wrong_scope' => rktsky_antispam_divi4_proof( $base, 'other-form' ),
		'array_proof' => array_merge( $base, array( 'rktsky_antispam_token' => array( 'malformed' ) ) ),
	) as $case => $posted ) {
		$result = rktsky_antispam_divi4_submit( $case, $page, $posted );
		rktsky_antispam_divi4_expect( 0 === $result['mail'] && array() === $result['native'], 'Rejected proof must stop before the original mail and submit observer: ' . $case );
		rktsky_antispam_divi4_expect( str_contains( $result['body'], 'et_pb_contact_error_text' ) && str_contains( $result['body'], 'spam protection could not verify' ), 'Rejected proof must carry the native visible error marker: ' . $case );
	}
	$result = rktsky_antispam_divi4_submit( 'fresh_retry', $page, rktsky_antispam_divi4_proof( $base ) );
	rktsky_antispam_divi4_expect( 1 === $result['mail'], 'A fresh retry must reach the unchanged mail path.' );
	foreach ( array(
		'required' => array( 'et_pb_contact_name_0' => '' ),
		'email'    => array( 'et_pb_contact_email_0' => 'invalid-email' ),
		'nonce'    => array( '_wpnonce-et-pb-contact-form-submitted-0' => 'invalid-native-nonce' ),
	) as $case => $change ) {
		$result = rktsky_antispam_divi4_submit( 'native_' . $case, $page, rktsky_antispam_divi4_proof( array_merge( $base, $change ) ) );
		rktsky_antispam_divi4_expect( 0 === $result['mail'] && str_contains( $result['body'], 'et_pb_contact_error_text' ) && ! str_contains( $result['body'], 'spam protection could not verify' ), 'Valid local proof must preserve native validation failure: ' . $case );
	}
	$many                               = rktsky_antispam_divi4_proof( $base );
	$many['et_pb_contactform_submit_1'] = 'et_contact_proccess';
	$result                             = rktsky_antispam_divi4_submit( 'multiple_targets', $page, $many );
	rktsky_antispam_divi4_expect( 0 === $result['mail'] && array() === $result['native'] && str_contains( $result['body'], 'spam protection could not verify' ), 'One proof must not fan out across multiple native targets.' );
	$result = rktsky_antispam_divi4_submit( 'plain_missing', $page, $base, false );
	rktsky_antispam_divi4_expect( 403 === $result['code'] && 0 === $result['mail'], 'No-JavaScript/plain POST without proof must fail before native processing.' );
	$result = rktsky_antispam_divi4_submit( 'plain_valid', $page, rktsky_antispam_divi4_proof( $base ), false );
	rktsky_antispam_divi4_expect( 200 === $result['code'] && 1 === $result['mail'], 'A genuine plain POST proof must preserve native delivery.' );
	$result = rktsky_antispam_divi4_submit( 'unknown', $page, array( 'unrelated_form_control' => 'synthetic' ) );
	rktsky_antispam_divi4_expect( 200 === $result['code'] && 0 === $result['mail'] && ! str_contains( $result['body'], 'spam protection could not verify' ), 'An unrelated POST must not be reclassified as a Divi submission.' );
	$second = rktsky_antispam_divi4_controls( (int) $forms['multiple'], 1 );
	$result = rktsky_antispam_divi4_submit( 'second_form', (int) $forms['multiple'], rktsky_antispam_divi4_proof( $second ) );
	rktsky_antispam_divi4_expect( 1 === $result['mail'] && 1 === ( $result['native'][0]['form'] ?? -1 ), 'The second genuine native form must be protected and delivered independently.' );
	$result = rktsky_antispam_divi4_submit( 'rest_override', $page, $base, false, '/index.php?rest_route=/wp/v2/pages/' . $page . '&_method=GET' );
	rktsky_antispam_divi4_expect( 0 === $result['mail'] && array() === $result['native'] && str_contains( $result['body'], 'spam protection could not verify' ), 'The public REST method-override render path must not bypass protection.' );
	$custom = rktsky_antispam_divi4_controls( (int) $forms['custom'] );
	$result = rktsky_antispam_divi4_submit( 'custom_missing', (int) $forms['custom'], $custom );
	rktsky_antispam_divi4_expect( 0 === $result['mail'] && str_contains( $result['body'], 'id="rk-fixture-sales"' ) && str_contains( $result['body'], 'page_id=' . (int) $forms['custom'] ), 'Native custom CSS ID and public reload destination must survive a proof failure.' );
	$long   = array( 'et_pb_contactform_submit_' . str_repeat( 'x', 65 ) => 'synthetic' );
	$result = rktsky_antispam_divi4_submit( 'unsupported_suffix', $page, $long );
	rktsky_antispam_divi4_expect( 0 === $result['mail'] && str_contains( $result['body'], 'spam protection could not verify' ), 'Reserved native envelopes with unsupported suffixes must fail closed.' );
	$options['privacy_mode']       = false;
	$options['quarantine_enabled'] = true;
	update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
	$quarantine = new \Rktsky\Antispam\Quarantine( $settings );
	rktsky_antispam_divi4_expect( $quarantine->enabled(), 'Encrypted quarantine must be available before testing its deliberate Divi exclusion.' );
	$before_quarantine = $quarantine->count();
	$result            = rktsky_antispam_divi4_submit( 'no_content_capture', $page, rktsky_antispam_divi4_proof( $base, 'contact', true ) );
	rktsky_antispam_divi4_expect( 0 === $result['mail'] && $before_quarantine === $quarantine->count(), 'Technical-only Divi rejection must not retain contact contents even with quarantine opted in.' );
	$options['privacy_mode']       = true;
	$options['quarantine_enabled'] = false;
	$options['minimum_seconds']    = 2;
	update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
	$timed  = rktsky_antispam_divi4_proof( $base );
	$result = rktsky_antispam_divi4_submit( 'too_fast', $page, $timed );
	rktsky_antispam_divi4_expect( 0 === $result['mail'], 'Optional timing must reject an immediate native submission.' );
	sleep( 3 );
	$result = rktsky_antispam_divi4_submit( 'timed_retry', $page, $timed );
	rktsky_antispam_divi4_expect( 1 === $result['mail'], 'A proof rejected only for timing must become usable after the bound.' );
} finally {
	update_option( \Rktsky\Antispam\Settings::OPTION, $original, false );
	remove_filter( 'pre_http_request', 'rktsky_antispam_divi4_loopback', PHP_INT_MAX );
}
echo 'PASS: ' . $rktsky_divi4_assertions . ' native Divi4 assertions at ' . esc_url_raw( $rktsky_divi4_origin ) . "; no real mail sent.\n";
