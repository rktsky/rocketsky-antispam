<?php
/**
 * Uses the current WordPress site's calendar without shifting Unix timestamps.
 *
 * @package RktskyAntispam
 * @since 0.5.3
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Shares local calendar boundaries across statistics, mail and presentation.
 *
 * Values are resolved per call so switch_to_blog() and timezone changes do not
 * reuse another site's configuration. Token and retention clocks stay separate.
 *
 * @since 0.5.3
 */
final class Dates {
	/**
	 * Returns the site-local time, optionally for a known absolute test/event time.
	 *
	 * @since 0.5.3
	 * @param int|null           $timestamp True Unix timestamp; null uses Core's clock.
	 * @param \DateTimeZone|null $zone Explicit historical calendar; null uses this site.
	 * @return \DateTimeImmutable Local wall time representing the same instant.
	 */
	public static function now( ?int $timestamp = null, ?\DateTimeZone $zone = null ): \DateTimeImmutable {
		$now = null === $timestamp ? current_datetime() : new \DateTimeImmutable( '@' . $timestamp );
		return $now->setTimezone( $zone ?? wp_timezone() );
	}

	/**
	 * Identifies a stored calendar without confusing equivalent zero-offset names.
	 *
	 * @since 0.5.3
	 * @return string Native timezone name or fixed offset, at most 64 characters.
	 */
	public static function calendar(): string {
		$name = wp_timezone()->getName();
		return '+00:00' === $name ? 'UTC' : $name;
	}

	/**
	 * Selects a local calendar day, including 23- and 25-hour daylight-saving days.
	 *
	 * @since 0.5.3
	 * @param int                $offset Calendar days from today; -1 selects yesterday.
	 * @param int|null           $timestamp True Unix instant; null uses the current time.
	 * @param \DateTimeZone|null $zone Historical calendar or the current site's default.
	 * @return string ISO calendar date, independent of the PHP process timezone.
	 */
	public static function day( int $offset = 0, ?int $timestamp = null, ?\DateTimeZone $zone = null ): string {
		$day = self::now( $timestamp, $zone )->setTime( 0, 0 );
		if ( 0 !== $offset ) {
			$day = $day->modify( sprintf( '%+d days', $offset ) );
		}
		return $day->format( 'Y-m-d' );
	}

	/**
	 * Builds consecutive site-local dates with translated compact labels.
	 *
	 * @since 0.5.3
	 * @param int      $count Inclusive day count, bounded to ninety.
	 * @param int|null $timestamp True Unix instant; null uses Core's current time.
	 * @return array<int,array{day:string,label:string}> Oldest date first, through today.
	 */
	public static function days( int $count = 7, ?int $timestamp = null ): array {
		$zone  = wp_timezone();
		$today = self::now( $timestamp, $zone )->setTime( 0, 0 );
		$days  = array();
		for ( $offset = max( 1, min( 90, $count ) ) - 1; $offset >= 0; --$offset ) {
			$day    = $today->modify( '-' . $offset . ' days' );
			$days[] = array(
				'day'   => $day->format( 'Y-m-d' ),
				'label' => wp_date( 'j M', $day->getTimestamp(), $zone ),
			);
		}
		return $days;
	}

	/**
	 * Formats an absolute record time using WordPress's date and time preferences.
	 *
	 * @since 0.5.3
	 * @param int $timestamp True Unix timestamp, never a timestamp plus an offset.
	 * @return string Translated date and time in the current site's timezone.
	 */
	public static function format( int $timestamp ): string {
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp, wp_timezone() );
	}
}
