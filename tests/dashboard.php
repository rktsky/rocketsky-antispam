<?php
/**
 * Verifies widget authorization, asset scope and empty/reviewed presentations.
 *
 * The native fixture is read only. Synthetic presentation cases are passed to
 * the owned template within this process, without changing counters or feedback.
 * tests/policies.php separately exercises the real Store aggregation contract.
 *
 * @package RktskyAntispamTests
 * @since 0.5.2
 */

use Rktsky\Antispam\Dashboard;
use Rktsky\Antispam\Store;

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/dashboard.php';

$rktsky_widget_assertions = 0;
$rktsky_widget_allowed    = false;

/**
 * Fails when an actual native widget boundary or rendered state changes.
 *
 * @since 0.5.2
 * @param bool   $condition Required observable behavior.
 * @param string $message Non-personal failure context.
 * @return void
 */
function rktsky_widget_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_widget_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Renders a bounded synthetic presentation case without persisting test metrics.
 *
 * @since 0.5.2
 * @param array $summary Seven-day accepted and blocked totals.
 * @param int   $total Independent lifetime comment rejection count.
 * @param array $quality Reviewed counts and nullable fractional rates.
 * @return string Escaped owned widget markup.
 */
function rktsky_widget_case( array $summary, int $total, array $quality ): string {
	$days = array();
	for ( $offset = 6; $offset >= 0; --$offset ) {
		$day    = gmdate( 'Y-m-d', time() - $offset * DAY_IN_SECONDS );
		$days[] = array( 'day' => $day, 'label' => gmdate( 'j M', strtotime( $day . ' UTC' ) ), 'accepted' => 0 === $offset ? $summary['accepted'] : 0, 'blocked' => 0 === $offset ? $summary['blocked'] : 0 );
	}
	ob_start();
	include dirname( __DIR__ ) . '/templates/dashboard.php';
	return (string) ob_get_clean();
}

/**
 * Parses actual widget HTML through the native PHP DOM extension.
 *
 * @since 0.5.2
 * @param string $html Rendered owned markup.
 * @return DOMXPath Parsed widget queries.
 */
function rktsky_widget_document( string $html ): DOMXPath {
	$document = new DOMDocument();
	@$document->loadHTML( $html );
	return new DOMXPath( $document );
}

/**
 * Models only the administrator boundary without changing a user or role.
 *
 * @since 0.5.2
 * @param array<string,bool> $caps Native primitive capabilities.
 * @return array<string,bool> Process-local manage_options decision.
 */
$rktsky_widget_capabilities = static function ( array $caps ) use ( &$rktsky_widget_allowed ): array {
	$caps['manage_options'] = $rktsky_widget_allowed;
	return $caps;
};
add_filter( 'user_has_cap', $rktsky_widget_capabilities, PHP_INT_MAX );

try {
	set_current_screen( 'dashboard' );
	Dashboard::register();
	rktsky_widget_expect( ! isset( $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core']['rktsky_antispam_dashboard'] ), 'A user without manage_options must receive no widget.' );
	Dashboard::assets( 'index.php' );
	rktsky_widget_expect( ! wp_style_is( 'rktsky-antispam-dashboard', 'enqueued' ), 'An unauthorized user must receive no widget stylesheet.' );
	ob_start();
	Dashboard::render();
	rktsky_widget_expect( '' === ob_get_clean(), 'Direct rendering must retain the administrator authorization boundary.' );

	$rktsky_widget_allowed = true;
	Dashboard::register();
	rktsky_widget_expect( isset( $GLOBALS['wp_meta_boxes']['dashboard']['normal']['core']['rktsky_antispam_dashboard'] ), 'An administrator must receive the native dashboard postbox.' );
	rktsky_widget_expect( false !== has_action( 'admin_enqueue_scripts', array( Dashboard::class, 'assets' ) ), 'The asset callback must use the documented native enqueue hook.' );
	set_current_screen( 'options-general' );
	Dashboard::assets( 'options-general.php' );
	rktsky_widget_expect( ! wp_style_is( 'rktsky-antispam-dashboard', 'enqueued' ), 'Other administration screens must receive no widget stylesheet.' );
	Dashboard::assets( 'index.php' );
	rktsky_widget_expect( ! wp_style_is( 'rktsky-antispam-dashboard', 'enqueued' ), 'An index filename without the actual dashboard screen must not load widget CSS.' );
	set_current_screen( 'dashboard' );
	Dashboard::assets( 'index.php' );
	rktsky_widget_expect( wp_style_is( 'rktsky-antispam-dashboard', 'enqueued' ), 'The authorized native dashboard must enqueue its widget stylesheet.' );
	$rktsky_widget_style = wp_styles()->registered['rktsky-antispam-dashboard'];
	rktsky_widget_expect( plugins_url( 'assets/dashboard.css', RKTSKY_ANTISPAM_FILE ) === $rktsky_widget_style->src && RKTSKY_ANTISPAM_VERSION === $rktsky_widget_style->ver && array() === $rktsky_widget_style->deps, 'The widget must use only its versioned local stylesheet, without extra dependencies.' );

	$rktsky_widget_quality = array( 'reviewed' => 0, 'pending' => 0, 'period_days' => 90, 'precision' => null, 'recall' => null, 'accuracy' => null );
	$rktsky_widget_empty   = rktsky_widget_case( array( 'accepted' => 0, 'blocked' => 0 ), 12, $rktsky_widget_quality );
	$rktsky_widget_xpath   = rktsky_widget_document( $rktsky_widget_empty );
	rktsky_widget_expect( 0 === $rktsky_widget_xpath->query( '//*[@id="rktsky-widget-stopped" or @id="rktsky-widget-passed"]' )->length, 'Empty activity must show an explanation instead of two unqualified zero cards.' );
	rktsky_widget_expect( 0 === $rktsky_widget_xpath->query( '//dl[contains(@class,"__rates")]' )->length && ! str_contains( $rktsky_widget_empty, '—' ), 'An unreviewed dataset must not display undefined quality metrics.' );
	rktsky_widget_expect( 0 === $rktsky_widget_xpath->query( '//*[contains(@class,"__rate") or contains(@class,"__daily-rate")]' )->length, 'Zero checked submissions must not produce a percentage.' );
	rktsky_widget_expect( '12' === $rktsky_widget_xpath->evaluate( 'string(//*[@id="rktsky-widget-comments"])' ), 'An empty seven-day series must preserve its independently accumulated lifetime comment total.' );
	rktsky_widget_expect( 7 === $rktsky_widget_xpath->query( '//details[not(@open)]//tbody/tr' )->length, 'Seven UTC-day rows must remain available in a closed native disclosure.' );
	rktsky_widget_expect( 2 === $rktsky_widget_xpath->query( '//a[contains(@href,"page=rocketsky-antispam")]' )->length, 'Both overview and review destinations must remain available in the empty widget.' );

	$rktsky_widget_quality = array( 'reviewed' => 10, 'pending' => 2, 'period_days' => 90, 'precision' => 0.75, 'recall' => 0.6, 'accuracy' => 0.7 );
	$rktsky_widget_filled  = rktsky_widget_case( array( 'accepted' => 18, 'blocked' => 2 ), 500, $rktsky_widget_quality );
	$rktsky_widget_xpath   = rktsky_widget_document( $rktsky_widget_filled );
	rktsky_widget_expect( '2' === $rktsky_widget_xpath->evaluate( 'string(//*[@id="rktsky-widget-stopped"])' ) && '18' === $rktsky_widget_xpath->evaluate( 'string(//*[@id="rktsky-widget-passed"])' ), 'Populated counts must retain their distinct seven-day totals.' );
	rktsky_widget_expect( '500' === $rktsky_widget_xpath->evaluate( 'string(//*[@id="rktsky-widget-comments"])' ), 'The lifetime total must never be replaced by the seven-day rejection count.' );
	rktsky_widget_expect( 1 === $rktsky_widget_xpath->query( '//*[contains(@class,"__daily-rate")]' )->length && str_contains( $rktsky_widget_xpath->evaluate( 'string(//*[contains(@class,"__rate")])' ), number_format_i18n( 10, 1 ) . '%' ), 'Only the active day must expose its real operative rejection share.' );
	rktsky_widget_expect( 3 === $rktsky_widget_xpath->query( '//dl[contains(@class,"__rates")]/div' )->length && str_contains( $rktsky_widget_filled, number_format_i18n( 75, 1 ) . '%' ) && str_contains( $rktsky_widget_filled, number_format_i18n( 60, 1 ) . '%' ) && str_contains( $rktsky_widget_filled, number_format_i18n( 70, 1 ) . '%' ), 'Reviewed rates must render their actual fractional values rather than the operational rejection share.' );
	$rktsky_widget_quality['precision'] = null;
	$rktsky_widget_xpath = rktsky_widget_document( rktsky_widget_case( array( 'accepted' => 18, 'blocked' => 2 ), 500, $rktsky_widget_quality ) );
	rktsky_widget_expect( 2 === $rktsky_widget_xpath->query( '//dl[contains(@class,"__rates")]/div' )->length, 'A metric without a reviewed denominator must be omitted rather than fabricated.' );

	$rktsky_widget_actual = ( new Store() )->summary( 7 );
	ob_start();
	Dashboard::render();
	$rktsky_widget_native = (string) ob_get_clean();
	$rktsky_widget_xpath  = rktsky_widget_document( $rktsky_widget_native );
	rktsky_widget_expect( 7 === $rktsky_widget_xpath->query( '//tbody/tr' )->length, 'The actual native controller must also produce exactly seven day rows.' );
	if ( $rktsky_widget_actual['accepted'] + $rktsky_widget_actual['blocked'] > 0 ) {
		rktsky_widget_expect( number_format_i18n( $rktsky_widget_actual['blocked'] ) === $rktsky_widget_xpath->evaluate( 'string(//*[@id="rktsky-widget-stopped"])' ), 'The native widget must use Store summary data without inventing activity.' );
	} else {
		rktsky_widget_expect( 1 === $rktsky_widget_xpath->query( '//*[contains(@class,"__empty")]' )->length, 'A truly empty native Store must select the readable empty state.' );
	}
	echo 'PASS: ' . $rktsky_widget_assertions . " native widget assertions without persistent fixture mutations.\n";
} finally {
	remove_filter( 'user_has_cap', $rktsky_widget_capabilities, PHP_INT_MAX );
	wp_dequeue_style( 'rktsky-antispam-dashboard' );
	remove_action( 'admin_enqueue_scripts', array( Dashboard::class, 'assets' ) );
}
