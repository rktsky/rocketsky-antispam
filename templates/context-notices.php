<?php
/**
 * Builds escaped notice content without loading provider data or administration UI.
 *
 * The caller uses wp_get_admin_notice() and an explicit local allowlist retaining
 * these controls. Destinations are fixed local links supplied by SpamHandling.
 *
 * @package RktskyAntispam
 * @since 0.5.0
 * @since 0.5.5 Adds a nonce-bound, script-free preference for the current account.
 * @var array<int,array{provider:string,name:string,handling:array}> $notices Visible active provider descriptions.
 * @var string $dismiss_providers Canonical fixed provider IDs shown by this notice.
 * @var string $dismiss_capability Server-resolved native capability, bound into the nonce.
 * @var string $dismiss_action Current-site native nonce action for this exact provider/capability pair.
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
<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="rktsky-antispam-context-notice__preferences">
	<input type="hidden" name="action" value="rktsky_antispam_notice_hide" />
	<input type="hidden" name="provider" value="<?php echo esc_attr( $dismiss_providers ); ?>" />
	<input type="hidden" name="capability" value="<?php echo esc_attr( $dismiss_capability ); ?>" />
	<?php wp_nonce_field( $dismiss_action, 'rktsky_antispam_notices_nonce' ); ?>
	<button type="submit" class="button" aria-describedby="rktsky-antispam-notice-preferences"><?php esc_html_e( 'Hide this notice', 'rocketsky-antispam' ); ?></button>
	<p id="rktsky-antispam-notice-preferences"><?php esc_html_e( 'Notice preferences apply only to your account on this website. They do not change protection.', 'rocketsky-antispam' ); ?> <a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>"><?php esc_html_e( 'Your profile', 'rocketsky-antispam' ); ?></a></p>
</form>
