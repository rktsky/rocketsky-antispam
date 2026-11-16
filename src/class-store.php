<?php
/**
 * Stores atomic replay claims and anonymous daily counters in dedicated tables.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps security state separate from statistics and provider-owned submissions.
 *
 * @since 0.1.0
 */
final class Store {
	/**
	 * Most recent failed claim's non-sensitive diagnostic code.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private string $claim_failure = 'replay';
	/**
	 * This instance's verified installation outcomes, separated by switched site.
	 *
	 * A failed migration must not let a new calendar merge into the old unique key.
	 * Token claims do not depend on this reporting-only readiness state.
	 *
	 * @since 0.5.3
	 * @var array<int,bool>
	 */
	private array $statistics_ready = array();
	/**
	 * Creates per-site tables using the native schema API; never alters provider tables.
	 *
	 * @since 0.1.0
	 * @since 0.5.3 Preserves legacy UTC aggregates and verifies the calendar-aware key.
	 * @return bool Whether the statistics columns and unique calendar key are ready.
	 */
	public function install(): bool {
		global $wpdb;
		$this->statistics_ready[ get_current_blog_id() ] = false;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collation = $wpdb->get_charset_collate();
		$tokens    = $wpdb->prefix . 'rktsky_antispam_tokens';
		$stats     = $wpdb->prefix . 'rktsky_antispam_stats';
		$exists    = $stats === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $stats ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads only the approved owned table before a trusted schema migration.
		// dbDelta adds columns but cannot replace an existing primary key. Omitting
		// that declaration for existing tables also avoids competing ADD PRIMARY calls.
		$primary = $exists ? '' : ",\n\t\t\tPRIMARY KEY  (day,provider,reason,calendar)";
		dbDelta(
			"CREATE TABLE $tokens (
			digest char(64) NOT NULL,
			expires bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (digest),
			KEY expires (expires)
		) $collation;"
		);
		$totals = $wpdb->prefix . 'rktsky_antispam_totals';
		dbDelta(
			"CREATE TABLE $totals (
			provider varchar(20) NOT NULL,
			total bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (provider)
		) $collation;"
		);
		dbDelta(
			"CREATE TABLE $stats (
			day date NOT NULL,
			provider varchar(20) NOT NULL,
			reason varchar(32) NOT NULL,
			total bigint(20) unsigned NOT NULL DEFAULT 0,
			calendar varchar(64) NOT NULL DEFAULT 'UTC'$primary,
			KEY calendar (calendar,day)
		) $collation;"
		);
		$jobs = $wpdb->prefix . 'rktsky_antispam_jobs';
		dbDelta(
			"CREATE TABLE $jobs (
			job varchar(64) NOT NULL,
			expires bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (job),
			KEY expires (expires)
		) $collation;"
		);
		$key = $this->statistics_key();
		if ( array( 'day', 'provider', 'reason' ) === $key ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP PRIMARY KEY, ADD PRIMARY KEY (day,provider,reason,calendar)', $stats ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The approved owned-table migration preserves every row and replaces only the known legacy key in one DDL operation.
		} elseif ( array() === $key ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD PRIMARY KEY (day,provider,reason,calendar)', $stats ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Repairs an incomplete owned schema without deleting or rewriting aggregates.
		}
		$columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $stats ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Readback prevents a failed column migration from receiving the new schema marker.
		$indexed = array_column( (array) $columns, null, 'Field' );
		$ready   = array( 'day', 'provider', 'reason', 'total', 'calendar' ) === array_keys( $indexed )
			&& 'varchar(64)' === strtolower( $indexed['calendar']['Type'] )
			&& 'NO' === $indexed['calendar']['Null']
			&& 'UTC' === $indexed['calendar']['Default']
			&& array( 'day', 'provider', 'reason', 'calendar' ) === $this->statistics_key();
		$this->statistics_ready[ get_current_blog_id() ] = $ready;
		return $ready;
	}

	/**
	 * Keeps daily reporting idle while its calendar-aware unique key is incomplete.
	 *
	 * The durable marker is published only after install() verifies the new key.
	 * An explicit failure on this instance overrides that marker. No site-specific
	 * result is reused after switch_to_blog(), and replay protection stays independent.
	 * This read-only capability exposes no counts or privileged records to callers.
	 *
	 * @since 0.5.3
	 * @return bool Read-only readiness for the current site's calendar-aware reporting.
	 */
	public function statistics_available(): bool {
		$site = get_current_blog_id();
		return $this->statistics_ready[ $site ] ?? ( Plugin::SCHEMA === get_option( 'rktsky_antispam_schema' ) );
	}

	/**
	 * Reads the approved statistics primary key in its actual database order.
	 *
	 * @since 0.5.3
	 * @return string[] Ordered primary-key columns, or an empty array when absent.
	 */
	private function statistics_key(): array {
		global $wpdb;
		$indexes = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $wpdb->prefix . 'rktsky_antispam_stats' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Trusted installation verifies only its owned atomic aggregate key.
		$columns = array();
		foreach ( (array) $indexes as $index ) {
			if ( 'PRIMARY' === $index['Key_name'] && 0 === (int) $index['Non_unique'] ) {
				$columns[ (int) $index['Seq_in_index'] ] = $index['Column_name'];
			}
		}
		ksort( $columns );
		return array_values( $columns );
	}

	/**
	 * Claims a bounded internal task without querying WordPress's options table.
	 *
	 * The unique owned-table key provides one winner across concurrent workers.
	 * Job names contain fixed task/date metadata, never submission identifiers.
	 * Claims survive mail failure; cleanup cannot cause a retry on the same day.
	 *
	 * @since 0.3.0
	 * @param string $job Fixed internal task identifier.
	 * @param int    $expires Expiry at most two days in the future.
	 * @return bool Whether this process inserted the unique task claim.
	 */
	public function claim_job( string $job, int $expires ): bool {
		if ( 1 !== preg_match( '/^[a-z0-9_-]{1,64}$/D', $job ) || $expires <= time() || $expires > time() + 2 * DAY_IN_SECONDS ) {
			return false;
		}
		global $wpdb;
		return 1 === $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (job,expires) VALUES (%s,%d)', $wpdb->prefix . 'rktsky_antispam_jobs', $job, $expires ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only the owned task ledger is changed; a unique key arbitrates parallel workers through the official WordPress database API.
	}

	/**
	 * Atomically consumes one random token; a duplicate or storage failure fails closed.
	 *
	 * @since 0.1.0
	 * @param string $digest Site-keyed SHA-256 token digest; never form content.
	 * @param int    $expires Token expiry as a UTC Unix timestamp.
	 * @return bool True only when this request inserted the first claim.
	 */
	public function claim( string $digest, int $expires ): bool {
		global $wpdb;
		$this->claim_failure = 'invalid';
		if ( ! preg_match( '/^[a-f0-9]{64}$/D', $digest ) || $expires < time() || $expires > time() + 7200 ) {
			return false;
		}
		$inserted            = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (digest, expires) VALUES (%s, %d)', $wpdb->prefix . 'rktsky_antispam_tokens', $digest, $expires ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dedicated security ledger needs an atomic unique-key write; object cache cannot replace it.
		$this->claim_failure = false === $inserted ? 'storage' : 'replay';
		return 1 === $inserted;
	}

	/**
	 * Distinguishes a duplicate token from a failed storage write without exposing SQL.
	 *
	 * @since 0.1.0
	 * @return string Fixed diagnostic for the immediately preceding failed claim.
	 */
	public function claim_failure(): string {
		return $this->claim_failure;
	}

	/**
	 * Counts a fixed reason without storing any submission or visitor identifier.
	 *
	 * @since 0.1.0
	 * @since 0.5.3 Records the site's calendar separately from legacy UTC days.
	 * @param string $provider Known provider identifier.
	 * @param string $reason Empty for accepted, otherwise a known rejection reason.
	 * @param string $day Observed calendar date; empty defaults to today in that calendar.
	 * @param string $calendar Observed timezone identifier; empty uses the current site.
	 * @return void
	 */
	public function record( string $provider, string $reason, string $day = '', string $calendar = '' ): void {
		global $wpdb;
		if ( ! $this->statistics_available() || ! in_array( $provider, Providers::ids(), true ) || ! in_array( $reason, array( '', 'missing', 'invalid', 'honeypot', 'too_fast', 'replay', 'pingback', 'storage', 'keyword', 'bbcode', 'pattern', 'pattern_limit', 'language', 'country', 'native_spam' ), true ) ) {
			return;
		}
		$calendar = '' === $calendar ? Dates::calendar() : $calendar;
		$zone     = $this->calendar_zone( $calendar );
		if ( null === $zone ) {
			return;
		}
		$calendar = '+00:00' === $calendar ? 'UTC' : $calendar;
		$day      = '' === $day ? Dates::day( 0, null, $zone ) : $day;
		if ( ! $this->valid_day( $day ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (day, provider, reason, total, calendar) VALUES (%s, %s, %s, 1, %s) ON DUPLICATE KEY UPDATE total = total + 1', $wpdb->prefix . 'rktsky_antispam_stats', $day, $provider, '' === $reason ? 'accepted' : $reason, $calendar ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic approved owned aggregate; the calendar prevents legacy and differently zoned dates from being merged.
		if ( 'comments' === $provider && '' !== $reason ) {
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (provider,total) VALUES (%s,1) ON DUPLICATE KEY UPDATE total=total+1', $wpdb->prefix . 'rktsky_antispam_totals', $provider ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Permanent anonymous counter, not subject to daily-row retention.
		}
	}

	/**
	 * Replaces a prior pass with native spam for the same observed processing event.
	 *
	 * @since 0.2.0
	 * @since 0.5.3 Preserves the earlier observation's calendar across timezone changes.
	 * @param string $provider Known native provider.
	 * @param string $day Calendar date of the earlier pass, including midnight crossings.
	 * @param string $calendar Earlier timezone identifier; empty uses the current site.
	 * @return void
	 */
	public function reclassify( string $provider, string $day, string $calendar = '' ): void {
		global $wpdb;
		$calendar = '' === $calendar ? Dates::calendar() : $calendar;
		if ( ! $this->statistics_available() || ! in_array( $provider, Providers::ids(), true ) || ! $this->valid_day( $day ) || null === $this->calendar_zone( $calendar ) ) {
			return;
		}
		$calendar = '+00:00' === $calendar ? 'UTC' : $calendar;
		$wpdb->query( $wpdb->prepare( "UPDATE %i SET total = CASE WHEN total > 0 THEN total - 1 ELSE 0 END WHERE day=%s AND provider=%s AND calendar=%s AND reason='accepted'", $wpdb->prefix . 'rktsky_antispam_stats', $day, $provider, $calendar ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reclassifies only the original calendar bucket in an approved owned table.
		$this->record( $provider, 'native_spam', $day, $calendar );
	}

	/**
	 * Returns the anonymous blocked-comment count since installation or manual reset.
	 *
	 * @since 0.2.0
	 * @return int All-time rejection count, including technical failures.
	 */
	public function blocked_comments(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT total FROM %i WHERE provider=%s', $wpdb->prefix . 'rktsky_antispam_totals', 'comments' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One administrator-only live read of a bounded owned counter.
	}

	/**
	 * Reads a bounded current-site-calendar summary for the administrator screen.
	 *
	 * @since 0.1.0
	 * @since 0.5.3 Excludes other timezones and dates beyond the requested local window.
	 * @param int      $days Inclusive calendar-day count, bounded to ninety.
	 * @param int|null $timestamp Optional true Unix timestamp defining the reporting date.
	 * @return array{accepted:int,blocked:int,reasons:array,daily:array} Aggregate-only data.
	 */
	public function summary( int $days = 7, ?int $timestamp = null ): array {
		global $wpdb;
		$result = array(
			'accepted' => 0,
			'blocked'  => 0,
			'reasons'  => array(),
			'daily'    => array(),
		);
		if ( ! $this->statistics_available() ) {
			return $result;
		}
		$timestamp = $timestamp ?? time();
		$days      = max( 1, min( 90, $days ) );
		$since     = Dates::day( -( $days - 1 ), $timestamp );
		$until     = Dates::day( 0, $timestamp );
		$rows      = $wpdb->get_results( $wpdb->prepare( 'SELECT day, reason, SUM(total) AS total FROM %i WHERE calendar=%s AND day BETWEEN %s AND %s GROUP BY day, reason ORDER BY day ASC', $wpdb->prefix . 'rktsky_antispam_stats', Dates::calendar(), $since, $until ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded live aggregate excludes incompatible historical calendar bases.
		$daily     = array();
		foreach ( (array) $rows as $row ) {
			$kind             = 'accepted' === $row['reason'] ? 'accepted' : 'blocked';
			$result[ $kind ] += (int) $row['total'];
			if ( ! isset( $daily[ $row['day'] ] ) ) {
				$daily[ $row['day'] ] = array(
					'day'      => $row['day'],
					'accepted' => 0,
					'blocked'  => 0,
				);
			}
			$daily[ $row['day'] ][ $kind ] += (int) $row['total'];
			if ( 'blocked' === $kind ) {
				$result['reasons'][ $row['reason'] ] = ( $result['reasons'][ $row['reason'] ] ?? 0 ) + (int) $row['total'];
			}
		}
		$result['daily'] = array_values( $daily );
		return $result;
	}

	/**
	 * Summarizes retained earlier timezones without relabeling their calendar dates.
	 *
	 * A legacy UTC daily count has no event timestamps and cannot be split into
	 * current local days. These aggregate-only groups therefore stay separate.
	 *
	 * @since 0.5.3
	 * @param int|null $timestamp True Unix instant shared with the surrounding report.
	 * @return array<int,array{calendar:string,accepted:int,blocked:int,first_day:string,last_day:string}> Retained groups using their original date boundaries.
	 */
	public function history( ?int $timestamp = null ): array {
		global $wpdb;
		if ( ! $this->statistics_available() ) {
			return array();
		}
		$retention = ( new Settings() )->all()['retention_days'];
		$history   = array();
		$current   = Dates::calendar();
		$timestamp = $timestamp ?? time();
		foreach ( $this->calendars() as $calendar => $zone ) {
			if ( $calendar === $current ) {
				continue;
			}
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT MIN(day) AS first_day, MAX(day) AS last_day, SUM(CASE WHEN reason='accepted' THEN total ELSE 0 END) AS accepted, SUM(CASE WHEN reason='accepted' THEN 0 ELSE total END) AS blocked FROM %i WHERE calendar=%s AND day BETWEEN %s AND %s", $wpdb->prefix . 'rktsky_antispam_stats', $calendar, Dates::day( -( $retention - 1 ), $timestamp, $zone ), Dates::day( 0, $timestamp, $zone ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One bounded aggregate per validated stored calendar; no submission or visitor data is queried.
			if ( is_array( $row ) && is_string( $row['first_day'] ) && is_string( $row['last_day'] ) ) {
				$history[] = array(
					'calendar'  => $calendar,
					'accepted'  => (int) $row['accepted'],
					'blocked'   => (int) $row['blocked'],
					'first_day' => $row['first_day'],
					'last_day'  => $row['last_day'],
				);
			}
		}
		return $history;
	}

	/**
	 * Resolves only named PHP timezones and bounded minute-offset site settings.
	 *
	 * @since 0.5.3
	 * @param string $calendar Stored or observed site timezone identifier.
	 * @return \DateTimeZone|null Valid timezone, or null for an invalid dimension.
	 */
	private function calendar_zone( string $calendar ): ?\DateTimeZone {
		static $named = null;
		if ( null === $named ) {
			$named = array_fill_keys( \DateTimeZone::listIdentifiers( \DateTimeZone::ALL_WITH_BC ), true );
		}
		if ( strlen( $calendar ) > 64 || ( ! isset( $named[ $calendar ] ) && 1 !== preg_match( '/^[+-](?:0[0-9]|1[0-4]):[0-5][0-9]$/D', $calendar ) ) ) {
			return null;
		}
		return new \DateTimeZone( $calendar );
	}

	/**
	 * Lists stored calendar dimensions within the finite allowed timezone space.
	 *
	 * @since 0.5.3
	 * @return array<string,\DateTimeZone> Validated calendars for bounded history and expiry work.
	 */
	private function calendars(): array {
		global $wpdb;
		$names  = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT calendar FROM %i ORDER BY calendar ASC LIMIT 4096', $wpdb->prefix . 'rktsky_antispam_stats' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only fixed aggregate dimensions are read; the bound exceeds all allowed named zones and minute offsets combined.
		$result = array();
		foreach ( (array) $names as $name ) {
			$zone = $this->calendar_zone( $name );
			if ( null !== $zone ) {
				$result[ $name ] = $zone;
			}
		}
		return $result;
	}

	/**
	 * Rejects invalid dates instead of relying on database date coercion.
	 *
	 * @since 0.5.3
	 * @param string $day Internal calendar date in ISO order.
	 * @return bool Whether the value names a real calendar date.
	 */
	private function valid_day( string $day ): bool {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $day, $parts ) ) {
			return false;
		}
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}

	/**
	 * Deletes aggregate counters while preserving replay security state.
	 *
	 * The caller must enforce administrator authorization and a CSRF nonce.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function clear(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . 'rktsky_antispam_stats' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit authenticated deletion of an owned aggregate table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . 'rktsky_antispam_totals' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Explicit reset also clears the anonymous lifetime counter.
	}

	/**
	 * Removes expired claims in bounded batches and old anonymous counters.
	 *
	 * WP-Cron may run late; expiry is always checked before a token is accepted.
	 *
	 * @since 0.1.0
	 * @since 0.5.3 Applies each retained calendar's own date boundary.
	 * @return void
	 */
	public function cleanup(): void {
		global $wpdb;
		$retention = ( new Settings() )->all()['retention_days'];
		$timestamp = time();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires < %d LIMIT 1000', $wpdb->prefix . 'rktsky_antispam_jobs', time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Expiry affects only fixed internal task metadata, never visitor data.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires < %d LIMIT 10000', $wpdb->prefix . 'rktsky_antispam_tokens', time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded expiry deletion over an owned indexed ledger.
		if ( ! $this->statistics_available() ) {
			return;
		}
		foreach ( $this->calendars() as $calendar => $zone ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE calendar=%s AND day < %s', $wpdb->prefix . 'rktsky_antispam_stats', $calendar, Dates::day( -( $retention - 1 ), $timestamp, $zone ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes only expired approved aggregates using their original calendar's day boundary.
		}
	}
}
