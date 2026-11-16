<?php
/**
 * Verifies the actual registered control renderers against native WordPress KSES.
 *
 * Uses original WordPress DOM, nonce-independent signed intents and hook registries.
 * Provider engines are excluded from this process through native activation-list
 * filters. Callback arguments and CF7 current-form lookup are explicit fixtures;
 * this is an owned output contract check. It makes no claim of native commercial
 * provider submission acceptance.
 * Native provider engines remain covered by their separate integration suites.
 * A source-derived inventory fails if a new terminal renderer lacks a probe.
 *
 * @package RktskyAntispamTests
 * @since 0.5.6
 */

use Rktsky\Antispam\{ContentRules, Country, Fields, Guard, Language, Providers, Settings, Store, Token};

// Native plugin filters isolate these output contracts from provider boot effects.
// Provider engines have their own suites; no active-plugin option is persisted.
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'RKTSKY_ANTISPAM_ALLOW_TESTS' ) ) {
	throw new RuntimeException( 'Explicit CLI test authorization is required.' );
}
$rktsky_output_root = realpath( (string) getenv( 'RKTSKY_ANTISPAM_WP_ROOT' ) );
if ( ! $rktsky_output_root || ! is_file( $rktsky_output_root . '/.rktsky-antispam-fixture' ) || 'rktsky-antispam-disposable' !== trim( file_get_contents( $rktsky_output_root . '/.rktsky-antispam-fixture' ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads only the explicit disposable test marker before Core boot.
	throw new RuntimeException( 'A separately marked native WordPress fixture is required.' );
}
require_once $rktsky_output_root . '/wp-includes/plugin.php';
add_filter( 'option_active_plugins', static fn( array $plugins ): array => array_values( array_filter( $plugins, static fn( string $file ): bool => str_ends_with( $file, '/rocketsky-antispam.php' ) ) ) );
add_filter( 'site_option_active_sitewide_plugins', static fn( array $plugins ): array => array_filter( $plugins, static fn( string $file ): bool => str_ends_with( $file, '/rocketsky-antispam.php' ), ARRAY_FILTER_USE_KEY ) );
require __DIR__ . '/bootstrap.php';

/**
 * Explicit provider-absent CF7 lookup fixture; no native CF7 engine is claimed.
 *
 * @since 0.5.6
 */
$rktsky_output_cf7_context = new class() {
	/**
	 * Supplies one server-selected synthetic form object.
	 *
	 * @since 0.5.6
	 * @return object Fixture form with a fixed identifier.
	 */
	public static function get_current(): object {
		return new class() {
			/**
			 * Supplies a fixed fixture scope.
			 *
			 * @since 0.5.6
			 * @return int Synthetic form ID.
			 */
			public function id(): int {
				return 41;
			}
		};
	}
};
if ( class_exists( 'WPCF7_ContactForm', false ) ) {
	throw new RuntimeException( 'The output-contract process must not load provider engines.' );
}
class_alias( get_class( $rktsky_output_cf7_context ), 'WPCF7_ContactForm' );
$rktsky_output_assertions = 0;

/**
 * Reports a concrete output-contract regression without dumping proof tokens.
 *
 * @since 0.5.6
 * @param bool   $condition Required rendered behavior.
 * @param string $message Fixed failure context.
 * @return void
 * @throws RuntimeException When an output invariant fails.
 */
function rktsky_output_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_output_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

/**
 * Finds markup producers and every output handoff in the real provider registry.
 *
 * @since 0.5.6
 * @return string[] Class/method identities of markup producers and their output boundaries.
 */
function rktsky_output_inventory(): array {
	$found = array();
	foreach ( Providers::ADAPTERS as $class ) {
		$tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/src/Integrations/' . $class . '.php' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads owned source to cover markup producers, appenders and terminal output.
		$method = '';
		foreach ( $tokens as $index => $token ) {
			if ( is_array( $token ) && T_FUNCTION === $token[0] ) {
				for ( $next = $index + 1; isset( $tokens[ $next ] ); ++$next ) {
					if ( is_array( $tokens[ $next ] ) && T_STRING === $tokens[ $next ][0] ) {
						$method = $tokens[ $next ][1];
						break;
					}
					if ( '(' === $tokens[ $next ] ) {
						break;
					}
				}
			}
			if ( 'Divi' === $class && is_array( $token ) && T_STRING === $token[0] && 'wp_die' === strtolower( $token[1] ) ) {
				$found[ $class . '::' . $method ] = true;
			}
			if ( ! is_array( $token ) || T_VARIABLE !== $token[0] || '$this' !== $token[1] ) {
				continue;
			}
			$expression = '';
			for ( $next = $index; isset( $tokens[ $next ] ); ++$next ) {
				$part = $tokens[ $next ];
				if ( is_array( $part ) && in_array( $part[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
					continue;
				}
				$expression .= is_array( $part ) ? $part[1] : $part;
				if ( '(' === $part || strlen( $expression ) > 40 ) {
					break;
				}
			}
			if ( in_array( $expression, array( '$this->fields->markup(', '$this->controls(' ), true ) ) {
				$found[ $class . '::' . $method ] = true;
			}
		}
	}
	$identities = array_keys( $found );
	sort( $identities );
	return $identities;
}

/**
 * Parses a fragment in its actual owning form and normalizes attribute spelling.
 *
 * @since 0.5.6
 * @param string $html Own control fragment.
 * @return array{document:DOMDocument,xpath:DOMXPath,contract:array} Parsed contract.
 */
function rktsky_output_dom( string $html ): array {
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$document->loadHTML( '<!doctype html><html><head><meta charset="UTF-8"></head><body><form id="rk-output-owner">' . $html . '</form></body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$xpath    = new DOMXPath( $document );
	$contract = array();
	foreach ( $xpath->query( '//*[@id="rk-output-owner"]//*' ) as $element ) {
		$attributes = array();
		foreach ( $element->attributes as $attribute ) {
			$attributes[ $attribute->name ] = $attribute->value;
		}
		ksort( $attributes );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOMElement properties have names fixed by PHP.
		$contract[] = array( strtolower( $element->tagName ), $attributes, trim( $element->textContent ) );
	}
	return array(
		'document' => $document,
		'xpath'    => $xpath,
		'contract' => $contract,
	);
}

/**
 * Requires complete signed controls and a usable no-JavaScript fallback.
 *
 * @since 0.5.6
 * @param string $html Actual terminal output after native KSES.
 * @param string $provider Fixed expected provider.
 * @param string $form_id Expected native form scope.
 * @param Token  $token Native HMAC intent verifier.
 * @return void
 */
function rktsky_output_controls( string $html, string $provider, string $form_id, Token $token ): void {
	$parsed = rktsky_output_dom( $html );
	$xpath  = $parsed['xpath'];
	$nodes  = $xpath->query( '//form[@id="rk-output-owner"]//span[@data-rk-antispam]' );
	rktsky_output_expect( 1 === $nodes->length, 'Each native renderer must retain exactly one inside-form descriptor.' );
	$control = $nodes->item( 0 );
	rktsky_output_expect( $control->hasAttribute( 'hidden' ) && $provider === $control->getAttribute( 'data-provider' ) && $form_id === $control->getAttribute( 'data-form' ), 'Hidden state and exact provider/form scope must survive the terminal filter.' );
	rktsky_output_expect( $token->valid_intent( $control->getAttribute( 'data-intent' ), $provider, $form_id ) && rest_url( 'rktsky-antispam/v1/challenge' ) === $control->getAttribute( 'data-endpoint' ), 'The browser must receive the unchanged native signed intent and challenge URL.' );
	$inputs = $xpath->query( '//span[@data-rk-antispam]/input' );
	rktsky_output_expect( 1 === $inputs->length && 'hidden' === $inputs->item( 0 )->getAttribute( 'type' ) && 'rktsky_antispam_token' === $inputs->item( 0 )->getAttribute( 'name' ) && '' === $inputs->item( 0 )->getAttribute( 'value' ) && 'off' === $inputs->item( 0 )->getAttribute( 'autocomplete' ) && ! $inputs->item( 0 )->hasAttribute( 'disabled' ), 'The initial token control must remain an enabled successful form control.' );
	rktsky_output_expect( 1 === $xpath->query( '//noscript/p' )->length && __( 'Enable JavaScript to submit this form.', 'rocketsky-antispam' ) === trim( $xpath->evaluate( 'string(//noscript/p)' ) ), 'The no-JavaScript instruction must survive without becoming an executable tag.' );
	rktsky_output_expect( wp_script_is( 'rktsky-antispam', 'enqueued' ), 'Actual rendering must enqueue the real shared protection script.' );
}

$rktsky_output_original = get_option( Settings::OPTION, null );
$rktsky_output_user     = get_current_user_id();
$rktsky_output_hooks    = array();
foreach ( $GLOBALS['wp_filter'] as $rktsky_output_hook => $rktsky_output_object ) {
	$rktsky_output_hooks[ $rktsky_output_hook ] = clone $rktsky_output_object;
}
$rktsky_output_options = ( new Settings() )->all();
foreach ( Providers::ids() as $rktsky_output_provider ) {
	$rktsky_output_options[ $rktsky_output_provider ] = true;
}
$rktsky_output_option_filter = static function () use ( &$rktsky_output_options ): array {
	return $rktsky_output_options;
};
add_filter( 'pre_option_' . Settings::OPTION, $rktsky_output_option_filter );
$rktsky_output_pay = get_query_var( 'order-pay', null );
// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Snapshots the CLI fixture superglobal for exact restoration; no request data is processed.
$rktsky_output_post = $_POST;
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Preserves the original CLI fixture value without converting its type before restoration.
$rktsky_output_ajax  = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null;
$rktsky_output_posts = array();
wp_set_current_user( 0 );
try {
	$rktsky_output_settings = new Settings();
	$rktsky_output_token    = new Token();
	$rktsky_output_fields   = new Fields( $rktsky_output_settings, $rktsky_output_token );
	$rktsky_output_guard    = new Guard( $rktsky_output_settings, $rktsky_output_token, new Store(), new ContentRules( $rktsky_output_settings, new Language(), new Country( $rktsky_output_settings ) ) );
	$rktsky_output_adapters = array();
	foreach ( Providers::ADAPTERS as $rktsky_output_class ) {
		require_once dirname( __DIR__ ) . '/src/Integrations/' . $rktsky_output_class . '.php';
		$rktsky_output_fqcn                             = 'Rktsky\\Antispam\\Integrations\\' . $rktsky_output_class;
		$rktsky_output_adapters[ $rktsky_output_class ] = new $rktsky_output_fqcn( $rktsky_output_settings, $rktsky_output_guard, $rktsky_output_fields );
		$rktsky_output_adapters[ $rktsky_output_class ]->register();
	}
	$rktsky_output_cases    = array(
		'Comments::comment_fields'            => array( 'comment_form', array( 41 ), 'comments', '41', '' ),
		'Registration::fields'                => array( 'register_form', array(), 'registration', 'register', '' ),
		'Registration::signup_fields'         => array( 'signup_hidden_fields', array( 'validate-user' ), 'registration', 'signup', '' ),
		'WooCommerceRegistration::render'     => array( 'woocommerce_register_form', array(), 'wc_registration', 'register', '' ),
		'WooCommerceCheckout::render_classic' => array( 'woocommerce_review_order_before_submit', array(), 'wc_checkout', 'checkout', 'classic' ),
		'WooCommerceCheckout::render_pay'     => array( 'woocommerce_pay_order_before_submit', array(), 'wc_checkout', 'pay_42', 'pay' ),
		'Formidable::render'                  => array( 'frm_entry_form', array( (object) array( 'id' => 41 ) ), 'formidable', '41', '' ),
		'WpForms::wpforms_fields'             => array( 'wpforms_frontend_output', array( array( 'id' => 41 ) ), 'wpforms', '41', '' ),
		'Fluent::render'                      => array( 'fluentform/form_element_start', array( (object) array( 'id' => 41 ) ), 'fluentforms', '41', '' ),
	);
	$rktsky_output_expected = array_merge( array_keys( $rktsky_output_cases ), array( 'ContactForm7::cf7_fields', 'GravityForms::gravity_fields', 'Elementor::render', 'WooCommerceCheckout::render_block', 'WooCommerceCheckout::controls', 'Divi::reject' ) );
	sort( $rktsky_output_expected );
	rktsky_output_expect( rktsky_output_inventory() === $rktsky_output_expected, 'Every markup producer, return/append handoff, output terminal and private Woo producer in the registry must have a rendered probe.' );
	foreach ( $rktsky_output_cases as $rktsky_output_identity => $rktsky_output_case ) {
		list( $rktsky_output_class, $rktsky_output_method ) = explode( '::', $rktsky_output_identity );
		$rktsky_output_adapter                              = $rktsky_output_adapters[ $rktsky_output_class ];
		list( $rktsky_output_hook, $rktsky_output_arguments, $rktsky_output_provider, $rktsky_output_form, $rktsky_output_transport ) = $rktsky_output_case;
		$rktsky_output_callback = array( $rktsky_output_adapter, $rktsky_output_method );
		rktsky_output_expect( false !== has_filter( $rktsky_output_hook, $rktsky_output_callback ), 'The probe must use the callback actually registered on the native provider hook: ' . $rktsky_output_identity );
		set_query_var( 'order-pay', 'pay' === $rktsky_output_transport ? 42 : null );
		ob_start();
		call_user_func_array( $rktsky_output_callback, $rktsky_output_arguments );
		$rktsky_output_html = (string) ob_get_clean();
		$rktsky_output_raw  = $rktsky_output_fields->markup( $rktsky_output_provider, $rktsky_output_form );
		if ( '' !== $rktsky_output_transport ) {
			$rktsky_output_raw = '<div data-rk-woocommerce-checkout="' . esc_attr( $rktsky_output_transport ) . '">' . $rktsky_output_raw . '</div>';
		}
		$rktsky_output_expected_dom = rktsky_output_dom( $rktsky_output_raw );
		$rktsky_output_actual_dom   = rktsky_output_dom( $rktsky_output_html );
		rktsky_output_expect( $rktsky_output_expected_dom['contract'] === $rktsky_output_actual_dom['contract'], 'Native terminal filtering must preserve the complete raw renderer DOM: ' . $rktsky_output_identity );
		rktsky_output_controls( $rktsky_output_html, $rktsky_output_provider, $rktsky_output_form, $rktsky_output_token );
		$rktsky_output_options[ $rktsky_output_provider ] = false;
		ob_start();
		call_user_func_array( $rktsky_output_callback, $rktsky_output_arguments );
		$rktsky_output_disabled = (string) ob_get_clean();
		rktsky_output_expect( '' === $rktsky_output_disabled, 'A disabled integration must not gain controls from the new output policy.' );
		$rktsky_output_options[ $rktsky_output_provider ] = true;
	}
	set_query_var( 'order-pay', null );
	$rktsky_output_foreign = '<section class="wc-block-checkout" data-wc-interactive="woocommerce/checkout"><input type="hidden" name="payment_method" value="native-payment"><input type="hidden" name="woocommerce-process-checkout-nonce" value="native-nonce"><script type="application/json">{"native":"unchanged"}</script><button type="submit">Native checkout</button></section>';
	$rktsky_output_block   = $rktsky_output_adapters['WooCommerceCheckout']->render_block( $rktsky_output_foreign );
	rktsky_output_expect( str_starts_with( $rktsky_output_block, $rktsky_output_foreign ), 'WooCommerce-owned block markup and payment/nonce controls must remain byte-identical.' );
	$rktsky_output_suffix = substr( $rktsky_output_block, strlen( $rktsky_output_foreign ) );
	rktsky_output_expect( 'block' === rktsky_output_dom( $rktsky_output_suffix )['xpath']->evaluate( 'string(//div/@data-rk-woocommerce-checkout)' ), 'The appended block bridge transport marker must survive.' );
	rktsky_output_controls( $rktsky_output_suffix, 'wc_checkout', 'checkout', $rktsky_output_token );
	// CF7/GF/Elementor pass native-owned HTML around the shared fields fragment.
	// These inputs deliberately contain tags/attributes excluded by our policy.
	$rktsky_output_provider_html = '<section data-native="keep" class="provider"><input type="hidden" name="payment_nonce" value="native&amp;&quot;nonce"><script type="application/json">{"unchanged":true}</script></section>';
	$rktsky_output_handoffs      = array(
		'ContactForm7::cf7_fields'     => array( 'ContactForm7', 'cf7_fields', 'wpcf7_form_elements', array( $rktsky_output_provider_html ), 'cf7', '41', $rktsky_output_provider_html ),
		'GravityForms::gravity_fields' => array( 'GravityForms', 'gravity_fields', 'gform_form_tag', array( '<form id="native-gf" data-native="a&amp;b" method="post">', array( 'id' => 41 ) ), 'gravityforms', '41', '<form id="native-gf" data-native="a&amp;b" method="post">' ),
		'Elementor::render'            => array(
			'Elementor',
			'render',
			'elementor/widget/render_content',
			array(
				'<div data-native="unchanged"><form class="elementor-form">' . $rktsky_output_provider_html . '</form></div>',
				new class() {
						/**
						 * Supplies the declared fixture widget type.
						 *
						 * @since 0.5.6
						 * @return string Fixed native widget name.
						 */
					public function get_name(): string {
						return 'form'; }
						/**
						 * Supplies the synthetic provider-selected scope.
						 *
						 * @since 0.5.6
						 * @return string Fixed widget ID.
						 */
					public function get_id(): string {
						return 'output_widget'; }
				},
			),
			'elementor',
			'output_widget',
			'<div data-native="unchanged"><form class="elementor-form">' . $rktsky_output_provider_html . '</form></div>',
		),
	);
	foreach ( $rktsky_output_handoffs as $rktsky_output_identity => $rktsky_output_case ) {
		list( $rktsky_output_class, $rktsky_output_method, $rktsky_output_hook, $rktsky_output_arguments, $rktsky_output_provider, $rktsky_output_form, $rktsky_output_foreign ) = $rktsky_output_case;
		$rktsky_output_callback = array( $rktsky_output_adapters[ $rktsky_output_class ], $rktsky_output_method );
		rktsky_output_expect( false !== has_filter( $rktsky_output_hook, $rktsky_output_callback ), 'Each return handoff must be registered at its documented provider hook.' );
		$rktsky_output_html      = call_user_func_array( $rktsky_output_callback, $rktsky_output_arguments );
		$rktsky_output_fragment  = wp_kses( $rktsky_output_fields->markup( $rktsky_output_provider, $rktsky_output_form ), Fields::allowed_html() );
		$rktsky_output_unwrapped = str_replace( $rktsky_output_fragment, '', $rktsky_output_html );
		rktsky_output_expect( 1 === substr_count( $rktsky_output_html, $rktsky_output_fragment ) && $rktsky_output_foreign === $rktsky_output_unwrapped, 'Only the owned fragment may be filtered; provider HTML must remain byte-identical: ' . $rktsky_output_identity );
		rktsky_output_controls( $rktsky_output_fragment, $rktsky_output_provider, $rktsky_output_form, $rktsky_output_token );
		$rktsky_output_options[ $rktsky_output_provider ] = false;
		$rktsky_output_disabled                           = call_user_func_array( $rktsky_output_callback, $rktsky_output_arguments );
		rktsky_output_expect( $rktsky_output_foreign === $rktsky_output_disabled, 'Disabled return adapters must retain native HTML byte-for-byte.' );
		$rktsky_output_options[ $rktsky_output_provider ] = true;
	}
	$rktsky_output_policy = Fields::allowed_html();
	$rktsky_output_unsafe = '<span hidden data-rk-antispam data-provider="comments" onclick="attack()" style="display:block" data-unrelated="no"><input type="hidden" name="rktsky_antispam_token" value="a&amp;&quot;b" autocomplete="off" disabled onfocus="attack()"><img src="https://example.invalid/pixel"><script>attack()</script><noscript><p onclick="attack()">Read &amp; retry</p></noscript></span>';
	$rktsky_output_clean  = rktsky_output_dom( wp_kses( $rktsky_output_unsafe, $rktsky_output_policy ) );
	rktsky_output_expect( 0 === $rktsky_output_clean['xpath']->query( '//script|//img|//*[@onclick or @onfocus or @style or @disabled or @data-unrelated]' )->length && 'a&"b' === $rktsky_output_clean['xpath']->evaluate( 'string(//input/@value)' ), 'The narrow native policy must remove hostile tags/attributes without corrupting an encoded control value.' );

	// The Divi provider itself is not loaded. Exercise its changed owned terminal
	// error fragment through real wp_die(), separately from native Divi acceptance.
	$rktsky_output_reject = new ReflectionMethod( $rktsky_output_adapters['Divi'], 'reject' );
	foreach ( array( 'publish', 'private' ) as $rktsky_output_visibility ) {
		$rktsky_output_post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => $rktsky_output_visibility,
				'post_title'   => 'Synthetic output-policy page',
				'post_name'    => 'synthetic-private-output-boundary',
				'post_content' => 'Disposable own output test.',
			),
			true
		);
		rktsky_output_expect( is_int( $rktsky_output_post_id ) && $rktsky_output_post_id > 0, 'A native disposable page must exist for public/private reload metadata.' );
		$rktsky_output_posts[] = $rktsky_output_post_id;
		foreach ( array( false, true ) as $rktsky_output_is_ajax ) {
			$_POST = wp_slash(
				array(
					'rktsky_antispam_target' => 'native-divi-container',
					'rktsky_antispam_page'   => (string) $rktsky_output_post_id,
					'et_pb_contact_message'  => 'Never echo this synthetic field.',
				)
			);
			if ( $rktsky_output_is_ajax ) {
				$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
			} else {
				unset( $_SERVER['HTTP_X_REQUESTED_WITH'] );
			}
			$rktsky_output_observed = array();
			/**
			 * Observes the native termination payload without replacing its renderer.
			 *
			 * @since 0.5.6
			 * @return Closure Native wp_die handler observing the actual passed HTML.
			 */
			$rktsky_output_die = static function () use ( &$rktsky_output_observed ): Closure {
				/**
				 * Captures only the fixed renderer payload and original response metadata.
				 *
				 * @since 0.5.6
				 * @param mixed $message Native renderer HTML.
				 * @param mixed $title Native error title.
				 * @param mixed $arguments Native response settings.
				 * @return never
				 * @throws RuntimeException Always, stopping only the current renderer.
				 */
				return static function ( $message, $title = '', $arguments = array() ) use ( &$rktsky_output_observed ): never {
					$rktsky_output_observed = array(
						'html'   => $message,
						'status' => $arguments['response'] ?? 0,
					);
					throw new RuntimeException( 'rktsky_output_native_stop' );
				};
			};
			add_filter( 'wp_die_handler', $rktsky_output_die, PHP_INT_MAX );
			try {
				$rktsky_output_reject->invoke( $rktsky_output_adapters['Divi'], '42' );
			} catch ( RuntimeException $rktsky_output_exception ) {
				if ( 'rktsky_output_native_stop' !== $rktsky_output_exception->getMessage() ) {
					throw $rktsky_output_exception;
				}
			} finally {
				remove_filter( 'wp_die_handler', $rktsky_output_die, PHP_INT_MAX );
			}
			$rktsky_output_error = rktsky_output_dom( $rktsky_output_observed['html'] );
			rktsky_output_expect( ( $rktsky_output_is_ajax ? 200 : 403 ) === $rktsky_output_observed['status'] && 1 === $rktsky_output_error['xpath']->query( '//div[@id="native-divi-container"]/div[@class="et-pb-contact-message"]//p[@class="et_pb_contact_error_text" and @role="alert"]' )->length, 'Native Divi retry marker, actual wrapper and AJAX/full-page response status must survive the new terminal filter.' );
			rktsky_output_expect( ( 'publish' === $rktsky_output_visibility ? get_permalink( $rktsky_output_post_id ) : home_url( '/' ) ) === $rktsky_output_error['xpath']->evaluate( 'string(//a/@href)' ) && ! str_contains( $rktsky_output_observed['html'], 'Never echo' ), 'The reload link must remain public-only and must never expose submitted content.' );
		}
	}
	rktsky_output_expect( '' === $rktsky_output_fields->markup( 'comments', 'bad"><script>' ) && '' === $rktsky_output_fields->markup( 'unknown', '41' ), 'Invalid scope values must fail before any HTML is rendered.' );
} finally {
	remove_filter( 'pre_option_' . Settings::OPTION, $rktsky_output_option_filter );
	set_query_var( 'order-pay', $rktsky_output_pay );
	$_POST = $rktsky_output_post;
	if ( null === $rktsky_output_ajax ) {
		unset( $_SERVER['HTTP_X_REQUESTED_WITH'] );
	} else {
		$_SERVER['HTTP_X_REQUESTED_WITH'] = $rktsky_output_ajax; }
	foreach ( $rktsky_output_posts as $rktsky_output_post_id ) {
		wp_delete_post( $rktsky_output_post_id, true ); }
	wp_set_current_user( $rktsky_output_user );
	$GLOBALS['wp_filter'] = $rktsky_output_hooks; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the original native hook registry after isolated registration probes.
}
rktsky_output_expect( get_option( Settings::OPTION, null ) === $rktsky_output_original, 'All output probes must preserve actual protection settings exactly.' );
echo 'PASS: ' . esc_html( (string) $rktsky_output_assertions ) . " native output-policy, registered-consumer and DOM-preservation assertions.\n";
