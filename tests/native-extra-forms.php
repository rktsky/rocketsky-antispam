<?php
/**
 * Exercises original free form engines before persistence, mail and native actions.
 *
 * Each case uses a new PHP request, including replay/retry sequences. This suite
 * never disables native antispam, validation, notifications or provider actions.
 *
 * @package RktskyAntispamTests
 * @since 0.3.0
 */
require __DIR__ . '/bootstrap.php';

/**
 * Stops at an observable native boundary without exposing actual visitor data.
 *
 * @since 0.3.0
 * @param bool   $condition Required behaviour.
 * @param string $message Failure context.
 * @return void
 */
function rktsky_extra_native_expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
}

$provider = $argv[1] ?? '';
$case     = $argv[2] ?? '';
$forms    = get_option( 'rktsky_antispam_extra_test_forms', array() );
rktsky_extra_native_expect( ! empty( $forms['formidable'] ) && ! empty( $forms['fluentforms'] ) && ! empty( $forms['wsform'] ), 'Run setup-extra-forms.php with all three original plugins active first.' );
rktsky_extra_native_expect( false !== has_filter( 'pre_wp_mail', '__return_false' ) && false !== has_filter( 'pre_http_request' ), 'Fixture mail and HTTP side effects must remain blocked.' );
if ( '' === $provider ) {
	$settings = new \Rktsky\Antispam\Settings();
	$original = $settings->all();
	$options  = $original;
	foreach ( array( 'formidable', 'fluentforms', 'wsform' ) as $name ) {
		$options[ $name ] = true; }
	$options['minimum_seconds'] = 0;
	$options['spam_keywords']   = '';
	$options['spam_patterns']   = '';
	$options['bbcode_filter']   = false;
	update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
	$total = 0;
	try {
		foreach ( array( 'formidable', 'fluentforms', 'wsform' ) as $name ) {
			foreach ( array( 'render', 'valid', 'missing', 'honeypot', 'replay_prime', 'replay', 'retry_missing', 'retry_fresh', 'keyword', 'quarantine' ) as $test ) {
				$command = array( PHP_BINARY );
				if ( ! extension_loaded( 'mbstring' ) && is_file( '/tmp/rktsky-antispam-runtime/iconv.so' ) ) {
					$command = array_merge( $command, array( '-d', 'extension=/tmp/rktsky-antispam-runtime/iconv.so' ) ); }
				$command = array_merge( $command, array( __FILE__, $name, $test ) );
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
				rktsky_extra_native_expect( 0 === $status && str_contains( $output, 'PASS: ' . $name . '/' . $test ), 'Native case failed: ' . $name . '/' . $test . "\n" . $output . "\n" . $error );
				echo trim( $output ) . "\n";
				++$total;
			}
		}
	} finally {
		update_option( \Rktsky\Antispam\Settings::OPTION, $original, false );
		delete_option( 'rktsky_antispam_extra_replay' );
	}
	echo 'PASS: ' . $total . " original extra-form engine cases.\n";
	return;
}

rktsky_extra_native_expect( in_array( $provider, array( 'formidable', 'fluentforms', 'wsform' ), true ), 'Unknown synthetic provider.' );
$id = (int) $forms[ $provider ];
wp_set_current_user( 0 );
$_SERVER['REMOTE_ADDR']        = '192.0.2.90';
$_SERVER['HTTP_USER_AGENT']    = 'Synthetic native extra-form browser';
$_SERVER['REQUEST_METHOD']     = 'GET';
$settings                      = new \Rktsky\Antispam\Settings();
$original                      = $settings->all();
$options                       = $original;
$options['privacy_mode']       = 'quarantine' !== $case;
$options['quarantine_enabled'] = 'quarantine' === $case;
$options['spam_keywords']      = in_array( $case, array( 'keyword', 'quarantine' ), true ) ? 'blocked-content-check' : '';
update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
$restore = static function () use ( $original ): void {
	update_option( \Rktsky\Antispam\Settings::OPTION, $original, false );
};
register_shutdown_function( $restore );
$token_service = new \Rktsky\Antispam\Token();
$proof         = 'replay' === $case ? get_option( 'rktsky_antispam_extra_replay', array() ) : $token_service->issue( $provider, (string) $id );
if ( 'replay_prime' === $case ) {
	update_option( 'rktsky_antispam_extra_replay', $proof, false ); }
$missing = in_array( $case, array( 'missing', 'retry_missing' ), true );
$pass    = in_array( $case, array( 'valid', 'replay_prime', 'retry_fresh' ), true );
$body    = ( in_array( $case, array( 'keyword', 'quarantine' ), true ) ? 'blocked-content-check ' : '' ) . 'Synthetic body ' . $provider . ' ' . $case . ' ' . wp_generate_uuid4();
$raw     = array();
if ( 'formidable' === $provider ) {
	$html     = do_shortcode( '[formidable id="' . $id . '"]' );
	$document = new DOMDocument();
	@$document->loadHTML( $html );
	$xpath = new DOMXPath( $document );
	foreach ( $xpath->query( '//form//input' ) as $input ) {
		if ( '' !== $input->getAttribute( 'name' ) ) {
			$raw[ $input->getAttribute( 'name' ) ] = $input->getAttribute( 'value' ); }
	}
	$native_form = $xpath->query( '//form' )->item( 0 );
	rktsky_extra_native_expect( null !== $native_form, 'Original Formidable renderer must output a real form.' );
	$raw['antispam_token'] = $native_form->getAttribute( 'data-token' );
	$raw['item_meta']      = array();
	foreach ( $forms['formidable_fields'] as $type => $field ) {
		$raw['item_meta'][ $field ] = 'email' === $type ? 'fixture@example.invalid' : ( 'textarea' === $type ? $body : 'Synthetic name' ); }
	$raw['item_meta'][999999] = 'unknown-secret';
} elseif ( 'fluentforms' === $provider ) {
	$html = do_shortcode( '[fluentform id="' . $id . '"]' );
	$raw  = array(
		'name'    => 'Synthetic name',
		'email'   => 'fixture@example.invalid',
		'message' => $body,
	);
} else {
	$html = do_shortcode( '[ws_form id="' . $id . '"]' );
	$raw  = array(
		'wsf_form_id'   => $id,
		'wsf_post_mode' => 'submit',
	);
	foreach ( $forms['wsform_fields'] as $type => $field ) {
		$raw[ 'field_' . $field ] = 'email' === $type ? 'fixture@example.invalid' : ( 'textarea' === $type ? $body : 'Synthetic name' ); }
	$raw['field_999999'] = 'unknown-secret';
}
if ( 'render' === $case ) {
	rktsky_extra_native_expect( str_contains( $html, 'wsform' === $provider ? 'data-rk-antispam-provider="wsform"' : 'data-rk-antispam' ), 'Original rendering must include the local controls/retained host descriptor.' );
	echo 'PASS: ' . $provider . '/' . $case . "\n";
	return;
}
$raw['password']    = 'password-secret';
$raw['credit_card'] = 'payment-secret';
$raw['upload']      = 'file-secret';
if ( ! $missing ) {
	$raw['rktsky_antispam_token'] = $proof['token'];
	$raw[ $proof['trap'] ]        = 'honeypot' === $case ? 'synthetic-bot' : ''; }
$_POST                     = wp_slash(
	'fluentforms' === $provider ? array(
		'data'    => http_build_query( $raw ),
		'form_id' => $id,
	) : $raw
);
$_REQUEST                  = $_POST;
$_SERVER['REQUEST_METHOD'] = 'POST';
$tables                    = array(
	'formidable'  => 'frm_items',
	'fluentforms' => 'fluentform_submissions',
	'wsform'      => 'wsf_submit',
);
$table                     = $wpdb->prefix . $tables[ $provider ];
$before                    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE form_id=%d', $table, $id ) );
$qtable                    = $wpdb->prefix . 'rktsky_antispam_quarantine';
$qbefore                   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i', $qtable ) );
$actions                   = 0;
$mails                     = 0;
$action_hooks              = array(
	'formidable'  => 'frm_after_create_entry',
	'fluentforms' => 'fluentform/submission_inserted',
	'wsform'      => 'wsf_actions_post',
);
add_action(
	$action_hooks[ $provider ],
	static function () use ( &$actions ): void {
		++$actions;
	},
	0
);
add_filter(
	'pre_wp_mail',
	static function ( $return ) use ( &$mails ) {
		++$mails;
		return $return;
	},
	0
);
$verify = static function ( array $result ) use ( &$actions, &$mails, $provider, $case, $pass, $before, $table, $id, $qbefore, $qtable, $settings ): void {
	global $wpdb;
	$after = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE form_id=%d', $table, $id ) );
	rktsky_extra_native_expect( $after === $before + ( $pass ? 1 : 0 ), 'The original provider must persist exactly one valid entry and no rejected entry.' );
	rktsky_extra_native_expect( $pass ? $actions > 0 : 0 === $actions && 0 === $mails, 'Rejected native forms must stop before provider actions and mail attempts.' );
	if ( ! $pass ) {
		$encoded = wp_json_encode( $result );
		rktsky_extra_native_expect( str_contains( $encoded, 'rktsky_antispam' ) || ( 'wsform' === $provider && ! empty( $result['error_validation'] ) ), 'A local rejection must remain a visible native validation failure.' );
		if ( 'wsform' === $provider ) {
			rktsky_extra_native_expect( false === ( $result['data']['js'][0]['form_hide'] ?? null ), 'WS retry errors must use boolean false so the native client keeps the form visible.' );
		}
	}
	if ( 'quarantine' === $case ) {
		wp_set_current_user( 1 );
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE id>%d AND provider=%s AND form_id=%s', $qtable, $qbefore, $provider, (string) $id ) );
		rktsky_extra_native_expect( 1 === count( $rows ), 'One rejected native submission must create exactly one optional quarantine record.' );
		$quarantine = new \Rktsky\Antispam\Quarantine( $settings );
		$record     = $quarantine->read( (int) $rows[0] );
		$text       = wp_json_encode( $record['fields'] ?? array() );
		rktsky_extra_native_expect( str_contains( $text, 'blocked-content-check' ) && str_contains( $text, 'fixture@example.invalid' ) && ! str_contains( $text, 'secret' ) && ! str_contains( $text, 'rktsky_' ), 'Native quarantine must preserve allowed text/email while excluding technical, unknown and sensitive inputs.' );
		$quarantine->delete( (int) $rows[0] );
	}
	echo 'PASS: ' . $provider . '/' . $case . "\n";
};
if ( 'formidable' === $provider ) {
	FrmEntriesController::process_entry();
	global $frm_vars;
	$verify( (array) ( $frm_vars['created_entries'][ $id ] ?? array() ) );
} elseif ( 'fluentforms' === $provider ) {
	try {
		$result = ( new \FluentForm\App\Services\Form\SubmissionHandlerService() )->handleSubmission( $raw, $id ); } catch ( \FluentForm\Framework\Validator\ValidationException $error ) {
		$result = $error->errors(); }
		$verify( (array) $result );
} else {
	if ( ! class_exists( 'WS_Form_API_Submit' ) ) {
		do_action( 'rest_api_init' ); }
	$controller = new WS_Form_API_Submit();
	$response   = array();
	add_filter(
		'wsf_api_submit_response_data',
		static function ( $result ) use ( &$response ) {
			$response = $result;
			return $result;
		}
	);
	// WordPress flushes output before later shutdown callbacks; inspect the unchanged native response filter instead.
	ob_start(
		static function (): string {
			return '';
		}
	);
	register_shutdown_function(
		static function () use ( $verify, &$response ): void {
			$verify( $response );
		}
	);
	$controller->api_post( array() );
}
