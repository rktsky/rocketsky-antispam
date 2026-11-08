<?php
/**
 * Exercises local policy, retention, digest, and dashboard contracts on native WordPress.
 *
 * @package RktskyAntispamTests
 * @since 0.2.0
 */

use Rktsky\Antispam\ContentRules;
use Rktsky\Antispam\Country;
use Rktsky\Antispam\Dashboard;
use Rktsky\Antispam\Language;
use Rktsky\Antispam\Maintenance;
use Rktsky\Antispam\Settings;
use Rktsky\Antispam\Store;

require __DIR__ . '/bootstrap.php';
$rktsky_policy_assertions = 0;
$rktsky_policy_mail = array();
$rktsky_policy_http = 0;

/**
 * Records a policy invariant without outputting synthetic personal data.
 *
 * @since 0.2.0
 * @param bool $condition Required behavior.
 * @param string $message Failure label.
 * @return void
 */
function rktsky_antispam_policy_expect( bool $condition, string $message ): void {
	global $rktsky_policy_assertions;
	++$rktsky_policy_assertions;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Captures native aggregate mail arguments without ever invoking a real mailer.
 *
 * @since 0.2.0
 * @param mixed $previous Earlier mail interception decision.
 * @param array $attributes Native wp_mail arguments.
 * @return bool False; the disposable fixture remains unable to send messages.
 */
function rktsky_antispam_policy_mail( $previous, array $attributes ): bool {
	global $rktsky_policy_mail;
	$rktsky_policy_mail[] = $attributes;
	return false;
}

/**
 * Records an attempted WordPress HTTP call while preserving the fixture block.
 *
 * @since 0.2.0
 * @param mixed $previous Native HTTP interception decision.
 * @return mixed Unchanged result so no request authorization is added.
 */
function rktsky_antispam_policy_http( $previous ) {
	global $rktsky_policy_http;
	++$rktsky_policy_http;
	return $previous;
}

global $wpdb;
$settings = new Settings();
$original = $settings->all();
$server = $_SERVER;
$store = new Store();
$store->install();
$store->clear();
$job_key = 'digest_' . gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
$job_before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE job=%s', $wpdb->prefix . 'rktsky_antispam_jobs', $job_key ), ARRAY_A );
$wpdb->delete( $wpdb->prefix . 'rktsky_antispam_jobs', array( 'job' => $job_key ) );
$option_names = array( 'rktsky_antispam_digest_claim', 'rktsky_antispam_digest_status' );
$original_digest_options = array();
foreach ( $option_names as $name ) {
	$original_digest_options[ $name ] = get_option( $name, false );
	delete_option( $name );
}
$post_id = 0;
$comments = array();
$mail_directory = '';
$old_mail_directory = getenv( 'RKTSKY_ANTISPAM_MAIL_DIR' );
add_filter( 'pre_wp_mail', 'rktsky_antispam_policy_mail', PHP_INT_MAX, 2 );
add_filter( 'pre_http_request', 'rktsky_antispam_policy_http', PHP_INT_MAX, 1 );

try {
	update_option( Settings::OPTION, $settings->defaults(), false );
	$strict = $settings->sanitize( array( 'privacy_mode' => '1', 'quarantine_enabled' => '1', 'notify_admin' => '1', 'country_mode' => 'block', 'countries' => 'CH,US' ) );
	rktsky_antispam_policy_expect( $strict['privacy_mode'] && ! $strict['quarantine_enabled'] && ! $strict['notify_admin'] && 'off' === $strict['country_mode'], 'Server-side strict privacy must override forged optional-processing settings.' );
	$base = $settings->defaults();
	$base['spam_keywords'] = "synthetic-blocked-word\nsecond phrase";
	$base['allowed_languages'] = array( 'de' );
	$base['unknown_language_allowed'] = true;
	update_option( Settings::OPTION, $base, false );
	$merged = $settings->sanitize( array( 'minimum_seconds' => '2', 'cf7' => '0' ) );
	rktsky_antispam_policy_expect( 2 === $merged['minimum_seconds'] && ! $merged['cf7'] && $base['spam_keywords'] === $merged['spam_keywords'] && array( 'de' ) === $merged['allowed_languages'], 'Saving one tab must preserve omitted rule values and honor explicit checkbox zeroes.' );
	$malformed = $settings->sanitize( array( 'trusted_proxies' => "203.0.113.0/24\n2001:db8::/32\nexample.invalid\n1.2.3.4/99", 'countries' => 'ch, us; 123 <script>', 'spam_patterns' => "[\nvalid.+rule\n~invalid~", 'allowed_languages' => array( 'de', array( 'en' ), 'unknown' ), 'quarantine_days' => '999', 'comment_spam_days' => '999' ) );
	rktsky_antispam_policy_expect( "203.0.113.0/24\n2001:db8::/32" === $malformed['trusted_proxies'] && array( 'CH', 'US' ) === $malformed['countries'] && 'valid.+rule' === $malformed['spam_patterns'] && array( 'de' ) === $malformed['allowed_languages'], 'Policy normalization must discard malformed addresses, patterns, and language shapes.' );
	rktsky_antispam_policy_expect( 30 === $malformed['quarantine_days'] && 90 === $malformed['comment_spam_days'], 'Optional retention periods must be bounded on the server.' );

	$de = 'Wir haben diese Hinweise gelesen und ich finde die Beschreibung sehr klar. Diese Erklärung ist für unsere Arbeit hilfreich, aber wir können auch einen anderen Weg wählen.';
	$en = 'The explanation is clear and we have used these details with our team. This approach should work because the documentation describes what happens when the form is submitted.';
	$language = new Language();
	rktsky_antispam_policy_expect( 'de' === $language->detect( $de ) && 'en' === $language->detect( $en ), 'Distinct German and English prose must receive the expected local language estimate.' );
	rktsky_antispam_policy_expect( '' === $language->detect( 'Danke!' ) && '' === $language->detect( str_repeat( 'xyz ', 40 ) ), 'Short and unrecognized text must remain unknown.' );
	$rules = new ContentRules( $settings, $language, new Country( $settings ) );
	rktsky_antispam_policy_expect( '' === $rules->check( 'comments', array( 'content' => $de, 'author' => $en ) ) && 'language' === $rules->check( 'comments', array( 'content' => $en ) ), 'Only comment body language may drive the configured language rule.' );
	rktsky_antispam_policy_expect( '' === $rules->check( 'comments', array( 'content' => 'Danke!' ) ) && '' === $rules->check( 'cf7', array( 'content' => $en ) ), 'Allowed unknown comments and contact forms must retain their separate language contracts.' );
	$deny_unknown = $base;
	$deny_unknown['unknown_language_allowed'] = false;
	update_option( Settings::OPTION, $deny_unknown, false );
	rktsky_antispam_policy_expect( 'language' === $rules->check( 'comments', array( 'content' => 'Danke!' ) ), 'An explicit unknown-language rejection policy must be enforced.' );

	$country_settings = $settings->defaults();
	$country_settings['privacy_mode'] = false;
	$country_settings['country_mode'] = 'allow';
	$country_settings['countries'] = array( 'CH' );
	$country_settings['country_source'] = 'trusted_header';
	$country_settings['country_header'] = 'HTTP_CF_IPCOUNTRY';
	$country_settings['trusted_proxies'] = "203.0.113.0/24\n2001:db8::/33";
	update_option( Settings::OPTION, $country_settings, false );
	$country = new Country( $settings );
	$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
	$_SERVER['HTTP_CF_IPCOUNTRY'] = 'CH';
	$_SERVER['HTTP_X_COUNTRY_CODE'] = 'US';
	rktsky_antispam_policy_expect( 'CH' === $country->detect(), 'An explicitly trusted IPv4 peer may supply only its configured country header.' );
	$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';
	rktsky_antispam_policy_expect( '' === $country->detect(), 'Forged forwarded headers from an untrusted direct peer must never establish trust.' );
	$_SERVER['REMOTE_ADDR'] = '2001:db8:4000::1';
	rktsky_antispam_policy_expect( 'CH' === $country->detect(), 'A trusted IPv6 peer inside a partial-byte CIDR must match.' );
	$_SERVER['REMOTE_ADDR'] = '2001:db8:8000::1';
	rktsky_antispam_policy_expect( '' === $country->detect(), 'A peer outside the IPv6 prefix must remain untrusted.' );
	$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
	foreach ( array( 'XX', 'EU', 'T1', 'ch', 'CHE', array( 'CH' ) ) as $unknown_country ) {
		$_SERVER['HTTP_CF_IPCOUNTRY'] = $unknown_country;
		rktsky_antispam_policy_expect( '' === $country->detect(), 'Unknown or malformed infrastructure country data must remain unknown.' );
	}
	foreach ( array( '203.0.113.9', '203.0.113.0/24', '2001:db8::/33', '2001:db8::1/128' ) as $network ) {
		rktsky_antispam_policy_expect( Country::valid_cidr( $network ), 'Valid numeric proxy networks must be accepted.' );
	}
	foreach ( array( 'example.invalid', '203.0.113.0/33', '2001:db8::/129', '203.0.113.0/-1', '203.0.113.0/24/2' ) as $network ) {
		rktsky_antispam_policy_expect( ! Country::valid_cidr( $network ), 'Hostnames and malformed proxy masks must be rejected.' );
	}
	$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
	rktsky_antispam_policy_expect( 'country' === $rules->check( 'comments', array( 'content' => $en ) ) && '' === $rules->check( 'wpforms', array( 'Message' => $en ) ), 'Country policy must apply to comments without leaking into contact forms.' );
	$_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';
	rktsky_antispam_policy_expect( '' === $rules->check( 'comments', array( 'content' => $en ) ), 'Configured allowed unknown countries must remain usable.' );
	$country_settings['country_unknown_allowed'] = false;
	update_option( Settings::OPTION, $country_settings, false );
	rktsky_antispam_policy_expect( 'country' === $rules->check( 'comments', array( 'content' => $en ) ), 'An explicit unknown-country rejection must be enforced.' );
	$country_settings['privacy_mode'] = true;
	update_option( Settings::OPTION, $country_settings, false );
	$_SERVER['HTTP_CF_IPCOUNTRY'] = 'CH';
	rktsky_antispam_policy_expect( '' === $country->detect() && '' === $rules->check( 'comments', array( 'content' => $en ) ), 'Strict privacy must disable country processing before peer metadata is used.' );
	rktsky_antispam_policy_expect( 0 === $rktsky_policy_http, 'Local language and country policy must not initiate WordPress HTTP requests.' );

	$maintenance_settings = $settings->defaults();
	$maintenance_settings['comment_spam_days'] = 7;
	update_option( Settings::OPTION, $maintenance_settings, false );
	$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Synthetic retention fixture' ) );
	$cases = array(
		array( 'spam', 'comment', 20, true ),
		array( 'spam', 'review', 20, true ),
		array( 'spam', 'comment', 2, false ),
		array( 1, 'comment', 20, false ),
		array( 0, 'comment', 20, false ),
		array( 'spam', 'pingback', 20, false ),
		array( 'spam', 'synthetic_ticket', 20, false ),
	);
	foreach ( $cases as $index => $definition ) {
		$date = gmdate( 'Y-m-d H:i:s', time() - $definition[2] * DAY_IN_SECONDS );
		$comments[ $index ] = wp_insert_comment( array( 'comment_post_ID' => $post_id, 'comment_author' => 'Synthetic retention test', 'comment_author_email' => 'fixture@example.invalid', 'comment_content' => 'Synthetic retention fixture only.', 'comment_type' => $definition[1], 'comment_approved' => $definition[0], 'comment_date' => $date, 'comment_date_gmt' => $date ) );
	}
	Maintenance::run();
	foreach ( $cases as $index => $definition ) {
		rktsky_antispam_policy_expect( $definition[3] === ( null === get_comment( $comments[ $index ] ) ), 'Retention must delete only aged spam comments/reviews while preserving recent, approved, pending, pingback, and unrelated types.' );
	}

	$store->clear();
	$old_day = gmdate( 'Y-m-d', time() - 40 * DAY_IN_SECONDS );
	$store->record( 'comments', 'honeypot', $old_day );
	$store->record( 'comments', 'keyword' );
	$store->record( 'cf7', 'honeypot' );
	for ( $index = 0; $index < 3; ++$index ) {
		$store->record( 'comments', '' );
	}
	rktsky_antispam_policy_expect( 2 === $store->blocked_comments(), 'The lifetime counter must count rejected comments without adding passed comments or other providers.' );
	$store->cleanup();
	rktsky_antispam_policy_expect( 2 === $store->blocked_comments() && 2 === $store->summary()['blocked'], 'Expired daily rows must not erase the independent lifetime comment counter.' );
	wp_set_current_user( 0 );
	ob_start();
	Dashboard::render();
	$anonymous_dashboard = ob_get_clean();
	rktsky_antispam_policy_expect( '' === $anonymous_dashboard, 'Dashboard content must be inaccessible without the administrator capability.' );
	wp_set_current_user( 1 );
	ob_start();
	Dashboard::render();
	$dashboard = ob_get_clean();
	$document = new DOMDocument();
	@$document->loadHTML( $dashboard );
	$xpath = new DOMXPath( $document );
	rktsky_antispam_policy_expect( 7 === $xpath->query( '//tbody/tr' )->length && '2' === $xpath->evaluate( 'string(//*[@id="rktsky-widget-stopped"])' ) && '3' === $xpath->evaluate( 'string(//*[@id="rktsky-widget-passed"])' ), 'Dashboard must retain seven UTC-day rows and display actual seven-day stopped/passed totals.' );
	rktsky_antispam_policy_expect( str_contains( $dashboard, number_format_i18n( 40, 1 ) . '%' ), 'The operative rejection rate must derive from the actual two stopped and three passed checks.' );
	rktsky_antispam_policy_expect( '2' === $xpath->evaluate( 'string(//*[@id="rktsky-widget-comments"])' ), 'The lifetime comment counter must remain separate from seven-day activity across all providers.' );
	$store->clear();
	rktsky_antispam_policy_expect( 0 === $store->blocked_comments(), 'Explicit statistics reset must also reset the lifetime comment counter.' );

	$digest_settings = $settings->defaults();
	$digest_settings['privacy_mode'] = false;
	$digest_settings['notify_admin'] = true;
	update_option( Settings::OPTION, $digest_settings, false );
	$yesterday = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
	$store->record( 'comments', 'honeypot', $yesterday );
	$store->record( 'cf7', 'keyword', $yesterday );
	Maintenance::digest();
	rktsky_antispam_policy_expect( 1 === count( $rktsky_policy_mail ) && false === get_option( 'rktsky_antispam_digest_status' )['accepted_by_mailer'], 'A failed native mail handoff must be recorded without reporting success.' );
	$mail = $rktsky_policy_mail[0];
	rktsky_antispam_policy_expect( str_contains( $mail['message'], $yesterday ) && preg_match( '/\b2\b/', $mail['message'] ) && str_contains( $mail['message'], admin_url( 'options-general.php?page=rocketsky-antispam' ) ) && ! str_contains( $mail['message'], 'Synthetic retention' ) && empty( $mail['attachments'] ), 'Digest must contain only the actual date/count and local review link, without form content or attachments.' );
	Maintenance::digest();
	rktsky_antispam_policy_expect( 1 === count( $rktsky_policy_mail ), 'Failed mail must not be automatically resent by another digest invocation.' );
	delete_option( 'rktsky_antispam_digest_claim' );
	$wpdb->delete( $wpdb->prefix . 'rktsky_antispam_jobs', array( 'job' => $job_key ) );
	$strict_digest = $digest_settings;
	$strict_digest['privacy_mode'] = true;
	update_option( Settings::OPTION, $strict_digest, false );
	Maintenance::digest();
	rktsky_antispam_policy_expect( 1 === count( $rktsky_policy_mail ) && false === get_option( 'rktsky_antispam_digest_claim', false ), 'Strict privacy must suppress both mail and a new digest claim.' );
	update_option( Settings::OPTION, $digest_settings, false );
	$mail_directory = sys_get_temp_dir() . '/rktsky-antispam-mail-' . bin2hex( random_bytes( 16 ) );
	mkdir( $mail_directory, 0700 );
	putenv( 'RKTSKY_ANTISPAM_MAIL_DIR=' . $mail_directory );
	$workers = array();
	foreach ( range( 1, 4 ) as $index ) {
		$pipes = array();
		$command = array( PHP_BINARY );
		if ( ! extension_loaded( 'mbstring' ) && is_file( '/tmp/rktsky-antispam-runtime/iconv.so' ) ) {
			$command = array_merge( $command, array( '-d', 'extension=/tmp/rktsky-antispam-runtime/iconv.so' ) );
		}
		$command = array_merge( $command, array( __DIR__ . '/digest-worker.php', (string) $index ) );
		$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		$workers[] = array( $process, $pipes );
	}
	foreach ( $workers as $worker ) {
		foreach ( $worker[1] as $pipe ) {
			fclose( $pipe );
		}
		rktsky_antispam_policy_expect( 0 === proc_close( $worker[0] ), 'Concurrent digest workers must finish without runtime errors.' );
	}
	$mail_files = glob( $mail_directory . '/*.jsonl' );
	$mail_attempts = 0;
	foreach ( $mail_files as $file ) {
		$mail_attempts += count( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) );
	}
	rktsky_antispam_policy_expect( 1 === $mail_attempts, 'Four simultaneous native workers must claim exactly one daily mail attempt; observed ' . $mail_attempts . '.' );
	echo 'PASS: ' . $rktsky_policy_assertions . " native policy, maintenance, and dashboard assertions.\n";
} finally {
	$wpdb->delete( $wpdb->prefix . 'rktsky_antispam_jobs', array( 'job' => $job_key ) );
	if ( $job_before ) {
		$wpdb->insert( $wpdb->prefix . 'rktsky_antispam_jobs', $job_before );
	}
	remove_filter( 'pre_wp_mail', 'rktsky_antispam_policy_mail', PHP_INT_MAX );
	remove_filter( 'pre_http_request', 'rktsky_antispam_policy_http', PHP_INT_MAX );
	foreach ( $comments as $id ) {
		wp_delete_comment( $id, true );
	}
	if ( $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $original_digest_options as $name => $value ) {
		if ( false === $value ) {
			delete_option( $name );
		} else {
			update_option( $name, $value, false );
		}
	}
	if ( '' !== $mail_directory && is_dir( $mail_directory ) ) {
		foreach ( glob( $mail_directory . '/*.jsonl' ) as $file ) {
			unlink( $file );
		}
		rmdir( $mail_directory );
	}
	putenv( false === $old_mail_directory ? 'RKTSKY_ANTISPAM_MAIL_DIR' : 'RKTSKY_ANTISPAM_MAIL_DIR=' . $old_mail_directory );
	update_option( Settings::OPTION, $original, false );
	$_SERVER = $server;
	wp_set_current_user( 0 );
}
