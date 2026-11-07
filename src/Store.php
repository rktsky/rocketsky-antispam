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
		dbDelta( "CREATE TABLE $tokens (
			digest char(64) NOT NULL,
			expires bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (digest),
			KEY expires (expires)
		) $collation;" );
		dbDelta( "CREATE TABLE $stats (
			day date NOT NULL,
			provider varchar(20) NOT NULL,
			reason varchar(32) NOT NULL,
			total bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,provider,reason)
		) $collation;" );
		update_option( 'rktsky_antispam_schema', '1', false );
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
		$inserted = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (digest, expires) VALUES (%s, %d)', $wpdb->prefix . 'rktsky_antispam_tokens', $digest, $expires ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dedicated security ledger needs an atomic unique-key write; object cache cannot replace it.
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
	 * @return void
	 */
	public function record( string $provider, string $reason ): void {
		global $wpdb;
		if ( ! in_array( $provider, array( 'comments', 'cf7', 'gravityforms', 'wpforms' ), true ) || ! in_array( $reason, array( '', 'missing', 'invalid', 'honeypot', 'too_fast', 'replay', 'pingback', 'storage' ), true ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (day, provider, reason, total) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE total = total + 1', $wpdb->prefix . 'rktsky_antispam_stats', gmdate( 'Y-m-d' ), $provider, '' === $reason ? 'accepted' : $reason ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic aggregate update in an owned table; no matching Core storage API.
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
		$result = array( 'accepted' => 0, 'blocked' => 0, 'reasons' => array(), 'daily' => array() );
		$daily  = array();
		foreach ( (array) $rows as $row ) {
			$kind = 'accepted' === $row['reason'] ? 'accepted' : 'blocked';
			$result[ $kind ] += (int) $row['total'];
			if ( ! isset( $daily[ $row['day'] ] ) ) {
				$daily[ $row['day'] ] = array( 'day' => $row['day'], 'accepted' => 0, 'blocked' => 0 );
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
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires < %d LIMIT 10000', $wpdb->prefix . 'rktsky_antispam_tokens', time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded expiry deletion over an owned indexed ledger.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', $wpdb->prefix . 'rktsky_antispam_stats', gmdate( 'Y-m-d', time() - ( $retention - 1 ) * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native SQL removes only expired owned aggregate rows.
	}
}
