<?php
/**
 * Verifies real Core/GF spam persistence, technical blocking and native moderation.
 *
 * Cases execute in fresh marked-site PHP processes. Original WordPress and GF
 * functions, validation, notes, notifications and feed processing remain active.
 * The fixture blocks actual mail and HTTP. Core/provider records use their APIs;
 * prepared SQL snapshots are restricted to the six approved owned tables.
 *
 * @package RktskyAntispamTests
 * @since 0.5.0
 */

use Rktsky\Antispam\{Settings, Store, Token, Feedback, Quarantine};

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$GLOBALS['rktsky_routing_assertions'] = 0;
set_exception_handler(
	static function ( Throwable $error ): void {
		fwrite( STDERR, $error->getMessage() . "\n" );
		exit( 1 );
	}
);

/**
 * Fails at an observed native boundary without dumping any submission contents.
 *
 * @since 0.5.0
 * @param bool   $condition Expected native invariant.
 * @param string $message Failure context.
 * @return void
 */
function rktsky_routing_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_routing_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
}

/**
 * Snapshots only an approved bounded owned table through native wpdb.
 *
 * @since 0.5.0
 * @param string $suffix Fixed owned-table suffix.
 * @return array Original rows for exact restoration.
 */
function rktsky_routing_snapshot( string $suffix ): array {
	global $wpdb;
	if ( ! in_array( $suffix, array( 'tokens', 'stats', 'totals', 'quarantine', 'feedback', 'jobs' ), true ) ) {
		throw new RuntimeException( 'Unknown owned snapshot table.' ); }
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i LIMIT 10001', $wpdb->prefix . 'rktsky_antispam_' . $suffix ), ARRAY_A );
	if ( ! is_array( $rows ) || count( $rows ) > 10000 ) {
		throw new RuntimeException( 'Owned fixture snapshot exceeds its bound.' ); }
	return $rows;
}

/**
 * Restores one approved fixture table; Core/provider data never uses this helper.
 *
 * @since 0.5.0
 * @param string $suffix Fixed approved suffix.
 * @param array  $rows Previously captured rows.
 * @return void
 */
function rktsky_routing_restore( string $suffix, array $rows ): void {
	global $wpdb;
	if ( ! in_array( $suffix, array( 'tokens', 'stats', 'totals', 'quarantine', 'feedback', 'jobs' ), true ) ) {
		throw new RuntimeException( 'Unknown owned restore table.' ); }
	$table = $wpdb->prefix . 'rktsky_antispam_' . $suffix;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	foreach ( $rows as $row ) {
		if ( false === $wpdb->insert( $table, $row ) ) {
			throw new RuntimeException( 'An owned fixture snapshot could not be restored.' ); }
	}
}

/**
 * Issues actual local proof with an independently selected expected native scope.
 *
 * @since 0.5.0
 * @param string $provider Native integration ID.
 * @param string $scope Native form/post ID or dedicated resume scope.
 * @param bool   $trap Whether to fill the dynamically signed trap.
 * @return array Own technical controls only.
 */
function rktsky_routing_proof( string $provider, string $scope, bool $trap = false ): array {
	$proof = ( new Token() )->issue( $provider, $scope );
	return array(
		'rktsky_antispam_token' => $proof['token'],
		$proof['trap']          => $trap ? 'synthetic-bot' : '',
	);
}

/**
 * Reads real native controls, retaining the provider's independent security state.
 *
 * @since 0.5.0
 * @param string $html Original rendered form.
 * @return array Native scalar input controls.
 */
function rktsky_routing_controls( string $html ): array {
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$document->loadHTML( $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$values = array();
	foreach ( ( new DOMXPath( $document ) )->query( '//form//input' ) as $field ) {
		$name = $field->getAttribute( 'name' );
		if ( '' !== $name && ! in_array( $field->getAttribute( 'type' ), array( 'submit', 'button' ), true ) ) {
			$values[ $name ] = $field->getAttribute( 'value' ); }
	}
	return $values;
}

/**
 * Returns actual active/spam/trash counts through the documented GFAPI.
 *
 * @since 0.5.0
 * @param int $form Native synthetic form ID.
 * @return int Total persisted native entries, including spam.
 */
function rktsky_routing_gf_count( int $form ): int {
	return GFAPI::count_entries( $form, array( 'status' => 'active' ) ) + GFAPI::count_entries( $form, array( 'status' => 'spam' ) ) + GFAPI::count_entries( $form, array( 'status' => 'trash' ) );
}

/**
 * Creates an independent native form, notification and original-framework feed.
 *
 * @since 0.5.0
 * @param bool $multi Whether to include a genuine native page break.
 * @param bool $double_resume Whether the native send-link event has two notifications.
 * @return int Native form ID.
 */
function rktsky_routing_gf_form( bool $multi = false, bool $double_resume = false ): int {
	$fields = array(
		new GF_Field_Text(
			array(
				'id'         => 1,
				'label'      => 'Message',
				'isRequired' => true,
				'pageNumber' => 1,
			)
		),
		new GF_Field_Email(
			array(
				'id'         => 2,
				'label'      => 'Email',
				'pageNumber' => 1,
			)
		),
		new GF_Field_Hidden(
			array(
				'id'         => 3,
				'label'      => 'Hidden',
				'pageNumber' => 1,
			)
		),
	);
	if ( $multi ) {
		$fields[] = new GF_Field_Page(
			array(
				'id'             => 4,
				'pageNumber'     => 2,
				'nextButton'     => array(
					'type' => 'text',
					'text' => 'Next',
				),
				'previousButton' => array(
					'type' => 'text',
					'text' => 'Previous',
				),
			)
		);
		$fields[] = new GF_Field_Text(
			array(
				'id'         => 5,
				'label'      => 'Second page',
				'isRequired' => true,
				'pageNumber' => 2,
			)
		); }
	$notifications = array();
	foreach ( array(
		'submission' => 'form_submission',
		'resume'     => 'form_save_email_requested',
	) + ( $double_resume ? array( 'resume2' => 'form_save_email_requested' ) : array() ) as $key => $event ) {
		$notifications[ $key ] = array(
			'id'       => $key,
			'name'     => 'Synthetic ' . $key,
			'event'    => $event,
			'toType'   => 'form_submission' === $event ? 'email' : 'hidden',
			'to'       => 'fixture@example.invalid',
			'subject'  => 'Synthetic routing',
			'message'  => 'Synthetic notification.',
			'isActive' => true,
			'from'     => 'fixture@example.invalid',
		);
	}
	$id = GFAPI::add_form(
		array(
			'title'          => 'Synthetic native spam routing',
			'fields'         => $fields,
			'button'         => array(
				'type' => 'text',
				'text' => 'Submit synthetic',
			),
			'pagination'     => $multi ? array(
				'type'           => 'steps',
				'pages'          => array( 'First', 'Second' ),
				'lastPageButton' => array(
					'type' => 'text',
					'text' => 'Previous',
				),
			) : array(),
			'save'           => array(
				'enabled' => true,
				'button'  => array( 'text' => 'Save synthetic draft' ),
			),
			'confirmations'  => array(
				array(
					'id'        => 'synthetic',
					'name'      => 'Default',
					'isDefault' => true,
					'event'     => '',
					'type'      => 'redirect',
					'url'       => 'https://example.invalid/synthetic-success',
				),
				array(
					'id'        => 'synthetic_save',
					'name'      => 'Save',
					'isDefault' => true,
					'event'     => 'form_saved',
					'type'      => 'message',
					'message'   => 'Synthetic draft saved. {save_link} {save_email_input}',
				),
				array(
					'id'        => 'synthetic_sent',
					'name'      => 'Sent',
					'isDefault' => true,
					'event'     => 'form_save_email_sent',
					'type'      => 'message',
					'message'   => 'Synthetic resume link sent.',
				),
			),
			'notifications'  => $notifications,
			'enableHoneypot' => true,
			'personalData'   => array( 'preventIP' => true ),
		)
	);
	rktsky_routing_expect( ! is_wp_error( $id ) && $id > 0, 'A dedicated native form must be created.' );
	$feed = GFAPI::add_feed( $id, array( 'feedName' => 'Synthetic local feed' ), 'rktsky-spam-routing-test' );
	rktsky_routing_expect( ! is_wp_error( $feed ) && $feed > 0, 'The original feed framework must accept the owned synthetic feed.' );
	return (int) $id;
}

/**
 * Runs the unchanged final/web/API/draft submission engine with real controls.
 *
 * @since 0.5.0
 * @param int   $form Native form ID.
 * @param array $posted Original controls plus explicitly selected input/proof changes.
 * @param int   $target Native destination page (zero submits).
 * @param int   $source Native source page.
 * @param bool  $api Whether to use the native API submission context.
 * @return array|WP_Error Native submission result.
 */
function rktsky_routing_gf_submit( int $form, array $posted, int $target = 0, int $source = 1, bool $api = false ) {
	$_POST                     = $posted;
	$_SERVER['REQUEST_METHOD'] = 'POST';
	return GFAPI::submit_form( $form, array(), array(), $target, $source, $api ? GFFormDisplay::SUBMISSION_INITIATED_BY_API : GFFormDisplay::SUBMISSION_INITIATED_BY_WEBFORM );
}

$case     = $argv[1] ?? '';
$settings = new Settings();
$store    = new Store();
if ( '' === $case ) {
	$original   = get_option( Settings::OPTION, null );
	$schema     = get_option( 'rktsky_antispam_schema', null );
	$options    = array();
	$snapshots  = array();
	$subscriber = 0;
	foreach ( array( 'comments_notify', 'moderation_notify' ) as $key ) {
		$options[ $key ] = get_option( $key, null ); }
	try {
		foreach ( array( 'tokens', 'stats', 'totals', 'quarantine', 'feedback', 'jobs' ) as $suffix ) {
			$snapshots[ $suffix ] = rktsky_routing_snapshot( $suffix ); }
		$enabled                 = $settings->all();
		$enabled['comments']     = true;
		$enabled['gravityforms'] = true;
		update_option( Settings::OPTION, $enabled, false );
		update_option( 'comments_notify', 1 );
		update_option( 'moderation_notify', 1 );
		$subscriber = wp_insert_user(
			array(
				'user_login' => 'routing-' . wp_generate_password( 12, false, false ),
				'user_pass'  => wp_generate_password( 32, true ),
				'role'       => 'subscriber',
				'user_email' => 'routing-' . wp_generate_password( 10, false, false ) . '@example.invalid',
			)
		);
		rktsky_routing_expect( ! is_wp_error( $subscriber ), 'The native REST fixture account must be created.' );
		putenv( 'WP_ANTISPAM_ROUTING_USER=' . $subscriber );
		putenv( 'WP_ANTISPAM_ROUTING_CHILD=1' );
		$cases = array( 'comment_valid', 'comment_keyword', 'comment_bbcode', 'comment_pattern', 'comment_language', 'comment_country', 'rest_keyword', 'rest_bbcode', 'rest_pattern', 'rest_language', 'rest_country', 'comment_missing', 'comment_trap', 'comment_wrong_scope', 'comment_replay', 'comment_storage', 'comment_pattern_limit', 'rest_missing', 'rest_trap', 'rest_wrong_scope', 'rest_replay', 'rest_storage', 'comment_keyword_q', 'rest_keyword_q', 'comment_missing_q', 'rest_missing_q', 'comment_unspam', 'rest_unspam' );
		if ( class_exists( 'GFAPI' ) ) {
			$cases = array_merge( $cases, array( 'gf_valid', 'gf_keyword', 'gf_bbcode', 'gf_pattern', 'gf_keyword_q', 'gf_keyword_async', 'gf_missing', 'gf_trap', 'gf_wrong_scope', 'gf_replay', 'gf_storage', 'gf_pattern_limit', 'gf_missing_q', 'gf_unspam', 'gf_prior_spam', 'gf_multi_next', 'gf_multi_content', 'gf_multi_final_spam', 'gf_save_normal', 'gf_save_content', 'gf_save_missing', 'gf_api_validate_content', 'gf_api_validate_then_submit', 'gf_api_final_spam', 'gf_same_request_replay_content', 'gf_same_request_cached_false', 'gf_resume_valid', 'gf_resume_missing', 'gf_resume_trap', 'gf_resume_wrong_scope', 'gf_resume_replay', 'gf_resume_bad_email', 'gf_resume_multiple', 'gf_resume_storage', 'gf_resume_fallback_missing', 'gf_resume_fallback_valid' ) );
		} else {
			echo "SKIP: native Gravity Forms routing requires its licensed unchanged package.\n"; }
		foreach ( $cases as $name ) {
			$command = array( PHP_BINARY );
			foreach ( array( 'WP_ANTISPAM_PHP_ICONV', 'WP_ANTISPAM_PHP_SIMPLEXML' ) as $extension ) {
				if ( getenv( $extension ) ) {
					$command[] = '-d';
					$command[] = 'extension=' . getenv( $extension ); }
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
				throw new RuntimeException( 'Native routing failed: ' . $name . "\n" . $output . "\n" . $error ); }
			echo trim( $output ) . "\n";
		}
		echo 'PASS: ' . count( $cases ) . " original Core/GF routing cases.\n";
	} finally {
		if ( is_int( $subscriber ) && $subscriber > 0 ) {
			wp_delete_user( $subscriber ); }
		foreach ( $snapshots as $suffix => $rows ) {
			rktsky_routing_restore( $suffix, $rows ); }
		foreach ( $options + array(
			Settings::OPTION         => $original,
			'rktsky_antispam_schema' => $schema,
		) as $key => $value ) {
			if ( null === $value ) {
				delete_option( $key );
			} else {
				update_option( $key, $value, false ); }
		}
		putenv( 'WP_ANTISPAM_ROUTING_USER' );
		putenv( 'WP_ANTISPAM_ROUTING_CHILD' );
	}
	return;
}

$administrator                        = (int) get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
)[0];
$GLOBALS['rktsky_routing_mail_calls'] = 0;
$GLOBALS['rktsky_routing_feed_calls'] = 0;
$GLOBALS['rktsky_routing_entries']    = array();
add_filter(
	'pre_wp_mail',
	static function ( $previous ) {
		++$GLOBALS['rktsky_routing_mail_calls'];
		return $previous;
	},
	-PHP_INT_MAX
);
$child_original       = get_option( Settings::OPTION, null );
$standalone_snapshots = array();
if ( '1' !== getenv( 'WP_ANTISPAM_ROUTING_CHILD' ) ) {
	foreach ( array( 'tokens', 'stats', 'totals', 'quarantine', 'feedback', 'jobs' ) as $suffix ) {
		$standalone_snapshots[ $suffix ] = rktsky_routing_snapshot( $suffix ); }
}
$enabled                       = $settings->defaults();
$enabled['comments']           = true;
$enabled['gravityforms']       = true;
$enabled['privacy_mode']       = ! str_ends_with( $case, '_q' ) && ! str_contains( $case, 'country' );
$enabled['quarantine_enabled'] = str_ends_with( $case, '_q' );
$kind                          = str_contains( $case, 'keyword' ) || str_contains( $case, 'unspam' ) || str_contains( $case, '_content' ) || str_contains( $case, 'final_spam' ) || str_contains( $case, 'cached_false' ) ? 'keyword' : ( str_contains( $case, 'bbcode' ) ? 'bbcode' : ( str_contains( $case, 'pattern_limit' ) ? 'pattern_limit' : ( str_contains( $case, 'pattern' ) ? 'pattern' : ( str_contains( $case, 'language' ) ? 'language' : ( str_contains( $case, 'country' ) ? 'country' : '' ) ) ) ) );
$text                          = 'Synthetic ordinary message.';
if ( 'keyword' === $kind ) {
	$enabled['spam_keywords'] = 'routing-keyword';
	$text                     = 'Synthetic routing-keyword message.'; }
if ( 'bbcode' === $kind ) {
	$enabled['bbcode_filter'] = true;
	$text                     = '[url=https://example.invalid]Synthetic link[/url]'; }
if ( 'pattern' === $kind ) {
	$enabled['spam_patterns'] = 'routing-pattern-[0-9]+';
	$text                     = 'Synthetic routing-pattern-42 message.'; }
if ( 'pattern_limit' === $kind ) {
	$enabled['spam_patterns'] = '(a+)+$';
	$text                     = str_repeat( 'a', 3000 ) . '!'; }
if ( 'language' === $kind ) {
	$enabled['allowed_languages']        = array( 'de' );
	$enabled['unknown_language_allowed'] = false;
	$text                                = 'The explanation is clear and we have used these details with our team. This approach should work because the documentation describes what happens when the form is submitted.'; }
if ( 'country' === $kind ) {
	$enabled['country_mode']    = 'allow';
	$enabled['countries']       = array( 'CH' );
	$enabled['country_source']  = 'trusted_header';
	$enabled['country_header']  = 'HTTP_X_COUNTRY_CODE';
	$enabled['trusted_proxies'] = '192.0.2.0/24'; }
update_option( Settings::OPTION, $enabled, false );
$_SERVER['REMOTE_ADDR']         = '192.0.2.' . ( 1 + random_int( 1, 240 ) );
$_SERVER['HTTP_X_COUNTRY_CODE'] = 'DE';
$_SERVER['HTTP_USER_AGENT']     = 'Synthetic native routing browser';
$feedback                       = new Feedback();
$quarantine                     = new Quarantine( $settings );
wp_set_current_user( $administrator );
$before_q = $quarantine->count();
wp_set_current_user( 0 );
$post            = 0;
$form            = 0;
$draft           = '';
$tokens_snapshot = null;
$schema          = get_option( 'rktsky_antispam_schema' );
$before          = $store->summary();
try {
	if ( ! str_starts_with( $case, 'gf_' ) ) {
		$post = wp_insert_post(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'post_author'    => $administrator,
				'post_title'     => 'Synthetic native spam routing',
				'comment_status' => 'open',
			)
		);
		rktsky_routing_expect( is_int( $post ) && $post > 0, 'A dedicated native comment target must exist.' );
		$rest        = str_starts_with( $case, 'rest_' );
		$proof       = str_contains( $case, '_missing' ) ? array() : rktsky_routing_proof( 'comments', (string) $post . ( str_contains( $case, 'wrong_scope' ) ? '_wrong' : '' ), str_contains( $case, '_trap' ) );
		$native_data = array(
			'comment_post_ID'      => $post,
			'comment_author'       => 'Synthetic routing visitor',
			'comment_author_email' => 'routing-' . wp_generate_password( 12, false, false ) . '@example.invalid',
			'comment_author_url'   => '',
			'comment_content'      => $text,
			'comment_type'         => 'comment',
			'user_id'              => 0,
		);
		add_filter(
			'wp_die_handler',
			static function () {
				return static function ( $message, $title = '', $arguments = array() ): void {
					throw new RuntimeException( 'native-comment-stop', is_int( $arguments ) ? $arguments : (int) ( $arguments['response'] ?? 500 ) );
				};
			}
		);
		$submit = static function ( array $controls ) use ( $rest, $post, &$native_data ) {
			if ( $rest ) {
				wp_set_current_user( (int) getenv( 'WP_ANTISPAM_ROUTING_USER' ) );
				$_POST   = array();
				$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
				$request->set_body_params(
					array(
						'post'         => $post,
						'author_name'  => $native_data['comment_author'],
						'author_email' => $native_data['comment_author_email'],
						'content'      => $native_data['comment_content'],
					) + $controls
				);
				return rest_do_request( $request );
			}
			wp_set_current_user( 0 );
			$_POST = $controls;
			return wp_new_comment( wp_slash( $native_data ), true );
		};
		$prime  = null;
		if ( str_contains( $case, 'replay' ) ) {
			$prime = $submit( $proof );
			rktsky_routing_expect( ! is_wp_error( $prime ), 'The replay prime must pass the original comment pipeline.' );
			$before                               = $store->summary();
			$GLOBALS['rktsky_routing_mail_calls'] = 0;
			$native_data['comment_content']       = 'A changed replay must still fail its technical proof.';
			$native_data['comment_author_email']  = 'replay-' . wp_generate_password( 12, false, false ) . '@example.invalid';
			$_SERVER['REMOTE_ADDR']               = '198.51.100.42';
		}
		if ( str_contains( $case, 'storage' ) ) {
			$tokens_snapshot = rktsky_routing_snapshot( 'tokens' );
			$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $wpdb->prefix . 'rktsky_antispam_tokens' ) );
		}
		$stopped  = false;
		$response = null;
		try {
			$response = $submit( $proof );
		} catch ( RuntimeException $error ) {
			if ( 'native-comment-stop' !== $error->getMessage() ) {
				throw $error;
			} $stopped = true;
			rktsky_routing_expect( 403 === $error->getCode(), 'A technical classic rejection must keep its native HTTP403 boundary.' ); }
		$technical = str_contains( $case, 'missing' ) || str_contains( $case, '_trap' ) || str_contains( $case, 'wrong_scope' ) || str_contains( $case, 'replay' ) || str_contains( $case, 'storage' ) || 'pattern_limit' === $kind;
		$counts    = wp_count_comments( $post );
		$total     = (int) $counts->approved + (int) $counts->moderated + (int) $counts->spam + (int) $counts->trash;
		$prior     = str_contains( $case, 'replay' ) ? 1 : 0;
		if ( $technical ) {
			rktsky_routing_expect( $total === $prior && 0 === $GLOBALS['rktsky_routing_mail_calls'], 'Technical rejection must create no native comment or mail attempt.' );
			rktsky_routing_expect( $rest ? ( $response instanceof WP_REST_Response && 403 === $response->get_status() ) : $stopped, 'The original technical rejection response must remain visible.' );
		} elseif ( '' !== $kind ) {
			rktsky_routing_expect( 1 === $total && 1 === (int) $counts->spam && 0 === $GLOBALS['rktsky_routing_mail_calls'], 'Verified local content must persist once as native Core spam without notifications.' );
			$ids = get_comments(
				array(
					'post_id' => $post,
					'status'  => 'spam',
					'fields'  => 'ids',
				)
			);
			$id  = (int) $ids[0];
			wp_set_current_user( $administrator );
			$status = $feedback->status( 'comments', $id );
			rktsky_routing_expect( is_array( $status ) && $status['predicted_spam'] && ! $status['reviewed'], 'Native incoming spam must start as one unreviewed prediction.' );
			if ( str_contains( $case, 'unspam' ) ) {
				wp_set_comment_status( $id, 'approve' );
				$review = $feedback->status( 'comments', $id );
				rktsky_routing_expect( $review['reviewed'] && false === $review['actual_spam'] && '1' === get_comment( $id )->comment_approved, 'Native approved moderation must label the original spam prediction as a false positive.' ); }
		} else {
			rktsky_routing_expect( 1 === $total && 0 === (int) $counts->spam && $GLOBALS['rktsky_routing_mail_calls'] > 0, 'The ordinary native comment route must remain available with observable notification processing.' );
		}
		$after = $store->summary();
		if ( $technical || '' !== $kind ) {
			rktsky_routing_expect( 1 === $after['blocked'] - $before['blocked'] && 0 === $after['accepted'] - $before['accepted'], 'A rejection must count once with no accepted counter.' ); }
		wp_set_current_user( $administrator );
		rktsky_routing_expect( $before_q + ( $technical && str_ends_with( $case, '_q' ) ? 1 : 0 ) === $quarantine->count(), 'Persisted native comment spam must never receive a second WP quarantine copy.' );
	} else {
		GFForms::include_feed_addon_framework();
		require __DIR__ . '/fixtures/spam-routing-feed.php';
		GFAddOn::register( 'RktskyAntispamRoutingFeed' );
		RktskyAntispamRoutingFeed::get_instance()->init();
		$multi = str_contains( $case, '_multi_' );
		$form  = rktsky_routing_gf_form( $multi, 'gf_resume_multiple' === $case );
		// Keep the owned fixture's native notifications synchronous so mail attempts
		// are observable before cleanup; this changes scheduling, not spam checks.
		if ( 'gf_keyword_async' !== $case ) {
			add_filter( 'gform_is_asynchronous_notifications_enabled_' . $form, '__return_false' ); }
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$posted                    = rktsky_routing_controls( GFFormDisplay::get_form( $form, false, false, false, null, true ) );
		$posted['input_1']         = $text;
		$posted['input_2']         = 'fixture@example.invalid';
		$posted['version_hash']    = wp_hash( GFForms::$version );
		if ( $multi ) {
			$posted['input_5'] = 'Synthetic second page.'; }
		$proof  = str_contains( $case, 'missing' ) ? array() : rktsky_routing_proof( 'gravityforms', (string) $form . ( str_contains( $case, 'wrong_scope' ) ? '_wrong' : '' ), str_contains( $case, '_trap' ) );
		$posted = array_replace( $posted, $proof );
		if ( 'gf_prior_spam' === $case ) {
			add_filter(
				'gform_entry_is_spam',
				static function ( $spam, $native_form ) use ( $form ) {
					if ( (int) $native_form['id'] === $form ) {
						GFCommon::set_spam_filter( $form, 'Synthetic previous filter', 'Earlier native reason.' );
						return true;
					} return $spam;
				},
				10,
				2
			); }
		if ( str_contains( $case, 'storage' ) && ! str_contains( $case, '_resume_' ) ) {
			$tokens_snapshot = rktsky_routing_snapshot( 'tokens' );
			$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $wpdb->prefix . 'rktsky_antispam_tokens' ) ); }
		if ( str_contains( $case, '_resume_' ) ) {
			$posted               = array_replace( $posted, rktsky_routing_proof( 'gravityforms', (string) $form ) );
			$posted['input_1']    = 'Synthetic ordinary draft.';
			$posted['gform_save'] = '1';
			$saved                = rktsky_routing_gf_submit( $form, $posted );
			$draft                = $saved['resume_token'] ?? '';
			rktsky_routing_expect( '' !== $draft && 0 === rktsky_routing_gf_count( $form ), 'The real protected Save-and-Continue step must create a draft rather than an entry.' );
			$html = GFFormDisplay::get_form( $form, false, false, false, null, true );
			rktsky_routing_expect( str_contains( $html, 'data-rk-antispam-form="' . $form . '_resume"' ), 'The original resume-email form must receive its dedicated signed host.' );
			$_POST                       = rktsky_routing_controls( $html );
			$_POST['gform_resume_email'] = 'gf_resume_bad_email' === $case ? 'invalid-email' : 'fixture@example.invalid';
			if ( ! str_contains( $case, 'missing' ) ) {
				$_POST = array_replace( $_POST, rktsky_routing_proof( 'gravityforms', (string) $form . ( 'gf_resume_wrong_scope' === $case ? '' : '_resume' ), 'gf_resume_trap' === $case ) ); }
			$_SERVER['REQUEST_METHOD']            = 'POST';
			$GLOBALS['rktsky_routing_mail_calls'] = 0;
			$before                               = $store->summary();
			if ( 'gf_resume_storage' === $case ) {
				$tokens_snapshot = rktsky_routing_snapshot( 'tokens' );
				$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $wpdb->prefix . 'rktsky_antispam_tokens' ) ); }
			if ( str_contains( $case, '_fallback_' ) ) {
				$native = GFAPI::get_form( $form );
				GFAPI::send_notification( $native['notifications']['resume'], $native, array( 'form_id' => $form ) );
			} else {
				GFFormDisplay::process_send_resume_link(); }
			$valid = in_array( $case, array( 'gf_resume_valid', 'gf_resume_replay', 'gf_resume_multiple', 'gf_resume_fallback_valid' ), true );
			rktsky_routing_expect( ( $valid ? ( 'gf_resume_multiple' === $case ? 2 : 1 ) : 0 ) === $GLOBALS['rktsky_routing_mail_calls'] && 0 === $GLOBALS['rktsky_routing_feed_calls'], 'Resume-mail protection must run before native mail without invoking entry feeds.' );
			if ( 'gf_resume_bad_email' === $case ) {
				$_POST['gform_resume_email'] = 'fixture@example.invalid';
				GFFormDisplay::process_send_resume_link();
				rktsky_routing_expect( 1 === $GLOBALS['rktsky_routing_mail_calls'], 'A native email error must leave unused proof available for corrected recipient input.' ); }
			if ( 'gf_resume_replay' === $case ) {
				$sent = $GLOBALS['rktsky_routing_mail_calls'];
				GFFormDisplay::process_send_resume_link();
				rktsky_routing_expect( $sent === $GLOBALS['rktsky_routing_mail_calls'], 'Reusing the same resume-mail proof must not dispatch another notification.' ); }
		} else {
			if ( str_contains( $case, '_save_' ) ) {
				$posted['gform_save'] = '1'; }
			if ( 'gf_api_validate_content' === $case || 'gf_api_validate_then_submit' === $case || 'gf_same_request_cached_false' === $case ) {
				$_POST = $posted;
				if ( 'gf_same_request_cached_false' === $case ) {
					$_POST['input_1'] = 'Synthetic ordinary preflight.'; }
				$validated = GFAPI::validate_form( $form );
				if ( 'gf_api_validate_content' === $case ) {
					rktsky_routing_expect( empty( $validated['is_valid'] ) && 0 === rktsky_routing_gf_count( $form ), 'Content-matching API-only validation must not grant native persistence.' ); }
				if ( 'gf_same_request_cached_false' === $case ) {
					$posted = array_replace( $posted, rktsky_routing_proof( 'gravityforms', (string) $form ) ); }
			}
			if ( 'gf_api_validate_content' !== $case ) {
				if ( 'gf_replay' === $case ) {
					$first = rktsky_routing_gf_submit( $form, $posted );
					rktsky_routing_expect( ! empty( $first['entry_id'] ), 'The native replay prime must create one entry.' );
					$before                               = $store->summary();
					$GLOBALS['rktsky_routing_mail_calls'] = 0;
					$GLOBALS['rktsky_routing_feed_calls'] = 0; }
				$native_processor = GFForms::get_service_container()->get( \Gravity_Forms\Gravity_Forms\Async\GF_Background_Process_Service_Provider::NOTIFICATIONS );
				$queued_before    = count( $native_processor->get_batches( 100 ) );
				$result           = rktsky_routing_gf_submit( $form, $posted, in_array( $case, array( 'gf_multi_next', 'gf_multi_content' ), true ) ? 2 : 0, 'gf_multi_final_spam' === $case ? 2 : 1, 'gf_api_final_spam' === $case );
				rktsky_routing_expect( ! is_wp_error( $result ), 'The original GF engine must return a native result.' );
				$technical     = str_contains( $case, 'missing' ) || str_contains( $case, '_trap' ) || str_contains( $case, 'wrong_scope' ) || 'gf_replay' === $case || str_contains( $case, 'storage' ) || 'pattern_limit' === $kind || 'gf_api_validate_then_submit' === $case;
				$early_content = in_array( $case, array( 'gf_multi_content', 'gf_save_content', 'gf_same_request_cached_false' ), true );
				if ( $technical || $early_content ) {
					rktsky_routing_expect( ( 'gf_replay' === $case ? 1 : 0 ) === rktsky_routing_gf_count( $form ) && 0 === $GLOBALS['rktsky_routing_mail_calls'] && 0 === $GLOBALS['rktsky_routing_feed_calls'], 'Early rejection must persist no new entry, notification or feed.' );
					rktsky_routing_expect( empty( $result['resume_token'] ), 'An early rejection must not save a draft.' );
				} elseif ( 'gf_save_normal' === $case ) {
					$draft = $result['resume_token'] ?? '';
					rktsky_routing_expect( '' !== $draft && 0 === rktsky_routing_gf_count( $form ) && 0 === $GLOBALS['rktsky_routing_mail_calls'], 'An ordinary protected Save-and-Continue must retain its native draft flow.' );
				} elseif ( 'gf_multi_next' === $case ) {
					rktsky_routing_expect( 2 === $result['page_number'] && 0 === rktsky_routing_gf_count( $form ), 'Protected multipage navigation must not create a premature entry.' );
				} else {
					rktsky_routing_expect( 1 === rktsky_routing_gf_count( $form ) && ! empty( $result['entry_id'] ), 'A genuine final native request must create exactly one entry.' );
					$id    = (int) $result['entry_id'];
					$entry = GFAPI::get_entry( $id );
					$spam  = '' !== $kind || 'gf_prior_spam' === $case;
					if ( 'gf_keyword_async' === $case ) {
						rktsky_routing_expect( $queued_before === count( $native_processor->get_batches( 100 ) ), 'Native spam must not queue background notifications under the original asynchronous scheduling.' ); }
					rktsky_routing_expect( ( $spam ? 'spam' : 'active' ) === $entry['status'], 'The original native entry must have the correct final spam state.' );
					rktsky_routing_expect( ( $spam ? 0 : 1 ) === $GLOBALS['rktsky_routing_mail_calls'] && ( $spam ? 0 : 1 ) === $GLOBALS['rktsky_routing_feed_calls'], 'Native spam must suppress notifications and feeds while normal submission reaches both. Mail=' . $GLOBALS['rktsky_routing_mail_calls'] . ', feed=' . $GLOBALS['rktsky_routing_feed_calls'] );
					wp_set_current_user( $administrator );
					$status = $feedback->status( 'gravityforms', $id );
					rktsky_routing_expect( is_array( $status ) && $spam === $status['predicted_spam'] && ! $status['reviewed'], 'Native incoming entries must have one unreviewed final prediction.' );
					if ( '' !== $kind ) {
						rktsky_routing_expect( empty( $result['confirmation_redirect'] ) && str_contains( (string) ( $result['confirmation_message'] ?? '' ), 'spam protection rejected' ), 'Owned native spam must return a visible rejection instead of a success redirect.' );
						$notes = GFAPI::get_notes( array( 'entry_id' => $id ) );
						rktsky_routing_expect( count( array_filter( $notes, static fn( $note ) => 'WP Antispam' === $note->user_name ) ) === 1, 'The original provider must create one WP Antispam spam note.' ); }
					if ( 'gf_unspam' === $case ) {
						GFAPI::update_entry_property( $id, 'status', 'active' );
						$status = $feedback->status( 'gravityforms', $id );
						rktsky_routing_expect( $status['reviewed'] && false === $status['actual_spam'] && 1 === $GLOBALS['rktsky_routing_feed_calls'], 'Authorized native unspam must correct feedback and allow its original feed follow-up.' ); }
					if ( 'gf_same_request_replay_content' === $case ) {
						$before_repeat = rktsky_routing_gf_count( $form );
						rktsky_routing_gf_submit( $form, $posted );
						rktsky_routing_expect( $before_repeat === rktsky_routing_gf_count( $form ), 'A consumed content-spam proof must not create another native spam entry.' ); }
				}
			}
		}
		wp_set_current_user( $administrator );
		$after_q    = $quarantine->count();
		$expected_q = str_ends_with( $case, '_q' ) && str_contains( $case, 'missing' ) ? 1 : 0;
		rktsky_routing_expect( $before_q + $expected_q === $after_q, 'Native GF spam must not be duplicated in WP quarantine; technical opt-in remains separate.' );
		$after = $store->summary();
		if ( in_array( $case, array( 'gf_keyword', 'gf_keyword_async', 'gf_bbcode', 'gf_pattern', 'gf_keyword_q', 'gf_unspam', 'gf_api_final_spam', 'gf_multi_final_spam' ), true ) ) {
			rktsky_routing_expect( 1 === $after['blocked'] - $before['blocked'] && 0 === $after['accepted'] - $before['accepted'], 'One native GF content decision must produce one rejection and no accepted count.' ); }
	}
	echo 'PASS: ' . $case . ' (' . $GLOBALS['rktsky_routing_assertions'] . " native assertions)\n";
} finally {
	if ( null !== $tokens_snapshot ) {
		$store->install();
		rktsky_routing_restore( 'tokens', $tokens_snapshot );
		update_option( 'rktsky_antispam_schema', $schema, false ); }
	if ( '' !== $draft && class_exists( 'GFFormsModel' ) ) {
		GFFormsModel::delete_draft_submission( $draft ); }
	if ( $form > 0 ) {
		GFAPI::delete_form( $form ); }
	if ( $post > 0 ) {
		wp_delete_post( $post, true ); }
	foreach ( $standalone_snapshots as $suffix => $rows ) {
		rktsky_routing_restore( $suffix, $rows ); }
	if ( null === $child_original ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $child_original, false ); }
}
