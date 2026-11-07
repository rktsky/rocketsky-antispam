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
<aside class="rktsky-antispam__sponsors" aria-label="<?php esc_attr_e( 'Project sponsorship', 'wp-antispam' ); ?>">
	<div class="rktsky-antispam__sponsor rktsky-antispam__sponsor--rocketsky">
		<div class="rktsky-antispam__sponsor-copy">
			<p class="rktsky-antispam__eyebrow"><?php esc_html_e( 'Developed and sponsored by', 'wp-antispam' ); ?></p>
			<a href="https://www.rocketsky.ch/" target="_blank" rel="noopener noreferrer" class="rktsky-antispam__sponsor-brand">Rocketsky<span aria-hidden="true">↗</span></a>
			<p><?php esc_html_e( 'WP Antispam is open source. Rocketsky supports its development.', 'wp-antispam' ); ?></p>
		</div>
		<span class="rktsky-antispam__sponsor-orbit" aria-hidden="true"><span></span></span>
	</div>
	<div class="rktsky-antispam__sponsor rktsky-antispam__sponsor--speo">
		<p class="rktsky-antispam__eyebrow"><?php esc_html_e( 'Sponsored product', 'wp-antispam' ); ?></p>
		<img src="<?php echo esc_url( plugins_url( 'assets/branding/speo-logo.svg', RKTSKY_ANTISPAM_FILE ) ); ?>" width="144" height="40" alt="Speo" />
		<p><?php esc_html_e( 'Website analytics by Rocketsky.', 'wp-antispam' ); ?></p>
		<a href="https://www.speo.ch" target="_blank" rel="noopener noreferrer" class="rktsky-antispam__sponsor-link"><?php esc_html_e( 'Explore Speo', 'wp-antispam' ); ?><span aria-hidden="true"> ↗</span></a>
	</div>
</aside>
