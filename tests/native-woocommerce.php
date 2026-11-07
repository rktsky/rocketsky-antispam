<?php
/**
 * Exercises original WooCommerce checkout/customer/payment engines on a disposable site.
 *
 * Each case has a fresh PHP request because WooCommerce retains request-local state.
 * All mail and external HTTP must remain blocked by the private fixture bootstrap.
 *
 * @package RktskyAntispamTests
 * @since 0.3.0
 */
$rktsky_case = $argv[1] ?? '';
if ( str_starts_with( $rktsky_case, 'store_' ) ) {
	define( 'REST_REQUEST', true );
}
require __DIR__ . '/bootstrap.php';

/**
 * Stops a regression at its observable native boundary.
 *
 * @since 0.3.0
 * @param bool   $condition Required actual behaviour.
 * @param string $message Failure context without native customer payloads.
 * @return void
 */
function rktsky_antispam_woo_expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		throw new RuntimeException( $message );
	}
}

if ( ! class_exists( 'WooCommerce' ) ) {
	echo "SKIP: Original WooCommerce is not installed on this fixture.\n";
	return;
}
$fixture = get_option( 'rktsky_antispam_test_woocommerce', array() );
rktsky_antispam_woo_expect( ! empty( $fixture['product'] ), 'Prepare the synthetic WooCommerce fixture first.' );
if ( '' === $rktsky_case ) {
	$cases = array(
		'render',
		'classic_missing',
		'classic_honeypot',
		'classic_valid',
		'classic_repeat',
		'classic_retry',
		'classic_update',
		'classic_bad_nonce',
		'register_missing',
		'register_honeypot',
		'register_valid',
		'register_repeat',
		'register_bad_nonce',
		'pay_missing',
		'pay_honeypot',
		'pay_valid',
		'pay_bad_nonce',
		'store_missing',
		'store_honeypot',
		'store_valid',
		'store_repeat',
		'store_retry',
		'store_updates',
		'store_batch_missing',
		'store_bad_nonce',
		'store_case_alias',
		'store_versionless',
		'store_wc_batch_missing',
		'store_pay_missing',
		'store_pay_valid',
		'store_pay_bad_key',
		'store_malformed',
		'store_other_extensions',
	);
	foreach ( $cases as $name ) {
		$command = array( PHP_BINARY );
		if ( ! extension_loaded( 'mbstring' ) && is_file( '/tmp/rktsky-antispam-runtime/iconv.so' ) ) {
			$command[] = '-d';
			$command[] = 'extension=/tmp/rktsky-antispam-runtime/iconv.so';
		}
		$command[] = __FILE__;
		$command[] = $name;
		$pipes     = array();
		$process   = proc_open(
			$command,
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes
		);
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error  = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$status = proc_close( $process );
		if ( 0 !== $status || ! str_contains( $output, 'PASS: ' . $name ) ) {
			throw new RuntimeException( 'Native WooCommerce case failed: ' . $name . "\n" . $output . "\n" . $error );
		}
		echo trim( $output ) . "\n";
	}
	echo 'PASS: ' . count( $cases ) . ' original WooCommerce ' . WC_VERSION . " cases.\n";
	return;
}

/**
 * Supplies a local payment counter without sending or simulating an external payment.
 *
 * Native WooCommerce invokes the real gateway interface, whose failed result avoids
 * redirects/exits and leaves native order/account creation observable to this test.
 *
 * @since 0.3.0
 */
class Rktsky_Antispam_Fixture_Gateway extends WC_Payment_Gateway {
	/**
	 * Declares an always available local synthetic gateway.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function __construct() {
		$this->id       = 'rktsky_fixture';
		$this->enabled  = 'yes';
		$this->title    = 'Synthetic local fixture';
		$this->supports = array( 'products' );
	}

	/**
	 * Counts the native payment boundary and returns an intentional local failure.
	 *
	 * @since 0.3.0
	 * @param int $order_id Native order ID, deliberately not transmitted.
	 * @return array Native unsuccessful gateway result.
	 */
	public function process_payment( $order_id ) {
		++$GLOBALS['rktsky_woo_payments'];
		return array( 'result' => 'failure' );
	}
}

/**
 * Selects only native orders, including checkout drafts, for fixture cleanup.
 *
 * @since 0.3.0
 * @return int[] Native original order identifiers.
 */
function rktsky_antispam_woo_orders(): array {
	return array_map(
		'intval',
		wc_get_orders(
			array(
				'limit'  => -1,
				'return' => 'ids',
				'status' => array_merge( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) ),
			)
		)
	);
}

/**
 * Creates fresh local technical evidence for a fixed known scope.
 *
 * @since 0.3.0
 * @param string $provider Stable local provider.
 * @param string $scope Native form/order-payment scope.
 * @param bool   $filled Whether to emulate a filled honeypot.
 * @return array<string,string> Own controls only.
 */
function rktsky_antispam_woo_proof( string $provider, string $scope, bool $filled = false ): array {
	$proof = ( new \Rktsky\Antispam\Token() )->issue( $provider, $scope );
	return array(
		'rktsky_antispam_token' => $proof['token'],
		$proof['trap']          => $filled ? 'synthetic-bot' : '',
	);
}

/**
 * Calls the original Store API through the native REST dispatcher.
 *
 * @since 0.3.0
 * @param string $method Native HTTP method.
 * @param string $route Exact registered WooCommerce route.
 * @param array  $data Synthetic request data.
 * @return WP_REST_Response Original dispatched response.
 */
function rktsky_antispam_woo_rest( string $method, string $route, array $data ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_header( 'Nonce', 'store_bad_nonce' === ( $GLOBALS['rktsky_case'] ?? '' ) ? 'invalid' : wp_create_nonce( 'wc_store_api' ) );
	$request->set_body( wp_json_encode( $data ) );
	return rest_do_request( $request );
}

/**
 * Encodes only technical evidence in the registered checkout namespace.
 *
 * @since 0.3.0
 * @param string $scope Known checkout/order-payment scope.
 * @param bool   $filled Whether the synthetic trap is filled.
 * @return array<string,array> Namespaced local proof.
 */
function rktsky_antispam_woo_extensions( string $scope, bool $filled = false ): array {
	$posted = rktsky_antispam_woo_proof( 'wc_checkout', $scope, $filled );
	$trap   = array_keys( $posted )[1];
	return array(
		'rktsky-antispam' => array(
			'token' => $posted['rktsky_antispam_token'],
			'trap'  => $trap,
			'value' => $posted[ $trap ],
		),
	);
}

wp_set_current_user( 0 );
$_SERVER['REQUEST_METHOD']      = 'POST';
$_SERVER['REMOTE_ADDR']         = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT']     = 'Synthetic local WooCommerce regression';
$_POST                          = array();
$_GET                           = array();
$_REQUEST                       = array();
$GLOBALS['rktsky_woo_payments'] = 0;
add_filter(
	'woocommerce_payment_gateways',
	static function ( array $gateways ): array {
		$gateways[] = 'Rktsky_Antispam_Fixture_Gateway';
		return $gateways;
	}
);
add_filter( 'woocommerce_registration_auth_new_customer', '__return_false' );
// Hard rejection content rules prove that checkout/registration use technical-only mode.
add_filter(
	'pre_option_rktsky_antispam_settings',
	static function () {
		$v                             = ( new \Rktsky\Antispam\Settings() )->defaults();
		$v['wc_checkout']              = true;
		$v['wc_registration']          = true;
		$v['spam_keywords']            = 'Synthetic';
		$v['allowed_languages']        = array( 'de' );
		$v['unknown_language_allowed'] = false;
		$v['privacy_mode']             = false;
		$v['country_mode']             = 'allow';
		$v['countries']                = array( 'CH' );
		$v['country_unknown_allowed']  = false;
		$v['quarantine_enabled']       = true;
		return $v;
	}
);
$before_orders     = rktsky_antispam_woo_orders();
$before_users      = get_users( array( 'fields' => 'ID' ) );
$before_tokens     = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}rktsky_antispam_tokens" );
$before_quarantine = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}rktsky_antispam_quarantine" );
$email             = 'synthetic-woo-' . strtolower( $rktsky_case ) . '-' . wp_generate_password( 8, false, false ) . '@example.invalid';

try {
	WC()->initialize_session();
	WC()->initialize_cart();
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $fixture['product'], 1 );
	WC()->cart->calculate_totals();
	WC()->session->set( 'order_awaiting_payment', null );
	WC()->session->set( 'store_api_draft_order', null );
	wc_clear_notices();
	$address = array(
		'first_name' => 'Synthetic',
		'last_name'  => 'Fixture',
		'company'    => '',
		'address_1'  => 'Synthetic 1',
		'address_2'  => '',
		'city'       => 'Bern',
		'state'      => '',
		'postcode'   => '3000',
		'country'    => 'CH',
		'email'      => $email,
		'phone'      => '0311234567',
	);
	if ( 'render' === $rktsky_case ) {
		ob_start();
		do_action( 'woocommerce_review_order_before_submit' );
		$html = ob_get_clean();
		rktsky_antispam_woo_expect( 1 === substr_count( $html, 'data-provider="wc_checkout"' ), 'Classic controls are not attached exactly once.' );
		$html = apply_filters( 'render_block_woocommerce/checkout', '<div>Native checkout</div>' );
		rktsky_antispam_woo_expect( str_contains( $html, 'data-rk-woocommerce-checkout="block"' ) && str_contains( $html, 'Native checkout' ), 'Block renderer did not preserve native checkout.' );
		ob_start();
		do_action( 'woocommerce_register_form' );
		$html = ob_get_clean();
		rktsky_antispam_woo_expect( str_contains( $html, 'data-provider="wc_registration"' ), 'Native registration controls missing.' );
	} elseif ( str_starts_with( $rktsky_case, 'classic_' ) ) {
		$_POST = array(
			'woocommerce-process-checkout-nonce' => wp_create_nonce( 'woocommerce-process_checkout' ),
			'payment_method'                     => 'rktsky_fixture',
			'createaccount'                      => '1',
			'terms'                              => '1',
		);
		foreach ( $address as $key => $value ) {
			$_POST[ 'billing_' . $key ] = $value; }
		if ( ! in_array( $rktsky_case, array( 'classic_missing', 'classic_bad_nonce', 'classic_update' ), true ) ) {
			$_POST += rktsky_antispam_woo_proof( 'wc_checkout', 'checkout', 'classic_honeypot' === $rktsky_case );
		}
		if ( 'classic_bad_nonce' === $rktsky_case ) {
			$_POST['woocommerce-process-checkout-nonce'] = 'invalid'; }
		if ( 'classic_update' === $rktsky_case ) {
			$_POST['woocommerce_checkout_update_totals'] = '1'; }
		if ( 'classic_retry' === $rktsky_case ) {
			$_POST['billing_first_name'] = ''; }
		$_REQUEST = $_POST;
		WC()->checkout()->process_checkout();
		if ( 'classic_retry' === $rktsky_case ) {
			rktsky_antispam_woo_expect( 0 === $GLOBALS['rktsky_woo_payments'] && ! email_exists( $email ), 'Native field rejection reached payment/account creation.' );
			wc_clear_notices();
			$_POST['billing_first_name'] = 'Synthetic';
			$_POST                       = array_replace( $_POST, rktsky_antispam_woo_proof( 'wc_checkout', 'checkout' ) );
			$_REQUEST                    = $_POST;
			WC()->checkout()->process_checkout();
		}
		if ( 'classic_repeat' === $rktsky_case ) {
			wc_clear_notices();
			$_REQUEST = $_POST;
			WC()->checkout()->process_checkout();
		}
		$accepted = in_array( $rktsky_case, array( 'classic_valid', 'classic_repeat', 'classic_retry' ), true );
		rktsky_antispam_woo_expect( ( $accepted ? 1 : 0 ) === $GLOBALS['rktsky_woo_payments'], 'Classic checkout crossed the payment boundary incorrectly. ' . wp_json_encode( wc_get_notices( 'error' ) ) );
		rktsky_antispam_woo_expect( $accepted === (bool) email_exists( $email ), 'Classic account creation did not respect the guard.' );
		if ( ! $accepted ) {
			rktsky_antispam_woo_expect( $before_orders === rktsky_antispam_woo_orders(), 'Rejected classic checkout created an order.' ); }
	} elseif ( str_starts_with( $rktsky_case, 'register_' ) ) {
		$_POST = array(
			'register'                   => '1',
			'email'                      => $email,
			'woocommerce-register-nonce' => wp_create_nonce( 'woocommerce-register' ),
			'password'                   => 'never-retain-this-synthetic-password',
		);
		if ( ! in_array( $rktsky_case, array( 'register_missing', 'register_bad_nonce' ), true ) ) {
			$_POST += rktsky_antispam_woo_proof( 'wc_registration', 'register', 'register_honeypot' === $rktsky_case ); }
		if ( 'register_bad_nonce' === $rktsky_case ) {
			$_POST['woocommerce-register-nonce'] = 'invalid'; }
		WC_Form_Handler::process_registration();
		$accepted = in_array( $rktsky_case, array( 'register_valid', 'register_repeat' ), true );
		rktsky_antispam_woo_expect( $accepted === (bool) email_exists( $email ), 'Native My Account registration ignored technical validation.' );
		if ( 'register_repeat' === $rktsky_case ) {
			$repeat_email   = 'repeat-' . $email;
			$_POST['email'] = $repeat_email;
			WC_Form_Handler::process_registration();
			rktsky_antispam_woo_expect( ! email_exists( $repeat_email ), 'A second native registration reused an already consumed token.' );
		}
	} elseif ( str_starts_with( $rktsky_case, 'pay_' ) || str_starts_with( $rktsky_case, 'store_pay_' ) ) {
		$order = wc_create_order();
		$order->add_product( wc_get_product( $fixture['product'] ), 1 );
		$order->set_address( $address, 'billing' );
		$order->calculate_totals();
		$order->save();
		$pay_before = wp_json_encode( wc_get_order( $order->get_id() )->get_data() );
		if ( str_starts_with( $rktsky_case, 'store_' ) ) {
			$data = array(
				'key'              => $order->get_order_key(),
				'billing_email'    => $email,
				'billing_address'  => $address,
				'shipping_address' => $address,
				'payment_method'   => 'rktsky_fixture',
			);
			if ( 'store_pay_missing' !== $rktsky_case ) {
				$data['extensions'] = rktsky_antispam_woo_extensions( 'pay_' . $order->get_id() ); }
			if ( 'store_pay_bad_key' === $rktsky_case ) {
				$data['key'] = 'invalid'; }
			$response = rktsky_antispam_woo_rest( 'POST', '/wc/store/v1/checkout/' . $order->get_id(), $data );
			$accepted = 'store_pay_valid' === $rktsky_case;
			if ( 'store_pay_missing' === $rktsky_case ) {
				rktsky_antispam_woo_expect( 'rktsky_antispam_rejected' === ( $response->get_data()['code'] ?? '' ), 'Native existing-order payment did not reach the local guard.' ); }
			rktsky_antispam_woo_expect( ( $accepted ? 1 : 0 ) === $GLOBALS['rktsky_woo_payments'], 'Store API order-payment crossed the payment boundary incorrectly. ' . wp_json_encode( $response->get_data() ) );
		} else {
			$GLOBALS['wp']->query_vars['order-pay'] = $order->get_id();
			$_GET['key']                            = $order->get_order_key();
			$_POST                                  = array(
				'woocommerce_pay'       => '1',
				'payment_method'        => 'rktsky_fixture',
				'woocommerce-pay-nonce' => wp_create_nonce( 'woocommerce-pay' ),
			);
			if ( ! in_array( $rktsky_case, array( 'pay_missing', 'pay_bad_nonce' ), true ) ) {
				$_POST += rktsky_antispam_woo_proof( 'wc_checkout', 'pay_' . $order->get_id(), 'pay_honeypot' === $rktsky_case ); }
			if ( 'pay_bad_nonce' === $rktsky_case ) {
				$_POST['woocommerce-pay-nonce'] = 'invalid'; }
			$_REQUEST = $_POST;
			add_filter(
				'wp_die_handler',
				static function () {
					return static function ( $message, $title, $args ) {
						throw new RuntimeException( 'fixture-stop-' . $args['response'] );
					};
				}
			);
			try {
				WC_Form_Handler::pay_action();
			} catch ( RuntimeException $error ) {
				rktsky_antispam_woo_expect( 'fixture-stop-403' === $error->getMessage(), 'Unexpected payment exception.' ); }
			$accepted = 'pay_valid' === $rktsky_case;
			rktsky_antispam_woo_expect( ( $accepted ? 1 : 0 ) === $GLOBALS['rktsky_woo_payments'], 'Native existing-order payment ignored local guard.' );
		}
		if ( ! $accepted ) {
			$unchanged = wc_get_order( $order->get_id() );
			rktsky_antispam_woo_expect( $pay_before === wp_json_encode( $unchanged->get_data() ), 'Rejected existing-order payment changed native order data. ' . wp_json_encode( array_diff_assoc( array_map( 'wp_json_encode', json_decode( $pay_before, true ) ), array_map( 'wp_json_encode', $unchanged->get_data() ) ) ) ); }
	} elseif ( str_starts_with( $rktsky_case, 'store_' ) ) {
		$data = array(
			'billing_address'  => $address,
			'shipping_address' => $address,
			'payment_method'   => 'rktsky_fixture',
			'create_account'   => true,
		);
		if ( ! in_array( $rktsky_case, array( 'store_missing', 'store_case_alias', 'store_versionless', 'store_wc_batch_missing', 'store_batch_missing', 'store_updates' ), true ) ) {
			$data['extensions'] = rktsky_antispam_woo_extensions( 'checkout', 'store_honeypot' === $rktsky_case ); }
		if ( 'store_updates' === $rktsky_case ) {
			foreach ( array( array( 'GET', '/wc/store/v1/checkout', array() ), array( 'PUT', '/wc/store/v1/checkout', $data ), array( 'POST', '/wc/store/v1/cart/update-customer', array( 'billing_address' => $address ) ) ) as $update ) {
				$response = rktsky_antispam_woo_rest( $update[0], $update[1], $update[2] );
				rktsky_antispam_woo_expect( $response->get_status() < 400, 'A native checkout/cart update was incorrectly blocked. ' . wp_json_encode( $response->get_data() ) );
			}
		} elseif ( in_array( $rktsky_case, array( 'store_batch_missing', 'store_wc_batch_missing' ), true ) ) {
			$response = rktsky_antispam_woo_rest(
				'POST',
				'store_wc_batch_missing' === $rktsky_case ? '/wc/store/v1/batch' : '/batch/v1',
				array(
					'requests' => array(
						array(
							'method'  => 'POST',
							'path'    => '/wc/store/v1/checkout',
							'headers' => array(
								'Content-Type' => 'application/json',
								'Nonce'        => wp_create_nonce( 'wc_store_api' ),
							),
							'body'    => $data,
						),
					),
				)
			);
			rktsky_antispam_woo_expect( 403 === ( $response->get_data()['responses'][0]['status'] ?? 0 ), 'A batched native REST checkout bypassed the guard.' );
		} else {
			if ( 'store_malformed' === $rktsky_case ) {
				$data['extensions']['rktsky-antispam']['token'] = array( 'wrong-shape' ); }
			if ( 'store_retry' === $rktsky_case ) {
				$data['billing_address']['first_name'] = ''; }
			if ( 'store_other_extensions' === $rktsky_case ) {
				$data['extensions']['rktsky-antispam']['unrelated'] = array( 'ignored' => 'synthetic' ); }
			$response = rktsky_antispam_woo_rest( 'POST', 'store_case_alias' === $rktsky_case ? '/WC/STORE/V1/CHECKOUT' : ( 'store_versionless' === $rktsky_case ? '/wc/store/checkout' : '/wc/store/v1/checkout' ), $data );
			if ( 'store_bad_nonce' === $rktsky_case ) {
				rktsky_antispam_woo_expect( 'woocommerce_rest_invalid_nonce' === ( $response->get_data()['code'] ?? '' ), 'A native Store API nonce error was bypassed or overwritten.' ); }
			if ( 'store_repeat' === $rktsky_case ) {
				rktsky_antispam_woo_expect( 1 === $GLOBALS['rktsky_woo_payments'], 'Initial Store API checkout did not reach the gateway. ' . wp_json_encode( $response->get_data() ) );
				$response = rktsky_antispam_woo_rest( 'POST', '/wc/store/v1/checkout', $data );
				rktsky_antispam_woo_expect( 403 === $response->get_status(), 'Repeated Store API evidence was accepted twice.' );
			}
			if ( 'store_retry' === $rktsky_case ) {
				rktsky_antispam_woo_expect( 0 === $GLOBALS['rktsky_woo_payments'], 'Native Store API field validation unexpectedly reached payment.' );
				$data['billing_address']['first_name'] = 'Synthetic';
				$data['extensions']                    = rktsky_antispam_woo_extensions( 'checkout' );
				$response                              = rktsky_antispam_woo_rest( 'POST', '/wc/store/v1/checkout', $data );
			}
			if ( in_array( $rktsky_case, array( 'store_missing', 'store_case_alias', 'store_versionless', 'store_honeypot' ), true ) ) {
				rktsky_antispam_woo_expect( 'rktsky_antispam_rejected' === ( $response->get_data()['code'] ?? '' ), 'Final checkout did not reach the local guard.' ); }
			$accepted = in_array( $rktsky_case, array( 'store_valid', 'store_repeat', 'store_retry', 'store_other_extensions' ), true );
			rktsky_antispam_woo_expect( ( $accepted ? 1 : 0 ) === $GLOBALS['rktsky_woo_payments'], 'Store API checkout crossed the payment boundary incorrectly. ' . wp_json_encode( $response->get_data() ) );
			rktsky_antispam_woo_expect( $accepted === (bool) email_exists( $email ), 'Store API account creation ignored the technical guard.' );
		}
		if ( 0 === $GLOBALS['rktsky_woo_payments'] ) {
			rktsky_antispam_woo_expect( $before_orders === rktsky_antispam_woo_orders(), 'Rejected/maintenance Store API request created an order.' ); }
	}
	rktsky_antispam_woo_expect( $before_quarantine === (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}rktsky_antispam_quarantine" ), 'WooCommerce copied registration/checkout data into quarantine.' );
	if ( in_array( $rktsky_case, array( 'classic_update', 'classic_bad_nonce', 'register_bad_nonce', 'pay_bad_nonce', 'store_updates', 'store_pay_bad_key' ), true ) ) {
		rktsky_antispam_woo_expect( $before_tokens === (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}rktsky_antispam_tokens" ), 'A non-final/unauthorized native request consumed a local proof.' );
	}
	echo 'PASS: ' . $rktsky_case . "\n";
} finally {
	foreach ( array_diff( rktsky_antispam_woo_orders(), $before_orders ) as $id ) {
		$order = wc_get_order( $id );
		if ( $order ) {
			$order->delete( true ); }
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array_diff( get_users( array( 'fields' => 'ID' ) ), $before_users ) as $id ) {
		wp_delete_user( (int) $id ); }
	WC()->cart->empty_cart();
	wc_clear_notices();
}
