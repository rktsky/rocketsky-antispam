<?php
/**
 * Displays the single editable privacy choice on the settings screen.
 *
 * Server-side settings enforce this policy independently of the preview script.
 * Other screens use privacy-status.php and never submit a duplicate mode value.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 * @since 0.4.0 Centralized the control in global settings.
 * @var bool $privacy_mode Whether optional processing controls are locked.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="rktsky-antispam__mode">
	<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[privacy_mode]" value="0" />
	<label class="rktsky-antispam__inline-check" for="rktsky-privacy-mode">
		<input type="checkbox" id="rktsky-privacy-mode" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[privacy_mode]" value="1" data-rk-privacy-mode aria-describedby="rktsky-mode-help" <?php checked( $privacy_mode ); ?> />
		<span><?php esc_html_e( 'Privacy-conscious mode', 'rocketsky-antispam' ); ?></span>
	</label>
	<p class="description" id="rktsky-mode-help"><?php esc_html_e( 'On by default. Keeps quarantine, country checks, administrator digest emails and native pingbacks/trackbacks off. Local content rules and anonymous statistics remain available.', 'rocketsky-antispam' ); ?></p>
</div>
