<?php
/**
 * Displays reviewed-outcome rates independently of the operational rejection count.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 * @var array<string,int|float|null> $quality Reviewed confusion counts and fractional rates.
 * @var array<string,string> $view_urls Allowlisted review and configuration links.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="rktsky-antispam__card rktsky-antispam__quality" aria-labelledby="rktsky-quality-heading">
	<div class="rktsky-antispam__section-title"><h2 id="rktsky-quality-heading"><?php esc_html_e( 'Reviewed results', 'rocketsky-antispam' ); ?></h2><span><?php echo esc_html( sprintf( /* translators: 1: reviewed records, 2: unreviewed records. */ __( '%1$d reviewed · %2$d awaiting review', 'rocketsky-antispam' ), (int) $quality['reviewed'], (int) $quality['pending'] ) ); ?></span></div>
	<p class="rktsky-antispam__quality-period"><?php echo esc_html( sprintf( /* translators: %d: observation window for cases, independent of when a case was reviewed. */ __( 'Based on submissions recorded in the last %d days.', 'rocketsky-antispam' ), (int) $quality['period_days'] ) ); ?></p>

	<?php if ( 0 === (int) $quality['reviewed'] ) : ?>
		<p class="rktsky-antispam__empty"><?php esc_html_e( 'Review spam or assess a delivered submission to see results here.', 'rocketsky-antispam' ); ?></p>
		<a class="button" href="<?php echo esc_url( $view_urls['quarantine'] ); ?>"><?php esc_html_e( 'Review spam', 'rocketsky-antispam' ); ?></a>
	<?php else : ?>
	<div class="rktsky-antispam__metrics">
		<div class="rktsky-antispam__metric"><span><?php esc_html_e( 'Precision', 'rocketsky-antispam' ); ?></span><strong><?php echo esc_html( null === $quality['precision'] ? '—' : number_format_i18n( 100 * $quality['precision'], 1 ) . '%' ); ?></strong><p><?php esc_html_e( 'Confirmed spam among reviewed rejections.', 'rocketsky-antispam' ); ?></p></div>
		<div class="rktsky-antispam__metric"><span><?php esc_html_e( 'Recall', 'rocketsky-antispam' ); ?></span><strong><?php echo esc_html( null === $quality['recall'] ? '—' : number_format_i18n( 100 * $quality['recall'], 1 ) . '%' ); ?></strong><p><?php esc_html_e( 'Detected spam among reviewed spam cases.', 'rocketsky-antispam' ); ?></p></div>
		<div class="rktsky-antispam__metric"><span><?php esc_html_e( 'Accuracy', 'rocketsky-antispam' ); ?></span><strong><?php echo esc_html( null === $quality['accuracy'] ? '—' : number_format_i18n( 100 * $quality['accuracy'], 1 ) . '%' ); ?></strong><p><?php esc_html_e( 'Correct decisions among all reviewed cases.', 'rocketsky-antispam' ); ?></p></div>
	</div>
	<?php endif; ?>
	<details class="rktsky-antispam__disclosure"><summary><?php esc_html_e( 'What is the measurement based on?', 'rocketsky-antispam' ); ?></summary><div class="rktsky-antispam__quality-basis"><p><?php esc_html_e( 'These rates use reviewed cases only. They do not measure every submission to your website.', 'rocketsky-antispam' ); ?></p><dl class="rktsky-antispam__reasons"><div><dt><?php esc_html_e( 'Spam caught', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $quality['tp'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'False alarms', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $quality['fp'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'Missed spam', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $quality['fn'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'Confirmed normal', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $quality['tn'] ) ); ?></dd></div></dl><p><?php esc_html_e( 'Use Review spam to assess stored messages. Changes in supported WordPress or form plugin spam lists also update these results. A dash means there is no reviewed basis for that rate.', 'rocketsky-antispam' ); ?></p>
	<p class="rktsky-antispam__quality-privacy"><?php esc_html_e( 'Review statistics keep local, linkable HMAC references for up to 90 days, also in privacy-conscious mode. Cleanup depends on WordPress cron.', 'rocketsky-antispam' ); ?> <a href="<?php echo esc_url( admin_url( 'erase-personal-data.php' ) ); ?>"><?php esc_html_e( 'WordPress privacy tools', 'rocketsky-antispam' ); ?> →</a></p>
</div></details>
</section>
