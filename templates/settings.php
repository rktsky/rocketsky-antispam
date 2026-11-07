<?php
/**
 * Displays the administrator view model prepared by Admin::render().
 *
 * @package RktskyAntispam
 * @since 0.1.0
 * @var array<string,mixed> $options Validated settings.
 * @var array<string,mixed> $summary Anonymous seven-day statistics.
 * @var array<string,bool> $detected Installed integration availability.
 * @var array<string,array<string,string>> $integrations Integration labels.
 * @var array $integration_groups Provider groups in display order.
 * @var int $active_count Enabled and available integrations.
 * @var array<string,string> $reason_labels Human-readable check names.
 * @var string $privacy_url Native WordPress privacy screen URL.
 * @var string $clear_url Authenticated action endpoint.
 */

defined( 'ABSPATH' ) || exit;
?>
	<?php require __DIR__ . '/admin-header.php'; ?>
	<?php if ( isset( $_GET['statistics-cleared'] ) && '1' === $_GET['statistics-cleared'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only static notice; this query value never triggers mutation or dynamic output. ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Activity statistics deleted. Used tokens remain protected against reuse.', 'wp-antispam' ); ?></p></div>
	<?php endif; ?>
	<?php
	if ( isset( $_GET['classification-saved'] ) && '1' === $_GET['classification-saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixed report notice.
		?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Classification saved. Reusing the same reference updates the existing case.', 'wp-antispam' ); ?></p></div>
		<?php
	elseif ( isset( $_GET['classification-saved'] ) && '0' === $_GET['classification-saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixed failure notice.
		?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'The case action could not be completed. Check the reference and try again.', 'wp-antispam' ); ?></p></div>
	<?php endif; ?>
	<?php
	if ( isset( $_GET['reference-forgotten'] ) && '1' === $_GET['reference-forgotten'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixed reference-removal notice.
		?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Case reference forgotten. Reviewed counts retain their evaluation window.', 'wp-antispam' ); ?></p></div>
	<?php endif; ?>
	<section class="rktsky-antispam__hero" aria-labelledby="rktsky-protection-heading">
		<div>
			<p class="rktsky-antispam__eyebrow"><?php esc_html_e( 'Local protection', 'wp-antispam' ); ?></p>
			<h2 id="rktsky-protection-heading"><?php esc_html_e( 'Spam protection on your own server.', 'wp-antispam' ); ?></h2>
			<p class="rktsky-antispam__hero-copy"><?php esc_html_e( 'Form checks run on your website. No external captcha, tracking service or visitor profile.', 'wp-antispam' ); ?></p>
			<div class="rktsky-antispam__status"><span class="rktsky-antispam__status-dot<?php echo 0 === $active_count ? ' is-off' : ''; ?>" aria-hidden="true"></span><?php echo esc_html( 0 === $active_count ? __( 'No integrations enabled', 'wp-antispam' ) : sprintf( /* translators: %s: number of available, enabled integrations. */ _n( '%s integration enabled', '%s integrations enabled', $active_count, 'wp-antispam' ), number_format_i18n( $active_count ) ) ); ?></div>
		</div>
		<div class="rktsky-antispam__shield" aria-hidden="true"><svg viewBox="0 0 140 160" focusable="false"><path d="M70 8 128 30v49c0 34-28 59-58 73C40 138 12 113 12 79V30Z"/><path d="m45 78 17 17 34-39"/></svg></div>
	</section>
	<section class="rktsky-antispam__overview" aria-labelledby="rktsky-activity-heading">
		<div class="rktsky-antispam__section-title"><h2 id="rktsky-activity-heading"><?php esc_html_e( 'Activity', 'wp-antispam' ); ?></h2><span><?php esc_html_e( 'Last 7 days · UTC', 'wp-antispam' ); ?></span></div>
		<div class="rktsky-antispam__metrics">
			<div class="rktsky-antispam__metric"><span><?php esc_html_e( 'Passed local checks', 'wp-antispam' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $summary['accepted'] ) ); ?></strong><p><?php esc_html_e( 'Other plugins may run additional checks.', 'wp-antispam' ); ?></p></div>
			<div class="rktsky-antispam__metric rktsky-antispam__metric--blocked"><span><?php esc_html_e( 'Stopped by WP Antispam', 'wp-antispam' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $summary['blocked'] ) ); ?></strong><p><?php esc_html_e( 'Includes expired tokens and technical failures.', 'wp-antispam' ); ?></p></div>
			<div class="rktsky-antispam__metric rktsky-antispam__metric--privacy"><span><?php esc_html_e( 'Stored with each count', 'wp-antispam' ); ?></span><strong><?php esc_html_e( 'No personal data', 'wp-antispam' ); ?></strong><p><?php esc_html_e( 'Only the day, integration, result and check.', 'wp-antispam' ); ?></p></div>
		</div>
		<?php if ( 0 === (int) $summary['accepted'] && 0 === (int) $summary['blocked'] ) : ?>
			<p class="rktsky-antispam__empty"><?php esc_html_e( 'No submissions checked in this period. Counts appear when a protected form is submitted.', 'wp-antispam' ); ?></p>
		<?php elseif ( ! empty( $summary['reasons'] ) ) : ?>
			<details class="rktsky-antispam__details"><summary><?php esc_html_e( 'View stopped submissions by check', 'wp-antispam' ); ?></summary><dl class="rktsky-antispam__reasons">
			<?php
			foreach ( $summary['reasons'] as $rktsky_antispam_reason => $rktsky_antispam_count ) :
				?>
				<div><dt><?php echo esc_html( $reason_labels[ $rktsky_antispam_reason ] ?? __( 'Protection check', 'wp-antispam' ) ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) $rktsky_antispam_count ) ); ?></dd></div><?php endforeach; ?></dl></details>
		<?php endif; ?>
	</section>
	<?php require __DIR__ . '/quality.php'; ?>
	<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post" class="rktsky-antispam__settings">
		<?php settings_fields( 'rktsky_antispam' ); ?>
		<?php require __DIR__ . '/privacy-mode.php'; ?>
		<div class="rktsky-antispam__columns">
			<section class="rktsky-antispam__card rktsky-antispam__integrations" aria-labelledby="rktsky-integrations-heading">
				<div class="rktsky-antispam__card-heading"><p class="rktsky-antispam__eyebrow"><?php esc_html_e( 'Connected forms', 'wp-antispam' ); ?></p><h2 id="rktsky-integrations-heading"><?php esc_html_e( 'Choose what to protect', 'wp-antispam' ); ?></h2><p><?php esc_html_e( 'Enable an integration for all its forms. No snippets or form edits required.', 'wp-antispam' ); ?></p></div>
				<div class="rktsky-antispam__integration-list">
					<?php foreach ( $integration_groups as $rktsky_antispam_group ) : ?>
					<fieldset class="rktsky-antispam__integration-group"><legend><?php echo esc_html( $rktsky_antispam_group['label'] ); ?></legend>
					<?php foreach ( $rktsky_antispam_group['providers'] as $rktsky_antispam_provider ) : ?>
						<?php $rktsky_antispam_integration = $integrations[ $rktsky_antispam_provider ]; ?>
						<div class="rktsky-antispam__integration">
							<span class="rktsky-antispam__provider-mark" aria-hidden="true"><?php echo esc_html( $rktsky_antispam_integration['mark'] ); ?></span>
							<div class="rktsky-antispam__provider-copy"><label for="rktsky-<?php echo esc_attr( $rktsky_antispam_provider ); ?>"><?php echo esc_html( $rktsky_antispam_integration['name'] ); ?></label><span class="rktsky-antispam__availability"><?php echo esc_html( ! empty( $detected[ $rktsky_antispam_provider ] ) ? __( 'Available', 'wp-antispam' ) : __( 'Compatible plugin not available', 'wp-antispam' ) ); ?></span><p id="rktsky-<?php echo esc_attr( $rktsky_antispam_provider ); ?>-help"><?php echo esc_html( $rktsky_antispam_integration['detail'] ); ?></p></div>
							<div class="rktsky-antispam__check"><input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[<?php echo esc_attr( $rktsky_antispam_provider ); ?>]" value="0" /><input type="checkbox" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[<?php echo esc_attr( $rktsky_antispam_provider ); ?>]" id="rktsky-<?php echo esc_attr( $rktsky_antispam_provider ); ?>" value="1" aria-describedby="rktsky-<?php echo esc_attr( $rktsky_antispam_provider ); ?>-help" <?php checked( ! empty( $options[ $rktsky_antispam_provider ] ) ); ?> /><span aria-hidden="true"><?php esc_html_e( 'Enable', 'wp-antispam' ); ?></span></div>
						</div>
						<?php if ( 'comments' === $rktsky_antispam_provider ) : ?>
							<div class="rktsky-antispam__field">
								<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[allow_pingbacks]" value="0" />
								<fieldset data-rk-optional-processing <?php disabled( $privacy_mode ); ?>>
									<legend class="screen-reader-text"><?php esc_html_e( 'Pingbacks and trackbacks', 'wp-antispam' ); ?></legend>
									<label class="rktsky-antispam__inline-check" for="rktsky-allow-pingbacks"><input type="checkbox" id="rktsky-allow-pingbacks" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[allow_pingbacks]" value="1" aria-describedby="rktsky-pingbacks-help" <?php checked( ! empty( $options['allow_pingbacks'] ) ); ?> /><span><?php esc_html_e( 'Allow native pingbacks and trackbacks', 'wp-antispam' ); ?></span></label>
									<p class="description" id="rktsky-pingbacks-help"><?php esc_html_e( 'Privacy relevant: WordPress verifies pingbacks by requesting the remote source page and may forward the sender IP address. Native WordPress moderation still applies. These protocol requests do not use JavaScript tokens.', 'wp-antispam' ); ?></p>
								</fieldset>
								<p class="rktsky-antispam__mode-hint" data-rk-mode-hint <?php if ( ! $privacy_mode ) : ?>hidden<?php endif; ?>><?php esc_html_e( 'Turn off privacy-conscious mode to allow pingbacks and trackbacks.', 'wp-antispam' ); ?></p>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
					</fieldset>
					<?php endforeach; ?>
				</div>
				<p class="rktsky-antispam__card-note"><?php esc_html_e( 'An enabled integration starts protecting forms when its plugin is active. Test an ordinary submission after changing settings.', 'wp-antispam' ); ?></p>
			</section>
			<div class="rktsky-antispam__side">
				<section class="rktsky-antispam__card" aria-labelledby="rktsky-checks-heading">
					<p class="rktsky-antispam__eyebrow"><?php esc_html_e( 'Protection checks', 'wp-antispam' ); ?></p><h2 id="rktsky-checks-heading"><?php esc_html_e( 'Verify each submission', 'wp-antispam' ); ?></h2><p><?php esc_html_e( 'Signed form tokens, a hidden honeypot and one-use verification work without a puzzle.', 'wp-antispam' ); ?></p>
					<div class="rktsky-antispam__field"><label for="rktsky-minimum"><?php esc_html_e( 'Minimum completion time', 'wp-antispam' ); ?></label><div class="rktsky-antispam__number"><input type="number" id="rktsky-minimum" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[minimum_seconds]" value="<?php echo esc_attr( (string) $options['minimum_seconds'] ); ?>" min="0" max="10" step="1" aria-describedby="rktsky-minimum-help" /><span><?php esc_html_e( 'seconds', 'wp-antispam' ); ?></span></div><p id="rktsky-minimum-help" class="description"><?php esc_html_e( '0 disables the timing check. A delay can reject fast submissions made with autofill.', 'wp-antispam' ); ?></p></div>
					<p class="rktsky-antispam__small-note"><?php esc_html_e( 'Protected forms need JavaScript to obtain a fresh token. Visitors are shown an error when verification is unavailable.', 'wp-antispam' ); ?></p>
				</section>
				<section class="rktsky-antispam__card rktsky-antispam__privacy" aria-labelledby="rktsky-privacy-heading">
					<p class="rktsky-antispam__eyebrow"><?php esc_html_e( 'Data handling', 'wp-antispam' ); ?></p><h2 id="rktsky-privacy-heading"><?php esc_html_e( 'Local data handling', 'wp-antispam' ); ?></h2><p><?php esc_html_e( 'Standard checks store no IP addresses, email addresses or message contents. Optional quarantine keeps selected rejected fields locally. Country rules process IP metadata. Digest emails use your mail provider. WP Antispam sets no cookies and calls no external spam service.', 'wp-antispam' ); ?></p>
					<dl class="rktsky-antispam__privacy-facts"><div><dt><?php esc_html_e( 'Used token digests', 'wp-antispam' ); ?></dt><dd><?php esc_html_e( 'Expire within 2 hours; removed by scheduled cleanup', 'wp-antispam' ); ?></dd></div><div><dt><?php esc_html_e( 'Anonymous daily counts', 'wp-antispam' ); ?></dt><dd><?php esc_html_e( 'Scheduled deletion after the period below', 'wp-antispam' ); ?></dd></div></dl>
					<div class="rktsky-antispam__field"><label for="rktsky-retention"><?php esc_html_e( 'Keep statistics for', 'wp-antispam' ); ?></label><div class="rktsky-antispam__number"><input type="number" id="rktsky-retention" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[retention_days]" value="<?php echo esc_attr( (string) $options['retention_days'] ); ?>" min="1" max="90" step="1" aria-describedby="rktsky-retention-help" /><span><?php esc_html_e( 'days', 'wp-antispam' ); ?></span></div><p id="rktsky-retention-help" class="description"><?php esc_html_e( 'Applies to WP Antispam counts. Your form plugin and server may keep their own records.', 'wp-antispam' ); ?></p></div>
					<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[delete_on_uninstall]" value="0" /><label class="rktsky-antispam__inline-check" for="rktsky-delete"><input type="checkbox" id="rktsky-delete" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[delete_on_uninstall]" value="1" <?php checked( ! empty( $options['delete_on_uninstall'] ) ); ?> /><span><?php esc_html_e( 'Delete plugin data when uninstalling', 'wp-antispam' ); ?></span></label>
					<a href="<?php echo esc_url( $privacy_url ); ?>" class="rktsky-antispam__text-link"><?php esc_html_e( 'Review the suggested privacy policy text', 'wp-antispam' ); ?><span aria-hidden="true"> →</span></a>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'quarantine', $base_url ) ); ?>" class="rktsky-antispam__text-link"><?php esc_html_e( 'Manage optional quarantine', 'wp-antispam' ); ?><span aria-hidden="true"> →</span></a>
				</section>
			</div>
		</div>
		<div class="rktsky-antispam__save"><p><?php esc_html_e( 'Changes apply after saving.', 'wp-antispam' ); ?></p><button type="submit" class="button button-primary rktsky-antispam__save-button"><?php esc_html_e( 'Save protection settings', 'wp-antispam' ); ?></button></div>
	</form>
	<?php require __DIR__ . '/sponsors.php'; ?>
	<footer class="rktsky-antispam__footer"><p><?php esc_html_e( 'Open source. All protection included.', 'wp-antispam' ); ?></p><details class="rktsky-antispam__delete"><summary><?php esc_html_e( 'Manage activity statistics', 'wp-antispam' ); ?></summary><form action="<?php echo esc_url( $clear_url ); ?>" method="post"><input type="hidden" name="action" value="rktsky_antispam_clear" /><?php wp_nonce_field( 'rktsky_antispam_clear' ); ?><p><?php esc_html_e( 'Deletes daily activity counts and the lifetime rejected-comment total. Reviewed detection results remain available for their 90-day window. This cannot be undone.', 'wp-antispam' ); ?></p><button type="submit" class="button"><?php esc_html_e( 'Delete activity statistics', 'wp-antispam' ); ?></button></form></details></footer>
</div>
