<?php
/**
 * Prints escaped cache-safe controls within the owning provider's form element.
 *
 * @var string $provider Known provider.
 * @var string $form_id Native form ID.
 * @var string $intent Public signed form descriptor.
 * @var string $endpoint Local REST endpoint.
 * @package RktskyAntispam
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;
?>
<span hidden data-rk-antispam data-provider="<?php echo esc_attr( $provider ); ?>" data-form="<?php echo esc_attr( $form_id ); ?>" data-intent="<?php echo esc_attr( $intent ); ?>" data-endpoint="<?php echo esc_url( $endpoint ); ?>">
	<input type="hidden" name="rktsky_antispam_token" value="" autocomplete="off">
</span>
<noscript><p><?php esc_html_e( 'Enable JavaScript to submit this form.', 'rocketsky-antispam' ); ?></p></noscript>
