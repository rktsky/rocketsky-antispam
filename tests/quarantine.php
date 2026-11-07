<?php
/**
 * Verifies encrypted content, role boundaries, bounded storage, and native privacy workflows.
 *
 * @package RktskyAntispamTests
 * @since 0.2.0
 */

use Rktsky\Antispam\Quarantine;
use Rktsky\Antispam\Settings;

require __DIR__ . '/bootstrap.php';
if ( ! class_exists( Quarantine::class ) ) {
	require dirname( __DIR__ ) . '/src/Quarantine.php';
}
$rktsky_quarantine_assertions = 0;

/**
 * Records a native quarantine invariant without outputting submitted content.
 *
 * @since 0.2.0
 * @param bool $condition Required behavior.
 * @param string $message Failure context.
 * @return void
 */
function rktsky_antispam_quarantine_expect( bool $condition, string $message ): void {
	global $rktsky_quarantine_assertions;
	++$rktsky_quarantine_assertions;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Simulates a changed auth salt without modifying the fixture configuration or Core.
 *
 * @since 0.2.0
 * @param string $salt Native salt selected by WordPress.
 * @param string $scheme Native protocol purpose.
 * @return string Changed auth salt only during the explicit key-rotation test.
 */
function rktsky_antispam_quarantine_rotate_salt( string $salt, string $scheme ): string {
	return 'auth' === $scheme ? hash( 'sha256', 'disposable-rotation|' . $salt ) : $salt;
}

global $wpdb;
$settings = new Settings();
$original = $settings->all();
$repository = new Quarantine( $settings );
$repository->install();
$table = $wpdb->prefix . 'rktsky_antispam_quarantine';
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
wp_set_current_user( 1 );

try {
	$strict = $settings->defaults();
	$strict['quarantine_enabled'] = true;
	$strict['privacy_mode'] = true;
	update_option( Settings::OPTION, $strict, false );
	$repository->capture( 'comments', '42', 'missing', array( 'Message' => 'Synthetic private message.' ), 'fixture@example.invalid' );
	rktsky_antispam_quarantine_expect( ! $repository->enabled() && 0 === $repository->count(), 'Strict privacy must prevent capture even when a quarantine checkbox was submitted.' );
	rktsky_antispam_quarantine_expect( $repository->encryption_ready(), 'Original WordPress Sodium encryption must be available.' );

	$enabled = $settings->defaults();
	$enabled['privacy_mode'] = false;
	$enabled['quarantine_enabled'] = true;
	$enabled['quarantine_days'] = 7;
	update_option( Settings::OPTION, $enabled, false );
	$repository->capture( 'comments', '42', 'honeypot', array( 'Message' => 'Synthetic <script>alert(1)</script> text.', 'Name' => 'Synthetic name' ), 'Fixture@Example.Invalid' );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 1', $table ), ARRAY_A );
	rktsky_antispam_quarantine_expect( is_array( $row ), 'Explicitly enabled public capture must persist one encrypted row.' );
	$id = (int) $row['id'];
	$read = $repository->read( $id );
	rktsky_antispam_quarantine_expect( is_array( $read ) && ! $read['unreadable'] && 'fixture@example.invalid' === $read['email'] && str_contains( $read['fields']['Message'], '<script>' ), 'Authenticated decryption must return plaintext data for later contextual escaping.' );
	rktsky_antispam_quarantine_expect( ! str_contains( $row['payload'], 'fixture@example.invalid' ) && ! str_contains( $row['payload'], 'Synthetic' ), 'Database payload must not contain plaintext subjects or messages.' );
	$export_html = $repository->export( 'fixture@example.invalid', (int) floor( (int) $row['slot'] / 50 ) + 1 );
	$export_serialized = wp_json_encode( $export_html );
	rktsky_antispam_quarantine_expect( str_contains( $export_serialized, '&lt;script&gt;' ) && ! str_contains( $export_serialized, '<script>' ), 'Native HTML privacy exports must display submitted markup as text.' );
	$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), 0 );
	rktsky_antispam_quarantine_expect( array( 'id', 'slot', 'provider', 'form_id', 'reason', 'created', 'expires', 'payload' ) === $columns, 'Quarantine must contain no plaintext email, IP, or arbitrary request columns.' );
	$repository->capture( 'comments', '42', 'honeypot', array( 'Message' => 'Synthetic <script>alert(1)</script> text.', 'Name' => 'Synthetic name' ), 'Fixture@Example.Invalid' );
	$second = $wpdb->get_var( $wpdb->prepare( 'SELECT payload FROM %i ORDER BY id DESC LIMIT 1', $table ) );
	rktsky_antispam_quarantine_expect( $second !== $row['payload'], 'Independent encryptions of identical content must use independent nonces.' );

	wp_set_current_user( 0 );
	rktsky_antispam_quarantine_expect( null === $repository->read( $id ) && 0 === $repository->count() && array() === $repository->listing()['items'] && ! $repository->delete( $id ), 'Anonymous callers must not read, list, count, or delete quarantine content.' );
	rktsky_antispam_quarantine_expect( is_wp_error( $repository->export( 'fixture@example.invalid' ) ) && is_wp_error( $repository->erase( 'fixture@example.invalid' ) ), 'Native privacy callbacks must independently enforce their dedicated capabilities.' );
	$repository->capture( 'comments', '43', 'native_spam', array( 'Message' => 'Anonymous synthetic rejected content.' ) );
	wp_set_current_user( 1 );
	rktsky_antispam_quarantine_expect( 3 === $repository->count(), 'Optional capture must still work for public forms without granting public read access.' );
	rktsky_antispam_quarantine_expect( null === $repository->read( -1 ) && ! $repository->delete( -1 ), 'Invalid administrative identifiers must not access a record.' );
	$list = $repository->listing();
	rktsky_antispam_quarantine_expect( ! isset( $list['items'][0]['payload'], $list['items'][0]['fields'], $list['items'][0]['email'] ), 'List queries must return metadata only.' );
	$before = $repository->count();
	$repository->capture( 'untrusted', '42', 'honeypot', array( 'Message' => 'Synthetic.' ) );
	$repository->capture( 'comments', '../42', 'honeypot', array( 'Message' => 'Synthetic.' ) );
	$repository->capture( 'comments', '42', 'untrusted reason', array( 'Message' => 'Synthetic.' ) );
	rktsky_antispam_quarantine_expect( $before === $repository->count(), 'Unbounded metadata must never be persisted.' );

	$fields = array();
	for ( $index = 0; $index < 40; ++$index ) {
		$fields[ 'Field ' . $index ] = str_repeat( '🛰', 5000 );
	}
	$repository->capture( 'cf7', '15', 'pattern', $fields );
	$large_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i ORDER BY id DESC LIMIT 1', $table ) );
	$large = $repository->read( $large_id );
	$bytes = 0;
	foreach ( $large['fields'] as $label => $value ) {
		$bytes += strlen( $label ) + strlen( $value );
		rktsky_antispam_quarantine_expect( strlen( $value ) <= 4096 && wp_check_invalid_utf8( $value ) === $value, 'Stored values must preserve complete UTF-8 characters within four KiB.' );
	}
	rktsky_antispam_quarantine_expect( count( $large['fields'] ) <= 30 && $bytes <= 16384, 'Combined plaintext fields must remain within the fixed field and byte budgets.' );

	add_filter( 'salt', 'rktsky_antispam_quarantine_rotate_salt', 10, 2 );
	$rotated = $repository->read( $id );
	rktsky_antispam_quarantine_expect( $rotated['unreadable'] && array() === $rotated['fields'] && '' === $rotated['email'], 'Key rotation must never return unauthenticated plaintext.' );
	$unreadable_export = $repository->export( 'fixture@example.invalid', (int) floor( (int) $row['slot'] / 50 ) + 1 );
	rktsky_antispam_quarantine_expect( array() === $unreadable_export['data'], 'Unreadable ciphertext must not be falsely attributed to a privacy subject.' );
	remove_filter( 'salt', 'rktsky_antispam_quarantine_rotate_salt', 10 );
	rktsky_antispam_quarantine_expect( ! $repository->read( $id )['unreadable'], 'Restoring the original key must restore authenticated readback.' );
	$wpdb->update( $table, array( 'payload' => ( 'A' === $row['payload'][0] ? 'B' : 'A' ) . substr( $row['payload'], 1 ) ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	rktsky_antispam_quarantine_expect( $repository->read( $id )['unreadable'], 'Ciphertext modification must fail authentication.' );
	$wpdb->update( $table, array( 'payload' => $row['payload'], 'form_id' => 'tampered' ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
	rktsky_antispam_quarantine_expect( $repository->read( $id )['unreadable'], 'Ciphertext must authenticate its original form metadata.' );
	$wpdb->update( $table, array( 'form_id' => $row['form_id'] ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );

	$shorter = $enabled;
	$shorter['quarantine_days'] = 1;
	update_option( Settings::OPTION, $shorter, false );
	$repository->cleanup();
	$short_read = $repository->read( $id );
	rktsky_antispam_quarantine_expect( ! $short_read['unreadable'] && $short_read['expires'] <= $short_read['created'] + DAY_IN_SECONDS, 'Shortening retention must affect existing records without corrupting authenticated content.' );
	$wpdb->update( $table, array( 'expires' => time() - 1 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
	rktsky_antispam_quarantine_expect( null === $repository->read( $id ), 'Expired content must be hidden even before cron executes.' );
	$repository->cleanup();
	rktsky_antispam_quarantine_expect( null === $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d', $table, $id ) ), 'Cleanup must physically remove expired ciphertext.' );
	update_option( Settings::OPTION, $enabled, false );

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	for ( $index = 0; $index < 52; ++$index ) {
		$repository->capture( 'comments', '42', 'keyword', array( 'Message' => 'Synthetic privacy fixture ' . $index ), 'Subject@Example.Invalid' );
	}
	$repository->capture( 'comments', '42', 'keyword', array( 'Message' => 'Unrelated synthetic subject.' ), 'other@example.invalid' );
	$repository->capture( 'comments', '42', 'keyword', array( 'Message' => 'No subject supplied.' ) );
	$exported = 0;
	$removed = false;
	for ( $page = 1; $page <= 10; ++$page ) {
		$page_data = $repository->export( 'SUBJECT@example.invalid', $page );
		$exported += count( $page_data['data'] );
		rktsky_antispam_quarantine_expect( ( $page >= 10 ) === $page_data['done'], 'Privacy export must traverse the complete fixed slot inventory.' );
	}
	rktsky_antispam_quarantine_expect( 52 === $exported, 'Native privacy export must match normalized subjects across all slot ranges.' );
	for ( $page = 1; $page <= 10; ++$page ) {
		$page_data = $repository->erase( 'subject@example.invalid', $page );
		$removed = $removed || $page_data['items_removed'];
		rktsky_antispam_quarantine_expect( ! $page_data['items_retained'] && ( $page >= 10 ) === $page_data['done'], 'Privacy erasure must use stable ranges without skipping deleted offsets.' );
	}
	rktsky_antispam_quarantine_expect( $removed && 2 === $repository->count(), 'Erasure must remove every matching row and preserve unrelated/no-email rows.' );

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	for ( $index = 0; $index < 499; ++$index ) {
		$repository->capture( 'comments', '42', 'bbcode', array( 'Message' => 'Synthetic quota fixture.' ) );
	}
	rktsky_antispam_quarantine_expect( 499 === $repository->count(), 'Native captures must fill exactly the expected fixed slots before the race.' );
	$workers = array();
	foreach ( range( 1, 4 ) as $index ) {
		$pipes = array();
		$command = array( PHP_BINARY );
		if ( ! extension_loaded( 'mbstring' ) && is_file( '/tmp/rktsky-antispam-runtime/iconv.so' ) ) {
			$command = array_merge( $command, array( '-d', 'extension=/tmp/rktsky-antispam-runtime/iconv.so' ) );
		}
		$command[] = __DIR__ . '/quarantine-worker.php';
		$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		$workers[] = array( $process, $pipes );
	}
	foreach ( $workers as $worker ) {
		foreach ( $worker[1] as $pipe ) {
			fclose( $pipe );
		}
		rktsky_antispam_quarantine_expect( 0 === proc_close( $worker[0] ), 'Concurrent quarantine workers must finish without a runtime failure.' );
	}
	rktsky_antispam_quarantine_expect( 500 === $repository->count(), 'Four concurrent captures competing for one free slot must never exceed five hundred records.' );
	$repository->capture( 'comments', '42', 'bbcode', array( 'Message' => 'Synthetic over-cap attempt.' ) );
	rktsky_antispam_quarantine_expect( 500 === $repository->count(), 'A full quarantine must discard additional captures without overwriting existing records.' );
	$last = $repository->listing( 999 );
	rktsky_antispam_quarantine_expect( 25 === $last['page'] && 25 === $last['pages'] && 20 === count( $last['items'] ), 'Administrative pagination must stay bounded to twenty metadata rows.' );
	$delete_id = (int) $last['items'][0]['id'];
	rktsky_antispam_quarantine_expect( $repository->delete( $delete_id ) && null === $repository->read( $delete_id ) && ! $repository->delete( $delete_id ), 'Authorized deletion must be permanent and repeat safely.' );
	$previous_errors = $wpdb->suppress_errors( true );
	try {
		// Expire synthetic sources through their real lifecycle before simulating missing storage.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET expires=%d', $table, time() - 1 ) );
		$repository->cleanup();
		$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $table ) );
		$queries_before_failure = $wpdb->num_queries;
		$repository->capture( 'comments', '42', 'missing', array( 'Message' => 'Synthetic unavailable-storage test.' ) );
		rktsky_antispam_quarantine_expect( $wpdb->num_queries > $queries_before_failure && '' !== $wpdb->last_error, 'Unavailable optional storage must fail locally without disrupting the guard.' );
	} finally {
		$repository->install();
		$wpdb->suppress_errors( $previous_errors );
	}

	echo 'PASS: ' . $rktsky_quarantine_assertions . " native quarantine assertions.\n";
} finally {
	remove_filter( 'salt', 'rktsky_antispam_quarantine_rotate_salt', 10 );
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET expires=%d', $table, time() - 1 ) );
	$repository->cleanup();
	update_option( Settings::OPTION, $original, false );
	wp_set_current_user( 0 );
}
