<?php
/**
 * Displays local content filters and optional comment-only restrictions.
 *
 * Configured advanced sections open on the server so saved rules remain visible.
 * The global privacy mode is read-only here; this form owns only filter fields.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 * @since 0.4.0 Separated filters from global retention and notification settings.
 * @var array<string,mixed> $options Validated plugin settings.
 * @var array<string,string> $rule_lists Normalized textarea and country values.
 * @var array<string,string> $languages Fixed supported comment-language labels.
 * @var bool $country_available Whether the configured country source is available.
 * @var bool $privacy_mode Whether additional processing controls are locked.
 * @var array<string,string> $view_urls Allowlisted local administration URLs.
 */

defined( 'ABSPATH' ) || exit;
require __DIR__ . '/admin-header.php';
$rktsky_antispam_selected_languages = array_intersect_key( $languages, array_flip( (array) $options['allowed_languages'] ) );
$rktsky_antispam_country_active     = 'off' !== $options['country_mode'];
$rktsky_antispam_country_configured = $rktsky_antispam_country_active || '' !== $rule_lists['countries'] || '' !== $rule_lists['trusted_proxies'];
?>
	<header class="rktsky-antispam__page-heading">
		<h2><?php esc_html_e( 'Filter rules', 'rocketsky-antispam' ); ?></h2>
		<p><?php esc_html_e( 'Set rules for comments and contact forms. Test new rules with legitimate messages.', 'rocketsky-antispam' ); ?></p>
	</header>
	<?php require __DIR__ . '/privacy-status.php'; ?>
	<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post" class="rktsky-antispam__settings-stack" data-rk-settings-form>
		<?php settings_fields( 'rktsky_antispam' ); ?>
		<section class="rktsky-antispam__card" aria-labelledby="rktsky-content-heading">
			<div class="rktsky-antispam__section-head">
				<h3 id="rktsky-content-heading"><?php esc_html_e( 'Content filters', 'rocketsky-antispam' ); ?></h3>
				<p><?php esc_html_e( 'Applies to comments and contact forms, except Divi. Registration and checkout use technical checks.', 'rocketsky-antispam' ); ?></p>
			</div>
			<div class="rktsky-antispam__field">
				<label for="rktsky-keywords"><?php esc_html_e( 'Blocked keywords', 'rocketsky-antispam' ); ?></label>
				<textarea id="rktsky-keywords" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[spam_keywords]" rows="5" aria-describedby="rktsky-keywords-help"><?php echo esc_textarea( $rule_lists['spam_keywords'] ); ?></textarea>
				<p id="rktsky-keywords-help" class="description"><?php esc_html_e( 'One word or phrase per line. Matches ignore case and can occur within longer words. Leave empty to disable.', 'rocketsky-antispam' ); ?></p>
			</div>
			<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[bbcode_filter]" value="0" />
			<label class="rktsky-antispam__inline-check" for="rktsky-bbcode"><input type="checkbox" id="rktsky-bbcode" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[bbcode_filter]" value="1" <?php checked( ! empty( $options['bbcode_filter'] ) ); ?> /><span><?php esc_html_e( 'Reject BBCode link markup', 'rocketsky-antispam' ); ?></span></label>
			<details class="rktsky-antispam__disclosure"
			<?php
			if ( '' !== $rule_lists['spam_patterns'] ) :
				?>
				open<?php endif; ?>>
				<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Regular expressions', 'rocketsky-antispam' ); ?></span><span class="rktsky-antispam__badge"><?php echo esc_html( '' !== $rule_lists['spam_patterns'] ? __( 'Configured', 'rocketsky-antispam' ) : __( 'Off', 'rocketsky-antispam' ) ); ?></span></summary>
				<div class="rktsky-antispam__disclosure-body">
					<div class="rktsky-antispam__field">
						<label for="rktsky-patterns"><?php esc_html_e( 'Patterns', 'rocketsky-antispam' ); ?></label>
						<textarea id="rktsky-patterns" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[spam_patterns]" rows="5" spellcheck="false" class="code" aria-describedby="rktsky-patterns-help"><?php echo esc_textarea( $rule_lists['spam_patterns'] ); ?></textarea>
						<p id="rktsky-patterns-help" class="description"><?php esc_html_e( 'One expression per line, without delimiters. Up to 10 expressions of 256 bytes each. Test them with normal messages before saving.', 'rocketsky-antispam' ); ?></p>
					</div>
				</div>
			</details>
		</section>
		<details class="rktsky-antispam__disclosure"
		<?php
		if ( ! empty( $rktsky_antispam_selected_languages ) ) :
			?>
			open<?php endif; ?>>
			<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Comment languages', 'rocketsky-antispam' ); ?></span><span class="rktsky-antispam__badge"><?php echo esc_html( empty( $rktsky_antispam_selected_languages ) ? __( 'All languages', 'rocketsky-antispam' ) : implode( ', ', $rktsky_antispam_selected_languages ) ); ?></span></summary>
			<div class="rktsky-antispam__disclosure-body">
				<p><?php esc_html_e( 'Language detection runs on your website. Short or ambiguous comments may remain unrecognised.', 'rocketsky-antispam' ); ?></p>
				<fieldset class="rktsky-antispam__field rktsky-antispam__language-choices">
					<legend><?php esc_html_e( 'Allow comments in these languages', 'rocketsky-antispam' ); ?></legend>
					<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[allowed_languages][]" value="" />
					<?php foreach ( $languages as $rktsky_antispam_code => $rktsky_antispam_language ) : ?>
						<label for="rktsky-language-<?php echo esc_attr( $rktsky_antispam_code ); ?>"><input type="checkbox" id="rktsky-language-<?php echo esc_attr( $rktsky_antispam_code ); ?>" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[allowed_languages][]" value="<?php echo esc_attr( $rktsky_antispam_code ); ?>" <?php checked( in_array( $rktsky_antispam_code, $options['allowed_languages'], true ) ); ?> /><span><?php echo esc_html( $rktsky_antispam_language ); ?></span></label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Leave all languages unchecked to allow any language.', 'rocketsky-antispam' ); ?></p>
				<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[unknown_language_allowed]" value="0" />
				<label class="rktsky-antispam__inline-check" for="rktsky-language-unknown"><input type="checkbox" id="rktsky-language-unknown" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[unknown_language_allowed]" value="1" <?php checked( ! empty( $options['unknown_language_allowed'] ) ); ?> /><span><?php esc_html_e( 'Allow comments with an unknown language', 'rocketsky-antispam' ); ?></span></label>
			</div>
		</details>
		<details class="rktsky-antispam__disclosure"
		<?php
		if ( $rktsky_antispam_country_configured ) :
			?>
			open<?php endif; ?>>
			<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Country filter for comments', 'rocketsky-antispam' ); ?></span><span class="rktsky-antispam__badge"><?php echo esc_html( $rktsky_antispam_country_active ? ( 'allow' === $options['country_mode'] ? __( 'Allow listed countries', 'rocketsky-antispam' ) : __( 'Block listed countries', 'rocketsky-antispam' ) ) : __( 'Off', 'rocketsky-antispam' ) ); ?></span></summary>
			<div class="rktsky-antispam__disclosure-body">
				<p><?php esc_html_e( 'Uses your local GeoIP extension or a trusted proxy to determine the connection\'s country. VPNs and proxies can affect the result. No external lookup service is used.', 'rocketsky-antispam' ); ?></p>
				<?php if ( $privacy_mode ) : ?>
					<p class="rktsky-antispam__mode-hint"><?php esc_html_e( 'Turn off privacy-conscious mode to configure country checks.', 'rocketsky-antispam' ); ?> <a href="<?php echo esc_url( $view_urls['settings'] . '#privacy' ); ?>"><?php esc_html_e( 'Privacy settings', 'rocketsky-antispam' ); ?></a></p>
				<?php endif; ?>
				<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[country_mode]" value="off" />
				<fieldset <?php disabled( $privacy_mode ); ?>>
					<legend class="screen-reader-text"><?php esc_html_e( 'Country check configuration', 'rocketsky-antispam' ); ?></legend>
					<div class="rktsky-antispam__field-grid">
						<div class="rktsky-antispam__field">
							<label for="rktsky-country-mode"><?php esc_html_e( 'Country rule', 'rocketsky-antispam' ); ?></label>
							<select id="rktsky-country-mode" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[country_mode]"><option value="off" <?php selected( $options['country_mode'], 'off' ); ?>><?php esc_html_e( 'Off', 'rocketsky-antispam' ); ?></option><option value="allow" <?php selected( $options['country_mode'], 'allow' ); ?>><?php esc_html_e( 'Allow listed countries', 'rocketsky-antispam' ); ?></option><option value="block" <?php selected( $options['country_mode'], 'block' ); ?>><?php esc_html_e( 'Block listed countries', 'rocketsky-antispam' ); ?></option></select>
						</div>
						<div class="rktsky-antispam__field">
							<label for="rktsky-countries"><?php esc_html_e( 'Country codes', 'rocketsky-antispam' ); ?></label>
							<input type="text" id="rktsky-countries" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[countries]" value="<?php echo esc_attr( $rule_lists['countries'] ); ?>" placeholder="CH, DE, FR" aria-describedby="rktsky-countries-help" />
							<p class="description" id="rktsky-countries-help"><?php esc_html_e( 'Two-letter country codes, separated by commas, e.g. CH, DE, FR.', 'rocketsky-antispam' ); ?></p>
						</div>
					</div>
					<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[country_unknown_allowed]" value="0" />
					<label class="rktsky-antispam__inline-check" for="rktsky-country-unknown"><input type="checkbox" id="rktsky-country-unknown" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[country_unknown_allowed]" value="1" <?php checked( ! empty( $options['country_unknown_allowed'] ) ); ?> /><span><?php esc_html_e( 'Allow comments when country is unknown', 'rocketsky-antispam' ); ?></span></label>
					<div class="rktsky-antispam__field">
						<label for="rktsky-country-source"><?php esc_html_e( 'Country source', 'rocketsky-antispam' ); ?></label>
						<select id="rktsky-country-source" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[country_source]"><option value="geoip" <?php selected( $options['country_source'], 'geoip' ); ?>><?php esc_html_e( 'Local PHP GeoIP extension', 'rocketsky-antispam' ); ?></option><option value="trusted_header" <?php selected( $options['country_source'], 'trusted_header' ); ?>><?php esc_html_e( 'Trusted proxy header', 'rocketsky-antispam' ); ?></option></select>
						<p class="description"><?php echo esc_html( $country_available ? __( 'Country source available.', 'rocketsky-antispam' ) : __( 'Country source unavailable. The unknown country setting applies.', 'rocketsky-antispam' ) ); ?></p>
					</div>
					<details class="rktsky-antispam__disclosure"
					<?php
					if ( 'trusted_header' === $options['country_source'] || '' !== $rule_lists['trusted_proxies'] ) :
						?>
						open<?php endif; ?>>
						<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Trusted proxy configuration', 'rocketsky-antispam' ); ?></span></summary>
						<div class="rktsky-antispam__disclosure-body">
							<div class="rktsky-antispam__field">
								<label for="rktsky-country-header"><?php esc_html_e( 'Country header', 'rocketsky-antispam' ); ?></label>
								<select id="rktsky-country-header" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[country_header]"><option value="HTTP_CF_IPCOUNTRY" <?php selected( $options['country_header'], 'HTTP_CF_IPCOUNTRY' ); ?>>CF-IPCountry</option><option value="HTTP_X_COUNTRY_CODE" <?php selected( $options['country_header'], 'HTTP_X_COUNTRY_CODE' ); ?>>X-Country-Code</option></select>
							</div>
							<div class="rktsky-antispam__field">
								<label for="rktsky-proxies"><?php esc_html_e( 'Trusted proxy networks', 'rocketsky-antispam' ); ?></label>
								<textarea id="rktsky-proxies" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[trusted_proxies]" rows="4" class="code" spellcheck="false" aria-describedby="rktsky-proxies-help"><?php echo esc_textarea( $rule_lists['trusted_proxies'] ); ?></textarea>
								<p class="description" id="rktsky-proxies-help"><?php esc_html_e( 'Enter one IPv4 or IPv6 network in CIDR notation per line, up to 20. Only listed, directly connected proxies are trusted. Configure them to replace country headers sent by visitors.', 'rocketsky-antispam' ); ?></p>
							</div>
						</div>
					</details>
				</fieldset>
			</div>
		</details>
		<div class="rktsky-antispam__save"><p><?php esc_html_e( 'Changes apply after saving.', 'rocketsky-antispam' ); ?></p><button type="submit" class="button button-primary rktsky-antispam__save-button"><?php esc_html_e( 'Save rules', 'rocketsky-antispam' ); ?></button></div>
	</form>
	<?php require __DIR__ . '/sponsors.php'; ?>
</div>
