<?php
/**
 * Creates only synthetic WooCommerce data in an explicitly disposable native site.
 *
 * @package RktskyAntispamTests
 * @since 0.3.0
 */
require __DIR__ . '/bootstrap.php';

if ( ! class_exists( 'WooCommerce' ) || version_compare( WC_VERSION, '9.9', '<' ) ) {
	throw new RuntimeException( 'An original WooCommerce 9.9+ installation is required.' );
}

$settings = get_option( \Rktsky\Antispam\Settings::OPTION, array() );
$settings['wc_checkout'] = true;
$settings['wc_registration'] = true;
$settings['minimum_seconds'] = 0;
update_option( \Rktsky\Antispam\Settings::OPTION, $settings, false );
update_option( 'woocommerce_store_address', 'Synthetic test address' );
update_option( 'woocommerce_default_country', 'CH' );
update_option( 'woocommerce_currency', 'CHF' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
update_option( 'woocommerce_registration_generate_username', 'yes' );
update_option( 'woocommerce_registration_generate_password', 'yes' );
update_option( 'woocommerce_calc_taxes', 'no' );
update_option( 'woocommerce_email_enabled', 'no' );
update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'yes', 'title' => 'Synthetic offline bank transfer', 'description' => 'Disposable fixture only; do not transfer funds.' ) );

$checkout_block = '';
$default_pages = static function ( array $pages ) use ( &$checkout_block ): array { $checkout_block = $pages['checkout']['content']; return $pages; };
add_filter( 'woocommerce_create_pages', $default_pages );
WC_Install::create_pages();
remove_filter( 'woocommerce_create_pages', $default_pages );
$fixture = get_option( 'rktsky_antispam_test_woocommerce', array() );
if ( empty( $fixture['product'] ) || ! wc_get_product( $fixture['product'] ) ) {
	$product = new WC_Product_Simple();
	$product->set_name( 'Synthetic disposable WooCommerce product' );
	$product->set_status( 'publish' );
	$product->set_virtual( true );
	$product->set_regular_price( '12.00' );
	$product->set_catalog_visibility( 'hidden' );
	$fixture['product'] = $product->save();
}
foreach ( array( 'classic' => '[woocommerce_checkout]', 'block' => $checkout_block, 'account' => '[woocommerce_my_account]', 'cart' => '[woocommerce_cart]' ) as $kind => $content ) {
	if ( empty( $fixture[ $kind ] ) || ! get_post( $fixture[ $kind ] ) ) {
		$fixture[ $kind ] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Synthetic WooCommerce ' . $kind, 'post_content' => $content ), true );
		if ( is_wp_error( $fixture[ $kind ] ) ) {
			throw new RuntimeException( 'Synthetic WooCommerce page creation failed.' );
		}
	}
}
// Obtain WooCommerce's own default block content through its public page-creation filter.
wp_update_post( array( 'ID' => $fixture['block'], 'post_content' => $checkout_block ) );
update_option( 'woocommerce_checkout_page_id', $fixture['classic'] );
update_option( 'woocommerce_myaccount_page_id', $fixture['account'] );
update_option( 'woocommerce_cart_page_id', $fixture['cart'] );
update_option( 'rktsky_antispam_test_woocommerce', $fixture, false );
echo wp_json_encode( array_merge( array( 'version' => WC_VERSION ), $fixture ) ) . "\n";
