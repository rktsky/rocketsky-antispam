<?php
/**
 * Shows a compact native dashboard summary without querying submission contents.
 *
 * @var array{accepted:int,blocked:int,reasons:array,daily:array} $summary Seven-day activity.
 * @var int $total Rejected comments since installation or manual statistics reset.
 * @var array $days Seven UTC days with machine-readable dates and localized labels.
 * @var array $quality Reviewed-only outcome counts and fractional rates.
 * @package RktskyAntispam
 * @since 0.2.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="rktsky-antispam-widget">
	<p class="rktsky-antispam-widget__period"><?php esc_html_e( 'Last 7 days · UTC', 'rocketsky-antispam' ); ?></p>
	<?php if ( $summary['accepted'] + $summary['blocked'] > 0 ) : ?>
		<dl class="rktsky-antispam-widget__counts">
			<div><dt><?php esc_html_e( 'Stopped', 'rocketsky-antispam' ); ?></dt><dd><span id="rktsky-widget-stopped"><?php echo esc_html( number_format_i18n( $summary['blocked'] ) ); ?></span><span class="rktsky-antispam-widget__rate"><?php esc_html_e( 'Rejection rate', 'rocketsky-antispam' ); ?>: <?php echo esc_html( number_format_i18n( 100 * $summary['blocked'] / ( $summary['accepted'] + $summary['blocked'] ), 1 ) . '%' ); ?></span></dd></div>
			<div><dt><?php esc_html_e( 'Passed local checks', 'rocketsky-antispam' ); ?></dt><dd id="rktsky-widget-passed"><?php echo esc_html( number_format_i18n( $summary['accepted'] ) ); ?></dd></div>
		</dl>
		<p class="rktsky-antispam-widget__note"><?php esc_html_e( 'Includes expired tokens and technical failures.', 'rocketsky-antispam' ); ?></p>
	<?php else : ?>
		<p class="rktsky-antispam-widget__empty"><?php esc_html_e( 'No checks in this period.', 'rocketsky-antispam' ); ?></p>
	<?php endif; ?>
	<details class="rktsky-antispam-widget__daily">
		<summary><?php esc_html_e( 'Daily activity', 'rocketsky-antispam' ); ?></summary>
		<table>
			<caption class="screen-reader-text"><?php esc_html_e( 'Daily activity · all integrations · UTC', 'rocketsky-antispam' ); ?></caption>
			<thead><tr><th scope="col"><?php esc_html_e( 'Day', 'rocketsky-antispam' ); ?></th><th scope="col"><?php esc_html_e( 'Passed', 'rocketsky-antispam' ); ?></th><th scope="col"><?php esc_html_e( 'Stopped', 'rocketsky-antispam' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $days as $rktsky_antispam_day ) : ?>
				<tr><th scope="row"><time datetime="<?php echo esc_attr( $rktsky_antispam_day['day'] ); ?>"><?php echo esc_html( $rktsky_antispam_day['label'] ); ?></time></th><td><?php echo esc_html( number_format_i18n( $rktsky_antispam_day['accepted'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $rktsky_antispam_day['blocked'] ) ); ?>
				<?php if ( $rktsky_antispam_day['accepted'] + $rktsky_antispam_day['blocked'] > 0 ) : ?>
					<span class="rktsky-antispam-widget__daily-rate"><span class="screen-reader-text"><?php esc_html_e( 'Rejection rate', 'rocketsky-antispam' ); ?>: </span><?php echo esc_html( number_format_i18n( 100 * $rktsky_antispam_day['blocked'] / ( $rktsky_antispam_day['accepted'] + $rktsky_antispam_day['blocked'] ), 1 ) . '%' ); ?></span>
				<?php endif; ?>
				</td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</details>
	<dl class="rktsky-antispam-widget__lifetime"><div><dt><?php esc_html_e( 'Comments stopped in total', 'rocketsky-antispam' ); ?></dt><dd id="rktsky-widget-comments"><?php echo esc_html( number_format_i18n( $total ) ); ?></dd></div></dl>
	<section class="rktsky-antispam-widget__quality" aria-labelledby="rktsky-widget-quality-heading">
		<h3 id="rktsky-widget-quality-heading"><?php esc_html_e( 'Reviewed results', 'rocketsky-antispam' ); ?></h3>
		<?php if ( $quality['reviewed'] > 0 ) : ?>
			<p class="rktsky-antispam-widget__note"><?php echo esc_html( sprintf( /* translators: %d: observation window for cases, independent of when a case was reviewed. */ __( 'Based on submissions recorded in the last %d days.', 'rocketsky-antispam' ), (int) $quality['period_days'] ) ); ?></p>
			<dl class="rktsky-antispam-widget__rates">
				<?php
				if ( null !== $quality['precision'] ) :
					?>
					<div><dt><?php esc_html_e( 'Precision', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( number_format_i18n( 100 * $quality['precision'], 1 ) . '%' ); ?></dd></div><?php endif; ?>
				<?php
				if ( null !== $quality['recall'] ) :
					?>
					<div><dt><?php esc_html_e( 'Recall', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( number_format_i18n( 100 * $quality['recall'], 1 ) . '%' ); ?></dd></div><?php endif; ?>
				<?php
				if ( null !== $quality['accuracy'] ) :
					?>
					<div><dt><?php esc_html_e( 'Accuracy', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( number_format_i18n( 100 * $quality['accuracy'], 1 ) . '%' ); ?></dd></div><?php endif; ?>
			</dl>
			<p class="rktsky-antispam-widget__note"><?php esc_html_e( 'Based on reviewed submissions only.', 'rocketsky-antispam' ); ?></p>
		<?php else : ?>
			<p class="rktsky-antispam-widget__note"><?php esc_html_e( 'No reviewed results yet.', 'rocketsky-antispam' ); ?></p>
		<?php endif; ?>
		<?php if ( $quality['reviewed'] + $quality['pending'] > 0 ) : ?>
			<p class="rktsky-antispam-widget__note"><?php echo esc_html( sprintf( /* translators: 1: reviewed records, 2: unreviewed records. */ __( '%1$d reviewed · %2$d awaiting review', 'rocketsky-antispam' ), (int) $quality['reviewed'], (int) $quality['pending'] ) ); ?></p>
		<?php endif; ?>
	</section>
	<div class="rktsky-antispam-widget__actions"><a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=rocketsky-antispam' ) ); ?>"><?php esc_html_e( 'Overview', 'rocketsky-antispam' ); ?></a><a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=rocketsky-antispam&tab=quarantine' ) ); ?>"><?php esc_html_e( 'Review spam', 'rocketsky-antispam' ); ?></a></div>
</div>
