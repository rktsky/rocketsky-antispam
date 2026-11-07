<?php
/**
 * Offers secondary feedback actions for delivered cases without retaining content.
 *
 * Each action has its own native nonce and separate form. Forgetting a keyed
 * reference is deliberately separate from assessing spam or legitimate mail.
 *
 * @package RktskyAntispam
 * @since 0.4.0
 * @var array<string,string> $provider_labels Fixed translated provider labels.
 */

defined( 'ABSPATH' ) || exit;
?>
<details class="rktsky-antispam__disclosure">
	<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Assess a delivered submission', 'wp-antispam' ); ?></span></summary>
	<div class="rktsky-antispam__disclosure-body">
		<p><?php esc_html_e( 'Use this for submissions that reached your inbox or entries list. Use the list above for quarantined submissions.', 'wp-antispam' ); ?></p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="rktsky-antispam__delivered-report">
			<input type="hidden" name="action" value="rktsky_antispam_classify_delivered" /><?php wp_nonce_field( 'rktsky_antispam_classify_delivered' ); ?>
			<div class="rktsky-antispam__report-fields">
				<div class="rktsky-antispam__field"><label for="rktsky-delivered-provider"><?php esc_html_e( 'Integration', 'wp-antispam' ); ?></label><select id="rktsky-delivered-provider" name="provider">
				<?php
				foreach ( $provider_labels as $rktsky_antispam_provider => $rktsky_antispam_label ) :
					?>
					<option value="<?php echo esc_attr( $rktsky_antispam_provider ); ?>"><?php echo esc_html( $rktsky_antispam_label ); ?></option><?php endforeach; ?></select></div>
				<div class="rktsky-antispam__field"><label for="rktsky-delivered-reference"><?php esc_html_e( 'Entry ID or internal reference', 'wp-antispam' ); ?></label><input type="text" id="rktsky-delivered-reference" name="reference" maxlength="128" required aria-describedby="rktsky-delivered-help" autocomplete="off" /></div>
				<div class="rktsky-antispam__field"><label for="rktsky-delivered-assessment"><?php esc_html_e( 'Assessment', 'wp-antispam' ); ?></label><select id="rktsky-delivered-assessment" name="assessment" required><option value=""><?php esc_html_e( 'Select an assessment', 'wp-antispam' ); ?></option><option value="spam"><?php esc_html_e( 'Spam', 'wp-antispam' ); ?></option><option value="normal"><?php esc_html_e( 'Not spam', 'wp-antispam' ); ?></option></select></div>
			</div>
			<p class="description" id="rktsky-delivered-help"><?php esc_html_e( 'Use an existing native entry ID or a stable internal case reference without personal content. Provider entries remain in place.', 'wp-antispam' ); ?></p>
			<button type="submit" class="button"><?php esc_html_e( 'Save assessment', 'wp-antispam' ); ?></button>
		</form>
	</div>
</details>
<details class="rktsky-antispam__disclosure">
	<summary><span class="rktsky-antispam__disclosure-title"><?php esc_html_e( 'Remove a saved case reference', 'wp-antispam' ); ?></span></summary>
	<div class="rktsky-antispam__disclosure-body">
		<p><?php esc_html_e( 'Forgetting removes the link; reviewed counts keep their evaluation window. Provider entries remain in place.', 'wp-antispam' ); ?></p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="rktsky_antispam_classify_delivered" /><input type="hidden" name="assessment" value="forget" /><?php wp_nonce_field( 'rktsky_antispam_classify_delivered' ); ?>
			<div class="rktsky-antispam__report-fields">
				<div class="rktsky-antispam__field"><label for="rktsky-forget-provider"><?php esc_html_e( 'Integration', 'wp-antispam' ); ?></label><select id="rktsky-forget-provider" name="provider">
				<?php
				foreach ( $provider_labels as $rktsky_antispam_provider => $rktsky_antispam_label ) :
					?>
					<option value="<?php echo esc_attr( $rktsky_antispam_provider ); ?>"><?php echo esc_html( $rktsky_antispam_label ); ?></option><?php endforeach; ?></select></div>
				<div class="rktsky-antispam__field"><label for="rktsky-forget-reference"><?php esc_html_e( 'Entry ID or internal reference', 'wp-antispam' ); ?></label><input type="text" id="rktsky-forget-reference" name="reference" maxlength="128" required autocomplete="off" /></div>
			</div>
			<button type="submit" class="button"><?php esc_html_e( 'Forget case reference', 'wp-antispam' ); ?></button>
		</form>
	</div>
</details>
