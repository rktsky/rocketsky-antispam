<?php
/**
 * Shows protection status and observed outcomes without configuration forms.
 *
 * @package RktskyAntispam
 * @since 0.4.0
 * @var array<string,mixed> $options Validated settings used only for status.
 * @var array $summary Anonymous seven-day totals and fixed reason counts.
 * @var array $days Seven complete UTC-day aggregate rows.
 * @var array $quality Reviewed-outcome counts and nullable rates.
 * @var int $active_count Available, explicitly enabled provider integrations.
 * @var int $blocked_comments Lifetime operational comment rejection count.
 * @var float|null $rejection_rate Operational rejection fraction, never accuracy.
 * @var array<string,string> $view_urls Fixed local section links.
 */

defined( 'ABSPATH' ) || exit;
require __DIR__ . '/admin-header.php';
?>
<header class="rktsky-antispam__page-heading">
	<h2><?php esc_html_e( 'Overview', 'wp-antispam' ); ?></h2>
	<p><?php esc_html_e( 'Check protection status and recent activity. Review individual submissions under Review spam.', 'wp-antispam' ); ?></p>
</header>
<section class="rktsky-antispam__status-grid" aria-label="<?php esc_attr_e( 'Protection status', 'wp-antispam' ); ?>">
	<div class="rktsky-antispam__status-item"><span><?php esc_html_e( 'Integrations', 'wp-antispam' ); ?></span><strong><?php echo esc_html( sprintf( /* translators: %d: available, enabled integrations, not a form count. */ __( '%d enabled', 'wp-antispam' ), $active_count ) ); ?></strong><a href="<?php echo esc_url( $view_urls['integrations'] ); ?>"><?php esc_html_e( 'Manage integrations', 'wp-antispam' ); ?></a></div>
	<div class="rktsky-antispam__status-item"><span><?php esc_html_e( 'Privacy-conscious mode', 'wp-antispam' ); ?></span><strong><?php echo esc_html( $privacy_mode ? __( 'On', 'wp-antispam' ) : __( 'Off', 'wp-antispam' ) ); ?></strong><a href="<?php echo esc_url( $view_urls['settings'] . '#privacy' ); ?>"><?php esc_html_e( 'Privacy settings', 'wp-antispam' ); ?></a></div>
	<div class="rktsky-antispam__status-item"><span><?php esc_html_e( 'Quarantine', 'wp-antispam' ); ?></span><strong><?php echo esc_html( ! empty( $options['quarantine_enabled'] ) ? __( 'Storing new rejections', 'wp-antispam' ) : __( 'New storage is off', 'wp-antispam' ) ); ?></strong><a href="<?php echo esc_url( $view_urls['quarantine'] ); ?>"><?php esc_html_e( 'Review spam', 'wp-antispam' ); ?></a></div>
</section>
<?php if ( 0 === $active_count ) : ?>
	<p class="rktsky-antispam__help-note"><?php esc_html_e( 'Choose at least one integration to start checking submissions.', 'wp-antispam' ); ?> <a href="<?php echo esc_url( $view_urls['integrations'] ); ?>"><?php esc_html_e( 'Set up protection', 'wp-antispam' ); ?></a></p>
<?php endif; ?>
<section class="rktsky-antispam__card rktsky-antispam__overview" aria-labelledby="rktsky-activity-heading">
	<div class="rktsky-antispam__section-title"><h2 id="rktsky-activity-heading"><?php esc_html_e( 'Activity', 'wp-antispam' ); ?></h2><span><?php esc_html_e( 'Last 7 days · UTC', 'wp-antispam' ); ?></span></div>
	<div class="rktsky-antispam__metrics">
		<div class="rktsky-antispam__metric"><span><?php esc_html_e( 'Passed local checks', 'wp-antispam' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $summary['accepted'] ) ); ?></strong><p><?php esc_html_e( 'Other plugins may run additional checks.', 'wp-antispam' ); ?></p></div>
		<div class="rktsky-antispam__metric rktsky-antispam__metric--blocked"><span><?php esc_html_e( 'Stopped by WP Antispam', 'wp-antispam' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $summary['blocked'] ) ); ?></strong><p><?php esc_html_e( 'Includes expired tokens and technical failures.', 'wp-antispam' ); ?></p></div>
		<div class="rktsky-antispam__metric"><span><?php esc_html_e( 'Rejection rate', 'wp-antispam' ); ?></span><strong><?php echo esc_html( null === $rejection_rate ? '—' : number_format_i18n( 100 * $rejection_rate, 1 ) . '%' ); ?></strong><p><?php esc_html_e( 'Share of checked submissions. This is not detection accuracy.', 'wp-antispam' ); ?></p></div>
	</div>
	<?php if ( 0 === $checked_count ) : ?>
		<p class="rktsky-antispam__empty"><?php esc_html_e( 'No submissions checked in this period. Counts appear when a protected form is submitted.', 'wp-antispam' ); ?></p>
	<?php endif; ?>
	<details class="rktsky-antispam__disclosure">
		<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Daily activity', 'wp-antispam' ); ?></span></summary>
		<div class="rktsky-antispam__daily-list">
			<?php foreach ( $days as $rktsky_antispam_day ) : ?>
				<div><strong><?php echo esc_html( $rktsky_antispam_day['day'] ); ?></strong><span><?php echo esc_html( sprintf( /* translators: %d: submissions passing local checks that day. */ __( '%d passed', 'wp-antispam' ), (int) $rktsky_antispam_day['accepted'] ) ); ?></span><span><?php echo esc_html( sprintf( /* translators: %d: operational rejections that day. */ __( '%d stopped', 'wp-antispam' ), (int) $rktsky_antispam_day['blocked'] ) ); ?></span></div>
			<?php endforeach; ?>
		</div>
	</details>
	<?php if ( ! empty( $summary['reasons'] ) ) : ?>
		<details class="rktsky-antispam__disclosure"><summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'View stopped submissions by check', 'wp-antispam' ); ?></span></summary><dl class="rktsky-antispam__reasons">
			<?php foreach ( $summary['reasons'] as $rktsky_antispam_reason => $rktsky_antispam_count ) : ?>
				<div><dt><?php echo esc_html( $reason_labels[ $rktsky_antispam_reason ] ?? __( 'Protection check', 'wp-antispam' ) ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $rktsky_antispam_count ) ); ?></dd></div>
			<?php endforeach; ?>
		</dl></details>
	<?php endif; ?>
	<p class="rktsky-antispam__help-note"><?php echo esc_html( sprintf( /* translators: %s: lifetime operational comment rejection total. */ __( 'Stopped WordPress comments since the last statistics reset: %s.', 'wp-antispam' ), number_format_i18n( $blocked_comments ) ) ); ?></p>
</section>
<?php require __DIR__ . '/quality.php'; ?>
<div class="rktsky-antispam__quick-actions">
	<a class="button" href="<?php echo esc_url( admin_url( 'edit-comments.php?comment_status=spam' ) ); ?>"><?php esc_html_e( 'Review spam comments', 'wp-antispam' ); ?></a>
	<a class="button" href="<?php echo esc_url( $view_urls['quarantine'] ); ?>"><?php esc_html_e( 'Open quarantine', 'wp-antispam' ); ?></a>
</div>
<?php require __DIR__ . '/sponsors.php'; ?>
</div>
