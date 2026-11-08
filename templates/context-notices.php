<?php
/**
 * Builds escaped notice content without loading provider data or administration UI.
 *
 * The caller wraps this partial through the native wp_admin_notice() API. Every
 * destination is a fixed local administration link supplied by SpamHandling.
 *
 * @package RktskyAntispam
 * @since 0.5.0
 * @var array<int,array{name:string,handling:array}> $notices Active provider descriptions.
 */

defined( 'ABSPATH' ) || exit;
?>
<?php foreach ( $notices as $rktsky_antispam_notice ) : ?>
	<p><strong><?php echo esc_html( sprintf( /* translators: %s: the active integration name. */ __( 'Rocketsky Antispam is enabled for %s.', 'rocketsky-antispam' ), $rktsky_antispam_notice['name'] ) ); ?></strong>
		<?php echo esc_html( $rktsky_antispam_notice['handling']['description'] ); ?>
		<?php if ( ! empty( $rktsky_antispam_notice['handling']['url'] ) ) : ?>
			<a href="<?php echo esc_url( $rktsky_antispam_notice['handling']['url'] ); ?>"><?php echo esc_html( $rktsky_antispam_notice['handling']['label'] ); ?></a>
		<?php endif; ?>
		<?php if ( ! empty( $rktsky_antispam_notice['handling']['secondary_url'] ) ) : ?>
			<span aria-hidden="true"> · </span><a href="<?php echo esc_url( $rktsky_antispam_notice['handling']['secondary_url'] ); ?>"><?php echo esc_html( $rktsky_antispam_notice['handling']['secondary_label'] ); ?></a>
		<?php endif; ?>
	</p>
<?php endforeach; ?>
