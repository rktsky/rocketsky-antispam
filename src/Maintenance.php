<?php
/**
 * Runs bounded retention work and optional aggregate-only administrator digests.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Uses native WordPress deletion/mail APIs without resending visitor submissions.
 *
 * @since 0.2.0
 */
final class Maintenance {
	/**
	 * Runs the scheduled per-site work; disabled settings cause no corresponding action.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public static function run(): void {
		$settings = new Settings();
		$options  = $settings->all();
		( new Quarantine( $settings ) )->cleanup();
		if ( $options['comment_spam_days'] > 0 ) {
			$comments = get_comments(
				array(
					'status'     => 'spam',
					'type__in'   => array( '', 'comment', 'review' ),
					'number'     => 100,
					'fields'     => 'ids',
					'orderby'    => 'comment_ID',
					'order'      => 'ASC',
					'date_query' => array(
						array(
							'column' => 'comment_date_gmt',
							'before' => gmdate( 'Y-m-d H:i:s', time() - $options['comment_spam_days'] * DAY_IN_SECONDS ),
						),
					),
				)
			);
			foreach ( $comments as $id ) {
				clean_comment_cache( $id );
				if ( 'spam' === wp_get_comment_status( $id ) ) {
					wp_delete_comment( $id, true );
				}
			}
		}
		if ( $options['notify_admin'] && ! $options['privacy_mode'] ) {
			self::digest();
		}
	}

	/**
	 * Claims at most one daily digest before calling wp_mail; failures are not retried blindly.
	 *
	 * Only yesterday's aggregate counts are mailed to WordPress's configured admin address.
	 *
	 * @since 0.2.0
	 * @since 0.5.3 Uses the completed site-local calendar day and its own calendar claim.
	 * @param int|null $timestamp Absolute reference instant for calendar selection; null uses now.
	 * @return void
	 */
	public static function digest( ?int $timestamp = null ): void {
		$options = ( new Settings() )->all();
		if ( ! $options['notify_admin'] || $options['privacy_mode'] ) {
			return;
		}
		$timestamp = $timestamp ?? time();
		$calendar  = Dates::calendar();
		$day       = Dates::day( -1, $timestamp, new \DateTimeZone( $calendar ) );
		$activity  = ( new Store() )->summary( 2, $timestamp );
		$blocked   = 0;
		foreach ( $activity['daily'] as $row ) {
			if ( $day === $row['day'] ) {
				$blocked = (int) $row['blocked'];
			}
		}
		if ( 0 === $blocked || ! is_email( get_option( 'admin_email' ) ) ) {
			return;
		}
		$name     = 'rktsky_antispam_digest_claim';
		$previous = get_option( $name, '' );
		if ( ( is_array( $previous ) && ( $previous['calendar'] ?? '' ) === $calendar && is_string( $previous['day'] ?? null ) && $previous['day'] >= $day ) || ( 'UTC' === $calendar && is_string( $previous ) && $previous >= $day ) ) {
			return;
		}
		$job = 'UTC' === $calendar ? 'digest_' . $day : 'digest_' . substr( hash( 'sha256', $calendar ), 0, 16 ) . '_' . $day;
		if ( ! ( new Store() )->claim_job( $job, time() + 2 * DAY_IN_SECONDS ) ) {
			return;
		}
		// Options use Core's API; task ownership is independently atomic above.
		update_option(
			$name,
			array(
				'day'      => $day,
				'calendar' => $calendar,
			),
			false
		);
		$sent = wp_mail(
			get_option( 'admin_email' ),
			__( 'Rocketsky Antispam: daily rejection summary', 'rocketsky-antispam' ),
			sprintf( /* translators: 1: localized date, 2: website timezone, 3: rejected submission count, 4: local settings URL. */ __( 'On %1$s (%2$s), Rocketsky Antispam stopped %3$d submissions. This includes failed verification checks. View the activity on your website: %4$s', 'rocketsky-antispam' ), wp_date( get_option( 'date_format' ), Dates::now( $timestamp )->setTime( 0, 0 )->modify( '-1 day' )->getTimestamp(), new \DateTimeZone( $calendar ) ), $calendar, $blocked, admin_url( 'options-general.php?page=rocketsky-antispam' ) )
		);
		update_option(
			'rktsky_antispam_digest_status',
			array(
				'day'                => $day,
				'calendar'           => $calendar,
				'accepted_by_mailer' => true === $sent,
			),
			false
		);
	}
}
