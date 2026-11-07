<?php
/**
 * Verifies Divi adapter contracts without loading or imitating its commercial engine.
 *
 * Provider transport shapes were read from original public D4/D5 demo assets.
 * Dependencies and wp_die are explicit stubs; WordPress's unchanged HTML API
 * verifies host mutation/escaping. This is not a native Divi server test.
 *
 * @package RktskyAntispamTests
 * @since 0.3.0
 */

namespace Rktsky\Antispam {
	/** Supplies an explicit integration toggle for contract tests. @since 0.3.0 */
	final class Settings {
		/** @var bool Synthetic integration enablement. @since 0.3.0 */
		public bool $active = true;
		/**
		 * Returns only the synthetic enablement decision.
		 *
		 * @since 0.3.0
		 * @param string $provider Provider ID.
		 * @return bool Fixture toggle.
		 */
		public function enabled( string $provider ): bool {
			return $this->active && 'divi' === $provider; }
	}
	/** Records exact guard boundaries without claiming cryptographic verification. @since 0.3.0 */
	final class Guard {
		/** @var string Synthetic local verdict. @since 0.3.0 */
		public string $reason = '';
		/** @var array Guard calls with technical-only flag. @since 0.3.0 */
		public array $checks = array();
		/** @var array Explicit malformed-envelope rejections. @since 0.3.0 */
		public array $rejected = array();
		/**
		 * Records one technical gate call.
		 *
		 * @since 0.3.0
		 * @param string $provider Provider ID.
		 * @param string $form Scope.
		 * @param array  $posted Unslashed transport.
		 * @param array  $fields Selected content.
		 * @param bool   $technical Whether content policies are excluded.
		 * @return string Synthetic verdict.
		 */
		public function check( string $provider, string $form, array $posted, array $fields = array(), bool $technical = false ): string {
			$this->checks[] = array( $provider, $form, $posted, $fields, $technical );
			return $this->reason; }
		/**
		 * Records an explicit fixed policy rejection.
		 *
		 * @since 0.3.0
		 * @param string $provider Provider ID.
		 * @param string $reason Fixed rejection code.
		 * @return void
		 */
		public function reject( string $provider, string $reason ): void {
			$this->rejected[] = array( $provider, $reason ); }
		/**
		 * Supplies metacharacters to test contextual error escaping.
		 *
		 * @since 0.3.0
		 * @return string Synthetic neutral error.
		 */
		public function message(): string {
			return 'Please retry <safely>.'; }
	}
	/** Supplies descriptors whose escaping is delegated to WordPress HTML API. @since 0.3.0 */
	final class Fields {
		/**
		 * Returns synthetic descriptors without enqueuing real assets.
		 *
		 * @since 0.3.0
		 * @param string $provider Provider ID.
		 * @param string $form Scope.
		 * @return array<string,string> Fixture host attributes.
		 */
		public function attributes( string $provider, string $form ): array {
			return array(
				'data-rk-antispam-provider' => $provider,
				'data-rk-antispam-form'     => $form,
				'data-rk-antispam-intent'   => 'proof"<&',
				'data-rk-antispam-endpoint' => '/local?a=1&b=2',
			); }
	}
	/** Explicit unused dependency, ensuring no quarantine methods are invoked. @since 0.3.0 */
	final class Quarantine {}
	/** Explicit unused metadata dependency. @since 0.3.0 */
	final class Feedback {}
}

namespace {
	if ( PHP_SAPI !== 'cli' ) {
		exit( 1 ); }
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['rktsky_divi_hooks']      = array();
	$GLOBALS['rktsky_divi_assertions'] = 0;
	$GLOBALS['rktsky_divi_admin']      = false;
	$GLOBALS['rktsky_divi_ajax']       = false;
	$GLOBALS['rktsky_divi_die']        = array();
	/**
	 * Records native callback registration without booting WordPress.
	 *
	 * @since 0.3.0
	 * @param string $hook Hook name.
	 * @param mixed  $callback Native callback.
	 * @param int    $priority Callback priority.
	 * @param int    $accepted Accepted argument count.
	 * @return void
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted = 1 ): void {
		$GLOBALS['rktsky_divi_hooks'][ $hook ] = array( $callback, $priority, $accepted ); }
	/**
	 * Records a native output filter contract.
	 *
	 * @since 0.3.0
	 * @param string $hook Hook name.
	 * @param mixed  $callback Native callback.
	 * @param int    $priority Callback priority.
	 * @param int    $accepted Accepted argument count.
	 * @return void
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted ); }
	/**
	 * Removes only synthetic WordPress-style request slashes.
	 *
	 * @since 0.3.0
	 * @param mixed $value Fixture transport.
	 * @return mixed Unslashed fixture.
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
	/**
	 * Normalizes fixed request method/header tokens for the isolated fixture.
	 *
	 * @since 0.3.0
	 * @param string $value Synthetic technical value.
	 * @return string Lowercase ASCII key.
	 */
	function sanitize_key( $value ): string {
		return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $value ) ); }

	/**
	 * Validates fixture UTF-8 without modifying caller input.
	 * @since 0.3.1
	 * @param string $value Candidate text.
	 * @param bool $strip Whether invalid input should be removed.
	 * @return string Original valid text or empty.
	 */
	function wp_check_invalid_utf8( $value, $strip = false ): string {
		return 1 === preg_match( '//u', $value ) ? $value : '';
	}
	/**
	 * Supplies the synthetic current public page identity.
	 * @since 0.3.1
	 * @return int Current page ID.
	 */
	function get_the_ID(): int {
		return 22;
	}
	/**
	 * Restricts presentation metadata to the synthetic public page.
	 * @since 0.3.1
	 * @param int $id Candidate page.
	 * @return bool Whether it is public.
	 */
	function is_post_publicly_viewable( $id ): bool {
		return 22 === (int) $id;
	}
	/**
	 * Normalizes a validated numeric display reference.
	 * @since 0.3.1
	 * @param mixed $value Candidate numeric value.
	 * @return int Positive integer.
	 */
	function absint( $value ): int {
		return abs( (int) $value );
	}
	/**
	 * Supplies basic fixture text sanitization for presentation references.
	 * @since 0.3.1
	 * @param string $value Candidate text.
	 * @return string Plain text.
	 */
	function sanitize_text_field( $value ): string {
		return trim( strip_tags( $value ) );
	}

	/**
	 * Returns the synthetic backend context.
	 *
	 * @since 0.3.0
	 * @return bool Fixture context.
	 */
	function is_admin(): bool {
		return $GLOBALS['rktsky_divi_admin']; }
	/**
	 * Returns the synthetic admin-ajax context, distinct from jQuery same-page POST.
	 *
	 * @since 0.3.0
	 * @return bool Fixture context.
	 */
	function wp_doing_ajax(): bool {
		return $GLOBALS['rktsky_divi_ajax']; }
	/**
	 * Supplies HTML-API URI metadata without filtering provider output.
	 *
	 * @since 0.3.0
	 * @return array<string> URI-bearing attributes used by the native parser.
	 */
	function wp_kses_uri_attributes(): array {
		return array( 'href', 'src', 'action' ); }
	/**
	 * Escapes valid ASCII fixture text as an HTML attribute.
	 *
	 * @since 0.3.0
	 * @param string $value Fixture text.
	 * @return string Escaped text.
	 */
	function esc_attr( $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	/**
	 * Escapes synthetic plain error content.
	 *
	 * @since 0.3.0
	 * @param string $value Fixture text.
	 * @return string Escaped text.
	 */
	function esc_html( $value ): string {
		return esc_attr( $value ); }
	/**
	 * Escapes a fixed local fixture URL.
	 *
	 * @since 0.3.0
	 * @param string $value Fixture URL.
	 * @return string Escaped URL attribute.
	 */
	function esc_url( $value ): string {
		return esc_attr( $value ); }
	/**
	 * Exposes an English gettext source label in the fixture.
	 *
	 * @since 0.3.0
	 * @param string $value Source label.
	 * @param string $domain Plugin domain.
	 * @return string Escaped source label.
	 */
	function esc_html__( $value, $domain ): string {
		return esc_html( $value ); }
	/**
	 * Supplies a server-resolved synthetic page ID.
	 *
	 * @since 0.3.0
	 * @return int Fixed page ID.
	 */
	function get_queried_object_id(): int {
		return 22; }
	/**
	 * Supplies a native generated URL, independent of submitted referrers.
	 *
	 * @since 0.3.0
	 * @param int $id Resolved native page ID.
	 * @return string Safe fixed local URL.
	 */
	function get_permalink( $id ): string {
		return 'https://local.invalid/?page_id=' . (int) $id; }
	/**
	 * Supplies the fixed configured site root.
	 *
	 * @since 0.3.0
	 * @param string $path Native relative path.
	 * @return string Fixed local root URL.
	 */
	function home_url( $path ): string {
		return 'https://local.invalid' . $path; }
	/**
	 * Records that a non-cacheable rejection was requested.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	function nocache_headers(): void {
		$GLOBALS['rktsky_divi_nocache'] = true; }
	/**
	 * Captures termination as an exception so the fixture can inspect output.
	 *
	 * @since 0.3.0
	 * @param string $message Escaped HTML failure.
	 * @param string $title Native error-page title.
	 * @param array  $args Native HTTP/termination arguments.
	 * @return void Always throws after recording the simulated response.
	 * @throws RuntimeException To represent native request termination.
	 */
	function wp_die( $message, $title, $args ): void {
		$GLOBALS['rktsky_divi_die'] = array( $message, $title, $args );
		throw new RuntimeException( 'fixture-request-stopped' ); }
	/**
	 * Records a contract assertion without outputting fixture transport.
	 *
	 * @since 0.3.0
	 * @param bool   $condition Expected boundary.
	 * @param string $message Failure context.
	 * @return void
	 */
	function rktsky_antispam_divi_expect( bool $condition, string $message ): void {
		++$GLOBALS['rktsky_divi_assertions'];
		if ( ! $condition ) {
			throw new RuntimeException( $message ); } }

	$wp_root = getenv( 'WP_ANTISPAM_WP_ROOT' ) ?: '/tmp/rktsky-antispam-runtime/site/wordpress';
	if ( ! is_file( $wp_root . '/wp-includes/html-api/class-wp-html-tag-processor.php' ) ) {
		throw new RuntimeException( 'An unchanged WordPress HTML API source directory is required for these contracts.' ); }
	if ( is_file( $wp_root . '/wp-includes/utf8.php' ) ) {
		require_once $wp_root . '/wp-includes/utf8.php'; }
	foreach ( array( 'span', 'attribute-token', 'text-replacement', 'decoder', 'tag-processor' ) as $name ) {
		require_once $wp_root . '/wp-includes/html-api/class-wp-html-' . $name . '.php'; }
	require dirname( __DIR__ ) . '/src/Integrations/Adapter.php';
	require dirname( __DIR__ ) . '/src/Integrations/Divi.php';
	$settings = new \Rktsky\Antispam\Settings();
	$guard    = new \Rktsky\Antispam\Guard();
	$adapter  = new \Rktsky\Antispam\Integrations\Divi( $settings, $guard, new \Rktsky\Antispam\Fields(), new \Rktsky\Antispam\Quarantine() );
	$adapter->register();
	rktsky_antispam_divi_expect( 100 === $GLOBALS['rktsky_divi_hooks']['wp_loaded'][1], 'Native contact POST must be checked before frontend, REST and AJAX rendering.' );
	rktsky_antispam_divi_expect( isset( $GLOBALS['rktsky_divi_hooks']['et_module_shortcode_output'], $GLOBALS['rktsky_divi_hooks']['divi_module_wrapper_render'] ) && ! isset( $GLOBALS['rktsky_divi_hooks']['et_pb_contact_form_submit'] ), 'D4/D5 output hooks must register without relying on the late submit observer.' );

	$native    = '<div id="et_pb_contact_form_0" class="et_pb_contact_form_container"><form method="post" class="et_pb_contact_form clearfix"><input name="et_pb_contactform_submit_0" value="et_contact_proccess"></form></div>';
	$decorated = $adapter->render_shortcode( $native, 'et_pb_contact_form' );
	rktsky_antispam_divi_expect( str_contains( $decorated, 'data-rk-antispam-provider="divi"' ) && str_contains( $decorated, 'data-rk-antispam-form="contact"' ) && str_contains( $decorated, 'proof&quot;&lt;&amp;' ), 'Native HTML API must escape the persistent D4 technical host.' );
	rktsky_antispam_divi_expect( str_contains( $decorated, 'name="et_pb_contactform_submit_0" value="et_contact_proccess"' ), 'The adapter must not rewrite native submit controls.' );
	$builder = array( 'content' => 'Synthetic Visual Builder data.' );
	rktsky_antispam_divi_expect( $builder === $adapter->render_shortcode( $builder, 'et_pb_contact_form' ), 'D4 Visual Builder structures must remain untouched.' );
	rktsky_antispam_divi_expect( $native === $adapter->render_shortcode( $native, 'et_pb_text' ), 'Unrelated D4 modules must remain untouched.' );
	rktsky_antispam_divi_expect( str_contains( $adapter->render_module( $native, array( 'name' => 'divi/contact-form' ) ), 'data-rk-antispam-provider="divi"' ), 'The documented D5 contact wrapper must receive a host.' );
	rktsky_antispam_divi_expect( $native === $adapter->render_module( $native, array( 'name' => 'divi/text' ) ), 'Unrelated D5 wrappers must remain untouched.' );
	$unrelated = '<form class="unrelated"><input name="private" value="unchanged"></form>';
	rktsky_antispam_divi_expect( $unrelated === $adapter->render_shortcode( $unrelated, 'et_pb_contact_form' ), 'A contact wrapper must not modify unrelated nested forms.' );

	$_SERVER['REQUEST_METHOD'] = 'POST';
	foreach ( array( '0', '17', 'c024e92d-c0b5-4052-86b2-0b27af7c40ac', 'cfbf417905a28f42a1223c8cb381e98a' ) as $target ) {
		$_POST = array(
			'et_pb_contactform_submit_' . $target   => 'et_contact_process',
			'et_pb_contact_email_fields_' . $target => 'UNTRUSTED-DECLARATION',
			'_wpnonce-et-pb-contact-form-submitted-' . $target => 'native-token',
			'rktsky_antispam_token'                 => 'synthetic-proof',
			'arbitrary_content'                     => 'DO-NOT-RETAIN',
		);
		$adapter->validate();
		$check = end( $guard->checks );
		rktsky_antispam_divi_expect( 'divi' === $check[0] && 'contact' === $check[1] && array( 'rktsky_antispam_token' => 'synthetic-proof' ) === $check[2] && array() === $check[3] && true === $check[4], 'Native numeric/UUID/hash targets must use only the fixed technical guard scope.' );
	}
	foreach ( array( 'et_pb_contactform_submit_', 'et_pb_contact_email_fields_', '_wpnonce-et-pb-contact-form-submitted-' ) as $prefix ) {
		$_POST  = array( $prefix . '0' => 'synthetic' );
		$before = count( $guard->checks );
		$adapter->validate();
		rktsky_antispam_divi_expect( count( $guard->checks ) === $before + 1, 'Removing one or two known envelope markers cannot avoid the technical gate.' );
	}
	foreach ( array( 'et_pb_contactform_submit_', 'et_pb_contactform_submit_' . str_repeat( 'x', 65 ), 'et_pb_contactform_submit_native/identifier', 'et_pb_contactform_submit_<script>' ) as $name ) {
		$_POST = array( $name => 'synthetic' );
		$before = count( $guard->rejected );
		try {
			$adapter->validate();
		} catch ( RuntimeException $failure ) {
			rktsky_antispam_divi_expect( 'fixture-request-stopped' === $failure->getMessage(), 'Unexpected unsupported-envelope failure.' );
		}
		rktsky_antispam_divi_expect( count( $guard->rejected ) === $before + 1, 'Every reserved native prefix must fail closed when its suffix is unsupported.' );
	}
	$_POST = array( 'other_provider' => 'unrelated' );
	$before = count( $guard->checks );
	$adapter->validate();
	rktsky_antispam_divi_expect( count( $guard->checks ) === $before, 'Unrelated provider fields must preserve native processing.' );
	$_POST  = array(
		'et_pb_contactform_submit_0' => 'et_contact_proccess',
		'et_pb_contactform_submit_1' => 'et_contact_proccess',
	);
	$before = count( $guard->checks );
	try {
		$adapter->validate();
	} catch ( RuntimeException $failure ) {
		rktsky_antispam_divi_expect( 'fixture-request-stopped' === $failure->getMessage(), 'Unexpected multi-target failure.' ); }
	rktsky_antispam_divi_expect( count( $guard->checks ) === $before && array( 'divi', 'invalid' ) === end( $guard->rejected ), 'A single proof must not authorize multiple native targets.' );

	foreach ( array( 'missing', 'honeypot', 'replay' ) as $reason ) {
		$guard->reason = $reason;
		foreach ( array( false, true ) as $ajax ) {
			$_POST                            = array(
				'et_pb_contactform_submit_c024e92d-c0b5-4052-86b2-0b27af7c40ac' => 'et_contact_process',
				'unsafe'           => '<script>DO-NOT-REFLECT</script>',
				'rktsky_antispam_page' => '22',
				'_wp_http_referer' => 'https://external.invalid/DO-NOT-REFLECT',
			);
			$_SERVER['HTTP_X_REQUESTED_WITH'] = $ajax ? 'XMLHttpRequest' : '';
			try {
				$adapter->validate();
			} catch ( RuntimeException $failure ) {
				rktsky_antispam_divi_expect( 'fixture-request-stopped' === $failure->getMessage(), 'Unexpected simulated request failure.' ); }
			list( $html, $title, $args ) = $GLOBALS['rktsky_divi_die'];
			rktsky_antispam_divi_expect( ( $ajax ? 200 : 403 ) === $args['response'] && false === $args['back_link'], 'Native AJAX needs selectable HTML; full-page rejection must remain HTTP403.' );
			rktsky_antispam_divi_expect( str_contains( $html, 'id="et_pb_contact_form_c024e92d-c0b5-4052-86b2-0b27af7c40ac"' ) && str_contains( $html, 'et_pb_contact_error_text' ) && str_contains( $html, 'role="alert"' ), 'Native container extraction must find a visible error and suppress success redirects.' );
			rktsky_antispam_divi_expect( str_contains( $html, '&lt;safely&gt;' ) && ! str_contains( $html, 'DO-NOT-REFLECT' ) && str_contains( $html, 'https://local.invalid/?page_id=22' ), 'Failure output must escape its message and use only a server-generated reload URL.' );
		}
	}
	$guard->reason = '';
	$_POST         = array( 'et_pb_contactform_submit_0' => 'et_contact_proccess' );
	foreach ( array( 'GET', 'disabled' ) as $context ) {
		$_SERVER['REQUEST_METHOD']    = 'GET' === $context ? 'GET' : 'POST';
		$settings->active             = 'disabled' !== $context;
		$GLOBALS['rktsky_divi_admin'] = 'admin' === $context;
		$GLOBALS['rktsky_divi_ajax']  = 'admin_ajax' === $context;
		$before                       = count( $guard->checks );
		$adapter->validate();
		rktsky_antispam_divi_expect( count( $guard->checks ) === $before, 'Disabled protection and native GET requests must preserve ordinary processing.' );
	}
	$settings->active = true;
	$_SERVER['REQUEST_METHOD'] = 'POST';
	foreach ( array( 'admin', 'admin_ajax' ) as $context ) {
		$GLOBALS['rktsky_divi_admin'] = 'admin' === $context;
		$GLOBALS['rktsky_divi_ajax'] = 'admin_ajax' === $context;
		$before = count( $guard->checks );
		$adapter->validate();
		rktsky_antispam_divi_expect( count( $guard->checks ) === $before + 1, 'Backend context flags cannot bypass the early native contact gate.' );
	}
	$GLOBALS['rktsky_divi_admin'] = false;
	$GLOBALS['rktsky_divi_ajax'] = false;
	$guard->reason = 'missing';
	foreach ( array( '22', '23' ) as $page ) {
		$_POST = array( 'et_pb_contactform_submit_0' => 'et_contact_proccess', 'rktsky_antispam_target' => 'rk-fixture-sales', 'rktsky_antispam_page' => $page );
		try {
			$adapter->validate();
		} catch ( RuntimeException $failure ) {
			rktsky_antispam_divi_expect( 'fixture-request-stopped' === $failure->getMessage(), 'Unexpected custom-target failure.' );
		}
		$html = $GLOBALS['rktsky_divi_die'][0];
		rktsky_antispam_divi_expect( str_contains( $html, 'id="rk-fixture-sales"' ), 'Custom CSS IDs must remain the exact native fragment target.' );
		rktsky_antispam_divi_expect( '22' === $page ? str_contains( $html, '?page_id=22' ) : ! str_contains( $html, '?page_id=23' ), 'A display reference must never reveal a private post permalink.' );
	}

	echo 'PASS: ' . $GLOBALS['rktsky_divi_assertions'] . " Divi adapter contracts; commercial PHP engine not loaded.\n";
}
