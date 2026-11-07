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
	 * Creates per-site tables using the native schema API; never alters provider tables.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collation = $wpdb->get_charset_collate();
		$tokens    = $wpdb->prefix . 'rktsky_antispam_tokens';
		$stats     = $wpdb->prefix . 'rktsky_antispam_stats';
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
			PRIMARY KEY  (day,provider,reason)
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
		update_option( 'rktsky_antispam_schema', '1', false );
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
	 * @param string $provider Known provider identifier.
	 * @param string $reason Empty for accepted, otherwise a known rejection reason.
	 * @param string $day Internal UTC event day; empty defaults to today.
	 * @return void
	 */
	public function record( string $provider, string $reason, string $day = '' ): void {
		global $wpdb;
		if ( ! in_array( $provider, Providers::ids(), true ) || ! in_array( $reason, array( '', 'missing', 'invalid', 'honeypot', 'too_fast', 'replay', 'pingback', 'storage', 'keyword', 'bbcode', 'pattern', 'pattern_limit', 'language', 'country', 'native_spam' ), true ) ) {
			return;
		}
		$day = preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $day ) ? $day : gmdate( 'Y-m-d' );
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (day, provider, reason, total) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE total = total + 1', $wpdb->prefix . 'rktsky_antispam_stats', $day, $provider, '' === $reason ? 'accepted' : $reason ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic owned aggregate, no matching Core API or cacheable result.
		if ( 'comments' === $provider && '' !== $reason ) {
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (provider,total) VALUES (%s,1) ON DUPLICATE KEY UPDATE total=total+1', $wpdb->prefix . 'rktsky_antispam_totals', $provider ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Permanent anonymous counter, not subject to daily-row retention.
		}
	}

	/**
	 * Replaces a prior pass with native spam for the same observed processing event.
	 *
	 * @since 0.2.0
	 * @param string $provider Known native provider.
	 * @param string $day UTC day of the earlier pass, including midnight crossings.
	 * @return void
	 */
	public function reclassify( string $provider, string $day ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE %i SET total = CASE WHEN total > 0 THEN total - 1 ELSE 0 END WHERE day=%s AND provider=%s AND reason='accepted'", $wpdb->prefix . 'rktsky_antispam_stats', $day, $provider ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reclassifies only an already recorded native event in an owned table.
		$this->record( $provider, 'native_spam', $day );
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
	 * Reads a bounded UTC-day summary for the plugin's administrator screen.
	 *
	 * @since 0.1.0
	 * @param int $days Inclusive day count, bounded to ninety.
	 * @return array{accepted:int,blocked:int,reasons:array,daily:array} Aggregate-only data.
	 */
	public function summary( int $days = 7 ): array {
		global $wpdb;
		$days   = max( 1, min( 90, $days ) );
		$since  = gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT day, reason, SUM(total) AS total FROM %i WHERE day >= %s GROUP BY day, reason ORDER BY day ASC', $wpdb->prefix . 'rktsky_antispam_stats', $since ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded administrator-only live aggregate over an owned table.
		$result = array(
			'accepted' => 0,
			'blocked'  => 0,
			'reasons'  => array(),
			'daily'    => array(),
		);
		$daily  = array();
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
	 * @return void
	 */
	public function cleanup(): void {
		global $wpdb;
		$retention = ( new Settings() )->all()['retention_days'];
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires < %d LIMIT 1000', $wpdb->prefix . 'rktsky_antispam_jobs', time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Expiry affects only fixed internal task metadata, never visitor data.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires < %d LIMIT 10000', $wpdb->prefix . 'rktsky_antispam_tokens', time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded expiry deletion over an owned indexed ledger.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', $wpdb->prefix . 'rktsky_antispam_stats', gmdate( 'Y-m-d', time() - ( $retention - 1 ) * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native SQL removes only expired owned aggregate rows.
	}
}
