<?php
/**
 * Verifies reviewed-outcome mathematics, native lifecycles, privacy, and SQL races.
 *
 * @package RktskyAntispamTests
 * @since 0.2.0
 */

use Rktsky\Antispam\Feedback;
use Rktsky\Antispam\Quarantine;
use Rktsky\Antispam\Settings;
use Rktsky\Antispam\Token;

require __DIR__ . '/bootstrap.php';
$rktsky_feedback_assertions = 0;

/**
 * Records a meaningful feedback invariant without printing subjects or case references.
 *
 * @since 0.2.0
 * @param bool $condition Required behavior.
 * @param string $message Failure context.
 * @return void
 */
function rktsky_antispam_feedback_expect( bool $condition, string $message ): void {
	global $rktsky_feedback_assertions;
	++$rktsky_feedback_assertions;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Runs independent review/forget workers against one existing synthetic comment.
 *
 * @since 0.2.0
 * @param int $id Synthetic native comment ID.
 * @param string[] $modes Labels or forget operations to race.
 * @return void
 */
function rktsky_antispam_feedback_race( int $id, array $modes ): void {
	$workers = array();
	foreach ( $modes as $mode ) {
		$pipes = array();
		$command = array( PHP_BINARY );
		if ( ! extension_loaded( 'mbstring' ) && is_file( '/tmp/rktsky-antispam-runtime/iconv.so' ) ) {
			$command = array_merge( $command, array( '-d', 'extension=/tmp/rktsky-antispam-runtime/iconv.so' ) );
		}
		$command = array_merge( $command, array( __DIR__ . '/feedback-worker.php', (string) $id, $mode ) );
		$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		$workers[] = array( $process, $pipes );
	}
	foreach ( $workers as $worker ) {
		foreach ( $worker[1] as $pipe ) {
			fclose( $pipe );
		}
		rktsky_antispam_feedback_expect( 0 === proc_close( $worker[0] ), 'Concurrent feedback workers must finish without a runtime error.' );
	}
}

global $wpdb;
$settings = new Settings();
$original = $settings->all();
$original_disallowed = get_option( 'disallowed_keys', false );
$server = $_SERVER;
$feedback = new Feedback();
$feedback->install();
$quarantine = new Quarantine( $settings );
$quarantine->install();
$table = $wpdb->prefix . 'rktsky_antispam_feedback';
$quarantine_table = $wpdb->prefix . 'rktsky_antispam_quarantine';
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
$comments = array();
$quarantine_ids = array();
$post_id = 0;
wp_set_current_user( 1 );
update_option( Settings::OPTION, $settings->defaults(), false );

try {
	$post_id = wp_insert_post( array( 'post_title' => 'Synthetic feedback fixture', 'post_type' => 'post', 'post_status' => 'publish', 'comment_status' => 'open' ) );
	$empty = $feedback->metrics();
	rktsky_antispam_feedback_expect( null === $empty['precision'] && null === $empty['recall'] && null === $empty['accuracy'] && 0 === $empty['reviewed'], 'Missing human labels must display unknown rates rather than invented accuracy.' );
	$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), 0 );
	rktsky_antispam_feedback_expect( array( 'ref', 'source', 'predicted_spam', 'actual_spam', 'linked', 'created', 'expires' ) === $columns, 'Feedback may store metadata and keyed pseudonyms, never copied fields or raw object references.' );
	foreach ( range( 0, 4 ) as $index ) {
		$comments[] = wp_insert_comment( array( 'comment_post_ID' => $post_id, 'comment_author' => 'Synthetic reviewer fixture', 'comment_author_email' => 'feedback-subject@example.invalid', 'comment_content' => 'Synthetic feedback content ' . $index, 'comment_approved' => 0 ) );
	}
	$predictions = array( true, true, false, false, true );
	foreach ( $predictions as $index => $prediction ) {
		$feedback->track( 'comments', $comments[ $index ], $prediction );
	}
	$feedback->track( 'comments', $comments[0], false );
	$status = $feedback->status( 'comments', $comments[0] );
	rktsky_antispam_feedback_expect( $status['predicted_spam'] && ! $status['reviewed'] && null === $status['actual_spam'] && 5 === $feedback->metrics()['pending'], 'Repeated tracking must preserve original predictions and leave unreviewed observations pending.' );
	foreach ( array( true, false, true, false ) as $index => $label ) {
		rktsky_antispam_feedback_expect( $feedback->review( 'comments', $comments[ $index ], $label ), 'An authorized review must label its existing native observation.' );
	}
	$metrics = $feedback->metrics();
	rktsky_antispam_feedback_expect( 1 === $metrics['tp'] && 1 === $metrics['fp'] && 1 === $metrics['fn'] && 1 === $metrics['tn'] && 4 === $metrics['reviewed'] && 1 === $metrics['pending'], 'Each reviewed observation must occupy exactly its correct confusion-matrix cell.' );
	rktsky_antispam_feedback_expect( 0.5 === $metrics['precision'] && 0.5 === $metrics['recall'] && 0.5 === $metrics['accuracy'], 'Rates must use only the labelled sample with their actual denominators.' );
	$feedback->review( 'comments', $comments[1], false );
	rktsky_antispam_feedback_expect( $metrics === $feedback->metrics(), 'Repeating the same label must be idempotent.' );
	wp_set_current_user( 0 );
	rktsky_antispam_feedback_expect( ! $feedback->review( 'comments', $comments[0], false ) && null === $feedback->status( 'comments', $comments[0] ) && 0 === $feedback->metrics()['reviewed'] && ! $feedback->report_missed( 'cf7', 'synthetic-unprivileged' ), 'Anonymous callers must not view or change quality labels or create manual reports.' );
	rktsky_antispam_feedback_expect( is_wp_error( $feedback->export( 'feedback-subject@example.invalid' ) ) && is_wp_error( $feedback->erase( 'feedback-subject@example.invalid' ) ), 'Feedback privacy callbacks must enforce their dedicated Core capabilities.' );
	wp_set_current_user( 1 );
	rktsky_antispam_feedback_expect( $feedback->report_missed( 'cf7', 'opaque-synthetic-case-42' ), 'A stable manually identified missed case must be recordable.' );
	$manual = $feedback->metrics();
	$feedback->report_missed( 'cf7', 'opaque-synthetic-case-42' );
	rktsky_antispam_feedback_expect( $manual === $feedback->metrics() && 1 === $manual['manual_fn'] && 2 === $manual['fn'], 'Duplicate manual case reports must not increase sample size.' );
	$feedback->report_delivered( 'cf7', 'opaque-synthetic-case-42', false );
	rktsky_antispam_feedback_expect( 0 === $feedback->metrics()['manual_fn'] && 2 === $feedback->metrics()['tn'] && 1 === $feedback->metrics()['fn'], 'Correcting a manual label must move its existing cell without adding an observation.' );
	$feedback->report_missed( 'wpforms', 'opaque-synthetic-case-42' );
	rktsky_antispam_feedback_expect( 1 === $feedback->metrics()['manual_fn'], 'Manual references with identical text must remain separated by provider.' );
	$raw = wp_json_encode( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A ) );
	rktsky_antispam_feedback_expect( ! str_contains( $raw, 'opaque-synthetic-case-42' ) && ! str_contains( $raw, 'feedback-subject@example.invalid' ) && ! str_contains( $raw, 'Synthetic feedback content' ), 'The metadata ledger must not retain raw references, email, or message content.' );
	$before_forget = $feedback->metrics();
	$feedback->forget( 'comments', $comments[0] );
	rktsky_antispam_feedback_expect( null === $feedback->status( 'comments', $comments[0] ) && ! $feedback->review( 'comments', $comments[0], false ) && $before_forget === $feedback->metrics(), 'Forgetting a reviewed source must remove its link while retaining the bounded anonymous outcome.' );
	wp_delete_comment( $comments[1], true );
	rktsky_antispam_feedback_expect( null === $feedback->status( 'comments', $comments[1] ) && 1 === $feedback->metrics()['fp'], 'Successful native comment deletion must detach its reviewed classification.' );
	wp_delete_comment( $comments[4], true );
	rktsky_antispam_feedback_expect( 0 === $feedback->metrics()['pending'], 'Deleting an unreviewed native comment must remove the pending observation.' );
	$detached = $wpdb->get_results( $wpdb->prepare( 'SELECT created,expires FROM %i WHERE linked=0', $table ), ARRAY_A );
	foreach ( $detached as $row ) {
		rktsky_antispam_feedback_expect( 0 === (int) $row['created'] % DAY_IN_SECONDS && 0 === (int) $row['expires'] % DAY_IN_SECONDS, 'Detached outcomes must lose precise source-event timestamps without extending expiry.' );
	}

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	$transaction_started = $wpdb->query( 'START TRANSACTION' );
	rktsky_antispam_feedback_expect( false !== $transaction_started, 'The original database adapter must support the outer-transaction test.' );
	try {
		$feedback->track( 'comments', $comments[2], true );
		$feedback->review( 'comments', $comments[2], true );
	} finally {
		$wpdb->query( 'ROLLBACK' );
	}
	rktsky_antispam_feedback_expect( null === $feedback->status( 'comments', $comments[2] ), 'Feedback writes must not implicitly commit a provider-owned outer transaction.' );
	$feedback->track( 'comments', $comments[2], true );
	rktsky_antispam_feedback_race( $comments[2], array( '1', '0', '1', '0' ) );
	$race = $feedback->metrics();
	rktsky_antispam_feedback_expect( 1 === $race['reviewed'] && 1 === $race['tp'] + $race['fp'] && 0 === $race['pending'], 'Concurrent label corrections must produce exactly one outcome, never duplicate bucket counts.' );
	$feedback->forget( 'comments', $comments[2] );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	$feedback->track( 'comments', $comments[2], true );
	rktsky_antispam_feedback_race( $comments[2], array( '1', 'forget', '0', 'forget' ) );
	$race = $feedback->metrics();
	rktsky_antispam_feedback_expect( null === $feedback->status( 'comments', $comments[2] ) && 0 === $race['pending'] && $race['reviewed'] <= 1, 'Concurrent erasure and review must leave no source link or duplicate outcome.' );

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	$privacy_ids = array();
	foreach ( array( 0, 1, 'spam', 'trash', 'post-trashed' ) as $index => $native_status ) {
		$privacy_id = wp_insert_comment( array( 'comment_post_ID' => $post_id, 'comment_author' => 'Synthetic privacy fixture', 'comment_author_email' => 'feedback-privacy@example.invalid', 'comment_content' => 'Synthetic privacy record ' . $index, 'comment_approved' => $native_status ) );
		$comments[] = $privacy_id;
		$privacy_ids[] = $privacy_id;
		$feedback->track( 'comments', $privacy_id, 'spam' === $native_status || 'trash' === $native_status );
	}
	$export = $feedback->export( 'feedback-privacy@example.invalid' );
	rktsky_antispam_feedback_expect( 5 === count( $export['data'] ), 'Native subject matching must include spam and both trash states, not just approved/pending comments.' );
	$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
	rktsky_antispam_feedback_expect( 'rktsky-antispam-feedback' === array_key_first( $erasers ), 'Feedback erasure must precede native comment email anonymization.' );
	$erased = $feedback->erase( 'feedback-privacy@example.invalid' );
	rktsky_antispam_feedback_expect( $erased['items_removed'] && ! $erased['items_retained'] && $erased['done'] && 0 === $feedback->metrics()['pending'], 'Confirmed subject erasure must remove pending references across every native status.' );
	foreach ( $privacy_ids as $privacy_id ) {
		rktsky_antispam_feedback_expect( null === $feedback->status( 'comments', $privacy_id ) && null !== get_comment( $privacy_id ), 'Feedback erasure must remove its own metadata link without deleting the native comment.' );
	}

	$enabled = $settings->defaults();
	$enabled['privacy_mode'] = false;
	$enabled['quarantine_enabled'] = true;
	update_option( Settings::OPTION, $enabled, false );
	foreach ( range( 1, 2 ) as $index ) {
		$quarantine->capture( 'cf7', (string) $post_id, 'keyword', array( 'Message' => 'Synthetic quality privacy record ' . $index ), 'quarantine-feedback@example.invalid' );
		$quarantine_ids[] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i ORDER BY id DESC LIMIT 1', $quarantine_table ) );
	}
	rktsky_antispam_feedback_expect( 2 === $feedback->metrics()['pending'] && true === $feedback->status( 'quarantine', $quarantine_ids[0] )['predicted_spam'], 'Successful quarantine capture must emit exactly one observed rejection without an automatic human label.' );
	$feedback->review( 'quarantine', $quarantine_ids[0], false );
	foreach ( range( 1, 10 ) as $page ) {
		$quarantine->erase( 'quarantine-feedback@example.invalid', $page );
	}
	rktsky_antispam_feedback_expect( 1 === $feedback->metrics()['fp'] && 0 === $feedback->metrics()['pending'] && null === $feedback->status( 'quarantine', $quarantine_ids[0] ) && null === $feedback->status( 'quarantine', $quarantine_ids[1] ), 'Quarantine privacy erasure must detach reviewed outcomes and delete pending references through the real lifecycle hook.' );

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	update_option( 'disallowed_keys', 'synthetic-native-spam-marker' );
	$tokens = new Token();
	$native_ids = array();
	foreach ( array( 'A normal synthetic native comment.', 'This contains synthetic-native-spam-marker for the native WordPress filter.' ) as $index => $content ) {
		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = '192.0.2.' . ( 30 + $index );
		$_SERVER['HTTP_USER_AGENT'] = 'Synthetic WordPress feedback browser';
		$proof = $tokens->issue( 'comments', (string) $post_id );
		$_POST = array( 'rktsky_antispam_token' => $proof['token'], $proof['trap'] => '' );
		$native_id = wp_new_comment( array( 'comment_post_ID' => $post_id, 'comment_author' => 'Synthetic native author ' . $index, 'comment_author_email' => 'native-feedback-' . $index . '@example.invalid', 'comment_content' => $content, 'comment_author_IP' => $_SERVER['REMOTE_ADDR'] ), true );
		rktsky_antispam_feedback_expect( ! is_wp_error( $native_id ) && $native_id > 0, 'A valid challenge must reach the original native WordPress comment pipeline.' );
		$comments[] = (int) $native_id;
		$native_ids[] = (int) $native_id;
	}
	wp_set_current_user( 1 );
	$normal = $feedback->status( 'comments', $native_ids[0] );
	$rejected = $feedback->status( 'comments', $native_ids[1] );
	rktsky_antispam_feedback_expect( ! $normal['predicted_spam'] && ! $normal['reviewed'] && $rejected['predicted_spam'] && ! $rejected['reviewed'], 'Native WordPress decisions must be observed after persistence without treating them as human truth.' );
	rktsky_antispam_feedback_expect( 2 === $feedback->metrics()['pending'], 'A native spam comment and any quarantine representation must not count as two different received cases.' );
	wp_set_comment_status( $native_ids[0], 'approve' );
	wp_set_comment_status( $native_ids[1], 'approve' );
	rktsky_antispam_feedback_expect( 1 === $feedback->metrics()['tn'] && 1 === $feedback->metrics()['fp'] && 0 === $feedback->metrics()['pending'], 'Actual native moderator approval must label the immutable original decisions.' );
	wp_set_comment_status( $native_ids[0], 'spam' );
	rktsky_antispam_feedback_expect( 1 === $feedback->metrics()['fn'] && 0 === $feedback->metrics()['tn'], 'Native reclassification as spam must correct an earlier reviewed delivered case.' );
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET expires=%d', $table, time() - 1 ) );
	rktsky_antispam_feedback_expect( 0 === $feedback->metrics()['reviewed'] && null === $feedback->status( 'comments', $native_ids[0] ), 'Expired feedback must disappear from metrics and review immediately.' );
	$feedback->cleanup();
	rktsky_antispam_feedback_expect( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ), 'Ninety-day metadata expiry must physically remove linked and detached outcomes.' );
	echo 'PASS: ' . $rktsky_feedback_assertions . " native feedback and moderation assertions.\n";
} finally {
	wp_set_current_user( 1 );
	foreach ( $quarantine_ids as $id ) {
		$quarantine->delete( $id );
	}
	if ( $post_id ) {
		$remaining_quarantine = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE provider=%s AND form_id=%s', $quarantine_table, 'comments', (string) $post_id ) );
		foreach ( $remaining_quarantine as $id ) {
			$quarantine->delete( (int) $id );
		}
	}
	foreach ( $comments as $id ) {
		wp_delete_comment( $id, true );
	}
	if ( $post_id ) {
		wp_delete_post( $post_id, true );
	}
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	if ( false === $original_disallowed ) {
		delete_option( 'disallowed_keys' );
	} else {
		update_option( 'disallowed_keys', $original_disallowed, false );
	}
	update_option( Settings::OPTION, $original, false );
	$_SERVER = $server;
	$_POST = array();
	wp_set_current_user( 0 );
}
