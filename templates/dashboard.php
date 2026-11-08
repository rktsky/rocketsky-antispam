<?php
/**
 * Renders escaped aggregate dashboard data; no submission contents are queried.
 *
 * @var int $total Rejected comments since installation or manual statistics reset.
 * @var array $days Bounded UTC-day series.
 * @var array $quality Reviewed-only quality counts and fractional rates.
 * @package RktskyAntispam
 * @since 0.2.0
 */

defined( 'ABSPATH' ) || exit;
?>
<p><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> <?php esc_html_e( 'comments rejected since installation or the last statistics reset.', 'rocketsky-antispam' ); ?></p>
<table class="widefat striped">
	<caption><?php esc_html_e( 'Daily activity · all integrations · UTC', 'rocketsky-antispam' ); ?></caption>
	<thead><tr><th scope="col"><?php esc_html_e( 'Day', 'rocketsky-antispam' ); ?></th><th scope="col"><?php esc_html_e( 'Passed', 'rocketsky-antispam' ); ?></th><th scope="col"><?php esc_html_e( 'Rejected', 'rocketsky-antispam' ); ?></th><th scope="col"><?php esc_html_e( 'Rejection rate', 'rocketsky-antispam' ); ?></th></tr></thead>
	<tbody>
	<?php foreach ( $days as $rktsky_antispam_day ) : ?>
		<tr><th scope="row"><?php echo esc_html( $rktsky_antispam_day['day'] ); ?></th><td><?php echo esc_html( number_format_i18n( $rktsky_antispam_day['accepted'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $rktsky_antispam_day['blocked'] ) ); ?></td><td><?php echo esc_html( $rktsky_antispam_day['accepted'] + $rktsky_antispam_day['blocked'] > 0 ? number_format_i18n( 100 * $rktsky_antispam_day['blocked'] / ( $rktsky_antispam_day['accepted'] + $rktsky_antispam_day['blocked'] ), 1 ) . '%' : '—' ); ?></td></tr>
	<?php endforeach; ?>
	</tbody>
</table>
<p><?php esc_html_e( 'Includes technical failures. The rejection rate is not a measured spam detection accuracy. Counts refresh when this dashboard loads.', 'rocketsky-antispam' ); ?></p>
<h3><?php esc_html_e( 'Reviewed detection results', 'rocketsky-antispam' ); ?></h3>
<p><?php echo esc_html( sprintf( /* translators: %d: number of days in the reviewed dataset. */ __( 'Reviewed entries from the last %d days.', 'rocketsky-antispam' ), (int) $quality['period_days'] ) ); ?></p>
<dl>
	<dt><?php esc_html_e( 'Precision', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( null === $quality['precision'] ? '—' : number_format_i18n( 100 * $quality['precision'], 1 ) . '%' ); ?></dd>
	<dt><?php esc_html_e( 'Recall', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( null === $quality['recall'] ? '—' : number_format_i18n( 100 * $quality['recall'], 1 ) . '%' ); ?></dd>
	<dt><?php esc_html_e( 'Accuracy', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( null === $quality['accuracy'] ? '—' : number_format_i18n( 100 * $quality['accuracy'], 1 ) . '%' ); ?></dd>
</dl>
<p><?php echo esc_html( sprintf( /* translators: 1: reviewed records, 2: unreviewed records. */ __( '%1$d reviewed · %2$d awaiting review', 'rocketsky-antispam' ), (int) $quality['reviewed'], (int) $quality['pending'] ) ); ?></p>
<p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=rocketsky-antispam' ) ); ?>"><?php esc_html_e( 'Open Rocketsky Antispam', 'rocketsky-antispam' ); ?></a></p>
