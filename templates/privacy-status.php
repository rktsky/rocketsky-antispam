<?php
/**
 * Links a read-only processing status to the single global privacy control.
 *
 * This partial has no inputs: saving another section cannot overwrite the mode.
 *
 * @package RktskyAntispam
 * @since 0.4.0
 * @var bool $privacy_mode Whether optional processing is locked.
 * @var array<string,string> $view_urls Allowlisted local administration URLs.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="rktsky-antispam__privacy-status">
	<span><?php echo esc_html( $privacy_mode ? __( 'Privacy-conscious mode is on.', 'wp-antispam' ) : __( 'Privacy-conscious mode is off.', 'wp-antispam' ) ); ?></span>
	<a href="<?php echo esc_url( $view_urls['settings'] . '#privacy' ); ?>"><?php esc_html_e( 'Privacy settings', 'wp-antispam' ); ?></a>
</div>
