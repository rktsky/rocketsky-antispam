<?php
/**
 * Tests WPForms Pro's unchanged native processing, spam queue and error boundary.
 *
 * Requires a marked disposable site with the supplied Pro version installed.
 * Fresh PHP children retain native field validation, storage and notifications.
 * Actual mail and HTTP must be blocked by the fixture. Only the six approved
 * owned tables use prepared snapshot SQL; provider data uses native APIs.
 *
 * @package RktskyAntispamTests
 * @since 0.5.0
 */

use Rktsky\Antispam\{Settings, Token, Store, Quarantine, Feedback};
use Rktsky\Antispam\Integrations\WpForms;

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
set_exception_handler( static function ( Throwable $error ): void { fwrite( STDERR, $error->getMessage() . "\n" ); exit( 1 ); } );
if ( ! function_exists( 'wpforms' ) || ! wpforms()->is_pro() ) { echo "SKIP: native WPForms Pro requires its separately supplied package.\n"; return; }
$GLOBALS['rktsky_wpf_assertions'] = 0;

/**
 * Stops on an observed native invariant without printing submitted values.
 *
 * @since 0.5.0
 * @param bool $condition Required native behavior.
 * @param string $message Non-personal failure context.
 * @return void
 */
function rktsky_wpf_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_wpf_assertions'];
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}

/**
 * Captures a bounded snapshot of an explicitly approved owned table.
 *
 * @since 0.5.0
 * @param string $suffix Fixed owned table name.
 * @return array Original rows.
 */
function rktsky_wpf_snapshot( string $suffix ): array {
	global $wpdb;
	if ( ! in_array( $suffix, array( 'tokens', 'stats', 'totals', 'quarantine', 'feedback', 'jobs' ), true ) ) { throw new RuntimeException( 'Unknown owned table.' ); }
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i LIMIT 10001', $wpdb->prefix . 'rktsky_antispam_' . $suffix ), ARRAY_A );
	if ( ! is_array( $rows ) || count( $rows ) > 10000 ) { throw new RuntimeException( 'The owned snapshot exceeds its bound.' ); }
	return $rows;
}

/**
 * Restores only an approved owned table from its bounded snapshot.
 *
 * @since 0.5.0
 * @param string $suffix Fixed table name.
 * @param array $rows Previously captured rows.
 * @return void
 */
function rktsky_wpf_restore( string $suffix, array $rows ): void {
	global $wpdb;
	if ( ! in_array( $suffix, array( 'tokens', 'stats', 'totals', 'quarantine', 'feedback', 'jobs' ), true ) ) { throw new RuntimeException( 'Unknown owned table.' ); }
	$table = $wpdb->prefix . 'rktsky_antispam_' . $suffix;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	foreach ( $rows as $row ) { if ( false === $wpdb->insert( $table, $row ) ) { throw new RuntimeException( 'The owned fixture snapshot could not be restored.' ); } }
}

$case = $argv[1] ?? '';
$original = get_option( Settings::OPTION, null );
$schema = get_option( 'rktsky_antispam_schema', null );
$snapshots = array();
try {
	foreach ( array( 'tokens', 'stats', 'totals', 'quarantine', 'feedback', 'jobs' ) as $suffix ) { $snapshots[ $suffix ] = rktsky_wpf_snapshot( $suffix ); }
	if ( '' === $case ) {
		$options = ( new Settings() )->all(); $options['wpforms'] = true; update_option( Settings::OPTION, $options, false );
		$cases = array( 'valid', 'keyword', 'bbcode', 'pattern', 'language', 'country', 'keyword_q', 'missing', 'trap', 'wrong_scope', 'replay', 'storage', 'pattern_limit', 'technical_q', 'store_off', 'store_off_q', 'disabled_entries', 'disabled_entries_zero', 'native_error', 'native_spam', 'storage_filter', 'storage_filter_q', 'same_request', 'unspam', 'stripe', 'square', 'sensitive', 'feedback' );
		foreach ( $cases as $name ) {
			$command = array( PHP_BINARY );
			foreach ( array( 'WP_ANTISPAM_PHP_ICONV', 'WP_ANTISPAM_PHP_SIMPLEXML' ) as $key ) { if ( getenv( $key ) ) { $command[] = '-d'; $command[] = 'extension=' . getenv( $key ); } }
			$command[] = __FILE__; $command[] = $name;
			$pipes = array(); $process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
			fclose( $pipes[0] ); $output = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] ); $status = proc_close( $process );
			if ( 0 !== $status || ! str_contains( $output, 'PASS: ' . $name ) ) { throw new RuntimeException( 'Native WPForms Pro case failed: ' . $name . "\n" . $output . "\n" . $error ); }
			echo trim( $output ) . "\n";
		}
		echo 'PASS: ' . count( $cases ) . " native WPForms Pro cases.\n";
		return;
	}
	$options = ( new Settings() )->all();
	$options['wpforms'] = true; $options['minimum_seconds'] = 0; $options['spam_keywords'] = ''; $options['spam_patterns'] = ''; $options['bbcode_filter'] = false; $options['allowed_languages'] = array(); $options['country_mode'] = 'off';
	$options['privacy_mode'] = false; $options['quarantine_enabled'] = str_ends_with( $case, '_q' ) || str_starts_with( $case, 'disabled_' );
	$text = 'An ordinary synthetic message about the local fixture.';
	$content = ! in_array( $case, array( 'valid', 'missing', 'trap', 'wrong_scope', 'replay', 'storage', 'technical_q', 'native_spam', 'language', 'country' ), true );
	if ( $content ) { $text = 'Synthetic routing-keyword content.'; $options['spam_keywords'] = 'routing-keyword'; }
	if ( 'bbcode' === $case ) { $options['spam_keywords'] = ''; $options['bbcode_filter'] = true; $text = '[url=https://example.invalid]Synthetic[/url]'; }
	if ( 'pattern' === $case ) { $options['spam_keywords'] = ''; $options['spam_patterns'] = 'routing-[a-z]+'; }
	if ( 'pattern_limit' === $case ) { $options['spam_keywords'] = ''; $options['spam_patterns'] = '(a+)+$'; $text = str_repeat( 'a', 10000 ) . '!'; }
	if ( 'language' === $case ) { $options['spam_keywords'] = ''; $options['allowed_languages'] = array( 'de' ); $options['unknown_language_allowed'] = false; $text = str_repeat( 'The people are working together with their friends because they want to build a useful website and share their information. ', 15 ); }
	if ( 'country' === $case ) { $options['spam_keywords'] = ''; $options['country_mode'] = 'block'; $options['countries'] = array( 'CH' ); $options['country_source'] = 'trusted_header'; $options['country_header'] = 'HTTP_CF_IPCOUNTRY'; $options['trusted_proxies'] = '127.0.0.1/32'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_CF_IPCOUNTRY'] = 'CH'; }
	update_option( Settings::OPTION, $options, false );
	wp_set_current_user( 1 );
	$form_settings = array( 'form_title' => 'Synthetic WPForms native routing', 'submit_text' => 'Submit synthetic', 'store_spam_entries' => '1', 'notification_enable' => '1', 'notifications' => array( 'synthetic' => array( 'email' => 'fixture@example.invalid', 'subject' => 'Synthetic routing', 'sender_name' => 'Fixture', 'sender_address' => 'fixture@example.invalid', 'message' => 'Synthetic notification.' ) ), 'confirmations' => array( 'synthetic' => array( 'type' => 'message', 'message' => 'Synthetic WPForms accepted.' ) ), 'disable_ip' => '1', 'antispam_v3' => 'native_spam' === $case ? '1' : '0' );
	if ( str_starts_with( $case, 'store_off' ) ) { $form_settings['store_spam_entries'] = '0'; }
	if ( str_starts_with( $case, 'disabled_entries' ) ) { $form_settings['disable_entries'] = 'disabled_entries_zero' === $case ? '0' : '1'; }
	$definition = array( 'settings' => $form_settings, 'fields' => array( 1 => array( 'id' => '1', 'type' => 'text', 'label' => 'Message', 'required' => '1' ), 2 => array( 'id' => '2', 'type' => 'email', 'label' => 'Email' ), 3 => array( 'id' => '3', 'type' => 'hidden', 'label' => 'Hidden' ), 4 => array( 'id' => '4', 'type' => 'password', 'label' => 'Password', 'confirmation' => '0' ) ) );
	$form = (int) wpforms()->obj( 'form' )->add( 'Synthetic WPForms native routing', array( 'post_content' => wpforms_encode( $definition ) ) );
	rktsky_wpf_expect( $form > 0, 'The native form API must create the owned synthetic form.' );
	$definition['id'] = $form; wpforms()->obj( 'form' )->update( $form, $definition );
	$form_data = wpforms_decode( get_post_field( 'post_content', $form ) );
	rktsky_wpf_expect( WpForms::native_available( $form_data ) === ( ! str_starts_with( $case, 'store_off' ) && ! str_starts_with( $case, 'disabled_entries' ) ), 'Storage capability must match each original form option, including a zero disable_entries key.' );
	ob_start(); wpforms_display( $form, false, false ); $html = (string) ob_get_clean();
	$document = new DOMDocument(); $previous = libxml_use_internal_errors( true ); $document->loadHTML( $html ); libxml_clear_errors(); libxml_use_internal_errors( $previous );
	$entry = array( 'id' => (string) $form, 'fields' => array( 1 => $text, 2 => 'fixture@example.invalid', 3 => 'DO-NOT-RETAIN-HIDDEN', 4 => 'DO-NOT-RETAIN-PASSWORD' ) );
	foreach ( $document->getElementsByTagName( 'input' ) as $input ) {
		if ( preg_match( '/^wpforms\[([a-z_]+)\]$/D', $input->getAttribute( 'name' ), $match ) ) { $entry[ $match[1] ] = $input->getAttribute( 'value' ); }
	}
	$proof = ( new Token() )->issue( 'wpforms', (string) $form . ( 'wrong_scope' === $case ? '_wrong' : '' ) );
	$controls = in_array( $case, array( 'missing', 'technical_q' ), true ) ? array() : array( 'rktsky_antispam_token' => $proof['token'], $proof['trap'] => 'trap' === $case ? 'synthetic-bot' : '' );
	$mail = 0; $complete = 0; $saved = 0; $payments = 0; $http = 0; $payment_callbacks = 0;
	add_filter( 'pre_wp_mail', static function ( $previous ) use ( &$mail ) { ++$mail; return $previous; }, -PHP_INT_MAX );
	add_filter( 'pre_http_request', static function ( $previous ) use ( &$http ) { ++$http; return $previous; }, -PHP_INT_MAX );
	add_action( 'wpforms_process_complete', static function () use ( &$complete ) { ++$complete; } );
	add_action( 'wpforms_process_entry_saved', static function () use ( &$saved ) { ++$saved; } );
	add_action( 'wpforms_process_payment_saved', static function () use ( &$payments ) { ++$payments; } );
	if ( 'native_error' === $case ) { add_action( 'wpforms_process', static function () use ( $form ) { wpforms()->process->errors[ $form ]['header'] = 'Synthetic preceding native error.'; }, 1 ); }
	if ( str_starts_with( $case, 'storage_filter' ) ) { add_filter( 'wpforms_entry_save', '__return_false' ); }
	if ( 'native_spam' === $case ) { $entry['fields'][9999] = 'synthetic-native-honeypot'; }
	if ( in_array( $case, array( 'stripe', 'square' ), true ) ) {
		// Invoke the unmodified original gateway callback later on the real native
		// action with an enabled payment definition. No gateway credentials exist.
		$gateway = 'stripe' === $case ? new \WPForms\Integrations\Stripe\Process() : new \WPForms\Integrations\Square\Process();
		add_action( 'wpforms_process', static function ( $fields, $raw, $native ) use ( $gateway, $case, &$payment_callbacks ) { ++$payment_callbacks; $native['payments'][ $case ] = array( 'enable' => '1' ); $gateway->process_entry( $fields, $raw, $native ); }, 10, 3 );
	}
	$store = new Store(); $before = $store->summary(); $quarantine = new Quarantine( new Settings() ); $before_q = $quarantine->count();
	$submit = static function ( array $evidence ) use ( &$entry ): void { wp_set_current_user( 0 ); $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = $evidence + array( 'wpforms' => $entry ); wpforms()->process->listen(); };
	if ( 'replay' === $case ) { $submit( $controls ); $before = $store->summary(); $mail = $complete = $saved = $payments = 0; }
	$tokens_snapshot = null;
	if ( 'storage' === $case ) { $tokens_snapshot = rktsky_wpf_snapshot( 'tokens' ); $wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $wpdb->prefix . 'rktsky_antispam_tokens' ) ); }
	try {
		$submit( $controls );
		wp_set_current_user( 1 );
		$entries = wpforms()->obj( 'entry' )->get_entries( array( 'form_id' => $form, 'status' => 'any', 'number' => 100 ) );
		$technical = in_array( $case, array( 'missing', 'trap', 'wrong_scope', 'replay', 'storage', 'technical_q', 'pattern_limit' ), true );
		$native_store = $content && ! $technical && ! in_array( $case, array( 'store_off', 'store_off_q', 'disabled_entries', 'disabled_entries_zero', 'native_error', 'storage_filter', 'storage_filter_q' ), true );
		if ( 'native_spam' === $case ) { $native_store = true; }
		$ordinary = in_array( $case, array( 'valid', 'language', 'country' ), true );
		$expected = $ordinary || 'replay' === $case || $native_store ? 1 : 0;
		rktsky_wpf_expect( count( $entries ) === $expected, 'The native entry count must match the selected spam/technical/storage route.' );
		if ( $native_store ) {
			$record = $entries[0];
			rktsky_wpf_expect( 'spam' === $record->status, 'The original saver must persist native spam status.' );
			$fields_json = (string) $record->fields;
			rktsky_wpf_expect( ( 'native_spam' === $case || ! str_contains( $fields_json, 'DO-NOT-RETAIN' ) ) && ! str_contains( $fields_json, 'rktsky_antispam' ) && ! str_contains( $fields_json, 'rktsky_as_' ), 'Native policy spam must exclude hidden/password/protection contents.' );
			$meta = wpforms()->obj( 'entry_meta' )->get_meta( array( 'entry_id' => $record->entry_id, 'type' => 'spam' ) );
			rktsky_wpf_expect( ! empty( $meta ) && ( 'native_spam' === $case || 'WP Antispam' === $meta[0]->data ), 'The native spam detail must retain the fixed protection reason.' );
			if ( 'native_spam' !== $case ) { $raw = wpforms()->obj( 'entry_meta' )->get_meta( array( 'entry_id' => $record->entry_id, 'type' => 'post_data_raw' ) ); rktsky_wpf_expect( '[]' === ( $raw[0]->data ?? '' ), 'The native spam workflow must receive an empty raw-data collection.' ); }
			if ( 'unspam' === $case ) { $moderated = 0; add_action( 'wpforms_pro_anti_spam_entry_set_as_not_spam', static function () use ( &$moderated ) { ++$moderated; } ); wpforms()->obj( 'spam_entry' )->set_as_not_spam( $record ); $changed = wpforms()->obj( 'entry' )->get( $record->entry_id ); rktsky_wpf_expect( '' === $changed->status && 1 === $moderated, 'Original moderation must restore normal status and emit its documented native moderation event.' ); }
		}
		if ( $ordinary ) { rktsky_wpf_expect( 1 === $complete && 1 === $saved && $mail > 0 && empty( wpforms()->process->errors[ $form ] ), 'The original ordinary workflow must still save, notify and complete.' ); }
		elseif ( 'native_spam' !== $case ) { rktsky_wpf_expect( 0 === $mail && 0 === $complete && 0 === $saved && 0 === $payments, 'A rejection must not run native notifications, completion, entry-saved feeds or payment saving.' ); rktsky_wpf_expect( ! empty( wpforms()->process->errors[ $form ]['header'] ), 'The native frontend must receive a visible rejection header.' ); ob_start(); wpforms_display( $form, false, false ); $rejected_html = (string) ob_get_clean(); rktsky_wpf_expect( str_contains( $rejected_html, esc_html( wpforms()->process->errors[ $form ]['header'] ) ) && ! str_contains( $rejected_html, 'Synthetic WPForms accepted.' ), 'The original frontend must render the rejection and suppress its success confirmation.' ); }
		if ( in_array( $case, array( 'stripe', 'square' ), true ) ) { rktsky_wpf_expect( 1 === $payment_callbacks && $gateway->form_id === $form && 0 === $http, 'The actual original gateway callback must respect the earlier error before making an API request.' ); }
		if ( 'same_request' === $case ) { $submit( $controls ); wp_set_current_user( 1 ); rktsky_wpf_expect( 1 === wpforms()->obj( 'entry' )->get_entries( array( 'form_id' => $form, 'status' => 'any' ), true ), 'A second native process in one request must not reuse a consumed proof to create another entry.' ); }
		$after = $store->summary();
		if ( ! $ordinary && 'same_request' !== $case ) { rktsky_wpf_expect( 1 === $after['blocked'] - $before['blocked'] && 0 === $after['accepted'] - $before['accepted'], 'Spam/technical decisions must count once with no accepted counter.' ); }
		$expected_q = in_array( $case, array( 'technical_q', 'store_off_q' ), true ) ? 1 : 0;
		rktsky_wpf_expect( $quarantine->count() === $before_q + $expected_q, 'Native persisted spam must have no duplicate quarantine; explicit storage bans must prevent either content copy: ' . wp_json_encode( array( 'before' => $before_q, 'after' => $quarantine->count(), 'expected' => $expected_q, 'enabled' => $quarantine->enabled() ) ) );

		if ( 'feedback' === $case ) {
			$feedback = new Feedback(); $first = (int) $entries[0]->entry_id;
			$initial = $feedback->status( 'wpforms', $first );
			rktsky_wpf_expect( is_array( $initial ) && $initial['predicted_spam'] && ! $initial['reviewed'], 'Actual observed spam must create one pending native prediction.' );
			wpforms()->obj( 'spam_entry' )->set_as_not_spam( $entries[0] );
			$reviewed = $feedback->status( 'wpforms', $first );
			rktsky_wpf_expect( $reviewed['reviewed'] && false === $reviewed['actual_spam'], 'Original native Unspam must label the observed spam as a false positive.' );
			wpforms()->obj( 'spam_entry' )->set_as_spam( $first, $form, 'Synthetic native moderation.' );
			rktsky_wpf_expect( true === $feedback->status( 'wpforms', $first )['actual_spam'], 'Original native Spam must update that same reviewed prediction.' );
			$subscriber = wp_insert_user( array( 'user_login' => 'wpf-review-' . wp_generate_password( 10, false, false ), 'user_pass' => wp_generate_password( 32, true ), 'role' => 'subscriber', 'user_email' => 'review-' . wp_generate_password( 10, false, false ) . '@example.invalid' ) );
			try {
				wp_set_current_user( $subscriber ); wpforms()->obj( 'spam_entry' )->set_as_not_spam( $entries[0] );
				rktsky_wpf_expect( is_wp_error( $feedback->export( 'fixture@example.invalid' ) ) && is_wp_error( $feedback->erase( 'fixture@example.invalid' ) ), 'An unprivileged native actor must not run feedback privacy tools.' );
				wp_set_current_user( 1 ); rktsky_wpf_expect( true === $feedback->status( 'wpforms', $first )['actual_spam'], 'A denied native update that still emits a hook must not relabel feedback.' );
			} finally { wp_set_current_user( 1 ); wp_delete_user( $subscriber ); }
			$stored = wpforms()->obj( 'entry' )->get( $first );
			wpforms()->obj( 'entry' )->update( $first, array( 'fields' => $stored->fields ), '', 'edit_entry' );
			rktsky_wpf_expect( null !== $feedback->status( 'wpforms', $first ), 'An unchanged native editor save must preserve the existing observation.' );
			$changed = json_decode( $stored->fields, true ); $changed[1]['value'] = 'Edited synthetic content.';
			wpforms()->obj( 'entry' )->update( $first, array( 'fields' => wp_json_encode( $changed ) ), '', 'edit_entry' );
			rktsky_wpf_expect( null === $feedback->status( 'wpforms', $first ), 'A real native editor content change must detach its earlier prediction.' );
			/** Executes the original provider listener with fresh site-local proof. */
			$spawn = static function ( string $message, string $email = 'fixture@example.invalid' ) use ( &$entry, $submit, $form ): int {
				$entry['fields'][1] = $message; $entry['fields'][2] = $email;
				$challenge = ( new Token() )->issue( 'wpforms', (string) $form );
				$previous_id = absint( wpforms()->process->entry_id );
				$submit( array( 'rktsky_antispam_token' => $challenge['token'], $challenge['trap'] => '' ) ); wp_set_current_user( 1 );
				$id = absint( wpforms()->process->entry_id ); rktsky_wpf_expect( $id > 0 && $id !== $previous_id, 'Each native feedback fixture must persist through a fresh real protected submission.' );
				return $id;
			};
			$generic = $spawn( 'Synthetic routing-keyword generic edit.' ); $stored = wpforms()->obj( 'entry' )->get( $generic ); $changed = json_decode( $stored->fields, true ); $changed[1]['value'] = 'Generic edited value.';
			wpforms()->obj( 'entry' )->update( $generic, array( 'fields' => wp_json_encode( $changed ) ) );
			rktsky_wpf_expect( null === $feedback->status( 'wpforms', $generic ), 'The original default-context fields API must detach changed content as well.' );
			$background = $spawn( 'Synthetic routing-keyword background privacy.' ); $stored = wpforms()->obj( 'entry' )->get( $background ); $changed = json_decode( $stored->fields, true ); $changed[1]['value'] = 'Anonymized synthetic content.';
			// Native background privacy may explicitly bypass an actor capability;
			// its accepted column names are normalized after the native prehook.
			wp_set_current_user( 0 ); wpforms()->obj( 'entry' )->update( $background, array( 'FIELDS' => wp_json_encode( $changed ) ), '', 'edit_entry', array( 'cap' => false ) ); wp_set_current_user( 1 );
			rktsky_wpf_expect( null === $feedback->status( 'wpforms', $background ), 'A permitted native background fields update with a normalized uppercase column must detach its previous observation.' );
			$deleted = $spawn( 'Synthetic routing-keyword delete.' ); wpforms()->obj( 'entry' )->delete( $deleted );
			rktsky_wpf_expect( null === $feedback->status( 'wpforms', $deleted ), 'Original exact-entry deletion must remove its pending feedback link.' );
			$subject = 'privacy-' . wp_generate_password( 12, false, false ) . '@example.invalid';
			$foreign = $spawn( $subject, 'other@example.invalid' ); $foreign_export = $feedback->export( $subject );
			rktsky_wpf_expect( empty( $foreign_export['data'] ) && $foreign_export['done'], 'An email merely mentioned in a text field must not identify that feedback subject.' );
			// Fifty further approximate text matches force the real email match
			// onto a second native page; no historic entries are imported.
			for ( $index = 0; $index < 50; ++$index ) { $spawn( $subject, 'other@example.invalid' ); }
			$matched = $spawn( 'Synthetic routing-keyword privacy subject.', $subject );
			$disabled = ( new Settings() )->all(); $disabled['wpforms'] = false; update_option( Settings::OPTION, $disabled, false );
			$page_one = $feedback->export( $subject, 1 ); $page_two = $feedback->export( $subject, 2 );
			rktsky_wpf_expect( empty( $page_one['data'] ) && ! $page_one['done'], 'A sparse page of fifty text-only candidates must keep native privacy pagination open.' );
			rktsky_wpf_expect( 1 === count( $page_two['data'] ) && $page_two['done'], 'Confirmed email-field identity on the second page must export even while the adapter is disabled.' );
			$erased_one = $feedback->erase( $subject, 1 ); $erased_two = $feedback->erase( $subject, 2 );
			rktsky_wpf_expect( ! $erased_one['done'] && $erased_two['done'] && $erased_two['items_removed'] && null === $feedback->status( 'wpforms', $matched ), 'Native subject erasure must detach the real email match across sparse pages.' );
			rktsky_wpf_expect( null !== $feedback->status( 'wpforms', $foreign ) && is_object( wpforms()->obj( 'entry' )->get( $matched ) ), 'Feedback privacy must leave text-only identities and the native provider entry intact.' );
			wp_delete_post( $form, true );
			rktsky_wpf_expect( null === $feedback->status( 'wpforms', $foreign ), 'Native form deletion must remove remaining pending links by form-owned entry IDs.' );
		}
		echo 'PASS: ' . $case . ' (' . $GLOBALS['rktsky_wpf_assertions'] . " native assertions)\n";
	} finally {
		if ( null !== $tokens_snapshot ) { ( new Store() )->install(); rktsky_wpf_restore( 'tokens', $tokens_snapshot ); }
		wp_set_current_user( 1 );
		foreach ( wpforms()->obj( 'entry' )->get_entries( array( 'form_id' => $form, 'status' => 'any', 'number' => 100 ) ) as $record ) { wpforms()->obj( 'entry' )->delete( $record->entry_id ); }
		wp_delete_post( $form, true );
	}
} finally {
	foreach ( $snapshots as $suffix => $rows ) { rktsky_wpf_restore( $suffix, $rows ); }
	foreach ( array( Settings::OPTION => $original, 'rktsky_antispam_schema' => $schema ) as $key => $value ) { if ( null === $value ) { delete_option( $key ); } else { update_option( $key, $value, false ); } }
}
