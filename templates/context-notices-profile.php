<?php
/**
 * Offers notice restoration inside the current user's existing Core profile form.
 *
 * The controller verifies the profile owner and native edit permission. The
 * unchecked checkbox changes nothing until the user explicitly saves it.
 *
 * @package RktskyAntispam
 * @since 0.5.5
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Protection notices', 'rocketsky-antispam' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row">Rocketsky Antispam</th>
		<td>
			<label for="rktsky-antispam-restore-notices"><input type="checkbox" name="rktsky_antispam_restore_notices" id="rktsky-antispam-restore-notices" value="1" aria-describedby="rktsky-antispam-notice-profile-description" /> <?php esc_html_e( 'Show protection notices again', 'rocketsky-antispam' ); ?></label>
			<p class="description" id="rktsky-antispam-notice-profile-description"><?php esc_html_e( 'Notice preferences apply only to your account on this website. They do not change protection.', 'rocketsky-antispam' ); ?></p>
			<?php wp_nonce_field( \Rktsky\Antispam\ContextNotices::restore_nonce_action(), 'rktsky_antispam_notices_nonce', false ); ?>
		</td>
	</tr>
</table>
