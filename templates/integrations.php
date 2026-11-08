<?php
/**
 * Configures available providers before exposing unavailable optional adapters.
 *
 * @package RktskyAntispam
 * @since 0.4.0
 * @var array $options Validated per-site settings.
 * @var array $integrations Fixed provider descriptions and availability states.
 * @var array $available_groups Available provider groups in semantic order.
 * @var string[] $missing_providers Unsupported or inactive provider IDs.
 * @var bool $privacy_mode Whether native pingbacks are locked off.
 * @var array<string,string> $view_urls Fixed local section links.
 */

defined( 'ABSPATH' ) || exit;
require __DIR__ . '/admin-header.php';
?>
<header class="rktsky-antispam__page-heading"><h2><?php esc_html_e( 'Integrations', 'rocketsky-antispam' ); ?></h2><p><?php esc_html_e( 'Enable protection for the providers you use. Each integration covers all of its forms; no form edits are needed.', 'rocketsky-antispam' ); ?></p></header>
<?php require __DIR__ . '/privacy-status.php'; ?>
<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post" class="rktsky-antispam__settings-stack" data-rk-settings-form>
	<?php settings_fields( 'rktsky_antispam' ); ?>
	<?php foreach ( $available_groups as $rktsky_antispam_group ) : ?>
		<fieldset class="rktsky-antispam__card rktsky-antispam__provider-group"><legend><?php echo esc_html( $rktsky_antispam_group['label'] ); ?></legend>
			<?php foreach ( $rktsky_antispam_group['providers'] as $rktsky_antispam_provider ) : ?>
				<?php require __DIR__ . '/provider.php'; ?>
				<?php if ( 'comments' === $rktsky_antispam_provider ) : ?>
					<details class="rktsky-antispam__disclosure">
						<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Pingbacks and trackbacks', 'rocketsky-antispam' ); ?></span><span class="rktsky-antispam__badge"><?php echo esc_html( ! empty( $options['allow_pingbacks'] ) ? __( 'Allowed', 'rocketsky-antispam' ) : __( 'Off', 'rocketsky-antispam' ) ); ?></span></summary>
						<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[allow_pingbacks]" value="0" />
						<fieldset <?php disabled( $privacy_mode ); ?>><legend class="screen-reader-text"><?php esc_html_e( 'Pingbacks and trackbacks', 'rocketsky-antispam' ); ?></legend>
							<label class="rktsky-antispam__inline-check" for="rktsky-allow-pingbacks"><input type="checkbox" id="rktsky-allow-pingbacks" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[allow_pingbacks]" value="1" aria-describedby="rktsky-pingbacks-help" <?php checked( ! empty( $options['allow_pingbacks'] ) ); ?> /><span><?php esc_html_e( 'Allow native pingbacks and trackbacks', 'rocketsky-antispam' ); ?></span></label>
							<p class="description" id="rktsky-pingbacks-help"><?php esc_html_e( 'Privacy relevant: WordPress verifies pingbacks by requesting the remote source page and may forward the sender IP address. Native WordPress moderation still applies. These protocol requests do not use JavaScript tokens.', 'rocketsky-antispam' ); ?></p>
						</fieldset>
						<?php
						if ( $privacy_mode ) :
							?>
							<p class="rktsky-antispam__help-note"><?php esc_html_e( 'Turn off privacy-conscious mode to allow pingbacks and trackbacks.', 'rocketsky-antispam' ); ?> <a href="<?php echo esc_url( $view_urls['settings'] . '#privacy' ); ?>"><?php esc_html_e( 'Privacy settings', 'rocketsky-antispam' ); ?></a></p><?php endif; ?>
					</details>
				<?php endif; ?>
			<?php endforeach; ?>
		</fieldset>
	<?php endforeach; ?>
	<?php if ( ! empty( $missing_providers ) ) : ?>
		<details class="rktsky-antispam__disclosure rktsky-antispam__provider-unavailable">
			<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Other supported integrations', 'rocketsky-antispam' ); ?></span><span class="rktsky-antispam__badge"><?php echo esc_html( number_format_i18n( count( $missing_providers ) ) ); ?></span></summary>
			<p class="rktsky-antispam__help-note"><?php esc_html_e( 'These providers are not currently available. Install or activate a compatible provider to use its integration. Earlier saved choices are kept.', 'rocketsky-antispam' ); ?></p>
			<?php
			foreach ( $missing_providers as $rktsky_antispam_provider ) :
				?>
				<?php require __DIR__ . '/provider.php'; ?><?php endforeach; ?>
		</details>
	<?php endif; ?>
	<section class="rktsky-antispam__card" aria-labelledby="rktsky-checks-heading">
		<h2 id="rktsky-checks-heading"><?php esc_html_e( 'Technical checks', 'rocketsky-antispam' ); ?></h2><p><?php esc_html_e( 'Signed form tokens, a hidden honeypot and one-use verification work without a puzzle.', 'rocketsky-antispam' ); ?></p>
		<div class="rktsky-antispam__field"><label for="rktsky-minimum"><?php esc_html_e( 'Minimum completion time', 'rocketsky-antispam' ); ?></label><div class="rktsky-antispam__number"><input type="number" id="rktsky-minimum" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[minimum_seconds]" value="<?php echo esc_attr( (string) $options['minimum_seconds'] ); ?>" min="0" max="10" step="1" aria-describedby="rktsky-minimum-help" /><span><?php esc_html_e( 'seconds', 'rocketsky-antispam' ); ?></span></div><p id="rktsky-minimum-help" class="description"><?php esc_html_e( '0 disables the timing check. A delay can reject fast submissions made with autofill.', 'rocketsky-antispam' ); ?></p></div>
		<p class="description"><?php esc_html_e( 'Protected forms need JavaScript to obtain a fresh token. Visitors are shown an error when verification is unavailable.', 'rocketsky-antispam' ); ?></p>
	</section>
	<div class="rktsky-antispam__save"><p><?php esc_html_e( 'Changes apply after saving.', 'rocketsky-antispam' ); ?></p><button type="submit" class="button button-primary rktsky-antispam__save-button"><?php esc_html_e( 'Save integrations', 'rocketsky-antispam' ); ?></button></div>
</form>
<?php require __DIR__ . '/sponsors.php'; ?>
</div>
