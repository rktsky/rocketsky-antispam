<?php
/**
 * Exercises native context routing and authenticated per-user/site notice preferences.
 *
 * Provider menu contracts use real WordPress menu APIs with synthetic capabilities;
 * this is not a claim that every provider's actual administration was browsed.
 * Menu-capability cases use process-local grants. Preference cases use real temporary
 * administrator/editor/subscriber accounts and native nonces. A network fixture also
 * creates/deletes one owned site. These fixtures are removed, settings stay unchanged,
 * and no submission/count or original package is edited.
 *
 * @package RktskyAntispamTests
 * @since 0.5.0
 */

use Rktsky\Antispam\{ContextNotices, Providers, Quarantine, Settings, SpamHandling};

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) {
	require_once ABSPATH . 'wp-admin/includes/ms.php';
}

$rktsky_notice_assertions = 0;

/**
 * Fails on an observed screen, permission or destination regression.
 *
 * @since 0.5.0
 * @param bool   $condition Required behavior.
 * @param string $message Failure context without visitor data.
 * @return void
 * @throws RuntimeException When a native invariant fails.
 */
function rktsky_notice_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_notice_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

/**
 * Captures the real native notice API output for an initialized screen.
 *
 * @since 0.5.0
 * @param ContextNotices $notices Owned read-only notice service.
 * @param string         $screen Native screen identifier or registered hook.
 * @return string Escaped HTML, or an empty string when the context is ineligible.
 */
function rktsky_notice_html( ContextNotices $notices, string $screen ): string {
	set_current_screen( $screen );
	$notices->prepare( get_current_screen() );
	ob_start();
	$notices->render();
	return (string) ob_get_clean();
}


/**
 * Extracts one actual POST form, including its user/session-bound native nonce.
 *
 * @since 0.5.5
 * @param string $html Rendered native notice or integrations page.
 * @param string $action Fixed owned admin-post action.
 * @return array<string,string> Submitted fields from exactly one matching form.
 */
function rktsky_notice_form( string $html, string $action ): array {
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$document->loadHTML( $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$xpath = new DOMXPath( $document );
	$forms = $xpath->query( '//form[.//input[@name="action" and @value="' . $action . '"]]' );
	rktsky_notice_expect( 1 === $forms->length, 'Exactly one native action form must own this preference.' );
	$form = $forms->item( 0 );
	rktsky_notice_expect( 'post' === strtolower( $form->getAttribute( 'method' ) ) && admin_url( 'admin-post.php' ) === $form->getAttribute( 'action' ), 'Preference mutations must use their native POST endpoint.' );
	$fields = array();
	foreach ( $xpath->query( './/input[@name]', $form ) as $field ) {
		$fields[ $field->getAttribute( 'name' ) ] = $field->getAttribute( 'value' );
	}
	return $fields;
}

/**
 * Observes native WordPress termination without replacing authentication APIs.
 *
 * @since 0.5.5
 * @param callable $operation Real registered hook or native controller callback.
 * @param array    $fields Native form fields, before WordPress request slashing.
 * @param string   $method Actual synthetic request method.
 * @return array{status:int,location:string} Original rejection or safe redirect.
 * @throws RuntimeException|ErrorException For unexpected controller failures or PHP diagnostics.
 */
function rktsky_notice_response( callable $operation, array $fields, string $method = 'POST' ): array {
	// phpcs:disable WordPress.Security.NonceVerification -- The fixture snapshots request globals; the actual controller below owns nonce verification.
	$original_post             = $_POST;
	$original_request          = $_REQUEST;
	$original_method           = $_SERVER['REQUEST_METHOD'] ?? 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Preserves the exact fixture method for restoration.
	$_POST                     = wp_slash( $fields );
	$_REQUEST                  = $_POST;
	$_SERVER['REQUEST_METHOD'] = $method;
	$response                  = array(
		'status'   => 0,
		'location' => '',
	);
	/**
	 * Supplies the native wp_die handler used only to expose its status to the test.
	 *
	 * @since 0.5.5
	 * @return Closure Observable native termination handler.
	 */
	$die = static function () use ( &$response ): Closure {
		/**
		 * Records an original native rejection instead of ending this test process.
		 *
		 * @since 0.5.5
		 * @param mixed $message Original escaped WordPress error.
		 * @param mixed $title Original native error title.
		 * @param mixed $arguments Native response metadata.
		 * @return never
		 * @throws RuntimeException Always, to stop the current handler only.
		 */
		return static function ( $message, $title = '', $arguments = array() ) use ( &$response ): never {
			$response['status'] = is_int( $arguments ) ? $arguments : (int) ( $arguments['response'] ?? 500 );
			throw new RuntimeException( 'rktsky_notice_native_stop' );
		};
	};
	/**
	 * Records the destination after wp_safe_redirect's original validation.
	 *
	 * @since 0.5.5
	 * @param string $location Original safe redirect destination.
	 * @param int    $status Native redirect status.
	 * @return never
	 * @throws RuntimeException Always, preventing only the final redirect/exit.
	 */
	$redirect = static function ( string $location, int $status ) use ( &$response ): never {
		$response = array(
			'status'   => $status,
			'location' => $location,
		);
		throw new RuntimeException( 'rktsky_notice_native_stop' );
	};
	/**
	 * Makes unexpected native PHP warnings fail the test instead of passing silently.
	 *
	 * @since 0.5.5
	 * @param int    $severity Native PHP diagnostic severity.
	 * @param string $message Original diagnostic, retained as failure evidence.
	 * @param string $file Original PHP source path.
	 * @param int    $line Original PHP source line.
	 * @return false Preserves explicit native error suppression where applicable.
	 * @throws ErrorException When an unsuppressed diagnostic occurs.
	 */
	$diagnostic = static function ( int $severity, string $message, string $file, int $line ): bool {
		if ( error_reporting() & $severity ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting,WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting -- Reads the current mask without changing configuration; diagnostics must fail this isolated test.
			throw new ErrorException( esc_html( $message ), 0, $severity, $file, $line ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Severity, filename and line are typed native diagnostic metadata, not HTML output.
		}
		return false;
	};
	set_error_handler( $diagnostic ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Unexpected native warnings fail this isolated regression; the previous handler is always restored.
	add_filter( 'wp_die_handler', $die, PHP_INT_MAX );
	add_filter( 'wp_redirect', $redirect, PHP_INT_MAX, 2 );
	try {
		$operation();
	} catch ( RuntimeException $exception ) {
		if ( 'rktsky_notice_native_stop' !== $exception->getMessage() ) {
			throw $exception;
		}
	} finally {
		restore_error_handler();
		remove_filter( 'wp_die_handler', $die, PHP_INT_MAX );
		remove_filter( 'wp_redirect', $redirect, PHP_INT_MAX );
		$_POST                     = $original_post;
		$_REQUEST                  = $original_request;
		$_SERVER['REQUEST_METHOD'] = $original_method;
	}
	// phpcs:enable WordPress.Security.NonceVerification
	return $response;
}

/**
 * Renders only the native profile extension for the selected actual account.
 *
 * @since 0.5.5
 * @param WP_User $user Original profile owner.
 * @return string Profile partial without a second form.
 */
function rktsky_notice_profile( WP_User $user ): string {
	ob_start();
	do_action( 'show_user_profile', $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Exercises the original native hook and registered owned callback.
	return (string) ob_get_clean();
}

$rktsky_notice_user   = get_current_user_id();
$rktsky_notice_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
rktsky_notice_expect( ! empty( $rktsky_notice_admins ), 'A disposable fixture administrator is required.' );

$rktsky_notice_created         = array();
$rktsky_notice_site            = 0;
$rktsky_notice_origin_site     = get_current_blog_id();
$rktsky_notice_settings_before = get_option( Settings::OPTION, null );
$rktsky_notice_roles           = array( 'administrator', 'editor', 'subscriber' );
foreach ( $rktsky_notice_roles as $rktsky_notice_role ) {
	$rktsky_notice_login = 'rk-notice-' . wp_generate_uuid4();
	$rktsky_notice_id    = wp_insert_user(
		array(
			'user_login' => $rktsky_notice_login,
			'user_email' => $rktsky_notice_login . '@example.invalid',
			'user_pass'  => wp_generate_password( 32, true, true ),
			'role'       => $rktsky_notice_role,
		)
	);
	rktsky_notice_expect( is_int( $rktsky_notice_id ) && $rktsky_notice_id > 0, 'A native temporary account must be created for each actual role.' );
	$rktsky_notice_created[ $rktsky_notice_role ] = $rktsky_notice_id;
}
// A non-super-admin account keeps native capability tests meaningful on networks.
wp_set_current_user( $rktsky_notice_created['administrator'] );
$rktsky_notice_options = ( new Settings() )->all();
foreach ( Providers::ids() as $rktsky_notice_provider ) {
	$rktsky_notice_options[ $rktsky_notice_provider ] = true;
}
$rktsky_notice_options['privacy_mode']       = true;
$rktsky_notice_options['quarantine_enabled'] = false;
$rktsky_notice_grants                        = array(
	'read'               => true,
	'manage_options'     => true,
	'rk_fixture_forms'   => true,
	'rk_fixture_entries' => true,
);

/**
 * Supplies only process-local settings through the documented option filter.
 *
 * @since 0.5.0
 * @return array Process-local validated test configuration.
 */
$rktsky_notice_option_filter = static function () use ( &$rktsky_notice_options ): array {
	return $rktsky_notice_options;
};
/**
 * Models a custom role without persisting roles or changing user metadata.
 *
 * @since 0.5.0
 * @return array<string,bool> Exact native capability grants for this case.
 */
$rktsky_notice_cap_filter = static function () use ( &$rktsky_notice_grants ): array {
	return $rktsky_notice_grants;
};
add_filter( 'pre_option_' . Settings::OPTION, $rktsky_notice_option_filter );
add_filter( 'user_has_cap', $rktsky_notice_cap_filter, PHP_INT_MAX );

try {
	$rktsky_notice_settings = new Settings();
	$rktsky_notice_handling = new SpamHandling( $rktsky_notice_settings, new Quarantine( $rktsky_notice_settings ) );
	$rktsky_notice_service  = new ContextNotices( $rktsky_notice_settings, $rktsky_notice_handling );
	$rktsky_notice_native   = $rktsky_notice_handling->describe( 'comments' );
	rktsky_notice_expect( 'native' === $rktsky_notice_native['mode'] && str_contains( $rktsky_notice_native['url'], 'comment_status=spam' ), 'Comments must retain their native spam destination.' );
	foreach ( array( 'elementor' ) as $rktsky_notice_provider ) {
		$rktsky_notice_route = $rktsky_notice_handling->describe( $rktsky_notice_provider );
		rktsky_notice_expect(
			'discard' === $rktsky_notice_route['mode']
			&& __( 'Rocketsky Antispam quarantine is off. New rejected messages are not saved here.', 'rocketsky-antispam' ) === $rktsky_notice_route['description']
			&& admin_url( 'options-general.php?page=rocketsky-antispam&tab=settings#retention' ) === $rktsky_notice_route['url'],
			'Disabled quarantine must explicitly disclose no new Rocketsky Antispam content storage and link to retention settings.'
		);
	}
	foreach ( array( 'divi', 'registration', 'wc_checkout', 'wc_registration' ) as $rktsky_notice_provider ) {
		rktsky_notice_expect( 'blocked' === $rktsky_notice_handling->describe( $rktsky_notice_provider )['mode'], 'Technical-only scopes must never promise a content quarantine.' );
	}
	$rktsky_notice_wpforms = $rktsky_notice_handling->describe( 'wpforms' );
	$rktsky_notice_pro     = is_callable( array( \Rktsky\Antispam\Integrations\WpForms::class, 'native_available' ) ) && \Rktsky\Antispam\Integrations\WpForms::native_available();
	rktsky_notice_expect( ( $rktsky_notice_pro ? 'conditional' : 'discard' ) === $rktsky_notice_wpforms['mode'], 'WPForms must distinguish Pro native capability from the Lite retention fallback.' );
	$rktsky_notice_options['privacy_mode']       = false;
	$rktsky_notice_options['quarantine_enabled'] = true;
	rktsky_notice_expect( 'quarantine' === $rktsky_notice_handling->local_retention()['mode'], 'Actual enabled encryption capability must expose local quarantine.' );
	$rktsky_notice_options['privacy_mode'] = true;
	rktsky_notice_expect( 'discard' === $rktsky_notice_handling->local_retention()['mode'], 'Privacy mode must prevent a local capture claim even if capture was previously selected.' );
	$rktsky_notice_options['cf7'] = false;
	rktsky_notice_expect( ! isset( $rktsky_notice_handling->directory()['cf7'] ), 'Disabled integrations must not appear as protected review destinations.' );
	$rktsky_notice_options['cf7'] = true;
	foreach ( $rktsky_notice_handling->directory() as $rktsky_notice_provider => $rktsky_notice_route ) {
		rktsky_notice_expect( ! empty( Providers::detected()[ $rktsky_notice_provider ] ), 'Undetected integrations must not appear in the review directory.' );
	}

	$rktsky_notice_inventory = array(
		array( 'cf7', 'wpcf7', 'wpcf7-dashboard' ),
		array( 'cf7', 'flamingo_inbound', 'flamingo' ),
		array( 'gravityforms', 'gf_entries', 'gf_edit_forms' ),
		array( 'wpforms', 'wpforms-overview', 'wpforms-overview' ),
		array( 'formidable', 'formidable-entries', 'formidable' ),
		array( 'fluentforms', 'fluent_forms_all_entries', 'fluent_forms' ),
		array( 'wsform', 'ws-form-submit', 'ws-form' ),
		array( 'elementor', 'e-form-submissions', 'elementor' ),
		array( 'divi', 'et_divi_options', 'et_divi_options' ),
		array( 'wc_checkout', 'wc-orders', 'woocommerce' ),
	);
	$rktsky_notice_hooks     = array();
	$rktsky_notice_match     = new ReflectionMethod( ContextNotices::class, 'screen_providers' );
	foreach ( $rktsky_notice_inventory as $rktsky_notice_page ) {
		list( $rktsky_notice_provider, $rktsky_notice_slug, $rktsky_notice_parent ) = $rktsky_notice_page;
		add_menu_page( 'Synthetic menu', 'Translated synthetic ' . $rktsky_notice_parent, 'rk_fixture_forms', $rktsky_notice_parent, '__return_null' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Synthetic menu capabilities are deliberately supplied by the process-local role test.
		$rktsky_notice_hooks[ $rktsky_notice_slug ] = add_submenu_page( $rktsky_notice_parent, 'Synthetic page', 'Synthetic page', 'rk_fixture_entries', $rktsky_notice_slug, '__return_null' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Synthetic menu capabilities are deliberately supplied by the process-local role test.
		set_current_screen( $rktsky_notice_hooks[ $rktsky_notice_slug ] );
		rktsky_notice_expect( array( $rktsky_notice_provider ) === $rktsky_notice_match->invoke( $rktsky_notice_service, get_current_screen() ), 'Registered translated native menu hooks must match their fixed integration.' );
	}

	$rktsky_notice_grants = array(
		'read'              => true,
		'moderate_comments' => true,
	);
	$rktsky_notice_html   = rktsky_notice_html( $rktsky_notice_service, 'edit-comments' );
	rktsky_notice_expect( str_contains( $rktsky_notice_html, 'comment_status=spam' ) && ! str_contains( $rktsky_notice_html, 'page=rocketsky-antispam' ), 'A comment moderator must receive the native queue without inaccessible Rocketsky Antispam links.' );
	$rktsky_notice_grants = array( 'read' => true );
	rktsky_notice_expect( '' === rktsky_notice_html( $rktsky_notice_service, 'edit-comments' ), 'A read-only subscriber must receive no protection notice on a forged comment screen.' );
	$rktsky_notice_grants = array(
		'read'               => true,
		'rk_fixture_entries' => true,
	);
	if ( ! empty( Providers::detected()['wpforms'] ) ) {
		$rktsky_notice_html = rktsky_notice_html( $rktsky_notice_service, $rktsky_notice_hooks['wpforms-overview'] );
		rktsky_notice_expect( str_contains( $rktsky_notice_html, 'Rocketsky Antispam is enabled' ) && ! str_contains( $rktsky_notice_html, 'page=rocketsky-antispam' ), 'An authorized WPForms manager must receive text without privileged local links.' );
	}
	if ( ! empty( Providers::detected()['gravityforms'] ) ) {
		$rktsky_notice_html = rktsky_notice_html( $rktsky_notice_service, $rktsky_notice_hooks['gf_entries'] );
		rktsky_notice_expect( str_contains( $rktsky_notice_html, 'page=gf_entries' ) && ! str_contains( $rktsky_notice_html, 'page=rocketsky-antispam' ), 'A native Gravity Forms entry manager must receive only the authorized native destination.' );
		$rktsky_notice_options['gravityforms'] = false;
		rktsky_notice_expect( '' === rktsky_notice_html( $rktsky_notice_service, $rktsky_notice_hooks['gf_entries'] ), 'An available but disabled provider must make no protection claim.' );
		$rktsky_notice_options['gravityforms']    = true;
		$rktsky_notice_grants['rk_fixture_forms'] = true;
		remove_submenu_page( 'gf_edit_forms', 'gf_entries' );
		remove_menu_page( 'gf_edit_forms' );
		$rktsky_notice_entry_hook = add_menu_page( 'Synthetic entries only', 'Translated entries only', 'rk_fixture_entries', 'gf_entries', '__return_null' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Synthetic menu capabilities are deliberately supplied by the process-local role test.
		add_submenu_page( 'gf_entries', 'Synthetic entries', 'Synthetic entries', 'rk_fixture_entries', 'gf_entries', '__return_null' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Synthetic menu capabilities are deliberately supplied by the process-local role test.
		unset( $rktsky_notice_grants['rk_fixture_forms'] );
		$rktsky_notice_html = rktsky_notice_html( $rktsky_notice_service, $rktsky_notice_entry_hook );
		rktsky_notice_expect( str_contains( $rktsky_notice_html, 'page=gf_entries' ) && str_contains( $rktsky_notice_html, 'gf-notice' ), 'An entry-only native role must retain its alternate top-level hook, inbox link and GF notice classification.' );
	}
	$rktsky_notice_grants = array(
		'read'              => true,
		'manage_options'    => true,
		'moderate_comments' => true,
	);
	foreach ( array( 'dashboard', 'plugins', 'edit-comments-network', 'profile-user', 'front' ) as $rktsky_notice_screen ) {
		rktsky_notice_expect( '' === rktsky_notice_html( $rktsky_notice_service, $rktsky_notice_screen ), 'Unrelated, network, user and frontend screens must receive no notice.' );
	}
	$rktsky_notice_options['comments'] = false;
	rktsky_notice_expect( '' === rktsky_notice_html( $rktsky_notice_service, 'edit-comments' ), 'Disabled comment protection must not emit a notice.' );
	$rktsky_notice_options['comments'] = true;
	$rktsky_notice_html                = rktsky_notice_html( $rktsky_notice_service, 'edit-comments' );
	rktsky_notice_expect( str_contains( $rktsky_notice_html, 'notice-info' ) && str_contains( $rktsky_notice_html, 'rktsky_antispam_notice_hide' ) && ! str_contains( $rktsky_notice_html, 'is-dismissible' ), 'Guidance must provide an authenticated persistent hide form without a transient JavaScript-only dismissal.' );
	$rktsky_notice_grants = array( 'read' => true );
	ob_start();
	$rktsky_notice_service->render();
	$rktsky_notice_late = (string) ob_get_clean();
	rktsky_notice_expect( '' === $rktsky_notice_late, 'Capability revocation after screen preparation must suppress output.' );
	// Authorization below uses actual Core roles, without the synthetic menu grants.
	remove_filter( 'user_has_cap', $rktsky_notice_cap_filter, PHP_INT_MAX );
	wp_set_current_user( $rktsky_notice_created['administrator'] );
	$rktsky_notice_hide    = static function (): void {
		do_action( 'admin_post_rktsky_antispam_notice_hide' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Exercises the original native hook and registered owned callback.
	};
	$rktsky_notice_restore = static function (): void {
		do_action( 'admin_post_rktsky_antispam_notice_restore' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Exercises the original native hook and registered owned callback.
	};
	rktsky_notice_expect( false !== has_action( 'admin_post_rktsky_antispam_notice_hide' ) && false !== has_action( 'admin_post_rktsky_antispam_notice_restore' ), 'Normal plugin boot must register both authenticated native action routes.' );
	rktsky_notice_expect( array() === ContextNotices::hidden() && ! ContextNotices::has_hidden(), 'A new account must start with visible notices and no hidden preference.' );
	$rktsky_notice_html   = rktsky_notice_html( $rktsky_notice_service, 'edit-comments' );
	$rktsky_notice_fields = rktsky_notice_form( $rktsky_notice_html, 'rktsky_antispam_notice_hide' );
	rktsky_notice_expect( 'comments' === $rktsky_notice_fields['provider'] && 'moderate_comments' === $rktsky_notice_fields['capability'] && false !== wp_verify_nonce( $rktsky_notice_fields['rktsky_antispam_notices_nonce'], 'rktsky_antispam_notice_hide|' . get_current_blog_id() . '|comments|moderate_comments' ), 'The actual form must bind the native provider, menu capability, user and site into its nonce.' );
	$rktsky_notice_meta_key = $GLOBALS['wpdb']->get_blog_prefix() . ContextNotices::USER_OPTION;
	rktsky_notice_expect( ! metadata_exists( 'user', get_current_user_id(), $rktsky_notice_meta_key ), 'Merely rendering the notice must not create a preference.' );
	$rktsky_notice_fields['_wp_http_referer'] = admin_url( 'edit-comments.php' );
	$rktsky_notice_response                   = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_fields, 'GET' );
	rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ! ContextNotices::has_hidden(), 'A GET request with a genuine nonce must not change notice preferences.' );
	$rktsky_notice_bad                                  = $rktsky_notice_fields;
	$rktsky_notice_bad['rktsky_antispam_notices_nonce'] = 'invalid';
	$rktsky_notice_response                             = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_bad );
	rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ! ContextNotices::has_hidden(), 'An invalid nonce must not create a hidden notice.' );
	foreach ( array( array( 'comments' ), 'comments,untrusted', '../comments', str_repeat( 'x', 257 ), 'comments,comments' ) as $rktsky_notice_bad_id ) {
		$rktsky_notice_bad             = $rktsky_notice_fields;
		$rktsky_notice_bad['provider'] = $rktsky_notice_bad_id;
		$rktsky_notice_response        = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_bad );
		rktsky_notice_expect( 400 === $rktsky_notice_response['status'] && ! ContextNotices::has_hidden(), 'Malformed, duplicate, oversized or unknown provider IDs must fail without persistence.' );
	}
	foreach ( array( array( 'nonce' ), false, 123 ) as $rktsky_notice_bad_nonce ) {
		$rktsky_notice_bad                                  = $rktsky_notice_fields;
		$rktsky_notice_bad['rktsky_antispam_notices_nonce'] = $rktsky_notice_bad_nonce;
		$rktsky_notice_response                             = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_bad );
		rktsky_notice_expect( in_array( $rktsky_notice_response['status'], array( 400, 403 ), true ) && ! ContextNotices::has_hidden(), 'Malformed nonce shapes must fail without PHP diagnostics or preference writes.' );
	}
	$rktsky_notice_bad               = $rktsky_notice_fields;
	$rktsky_notice_bad['capability'] = array( 'moderate_comments' );
	$rktsky_notice_response          = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_bad );
	rktsky_notice_expect( 400 === $rktsky_notice_response['status'] && ! ContextNotices::has_hidden(), 'An array capability must fail before native authorization.' );
	$rktsky_notice_bad               = $rktsky_notice_fields;
	$rktsky_notice_bad['capability'] = 'read';
	$rktsky_notice_response          = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_bad );
	rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ! ContextNotices::has_hidden(), 'Changing the signed native capability must invalidate the rendered nonce.' );
	$rktsky_notice_options['comments'] = false;
	$rktsky_notice_response            = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_fields );
	rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ! ContextNotices::has_hidden(), 'Disabling protection after form rendering must invalidate a later hide request.' );
	$rktsky_notice_options['comments'] = true;
	foreach ( Providers::detected() as $rktsky_notice_provider => $rktsky_notice_available ) {
		if ( ! $rktsky_notice_available ) {
			$rktsky_notice_bad                                  = $rktsky_notice_fields;
			$rktsky_notice_bad['provider']                      = $rktsky_notice_provider;
			$rktsky_notice_bad['capability']                    = 'manage_options';
			$rktsky_notice_bad['rktsky_antispam_notices_nonce'] = wp_create_nonce( 'rktsky_antispam_notice_hide|' . get_current_blog_id() . '|' . $rktsky_notice_provider . '|manage_options' );
			$rktsky_notice_response                             = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_bad );
			rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ! ContextNotices::has_hidden(), 'An unavailable provider must be rejected even with an otherwise genuine signed capability.' );
			break;
		}
	}
	wp_set_current_user( 0 );
	$rktsky_notice_response = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_fields );
	rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && array() === ContextNotices::hidden(), 'An unauthenticated action must not create any preference.' );
	$rktsky_notice_response = rktsky_notice_response( $rktsky_notice_restore, array() );
	rktsky_notice_expect( 403 === $rktsky_notice_response['status'], 'Restoring display preferences must also require an authenticated current account.' );
	wp_set_current_user( $rktsky_notice_created['subscriber'] );
	$rktsky_notice_bad                                  = $rktsky_notice_fields;
	$rktsky_notice_bad['rktsky_antispam_notices_nonce'] = wp_create_nonce( 'rktsky_antispam_notice_hide|' . get_current_blog_id() . '|comments|moderate_comments' );
	$rktsky_notice_response                             = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_bad );
	rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ! ContextNotices::has_hidden(), 'A native subscriber cannot hide a moderator notice even with a current user-bound nonce.' );

	wp_set_current_user( $rktsky_notice_created['administrator'] );
	$rktsky_notice_fields['user_id'] = $rktsky_notice_created['editor'];
	$rktsky_notice_fields['blog_id'] = 999999;
	$rktsky_notice_response          = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_fields );
	rktsky_notice_expect( 302 === $rktsky_notice_response['status'] && admin_url( 'edit-comments.php' ) === $rktsky_notice_response['location'] && array( 'comments' ) === ContextNotices::hidden(), 'A valid POST must persist only the current account/site and return to the native screen.' );
	$rktsky_notice_saved = get_user_option( ContextNotices::USER_OPTION );
	rktsky_notice_expect(
		array(
			'site'      => get_current_blog_id(),
			'providers' => array( 'comments' => true ),
		) === $rktsky_notice_saved && false === get_user_option( ContextNotices::USER_OPTION, $rktsky_notice_created['editor'] ),
		'Spoofed user/blog IDs must not select metadata owners or create extra stored fields.'
	);
	$rktsky_notice_response = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_fields );
	rktsky_notice_expect( 302 === $rktsky_notice_response['status'] && get_user_option( ContextNotices::USER_OPTION ) === $rktsky_notice_saved, 'Repeating a valid hide action must be idempotent.' );
	rktsky_notice_expect( '' === rktsky_notice_html( $rktsky_notice_service, 'edit-comments' ) && str_contains( rktsky_notice_html( $rktsky_notice_service, 'users' ), 'Rocketsky Antispam is enabled' ), 'Hiding comments must suppress later comment notices without hiding another native provider.' );

	wp_set_current_user( $rktsky_notice_created['editor'] );
	rktsky_notice_expect( ! ContextNotices::has_hidden() && str_contains( rktsky_notice_html( $rktsky_notice_service, 'edit-comments' ), 'rktsky_antispam_notice_hide' ), 'Another real native account on the same site must retain the default visible notice.' );
	$rktsky_notice_editor_fields = rktsky_notice_form( rktsky_notice_html( $rktsky_notice_service, 'edit-comments' ), 'rktsky_antispam_notice_hide' );
	$rktsky_notice_editor        = wp_get_current_user();
	$rktsky_notice_editor->set_role( 'subscriber' );
	$rktsky_notice_response = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_editor_fields );
	rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ! ContextNotices::has_hidden(), 'A native role revocation after nonce creation must stop the same previously authorized user.' );
	$rktsky_notice_editor->set_role( 'editor' );
	$rktsky_notice_editor_fields['_wp_http_referer'] = 'https://external.example/wp-admin/edit-comments.php';
	$rktsky_notice_response                          = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_editor_fields );
	rktsky_notice_expect( current_user_can( 'moderate_comments' ) && ! current_user_can( 'manage_options' ) && 302 === $rktsky_notice_response['status'] && admin_url( 'profile.php' ) === $rktsky_notice_response['location'], 'A native editor must hide an authorized notice and receive an accessible own-profile fallback.' );
	$rktsky_notice_profile = rktsky_notice_profile( wp_get_current_user() );
	rktsky_notice_expect( str_contains( $rktsky_notice_profile, 'rktsky_antispam_restore_notices' ) && ! str_contains( strtolower( $rktsky_notice_profile ), '<form' ) && '' === rktsky_notice_profile( get_user_by( 'id', $rktsky_notice_created['administrator'] ) ), 'Only the current profile must offer a restore checkbox inside Core\'s existing form.' );
	$rktsky_notice_profile_fields = array(
		'email'                         => wp_get_current_user()->user_email,
		'rktsky_antispam_notices_nonce' => wp_create_nonce( ContextNotices::restore_nonce_action() ),
	);
	$rktsky_notice_profile_save   = static function () use ( $rktsky_notice_created ): void {
		do_action( 'personal_options_update', $rktsky_notice_created['editor'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Exercises the original native hook and registered owned callback.
	};
	rktsky_notice_response( $rktsky_notice_profile_save, $rktsky_notice_profile_fields );
	rktsky_notice_expect( ContextNotices::has_hidden(), 'Saving an unchecked native profile must leave preferences unchanged.' );
	$rktsky_notice_profile_fields['rktsky_antispam_restore_notices'] = '1';
	$rktsky_notice_bad                                  = $rktsky_notice_profile_fields;
	$rktsky_notice_bad['rktsky_antispam_notices_nonce'] = 'invalid';
	$rktsky_notice_response                             = rktsky_notice_response( $rktsky_notice_profile_save, $rktsky_notice_bad );
	rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ContextNotices::has_hidden(), 'The profile restore requires its additional native preference nonce.' );
	rktsky_notice_response(
		static function () use ( $rktsky_notice_created, $rktsky_notice_service ): void {
			$rktsky_notice_service->save_profile( $rktsky_notice_created['administrator'] );
		},
		$rktsky_notice_profile_fields
	);
	rktsky_notice_expect( ContextNotices::has_hidden() && ! empty( get_user_option( ContextNotices::USER_OPTION, $rktsky_notice_created['administrator'] )['providers']['comments'] ), 'A profile save targeting another account must not restore either account.' );
	rktsky_notice_response( $rktsky_notice_profile_save, $rktsky_notice_profile_fields );
	rktsky_notice_expect( ! ContextNotices::has_hidden() && '' === rktsky_notice_profile( wp_get_current_user() ) && ! empty( get_user_option( ContextNotices::USER_OPTION, $rktsky_notice_created['administrator'] )['providers']['comments'] ), 'A native moderator must be able to restore their own notices without manage_options or another account change.' );
	rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_editor_fields );

	wp_set_current_user( $rktsky_notice_created['administrator'] );
	$rktsky_notice_restore_fields = array(
		'action'                        => 'rktsky_antispam_notice_restore',
		'rktsky_antispam_notices_nonce' => wp_create_nonce( ContextNotices::restore_nonce_action() ),
		'_wp_http_referer'              => 'https://external.example/wp-admin/edit-comments.php',
		'user_id'                       => $rktsky_notice_created['editor'],
		Settings::OPTION                => array(
			'comments'     => false,
			'privacy_mode' => false,
		),
	);
	$rktsky_notice_response       = rktsky_notice_response( $rktsky_notice_restore, $rktsky_notice_restore_fields, 'GET' );
	rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ContextNotices::has_hidden(), 'A GET restore with a valid nonce must not change display preferences.' );
	$rktsky_notice_bad                                  = $rktsky_notice_restore_fields;
	$rktsky_notice_bad['rktsky_antispam_notices_nonce'] = 'invalid';
	$rktsky_notice_response                             = rktsky_notice_response( $rktsky_notice_restore, $rktsky_notice_bad );
	rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ContextNotices::has_hidden(), 'An invalid restore nonce must preserve preferences.' );
	$rktsky_notice_bad                                  = $rktsky_notice_restore_fields;
	$rktsky_notice_bad['rktsky_antispam_notices_nonce'] = array( 'invalid' );
	$rktsky_notice_response                             = rktsky_notice_response( $rktsky_notice_restore, $rktsky_notice_bad );
	rktsky_notice_expect( in_array( $rktsky_notice_response['status'], array( 400, 403 ), true ) && ContextNotices::has_hidden(), 'An array restore nonce must fail without PHP diagnostics or a preference change.' );
	$rktsky_notice_response = rktsky_notice_response( $rktsky_notice_restore, $rktsky_notice_restore_fields );
	rktsky_notice_expect( 302 === $rktsky_notice_response['status'] && admin_url( 'options-general.php?page=rocketsky-antispam&tab=integrations' ) === $rktsky_notice_response['location'] && ! ContextNotices::has_hidden() && ! empty( get_user_option( ContextNotices::USER_OPTION, $rktsky_notice_created['editor'] )['providers']['comments'] ), 'Restore must clear only the caller\'s display preferences and reject an external return URL.' );

	$rktsky_notice_allowed_hosts = static function ( array $hosts ): array {
		$hosts[] = 'external.example';
		return $hosts;
	};
	add_filter( 'allowed_redirect_hosts', $rktsky_notice_allowed_hosts );
	try {
		foreach ( array( 'https://external.example/wp-admin/edit-comments.php', admin_url( '../index.php' ), admin_url( 'nested/page.php' ), admin_url( '%2e%2e/index.php' ), 'http://127.0.0.1:8899/wp-admin/edit-comments.php' ) as $rktsky_notice_referer ) {
			$rktsky_notice_bad                     = $rktsky_notice_fields;
			$rktsky_notice_bad['_wp_http_referer'] = $rktsky_notice_referer;
			$rktsky_notice_response                = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_bad );
			rktsky_notice_expect( 302 === $rktsky_notice_response['status'] && admin_url( 'options-general.php?page=rocketsky-antispam&tab=integrations' ) === $rktsky_notice_response['location'], 'Even allowed hosts, dot/encoded segments, nested paths or a changed port must not widen the current site\'s admin return route.' );
		}
	} finally {
		remove_filter( 'allowed_redirect_hosts', $rktsky_notice_allowed_hosts );
	}

	if ( is_multisite() ) {
		$rktsky_notice_network = get_network();
		$rktsky_notice_site    = wp_insert_site(
			array(
				'domain'     => $rktsky_notice_network->domain,
				'path'       => $rktsky_notice_network->path . 'rk-notice-' . wp_generate_uuid4() . '/',
				'network_id' => $rktsky_notice_network->id,
				'public'     => 0,
			)
		);
		rktsky_notice_expect( is_int( $rktsky_notice_site ) && $rktsky_notice_site > 0, 'Native site APIs must create an isolated preference scope.' );
		add_user_to_blog( $rktsky_notice_site, $rktsky_notice_created['administrator'], 'administrator' );
		$rktsky_notice_other_key   = $GLOBALS['wpdb']->get_blog_prefix( $rktsky_notice_site ) . ContextNotices::USER_OPTION;
		$rktsky_notice_main_admin  = get_user_meta( $rktsky_notice_created['administrator'], $rktsky_notice_meta_key, true );
		$rktsky_notice_main_editor = get_user_meta( $rktsky_notice_created['editor'], $rktsky_notice_meta_key, true );
		update_user_meta(
			$rktsky_notice_created['administrator'],
			ContextNotices::USER_OPTION,
			array(
				'site'      => $rktsky_notice_origin_site,
				'providers' => array( 'comments' => true ),
			)
		);
		update_user_meta( $rktsky_notice_created['administrator'], 'rktsky_notice_fixture_keep', 'Unrelated native metadata.' );
		switch_to_blog( $rktsky_notice_site );
		try {
			rktsky_notice_expect( ! ContextNotices::has_hidden(), 'Another site must ignore the first site\'s preference and Core\'s unprefixed global fallback.' );
			$rktsky_notice_response = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_fields );
			rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ! ContextNotices::has_hidden(), 'A genuine main-site hide nonce must not authorize the same account on another site.' );
			$rktsky_notice_site_fields                     = rktsky_notice_form( rktsky_notice_html( $rktsky_notice_service, 'edit-comments' ), 'rktsky_antispam_notice_hide' );
			$rktsky_notice_site_fields['_wp_http_referer'] = admin_url( 'edit-comments.php' );
			$rktsky_notice_response                        = rktsky_notice_response( $rktsky_notice_hide, $rktsky_notice_site_fields );
			rktsky_notice_expect( 302 === $rktsky_notice_response['status'] && array( 'comments' ) === ContextNotices::hidden() && get_user_option( ContextNotices::USER_OPTION )['site'] === $rktsky_notice_site, 'The second site must persist its own stamped preference through a site-bound nonce.' );
			$rktsky_notice_response = rktsky_notice_response( $rktsky_notice_restore, $rktsky_notice_restore_fields );
			rktsky_notice_expect( 403 === $rktsky_notice_response['status'] && ContextNotices::has_hidden(), 'A main-site restore nonce must not clear another site\'s preferences.' );
			$rktsky_notice_response = rktsky_notice_response( $rktsky_notice_restore, array( 'rktsky_antispam_notices_nonce' => wp_create_nonce( ContextNotices::restore_nonce_action() ) ) );
			rktsky_notice_expect( 302 === $rktsky_notice_response['status'] && ! ContextNotices::has_hidden(), 'A genuine second-site restore must remain usable.' );
			update_user_option(
				$rktsky_notice_created['administrator'],
				ContextNotices::USER_OPTION,
				array(
					'site'      => $rktsky_notice_site,
					'providers' => array( 'comments' => true ),
				),
				false
			);
			update_user_option(
				$rktsky_notice_created['editor'],
				ContextNotices::USER_OPTION,
				array(
					'site'      => $rktsky_notice_site,
					'providers' => array( 'comments' => true ),
				),
				false
			);
		} finally {
			restore_current_blog();
		}
		rktsky_notice_expect( get_user_meta( $rktsky_notice_created['administrator'], $rktsky_notice_meta_key, true ) === $rktsky_notice_main_admin && get_user_meta( $rktsky_notice_created['editor'], $rktsky_notice_meta_key, true ) === $rktsky_notice_main_editor, 'Second-site hide/restore must leave both first-site user preferences unchanged.' );
		wp_delete_site( $rktsky_notice_site );
		$rktsky_notice_site = 0;
		rktsky_notice_expect( ! metadata_exists( 'user', $rktsky_notice_created['administrator'], $rktsky_notice_other_key ) && ! metadata_exists( 'user', $rktsky_notice_created['editor'], $rktsky_notice_other_key ), 'Actual native site deletion must remove that site\'s preference key for every account.' );
		rktsky_notice_expect( get_user_meta( $rktsky_notice_created['administrator'], $rktsky_notice_meta_key, true ) === $rktsky_notice_main_admin && get_user_meta( $rktsky_notice_created['editor'], $rktsky_notice_meta_key, true ) === $rktsky_notice_main_editor && 'Unrelated native metadata.' === get_user_meta( $rktsky_notice_created['administrator'], 'rktsky_notice_fixture_keep', true ) && metadata_exists( 'user', $rktsky_notice_created['administrator'], ContextNotices::USER_OPTION ), 'Site deletion must preserve other-site preferences and unrelated/global user metadata.' );
	}
} finally {
	remove_filter( 'pre_option_' . Settings::OPTION, $rktsky_notice_option_filter );
	remove_filter( 'user_has_cap', $rktsky_notice_cap_filter, PHP_INT_MAX );

	wp_set_current_user( (int) $rktsky_notice_admins[0] );
	if ( $rktsky_notice_site > 0 && get_site( $rktsky_notice_site ) ) {
		wp_delete_site( $rktsky_notice_site );
	}
	foreach ( $rktsky_notice_created as $rktsky_notice_id ) {
		if ( is_multisite() ) {
			wpmu_delete_user( $rktsky_notice_id );
		} else {
			wp_delete_user( $rktsky_notice_id );
		}
	}
	wp_set_current_user( $rktsky_notice_user );
}
rktsky_notice_expect( get_option( Settings::OPTION, null ) === $rktsky_notice_settings_before, 'Every native notice flow must preserve the actual protection settings exactly.' );
foreach ( $rktsky_notice_created as $rktsky_notice_id ) {
	rktsky_notice_expect( false === get_user_by( 'id', $rktsky_notice_id ), 'Every temporary native account and its preferences must be removed.' );
}
echo 'PASS: ' . esc_html( (string) $rktsky_notice_assertions ) . " native menu, role, preference and site-isolation assertions; fixture state restored.\n";
