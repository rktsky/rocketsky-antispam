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
		<div class="rktsky-antispam__identity"><span class="rktsky-antispam__symbol" aria-hidden="true">a.</span><h1>WP Antispam</h1><span class="rktsky-antispam__byline"><?php esc_html_e( 'by rktsky', 'wp-antispam' ); ?></span></div>
		<span class="rktsky-antispam__version"><?php echo esc_html( RKTSKY_ANTISPAM_VERSION ); ?></span>
	</div>
	<nav class="rktsky-antispam__tabs" aria-label="<?php esc_attr_e( 'WP Antispam sections', 'wp-antispam' ); ?>">
		<?php foreach ( $tabs as $rktsky_antispam_tab => $rktsky_antispam_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $rktsky_antispam_tab, $base_url ) ); ?>" <?php if ( $tab === $rktsky_antispam_tab ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( $rktsky_antispam_label ); ?></a>
		<?php endforeach; ?>
	</nav>
