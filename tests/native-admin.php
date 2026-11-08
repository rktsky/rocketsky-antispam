<?php
/**
 * Verifies native administrator routing, form boundaries and partial settings saves.
 *
 * The real controller and templates run only in a marked disposable site.
 * Native termination filters expose responses without replacing Core functions.
 * Approved owned counter tables are snapshotted for the actual clear action;
 * settings and counters are restored on failure. Browser HTTP authentication,
 * options.php redirects and visual geometry require their separate acceptance run.
 *
 * @package RktskyAntispamTests
 * @since 0.4.0
 */

use Rktsky\Antispam\Admin;
use Rktsky\Antispam\Feedback;
use Rktsky\Antispam\Providers;
use Rktsky\Antispam\Quarantine;
use Rktsky\Antispam\Settings;
use Rktsky\Antispam\Store;

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$rktsky_admin_assertions = 0;
$rktsky_admin_queries    = array();

/**
 * Records an observable native invariant without exposing stored contact values.
 *
 * @since 0.4.0
 * @param bool   $condition Required native behavior.
 * @param string $message Failure context.
 * @return void
 */
function rktsky_antispam_admin_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_admin_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Counts owned-table activity through the documented query passthrough filter.
 *
 * SQL is neither changed nor retained; Core/provider tables are not examined.
 *
 * @since 0.4.0
 * @param string $query Original WordPress query, returned exactly as received.
 * @return string Unmodified query.
 */
function rktsky_antispam_admin_query( string $query ): string {
	global $wpdb;
	foreach ( array( 'stats', 'totals', 'quarantine', 'feedback' ) as $table ) {
		if ( str_contains( $query, $wpdb->prefix . 'rktsky_antispam_' . $table ) ) {
			$GLOBALS['rktsky_admin_queries'][ $table ] = ( $GLOBALS['rktsky_admin_queries'][ $table ] ?? 0 ) + 1;
		}
	}
	return $query;
}

/**
 * Renders the actual view while observing its owned repository work.
 *
 * @since 0.4.0
 * @param Admin $admin Real administrator controller.
 * @param mixed $tab Requested query value, including malformed shapes.
 * @return array{html:string,xpath:DOMXPath,queries:array<string,int>} Actual view.
 */
function rktsky_antispam_admin_view( Admin $admin, $tab ): array {
	$_GET                            = array(
		'page' => 'rocketsky-antispam',
		'tab'  => $tab,
	);
	$_SERVER['REQUEST_URI']          = '/wp-admin/options-general.php?page=rocketsky-antispam&tab=' . ( is_string( $tab ) ? rawurlencode( $tab ) : 'overview' );
	$GLOBALS['rktsky_admin_queries'] = array();
	add_filter( 'query', 'rktsky_antispam_admin_query', PHP_INT_MAX );
	ob_start();
	try {
		$admin->render();
		$html = (string) ob_get_contents();
	} finally {
		ob_end_clean();
		remove_filter( 'query', 'rktsky_antispam_admin_query', PHP_INT_MAX );
	}
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8">' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	return array(
		'html'    => $html,
		'xpath'   => new DOMXPath( $document ),
		'queries' => $GLOBALS['rktsky_admin_queries'],
	);
}

/**
 * Reads the visible quarantine state from the actual monitoring or review view.
 *
 * The translated field label identifies Overview's state without depending on
 * column order. Review uses its stable list heading, shared with native links.
 *
 * @since 0.5.2
 * @param array  $view Actual controller output and native DOM query service.
 * @param string $tab Allowlisted overview or quarantine view.
 * @return string Visible state, or empty if the expected status is absent.
 */
function rktsky_antispam_admin_quarantine_status( array $view, string $tab ): string {
	$xpath = $view['xpath'];
	if ( 'overview' === $tab ) {
		foreach ( $xpath->query( '//div[contains(concat(" ",normalize-space(@class)," ")," rktsky-antispam__status-item ")]' ) as $row ) {
			$label = $xpath->query( './span', $row )->item( 0 );
			if ( null !== $label && __( 'Quarantine', 'rocketsky-antispam' ) === trim( $label->textContent ) ) {
				$status = $xpath->query( './strong', $row )->item( 0 );
				return null !== $status ? trim( $status->textContent ) : '';
			}
		}
		return '';
	}
	$status = $xpath->query( '//*[@id="rktsky-quarantine-list-heading"]/../span' )->item( 0 );
	return null !== $status ? trim( $status->textContent ) : '';
}

/**
 * Inspects raw form tokens before a DOM parser can repair invalid nesting.
 *
 * @since 0.4.0
 * @param string $html Actual controller output.
 * @return array<int,array{action:string,method:string,fields:array<string,string>}> Forms.
 */
function rktsky_antispam_admin_forms( string $html ): array {
	$processor = new WP_HTML_Tag_Processor( $html );
	$forms     = array();
	$current   = null;
	while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
		$tag = $processor->get_tag();
		if ( 'FORM' === $tag ) {
			if ( $processor->is_tag_closer() ) {
				rktsky_antispam_admin_expect( null !== $current, 'A form closing token has no matching opening token.' );
				$current = null;
			} else {
				rktsky_antispam_admin_expect( null === $current, 'Rendered administrator forms must never be nested.' );
				$forms[] = array(
					'action' => (string) $processor->get_attribute( 'action' ),
					'method' => strtolower( (string) $processor->get_attribute( 'method' ) ),
					'fields' => array(),
				);
				$current = array_key_last( $forms );
			}
		} elseif ( ! $processor->is_tag_closer() && in_array( $tag, array( 'INPUT', 'SELECT', 'TEXTAREA', 'BUTTON' ), true ) ) {
			$name = $processor->get_attribute( 'name' );
			if ( is_string( $name ) ) {
				if ( null === $current ) {
					rktsky_antispam_admin_expect( ! str_starts_with( $name, Settings::OPTION . '[' ), 'Settings controls must be inside their native form.' );
				} else {
					$forms[ $current ]['fields'][ $name ] = (string) $processor->get_attribute( 'value' );
				}
			}
		}
	}
	rktsky_antispam_admin_expect( null === $current, 'Every administrator form must be explicitly closed.' );
	return $forms;
}

/**
 * Observes native termination after the real controller's authorization checks.
 *
 * @since 0.4.0
 * @param callable $operation Actual controller callback to execute.
 * @return array{status:int,location:string} Native status or safe redirect target.
 */
function rktsky_antispam_admin_response( callable $operation ): array {
	$observed = array(
		'status'   => 0,
		'location' => '',
	);
	$die      = static function () use ( &$observed ) {
		return static function ( $message, $title = '', $arguments = array() ) use ( &$observed ): void {
			$observed['status'] = is_int( $arguments ) ? $arguments : (int) ( $arguments['response'] ?? 500 );
			throw new RuntimeException( 'rktsky_admin_native_stop' );
		};
	};
	$redirect = static function ( string $location, int $status ) use ( &$observed ): string {
		$observed = array(
			'status'   => $status,
			'location' => $location,
		);
		throw new RuntimeException( 'rktsky_admin_native_stop' );
	};
	add_filter( 'wp_die_handler', $die, PHP_INT_MAX );
	add_filter( 'wp_redirect', $redirect, PHP_INT_MAX, 2 );
	try {
		$operation();
	} catch ( RuntimeException $error ) {
		if ( 'rktsky_admin_native_stop' !== $error->getMessage() ) {
			throw $error;
		}
	} finally {
		remove_filter( 'wp_die_handler', $die, PHP_INT_MAX );
		remove_filter( 'wp_redirect', $redirect, PHP_INT_MAX );
	}
	return $observed;
}

/**
 * Checks that an action returns to the allowlisted local administration view.
 *
 * @since 0.4.0
 * @param array  $response Native redirect observation.
 * @param string $tab Required destination view.
 * @return void
 */
function rktsky_antispam_admin_redirect( array $response, string $tab ): void {
	$parts    = wp_parse_url( $response['location'] );
	$expected = wp_parse_url( admin_url( 'options-general.php' ) );
	parse_str( $parts['query'] ?? '', $query );
	rktsky_antispam_admin_expect( 302 === $response['status'] && ( $parts['host'] ?? '' ) === ( $expected['host'] ?? '' ) && ( $parts['path'] ?? '' ) === $expected['path'] && 'rocketsky-antispam' === ( $query['page'] ?? '' ) && $tab === ( $query['tab'] ?? '' ), 'An action redirect must return to its local plugin view.' );
}

$settings         = new Settings();
$store            = new Store();
$quarantine       = new Quarantine( $settings );
$admin            = new Admin( $settings, $store, $quarantine, new Feedback() );
$original         = get_option( Settings::OPTION, null );
$original_user    = get_current_user_id();
$original_get     = $_GET;
$original_post    = $_POST;
$original_request = $_REQUEST;
$original_server  = $_SERVER;
$original_errors  = $GLOBALS['wp_settings_errors'] ?? array();
$counter_snapshot = array();
$subscriber       = 0;
$administrator    = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
if ( empty( $administrator ) ) {
	throw new RuntimeException( 'A native administrator is required in the disposable fixture.' );
}
$administrator = (int) $administrator[0];
$settings->register();
$tabs   = array(
	'overview'     => 'Overview',
	'integrations' => 'Integrations',
	'rules'        => 'Filter rules',
	'quarantine'   => 'Review spam',
	'settings'     => 'Settings',
);
$groups = array(
	'integrations' => array_merge( Providers::ids(), array( 'minimum_seconds', 'allow_pingbacks' ) ),
	'rules'        => array( 'spam_keywords', 'bbcode_filter', 'spam_patterns', 'allowed_languages', 'unknown_language_allowed', 'country_mode', 'countries', 'country_unknown_allowed', 'country_source', 'country_header', 'trusted_proxies' ),
	'settings'     => array( 'privacy_mode', 'quarantine_enabled', 'quarantine_days', 'retention_days', 'comment_spam_days', 'notify_admin', 'delete_on_uninstall' ),
);

try {
	wp_set_current_user( $administrator );
	$subscriber = wp_insert_user(
		array(
			'user_login' => 'rktsky-admin-denied-' . wp_generate_password( 10, false, false ),
			'user_pass'  => wp_generate_password( 32, true, true ),
			'user_email' => 'admin-denied@example.invalid',
			'role'       => 'subscriber',
		)
	);
	if ( is_wp_error( $subscriber ) ) {
		throw new RuntimeException( 'The isolated low-privilege test account could not be created.' );
	}
	add_settings_error( Settings::OPTION, 'rktsky_admin_notice_test', 'rktsky_native_admin_notice_sentinel', 'success' );
	foreach ( $tabs as $tab => $label ) {
		$view_options = get_option( Settings::OPTION, null );
		$view         = rktsky_antispam_admin_view( $admin, $tab );
		$xpath        = $view['xpath'];
		$navigation   = $xpath->query( '//nav[contains(@class,"rktsky-antispam__tabs")]/a' );
		rktsky_antispam_admin_expect( 5 === $navigation->length, 'Every view must expose five ordinary navigation links.' );
		$seen = array();
		foreach ( $navigation as $link ) {
			parse_str( (string) wp_parse_url( $link->getAttribute( 'href' ), PHP_URL_QUERY ), $query );
			$key    = $query['tab'] ?? '';
			$seen[] = $key;
			rktsky_antispam_admin_expect( isset( $tabs[ $key ] ) && 'rocketsky-antispam' === ( $query['page'] ?? '' ), 'Navigation must use fixed native page and tab values.' );
			if ( 'page' === $link->getAttribute( 'aria-current' ) ) {
				rktsky_antispam_admin_expect( $tab === $key, 'The requested allowlisted view must be selected.' );
			}
		}
		rktsky_antispam_admin_expect( array_keys( $tabs ) === $seen && 1 === $xpath->query( '//nav//a[@aria-current="page"]' )->length, 'Tab order and selected navigation must be unambiguous.' );
		$forms        = rktsky_antispam_admin_forms( $view['html'] );
		$option_path  = wp_parse_url( admin_url( 'options.php' ), PHP_URL_PATH );
		$option_forms = array_values( array_filter( $forms, static fn( $form ) => $option_path === wp_parse_url( $form['action'], PHP_URL_PATH ) ) );
		rktsky_antispam_admin_expect( ( isset( $groups[ $tab ] ) ? 1 : 0 ) === count( $option_forms ), 'Only configuration views may contain a Settings API form.' );
		$actual = array();
		foreach ( $option_forms as $form ) {
			rktsky_antispam_admin_expect( 'post' === $form['method'] && 'rktsky_antispam' === ( $form['fields']['option_page'] ?? '' ) && isset( $form['fields']['_wpnonce'], $form['fields']['_wp_http_referer'] ), 'Settings need their native POST group, nonce and back route.' );
			parse_str( (string) wp_parse_url( $form['fields']['_wp_http_referer'], PHP_URL_QUERY ), $back );
			rktsky_antispam_admin_expect( $tab === ( $back['tab'] ?? '' ), 'Native Settings API back routes must retain the current view.' );
			foreach ( array_keys( $form['fields'] ) as $name ) {
				if ( 1 === preg_match( '/^' . preg_quote( Settings::OPTION, '/' ) . '\[([a-z0-9_]+)\](?:\[\])?$/D', $name, $match ) ) {
					$actual[ $match[1] ] = true;
				}
			}
		}
		$actual   = array_keys( $actual );
		$expected = $groups[ $tab ] ?? array();
		sort( $actual );
		sort( $expected );
		rktsky_antispam_admin_expect( $expected === $actual, 'Each view submits exactly its option group: ' . $tab );
		rktsky_antispam_admin_expect( ( 'settings' === $tab ? 1 : 0 ) === $xpath->query( '//input[@id="rktsky-privacy-mode"]' )->length, 'Only Settings may render the editable privacy choice.' );
		rktsky_antispam_admin_expect( ! str_contains( $view['html'], 'rktsky_native_admin_notice_sentinel' ), 'Templates must not duplicate native options-head notices.' );
		if ( isset( $groups[ $tab ] ) ) {
			rktsky_antispam_admin_expect( array() === $view['queries'], 'Configuration views must not load aggregate or quarantine repositories: ' . $tab );
		} elseif ( 'overview' === $tab ) {
			rktsky_antispam_admin_expect( ! empty( $view['queries']['stats'] ) && ! empty( $view['queries']['feedback'] ) && empty( $view['queries']['quarantine'] ), 'Overview loads activity and quality without quarantine contents.' );
			rktsky_antispam_admin_expect( 0 === $xpath->query( '//*[@id="rktsky-delivered-reference"]' )->length, 'Manual case review must not remain on Overview.' );
		} else {
			rktsky_antispam_admin_expect( ! empty( $view['queries']['quarantine'] ) && empty( $view['queries']['stats'] ) && empty( $view['queries']['feedback'] ), 'The quarantine list must not load overview aggregates.' );
			rktsky_antispam_admin_expect( 1 === $xpath->query( '//*[@id="rktsky-delivered-reference"]' )->length && 1 === $xpath->query( '//*[@id="rktsky-forget-reference"]' )->length, 'Classification and forgetting need distinct unique controls.' );
		}
		foreach ( array(
			'https://wordpress.org/support/plugin/rocketsky-antispam/reviews/#new-post',
			'https://ko-fi.com/rocketsky',
			'https://www.rocketsky.ch/',
			'https://www.speo.ch',
		) as $support_url ) {
			$links = $xpath->query( '//a[@href="' . $support_url . '"]' );
			rktsky_antispam_admin_expect( $links->length > 0, 'Each plugin view must expose its fixed, voluntary support or sponsor destination: ' . $support_url );
			foreach ( $links as $link ) {
				$rel = preg_split( '/\s+/', trim( $link->getAttribute( 'rel' ) ) );
				rktsky_antispam_admin_expect( '_blank' === $link->getAttribute( 'target' ) && in_array( 'noopener', $rel, true ) && in_array( 'noreferrer', $rel, true ), 'Support links must open safely in a new tab without a referrer: ' . $support_url );
				rktsky_antispam_admin_expect( 0 === $xpath->query( 'ancestor::form', $link )->length, 'Support and review links must not submit settings or gate a form: ' . $support_url );
			}
		}
		rktsky_antispam_admin_expect( 0 === $xpath->query( '//footer//form | //footer//input | //footer//select | //footer//textarea | //footer//iframe' )->length, 'Voluntary support must contain no submission form, embedded service or gating controls.' );
		rktsky_antispam_admin_expect( $view_options === get_option( Settings::OPTION, null ), 'Viewing support or sponsor links must leave all saved settings unchanged: ' . $tab );
	}

	// Model a legacy stored preference through Core's option API, before the
	// current sanitizer enforces privacy mode. Rendering must not rewrite it.
	$capture                       = $settings->defaults();
	$capture['privacy_mode']       = true;
	$capture['quarantine_enabled'] = true;
	remove_filter( 'sanitize_option_' . Settings::OPTION, array( $settings, 'sanitize' ) );
	try {
		update_option( Settings::OPTION, $capture, false );
	} finally {
		add_filter( 'sanitize_option_' . Settings::OPTION, array( $settings, 'sanitize' ) );
	}
	$stored_capture = get_option( Settings::OPTION );
	rktsky_antispam_admin_expect( ! empty( $stored_capture['privacy_mode'] ) && ! empty( $stored_capture['quarantine_enabled'] ), 'The privacy regression must retain an actual conflicting saved preference.' );
	foreach ( array( 'overview', 'quarantine' ) as $tab ) {
		$view = rktsky_antispam_admin_view( $admin, $tab );
		rktsky_antispam_admin_expect( __( 'Quarantine is off', 'rocketsky-antispam' ) === rktsky_antispam_admin_quarantine_status( $view, $tab ), 'Privacy mode must report capture off even when the raw saved checkbox is on: ' . $tab );
		rktsky_antispam_admin_expect( $stored_capture === get_option( Settings::OPTION ), 'Reading effective privacy state must not discard the stored quarantine preference: ' . $tab );
	}
	$capture['privacy_mode'] = false;
	update_option( Settings::OPTION, $capture, false );
	$stored_capture = get_option( Settings::OPTION );
	rktsky_antispam_admin_expect( $quarantine->encryption_ready() && $quarantine->enabled(), 'The original WordPress fixture must provide actual Sodium support for enabled-capture coverage.' );
	foreach ( array( 'overview', 'quarantine' ) as $tab ) {
		$view = rktsky_antispam_admin_view( $admin, $tab );
		rktsky_antispam_admin_expect( __( 'Quarantine is on', 'rocketsky-antispam' ) === rktsky_antispam_admin_quarantine_status( $view, $tab ), 'Enabled capture with real encryption must have the same visible status in both views: ' . $tab );
		rktsky_antispam_admin_expect( $stored_capture === get_option( Settings::OPTION ), 'Viewing enabled quarantine must not change its saved configuration: ' . $tab );
	}
	$without_quarantine = new Admin( $settings, $store, null, new Feedback() );
	$view               = rktsky_antispam_admin_view( $without_quarantine, 'overview' );
	rktsky_antispam_admin_expect( __( 'Encryption unavailable', 'rocketsky-antispam' ) === rktsky_antispam_admin_quarantine_status( $view, 'overview' ), 'Overview must not promise capture when the quarantine service is unavailable.' );
	rktsky_antispam_admin_expect( $stored_capture === get_option( Settings::OPTION ), 'Unavailable capture must preserve the saved enabled preference for a repaired runtime.' );
	foreach ( array( 'unknown', 'not-a-view', array( 'settings' ) ) as $invalid_tab ) {
		$view    = rktsky_antispam_admin_view( $admin, $invalid_tab );
		$current = $view['xpath']->query( '//nav//a[@aria-current="page"]' )->item( 0 );
		rktsky_antispam_admin_expect( null !== $current && str_contains( $current->getAttribute( 'href' ), 'tab=overview' ), 'Unknown or malformed tab values must select Overview.' );
	}
	foreach ( array( 0, $subscriber ) as $denied_user ) {
		wp_set_current_user( $denied_user );
		foreach ( array_keys( $tabs ) as $tab ) {
			$view = rktsky_antispam_admin_view( $admin, $tab );
			rktsky_antispam_admin_expect( '' === $view['html'] && array() === $view['queries'], 'Callers without manage_options must receive no private view or repository reads.' );
		}
	}
	wp_set_current_user( $administrator );
	$links = $admin->action_links( array() );
	rktsky_antispam_admin_expect( 1 === count( $links ) && str_contains( $links[0], 'tab=settings' ), 'The Plugins Settings action must open global Settings.' );

	$base                      = $settings->defaults();
	$base['privacy_mode']      = false;
	$base['spam_keywords']     = 'synthetic-admin-preserved';
	$base['allowed_languages'] = array( 'de' );
	$base['quarantine_days']   = 9;
	$base['retention_days']    = 41;
	$base['divi']              = true;
	update_option( Settings::OPTION, $base, false );
	foreach ( array(
		'integrations' => array(
			'minimum_seconds' => '2',
			'comments'        => '0',
		),
		'rules'        => array(
			'spam_keywords' => 'synthetic-admin-updated',
			'bbcode_filter' => '1',
		),
		'settings'     => array(
			'retention_days'      => '42',
			'delete_on_uninstall' => '0',
		),
	) as $tab => $payload ) {
		$before = $settings->all();
		update_option( Settings::OPTION, $payload, false );
		$after = get_option( Settings::OPTION );
		foreach ( array_diff( array_keys( $before ), array_keys( $payload ) ) as $key ) {
			rktsky_antispam_admin_expect( $before[ $key ] === $after[ $key ], 'Native sanitization must preserve omitted values in ' . $tab . ': ' . $key );
		}
		rktsky_antispam_admin_expect( $before !== $after, 'Native Settings API persistence must save the submitted changes: ' . $tab );
	}

	$actions = array(
		'clear'              => 'rktsky_antispam_clear',
		'classify_delivered' => 'rktsky_antispam_classify_delivered',
		'delete_quarantine'  => 'rktsky_antispam_quarantine_delete_999999999',
		'review'             => 'rktsky_antispam_review_999999999',
	);
	foreach ( $actions as $method => $nonce_action ) {
		$_POST                     = array(
			'entry'      => '999999999',
			'provider'   => 'unknown',
			'reference'  => 'synthetic-admin-route',
			'assessment' => 'spam',
		);
		$_REQUEST                  = $_POST;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		wp_set_current_user( 0 );
		$response = rktsky_antispam_admin_response( array( $admin, $method ) );
		rktsky_antispam_admin_expect( 403 === $response['status'], 'Mutations reject missing capabilities: ' . $method );
		wp_set_current_user( $subscriber );
		$response = rktsky_antispam_admin_response( array( $admin, $method ) );
		rktsky_antispam_admin_expect( 403 === $response['status'], 'Mutations reject an authenticated subscriber: ' . $method );
		wp_set_current_user( $administrator );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$response                  = rktsky_antispam_admin_response( array( $admin, $method ) );
		rktsky_antispam_admin_expect( 405 === $response['status'], 'Mutations require POST: ' . $method );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['_wpnonce']         = 'invalid';
		$_REQUEST                  = $_POST;
		$response                  = rktsky_antispam_admin_response( array( $admin, $method ) );
		rktsky_antispam_admin_expect( 403 === $response['status'], 'Mutations retain native nonce checks: ' . $method );
		if ( 'clear' !== $method ) {
			rktsky_antispam_admin_expect( null === $quarantine->read( 999999999 ), 'The reserved absent-record ID must not identify stored data.' );
			$_POST['_wpnonce'] = wp_create_nonce( $nonce_action );
			$_REQUEST          = $_POST;
			rktsky_antispam_admin_redirect( rktsky_antispam_admin_response( array( $admin, $method ) ), 'quarantine' );
		}
	}
	global $wpdb;
	foreach ( array( 'stats', 'totals' ) as $suffix ) {
		$table = $wpdb->prefix . 'rktsky_antispam_' . $suffix;
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i LIMIT 10001', $table ), ARRAY_A );
		rktsky_antispam_admin_expect( is_array( $rows ) && count( $rows ) <= 10000, 'The isolated counter snapshot must remain bounded.' );
		$counter_snapshot[ $table ] = $rows;
	}
	$_POST    = array( '_wpnonce' => wp_create_nonce( 'rktsky_antispam_clear' ) );
	$_REQUEST = $_POST;
	rktsky_antispam_admin_redirect( rktsky_antispam_admin_response( array( $admin, 'clear' ) ), 'settings' );
	rktsky_antispam_admin_expect( 0 === $store->summary()['blocked'] && 0 === $store->blocked_comments(), 'The valid clear action must delete its actual owned counters.' );
} finally {
	remove_filter( 'sanitize_option_' . Settings::OPTION, array( $settings, 'sanitize' ) );
	if ( null === $original ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original, false ); }
	foreach ( $counter_snapshot as $table => $rows ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
		foreach ( $rows as $row ) {
			if ( false === $wpdb->insert( $table, $row ) ) {
				throw new RuntimeException( 'An isolated counter snapshot could not be restored.' ); }
		}
	}
	if ( is_int( $subscriber ) && $subscriber > 0 ) {
		wp_delete_user( $subscriber ); }
	wp_set_current_user( $original_user );
	$_GET                          = $original_get;
	$_POST                         = $original_post;
	$_REQUEST                      = $original_request;
	$_SERVER                       = $original_server;
	$GLOBALS['wp_settings_errors'] = $original_errors;
}
rktsky_antispam_admin_expect( $original === get_option( Settings::OPTION, null ), 'Original site settings must be restored exactly.' );
echo 'PASS: ' . $rktsky_admin_assertions . " native administrator routing, form, settings and authorization assertions.\n";
