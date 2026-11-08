<?php
/**
 * Displays global processing, retention and maintenance settings in one form.
 *
 * Integration switches and content filters belong to their own screens. Each
 * visible checkbox submits its own zero; omitted sections retain saved values.
 * Statistics deletion is a separate authenticated action outside the form.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 * @since 0.4.0 Limited this screen to global settings.
 * @var array<string,mixed> $options Validated global settings.
 * @var bool $privacy_mode Whether optional processing controls are locked.
 * @var string $notification_address Native WordPress administrator email.
 * @var string $privacy_url Native WordPress privacy screen URL.
 * @var string $clear_url Authenticated statistics-action endpoint.
 * @var array<string,string> $view_urls Allowlisted local administration URLs.
 */

defined( 'ABSPATH' ) || exit;
require __DIR__ . '/admin-header.php';
?>
	<header class="rktsky-antispam__page-heading">
		<h2><?php esc_html_e( 'Settings', 'rocketsky-antispam' ); ?></h2>
		<p><?php esc_html_e( 'Choose what Rocketsky Antispam stores, how long it keeps records and whether it sends notifications.', 'rocketsky-antispam' ); ?></p>
	</header>
	<?php if ( isset( $_GET['statistics-cleared'] ) && '1' === $_GET['statistics-cleared'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Fixed read-only result notice; no mutation occurs here. ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Activity statistics deleted.', 'rocketsky-antispam' ); ?></p></div>
	<?php endif; ?>
	<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post" class="rktsky-antispam__settings-stack" data-rk-settings-form>
		<?php settings_fields( 'rktsky_antispam' ); ?>
		<section class="rktsky-antispam__card" id="privacy" aria-labelledby="rktsky-privacy-heading">
			<div class="rktsky-antispam__section-head">
				<h3 id="rktsky-privacy-heading"><?php esc_html_e( 'Privacy settings', 'rocketsky-antispam' ); ?></h3>
			</div>
			<?php require __DIR__ . '/privacy-mode.php'; ?>
			<p><?php esc_html_e( 'This mode limits optional processing. It does not certify compliance with the GDPR or the Swiss FADP.', 'rocketsky-antispam' ); ?></p>
			<details class="rktsky-antispam__disclosure">
				<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Stored data and privacy information', 'rocketsky-antispam' ); ?></span></summary>
				<div class="rktsky-antispam__disclosure-body">
					<p><?php esc_html_e( 'Rocketsky Antispam stores no IP addresses, user agents or fingerprints in its own tables. Quarantine can keep names, email addresses and messages. Country filters use connection metadata. Email summaries use your WordPress mail setup. No cookies or external spam service are used. Form plugins control their own storage.', 'rocketsky-antispam' ); ?></p>
					<dl class="rktsky-antispam__privacy-facts">
						<div><dt><?php esc_html_e( 'Used token digests', 'rocketsky-antispam' ); ?></dt><dd><?php esc_html_e( 'Expire within 2 hours; removed by scheduled cleanup', 'rocketsky-antispam' ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Anonymous daily counts', 'rocketsky-antispam' ); ?></dt><dd><?php esc_html_e( 'Removed by scheduled cleanup after this period', 'rocketsky-antispam' ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Review data', 'rocketsky-antispam' ); ?></dt><dd><?php esc_html_e( 'Local, linkable references for up to 90 days, including in privacy-conscious mode', 'rocketsky-antispam' ); ?></dd></div>
					</dl>
					<p><?php esc_html_e( 'Describe your enabled settings in your website\'s privacy notice.', 'rocketsky-antispam' ); ?></p>
					<a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Review the suggested privacy policy text', 'rocketsky-antispam' ); ?></a>
				</div>
			</details>
		</section>
		<section class="rktsky-antispam__card" id="retention" aria-labelledby="rktsky-retention-heading">
			<div class="rktsky-antispam__section-head"><h3 id="rktsky-retention-heading"><?php esc_html_e( 'Retention and quarantine', 'rocketsky-antispam' ); ?></h3></div>
			<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[quarantine_enabled]" value="0" />
			<fieldset data-rk-optional-processing <?php disabled( $privacy_mode ); ?>>
				<legend class="screen-reader-text"><?php esc_html_e( 'Quarantine storage configuration', 'rocketsky-antispam' ); ?></legend>
				<label class="rktsky-antispam__inline-check" for="rktsky-quarantine-enabled"><input type="checkbox" id="rktsky-quarantine-enabled" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[quarantine_enabled]" value="1" aria-describedby="rktsky-quarantine-privacy" <?php checked( ! empty( $options['quarantine_enabled'] ) ); ?> /><span><?php esc_html_e( 'Store rejected submissions in quarantine', 'rocketsky-antispam' ); ?></span></label>
				<p class="description" id="rktsky-quarantine-privacy"><?php esc_html_e( 'Can store names, email addresses and message text. Include this storage in your privacy notice before enabling.', 'rocketsky-antispam' ); ?></p>
				<div class="rktsky-antispam__field">
					<label for="rktsky-quarantine-days"><?php esc_html_e( 'Delete quarantined records after', 'rocketsky-antispam' ); ?></label>
					<div class="rktsky-antispam__number"><input type="number" id="rktsky-quarantine-days" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[quarantine_days]" value="<?php echo esc_attr( (string) $options['quarantine_days'] ); ?>" min="1" max="30" step="1" aria-describedby="rktsky-quarantine-retention" /><span><?php esc_html_e( 'days', 'rocketsky-antispam' ); ?></span></div>
				</div>
			</fieldset>
			<p class="rktsky-antispam__mode-hint" data-rk-mode-hint
			<?php
			if ( ! $privacy_mode ) :
				?>
				hidden<?php endif; ?>><?php esc_html_e( 'Turn off privacy-conscious mode to enable quarantine.', 'rocketsky-antispam' ); ?></p>
			<p class="description" id="rktsky-quarantine-retention"><?php esc_html_e( 'Up to 500 records. Expired records are hidden immediately and deleted by scheduled cleanup, which can run late. Turning quarantine off stops new storage; existing records keep their expiry.', 'rocketsky-antispam' ); ?></p>
			<a href="<?php echo esc_url( $view_urls['quarantine'] ); ?>"><?php esc_html_e( 'Review rejected submissions', 'rocketsky-antispam' ); ?></a>
			<div class="rktsky-antispam__field-grid">
				<div class="rktsky-antispam__field">
					<label for="rktsky-retention"><?php esc_html_e( 'Keep daily activity for', 'rocketsky-antispam' ); ?></label>
					<div class="rktsky-antispam__number"><input type="number" id="rktsky-retention" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[retention_days]" value="<?php echo esc_attr( (string) $options['retention_days'] ); ?>" min="1" max="90" step="1" aria-describedby="rktsky-retention-help" /><span><?php esc_html_e( 'days', 'rocketsky-antispam' ); ?></span></div>
					<p id="rktsky-retention-help" class="description"><?php esc_html_e( 'Does not change the total comment count or the 90-day review period.', 'rocketsky-antispam' ); ?></p>
				</div>
				<div class="rktsky-antispam__field">
					<label for="rktsky-comment-spam-days"><?php esc_html_e( 'Permanently delete spam comments after', 'rocketsky-antispam' ); ?></label>
					<div class="rktsky-antispam__number"><input type="number" id="rktsky-comment-spam-days" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[comment_spam_days]" value="<?php echo esc_attr( (string) $options['comment_spam_days'] ); ?>" min="0" max="90" step="1" aria-describedby="rktsky-comment-spam-help" /><span><?php esc_html_e( 'days', 'rocketsky-antispam' ); ?></span></div>
					<p class="description" id="rktsky-comment-spam-help"><?php esc_html_e( 'Applies to existing and future WordPress comments marked as spam, based on the date they were submitted. 0 turns this cleanup off.', 'rocketsky-antispam' ); ?></p>
				</div>
			</div>
		</section>
		<section class="rktsky-antispam__card" id="notifications" aria-labelledby="rktsky-digest-heading">
			<div class="rktsky-antispam__section-head"><h3 id="rktsky-digest-heading"><?php esc_html_e( 'Daily email summary', 'rocketsky-antispam' ); ?></h3><p><?php esc_html_e( 'Emails the previous day\'s rejection counts through WordPress. No message contents are included.', 'rocketsky-antispam' ); ?></p></div>
			<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[notify_admin]" value="0" />
			<fieldset data-rk-optional-processing <?php disabled( $privacy_mode ); ?>>
				<legend class="screen-reader-text"><?php esc_html_e( 'Digest email configuration', 'rocketsky-antispam' ); ?></legend>
				<label class="rktsky-antispam__inline-check" for="rktsky-notify"><input type="checkbox" id="rktsky-notify" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[notify_admin]" value="1" <?php checked( ! empty( $options['notify_admin'] ) ); ?> /><span><?php esc_html_e( 'Send a daily summary when submissions were stopped', 'rocketsky-antispam' ); ?></span></label>
				<p class="description"><?php echo esc_html( sprintf( /* translators: %s: configured WordPress administrator email address. */ __( 'Recipient: %s', 'rocketsky-antispam' ), (string) $notification_address ) ); ?></p>
			</fieldset>
			<p class="rktsky-antispam__mode-hint" data-rk-mode-hint
			<?php
			if ( ! $privacy_mode ) :
				?>
				hidden<?php endif; ?>><?php esc_html_e( 'Turn off privacy-conscious mode to enable digest email.', 'rocketsky-antispam' ); ?></p>
		</section>
		<section class="rktsky-antispam__card" id="data" aria-labelledby="rktsky-data-heading">
			<div class="rktsky-antispam__section-head"><h3 id="rktsky-data-heading"><?php esc_html_e( 'Uninstalling', 'rocketsky-antispam' ); ?></h3></div>
			<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[delete_on_uninstall]" value="0" />
			<label class="rktsky-antispam__inline-check" for="rktsky-delete"><input type="checkbox" id="rktsky-delete" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[delete_on_uninstall]" value="1" <?php checked( ! empty( $options['delete_on_uninstall'] ) ); ?> /><span><?php esc_html_e( 'Delete plugin data when uninstalling', 'rocketsky-antispam' ); ?></span></label>
		</section>
		<div class="rktsky-antispam__save"><p><?php esc_html_e( 'Changes apply after saving.', 'rocketsky-antispam' ); ?></p><button type="submit" class="button button-primary rktsky-antispam__save-button"><?php esc_html_e( 'Save settings', 'rocketsky-antispam' ); ?></button></div>
	</form>
	<details class="rktsky-antispam__disclosure">
		<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Delete statistics', 'rocketsky-antispam' ); ?></span></summary>
		<div class="rktsky-antispam__disclosure-body">
			<form action="<?php echo esc_url( $clear_url ); ?>" method="post">
				<input type="hidden" name="action" value="rktsky_antispam_clear" />
				<?php wp_nonce_field( 'rktsky_antispam_clear' ); ?>
				<p><?php esc_html_e( 'Deletes daily counts and the total rejected-comment count. Reviewed results keep their 90-day expiry. This cannot be undone.', 'rocketsky-antispam' ); ?></p>
				<button type="submit" class="button rktsky-antispam__danger-button"><?php esc_html_e( 'Delete activity statistics', 'rocketsky-antispam' ); ?></button>
			</form>
		</div>
	</details>
	<?php require __DIR__ . '/sponsors.php'; ?>
</div>
