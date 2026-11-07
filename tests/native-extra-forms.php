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

/**
 * Counts synthetic records through provider APIs without querying their tables.
 *
 * @since 0.5.0
 * @param string $provider Explicit fixture provider.
 * @param int $id Native fixture form ID.
 * @return int Native record count including spam.
 */
function rktsky_extra_native_count( string $provider, int $id ): int {
	$user = get_current_user_id();
	wp_set_current_user( 1 );
	try {
		if ( 'formidable' === $provider ) {
			return (int) FrmEntry::getRecordCount( array( 'it.form_id' => $id, 'it.is_draft' => array( 0, 1, FrmSpamEntriesHelper::SPAM_ENTRY_STATUS ) ) );
		}
		if ( 'fluentforms' === $provider ) {
			return (int) fluentFormApi( 'submissions' )->get( array( 'form_ids' => array( $id ), 'per_page' => 1 ) )['total'];
		}
		$submit = new WS_Form_Submit();
		$total = 0;
		foreach ( array( 'publish', 'draft', 'spam', 'trash' ) as $status ) {
			$total += (int) $submit->db_get_count_by_status( $id, $status );
		}
		return $total;
	} finally {
		wp_set_current_user( $user );
	}
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
$id = 'fluentforms' === $provider && isset( $argv[5] ) ? (int) $argv[5] : (int) $forms[ $provider ];
wp_set_current_user( 0 );
// Fluent's default visitor budget counts stored submissions across all forms.
// The independent feedback suite has its own fixed TEST-NET visitor; both
// suites retain the provider's unchanged limit, including repeated cases.
$_SERVER['REMOTE_ADDR']        = 'fluentforms' === $provider && 'observe-id' === ( $argv[3] ?? '' ) ? '198.51.100.90' : '192.0.2.90';
$_SERVER['HTTP_USER_AGENT']    = 'Synthetic native extra-form browser';
$_SERVER['REQUEST_METHOD']     = 'GET';
$settings                      = new \Rktsky\Antispam\Settings();
$original                      = $settings->all();
$options                       = $original;
$policy_cases = array( 'keyword', 'quarantine', 'content_missing', 'content_honeypot', 'content_replay', 'native_block', 'native_redirect' );
$options['privacy_mode']       = ! in_array( $case, array( 'quarantine', 'native_block' ), true );
$options['quarantine_enabled'] = in_array( $case, array( 'quarantine', 'native_block' ), true );
$options['spam_keywords']      = in_array( $case, $policy_cases, true ) ? 'blocked-content-check' : '';
$options['bbcode_filter']      = 'bbcode' === $case;
$options['spam_patterns']      = 'pattern' === $case ? 'blocked-content-check' : '';
update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
$restore = static function () use ( $original ): void {
	update_option( \Rktsky\Antispam\Settings::OPTION, $original, false );
};
register_shutdown_function( $restore );
$token_service = new \Rktsky\Antispam\Token();
$proof         = 'replay' === $case ? get_option( 'rktsky_antispam_extra_replay', array() ) : $token_service->issue( $provider, (string) $id );
if ( 'replay_prime' === $case ) {
	update_option( 'rktsky_antispam_extra_replay', $proof, false ); }
$missing = in_array( $case, array( 'missing', 'retry_missing', 'content_missing' ), true );
$pass    = in_array( $case, array( 'valid', 'replay_prime', 'retry_fresh' ), true );
$body    = ( in_array( $case, array_merge( $policy_cases, array( 'pattern' ) ), true ) ? 'blocked-content-check ' : '' ) . 'Synthetic body ' . $provider . ' ' . $case . ' ' . wp_generate_uuid4();
if ( 'bbcode' === $case ) {
	$body .= ' [url=https://example.invalid]Synthetic link[/url]';
}
if ( 'native_block' === $case && 'formidable' === $provider ) {
	add_filter( 'frm_save_spam_entry', static fn( $save, $source ) => 'rktsky_antispam' === $source ? false : $save, PHP_INT_MAX, 2 );
}
if ( 'native_block' === $case && 'wsform' === $provider ) {
	add_filter( 'wsf_pre_render', static function ( $form ) {
		foreach ( $form->meta->action->groups[0]->rows ?? array() as $row ) {
			$config = json_decode( $row->data[1] ?? '', true );
			if ( 'database' === ( $config['id'] ?? '' ) ) { $row->disabled = 'on'; }
		}
		return $form;
	}, PHP_INT_MAX );
}
if ( 'native_redirect' === $case && 'formidable' === $provider ) {
	$form = FrmForm::getOne( $id );
	$original_form_options = $form->options;
	FrmForm::update( $id, array( 'options' => array_replace( $form->options, array( 'success_action' => 'redirect', 'success_url' => 'https://example.invalid/should-not-redirect' ) ) ) );
	register_shutdown_function( static function () use ( $id, $original_form_options ): void { FrmForm::update( $id, array( 'options' => $original_form_options ) ); } );
}
$fixture_email = $argv[4] ?? 'fixture@example.invalid';
rktsky_extra_native_expect( 'fixture@example.invalid' === $fixture_email || 1 === preg_match( '/^feedback-[a-f0-9]{12}@example[.]invalid$/D', $fixture_email ), 'Only synthetic fixture email addresses are accepted.' );
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
		$raw['item_meta'][ $field ] = 'email' === $type ? $fixture_email : ( 'textarea' === $type ? $body : 'Synthetic name' ); }
	$raw['item_meta'][999999] = 'unknown-secret';
} elseif ( 'fluentforms' === $provider ) {
	$html = do_shortcode( '[fluentform id="' . $id . '"]' );
	$raw  = array(
		'name'    => 'Synthetic name',
		'email'   => $fixture_email,
		'message' => $body,
	);
} else {
	$html = do_shortcode( '[ws_form id="' . $id . '"]' );
	$raw  = array(
		'wsf_form_id'   => $id,
		'wsf_post_mode' => 'submit',
	);
	foreach ( $forms['wsform_fields'] as $type => $field ) {
		$raw[ 'field_' . $field ] = 'email' === $type ? $fixture_email : ( 'textarea' === $type ? $body : 'Synthetic name' ); }
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
	$raw[ $proof['trap'] ]        = in_array( $case, array( 'honeypot', 'content_honeypot' ), true ) ? 'synthetic-bot' : ''; }
if ( 'content_replay' === $case ) {
	$guard = new \Rktsky\Antispam\Guard( $settings, $token_service, new \Rktsky\Antispam\Store() );
	rktsky_extra_native_expect( '' === $guard->check( $provider, (string) $id, $raw, array(), true ), 'The replay fixture must first consume authentic proof.' );
}
$_POST                     = wp_slash(
	'fluentforms' === $provider ? array(
		'data'    => http_build_query( $raw ),
		'form_id' => $id,
	) : $raw
);
$_REQUEST                  = $_POST;
$_SERVER['REQUEST_METHOD'] = 'POST';
$before                    = rktsky_extra_native_count( $provider, $id );
$native_spam = false;
if ( in_array( $case, array( 'keyword', 'quarantine', 'bbcode', 'pattern', 'native_redirect' ), true ) ) {
	if ( 'formidable' === $provider ) {
		$native_spam = \Rktsky\Antispam\Integrations\Formidable::native_available();
	} elseif ( 'fluentforms' === $provider ) {
		$native_spam = \Rktsky\Antispam\Integrations\Fluent::native_available();
	} else {
		wp_set_current_user( 1 );
		$ws = new WS_Form_Form();
		$ws->id = $id;
		$native_spam = \Rktsky\Antispam\Integrations\WsForm::native_available( $ws->db_read( true, true ) );
		wp_set_current_user( 0 );
	}
}
$qtable                    = $wpdb->prefix . 'rktsky_antispam_quarantine';
$qbefore                   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i', $qtable ) );
$actions                   = 0;
$mails                     = 0;
$native_ids                = array();
if ( 'formidable' === $provider ) {
	add_action( 'frm_after_create_entry', static function ( $created ) use ( &$native_ids ): void { $native_ids[] = (int) $created; }, PHP_INT_MAX );
} elseif ( 'wsform' === $provider ) {
	add_action( 'wsf_submit_create', static function ( $created ) use ( &$native_ids ): void { $native_ids[] = (int) $created->id; }, PHP_INT_MAX );
}
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
if ( 'fluentforms' === $provider ) {
	foreach ( array( 'fluentform/before_insert_payment_form', 'fluentform/notify_on_form_submit', 'fluentform/before_form_actions_processing', 'fluentform_submission_inserted' ) as $side_effect ) {
		add_action( $side_effect, static function () use ( &$actions ): void { ++$actions; }, 0 );
	}
}
$verify = static function ( array $result ) use ( &$actions, &$mails, &$native_ids, $provider, $case, $pass, $native_spam, $before, $id, $qbefore, $qtable, $settings ): void {
	global $wpdb;
	$after = rktsky_extra_native_count( $provider, $id );
	rktsky_extra_native_expect( $after === $before + ( $pass || $native_spam ? 1 : 0 ), sprintf( 'Only accepted submissions and supported native content-spam decisions may create a provider record (%s/%s, before=%d, after=%d, native_status=%d).', $provider, $case, $before, $after, (int) ( $result['_fixture_native_status'] ?? 0 ) ) );
	rktsky_extra_native_expect( $pass ? $actions > 0 : ( ( 'fluentforms' !== $provider && $native_spam ) || 0 === $actions ) && 0 === $mails, 'Rejected native forms must not send mail or run ordinary submission actions.' );
	if ( $pass || $native_spam ) {
		wp_set_current_user( 1 );
		if ( 'fluentforms' === $provider ) {
			$latest = fluentFormApi( 'submissions' )->get( array( 'form_ids' => array( $id ), 'per_page' => 1, 'sort_type' => 'DESC', 'entry_type' => 'all' ) );
			$native_ids = array( (int) $latest['data'][0]->id );
		}
		rktsky_extra_native_expect( 1 === count( $native_ids ), 'A content verdict must create exactly one native spam object.' );
		if ( 'formidable' === $provider ) {
			$entry = FrmEntry::getOne( $native_ids[0], true );
			rktsky_extra_native_expect( FrmSpamEntriesHelper::is_spam( $entry ) === $native_spam && ( ! $native_spam || ! isset( $entry->metas[999999] ) ), 'Native Formidable spam must contain no undeclared metadata.' );
		} elseif ( 'wsform' === $provider ) {
			$reader = new WS_Form_Submit();
			$reader->id = $native_ids[0];
			$entry = $reader->db_read( true, false );
			rktsky_extra_native_expect( ( 'spam' === $entry->status ) === $native_spam, 'The original WS database action must persist native spam status.' );
		} else {
			$entry = fluentFormApi( 'submissions' )->find( $native_ids[0] );
			$stored = wp_json_encode( $entry->response );
			rktsky_extra_native_expect( ( 'spam' === $entry->status ) === $native_spam, 'Fluent must retain the actual native status.' );
			if ( $native_spam ) {
				rktsky_extra_native_expect( ! str_contains( $stored, 'secret' ) && ! str_contains( $stored, 'rktsky_' ) && empty( $entry->ip ) && empty( $entry->browser ) && empty( $entry->device ) && empty( $entry->source_url ) && empty( $entry->user_id ), 'Fluent native spam must exclude undeclared, sensitive and technical metadata.' );
			}
		}
		$feedback = ( new \Rktsky\Antispam\Feedback() )->status( $provider, $native_ids[0] );
		rktsky_extra_native_expect( null !== $feedback && $feedback['predicted_spam'] === $native_spam && ! $feedback['reviewed'], 'Native storage must create one exact unreviewed prediction for accepted and spam entries.' );
		if ( 'observe-id' === ( $GLOBALS['argv'][3] ?? '' ) ) {
			echo 'NATIVE-ID: ' . $native_ids[0] . "\n";
		}
		wp_set_current_user( 0 );
	}
	if ( ! $pass ) {
		$encoded = wp_json_encode( $result );
		rktsky_extra_native_expect( $native_spam || str_contains( $encoded, 'rktsky_antispam' ) || ( 'wsform' === $provider && ! empty( $result['error_validation'] ) ), 'A local rejection must remain a visible native validation failure.' );
		if ( 'wsform' === $provider ) {
			rktsky_extra_native_expect( false === ( $result['data']['js'][0]['form_hide'] ?? null ), 'WS retry errors must use boolean false so the native client keeps the form visible.' );
		}
	}
	if ( in_array( $case, array( 'quarantine', 'native_block' ), true ) ) {
		wp_set_current_user( 1 );
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE id>%d AND provider=%s AND form_id=%s', $qtable, $qbefore, $provider, (string) $id ) );
		rktsky_extra_native_expect( ( $native_spam ? 0 : 1 ) === count( $rows ), 'Native spam records must not be duplicated into optional local quarantine.' );
		if ( $native_spam ) {
			echo 'PASS: ' . $provider . '/' . $case . "\n";
			return;
		}
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
	if ( $native_spam ) {
		$feedback_html = do_shortcode( '[formidable id="' . $id . '"]' );
		$public_error = ( new \Rktsky\Antispam\Guard( $settings, $token_service, new \Rktsky\Antispam\Store() ) )->spam_message();
		rktsky_extra_native_expect( str_contains( $feedback_html, esc_html( $public_error ) ) && ! str_contains( $feedback_html, 'should-not-redirect' ), 'Stored native spam must display a real rejection and never the configured success redirect.' );
	}
	$verify( (array) ( $frm_vars['created_entries'][ $id ] ?? array() ) );
} elseif ( 'fluentforms' === $provider ) {
	try {
		$result = ( new \FluentForm\App\Services\Form\SubmissionHandlerService() )->handleSubmission( $raw, $id );
	} catch ( \FluentForm\Framework\Validator\ValidationException $error ) {
		$result                          = $error->errors();
		$result['_fixture_native_status'] = (int) $error->getCode();
	}
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
		},
		PHP_INT_MAX
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
