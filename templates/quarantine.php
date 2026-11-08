<?php
/**
 * Displays the review queue and escaped submissions without storage configuration.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 * @since 0.4.0 Moved configuration to Settings and delivered-case review here.
 * @since 0.5.2 Distinguishes effective capture from unavailable encryption.
 * @var array<string,mixed> $options Validated settings.
 * @var array<string,mixed> $listing Bounded metadata page; no submitted fields.
 * @var array<string,mixed>|null $entry Authorized selected record, or null.
 * @var array<string,mixed>|null $review_status Tracked prediction and explicit human assessment, or null.
 * @var int $entry_id Requested record identifier; zero selects the list.
 * @var array<string,string> $provider_labels Fixed integration labels.
 * @var array<string,string> $reason_labels Fixed check labels.
 * @var string $quarantine_url Local quarantine list URL.
 * @var string $delete_url Authenticated POST endpoint.
 * @var string $quarantine_status Effective capture state, including encryption readiness.
 * @var bool $quarantine_unavailable Whether configured capture lacks encryption support.
 * @var \Rktsky\Antispam\SpamHandling $spam_handling Active integration review destinations.
 */

defined( 'ABSPATH' ) || exit;
require __DIR__ . '/admin-header.php';
?>
	<?php
	if ( isset( $_GET['record-deleted'] ) && '1' === $_GET['record-deleted'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixed success notice, never a mutation.
		?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Quarantine record deleted.', 'rocketsky-antispam' ); ?></p></div>
		<?php
	elseif ( isset( $_GET['record-deleted'] ) && '0' === $_GET['record-deleted'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixed failure notice, never a mutation.
		?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'The record is no longer available or could not be deleted.', 'rocketsky-antispam' ); ?></p></div>
	<?php endif; ?>
	<?php
	if ( isset( $_GET['review-updated'] ) && '1' === $_GET['review-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixed review notice.
		?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Review saved. The submission remains in quarantine.', 'rocketsky-antispam' ); ?></p></div>
		<?php
	elseif ( isset( $_GET['review-updated'] ) && '0' === $_GET['review-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixed failure notice.
		?>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'This message cannot be assessed because its saved decision is unavailable or has expired.', 'rocketsky-antispam' ); ?></p></div>
	<?php endif; ?>
	<?php
	if ( isset( $_GET['classification-saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Fixed read-only action result; query input is never reflected.
		$rktsky_antispam_classification_saved = '1' === $_GET['classification-saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Strict comparison selects static read-only text only.
		?>
		<div class="notice <?php echo $rktsky_antispam_classification_saved ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $rktsky_antispam_classification_saved ? __( 'Assessment saved.', 'rocketsky-antispam' ) : __( 'Could not complete this action. Check the entry ID or reference and try again.', 'rocketsky-antispam' ) ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['reference-forgotten'] ) && '1' === $_GET['reference-forgotten'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Static read-only success notice. ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Reference removed. Reviewed totals remain until their original expiry.', 'rocketsky-antispam' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $entry_id > 0 ) : ?>
		<a href="<?php echo esc_url( $quarantine_url ); ?>" class="rktsky-antispam__back-link">← <?php esc_html_e( 'Back to rejected submissions', 'rocketsky-antispam' ); ?></a>
		<?php if ( null === $entry ) : ?>
			<section class="rktsky-antispam__card rktsky-antispam__quarantine-empty"><h2><?php esc_html_e( 'This record is unavailable', 'rocketsky-antispam' ); ?></h2><p><?php esc_html_e( 'It may have expired or already been deleted.', 'rocketsky-antispam' ); ?></p></section>
		<?php else : ?>
			<section class="rktsky-antispam__card rktsky-antispam__record" aria-labelledby="rktsky-record-heading">
				<p class="rktsky-antispam__eyebrow"><?php esc_html_e( 'Quarantined message', 'rocketsky-antispam' ); ?></p>
				<h2 id="rktsky-record-heading"><?php echo esc_html( $provider_labels[ $entry['provider'] ] ?? __( 'Form integration', 'rocketsky-antispam' ) ); ?></h2>
				<p><?php esc_html_e( 'This message was rejected by a protection check. Review its contents before marking it as spam.', 'rocketsky-antispam' ); ?></p>
				<dl class="rktsky-antispam__record-meta">
					<div><dt><?php esc_html_e( 'Received', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( gmdate( 'Y-m-d H:i', (int) $entry['created'] ) ); ?> UTC</dd></div>
					<div><dt><?php esc_html_e( 'Form ID', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( $entry['form_id'] ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Protection check', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( $reason_labels[ $entry['reason'] ] ?? __( 'Protection check', 'rocketsky-antispam' ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Expires', 'rocketsky-antispam' ); ?></dt><dd><?php echo esc_html( gmdate( 'Y-m-d H:i', (int) $entry['expires'] ) ); ?> UTC</dd></div>
				</dl>
				<h3><?php esc_html_e( 'Stored fields', 'rocketsky-antispam' ); ?></h3>
				<p class="rktsky-antispam__record-note"><?php esc_html_e( 'Only selected text fields are stored. Files and verification fields are excluded. Long values may be shortened.', 'rocketsky-antispam' ); ?></p>
				<?php if ( ! empty( $entry['unreadable'] ) ) : ?>
					<p class="rktsky-antispam__quarantine-disclosure"><?php esc_html_e( 'Stored fields could not be read. You can still delete this record.', 'rocketsky-antispam' ); ?></p>
				<?php elseif ( empty( $entry['fields'] ) ) : ?>
					<p class="rktsky-antispam__empty"><?php esc_html_e( 'No text fields were retained for this submission.', 'rocketsky-antispam' ); ?></p>
				<?php else : ?>
					<dl class="rktsky-antispam__record-fields">
						<?php foreach ( $entry['fields'] as $rktsky_antispam_field => $rktsky_antispam_value ) : ?>
							<div><dt><?php echo esc_html( $rktsky_antispam_field ); ?></dt><dd><?php echo esc_html( $rktsky_antispam_value ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
				<?php if ( null !== $review_status ) : ?>
					<section class="rktsky-antispam__review" aria-labelledby="rktsky-review-heading"><h3 id="rktsky-review-heading"><?php esc_html_e( 'Assess this message', 'rocketsky-antispam' ); ?></h3><p><?php esc_html_e( 'This updates the reviewed results. The message stays in quarantine.', 'rocketsky-antispam' ); ?></p>
						<?php
						if ( ! empty( $review_status['reviewed'] ) ) :
							?>
							<p class="rktsky-antispam__review-status"><?php echo esc_html( ! empty( $review_status['actual_spam'] ) ? __( 'Assessment: spam', 'rocketsky-antispam' ) : __( 'Assessment: not spam', 'rocketsky-antispam' ) ); ?></p><?php endif; ?>
						<form action="<?php echo esc_url( $delete_url ); ?>" method="post"><input type="hidden" name="action" value="rktsky_antispam_review" /><input type="hidden" name="entry" value="<?php echo esc_attr( (string) $entry['id'] ); ?>" /><?php wp_nonce_field( 'rktsky_antispam_review_' . (int) $entry['id'] ); ?><div class="rktsky-antispam__review-buttons"><button type="submit" name="assessment" value="spam" class="button"><?php esc_html_e( 'Confirm spam', 'rocketsky-antispam' ); ?></button><button type="submit" name="assessment" value="normal" class="button"><?php esc_html_e( 'Not spam', 'rocketsky-antispam' ); ?></button></div></form>
					</section>
				<?php endif; ?>
				<div class="rktsky-antispam__record-actions">
					<p><?php esc_html_e( 'This permanently deletes the quarantined message.', 'rocketsky-antispam' ); ?></p>
					<form action="<?php echo esc_url( $delete_url ); ?>" method="post">
						<input type="hidden" name="action" value="rktsky_antispam_quarantine_delete" />
						<input type="hidden" name="entry" value="<?php echo esc_attr( (string) $entry['id'] ); ?>" />
						<?php wp_nonce_field( 'rktsky_antispam_quarantine_delete_' . (int) $entry['id'] ); ?>
						<button type="submit" class="button rktsky-antispam__danger-button"><?php esc_html_e( 'Delete this record', 'rocketsky-antispam' ); ?></button>
					</form>
				</div>
			</section>
		<?php endif; ?>
	<?php else : ?>
		<header class="rktsky-antispam__page-heading">
			<h2><?php esc_html_e( 'Review spam', 'rocketsky-antispam' ); ?></h2>
			<p><?php esc_html_e( 'Choose an integration to review its spam or activity, or open a quarantined message below.', 'rocketsky-antispam' ); ?></p>
		</header>
		<?php $rktsky_antispam_directory = $spam_handling->directory(); ?>
		<?php if ( ! empty( $rktsky_antispam_directory ) ) : ?>
			<section class="rktsky-antispam__card rktsky-antispam__spam-directory" aria-labelledby="rktsky-spam-directory-heading">
				<h3 id="rktsky-spam-directory-heading"><?php esc_html_e( 'Where to review spam', 'rocketsky-antispam' ); ?></h3>
				<dl>
					<?php foreach ( $rktsky_antispam_directory as $rktsky_antispam_provider => $rktsky_antispam_route ) : ?>
						<div><dt><?php echo esc_html( $provider_labels[ $rktsky_antispam_provider ] ); ?></dt><dd><a href="<?php echo esc_url( $rktsky_antispam_route['url'] ); ?>"><?php echo esc_html( $rktsky_antispam_route['label'] ); ?></a>
							<?php
							if ( in_array( $rktsky_antispam_route['mode'], array( 'blocked', 'conditional' ), true ) ) :
								?>
								<p><?php echo esc_html( $rktsky_antispam_route['description'] ); ?></p><?php endif; ?>
						</dd></div>
					<?php endforeach; ?>
				</dl>
				<p class="rktsky-antispam__help-note"><?php esc_html_e( 'Your form plugin controls its own spam storage and retention, including in privacy-conscious mode.', 'rocketsky-antispam' ); ?> <?php echo esc_html( $spam_handling->local_retention()['description'] ); ?></p>
			</section>
		<?php endif; ?>

		<section class="rktsky-antispam__quarantine-list" aria-labelledby="rktsky-quarantine-list-heading">
			<div class="rktsky-antispam__section-title"><h2 id="rktsky-quarantine-list-heading"><?php esc_html_e( 'Quarantined messages', 'rocketsky-antispam' ); ?></h2><span><?php echo esc_html( $quarantine_status ); ?></span></div>
			<?php if ( $quarantine_unavailable ) : ?>
				<p class="rktsky-antispam__help-note"><?php esc_html_e( 'Encryption is unavailable, so new messages cannot be stored. Ask your hosting provider to check WordPress Sodium support.', 'rocketsky-antispam' ); ?></p>
			<?php endif; ?>
			<?php if ( empty( $listing['items'] ) ) : ?>
				<div class="rktsky-antispam__card rktsky-antispam__quarantine-empty"><h3><?php esc_html_e( 'No stored submissions', 'rocketsky-antispam' ); ?></h3><p><?php esc_html_e( 'When enabled, quarantine can keep selected fields from rejected contact form submissions. Messages already discarded cannot be recovered.', 'rocketsky-antispam' ); ?></p><a class="button" href="<?php echo esc_url( $view_urls['settings'] . '#retention' ); ?>"><?php esc_html_e( 'Storage settings', 'rocketsky-antispam' ); ?></a></div>
			<?php else : ?>
				<div class="rktsky-antispam__quarantine-table-wrap"><table class="rktsky-antispam__quarantine-table" role="table"><caption class="screen-reader-text"><?php esc_html_e( 'Quarantined messages. Open a record to read its stored fields.', 'rocketsky-antispam' ); ?></caption><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader"><?php esc_html_e( 'Received', 'rocketsky-antispam' ); ?></th><th scope="col" role="columnheader"><?php esc_html_e( 'Integration', 'rocketsky-antispam' ); ?></th><th scope="col" role="columnheader"><?php esc_html_e( 'Form ID', 'rocketsky-antispam' ); ?></th><th scope="col" role="columnheader"><?php esc_html_e( 'Protection check', 'rocketsky-antispam' ); ?></th><th scope="col" role="columnheader"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'rocketsky-antispam' ); ?></span></th></tr></thead><tbody role="rowgroup">
					<?php foreach ( $listing['items'] as $rktsky_antispam_row ) : ?>
						<tr role="row"><td role="cell"><span class="rktsky-antispam__mobile-label" aria-hidden="true"><?php esc_html_e( 'Received', 'rocketsky-antispam' ); ?></span><?php echo esc_html( gmdate( 'Y-m-d H:i', (int) $rktsky_antispam_row['created'] ) ); ?> UTC</td><td role="cell"><span class="rktsky-antispam__mobile-label" aria-hidden="true"><?php esc_html_e( 'Integration', 'rocketsky-antispam' ); ?></span><?php echo esc_html( $provider_labels[ $rktsky_antispam_row['provider'] ] ?? __( 'Form integration', 'rocketsky-antispam' ) ); ?></td><td role="cell"><span class="rktsky-antispam__mobile-label" aria-hidden="true"><?php esc_html_e( 'Form ID', 'rocketsky-antispam' ); ?></span><?php echo esc_html( $rktsky_antispam_row['form_id'] ); ?></td><td role="cell"><span class="rktsky-antispam__mobile-label" aria-hidden="true"><?php esc_html_e( 'Protection check', 'rocketsky-antispam' ); ?></span><?php echo esc_html( $reason_labels[ $rktsky_antispam_row['reason'] ] ?? __( 'Protection check', 'rocketsky-antispam' ) ); ?></td><td role="cell"><a class="button" href="<?php echo esc_url( add_query_arg( 'entry', (int) $rktsky_antispam_row['id'], $quarantine_url ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: local quarantine record ID. */ __( 'View quarantine record %d', 'rocketsky-antispam' ), (int) $rktsky_antispam_row['id'] ) ); ?>"><?php esc_html_e( 'View', 'rocketsky-antispam' ); ?></a></td></tr>
					<?php endforeach; ?>
				</tbody></table></div>
				<?php if ( (int) $listing['pages'] > 1 ) : ?>
					<nav class="rktsky-antispam__pagination" aria-label="<?php esc_attr_e( 'Quarantine pages', 'rocketsky-antispam' ); ?>">
						<?php
						if ( (int) $listing['page'] > 1 ) :
							?>
							<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', (int) $listing['page'] - 1, $quarantine_url ) ); ?>"><?php esc_html_e( 'Previous', 'rocketsky-antispam' ); ?></a><?php endif; ?>
						<span><?php echo esc_html( sprintf( /* translators: 1: current page number, 2: total pages. */ __( 'Page %1$d of %2$d', 'rocketsky-antispam' ), (int) $listing['page'], (int) $listing['pages'] ) ); ?></span>
						<?php
						if ( (int) $listing['page'] < (int) $listing['pages'] ) :
							?>
							<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', (int) $listing['page'] + 1, $quarantine_url ) ); ?>"><?php esc_html_e( 'Next', 'rocketsky-antispam' ); ?></a><?php endif; ?>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php require __DIR__ . '/delivered-review.php'; ?>
	<?php endif; ?>
	<?php require __DIR__ . '/sponsors.php'; ?>
</div>
