<?php
/**
 * Verifies CF7 and Flamingo storage, moderation and privacy with original providers.
 *
 * Each submission runs in a new PHP process because CF7 retains a singleton. No
 * provider table is accessed directly; the disposable fixture blocks external mail.
 *
 * @package RktskyAntispamTests
 * @since 0.5.0
 */

use Rktsky\Antispam\{Feedback, Quarantine, Settings, SpamHandling, Token};

require __DIR__ . '/bootstrap.php';
if ( ! class_exists( 'WPCF7_ContactForm' ) || ! class_exists( 'Flamingo_Inbound_Message' ) ) {
	echo "SKIP: Original CF7 and Flamingo are required.\n";
	return;
}

/**
 * Fails at an observable boundary without exposing submitted data.
 *
 * @since 0.5.0
 * @param bool   $condition Required behavior.
 * @param string $message Failure context.
 * @return void
 * @throws RuntimeException When the observed native boundary differs.
 */
function rktsky_cf7_expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$case = $argv[1] ?? '';
if ( '' === $case ) {
	$cases    = array( 'valid', 'keyword', 'missing', 'replay', 'private', 'no-inbox', 'no-inbox-private', 'no-retention', 'provider-spam', 'provider-spam-missing', 'provider-spam-replay', 'native-save-off' );
	$settings = new Settings();
	$original = get_option( Settings::OPTION );
	update_option(
		Settings::OPTION,
		array_replace(
			$settings->defaults(),
			array(
				'cf7'                => true,
				'privacy_mode'       => false,
				'quarantine_enabled' => true,
				'spam_keywords'      => 'rktsky-synthetic-spam',
			)
		),
		false
	);
	try {
		foreach ( $cases as $name ) {
			update_option(
				Settings::OPTION,
				array_replace(
					$settings->defaults(),
					array(
						'cf7'                => true,
						'privacy_mode'       => false,
						'quarantine_enabled' => true,
						'spam_keywords'      => 'rktsky-synthetic-spam',
					)
				),
				false
			);
			$command = array( PHP_BINARY );
			if ( is_file( '/tmp/rktsky-antispam-runtime/iconv.so' ) && ! extension_loaded( 'mbstring' ) ) {
				$command = array_merge( $command, array( '-d', 'extension=/tmp/rktsky-antispam-runtime/iconv.so' ) );
			}
			$command = array_merge( $command, array( __FILE__, $name ) );
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
			$output      = stream_get_contents( $pipes[1] );
			$child_error = stream_get_contents( $pipes[2] );
			fclose( $pipes[1] );
			fclose( $pipes[2] );
			$child_status = proc_close( $process );
			rktsky_cf7_expect( 0 === $child_status && str_contains( $output, 'PASS: CF7 ' . $name ), 'CF7 case failed: ' . $name . "\n" . $output . $child_error );
			echo $output;
		}
	} finally {
		update_option( Settings::OPTION, $original, false );
	}
	echo 'PASS: ' . count( $cases ) . " native CF7/Flamingo flows.\n";
	return;
}

wp_set_current_user( 1 );
$settings      = new Settings();
$case_original = get_option( Settings::OPTION );
if ( 'no-retention' === $case ) {
	update_option( Settings::OPTION, array_replace( $settings->all(), array( 'privacy_mode' => true ) ), false );
}
$form = WPCF7_ContactForm::get_template();
$form->set_title( 'Synthetic native inbox flow' );
$form->set_properties(
	array(
		'form'                => '[text* your-name][email* your-email][textarea your-message][hidden internal "private-hidden"][submit "Send"]',
		'additional_settings' => in_array( $case, array( 'private', 'no-inbox-private' ), true ) ? "skip_mail: on\ndo_not_store: on" : 'skip_mail: on',
	)
);
$synthetic_form_id = $form->save();
$quarantine        = new Quarantine( $settings );
$before_quarantine = $quarantine->listing()['total'];
$before_ids        = get_posts(
	array(
		'post_type'   => 'flamingo_inbound',
		'post_status' => array( 'publish', 'flamingo-spam', 'trash' ),
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
$mail_attempts     = 0;
/** Counts intercepted mail without delivering it. @since 0.5.0 @return bool Always false. */
add_filter(
	'pre_wp_mail',
	static function () use ( &$mail_attempts ) {
		++$mail_attempts;
		return false;
	},
	1
);
if ( str_starts_with( $case, 'no-inbox' ) ) {
	remove_action( 'wpcf7_submit', 'wpcf7_flamingo_submit', 10 );
}
if ( 'native-save-off' === $case ) {
	add_filter(
		'wpcf7_flamingo_submit_if',
		static function () {
			return array( 'mail_sent', 'mail_failed' );
		}
	);
}
if ( str_starts_with( $case, 'provider-spam' ) ) {
	add_filter( 'wpcf7_spam', '__return_true', 5 );
}
$_SERVER['REQUEST_METHOD']  = 'POST';
$_SERVER['REMOTE_ADDR']     = '192.0.2.42';
$_SERVER['HTTP_USER_AGENT'] = 'Synthetic local CF7 test';
$proof                      = ( new Token() )->issue( 'cf7', (string) $synthetic_form_id );
$_POST                      = array(
	'_wpcf7'                => (string) $synthetic_form_id,
	'_wpcf7_version'        => WPCF7_VERSION,
	'_wpcf7_unit_tag'       => 'wpcf7-f' . $synthetic_form_id . '-p0-o1',
	'your-name'             => 'Synthetic sender',
	'your-email'            => 'cf7-native@example.invalid',
	'your-message'          => 'valid' === $case ? 'Ordinary synthetic message.' : 'rktsky-synthetic-spam',
	'internal'              => 'private-hidden',
	'rktsky_antispam_token' => $proof['token'],
	$proof['trap']          => '',
);
if ( in_array( $case, array( 'missing', 'provider-spam-missing' ), true ) ) {
	unset( $_POST['rktsky_antispam_token'] );
}
if ( in_array( $case, array( 'replay', 'provider-spam-replay' ), true ) ) {
	$verified = ( new Token() )->verify( $proof['token'], 'cf7', (string) $synthetic_form_id );
	rktsky_cf7_expect( ( new Rktsky\Antispam\Store() )->claim( $verified['digest'], $verified['expires'] ), 'Cannot reserve synthetic replay proof.' );
}
wp_set_current_user( 0 );
$result = $form->submit( array( 'skip_mail' => true ) );
wp_set_current_user( 1 );
$after_ids           = get_posts(
	array(
		'post_type'   => 'flamingo_inbound',
		'post_status' => array( 'publish', 'flamingo-spam', 'trash' ),
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
$new_ids             = array_values( array_diff( $after_ids, $before_ids ) );
$native_expected     = in_array( $case, array( 'valid', 'keyword', 'no-retention', 'provider-spam' ), true );
$quarantine_expected = in_array( $case, array( 'missing', 'replay', 'no-inbox', 'provider-spam-missing', 'provider-spam-replay', 'native-save-off' ), true );
try {
	rktsky_cf7_expect( ( 'valid' === $case ? 'mail_sent' : 'spam' ) === $result['status'], 'Unexpected native public verdict.' );
	rktsky_cf7_expect( count( $new_ids ) === (int) $native_expected, 'Native spam destination or technical rejection boundary differs.' );
	rktsky_cf7_expect(
		$quarantine->listing()['total'] - $before_quarantine === (int) $quarantine_expected,
		'Duplicate quarantine copy or missing configured fallback: ' . wp_json_encode(
			array(
				'before'       => $before_quarantine,
				'after'        => $quarantine->listing()['total'],
				'enabled'      => $quarantine->enabled(),
				'do_not_store' => WPCF7_Submission::get_instance()->get_meta( 'do_not_store' ),
				'spam_log'     => WPCF7_Submission::get_instance()->get_spam_log(),
			)
		)
	);
	rktsky_cf7_expect( 0 === $mail_attempts, 'A rejected submission attempted email delivery.' );
	wp_set_current_user( 1 );
	$route = ( new SpamHandling( $settings, $quarantine ) )->describe( 'cf7' );
	rktsky_cf7_expect( str_starts_with( $case, 'no-inbox' ) ? 'quarantine' === $route['mode'] : in_array( $route['mode'], array( 'native', 'conditional' ), true ), 'The displayed inbox differs from the active saver.' );
	foreach ( $new_ids as $entry_id ) {
		$entry    = new Flamingo_Inbound_Message( $entry_id );
		$feedback = new Feedback();
		rktsky_cf7_expect( 'valid' === $case ? 'publish' === get_post_status( $entry_id ) : 'flamingo-spam' === get_post_status( $entry_id ), 'Native record did not keep its spam status.' );
		rktsky_cf7_expect( ! isset( $entry->fields['rktsky_antispam_token'] ), 'Native inbox retained the proof token.' );
		$state = $feedback->status( 'cf7', $entry_id );
		rktsky_cf7_expect( is_array( $state ) && $state['predicted_spam'] === ( 'valid' !== $case ), 'Native prediction was not tracked.' );
		if ( 'keyword' === $case ) {
			$entry->unspam();
			rktsky_cf7_expect( false === $feedback->status( 'cf7', $entry_id )['actual_spam'], 'Native Not spam action did not correct the prediction.' );
			$entry = new Flamingo_Inbound_Message( $entry_id );
			$entry->spam();
			rktsky_cf7_expect( true === $feedback->status( 'cf7', $entry_id )['actual_spam'], 'Native Spam action did not correct the human label.' );
			$export = $feedback->export( 'cf7-native@example.invalid' );
			rktsky_cf7_expect( count( $export['data'] ) > 0, 'Confirmed privacy export missed the native sender.' );
			$erase = $feedback->erase( 'cf7-native@example.invalid' );
			rktsky_cf7_expect( $erase['items_removed'] && null === $feedback->status( 'cf7', $entry_id ), 'Privacy erasure retained a source link.' );
		} elseif ( 'valid' === $case ) {
			$entry = new Flamingo_Inbound_Message( $entry_id );
			$entry->spam();
			rktsky_cf7_expect( true === $feedback->status( 'cf7', $entry_id )['actual_spam'], 'Native missed-spam marking was not observed.' );
			update_post_meta( $entry_id, '_from_email', 'changed@example.invalid' );
			rktsky_cf7_expect( null === $feedback->status( 'cf7', $entry_id ), 'Native source editing retained an unrelated prediction.' );
		}
		$entry->delete();
		rktsky_cf7_expect( null === $feedback->status( 'cf7', $entry_id ), 'Deleted native message retained a feedback reference.' );
	}
} finally {
	wp_set_current_user( 1 );
	update_option( Settings::OPTION, $case_original, false );
	wp_delete_post( $synthetic_form_id, true );
	foreach ( $new_ids as $entry_id ) {
		wp_delete_post( $entry_id, true );
	}
	foreach ( $quarantine->listing()['items'] ?? array() as $record ) {
		if ( (string) $synthetic_form_id === (string) $record['form_id'] ) {
			$quarantine->delete( (int) $record['id'] );
		}
	}
}
echo 'PASS: CF7 ' . $case . " storage, feedback and side-effect boundaries.\n";
