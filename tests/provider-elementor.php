<?php
/**
 * Exercises the owned adapter with original Elementor Pro Form_Record processing.
 *
 * Requires unchanged Elementor and Elementor Pro plugins on the marked local
 * fixture. These native record tests do not claim browser transport coverage.
 * Settings are overridden only in this process; owned fixture rows are restored.
 *
 * @package RktskyAntispamTests
 * @since 0.3.0
 */

use Rktsky\Antispam\{Settings, Token, Store, Guard, Fields, Quarantine, ContentRules, Country, Language};
use Rktsky\Antispam\Integrations\Elementor;
use ElementorPro\Modules\Forms\Classes\{Form_Record, Ajax_Handler};

require __DIR__ . '/bootstrap.php';
if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) || ! class_exists( Form_Record::class ) || ! class_exists( Ajax_Handler::class ) ) {
	throw new RuntimeException( 'Original Elementor and Elementor Pro plugins are required.' );
}
require_once dirname( __DIR__ ) . '/src/Integrations/Adapter.php';
require_once dirname( __DIR__ ) . '/src/Integrations/Elementor.php';

$rktsky_elementor_assertions = 0;

/**
 * Records a native invariant without printing submitted content.
 *
 * @since 0.3.0
 * @param bool   $condition Expected native behavior.
 * @param string $message Failure context.
 * @return void
 */
function rktsky_antispam_elementor_expect( bool $condition, string $message ): void {
	global $rktsky_elementor_assertions;
	++$rktsky_elementor_assertions;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Constructs an original record from a synthetic server-side form definition.
 *
 * @since 0.3.0
 * @param array  $values Synthetic native field values.
 * @param string $id Server-selected widget identifier.
 * @return Form_Record Original provider record, not a mocked validator.
 */
function rktsky_antispam_elementor_record( array $values = array(), string $id = 'rk-native-elementor' ): Form_Record {
	$definitions = array();
	foreach ( array(
		'name'    => 'text',
		'email'   => 'email',
		'message' => 'textarea',
		'secret'  => 'password',
		'opaque'  => 'hidden',
		'upload'  => 'upload',
	) as $key => $type ) {
		$definitions[] = array(
			'custom_id'   => $key,
			'field_type'  => $type,
			'field_label' => $key,
			'required'    => 'name' === $key ? 'yes' : '',
		);
	}
	return new Form_Record(
		array_merge(
			array(
				'name'    => 'Synthetic native visitor',
				'email'   => 'fixture@example.invalid',
				'message' => 'Synthetic local message.',
			),
			$values
		),
		array(
			'widgetType' => 'form',
			'settings'   => array(
				'id'            => $id,
				'form_fields'   => $definitions,
				'form_metadata' => array(),
			),
		)
	);
}

/**
 * Adds a fresh real signed proof to the request and records its own cleanup key.
 *
 * @since 0.3.0
 * @param Token  $tokens Original local challenge issuer.
 * @param array  $digests Issued token digests, updated for cleanup.
 * @param string $id Expected native widget scope.
 * @param bool   $filled Whether the synthetic trap should reject.
 * @return void
 */
function rktsky_antispam_elementor_proof( Token $tokens, array &$digests, string $id = 'rk-native-elementor', bool $filled = false ): void {
	$proof     = $tokens->issue( 'elementor', $id );
	$_POST     = array(
		'rktsky_antispam_token' => $proof['token'],
		$proof['trap']          => $filled ? 'synthetic-bot' : '',
	);
	$metadata  = $tokens->verify( $proof['token'], 'elementor', $id );
	$digests[] = $metadata['digest'];
}

// Own callbacks are replaced only for this CLI process, avoiding double registration.
foreach ( array( 'elementor/widget/render_content', 'elementor/frontend/the_content', 'elementor_pro/forms/validation', 'elementor_pro/forms/process' ) as $hook ) {
	foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array() as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Elementor ) {
				remove_filter( $hook, $callback['function'], $priority );
			}
		}
	}
}
$settings                     = new Settings();
$effective                    = $settings->defaults();
$effective['elementor']       = true;
$effective['minimum_seconds'] = 0;
$override                     = static function () use ( &$effective ) {
	return $effective;
};
add_filter( 'pre_option_' . Settings::OPTION, $override );
$tokens     = new Token();
$store      = new Store();
$quarantine = new Quarantine( $settings );
$guard      = new Guard( $settings, $tokens, $store, new ContentRules( $settings, new Language(), new Country( $settings ) ) );
$fields     = new Fields( $settings, $tokens );
$adapter    = new Elementor( $settings, $guard, $fields, $quarantine );
$adapter->register();
$digests      = array();
$server       = $_SERVER;
$posted       = $_POST;
$current_user = get_current_user_id();
global $wpdb;
$table                     = $wpdb->prefix . 'rktsky_antispam_stats';
$before                    = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE provider=%s', $table, 'elementor' ), ARRAY_A );
$before_quarantine         = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE provider=%s AND form_id=%s', $wpdb->prefix . 'rktsky_antispam_quarantine', 'elementor', 'rk-native-elementor' ) );
$_SERVER['REQUEST_METHOD'] = 'POST';
wp_set_current_user( 0 );

try {
	$accepted = $store->summary()['accepted'];
	rktsky_antispam_elementor_proof( $tokens, $digests );
	$record  = rktsky_antispam_elementor_record();
	$handler = new Ajax_Handler();
	rktsky_antispam_elementor_expect( $record->validate( $handler ), 'Valid native record must pass the validation hook.' );
	$record->process_fields( $handler );
	rktsky_antispam_elementor_expect( empty( $handler->errors ) && $store->summary()['accepted'] === $accepted + 1, 'Validation and processing must consume exactly one proof.' );
	$record->process_fields( $handler );
	rktsky_antispam_elementor_expect( isset( $handler->errors['rktsky_antispam'] ), 'A second process call must reject replay, even on the same record object.' );

	foreach ( array( 'missing', 'honeypot', 'wrong_scope', 'array_token' ) as $case ) {
		rktsky_antispam_elementor_proof( $tokens, $digests, 'wrong_scope' === $case ? 'another-widget' : 'rk-native-elementor', 'honeypot' === $case );
		if ( 'missing' === $case ) {
			$_POST = array();
		} elseif ( 'array_token' === $case ) {
			$_POST['rktsky_antispam_token'] = array( 'malformed' );
		}
		$handler = new Ajax_Handler();
		$record  = rktsky_antispam_elementor_record();
		rktsky_antispam_elementor_expect( ! $record->validate( $handler ) && isset( $handler->errors['rktsky_antispam'] ) && ! empty( $handler->messages['error'] ), 'Native rejection must stop processing and expose a visible local error: ' . $case );
	}

	rktsky_antispam_elementor_proof( $tokens, $digests );
	$handler = new Ajax_Handler();
	rktsky_antispam_elementor_expect( ! rktsky_antispam_elementor_record( array( 'name' => '' ) )->validate( $handler ) && isset( $handler->errors['name'] ) && ! isset( $handler->errors['rktsky_antispam'] ), 'Native field errors must be preserved without consuming proof.' );
	$handler = new Ajax_Handler();
	$record  = rktsky_antispam_elementor_record();
	rktsky_antispam_elementor_expect( $record->validate( $handler ), 'Corrected fields may reuse proof left unused after native validation failure.' );
	$record->process_fields( $handler );
	rktsky_antispam_elementor_expect( empty( $handler->errors ), 'Corrected native record must reach processing.' );

	add_filter( 'elementor_pro/forms/validation/skip_all', '__return_true' );
	$_POST   = array();
	$record  = rktsky_antispam_elementor_record();
	$handler = new Ajax_Handler();
	rktsky_antispam_elementor_expect( $record->validate( $handler ), 'Original skip_all must really bypass the native validation hook in this fixture.' );
	$record->process_fields( $handler );
	rktsky_antispam_elementor_expect( isset( $handler->errors['rktsky_antispam'] ), 'The original validation bypass must not bypass the before-actions processing gate.' );
	rktsky_antispam_elementor_proof( $tokens, $digests );
	$record  = rktsky_antispam_elementor_record();
	$handler = new Ajax_Handler();
	$record->validate( $handler );
	$record->process_fields( $handler );
	rktsky_antispam_elementor_expect( empty( $handler->errors ), 'Processing fallback must accept a fresh legitimate proof.' );
	remove_filter( 'elementor_pro/forms/validation/skip_all', '__return_true' );

	rktsky_antispam_elementor_proof( $tokens, $digests );
	$record  = rktsky_antispam_elementor_record();
	$handler = new Ajax_Handler();
	$record->validate( $handler );
	rktsky_antispam_elementor_proof( $tokens, $digests, 'another-widget' );
	$record->process_fields( $handler );
	rktsky_antispam_elementor_expect( isset( $handler->errors['rktsky_antispam'] ), 'A token swap cannot reuse an earlier record permit.' );

	rktsky_antispam_elementor_proof( $tokens, $digests );
	$handler = new Ajax_Handler();
	rktsky_antispam_elementor_expect( ! rktsky_antispam_elementor_record( array(), '<invalid>' )->validate( $handler ), 'Malformed server-selected scope must fail closed.' );

	$effective['spam_keywords']      = 'synthetic-blocked';
	$effective['privacy_mode']       = false;
	$effective['quarantine_enabled'] = true;
	rktsky_antispam_elementor_proof( $tokens, $digests );
	$record  = rktsky_antispam_elementor_record(
		array(
			'message' => 'synthetic-blocked',
			'secret'  => 'DO-NOT-RETAIN-password',
			'opaque'  => 'DO-NOT-RETAIN-hidden',
			'rogue'   => 'DO-NOT-RETAIN-unknown',
			'upload'  => 'DO-NOT-RETAIN-upload',
		)
	);
	$handler = new Ajax_Handler();
	rktsky_antispam_elementor_expect( ! $record->validate( $handler ), 'Declared contact content must reach local keyword rules.' );
	$captured = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE provider=%s AND form_id=%s ORDER BY id DESC LIMIT 1', $wpdb->prefix . 'rktsky_antispam_quarantine', 'elementor', 'rk-native-elementor' ) );
	wp_set_current_user( 1 );
	$retained = $quarantine->read( $captured );
	rktsky_antispam_elementor_expect( is_array( $retained ) && str_contains( wp_json_encode( $retained['fields'] ), 'synthetic-blocked' ) && ! str_contains( wp_json_encode( $retained['fields'] ), 'DO-NOT-RETAIN' ) && 'fixture@example.invalid' === $retained['email'], 'Quarantine must retain only declared safe values and the declared contact email index.' );
	wp_set_current_user( 0 );
	rktsky_antispam_elementor_proof( $tokens, $digests );
	$record  = rktsky_antispam_elementor_record(
		array(
			'secret' => 'synthetic-blocked',
			'opaque' => 'synthetic-blocked',
			'rogue'  => 'synthetic-blocked',
		)
	);
	$handler = new Ajax_Handler();
	rktsky_antispam_elementor_expect( $record->validate( $handler ), 'Password, hidden and undeclared values must not trigger contact-content rules.' );

	wp_dequeue_script( 'rktsky-antispam' );
	$plain = '<form class="unrelated"><input name="form_id" value="foreign"></form>';
	rktsky_antispam_elementor_expect( $plain === $adapter->cached_content( $plain ) && ! wp_script_is( 'rktsky-antispam', 'enqueued' ), 'Unrelated content must preserve markup without loading protection assets.' );
	$cached    = '<form class="elementor-form"><input type="hidden" name="form_id" value="rk-native-elementor"></form>';
	$protected = $adapter->cached_content( $cached );
	rktsky_antispam_elementor_expect( str_contains( $protected, 'data-rk-antispam-provider="elementor"' ) && wp_script_is( 'rktsky-antispam', 'enqueued' ), 'Warm cached native form markup must acquire a persistent host and the protection asset.' );
	$ambiguous = '<form class="elementor-form"><input name="form_id" value="first"><input name="form_id" value="second"></form>';
	rktsky_antispam_elementor_expect( $ambiguous === $adapter->cached_content( $ambiguous ), 'Ambiguous cached native scope controls must not receive a descriptor.' );
	$malformed = '<form class="elementor-form"><input name="form_id" value="&lt;bad&gt;"></form>';
	rktsky_antispam_elementor_expect( $malformed === $adapter->cached_content( $malformed ), 'Malformed cached scopes must not receive a descriptor.' );
	$fields = $record->get( 'fields' );
	rktsky_antispam_elementor_expect( ! isset( $fields['rktsky_antispam_token'] ) && ! array_filter( array_keys( $fields ), static fn( $key ) => str_starts_with( $key, 'rktsky_as_' ) ), 'Original provider records must never contain technical protection controls.' );
} finally {
	remove_filter( 'elementor_pro/forms/validation/skip_all', '__return_true' );
	remove_filter( 'pre_option_' . Settings::OPTION, $override );
	wp_set_current_user( 1 );
	$after_quarantine = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE provider=%s AND form_id=%s', $wpdb->prefix . 'rktsky_antispam_quarantine', 'elementor', 'rk-native-elementor' ) );
	foreach ( array_diff( $after_quarantine, $before_quarantine ) as $id ) {
		$quarantine->delete( (int) $id );
	}
	foreach ( $digests as $digest ) {
		$wpdb->delete( $wpdb->prefix . 'rktsky_antispam_tokens', array( 'digest' => $digest ) );
	}
	$wpdb->delete( $table, array( 'provider' => 'elementor' ) );
	foreach ( $before as $row ) {
		$wpdb->insert( $table, $row );
	}
	$_SERVER = $server;
	$_POST   = $posted;
	wp_set_current_user( $current_user );
}
echo 'PASS: ' . $rktsky_elementor_assertions . ' native Elementor Pro ' . ELEMENTOR_PRO_VERSION . " record/adapter assertions.\n";
