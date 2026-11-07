<?php
/**
 * Exercises original Divi 5 and its legacy/conversion PHP through real local HTTP.
 *
 * No provider hooks are mocked. A private MU observer records original actions and
 * wp_mail attempts, then short-circuits all actual delivery and WordPress HTTP.
 *
 * @package RktskyAntispamTests
 * @since 0.3.1
 */
require __DIR__ . '/bootstrap.php';
if ( ! class_exists( '\ET\Builder\Packages\ModuleLibrary\ContactForm\ContactFormModule' ) || ! et_builder_d5_enabled() ) {
	throw new RuntimeException( 'The original Divi 5 theme must be active.' );
}
if ( ! defined( 'RKTSKY_DIVI5_FIXTURE_LOG' ) || ! function_exists( 'rktsky_divi5_event' ) || false === has_filter( 'pre_http_request', 'rktsky_divi5_fixture_http' ) || false === has_filter( 'pre_wp_mail', 'rktsky_divi5_fixture_mail' ) || false === has_filter( 'pre_wp_mail', '__return_true' ) ) {
	throw new RuntimeException( 'Local test delivery and HTTP blocks are required.' );
}
$fixture = get_option( 'rktsky_antispam_test_divi5', array() );
$base    = get_option( 'home' );
$parsed  = wp_parse_url( $base );
if ( '127.0.0.1' !== ( $parsed['host'] ?? '' ) || 8885 !== ( $parsed['port'] ?? 0 ) ) {
	throw new RuntimeException( 'This native test is restricted to its explicit loopback fixture.' );
}
$GLOBALS['rktsky_divi5_results'] = array();

/**
 * Stops a native assertion without disclosing any actual submission fields.
 *
 * @since 0.3.1
 * @param bool   $condition Required observable behaviour.
 * @param string $message Failure context.
 * @return void
 */
function rktsky_divi5_expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
}

/**
 * Calls only the real local WordPress frontend/REST transport under test.
 *
 * @since 0.3.1
 * @param string     $url Explicit same-site fixture URL.
 * @param array|null $data Native form body; null fetches the original form.
 * @param string     $case Event correlation tag for synthetic test observations.
 * @param bool       $ajax Whether to use the original native XHR header.
 * @return array{status:int,body:string} Original HTTP response.
 */
function rktsky_divi5_http( string $url, ?array $data = null, string $case = 'native_fetch', bool $ajax = false ): array {
	if ( ! str_starts_with( $url, 'http://127.0.0.1:8885/' ) ) {
		throw new RuntimeException( 'Refusing a non-fixture HTTP destination.' ); }
	$curl    = curl_init( $url );
	$headers = array( 'X-Rk-Case: ' . $case );
	if ( $ajax ) {
		$headers[] = 'X-Requested-With: XMLHttpRequest'; }
	curl_setopt_array(
		$curl,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HTTPHEADER     => $headers,
		)
	);
	if ( null !== $data ) {
		curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $data ) ); }
	$body   = curl_exec( $curl );
	$status = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
	$error  = curl_error( $curl );
	curl_close( $curl );
	if ( false === $body ) {
		throw new RuntimeException( 'Native fixture HTTP failed: ' . $error ); }
	return array(
		'status' => $status,
		'body'   => $body,
	);
}

/**
 * Extracts actual original controls, including provider-specific nonce/field names.
 *
 * @since 0.3.1
 * @param string $html Original HTML returned by WordPress and the unmodified theme.
 * @param int    $index Native form index on the page.
 * @param bool   $captcha Whether to fill the genuine local arithmetic captcha correctly.
 * @return array<string,string> Synthetic values under original rendered field names.
 */
function rktsky_divi5_posted( string $html, int $index = 0, bool $captcha = true ): array {
	$document = new DOMDocument();
	@$document->loadHTML( $html );
	$forms = array();
	foreach ( $document->getElementsByTagName( 'form' ) as $form ) {
		if ( str_contains( $form->getAttribute( 'class' ), 'et_pb_contact_form' ) ) {
			$forms[] = $form; }
	}
	rktsky_divi5_expect( isset( $forms[ $index ] ), 'The original Divi form was not rendered.' );
	$form   = $forms[ $index ];
	$posted = array();
	$fields = array();
	$suffix = '';
	foreach ( array( 'target', 'page' ) as $meta ) {
		if ( $form->hasAttribute( 'data-rk-antispam-' . $meta ) ) {
			$posted[ 'rktsky_antispam_' . $meta ] = $form->getAttribute( 'data-rk-antispam-' . $meta ); }
	}
	foreach ( array( 'input', 'textarea' ) as $tag ) {
		foreach ( $form->getElementsByTagName( $tag ) as $input ) {
			$name = $input->getAttribute( 'name' );
			if ( '' === $name ) {
				continue; }
			$value    = $input->getAttribute( 'value' );
			$original = $input->getAttribute( 'data-original_id' );
			if ( '' !== $original ) {
				$value    = array(
					'name'    => 'Synthetic Divi native test',
					'email'   => 'fixture@example.invalid',
					'message' => 'Synthetic native message.',
				)[ strtolower( $original ) ] ?? '';
				$fields[] = array(
					'field_id'      => $name,
					'original_id'   => $original,
					'required_mark' => $input->getAttribute( 'data-required_mark' ),
					'field_type'    => $input->getAttribute( 'data-field_type' ),
					'field_label'   => $input->getAttribute( 'placeholder' ),
				);
			}
			$posted[ $name ] = $value;
			if ( str_starts_with( $name, 'et_pb_contactform_submit_' ) ) {
				$suffix = substr( $name, strlen( 'et_pb_contactform_submit_' ) ); }
			if ( str_starts_with( $name, 'et_pb_contact_captcha_' ) && $input->hasAttribute( 'data-first_digit' ) ) {
				$first           = (int) $input->getAttribute( 'data-first_digit' );
				$second          = (int) $input->getAttribute( 'data-second_digit' );
				$posted[ $name ] = $captcha ? (string) ( $first + $second ) : '999999';
				$captcha_suffix  = substr( $name, strlen( 'et_pb_contact_captcha_' ) );
				$posted[ 'et_pb_contact_captcha_first_digit_' . $captcha_suffix ]  = (string) $first;
				$posted[ 'et_pb_contact_captcha_second_digit_' . $captcha_suffix ] = (string) $second;
			}
		}
	}
	$posted[ 'et_pb_contact_email_fields_' . $suffix ] = wp_json_encode( $fields );
	return $posted;
}

/**
 * Generates independent native local proof for this fixed adapter scope.
 *
 * @since 0.3.1
 * @param bool $trap Whether the original dynamic trap is filled synthetically.
 * @return array<string,string> Only own signed technical evidence.
 */
function rktsky_divi5_proof( bool $trap = false ): array {
	$proof = ( new \Rktsky\Antispam\Token() )->issue( 'divi', 'contact' );
	return array(
		'rktsky_antispam_token' => $proof['token'],
		$proof['trap']          => $trap ? 'synthetic-bot' : '',
	);
}

/**
 * Reads only synthetic event counts written before the actual mail blocker.
 *
 * @since 0.3.1
 * @param string $case Unique correlation tag sent by the local test transport.
 * @return array<string,int> Counts of native action and delivery boundaries.
 */
function rktsky_divi5_events( string $case ): array {
	$counts = array(
		'mail_attempt'     => 0,
		'native_submit'    => 0,
		'native_recipient' => 0,
	);
	foreach ( is_file( RKTSKY_DIVI5_FIXTURE_LOG ) ? file( RKTSKY_DIVI5_FIXTURE_LOG, FILE_IGNORE_NEW_LINES ) : array() as $line ) {
		$row = json_decode( $line, true );
		if ( ( $row['case'] ?? '' ) === $case && isset( $counts[ $row['event'] ?? '' ] ) ) {
			++$counts[ $row['event'] ]; }
	}
	return $counts;
}

/**
 * Executes a real HTTP case and checks pre-mail/native-action separation.
 *
 * @since 0.3.1
 * @param string $name Human-readable case label.
 * @param string $url Original page or public REST URL.
 * @param array  $data Native form controls plus optional local evidence.
 * @param int    $mail Expected original wp_mail attempts before interception.
 * @param int    $actions Expected original et_pb_contact_form_submit actions.
 * @param bool   $ajax Native XHR response shape.
 * @param bool   $guard Whether a neutral local rejection must be visible.
 * @return array Original HTTP response for additional original-outcome assertions.
 */
function rktsky_divi5_case( string $name, string $url, array $data, int $mail, int $actions, bool $ajax = false, bool $guard = false ): array {
	$case     = $name . '_' . bin2hex( random_bytes( 4 ) );
	$response = rktsky_divi5_http( $url, $data, $case, $ajax );
	$counts   = rktsky_divi5_events( $case );
	rktsky_divi5_expect( $mail === $counts['mail_attempt'], $name . ': native mail-attempt count differs: ' . wp_json_encode( $counts ) );
	rktsky_divi5_expect( $actions === $counts['native_submit'], $name . ': native submission action escaped/vanished: ' . wp_json_encode( $counts ) );
	if ( $guard ) {
		rktsky_divi5_expect( str_contains( $response['body'], 'spam protection could not verify' ), $name . ': the original transport did not expose the neutral local error.' ); }
	if ( $mail > 0 ) {
		rktsky_divi5_expect( 200 === $response['status'] && str_contains( $response['body'], 'accepted.' ), $name . ': original native success was not rendered.' ); }
	$GLOBALS['rktsky_divi5_results'][] = array(
		'case'           => $name,
		'status'         => $response['status'],
		'mail'           => $mail,
		'native_actions' => $actions,
		'pass'           => true,
	);
	echo 'PASS: ' . $name . "\n";
	return $response;
}

$original                            = get_option( \Rktsky\Antispam\Settings::OPTION );
$options                             = $original;
$options['divi']                     = true;
$options['minimum_seconds']          = 0;
$options['privacy_mode']             = false;
$options['quarantine_enabled']       = true;
$options['spam_keywords']            = 'Synthetic';
$options['allowed_languages']        = array( 'ja' );
$options['unknown_language_allowed'] = false;
$options['country_mode']             = 'allow';
$options['countries']                = array( 'CH' );
$options['country_unknown_allowed']  = false;
update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
global $wpdb;
$quarantine   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'rktsky_antispam_quarantine' ) );
$private_page = 0;
try {
	foreach ( array( 'native', 'legacy', 'migrated' ) as $kind ) {
		$url  = $base . '/?page_id=' . $fixture[ $kind ];
		$html = rktsky_divi5_http( $url )['body'];
		rktsky_divi5_expect( 1 === substr_count( $html, 'data-rk-antispam-provider="divi"' ), 'Original ' . $kind . ' renderer did not receive exactly one automatic host descriptor.' );
		$data = rktsky_divi5_posted( $html );
		rktsky_divi5_case( $kind . '_missing', $url, $data, 0, 0, false, true );
		rktsky_divi5_case( $kind . '_ajax_missing', $url, $data, 0, 0, true, true );
		rktsky_divi5_case( $kind . '_trap', $url, $data + rktsky_divi5_proof( true ), 0, 0, false, true );
		$valid = $data + rktsky_divi5_proof();
		rktsky_divi5_case( $kind . '_valid', $url, $valid, 1, 1 );
		rktsky_divi5_case( $kind . '_replay', $url, $valid, 0, 0, false, true );
		rktsky_divi5_case( $kind . '_retry_fresh', $url, $data + rktsky_divi5_proof(), 1, 1 );
		$rest = $base . '/index.php?rest_route=/wp/v2/pages/' . $fixture[ $kind ] . '&_method=GET';
		rktsky_divi5_case( $kind . '_rest_missing', $rest, $data, 0, 0, false, true );
		$required = $data;
		foreach ( array_keys( $required ) as $key ) {
			if ( str_contains( $key, '_name_' ) ) {
				$required[ $key ] = ''; }
		}
		$native_error = rktsky_divi5_case( $kind . '_required', $url, $required + rktsky_divi5_proof(), 0, 1 );
		rktsky_divi5_expect( str_contains( $native_error['body'], 'et_pb_contact_error_text' ), 'Original required-field validation disappeared.' );
	}
	$url  = $base . '/?page_id=' . $fixture['captcha'];
	$html = rktsky_divi5_http( $url )['body'];
	rktsky_divi5_case( 'captcha_wrong', $url, rktsky_divi5_posted( $html, 0, false ) + rktsky_divi5_proof(), 0, 1 );
	rktsky_divi5_case( 'captcha_correct', $url, rktsky_divi5_posted( $html ) + rktsky_divi5_proof(), 1, 1 );
	$url    = $base . '/?page_id=' . $fixture['multiple'];
	$html   = rktsky_divi5_http( $url )['body'];
	$first  = rktsky_divi5_posted( $html, 0 );
	$second = rktsky_divi5_posted( $html, 1 );
	rktsky_divi5_case( 'multiple_first', $url, $first + rktsky_divi5_proof(), 1, 1 );
	rktsky_divi5_case( 'multiple_second', $url, $second + rktsky_divi5_proof(), 1, 1 );
	rktsky_divi5_case( 'multiple_fanout', $url, array_merge( $first, $second, rktsky_divi5_proof() ), 0, 0, false, true );
	$url  = $base . '/?page_id=' . $fixture['duplicate'];
	$html = rktsky_divi5_http( $url )['body'];
	rktsky_divi5_case( 'duplicate_native_coordination', $url, rktsky_divi5_posted( $html ) + rktsky_divi5_proof(), 1, 1 );
	foreach ( array( 'unusual', 'long' ) as $kind ) {
		$url  = $base . '/?page_id=' . $fixture[ $kind ];
		$html = rktsky_divi5_http( $url )['body'];
		$data = rktsky_divi5_posted( $html );
		rktsky_divi5_case( $kind . '_missing', $url, $data, 0, 0, false, true );
		$rest = $base . '/index.php?rest_route=/wp/v2/pages/' . $fixture[ $kind ] . '&_method=GET';
		rktsky_divi5_case( $kind . '_rest_missing', $rest, $data, 0, 0, false, true );
		rktsky_divi5_case( $kind . '_unsupported_with_proof', $url, $data + rktsky_divi5_proof(), 0, 0, false, true );
	}
	$url  = $base . '/?page_id=' . $fixture['native'];
	$data = rktsky_divi5_posted( rktsky_divi5_http( $url )['body'] );
	$bad  = $data;
	foreach ( array_keys( $bad ) as $key ) {
		if ( str_starts_with( $key, '_wpnonce-et-pb-contact-form-submitted-' ) ) {
			$bad[ $key ] = 'invalid'; }
	}
	rktsky_divi5_case( 'native_bad_nonce', $url, $bad + rktsky_divi5_proof(), 0, 0 );
	$private_page                    = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'private',
			'post_title'  => 'Synthetic confidential fixture',
			'post_name'   => 'rktsky-confidential-page-hidden',
			'post_author' => 1,
		)
	);
	$private                         = $data;
	$private['rktsky_antispam_page'] = (string) $private_page;
	$private_response                = rktsky_divi5_case( 'private_reload', $url, $private, 0, 0, false, true );
	rktsky_divi5_expect( ! str_contains( $private_response['body'], esc_url( get_permalink( $private_page ) ) ) && ! str_contains( $private_response['body'], 'rktsky-confidential-page-hidden' ), 'The rejection disclosed a private page URL or slug.' );
	$public_response = rktsky_divi5_case( 'public_reload', $url, $data, 0, 0, false, true );
	rktsky_divi5_expect( str_contains( $public_response['body'], esc_url( get_permalink( $fixture['native'] ) ) ), 'The native public page metadata did not produce its correct reload link.' );
	$options['minimum_seconds'] = 2;
	update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
	rktsky_divi5_case( 'native_minimum_fast', $url, $data + rktsky_divi5_proof(), 0, 0, false, true );
	$aged = rktsky_divi5_proof();
	usleep( 2100000 );
	rktsky_divi5_case( 'native_minimum_aged', $url, $data + $aged, 1, 1 );
	rktsky_divi5_expect( $quarantine === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'rktsky_antispam_quarantine' ) ), 'The technical-only integration copied form content into quarantine.' );
	file_put_contents(
		'/tmp/rktsky-antispam-divi/native-divi5-results.json',
		wp_json_encode(
			array(
				'theme' => wp_get_theme()->get( 'Version' ),
				'cases' => $GLOBALS['rktsky_divi5_results'],
			),
			JSON_PRETTY_PRINT
		) . "\n"
	);
	echo 'PASS: ' . count( $GLOBALS['rktsky_divi5_results'] ) . " original Divi 5/legacy/conversion HTTP cases; no actual mail delivery.\n";
} finally {
	if ( $private_page > 0 ) {
		wp_delete_post( $private_page, true ); }
	update_option( \Rktsky\Antispam\Settings::OPTION, $original, false );
}
