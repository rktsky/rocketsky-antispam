<?php
/**
 * Shows protection status and observed outcomes without configuration forms.
 *
 * @package RktskyAntispam
 * @since 0.4.0
 * @since 0.5.2 Uses effective quarantine availability for the storage status.
 * @var array<string,mixed> $options Validated settings used only for status.
 * @var array $summary Anonymous seven-day totals and fixed reason counts.
 * @var array $days Seven site-local calendar days, including the current partial day.
 * @var string $calendar Website timezone used for the current daily counts.
 * @var array $history Retained aggregate groups from earlier timezones.
 * @var array $quality Reviewed-outcome counts and nullable rates.
 * @var int $active_count Available, explicitly enabled provider integrations.
 * @var int $blocked_comments Lifetime operational comment rejection count.
 * @var float|null $rejection_rate Operational rejection fraction, never accuracy.
 * @var string $quarantine_status Effective quarantine state, including encryption readiness.
 * @var array<string,string> $view_urls Fixed local section links.
 */

defined( 'ABSPATH' ) || exit;
require __DIR__ . '/admin-header.php';
?>
<header class="rktsky-antispam__page-heading">
	<h2><?php esc_html_e( 'Overview', 'rocketsky-antispam' ); ?></h2>
	<p><?php esc_html_e( 'See protection status and recent activity. Open Review spam to check individual messages.', 'rocketsky-antispam' ); ?></p>
</header>
<section class="rktsky-antispam__status-grid" aria-label="<?php esc_attr_e( 'Protection status', 'rocketsky-antispam' ); ?>">
	<div class="rktsky-antispam__status-item"><span><?php esc_html_e( 'Integrations', 'rocketsky-antispam' ); ?></span><strong><?php echo esc_html( sprintf( /* translators: %d: available, enabled integrations, not a form count. */ __( '%d enabled', 'rocketsky-antispam' ), $active_count ) ); ?></strong><a href="<?php echo esc_url( $view_urls['integrations'] ); ?>"><?php esc_html_e( 'Manage integrations', 'rocketsky-antispam' ); ?></a></div>
	<div class="rktsky-antispam__status-item"><span><?php esc_html_e( 'Privacy-conscious mode', 'rocketsky-antispam' ); ?></span><strong><?php echo esc_html( $privacy_mode ? __( 'On', 'rocketsky-antispam' ) : __( 'Off', 'rocketsky-antispam' ) ); ?></strong><a href="<?php echo esc_url( $view_urls['settings'] . '#privacy' ); ?>"><?php esc_html_e( 'Privacy settings', 'rocketsky-antispam' ); ?></a></div>
	<div class="rktsky-antispam__status-item"><span><?php esc_html_e( 'Quarantine', 'rocketsky-antispam' ); ?></span><strong><?php echo esc_html( $quarantine_status ); ?></strong><a href="<?php echo esc_url( $view_urls['quarantine'] ); ?>"><?php esc_html_e( 'Review spam', 'rocketsky-antispam' ); ?></a></div>
</section>
<?php if ( 0 === $active_count ) : ?>
	<p class="rktsky-antispam__help-note"><?php esc_html_e( 'Choose at least one integration to start checking submissions.', 'rocketsky-antispam' ); ?> <a href="<?php echo esc_url( $view_urls['integrations'] ); ?>"><?php esc_html_e( 'Set up protection', 'rocketsky-antispam' ); ?></a></p>
<?php endif; ?>
<section class="rktsky-antispam__card rktsky-antispam__overview" aria-labelledby="rktsky-activity-heading">
	<div class="rktsky-antispam__section-title"><h2 id="rktsky-activity-heading"><?php esc_html_e( 'Activity', 'rocketsky-antispam' ); ?></h2><span><?php echo esc_html( sprintf( /* translators: %s: configured WordPress timezone name or fixed offset. */ __( 'Last 7 days · %s', 'rocketsky-antispam' ), $calendar ) ); ?></span></div>
	<?php if ( ! $statistics_available ) : ?>
		<p class="rktsky-antispam__help-note"><?php esc_html_e( 'Daily statistics are unavailable while their storage upgrade is incomplete. Existing records have been kept.', 'rocketsky-antispam' ); ?> <?php esc_html_e( 'Reload this page to retry. If the problem continues, ask your hosting provider to check database permissions and the WordPress error log.', 'rocketsky-antispam' ); ?></p>
	<?php else : ?>
	<div class="rktsky-antispam__metrics">
		<div class="rktsky-antispam__metric"><span><?php esc_html_e( 'Passed checks', 'rocketsky-antispam' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $summary['accepted'] ) ); ?></strong><p><?php esc_html_e( 'Other plugins may run additional checks.', 'rocketsky-antispam' ); ?></p></div>
		<div class="rktsky-antispam__metric rktsky-antispam__metric--blocked"><span><?php esc_html_e( 'Stopped submissions', 'rocketsky-antispam' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $summary['blocked'] ) ); ?></strong><p><?php esc_html_e( 'Includes failed verification checks.', 'rocketsky-antispam' ); ?></p></div>
		<div class="rktsky-antispam__metric"><span><?php esc_html_e( 'Rejection rate', 'rocketsky-antispam' ); ?></span><strong><?php echo esc_html( null === $rejection_rate ? '—' : number_format_i18n( 100 * $rejection_rate, 1 ) . '%' ); ?></strong><p><?php esc_html_e( 'Share of checked submissions that were stopped.', 'rocketsky-antispam' ); ?></p></div>
	</div>
		<?php if ( 0 === $checked_count ) : ?>
		<p class="rktsky-antispam__empty"><?php esc_html_e( 'No submissions have been checked in this period.', 'rocketsky-antispam' ); ?></p>
	<?php endif; ?>
	<details class="rktsky-antispam__disclosure">
		<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Daily activity', 'rocketsky-antispam' ); ?></span></summary>
		<div class="rktsky-antispam__daily-list">
			<?php foreach ( $days as $rktsky_antispam_day ) : ?>
				<div><strong><time datetime="<?php echo esc_attr( $rktsky_antispam_day['day'] ); ?>"><?php echo esc_html( $rktsky_antispam_day['label'] ); ?></time></strong><span><?php echo esc_html( sprintf( /* translators: %d: submissions passing local checks that day. */ __( '%d passed', 'rocketsky-antispam' ), (int) $rktsky_antispam_day['accepted'] ) ); ?></span><span><?php echo esc_html( sprintf( /* translators: %d: operational rejections that day. */ __( '%d stopped', 'rocketsky-antispam' ), (int) $rktsky_antispam_day['blocked'] ) ); ?></span></div>
			<?php endforeach; ?>
		</div>
	</details>
		<?php if ( ! empty( $summary['reasons'] ) ) : ?>
		<details class="rktsky-antispam__disclosure"><summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'View stopped submissions by check', 'rocketsky-antispam' ); ?></span></summary><dl class="rktsky-antispam__reasons">
			<?php foreach ( $summary['reasons'] as $rktsky_antispam_reason => $rktsky_antispam_count ) : ?>
				<div><dt><?php echo esc_html( $reason_labels[ $rktsky_antispam_reason ] ?? __( 'Protection check', 'rocketsky-antispam' ) ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $rktsky_antispam_count ) ); ?></dd></div>
			<?php endforeach; ?>
		</dl></details>
	<?php endif; ?>
	<?php endif; ?>
	<p class="rktsky-antispam__help-note"><?php echo esc_html( sprintf( /* translators: %s: lifetime operational comment rejection total. */ __( 'Comments stopped since the last statistics reset: %s.', 'rocketsky-antispam' ), number_format_i18n( $blocked_comments ) ) ); ?></p>
	<?php if ( ! empty( $history ) ) : ?>
		<details class="rktsky-antispam__disclosure" id="rktsky-earlier-calendars">
			<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Earlier time zones', 'rocketsky-antispam' ); ?></span></summary>
			<p><?php esc_html_e( 'These counts keep their original dates and time zones. They are separate from the current seven-day period.', 'rocketsky-antispam' ); ?></p>
			<dl class="rktsky-antispam__reasons">
				<?php foreach ( $history as $rktsky_antispam_period ) : ?>
					<div><dt><?php echo esc_html( $rktsky_antispam_period['calendar'] ); ?><br /><span><?php echo esc_html( $rktsky_antispam_period['first_day'] . ' – ' . $rktsky_antispam_period['last_day'] ); ?></span></dt><dd><?php echo esc_html( sprintf( /* translators: 1: submissions passing local checks, 2: rejected submissions, within this historical timezone group. */ __( '%1$d passed · %2$d stopped', 'rocketsky-antispam' ), $rktsky_antispam_period['accepted'], $rktsky_antispam_period['blocked'] ) ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
		</details>
	<?php endif; ?>
</section>
<?php require __DIR__ . '/quality.php'; ?>
<div class="rktsky-antispam__quick-actions">
	<a class="button" href="<?php echo esc_url( admin_url( 'edit-comments.php?comment_status=spam' ) ); ?>"><?php esc_html_e( 'Review spam comments', 'rocketsky-antispam' ); ?></a>
	<a class="button" href="<?php echo esc_url( $view_urls['quarantine'] ); ?>"><?php esc_html_e( 'Open quarantine', 'rocketsky-antispam' ); ?></a>
</div>
<?php require __DIR__ . '/sponsors.php'; ?>
</div>
