<?php
/**
 * Displays optional quarantine settings, metadata and escaped submission details.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 * @var array<string,mixed> $options Validated settings.
 * @var array<string,mixed> $listing Bounded metadata page; no submitted fields.
 * @var array<string,mixed>|null $entry Authorized selected record, or null.
 * @var array<string,mixed>|null $review_status Tracked prediction and explicit human assessment, or null.
 * @var int $entry_id Requested record identifier; zero selects the list.
 * @var array<string,string> $provider_labels Fixed integration labels.
 * @var array<string,string> $reason_labels Fixed check labels.
 * @var string $quarantine_url Local quarantine list URL.
 * @var string $delete_url Authenticated POST endpoint.
 */

defined( 'ABSPATH' ) || exit;
require __DIR__ . '/admin-header.php';
?>
	<?php
	if ( isset( $_GET['record-deleted'] ) && '1' === $_GET['record-deleted'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixed success notice, never a mutation.
		?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Quarantine record deleted.', 'wp-antispam' ); ?></p></div>
		<?php
	elseif ( isset( $_GET['record-deleted'] ) && '0' === $_GET['record-deleted'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixed failure notice, never a mutation.
		?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'The record is no longer available or could not be deleted.', 'wp-antispam' ); ?></p></div>
	<?php endif; ?>
	<?php
	if ( isset( $_GET['review-updated'] ) && '1' === $_GET['review-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixed review notice.
		?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Review saved. The submission remains in quarantine.', 'wp-antispam' ); ?></p></div>
		<?php
	elseif ( isset( $_GET['review-updated'] ) && '0' === $_GET['review-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixed failure notice.
		?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'This record could not be reviewed. It may have expired or have no tracked prediction.', 'wp-antispam' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $entry_id > 0 ) : ?>
		<a href="<?php echo esc_url( $quarantine_url ); ?>" class="rktsky-antispam__back-link">← <?php esc_html_e( 'Back to rejected submissions', 'wp-antispam' ); ?></a>
		<?php if ( null === $entry ) : ?>
			<section class="rktsky-antispam__card rktsky-antispam__quarantine-empty"><h2><?php esc_html_e( 'This record is unavailable', 'wp-antispam' ); ?></h2><p><?php esc_html_e( 'It may have expired or already been deleted.', 'wp-antispam' ); ?></p></section>
		<?php else : ?>
			<section class="rktsky-antispam__card rktsky-antispam__record" aria-labelledby="rktsky-record-heading">
				<p class="rktsky-antispam__eyebrow"><?php esc_html_e( 'Rejected submission', 'wp-antispam' ); ?></p>
				<h2 id="rktsky-record-heading"><?php echo esc_html( $provider_labels[ $entry['provider'] ] ?? __( 'Form integration', 'wp-antispam' ) ); ?></h2>
				<p><?php esc_html_e( 'This submission was stopped by a local protection check. A failed check does not confirm that its contents are spam.', 'wp-antispam' ); ?></p>
				<dl class="rktsky-antispam__record-meta">
					<div><dt><?php esc_html_e( 'Received', 'wp-antispam' ); ?></dt><dd><?php echo esc_html( gmdate( 'Y-m-d H:i', (int) $entry['created'] ) ); ?> UTC</dd></div>
					<div><dt><?php esc_html_e( 'Form ID', 'wp-antispam' ); ?></dt><dd><?php echo esc_html( $entry['form_id'] ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Protection check', 'wp-antispam' ); ?></dt><dd><?php echo esc_html( $reason_labels[ $entry['reason'] ] ?? __( 'Protection check', 'wp-antispam' ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Scheduled for deletion', 'wp-antispam' ); ?></dt><dd><?php echo esc_html( gmdate( 'Y-m-d H:i', (int) $entry['expires'] ) ); ?> UTC</dd></div>
				</dl>
				<h3><?php esc_html_e( 'Stored fields', 'wp-antispam' ); ?></h3>
				<p class="rktsky-antispam__record-note"><?php esc_html_e( 'Only selected text fields are kept. Files and technical security fields are excluded. Long values may be shortened.', 'wp-antispam' ); ?></p>
				<?php if ( ! empty( $entry['unreadable'] ) ) : ?>
					<p class="rktsky-antispam__quarantine-disclosure"><?php esc_html_e( 'Stored fields could not be read. The record can still be deleted.', 'wp-antispam' ); ?></p>
				<?php elseif ( empty( $entry['fields'] ) ) : ?>
					<p class="rktsky-antispam__empty"><?php esc_html_e( 'No text fields were retained for this submission.', 'wp-antispam' ); ?></p>
				<?php else : ?>
					<dl class="rktsky-antispam__record-fields">
						<?php foreach ( $entry['fields'] as $rktsky_antispam_field => $rktsky_antispam_value ) : ?>
							<div><dt><?php echo esc_html( $rktsky_antispam_field ); ?></dt><dd><?php echo esc_html( $rktsky_antispam_value ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
				<?php if ( null !== $review_status ) : ?>
					<section class="rktsky-antispam__review" aria-labelledby="rktsky-review-heading"><h3 id="rktsky-review-heading"><?php esc_html_e( 'Review this decision', 'wp-antispam' ); ?></h3><p><?php esc_html_e( 'Your assessment updates the reviewed metrics and keeps this quarantined entry in place.', 'wp-antispam' ); ?></p>
						<?php
						if ( ! empty( $review_status['reviewed'] ) ) :
							?>
							<p class="rktsky-antispam__review-status"><?php echo esc_html( ! empty( $review_status['actual_spam'] ) ? __( 'Current assessment: spam', 'wp-antispam' ) : __( 'Current assessment: normal message', 'wp-antispam' ) ); ?></p><?php endif; ?>
						<form action="<?php echo esc_url( $delete_url ); ?>" method="post"><input type="hidden" name="action" value="rktsky_antispam_review" /><input type="hidden" name="entry" value="<?php echo esc_attr( (string) $entry['id'] ); ?>" /><?php wp_nonce_field( 'rktsky_antispam_review_' . (int) $entry['id'] ); ?><div class="rktsky-antispam__review-buttons"><button type="submit" name="assessment" value="spam" class="button"><?php esc_html_e( 'Confirm spam', 'wp-antispam' ); ?></button><button type="submit" name="assessment" value="normal" class="button"><?php esc_html_e( 'Not spam', 'wp-antispam' ); ?></button></div></form>
					</section>
				<?php endif; ?>
				<div class="rktsky-antispam__record-actions">
					<p><?php esc_html_e( 'Deleting this record is permanent. This screen cannot publish comments or resend forms.', 'wp-antispam' ); ?></p>
					<form action="<?php echo esc_url( $delete_url ); ?>" method="post">
						<input type="hidden" name="action" value="rktsky_antispam_quarantine_delete" />
						<input type="hidden" name="entry" value="<?php echo esc_attr( (string) $entry['id'] ); ?>" />
						<?php wp_nonce_field( 'rktsky_antispam_quarantine_delete_' . (int) $entry['id'] ); ?>
						<button type="submit" class="button rktsky-antispam__danger-button"><?php esc_html_e( 'Delete this record', 'wp-antispam' ); ?></button>
					</form>
				</div>
			</section>
		<?php endif; ?>
	<?php else : ?>
		<header class="rktsky-antispam__page-heading">
			<p class="rktsky-antispam__eyebrow"><?php esc_html_e( 'Optional local storage', 'wp-antispam' ); ?></p>
			<h2><?php esc_html_e( 'Review rejected submissions', 'wp-antispam' ); ?></h2>
			<p><?php esc_html_e( 'Quarantine is off by default. Enable it to review selected fields from future rejected comments and forms.', 'wp-antispam' ); ?></p>
		</header>
		<p class="rktsky-antispam__queue-note"><?php esc_html_e( 'WordPress spam comments stay in Comments → Spam. Review them there; the separate native-comment deletion period applies.', 'wp-antispam' ); ?> <a href="<?php echo esc_url( admin_url( 'edit-comments.php?comment_status=spam' ) ); ?>"><?php esc_html_e( 'WordPress comments', 'wp-antispam' ); ?> →</a></p>
		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post" class="rktsky-antispam__card rktsky-antispam__quarantine-settings">
			<?php settings_fields( 'rktsky_antispam' ); ?>
			<?php require __DIR__ . '/privacy-mode.php'; ?>
			<p class="rktsky-antispam__mode-hint" data-rk-mode-hint
			<?php
			if ( ! $privacy_mode ) :
				?>
				hidden<?php endif; ?>><?php esc_html_e( 'Turn off privacy-conscious mode to enable quarantine.', 'wp-antispam' ); ?></p>
			<input type="hidden" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[quarantine_enabled]" value="0" />
			<fieldset data-rk-optional-processing <?php disabled( $privacy_mode ); ?>>
			<legend class="screen-reader-text"><?php esc_html_e( 'Quarantine storage configuration', 'wp-antispam' ); ?></legend>
			<div class="rktsky-antispam__quarantine-controls">
				<div>
					<label class="rktsky-antispam__inline-check" for="rktsky-quarantine-enabled"><input type="checkbox" id="rktsky-quarantine-enabled" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[quarantine_enabled]" value="1" aria-describedby="rktsky-quarantine-privacy" <?php checked( ! empty( $options['quarantine_enabled'] ) ); ?> /><span><?php esc_html_e( 'Store rejected submissions in quarantine', 'wp-antispam' ); ?></span></label>
					<p class="description" id="rktsky-quarantine-privacy"><?php esc_html_e( 'Stored fields may include names, email addresses and messages. Update your privacy information before enabling this option. No data leaves your website.', 'wp-antispam' ); ?></p>
				</div>
				<div class="rktsky-antispam__field"><label for="rktsky-quarantine-days"><?php esc_html_e( 'Delete quarantined records after', 'wp-antispam' ); ?></label><div class="rktsky-antispam__number"><input type="number" id="rktsky-quarantine-days" name="<?php echo esc_attr( \Rktsky\Antispam\Settings::OPTION ); ?>[quarantine_days]" value="<?php echo esc_attr( (string) ( $options['quarantine_days'] ?? 7 ) ); ?>" min="1" max="30" step="1" aria-describedby="rktsky-quarantine-retention" /><span><?php esc_html_e( 'days', 'wp-antispam' ); ?></span></div></div>
			</div>
			</fieldset>
			<p class="rktsky-antispam__quarantine-disclosure" id="rktsky-quarantine-retention"><?php esc_html_e( 'Expired records are hidden immediately and removed by scheduled cleanup, which can run late. Disabling quarantine stops new storage; existing records remain until expiry. The local storage limit is 500 records.', 'wp-antispam' ); ?></p>
			<div class="rktsky-antispam__quarantine-save"><a href="<?php echo esc_url( admin_url( 'options-privacy.php' ) ); ?>"><?php esc_html_e( 'Review the suggested privacy policy text', 'wp-antispam' ); ?></a><button type="submit" class="button button-primary rktsky-antispam__save-button"><?php esc_html_e( 'Save quarantine settings', 'wp-antispam' ); ?></button></div>
		</form>
		<section class="rktsky-antispam__quarantine-list" aria-labelledby="rktsky-quarantine-list-heading">
			<div class="rktsky-antispam__section-title"><h2 id="rktsky-quarantine-list-heading"><?php esc_html_e( 'Rejected submissions', 'wp-antispam' ); ?></h2><span><?php echo esc_html( ! empty( $options['quarantine_enabled'] ) ? __( 'Quarantine enabled', 'wp-antispam' ) : __( 'New storage is off', 'wp-antispam' ) ); ?></span></div>
			<?php if ( empty( $listing['items'] ) ) : ?>
				<div class="rktsky-antispam__card rktsky-antispam__quarantine-empty"><h3><?php esc_html_e( 'No stored submissions', 'wp-antispam' ); ?></h3><p><?php esc_html_e( 'Records appear here when quarantine is enabled and WP Antispam rejects a new submission. Earlier rejections are not recovered.', 'wp-antispam' ); ?></p></div>
			<?php else : ?>
				<div class="rktsky-antispam__quarantine-table-wrap"><table class="rktsky-antispam__quarantine-table" role="table"><caption class="screen-reader-text"><?php esc_html_e( 'Rejected submission metadata. Open a record to view its stored fields.', 'wp-antispam' ); ?></caption><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader"><?php esc_html_e( 'Received', 'wp-antispam' ); ?></th><th scope="col" role="columnheader"><?php esc_html_e( 'Integration', 'wp-antispam' ); ?></th><th scope="col" role="columnheader"><?php esc_html_e( 'Form ID', 'wp-antispam' ); ?></th><th scope="col" role="columnheader"><?php esc_html_e( 'Protection check', 'wp-antispam' ); ?></th><th scope="col" role="columnheader"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'wp-antispam' ); ?></span></th></tr></thead><tbody role="rowgroup">
					<?php foreach ( $listing['items'] as $rktsky_antispam_row ) : ?>
						<tr role="row"><td role="cell"><span class="rktsky-antispam__mobile-label" aria-hidden="true"><?php esc_html_e( 'Received', 'wp-antispam' ); ?></span><?php echo esc_html( gmdate( 'Y-m-d H:i', (int) $rktsky_antispam_row['created'] ) ); ?> UTC</td><td role="cell"><span class="rktsky-antispam__mobile-label" aria-hidden="true"><?php esc_html_e( 'Integration', 'wp-antispam' ); ?></span><?php echo esc_html( $provider_labels[ $rktsky_antispam_row['provider'] ] ?? __( 'Form integration', 'wp-antispam' ) ); ?></td><td role="cell"><span class="rktsky-antispam__mobile-label" aria-hidden="true"><?php esc_html_e( 'Form ID', 'wp-antispam' ); ?></span><?php echo esc_html( $rktsky_antispam_row['form_id'] ); ?></td><td role="cell"><span class="rktsky-antispam__mobile-label" aria-hidden="true"><?php esc_html_e( 'Protection check', 'wp-antispam' ); ?></span><?php echo esc_html( $reason_labels[ $rktsky_antispam_row['reason'] ] ?? __( 'Protection check', 'wp-antispam' ) ); ?></td><td role="cell"><a class="button" href="<?php echo esc_url( add_query_arg( 'entry', (int) $rktsky_antispam_row['id'], $quarantine_url ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: local quarantine record ID. */ __( 'View quarantine record %d', 'wp-antispam' ), (int) $rktsky_antispam_row['id'] ) ); ?>"><?php esc_html_e( 'View', 'wp-antispam' ); ?></a></td></tr>
					<?php endforeach; ?>
				</tbody></table></div>
				<?php if ( (int) $listing['pages'] > 1 ) : ?>
					<nav class="rktsky-antispam__pagination" aria-label="<?php esc_attr_e( 'Quarantine pages', 'wp-antispam' ); ?>">
						<?php
						if ( (int) $listing['page'] > 1 ) :
							?>
							<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', (int) $listing['page'] - 1, $quarantine_url ) ); ?>"><?php esc_html_e( 'Previous', 'wp-antispam' ); ?></a><?php endif; ?>
						<span><?php echo esc_html( sprintf( /* translators: 1: current page number, 2: total pages. */ __( 'Page %1$d of %2$d', 'wp-antispam' ), (int) $listing['page'], (int) $listing['pages'] ) ); ?></span>
						<?php
						if ( (int) $listing['page'] < (int) $listing['pages'] ) :
							?>
							<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', (int) $listing['page'] + 1, $quarantine_url ) ); ?>"><?php esc_html_e( 'Next', 'wp-antispam' ); ?></a><?php endif; ?>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		</section>
	<?php endif; ?>
	<?php require __DIR__ . '/sponsors.php'; ?>
</div>
