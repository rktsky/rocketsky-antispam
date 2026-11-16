<?php
/**
 * Prepares aggregate-only daily emails and hands them to WordPress's mailer.
 *
 * @package RktskyAntispam
 * @since 0.5.4
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps email presentation separate from the calendar and atomic sending claim.
 *
 * @since 0.5.4
 */
final class Digest {

	/**
	 * Renders equivalent HTML and plain text without reading visitor submissions.
	 *
	 * The caller supplies the completed reporting date in the site's date format.
	 * Site identity is bounded plain text; the template escapes every output value.
	 *
	 * @since 0.5.4
	 * @param int    $blocked Positive aggregate rejection count, including failed checks.
	 * @param string $date Localized reporting date, selected by the maintenance job.
	 * @param string $calendar WordPress time zone used for that reporting date.
	 * @return array{subject:string,html:string,text:string} Ready-to-send representations.
	 */
	public static function message( int $blocked, string $date, string $calendar ): array {
		$site_name = sanitize_text_field( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		if ( mb_strlen( $site_name, 'UTF-8' ) > 200 ) {
			$site_name = mb_substr( $site_name, 0, 200, 'UTF-8' ) . '…';
		}
		if ( '' === $site_name ) {
			$site_name = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		}
		$email = array(
			'language'       => get_bloginfo( 'language' ),
			'direction'      => is_rtl() ? 'rtl' : 'ltr',
			'site_name'      => $site_name,
			'title'          => __( 'Daily summary', 'rocketsky-antispam' ),
			'date'           => $date,
			'calendar'       => $calendar,
			'count'          => number_format_i18n( $blocked ),
			'count_label'    => _n( 'Submission stopped', 'Submissions stopped', $blocked, 'rocketsky-antispam' ),
			'note'           => __( 'The count includes failed form checks.', 'rocketsky-antispam' ),
			'activity_label' => __( 'View activity', 'rocketsky-antispam' ),
			'activity_url'   => admin_url( 'options-general.php?page=rocketsky-antispam#rktsky-activity-heading' ),
			'settings_label' => __( 'Email settings', 'rocketsky-antispam' ),
			'settings_url'   => admin_url( 'options-general.php?page=rocketsky-antispam&tab=settings#notifications' ),
			'footer'         => __( 'You receive this summary because daily email notifications are enabled for this website.', 'rocketsky-antispam' ),
		);
		ob_start();
		try {
			require dirname( RKTSKY_ANTISPAM_FILE ) . '/templates/email-digest.php';
		} finally {
			$html = (string) ob_get_clean();
		}
		return array(
			/* translators: %s: website title. */
			'subject' => sprintf( __( 'Rocketsky Antispam: daily summary for %s', 'rocketsky-antispam' ), $site_name ),
			'html'    => $html,
			'text'    => implode(
				"\n\n",
				array(
					'Rocketsky Antispam',
					$email['title'] . ' · ' . $site_name,
					$date . ' · ' . $calendar,
					$email['count'] . ' — ' . $email['count_label'],
					$email['note'],
					$email['activity_label'] . ":\n" . $email['activity_url'],
					$email['footer'],
					$email['settings_label'] . ":\n" . $email['settings_url'],
				)
			) . "\n",
		);
	}

	/**
	 * Sends one HTML digest with a plain text alternative through native wp_mail().
	 *
	 * The temporary hook only configures this exact, unmodified HTML body. Other
	 * messages, replaced bodies and existing text alternatives keep their sender's setup.
	 * The finally block removes the hook and restores AltBody
	 * even when another mail integration throws. Sender and transport remain native.
	 * A true result means mailer acceptance, not confirmed delivery; retry ownership
	 * remains with Maintenance's existing atomic daily claim.
	 *
	 * @since 0.5.4
	 * @param int    $blocked Positive aggregate rejection count.
	 * @param string $date Localized completed reporting date.
	 * @param string $calendar Reporting calendar's WordPress time zone.
	 * @return bool Whether WordPress explicitly accepted this mail handoff.
	 */
	public static function send( int $blocked, string $date, string $calendar ): bool {
		$message      = self::message( $blocked, $date, $calendar );
		$prepared     = null;
		$previous_alt = '';
		/**
		 * Adds the text alternative only to this digest's initialized native message.
		 *
		 * @since 0.5.4
		 * @param \PHPMailer\PHPMailer\PHPMailer $mailer WordPress's existing mailer instance.
		 * @return void
		 */
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native PHPMailer properties retain their documented names.
		$configure = static function ( \PHPMailer\PHPMailer\PHPMailer $mailer ) use ( $message, &$prepared, &$previous_alt ): void {
			if ( $message['html'] !== $mailer->Body || 'text/html' !== $mailer->ContentType || '' !== $mailer->AltBody ) {
				return;
			}
			$previous_alt    = $mailer->AltBody;
			$mailer->AltBody = $message['text'];
			$prepared        = $mailer;
		};
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		add_action( 'phpmailer_init', $configure, PHP_INT_MAX );
		try {
			return true === wp_mail( get_option( 'admin_email' ), $message['subject'], $message['html'], array( 'Content-Type: text/html; charset=UTF-8' ) );
		} finally {
			remove_action( 'phpmailer_init', $configure, PHP_INT_MAX );
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Restore only the native AltBody value assigned above.
			if ( null !== $prepared && $message['text'] === $prepared->AltBody ) {
				$prepared->AltBody = $previous_alt;
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}
}
