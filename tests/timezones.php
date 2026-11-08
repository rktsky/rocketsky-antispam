<?php
/**
 * Exercises native site calendars, retained UTC history and absolute security clocks.
 *
 * Run only against a marked disposable WordPress fixture. The six approved owned
 * tables and affected options are restored exactly, including on failure. Core
 * and provider records are never queried directly. Child workers intercept mail
 * before delivery and share only a private temporary directory.
 *
 * @package RktskyAntispamTests
 * @since 0.5.3
 */

use Rktsky\Antispam\Admin;
use Rktsky\Antispam\Dates;
use Rktsky\Antispam\Dashboard;
use Rktsky\Antispam\Feedback;
use Rktsky\Antispam\Guard;
use Rktsky\Antispam\Maintenance;
use Rktsky\Antispam\Plugin;
use Rktsky\Antispam\Quarantine;
use Rktsky\Antispam\Settings;
use Rktsky\Antispam\Store;
use Rktsky\Antispam\Token;

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$rktsky_timezone_assertions = 0;
$rktsky_timezone_mail       = array();

/**
 * Records an observed native invariant without exposing retained contents.
 *
 * @since 0.5.3
 * @param bool   $condition Required behavior.
 * @param string $message Non-personal failure context.
 * @return void
 * @throws RuntimeException When the observed native behavior violates the contract.
 */
function rktsky_timezone_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_timezone_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

/**
 * Builds a real Unix instant from an explicitly UTC test vector.
 *
 * @since 0.5.3
 * @param string $instant ISO timestamp containing its explicit UTC suffix.
 * @return int Absolute Unix timestamp, never shifted by a website offset.
 */
function rktsky_timezone_instant( string $instant ): int {
	return ( new DateTimeImmutable( $instant, new DateTimeZone( 'UTC' ) ) )->getTimestamp();
}

/**
 * Configures a named site timezone or Core's documented fixed-offset fallback.
 *
 * @since 0.5.3
 * @param string $name Native timezone identifier; empty uses the numeric offset.
 * @param float  $offset WordPress offset in hours, including fractional hours.
 * @return void
 */
function rktsky_timezone_set( string $name, float $offset = 0 ): void {
	update_option( 'timezone_string', $name );
	update_option( 'gmt_offset', $offset );
}

/**
 * Reads a bounded snapshot of one approved owned table.
 *
 * @since 0.5.3
 * @param string $suffix Fixed suffix from the explicitly approved table inventory.
 * @return array<int,array<string,mixed>> Stable rows for exact restoration.
 * @throws RuntimeException For an unapproved table or an unsafe snapshot size.
 */
function rktsky_timezone_rows( string $suffix ): array {
	if ( ! in_array( $suffix, array( 'tokens', 'stats', 'totals', 'quarantine', 'feedback', 'jobs' ), true ) ) {
		throw new RuntimeException( 'Unexpected owned table.' );
	}
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i LIMIT 10001', $wpdb->prefix . 'rktsky_antispam_' . $suffix ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Approved owned-table fixture snapshot, schema migration or exact restoration requires live readback.
	if ( ! is_array( $rows ) || count( $rows ) > 10000 ) {
		throw new RuntimeException( 'Owned synthetic snapshot exceeds the restoration bound.' );
	}
	usort( $rows, static fn( array $left, array $right ): int => strcmp( wp_json_encode( $left ), wp_json_encode( $right ) ) );
	return $rows;
}

/**
 * Selects the documented digest key for one calendar without changing job expiry.
 *
 * @since 0.5.3
 * @param string $calendar Stored native calendar identifier.
 * @param string $day Completed local calendar date.
 * @return string Fixed anonymous job key.
 */
function rktsky_timezone_job( string $calendar, string $day ): string {
	return 'UTC' === $calendar ? 'digest_' . $day : 'digest_' . substr( hash( 'sha256', $calendar ), 0, 16 ) . '_' . $day;
}

/**
 * Captures native mail arguments and always preserves the fixture's hard block.
 *
 * @since 0.5.3
 * @param mixed $previous Earlier native interception decision.
 * @param array $attributes Aggregate-only wp_mail arguments.
 * @return bool False; no real mailer may run.
 */
function rktsky_timezone_capture_mail( $previous, array $attributes ): bool {
	$GLOBALS['rktsky_timezone_mail'][] = $attributes;
	return false;
}

// The same source file provides genuine separate-process race participants.
if ( 'worker' === ( $argv[1] ?? '' ) ) {
	$rktsky_timezone_directory = realpath( $argv[3] ?? '' );
	if ( ! $rktsky_timezone_directory || dirname( $rktsky_timezone_directory ) !== realpath( sys_get_temp_dir() ) || is_link( $argv[3] ) || ! preg_match( '/^rocketsky-timezone-mail-[a-f0-9]{24}$/D', basename( $rktsky_timezone_directory ) ) || ! preg_match( '/^[1-4]$/D', $argv[4] ?? '' ) || ! ctype_digit( $argv[2] ?? '' ) ) {
		throw new RuntimeException( 'A bounded private synthetic mail worker is required.' );
	}
	add_filter( 'pre_wp_mail', 'rktsky_timezone_capture_mail', PHP_INT_MAX, 2 );
	Maintenance::digest( (int) $argv[2] );
	if ( false === file_put_contents( $rktsky_timezone_directory . '/worker-' . $argv[4] . '.json', wp_json_encode( $rktsky_timezone_mail ), LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI-only private local worker files and pipes need direct bounded filesystem access.
		throw new RuntimeException( 'The private native mail capture could not be written.' );
	}
	return;
}

global $wpdb;
$rktsky_timezone_store = new Store();
$rktsky_timezone_store->install();
$rktsky_timezone_tables  = array();
$rktsky_timezone_options = array();
foreach ( array( 'tokens', 'stats', 'totals', 'quarantine', 'feedback', 'jobs' ) as $rktsky_timezone_suffix ) {
	$rktsky_timezone_tables[ $rktsky_timezone_suffix ] = rktsky_timezone_rows( $rktsky_timezone_suffix );
}
foreach ( array( 'timezone_string', 'gmt_offset', 'date_format', 'time_format', Settings::OPTION, 'rktsky_antispam_schema', 'rktsky_antispam_digest_claim', 'rktsky_antispam_digest_status' ) as $rktsky_timezone_option ) {
	$rktsky_timezone_options[ $rktsky_timezone_option ] = get_option( $rktsky_timezone_option, null );
}
$rktsky_timezone_user      = get_current_user_id();
$rktsky_timezone_query     = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Snapshots only the marked CLI fixture query state; no submitted action is executed.
$rktsky_timezone_server    = $_SERVER;
$rktsky_timezone_directory = '';
add_filter( 'pre_wp_mail', 'rktsky_timezone_capture_mail', PHP_INT_MAX, 2 );

try {
	foreach ( array_keys( $rktsky_timezone_tables ) as $rktsky_timezone_suffix ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . 'rktsky_antispam_' . $rktsky_timezone_suffix ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Approved owned-table fixture snapshot, schema migration or exact restoration requires live readback.
	}
	wp_set_current_user( 1 );
	$rktsky_timezone_settings                   = new Settings();
	$rktsky_timezone_base                       = $rktsky_timezone_settings->defaults();
	$rktsky_timezone_base['comments']           = true;
	$rktsky_timezone_base['cf7']                = true;
	$rktsky_timezone_base['minimum_seconds']    = 0;
	$rktsky_timezone_base['privacy_mode']       = false;
	$rktsky_timezone_base['quarantine_enabled'] = true;
	$rktsky_timezone_base['notify_admin']       = true;
	update_option( Settings::OPTION, $rktsky_timezone_base, false );
	update_option( 'date_format', 'Y-m-d' );
	update_option( 'time_format', 'H:i:s P' );

	$rktsky_timezone_vectors = array(
		array( 'Europe/Zurich', '2026-03-29T00:30:00Z', '2026-03-29 01:30:00 +01:00' ),
		array( 'Europe/Zurich', '2026-03-29T01:30:00Z', '2026-03-29 03:30:00 +02:00' ),
		array( 'Europe/Zurich', '2026-10-25T00:30:00Z', '2026-10-25 02:30:00 +02:00' ),
		array( 'Europe/Zurich', '2026-10-25T01:30:00Z', '2026-10-25 02:30:00 +01:00' ),
		array( 'Asia/Kathmandu', '2026-01-01T18:14:59Z', '2026-01-01 23:59:59 +05:45' ),
		array( 'Asia/Kathmandu', '2026-01-01T18:15:00Z', '2026-01-02 00:00:00 +05:45' ),
	);
	foreach ( $rktsky_timezone_vectors as $rktsky_timezone_vector ) {
		rktsky_timezone_set( $rktsky_timezone_vector[0] );
		$rktsky_timezone_stamp = rktsky_timezone_instant( $rktsky_timezone_vector[1] );
		$rktsky_timezone_now   = Dates::now( $rktsky_timezone_stamp );
		rktsky_timezone_expect( $rktsky_timezone_now->getTimestamp() === $rktsky_timezone_stamp, 'Website formatting must preserve the absolute Unix instant.' );
		rktsky_timezone_expect( $rktsky_timezone_now->format( 'Y-m-d H:i:s P' ) === $rktsky_timezone_vector[2] && Dates::format( $rktsky_timezone_stamp ) === $rktsky_timezone_vector[2], 'Named site zones must handle DST gaps, repeated hours and quarter-hour offsets exactly.' );
		rktsky_timezone_expect( substr( $rktsky_timezone_vector[2], 0, 10 ) === Dates::day( 0, $rktsky_timezone_stamp ), 'The recorded day must follow the website calendar rather than the UTC date.' );
	}
	rktsky_timezone_set( '', 5.75 );
	rktsky_timezone_expect( '+05:45' === Dates::calendar() && '2026-01-02' === Dates::day( 0, rktsky_timezone_instant( '2026-01-01T18:15:00Z' ) ), 'Core fixed-offset settings must support fractional-hour offsets without requiring a named zone.' );
	rktsky_timezone_set( '', -3.5 );
	rktsky_timezone_expect( '-03:30' === Dates::calendar() && '2025-12-31' === Dates::day( 0, rktsky_timezone_instant( '2026-01-01T02:30:00Z' ) ), 'Negative half-hour offsets must select the correct preceding local date.' );
	rktsky_timezone_set( '', 0 );
	rktsky_timezone_expect( 'UTC' === Dates::calendar(), 'Equivalent zero-offset fallback settings must share the retained UTC calendar identifier.' );
	rktsky_timezone_set( 'Europe/Zurich' );
	foreach ( array( array( '2026-03-29T10:00:00Z', 23 ), array( '2026-10-25T10:00:00Z', 25 ) ) as $rktsky_timezone_transition ) {
		$rktsky_timezone_midnight = Dates::now( rktsky_timezone_instant( $rktsky_timezone_transition[0] ) )->setTime( 0, 0 );
		rktsky_timezone_expect( $rktsky_timezone_transition[1] * HOUR_IN_SECONDS === $rktsky_timezone_midnight->modify( '+1 day' )->getTimestamp() - $rktsky_timezone_midnight->getTimestamp(), 'Local midnight boundaries must include complete 23-hour and 25-hour dates.' );
	}
	$rktsky_timezone_spring = rktsky_timezone_instant( '2026-03-29T22:30:00Z' );
	$rktsky_timezone_days   = Dates::days( 7, $rktsky_timezone_spring );
	$rktsky_timezone_dates  = array( '2026-03-24', '2026-03-25', '2026-03-26', '2026-03-27', '2026-03-28', '2026-03-29', '2026-03-30' );
	rktsky_timezone_expect( array_column( $rktsky_timezone_days, 'day' ) === $rktsky_timezone_dates, 'The seven-day series must use calendar subtraction across spring DST rather than fixed 86400-second steps.' );
	rktsky_timezone_expect( '2026-03-29' === Dates::day( -1, $rktsky_timezone_spring ) && '2026-03-31' === Dates::day( 1, $rktsky_timezone_spring ), 'Yesterday and tomorrow must refer to adjacent local dates.' );
	rktsky_timezone_expect( 1 === count( Dates::days( 0, $rktsky_timezone_spring ) ) && 90 === count( Dates::days( 1000, $rktsky_timezone_spring ) ), 'Internal calendar series must retain their bounded day count.' );

	// Upgrade the real approved table from its released pre-calendar schema.
	$rktsky_timezone_stats = $wpdb->prefix . 'rktsky_antispam_stats';
	$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $rktsky_timezone_stats ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Approved owned-table fixture snapshot, schema migration or exact restoration requires live readback.
	$rktsky_timezone_collation = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE $rktsky_timezone_stats (\n day date NOT NULL,\n provider varchar(20) NOT NULL,\n reason varchar(32) NOT NULL,\n total bigint(20) unsigned NOT NULL DEFAULT 0,\n PRIMARY KEY  (day,provider,reason)\n) $rktsky_timezone_collation;" );
	$rktsky_timezone_legacy_day = gmdate( 'Y-m-d' );
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Approved owned-table fixture snapshot, schema migration or exact restoration requires live readback.
		$rktsky_timezone_stats,
		array(
			'day'      => $rktsky_timezone_legacy_day,
			'provider' => 'comments',
			'reason'   => 'accepted',
			'total'    => 11,
		)
	);
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Approved owned-table fixture snapshot, schema migration or exact restoration requires live readback.
		$rktsky_timezone_stats,
		array(
			'day'      => $rktsky_timezone_legacy_day,
			'provider' => 'cf7',
			'reason'   => 'honeypot',
			'total'    => 7,
		)
	);
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Approved owned-table synthetic fixture or exact snapshot restoration.
		$rktsky_timezone_stats,
		array(
			'day'      => gmdate( 'Y-m-d', time() - 120 * DAY_IN_SECONDS ),
			'provider' => 'cf7',
			'reason'   => 'keyword',
			'total'    => 3,
		)
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Approved expired owned-row fixture proves cleanup stays idle during incomplete migration.

	rktsky_timezone_expect( $rktsky_timezone_store->install(), 'The released UTC-only schema must upgrade successfully through the native schema API.' );
	$rktsky_timezone_legacy = rktsky_timezone_rows( 'stats' );
	rktsky_timezone_expect( 3 === count( $rktsky_timezone_legacy ) && array( 'UTC', 'UTC', 'UTC' ) === array_column( $rktsky_timezone_legacy, 'calendar' ) && 21 === array_sum( array_column( $rktsky_timezone_legacy, 'total' ) ), 'Actual schema upgrade must preserve historical UTC counts and their original dates without relabelling them as local.' );
	$rktsky_timezone_primary_alters = 0;
	$rktsky_timezone_observe_query  = static function ( string $query ) use ( &$rktsky_timezone_primary_alters, $rktsky_timezone_stats ): string {
		if ( str_contains( $query, $rktsky_timezone_stats ) && preg_match( '/\bALTER\s+TABLE\b.*\b(?:DROP|ADD)\s+PRIMARY\b/is', $query ) ) {
			++$rktsky_timezone_primary_alters;
		}
		return $query;
	};
	add_filter( 'query', $rktsky_timezone_observe_query );
	try {
		rktsky_timezone_expect( $rktsky_timezone_store->install() && 0 === $rktsky_timezone_primary_alters && rktsky_timezone_rows( 'stats' ) === $rktsky_timezone_legacy, 'An already upgraded primary key must be a row-preserving no-op rather than another ALTER.' );
	} finally {
		remove_filter( 'query', $rktsky_timezone_observe_query );
	}

	// Simulate a server accepting the DDL request without actually replacing its key.
	$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP PRIMARY KEY, ADD PRIMARY KEY (day,provider,reason)', $rktsky_timezone_stats ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Approved owned-table fixture snapshot, schema migration or exact restoration requires live readback.
	update_option( 'rktsky_antispam_schema', '4', false );
	$rktsky_timezone_block_primary = static function ( string $query ) use ( $rktsky_timezone_stats ): string {
		if ( str_contains( $query, $rktsky_timezone_stats ) && preg_match( '/\bALTER\s+TABLE\b.*\b(?:DROP|ADD)\s+PRIMARY\b/is', $query ) ) {
			return 'SELECT 1';
		}
		return $query;
	};
	add_filter( 'query', $rktsky_timezone_block_primary );
	try {
		$rktsky_timezone_failed_store = new Store();
		rktsky_timezone_expect( ! $rktsky_timezone_failed_store->install() && ! Plugin::install_site() && '4' === get_option( 'rktsky_antispam_schema' ), 'A no-op primary-key migration must fail native readback without advancing the durable schema marker.' );
		$rktsky_timezone_failed_store->record( 'comments', 'honeypot', $rktsky_timezone_legacy_day );
		$rktsky_timezone_failed_store->reclassify( 'comments', $rktsky_timezone_legacy_day, 'UTC' );
		$rktsky_timezone_failed_store->cleanup();
		$rktsky_timezone_failed_token = new Token();
		$rktsky_timezone_failed_proof = $rktsky_timezone_failed_token->issue( 'cf7', 'timezone-migration' );
		$rktsky_timezone_failed_guard = new Guard( $rktsky_timezone_settings, $rktsky_timezone_failed_token, $rktsky_timezone_failed_store );
		rktsky_timezone_expect(
			'' === $rktsky_timezone_failed_guard->check(
				'cf7',
				'timezone-migration',
				array(
					'rktsky_antispam_token'               => $rktsky_timezone_failed_proof['token'],
					$rktsky_timezone_failed_proof['trap'] => '',
				)
			) && 'replay' === $rktsky_timezone_failed_guard->check(
				'cf7',
				'timezone-migration',
				array(
					'rktsky_antispam_token'               => $rktsky_timezone_failed_proof['token'],
					$rktsky_timezone_failed_proof['trap'] => '',
				)
			),
			'Replay protection must remain atomic while unrelated statistics migration is unavailable.'
		);
		rktsky_timezone_expect( rktsky_timezone_rows( 'stats' ) === $rktsky_timezone_legacy && array() === $rktsky_timezone_failed_store->history() && 0 === $rktsky_timezone_failed_store->summary()['blocked'], 'Failed migration must leave legacy aggregates untouched and stop daily writes, reads and retention until repair.' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Approved owned-table synthetic fixture or exact snapshot restoration.
			$wpdb->prefix . 'rktsky_antispam_totals',
			array(
				'provider' => 'comments',
				'total'    => 7,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Approved independent lifetime fixture proves daily schema failure does not erase other metrics.
		$rktsky_timezone_fault_feedback = new Feedback();
		rktsky_timezone_expect( $rktsky_timezone_fault_feedback->report_delivered( 'cf7', 'timezone-fault', false ), 'A reviewed native case must remain available independently of daily reporting migration.' );
		$_GET                        = array(
			'page' => 'rocketsky-antispam',
			'tab'  => 'overview',
		);
		$rktsky_timezone_fault_admin = new Admin( $rktsky_timezone_settings, $rktsky_timezone_failed_store, new Quarantine( $rktsky_timezone_settings ), $rktsky_timezone_fault_feedback );
		ob_start();
		$rktsky_timezone_fault_admin->render();
		$rktsky_timezone_fault_overview = (string) ob_get_clean();
		ob_start();
		Dashboard::render();
		$rktsky_timezone_fault_widget        = (string) ob_get_clean();
		$rktsky_timezone_fault_message       = __( 'Daily statistics are unavailable while their storage upgrade is incomplete. Existing records have been kept.', 'rocketsky-antispam' );
		$rktsky_timezone_fault_document      = new DOMDocument();
		$rktsky_timezone_previous_xml_errors = libxml_use_internal_errors( true );
		try {
			$rktsky_timezone_fault_document->loadHTML( $rktsky_timezone_fault_overview );
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $rktsky_timezone_previous_xml_errors );
		}
		$rktsky_timezone_fault_xpath = new DOMXPath( $rktsky_timezone_fault_document );
		rktsky_timezone_expect( str_contains( $rktsky_timezone_fault_overview, $rktsky_timezone_fault_message ) && 0 === $rktsky_timezone_fault_xpath->query( '//*[@aria-labelledby="rktsky-activity-heading"]//*[contains(@class,"__metrics") or contains(@class,"__daily-list")]' )->length, 'The real overview must explain incomplete migration without inventing daily zero counts or an empty date series.' );
		rktsky_timezone_expect( str_contains( $rktsky_timezone_fault_widget, $rktsky_timezone_fault_message ) && ! str_contains( $rktsky_timezone_fault_widget, 'rktsky-widget-stopped' ) && ! str_contains( $rktsky_timezone_fault_widget, '<tbody>' ), 'The real native dashboard must distinguish unavailable daily statistics from an empty period.' );
		rktsky_timezone_expect( str_contains( $rktsky_timezone_fault_overview, sprintf( /* translators: %s: independent lifetime comment rejection count. */ __( 'Comments stopped since the last statistics reset: %s.', 'rocketsky-antispam' ), '7' ) ) && str_contains( $rktsky_timezone_fault_widget, 'id="rktsky-widget-comments">7<' ) && str_contains( $rktsky_timezone_fault_widget, sprintf( /* translators: 1: reviewed cases, 2: pending cases. */ __( '%1$d reviewed · %2$d awaiting review', 'rocketsky-antispam' ), 1, 0 ) ), 'Native overview and widget must preserve independent lifetime and reviewed metrics during daily migration failure.' );

	} finally {
		remove_filter( 'query', $rktsky_timezone_block_primary );
	}
	rktsky_timezone_expect( Plugin::install_site() && Plugin::SCHEMA === get_option( 'rktsky_antispam_schema' ) && rktsky_timezone_rows( 'stats' ) === $rktsky_timezone_legacy, 'A native retry after removing the failure must publish schema5 without changing historical counts.' );
	$rktsky_timezone_store = new Store();

	$rktsky_timezone_store->record( 'comments', '', $rktsky_timezone_legacy_day );
	$rktsky_timezone_history = $rktsky_timezone_store->history();
	rktsky_timezone_expect( 4 === count( rktsky_timezone_rows( 'stats' ) ) && 1 === count( $rktsky_timezone_history ) && 'UTC' === $rktsky_timezone_history[0]['calendar'] && 11 === $rktsky_timezone_history[0]['accepted'] && 7 === $rktsky_timezone_history[0]['blocked'], 'The migrated primary key must keep current local activity separate from identically dated retained UTC history.' );

	$rktsky_timezone_store->clear();
	foreach ( $rktsky_timezone_dates as $rktsky_timezone_day ) {
		if ( '2026-03-27' !== $rktsky_timezone_day ) {
			$rktsky_timezone_store->record( 'cf7', '', $rktsky_timezone_day );
		}
	}
	foreach ( array( '2026-03-23', '2026-03-28', '2026-03-31' ) as $rktsky_timezone_day ) {
		$rktsky_timezone_store->record( 'cf7', 'honeypot', $rktsky_timezone_day );
	}
	$rktsky_timezone_summary = $rktsky_timezone_store->summary( 7, $rktsky_timezone_spring );
	rktsky_timezone_expect( 6 === $rktsky_timezone_summary['accepted'] && 1 === $rktsky_timezone_summary['blocked'] && ! in_array( '2026-03-23', array_column( $rktsky_timezone_summary['daily'], 'day' ), true ) && ! in_array( '2026-03-31', array_column( $rktsky_timezone_summary['daily'], 'day' ), true ), 'Native aggregation must include exactly the seven local dates through today and exclude earlier and future dates.' );
	$rktsky_timezone_store->clear();
	$rktsky_timezone_store->record( 'cf7', '' );
	$rktsky_timezone_store->record( 'cf7', 'honeypot' );
	$rktsky_timezone_current_summary = $rktsky_timezone_store->summary();
	$rktsky_timezone_zurich_rows     = rktsky_timezone_rows( 'stats' );
	rktsky_timezone_set( 'Asia/Kathmandu' );
	rktsky_timezone_expect( 0 === $rktsky_timezone_store->summary()['accepted'] && rktsky_timezone_rows( 'stats' ) === $rktsky_timezone_zurich_rows, 'Changing the website timezone must neither relabel nor reset previously recorded dates.' );
	$rktsky_timezone_history = $rktsky_timezone_store->history();
	rktsky_timezone_expect( 1 === count( $rktsky_timezone_history ) && 'Europe/Zurich' === $rktsky_timezone_history[0]['calendar'] && 1 === $rktsky_timezone_history[0]['accepted'] && 1 === $rktsky_timezone_history[0]['blocked'], 'Retained previous-calendar totals must remain available separately from current-calendar activity.' );
	rktsky_timezone_set( 'Europe/Zurich' );
	rktsky_timezone_expect( $rktsky_timezone_store->summary() === $rktsky_timezone_current_summary, 'Returning to an earlier website timezone must recover its unchanged local series.' );

	$rktsky_timezone_store->clear();
	rktsky_timezone_set( 'Pacific/Kiritimati' );
	$rktsky_timezone_token  = new Token();
	$rktsky_timezone_guard  = new Guard( $rktsky_timezone_settings, $rktsky_timezone_token, $rktsky_timezone_store );
	$rktsky_timezone_before = time();
	$rktsky_timezone_proof  = $rktsky_timezone_token->issue( 'cf7', 'timezone-receipt' );
	$rktsky_timezone_posted = array(
		'rktsky_antispam_token'        => $rktsky_timezone_proof['token'],
		$rktsky_timezone_proof['trap'] => '',
	);
	rktsky_timezone_expect( '' === $rktsky_timezone_guard->check( 'cf7', 'timezone-receipt', $rktsky_timezone_posted ), 'A real unspent native proof must pass without any fake security clock.' );
	$rktsky_timezone_receipt = rktsky_timezone_rows( 'stats' )[0];
	rktsky_timezone_set( 'Etc/GMT+12' );
	rktsky_timezone_expect( Dates::day() !== $rktsky_timezone_receipt['day'] && 'Pacific/Kiritimati' === $rktsky_timezone_receipt['calendar'], 'The controlled site-zone change must cross a real calendar date boundary.' );
	$rktsky_timezone_guard->observe_spam( 'cf7', 'timezone-receipt', $rktsky_timezone_posted );
	$rktsky_timezone_after          = rktsky_timezone_rows( 'stats' );
	$rktsky_timezone_verdict_totals = array_column( $rktsky_timezone_after, 'total', 'reason' );
	rktsky_timezone_expect( 0 === (int) ( $rktsky_timezone_verdict_totals['accepted'] ?? -1 ) && 1 === (int) ( $rktsky_timezone_verdict_totals['native_spam'] ?? 0 ), 'Native reclassification must replace the original pass with exactly one spam verdict.' );
	rktsky_timezone_expect( 2 === count( $rktsky_timezone_after ) && array( $rktsky_timezone_receipt['day'] ) === array_values( array_unique( array_column( $rktsky_timezone_after, 'day' ) ) ) && array( 'Pacific/Kiritimati' ) === array_values( array_unique( array_column( $rktsky_timezone_after, 'calendar' ) ) ), 'Native spam reclassification must remain on the original event day and calendar across midnight and a timezone change.' );
	$rktsky_timezone_guard->observe_spam( 'cf7', 'timezone-receipt', $rktsky_timezone_posted );
	rktsky_timezone_expect( rktsky_timezone_rows( 'stats' ) === $rktsky_timezone_after, 'Repeated native spam observation must not count the same event again.' );
	$rktsky_timezone_verified = $rktsky_timezone_token->verify( $rktsky_timezone_proof['token'], 'cf7', 'timezone-receipt' );
	rktsky_timezone_expect( is_array( $rktsky_timezone_verified ) && $rktsky_timezone_verified['issued'] >= $rktsky_timezone_before && 7200 === $rktsky_timezone_verified['expires'] - $rktsky_timezone_verified['issued'] && ! $rktsky_timezone_store->claim( $rktsky_timezone_verified['digest'], $rktsky_timezone_verified['expires'] ), 'Timezone changes must preserve real token timestamps, lifetime, HMAC validation and consumed replay state.' );
	$rktsky_timezone_honeypot = $rktsky_timezone_token->issue( 'cf7', 'timezone-honeypot' );
	$rktsky_timezone_posted   = array(
		'rktsky_antispam_token'           => $rktsky_timezone_honeypot['token'],
		$rktsky_timezone_honeypot['trap'] => 'synthetic-bot',
	);
	rktsky_timezone_expect( 'honeypot' === $rktsky_timezone_guard->check( 'cf7', 'timezone-honeypot', $rktsky_timezone_posted ), 'A filled native trap must remain a technical rejection.' );
	$rktsky_timezone_before_rows = rktsky_timezone_rows( 'stats' );
	rktsky_timezone_set( 'Pacific/Kiritimati' );
	$rktsky_timezone_guard->observe_spam( 'cf7', 'timezone-honeypot', $rktsky_timezone_posted );
	rktsky_timezone_expect( rktsky_timezone_rows( 'stats' ) === $rktsky_timezone_before_rows, 'A prior honeypot rejection must never acquire a duplicate native-spam count after a date change.' );

	$rktsky_timezone_store->clear();
	rktsky_timezone_set( 'Europe/Zurich' );
	$rktsky_timezone_mail = array();
	foreach ( array( 'rktsky_antispam_digest_claim', 'rktsky_antispam_digest_status' ) as $rktsky_timezone_option ) {
		delete_option( $rktsky_timezone_option );
	}
	$rktsky_timezone_store->record( 'cf7', 'honeypot', '2026-03-29' );
	$rktsky_timezone_store->record( 'cf7', 'keyword', '2026-03-30' );
	Maintenance::digest( $rktsky_timezone_spring );
	rktsky_timezone_expect( 1 === count( $rktsky_timezone_mail ) && str_contains( $rktsky_timezone_mail[0]['message'], '2026-03-29' ) && str_contains( $rktsky_timezone_mail[0]['message'], 'Europe/Zurich' ) && ! str_contains( $rktsky_timezone_mail[0]['message'], '2026-03-30' ), 'Digest must describe the completed local DST day, excluding the current partial date.' );
	$rktsky_timezone_claim = get_option( 'rktsky_antispam_digest_claim' );
	rktsky_timezone_expect(
		array(
			'day'      => '2026-03-29',
			'calendar' => 'Europe/Zurich',
		) === $rktsky_timezone_claim && false === get_option( 'rktsky_antispam_digest_status' )['accepted_by_mailer'],
		'Failed mail handoff must retain the date/calendar claim without claiming delivery success.'
	);
	Maintenance::digest( $rktsky_timezone_spring );
	rktsky_timezone_expect( 1 === count( $rktsky_timezone_mail ), 'A failed local daily digest must not be automatically sent again.' );
	$rktsky_timezone_job_key = rktsky_timezone_job( 'Europe/Zurich', '2026-03-29' );
	$rktsky_timezone_job_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE job=%s', $wpdb->prefix . 'rktsky_antispam_jobs', $rktsky_timezone_job_key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Approved owned-table fixture snapshot, schema migration or exact restoration requires live readback.
	rktsky_timezone_expect( is_array( $rktsky_timezone_job_row ) && (int) $rktsky_timezone_job_row['expires'] > time() && (int) $rktsky_timezone_job_row['expires'] <= time() + 2 * DAY_IN_SECONDS, 'A synthetic reporting instant must never move atomic task expiry away from the actual Unix security clock.' );

	// Released UTC claim strings retain their meaning only for the UTC calendar.
	rktsky_timezone_set( 'UTC' );
	$rktsky_timezone_store->record( 'cf7', 'honeypot', '2026-03-29' );
	update_option( 'rktsky_antispam_digest_claim', '2026-03-29', false );
	Maintenance::digest( rktsky_timezone_instant( '2026-03-30T10:00:00Z' ) );
	rktsky_timezone_expect( 1 === count( $rktsky_timezone_mail ), 'An already used released UTC claim string must continue to prevent the same UTC digest.' );
	delete_option( 'rktsky_antispam_digest_claim' );
	Maintenance::digest( rktsky_timezone_instant( '2026-03-30T10:00:00Z' ) );
	rktsky_timezone_expect( 2 === count( $rktsky_timezone_mail ) && is_array( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE job=%s', $wpdb->prefix . 'rktsky_antispam_jobs', 'digest_2026-03-29' ), ARRAY_A ) ), 'UTC digests must preserve the released un-hashed atomic job key.' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Approved owned-table fixture snapshot, schema migration or exact restoration requires live readback.
	rktsky_timezone_set( 'Europe/Zurich' );
	$rktsky_timezone_store->record( 'cf7', 'honeypot', '2026-03-31' );
	update_option( 'rktsky_antispam_digest_claim', '2026-12-31', false );
	Maintenance::digest( rktsky_timezone_instant( '2026-04-01T10:00:00Z' ) );
	rktsky_timezone_expect( 3 === count( $rktsky_timezone_mail ), 'A released UTC-only string must not suppress a different calendar after timezone migration.' );

	// Race the repeated autumn hour in independent real database connections.
	delete_option( 'rktsky_antispam_digest_claim' );
	$rktsky_timezone_store->record( 'cf7', 'honeypot', '2026-10-24' );
	$rktsky_timezone_directory = sys_get_temp_dir() . '/rocketsky-timezone-mail-' . bin2hex( random_bytes( 12 ) );
	mkdir( $rktsky_timezone_directory, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- CLI-only private local worker files and pipes need direct bounded filesystem access.
	$rktsky_timezone_workers = array();
	foreach ( range( 1, 4 ) as $rktsky_timezone_index ) {
		$rktsky_timezone_command = array( PHP_BINARY );
		foreach ( array( 'RKTSKY_ANTISPAM_PHP_ICONV', 'RKTSKY_ANTISPAM_PHP_SIMPLEXML' ) as $rktsky_timezone_extension ) {
			if ( getenv( $rktsky_timezone_extension ) ) {
				$rktsky_timezone_command[] = '-d';
				$rktsky_timezone_command[] = 'extension=' . getenv( $rktsky_timezone_extension );
			}
		}
		$rktsky_timezone_command = array_merge( $rktsky_timezone_command, array( __FILE__, 'worker', (string) rktsky_timezone_instant( 0 === $rktsky_timezone_index % 2 ? '2026-10-25T00:30:00Z' : '2026-10-25T01:30:00Z' ), $rktsky_timezone_directory, (string) $rktsky_timezone_index ) );
		$rktsky_timezone_pipes   = array();
		$rktsky_timezone_process = proc_open( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Real separate PHP workers verify the owned atomic mail claim in the disposable fixture.
			$rktsky_timezone_command,
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$rktsky_timezone_pipes
		);
		if ( ! is_resource( $rktsky_timezone_process ) ) {
			throw new RuntimeException( 'The native timezone race worker could not start.' );
		}
		fclose( $rktsky_timezone_pipes[0] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI-only private local worker files and pipes need direct bounded filesystem access.
		$rktsky_timezone_workers[] = array( $rktsky_timezone_process, $rktsky_timezone_pipes );
	}
	foreach ( $rktsky_timezone_workers as $rktsky_timezone_worker ) {
		$rktsky_timezone_output = stream_get_contents( $rktsky_timezone_worker[1][1] );
		$rktsky_timezone_error  = stream_get_contents( $rktsky_timezone_worker[1][2] );
		fclose( $rktsky_timezone_worker[1][1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI-only private local worker files and pipes need direct bounded filesystem access.
		fclose( $rktsky_timezone_worker[1][2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI-only private local worker files and pipes need direct bounded filesystem access.
		rktsky_timezone_expect( 0 === proc_close( $rktsky_timezone_worker[0] ) && '' === trim( $rktsky_timezone_output ) && '' === trim( $rktsky_timezone_error ), 'Independent native digest workers must finish without runtime diagnostics.' );
	}
	$rktsky_timezone_attempts      = array();
	$rktsky_timezone_capture_files = glob( $rktsky_timezone_directory . '/*.json' );
	rktsky_timezone_expect( 4 === count( $rktsky_timezone_capture_files ), 'Every native race worker must produce its bounded private capture result.' );
	foreach ( $rktsky_timezone_capture_files as $rktsky_timezone_file ) {
		$rktsky_timezone_attempts = array_merge( $rktsky_timezone_attempts, json_decode( file_get_contents( $rktsky_timezone_file ), true, 32, JSON_THROW_ON_ERROR ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI-only private local worker files and pipes need direct bounded filesystem access.
	}
	rktsky_timezone_expect( 1 === count( $rktsky_timezone_attempts ) && str_contains( $rktsky_timezone_attempts[0]['message'], '2026-10-24' ), 'Four concurrent workers in both occurrences of the repeated local hour must claim exactly one prior-day mail attempt.' );

	$rktsky_timezone_mail = array();
	$rktsky_timezone_store->record( 'cf7', 'honeypot', '2026-10-25' );
	$rktsky_timezone_store->record( 'cf7', 'honeypot', '2026-10-26' );
	Maintenance::digest( rktsky_timezone_instant( '2026-10-25T23:30:00Z' ) );
	rktsky_timezone_expect( 1 === count( $rktsky_timezone_mail ) && str_contains( $rktsky_timezone_mail[0]['message'], '2026-10-25' ) && ! str_contains( $rktsky_timezone_mail[0]['message'], '2026-10-26' ), 'After the 25-hour autumn day, only that completed local date must appear in the digest.' );

	$rktsky_timezone_quarantine = new Quarantine( $rktsky_timezone_settings );
	$rktsky_timezone_quarantine->capture( 'cf7', 'timezone-date', 'honeypot', array( 'Message' => 'Synthetic local date test.' ) );
	$rktsky_timezone_record = $rktsky_timezone_quarantine->listing()['items'][0];
	$rktsky_timezone_raw    = $rktsky_timezone_quarantine->read( $rktsky_timezone_record['id'] );
	rktsky_timezone_set( 'Asia/Kathmandu' );
	update_option( 'date_format', 'd/m/Y' );
	update_option( 'time_format', 'H:i' );
	$rktsky_timezone_admin = new Admin( $rktsky_timezone_settings, $rktsky_timezone_store, $rktsky_timezone_quarantine );
	$_GET                  = array(
		'page'  => 'rocketsky-antispam',
		'tab'   => 'quarantine',
		'entry' => (string) $rktsky_timezone_record['id'],
	);
	ob_start();
	$rktsky_timezone_admin->render();
	$rktsky_timezone_detail = (string) ob_get_clean();
	unset( $_GET['entry'] );
	ob_start();
	$rktsky_timezone_admin->render();
	$rktsky_timezone_list     = (string) ob_get_clean();
	$rktsky_timezone_received = wp_date( 'd/m/Y H:i', $rktsky_timezone_record['created'], wp_timezone() );
	$rktsky_timezone_expires  = wp_date( 'd/m/Y H:i', $rktsky_timezone_record['expires'], wp_timezone() );
	rktsky_timezone_expect( str_contains( $rktsky_timezone_detail, $rktsky_timezone_received ) && str_contains( $rktsky_timezone_detail, $rktsky_timezone_expires ) && str_contains( $rktsky_timezone_list, $rktsky_timezone_received ), 'Real quarantine list and detail must honor the site timezone and both WordPress date/time formats.' );
	rktsky_timezone_expect( $rktsky_timezone_quarantine->read( $rktsky_timezone_record['id'] ) === $rktsky_timezone_raw && 7 * DAY_IN_SECONDS === $rktsky_timezone_record['expires'] - $rktsky_timezone_record['created'], 'Presentation timezone changes must not alter ciphertext, stored absolute timestamps or retention duration.' );
} finally {
	remove_filter( 'pre_wp_mail', 'rktsky_timezone_capture_mail', PHP_INT_MAX );
	foreach ( $rktsky_timezone_tables as $rktsky_timezone_suffix => $rktsky_timezone_rows ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . 'rktsky_antispam_' . $rktsky_timezone_suffix ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Approved owned-table fixture snapshot, schema migration or exact restoration requires live readback.
		foreach ( $rktsky_timezone_rows as $rktsky_timezone_row ) {
			$wpdb->insert( $wpdb->prefix . 'rktsky_antispam_' . $rktsky_timezone_suffix, $rktsky_timezone_row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Approved owned-table fixture snapshot, schema migration or exact restoration requires live readback.
		}
	}
	foreach ( $rktsky_timezone_options as $rktsky_timezone_option => $rktsky_timezone_value ) {
		if ( null === $rktsky_timezone_value ) {
			delete_option( $rktsky_timezone_option );
		} else {
			update_option( $rktsky_timezone_option, $rktsky_timezone_value, false );
		}
	}
	if ( '' !== $rktsky_timezone_directory && is_dir( $rktsky_timezone_directory ) ) {
		foreach ( glob( $rktsky_timezone_directory . '/*.json' ) as $rktsky_timezone_file ) {
			unlink( $rktsky_timezone_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- CLI-only private local worker files and pipes need direct bounded filesystem access.
		}
		rmdir( $rktsky_timezone_directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- CLI-only private local worker files and pipes need direct bounded filesystem access.
	}
	wp_set_current_user( $rktsky_timezone_user );
	$_GET    = $rktsky_timezone_query;
	$_SERVER = $rktsky_timezone_server;
}

foreach ( $rktsky_timezone_tables as $rktsky_timezone_suffix => $rktsky_timezone_snapshot ) {
	rktsky_timezone_expect( rktsky_timezone_rows( $rktsky_timezone_suffix ) === $rktsky_timezone_snapshot, 'Every approved owned table must match its exact pre-test row snapshot after restoration.' );
}
foreach ( $rktsky_timezone_options as $rktsky_timezone_option => $rktsky_timezone_value ) {
	rktsky_timezone_expect( get_option( $rktsky_timezone_option, null ) === $rktsky_timezone_value, 'Every affected native option must match its pre-test value after restoration.' );
}
echo 'PASS: ' . (int) $rktsky_timezone_assertions . " native timezone, migration, history, reclassification, mail and quarantine assertions.\n";
