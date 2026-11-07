<?php
/**
 * Renders one provider choice with truthful availability and saved-state handling.
 *
 * @package RktskyAntispam
 * @since 0.4.0
 * @var string $rktsky_antispam_provider Fixed provider ID from the controller.
 * @var array $integrations Fixed labels, descriptions and validated boolean states.
 * @var \Rktsky\Antispam\SpamHandling $spam_handling Shared review destination metadata.
 */

defined( 'ABSPATH' ) || exit;
$rktsky_antispam_integration = $integrations[ $rktsky_antispam_provider ];
?>
<div class="rktsky-antispam__provider-row">
	<span class="rktsky-antispam__provider-mark" aria-hidden="true"><?php echo esc_html( $rktsky_antispam_integration['mark'] ); ?></span>
	<div class="rktsky-antispam__provider-copy">
		<label for="rktsky-<?php echo esc_attr( $rktsky_antispam_provider ); ?>"><?php echo esc_html( $rktsky_antispam_integration['name'] ); ?></label>
		<span class="rktsky-antispam__provider-state"><?php echo esc_html( ! $rktsky_antispam_integration['available'] ? __( 'Compatible plugin not available', 'wp-antispam' ) : ( $rktsky_antispam_integration['configured'] ? __( 'Enabled', 'wp-antispam' ) : __( 'Off', 'wp-antispam' ) ) ); ?></span>
		<p id="rktsky-<?php echo esc_attr( $rktsky_antispam_provider ); ?>-help"><?php echo esc_html( $rktsky_antispam_integration['detail'] ); ?></p>
		<?php if ( $rktsky_antispam_integration['available'] && $rktsky_antispam_integration['configured'] ) : ?>
			<?php $rktsky_antispam_route = $spam_handling->describe( $rktsky_antispam_provider ); ?>
			<p class="rktsky-antispam__handling"><a href="<?php echo esc_url( $rktsky_antispam_route['url'] ); ?>"><?php echo esc_html( $rktsky_antispam_route['label'] ); ?></a> <span><?php echo esc_html( $rktsky_antispam_route['description'] ); ?></span>
				<?php if ( ! empty( $rktsky_antispam_route['secondary_url'] ) ) : ?>
					<a href="<?php echo esc_url( $rktsky_antispam_route['secondary_url'] ); ?>"><?php echo esc_html( $rktsky_antispam_route['secondary_label'] ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
	<div class="rktsky-antispam__check">
		<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[<?php echo esc_attr( $rktsky_antispam_provider ); ?>]" value="<?php echo esc_attr( ! $rktsky_antispam_integration['available'] && $rktsky_antispam_integration['configured'] ? '1' : '0' ); ?>" />
		<input type="checkbox" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[<?php echo esc_attr( $rktsky_antispam_provider ); ?>]" id="rktsky-<?php echo esc_attr( $rktsky_antispam_provider ); ?>" value="1" aria-describedby="rktsky-<?php echo esc_attr( $rktsky_antispam_provider ); ?>-help" <?php checked( $rktsky_antispam_integration['available'] && $rktsky_antispam_integration['configured'] ); ?> <?php disabled( ! $rktsky_antispam_integration['available'] ); ?> />
		<span aria-hidden="true"><?php esc_html_e( 'Enable', 'wp-antispam' ); ?></span>
	</div>
</div>
