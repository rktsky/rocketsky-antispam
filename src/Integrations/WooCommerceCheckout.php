<?php
/**
 * Stops rejected native WooCommerce checkouts before order, account and payment work.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Covers classic checkout, order payment and the public Checkout Store API.
 *
 * The adapter reads only its own technical controls. It never quarantines checkout
 * data or applies message, language or country policies to customer/payment fields.
 *
 * @since 0.3.0
 */
final class WooCommerceCheckout extends Adapter {
	/**
	 * Connects early validation boundaries and native frontend renderers.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_classic' ) );
		add_action( 'woocommerce_pay_order_before_submit', array( $this, 'render_pay' ) );
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'render_block' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_classic' ), 10, 2 );
		add_action( 'woocommerce_before_pay_action', array( $this, 'validate_pay' ) );
		add_filter( 'rest_dispatch_request', array( $this, 'validate_store_api' ), 10, 4 );
		add_action( 'rest_api_init', array( $this, 'register_schema' ), 5 );
	}

	/**
	 * Registers only our technical fields with WooCommerce's public schema extension.
	 *
	 * Unknown extension namespaces are removed by the native recursive sanitizer.
	 * Registration precedes native REST route creation, without exposing stored tokens
	 * or adding fields to orders, customer records or address/payment schemas.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register_schema(): void {
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => 'checkout',
					'namespace'       => 'rktsky-antispam',
					'schema_callback' => array( $this, 'schema' ),
					'data_callback'   => '__return_empty_array',
					'schema_type'     => ARRAY_A,
				)
			);
		}
	}

	/**
	 * Describes bounded proof fields accepted through native checkout extension data.
	 *
	 * Fields remain optional at the schema level so GET and PUT updates work normally;
	 * the early final-POST guard independently requires and verifies complete evidence.
	 *
	 * @since 0.3.0
	 * @return array<string,array> Public namespaced technical request schema.
	 */
	public function schema(): array {
		return array(
			'token' => array(
				'type'      => 'string',
				'maxLength' => 400,
				'readonly'  => false,
			),
			'trap'  => array(
				'type'      => 'string',
				'maxLength' => 32,
				'readonly'  => false,
			),
			'value' => array(
				'type'      => 'string',
				'maxLength' => 4096,
				'readonly'  => false,
			),
		);
	}

	/**
	 * Prints controls in the classic payment section, including AJAX replacements.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function render_classic(): void {
		echo $this->controls( 'checkout', 'classic' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controls() and Fields::markup() escape all attributes and markup.
	}

	/**
	 * Prints an order-specific scope for the native existing-order payment form.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function render_pay(): void {
		$id = absint( get_query_var( 'order-pay' ) );
		if ( $id > 0 ) {
			echo $this->controls( 'pay_' . $id, 'pay' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controls() and Fields::markup() escape all attributes and markup.
		}
	}

	/**
	 * Adds a cache-safe descriptor alongside the automatically rendered checkout.
	 *
	 * The outer Checkout block covers both its normal and express payment controls.
	 * No editor insertion, inline script or modification of a WooCommerce file is needed.
	 *
	 * @since 0.3.0
	 * @param string $content Native rendered Checkout block.
	 * @return string Original native markup followed by escaped local controls.
	 */
	public function render_block( string $content ): string {
		if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) {
			return $content;
		}
		return $content . $this->controls( 'checkout', 'block' );
	}

	/**
	 * Adds an error before classic checkout can create a customer or an order.
	 *
	 * Updating totals is not a final checkout and does not consume evidence.
	 * A second native checkout invocation must present fresh evidence; no request-wide
	 * acceptance cache can accidentally authorize a second order with the same token.
	 *
	 * @since 0.3.0
	 * @param array     $data Native validated checkout flags; customer content is ignored.
	 * @param \WP_Error $errors Native validation accumulator.
	 * @return void
	 */
	public function validate_classic( array $data, \WP_Error $errors ): void {
		if ( ! empty( $data['woocommerce_checkout_update_totals'] ) ) {
			return;
		}
		if ( '' !== $this->guard->check( 'wc_checkout', 'checkout', $this->protection_controls(), array(), true ) ) {
			$errors->add( 'rktsky_antispam_rejected', $this->guard->message() );
		}
	}

	/**
	 * Stops existing-order payment before customer/order mutation or gateway execution.
	 *
	 * WooCommerce invokes this boundary only after checking its payment nonce, order
	 * key and payable status. A neutral 403 ends processing because this action has
	 * no native error-return contract before those side effects.
	 *
	 * @since 0.3.0
	 * @param \WC_Order $order Native authorized payable order.
	 * @return void
	 */
	public function validate_pay( \WC_Order $order ): void {
		if ( '' !== $this->guard->check( 'wc_checkout', 'pay_' . $order->get_id(), $this->protection_controls(), array(), true ) ) {
			wp_die(
				esc_html( $this->guard->message() ),
				esc_html__( 'Payment not submitted', 'rocketsky-antispam' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
	}

	/**
	 * Rejects final Store API calls before their endpoint callback can have side effects.
	 *
	 * The WordPress dispatch filter also covers internal and batch REST dispatches,
	 * unlike authentication hooks that inspect only the outer HTTP route. Existing
	 * dispatcher results and native permission errors are never replaced. Native
	 * nonce/cart-token and order-key checks remain WooCommerce's responsibility.
	 *
	 * @since 0.3.0
	 * @param mixed            $result Earlier short-circuit result, normally null.
	 * @param \WP_REST_Request $request Native parsed and validated REST request.
	 * @param string           $route Matched native route expression.
	 * @param array            $handler Native route handler, intentionally not changed.
	 * @return mixed Existing result, null to proceed, or a neutral WP_Error rejection.
	 */
	public function validate_store_api( $result, \WP_REST_Request $request, string $route, array $handler ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The public WordPress filter supplies route/handler metadata; only the resolved request route is needed.
		if ( null !== $result || 'POST' !== $request->get_method() || 1 !== preg_match( '#^/wc/store(?:/v[1-9][0-9]*)?/checkout(?:/([0-9]+))?/?$#Di', $request->get_route(), $match ) ) {
			return $result;
		}
		$form_id    = isset( $match[1] ) && '' !== $match[1] ? 'pay_' . absint( $match[1] ) : 'checkout';
		$extensions = $request->get_param( 'extensions' );
		$extension  = is_array( $extensions ) && isset( $extensions['rktsky-antispam'] ) && is_array( $extensions['rktsky-antispam'] ) ? $extensions['rktsky-antispam'] : array();
		$posted     = array();
		if ( isset( $extension['token'] ) && is_string( $extension['token'] ) && strlen( $extension['token'] ) <= 400 ) {
			$posted['rktsky_antispam_token'] = $extension['token'];
		}
		if ( isset( $extension['trap'], $extension['value'] ) && is_string( $extension['trap'] ) && 1 === preg_match( '/^rktsky_as_[a-f0-9]{20}$/D', $extension['trap'] ) && is_string( $extension['value'] ) ) {
			// A nonempty or oversized value can only reject; it is never persisted.
			$posted[ $extension['trap'] ] = '' === $extension['value'] ? '' : 'filled';
		}
		if ( '' !== $this->guard->check( 'wc_checkout', $form_id, $posted, array(), true ) ) {
			return new \WP_Error( 'rktsky_antispam_rejected', $this->guard->message(), array( 'status' => 403 ) );
		}
		return $result;
	}

	/**
	 * Builds escaped controls and loads only the required native transport dependencies.
	 *
	 * @since 0.3.0
	 * @param string $form_id Fixed checkout scope or authorized order-payment scope.
	 * @param string $transport Known native frontend transport.
	 * @return string Escaped wrapper and protocol controls, or empty when disabled.
	 */
	private function controls( string $form_id, string $transport ): string {
		$markup = $this->fields->markup( 'wc_checkout', $form_id );
		if ( '' === $markup ) {
			return '';
		}
		$dependencies = array( 'jquery', 'rktsky-antispam' );
		if ( 'block' === $transport ) {
			$dependencies = array_merge( $dependencies, array( 'wp-api-fetch', 'wp-data', 'wc-blocks-data-store' ) );
		}
		wp_enqueue_script( 'rktsky-antispam-woocommerce', plugins_url( 'assets/woocommerce-checkout.js', RKTSKY_ANTISPAM_FILE ), $dependencies, RKTSKY_ANTISPAM_VERSION, true );
		return '<div data-rk-woocommerce-checkout="' . esc_attr( $transport ) . '">' . $markup . '</div>';
	}
}
