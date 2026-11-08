<?php
/**
 * Opens the shared administration shell and ordinary link navigation.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 * @var array<string,string> $tabs Fixed view labels.
 * @var string $tab Current allowlisted view.
 * @var string $base_url Base settings screen URL.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap rktsky-antispam">
	<div class="rktsky-antispam__masthead">
		<div class="rktsky-antispam__identity"><span class="rktsky-antispam__symbol" aria-hidden="true">a.</span><h1>Rocketsky Antispam</h1><a class="rktsky-antispam__byline" href="https://www.rocketsky.ch/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'by Rocketsky', 'rocketsky-antispam' ); ?></a></div>
		<div class="rktsky-antispam__masthead-actions">
			<a class="rktsky-antispam__review-link" href="https://wordpress.org/support/plugin/rocketsky-antispam/reviews/#new-post" target="_blank" rel="noopener noreferrer"><span><?php esc_html_e( 'Write a review', 'rocketsky-antispam' ); ?></span><svg class="rktsky-antispam__external-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M7 17 17 7M7 7h10v10" /></svg></a>
			<span class="rktsky-antispam__version"><?php echo esc_html( RKTSKY_ANTISPAM_VERSION ); ?></span>
		</div>
	</div>
	<nav class="rktsky-antispam__tabs" aria-label="<?php esc_attr_e( 'Rocketsky Antispam sections', 'rocketsky-antispam' ); ?>">
		<?php foreach ( $tabs as $rktsky_antispam_tab => $rktsky_antispam_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $rktsky_antispam_tab, $base_url ) ); ?>"
			<?php
			if ( $tab === $rktsky_antispam_tab ) :
				?>
				aria-current="page"<?php endif; ?>><?php echo esc_html( $rktsky_antispam_label ); ?></a>
		<?php endforeach; ?>
	</nav>
