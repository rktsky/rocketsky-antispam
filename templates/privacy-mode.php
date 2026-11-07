<?php
/**
 * Displays the shared data-minimisation choice within each settings form.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 * @var bool $privacy_mode Whether optional storage, country checks and digest mail are locked.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="rktsky-antispam__mode" aria-labelledby="rktsky-mode-heading">
	<div>
		<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[privacy_mode]" value="0" />
		<label class="rktsky-antispam__inline-check" id="rktsky-mode-heading" for="rktsky-privacy-mode"><input type="checkbox" id="rktsky-privacy-mode" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[privacy_mode]" value="1" data-rk-privacy-mode aria-describedby="rktsky-mode-help" <?php checked( $privacy_mode ); ?> /><span><?php esc_html_e( 'Privacy-conscious mode', 'wp-antispam' ); ?></span></label>
		<p id="rktsky-mode-help"><?php esc_html_e( 'On by default. Keeps quarantine, country checks, administrator digest emails and native pingbacks/trackbacks off. Local content rules and anonymous statistics remain available.', 'wp-antispam' ); ?></p>
	</div>
	<p><?php esc_html_e( 'Make sure your website privacy information describes the options you enable.', 'wp-antispam' ); ?></p>
</section>
