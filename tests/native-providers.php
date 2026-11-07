<?php
/**
 * Exercises original provider submission engines in explicitly disposable WordPress.
 *
 * Cases run in fresh PHP processes because providers retain request-local state.
 * Browser coverage is recorded separately from these native API cases.
 *
 * @package RktskyAntispamTests
 * @since 0.1.0
 */
require __DIR__ . '/bootstrap.php';

/**
 * Fails at a broken provider boundary without exposing submission data.
 *
 * @since 0.1.0
 * @param bool $condition Required observable behavior.
 * @param string $message Failure context.
 * @return void
 */
function rktsky_antispam_provider_expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$forms = get_option( 'rktsky_antispam_test_forms', array() );
$case = $argv[1] ?? '';
if ( '' === $case ) {
	$cases = array();
	if ( ! empty( $forms['cf7'] ) && class_exists( 'WPCF7_ContactForm' ) ) {
		$cases = array_merge( $cases, array( 'cf7_valid', 'cf7_missing', 'cf7_honeypot' ) );
	}
	if ( ! empty( $forms['gravityforms'] ) && class_exists( 'GFAPI' ) ) {
		$cases = array_merge( $cases, array( 'gf_valid', 'gf_missing', 'gf_api_missing', 'gf_save_missing', 'gf_save_valid', 'gf_replay_same_request', 'gf_validation_then_submit' ) );
	}
	if ( ! empty( $forms['gravityforms_multi'] ) && class_exists( 'GFAPI' ) ) {
		$cases = array_merge( $cases, array( 'gf_multi_next', 'gf_multi_missing' ) );
	}
	if ( ! empty( $forms['wpforms'] ) && function_exists( 'wpforms' ) ) {
		$cases = array_merge( $cases, array( 'wpforms_valid', 'wpforms_missing', 'wpforms_honeypot' ) );
	}
	rktsky_antispam_provider_expect( count( $cases ) >= 3, 'An original provider fixture is required.' );
	foreach ( $cases as $name ) {
		$command = array( PHP_BINARY );
		// The optional locally compiled extension is never needed by the CI runtime.
		if ( ! extension_loaded( 'mbstring' ) && is_file( '/tmp/rktsky-antispam-runtime/iconv.so' ) ) {
			$command[] = '-d';
			$command[] = 'extension=/tmp/rktsky-antispam-runtime/iconv.so';
		}
		$command[] = __FILE__;
		$command[] = $name;
		$pipes = array();
		$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$status = proc_close( $process );
		if ( 0 !== $status || ! str_contains( $output, 'PASS: ' . $name ) ) {
			throw new RuntimeException( 'Native provider case failed: ' . $name . "\n" . $output . "\n" . $error );
		}
		echo trim( $output ) . "\n";
	}
	echo 'PASS: ' . count( $cases ) . " original provider cases.\n";
	return;
}

$_SERVER['HTTP_USER_AGENT'] = 'WP Antispam disposable native test browser';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array();
wp_set_current_user( 0 );
$tokens = new \Rktsky\Antispam\Token();

if ( str_starts_with( $case, 'cf7_' ) ) {
	$id = (int) $forms['cf7'];
	$form = WPCF7_ContactForm::get_instance( $id );
	$_POST = array( '_wpcf7' => (string) $id, '_wpcf7_version' => WPCF7_VERSION, '_wpcf7_unit_tag' => 'wpcf7-f' . $id . '-p' . $forms['page'] . '-o1', '_wpcf7_container_post' => (string) $forms['page'], 'your-name' => 'Synthetic native test', 'your-email' => 'fixture@example.invalid', 'your-message' => 'Synthetic submission.' );
	if ( 'cf7_missing' !== $case ) {
		$proof = $tokens->issue( 'cf7', (string) $id );
		$_POST['rktsky_antispam_token'] = $proof['token'];
		$_POST[ $proof['trap'] ] = 'cf7_honeypot' === $case ? 'synthetic-bot' : '';
	}
	$result = $form->submit( array( 'skip_mail' => true ) );
	rktsky_antispam_provider_expect( ( 'cf7_valid' === $case ? 'mail_sent' : 'spam' ) === ( $result['status'] ?? '' ), 'CF7 native accepted/spam result differs.' );
	$posted = WPCF7_Submission::get_instance()->get_posted_data();
	rktsky_antispam_provider_expect( ! isset( $posted['rktsky_antispam_token'] ), 'CF7 processed data retained a technical token.' );
	foreach ( array_keys( $posted ) as $key ) {
		rktsky_antispam_provider_expect( ! str_starts_with( $key, 'rktsky_as_' ), 'CF7 processed data retained a dynamic trap.' );
	}
} elseif ( str_starts_with( $case, 'gf_' ) ) {
	$multi = str_starts_with( $case, 'gf_multi_' );
	$id = (int) $forms[ $multi ? 'gravityforms_multi' : 'gravityforms' ];
	$before = GFAPI::count_entries( $id );
	// Submit every original rendered control, including GF's independent signed state and honeypot.
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$native_html = GFFormDisplay::get_form( $id, false, false, false, null, true );
	$document = new DOMDocument();
	@$document->loadHTML( $native_html );
	$xpath = new DOMXPath( $document );
	foreach ( $xpath->query( '//form[@id="gform_' . $id . '"]//input' ) as $input ) {
		$name = $input->getAttribute( 'name' );
		if ( '' !== $name && ! in_array( $input->getAttribute( 'type' ), array( 'submit', 'button' ), true ) ) {
			$_POST[ $name ] = $input->getAttribute( 'value' );
		}
	}
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST['input_1'] = 'Synthetic native test';
	// GF's browser script submits this public value from its native inline configuration.
	$_POST['version_hash'] = wp_hash( GFForms::$version );
	$values = array();
	if ( ! str_ends_with( $case, 'missing' ) ) {
		$proof = $tokens->issue( 'gravityforms', (string) $id );
		$_POST['rktsky_antispam_token'] = $proof['token'];
		$_POST[ $proof['trap'] ] = '';
	}
	if ( str_starts_with( $case, 'gf_save_' ) ) {
		$_POST['gform_save'] = '1';
	}
	if ( 'gf_validation_then_submit' === $case ) {
		$validated = GFAPI::validate_form( $id, $values );
		rktsky_antispam_provider_expect( ! is_wp_error( $validated ) && ! empty( $validated['is_valid'] ), 'Native API validation must execute the guard.' );
	}
	$context = 'gf_api_missing' === $case ? GFFormDisplay::SUBMISSION_INITIATED_BY_API : GFFormDisplay::SUBMISSION_INITIATED_BY_WEBFORM;
	$result = GFAPI::submit_form( $id, $values, array(), $multi ? 2 : 0, 1, $context );
	rktsky_antispam_provider_expect( ! is_wp_error( $result ), 'GF did not return a native validation result.' );
	if ( str_ends_with( $case, 'missing' ) || 'gf_validation_then_submit' === $case ) {
		rktsky_antispam_provider_expect( empty( $result['is_valid'] ), 'GF accepted unprotected or consumed evidence.' );
		rktsky_antispam_provider_expect( $before === GFAPI::count_entries( $id ), 'GF persisted a rejected entry.' );
	} elseif ( 'gf_save_valid' === $case ) {
		rktsky_antispam_provider_expect( ! empty( $result['resume_token'] ), 'Protected Save and Continue did not create a draft token. Keys: ' . implode( ',', array_keys( $result ) ) );
	} else {
		rktsky_antispam_provider_expect( ! empty( $result['is_valid'] ), 'GF rejected a protected native submission.' );
		if ( $multi ) {
			rktsky_antispam_provider_expect( 2 === $result['page_number'], 'GF multipage navigation did not reach page two.' );
		} else {
			rktsky_antispam_provider_expect( ! empty( $result['entry_id'] ), 'GF did not persist an accepted final submission.' );
		}
	}
	if ( 'gf_replay_same_request' === $case ) {
		$_POST = array( 'rktsky_antispam_token' => $proof['token'], $proof['trap'] => '' );
		$persisted_after_first = GFAPI::count_entries( $id );
		$before_replay = ( new \Rktsky\Antispam\Store() )->summary()['reasons']['replay'] ?? 0;
		$second = GFAPI::submit_form( $id, array( 'input_1' => 'Synthetic second native test' ), array(), 0, 1, GFFormDisplay::SUBMISSION_INITIATED_BY_WEBFORM );
		// GF may retain the first response in its own per-form static cache; persistence is authoritative.
		rktsky_antispam_provider_expect( $persisted_after_first === GFAPI::count_entries( $id ), 'A second native GF submission persisted a replay.' );
		$after_replay = ( new \Rktsky\Antispam\Store() )->summary()['reasons']['replay'] ?? 0;
		rktsky_antispam_provider_expect( $after_replay === $before_replay + 1, 'The second native GF submission did not recheck the consumed token.' );
	}
} elseif ( str_starts_with( $case, 'wpforms_' ) ) {
	$id = (int) $forms['wpforms'];
	ob_start();
	wpforms_display( $id, false, false );
	$html = (string) ob_get_clean();
	$document = new DOMDocument();
	@$document->loadHTML( $html );
	$entry = array( 'id' => (string) $id, 'fields' => array( 1 => 'Synthetic native test' ) );
	foreach ( $document->getElementsByTagName( 'input' ) as $input ) {
		$name = $input->getAttribute( 'name' );
		if ( preg_match( '/^wpforms\[([a-z_]+)\]$/D', $name, $matches ) ) {
			$entry[ $matches[1] ] = $input->getAttribute( 'value' );
		} elseif ( preg_match( '/^wpforms\[fields\]\[(\d+)\]$/D', $name, $matches ) && '1' !== $matches[1] ) {
			$entry['fields'][ $matches[1] ] = '';
		}
	}
	if ( 'wpforms_missing' !== $case ) {
		$proof = $tokens->issue( 'wpforms', (string) $id );
		$_POST['rktsky_antispam_token'] = $proof['token'];
		$_POST[ $proof['trap'] ] = 'wpforms_honeypot' === $case ? 'synthetic-bot' : '';
	}
	$_POST['wpforms'] = $entry;
	wpforms()->process->process( $entry );
	$errors = wpforms()->process->errors[ $id ] ?? array();
	if ( 'wpforms_valid' === $case ) {
		rktsky_antispam_provider_expect( empty( $errors ) && empty( wpforms()->process->spam_errors[ $id ] ), 'WPForms rejected valid native controls with a local challenge.' );
	} else {
		rktsky_antispam_provider_expect( str_contains( $errors['header'] ?? '', 'spam protection' ), 'WPForms did not expose the guard failure in its native header.' );
	}
} else {
	throw new RuntimeException( 'Unknown native provider case.' );
}
echo 'PASS: ' . $case . "\n";
