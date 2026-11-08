<?php
/**
 * Identifies the project sponsor and its product without remote backend assets.
 *
 * Fixed outbound links carry no form data or tracking parameters. The original
 * Speo mark belongs to the sponsor's own website project and is served locally.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

defined( 'ABSPATH' ) || exit;
?>
<footer class="rktsky-antispam__sponsors" aria-label="<?php esc_attr_e( 'Project sponsorship', 'rocketsky-antispam' ); ?>">
	<div class="rktsky-antispam__sponsor rktsky-antispam__sponsor--rocketsky">
		<p class="rktsky-antispam__eyebrow"><?php esc_html_e( 'Developed and sponsored by', 'rocketsky-antispam' ); ?></p>
		<a href="https://www.rocketsky.ch/" target="_blank" rel="noopener noreferrer" class="rktsky-antispam__sponsor-brand">Rocketsky<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M7 17 17 7M7 7h10v10" /></svg></a>
	</div>
	<div class="rktsky-antispam__sponsor rktsky-antispam__sponsor--speo">
		<div>
			<p class="rktsky-antispam__eyebrow"><?php esc_html_e( 'Sponsored product', 'rocketsky-antispam' ); ?></p>
			<img src="<?php echo esc_url( plugins_url( 'assets/branding/speo-logo.svg', RKTSKY_ANTISPAM_FILE ) ); ?>" width="108" height="30" alt="Speo" />
			<p><?php esc_html_e( 'Website analytics by Rocketsky.', 'rocketsky-antispam' ); ?></p>
		</div>
		<a href="https://www.speo.ch" target="_blank" rel="noopener noreferrer" class="rktsky-antispam__sponsor-link"><span><?php esc_html_e( 'Explore Speo', 'rocketsky-antispam' ); ?></span><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M7 17 17 7M7 7h10v10" /></svg></a>
	</div>
</footer>
