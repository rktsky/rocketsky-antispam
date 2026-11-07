<?php
/**
 * Verifies feedback against the original licensed Gravity Forms submission engine.
 *
 * @package RktskyAntispamTests
 * @since 0.2.0
 */
use Rktsky\Antispam\Feedback;
use Rktsky\Antispam\Settings;
use Rktsky\Antispam\Token;
require __DIR__ . '/bootstrap.php';
if ( ! class_exists( 'GFAPI' ) ) {
	echo "SKIP: licensed Gravity Forms package is unavailable.\n";
	return;
}
$rktsky_gf_assertions = 0;

/**
 * Fails on an observable original-provider contract.
 *
 * @since 0.2.0
 * @param bool $condition Required behavior.
 * @param string $message Failure label.
 * @return void
 */
function rktsky_antispam_gf_expect( bool $condition, string $message ): void {
	global $rktsky_gf_assertions;
	++$rktsky_gf_assertions;
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}

/**
 * Executes one original WEBFORM submission with actual rendered controls.
 *
 * Omitting GF's browser-generated version control tests its own spam verdict.
 *
 * @since 0.2.0
 * @param int $form_id Dedicated synthetic form used once in this process.
 * @param bool $spam Whether the original GF version control is omitted.
 * @return int Actually persisted native entry identifier.
 */
function rktsky_antispam_gf_receive( int $form_id, bool $spam ): int {
	wp_set_current_user( 0 );
	$_POST = array();
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$document = new DOMDocument();
	@$document->loadHTML( GFFormDisplay::get_form( $form_id, false, false, false, null, true ) );
	$xpath = new DOMXPath( $document );
	foreach ( $xpath->query( '//form[@id="gform_' . $form_id . '"]//input' ) as $input ) {
		$name = $input->getAttribute( 'name' );
		if ( '' !== $name && ! in_array( $input->getAttribute( 'type' ), array( 'submit', 'button' ), true ) ) {
			$_POST[ $name ] = $input->getAttribute( 'value' );
		}
	}
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['REMOTE_ADDR'] = '192.0.2.90';
	$_SERVER['HTTP_USER_AGENT'] = 'Synthetic GF feedback browser';
	$_POST['input_1'] = 'Synthetic original GF content';
	$_POST['input_2'] = 'gf-feedback-privacy@example.invalid';
	if ( ! $spam ) { $_POST['version_hash'] = wp_hash( GFForms::$version ); }
	$proof = ( new Token() )->issue( 'gravityforms', (string) $form_id );
	$_POST['rktsky_antispam_token'] = $proof['token'];
	$_POST[ $proof['trap'] ] = '';
	$result = GFAPI::submit_form( $form_id, array(), array(), 0, 1, GFFormDisplay::SUBMISSION_INITIATED_BY_WEBFORM );
	rktsky_antispam_gf_expect( ! is_wp_error( $result ) && ! empty( $result['is_valid'] ) && ! empty( $result['entry_id'] ), 'Native GF must persist the synthetic WEBFORM submission.' );
	return (int) $result['entry_id'];
}

global $wpdb;
$settings = new Settings();
$original = $settings->all();
$feedback = new Feedback();
$feedback->install();
$table = $wpdb->prefix . 'rktsky_antispam_feedback';
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
$forms = array();
$entries = array();
$server = $_SERVER;
wp_set_current_user( 1 );
$enabled = $settings->defaults();
$enabled['privacy_mode'] = false;
$enabled['quarantine_enabled'] = true;
update_option( Settings::OPTION, $enabled, false );
try {
	foreach ( range( 1, 5 ) as $index ) {
		$id = GFAPI::add_form( array( 'title' => 'Synthetic GF feedback ' . $index,
			'fields' => array( new GF_Field_Text( array( 'id' => 1, 'label' => 'Message', 'isRequired' => true ) ), new GF_Field_Email( array( 'id' => 2, 'label' => 'Email', 'isRequired' => true ) ) ),
			'button' => array( 'type' => 'text', 'text' => 'Synthetic submit' ),
			'confirmations' => array( array( 'id' => 'synthetic', 'name' => 'Default', 'isDefault' => true, 'type' => 'message', 'message' => 'Synthetic accepted.' ) ),
			'notifications' => array(), 'enableHoneypot' => true,
			'personalData' => array( 'preventIP' => true, 'exportingAndErasing' => array( 'enabled' => true, 'identificationField' => ( 5 === $index ? 'created_by' : '2' ) ) ) ) );
		rktsky_antispam_gf_expect( ! is_wp_error( $id ) && $id > 0, 'Dedicated native privacy forms must be created.' );
		$forms[] = (int) $id;
	}
	$before_q = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'rktsky_antispam_quarantine' ) );
	foreach ( array_slice( $forms, 0, 4 ) as $index => $id ) { $entries[] = rktsky_antispam_gf_receive( $id, 1 === $index ); }
	wp_set_current_user( 1 );
	$normal = $feedback->status( 'gravityforms', $entries[0] );
	$spam = $feedback->status( 'gravityforms', $entries[1] );
	rktsky_antispam_gf_expect( ! $normal['predicted_spam'] && ! $normal['reviewed'] && $spam['predicted_spam'] && ! $spam['reviewed'], 'Final native active/spam status must be a prediction, never a human label.' );
	rktsky_antispam_gf_expect( 4 === $feedback->metrics()['pending'] && $before_q === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'rktsky_antispam_quarantine' ) ), 'Native entries must appear once without quarantine duplicates.' );
	wp_set_current_user( 0 );
	GFAPI::update_entry_property( $entries[0], 'status', 'spam' );
	wp_set_current_user( 1 );
	rktsky_antispam_gf_expect( ! $feedback->status( 'gravityforms', $entries[0] )['reviewed'], 'Background status changes must not impersonate a moderator.' );
	GFAPI::update_entry_property( $entries[0], 'status', 'active' );
	GFAPI::update_entry_property( $entries[1], 'status', 'active' );
	rktsky_antispam_gf_expect( 1 === $feedback->metrics()['tn'] && 1 === $feedback->metrics()['fp'], 'Authorized native status changes must classify original predictions.' );
	GFAPI::update_entry_property( $entries[1], 'status', 'spam' );
	rktsky_antispam_gf_expect( 1 === $feedback->metrics()['tp'] && 0 === $feedback->metrics()['fp'], 'Native moderator corrections must move one existing cell.' );
	GFAPI::update_entry_field( $entries[2], '1', 'Synthetic original GF content' );
	rktsky_antispam_gf_expect( null !== $feedback->status( 'gravityforms', $entries[2] ), 'Unchanged content must retain its link.' );
	GFAPI::update_entry_field( $entries[2], '1', 'Edited synthetic GF content' );
	rktsky_antispam_gf_expect( null === $feedback->status( 'gravityforms', $entries[2] ) && 'Edited synthetic GF content' === GFAPI::get_entry( $entries[2] )['1'], 'Field edits must detach old predictions without changing provider writes.' );
	$feedback->review( 'gravityforms', $entries[3], false );
	$full = GFAPI::get_entry( $entries[3] );
	$full['1'] = 'Full API edited content';
	GFAPI::update_entry( $full );
	rktsky_antispam_gf_expect( null === $feedback->status( 'gravityforms', $entries[3] ) && 2 === $feedback->metrics()['tn'], 'Full-entry changes must detach reviewed references while preserving anonymous outcomes.' );
	foreach ( range( 0, 51 ) as $index ) {
		$status = array( 'active', 'spam', 'trash' )[ $index % 3 ];
		$id = GFAPI::add_entry( array( 'form_id' => $forms[0], '1' => 'Synthetic imported privacy ' . $index, '2' => 'gf-feedback-privacy@example.invalid', 'status' => $status ) );
		rktsky_antispam_gf_expect( ! is_wp_error( $id ) && $id > 0, 'Native privacy fixtures must be persisted.' );
		$entries[] = (int) $id;
		rktsky_antispam_gf_expect( null === $feedback->status( 'gravityforms', $id ), 'Historical imports must not receive invented predictions.' );
		$feedback->track( 'gravityforms', $id, 'spam' === $status );
	}
	$exported = 0;
	foreach ( range( 1, 2 ) as $page ) {
		$data = $feedback->export( 'gf-feedback-privacy@example.invalid', $page );
		$exported += count( $data['data'] );
		rktsky_antispam_gf_expect( ( 2 === $page ) === $data['done'], 'GF privacy must paginate bounded IDs across forms and statuses.' );
	}
	rktsky_antispam_gf_expect( 54 === $exported, 'Export must cover all linked GF cases including spam and trash.' );
	foreach ( range( 1, 2 ) as $page ) {
		$data = $feedback->erase( 'gf-feedback-privacy@example.invalid', $page );
		rktsky_antispam_gf_expect( $data['items_removed'] && ! $data['items_retained'] && ( 2 === $page ) === $data['done'], 'GF erasure must paginate without deleting original payloads.' );
	}
	rktsky_antispam_gf_expect( 0 === $feedback->metrics()['pending'] && null === $feedback->status( 'gravityforms', $entries[1] ) && ! is_wp_error( GFAPI::get_entry( $entries[1] ) ), 'Confirmed erasure must detach every matching metadata link.' );
	$account_entry = GFAPI::add_entry( array( 'form_id' => $forms[4], 'created_by' => 1, '1' => 'Synthetic registered-user privacy case', 'status' => 'active' ) );
	$entries[] = (int) $account_entry;
	$feedback->track( 'gravityforms', $account_entry, false );
	$account_email = get_userdata( 1 )->user_email;
	$account_export = $feedback->export( $account_email, 1 );
	rktsky_antispam_gf_expect( 1 === count( $account_export['data'] ), 'The native created_by mapping must resolve a registered privacy subject without guessing form fields.' );
	$feedback->erase( $account_email, 1 );
	rktsky_antispam_gf_expect( null === $feedback->status( 'gravityforms', $account_entry ) && ! is_wp_error( GFAPI::get_entry( $account_entry ) ), 'Registered-user privacy erasure must detach metadata without deleting the original entry.' );
	$feedback->track( 'gravityforms', $entries[0], false );
	GFAPI::delete_entry( $entries[0] );
	rktsky_antispam_gf_expect( null === $feedback->status( 'gravityforms', $entries[0] ), 'Original entry deletion must remove its feedback link.' );
	echo 'PASS: ' . $rktsky_gf_assertions . ' native Gravity Forms ' . GFForms::$version . " feedback assertions.\n";
} finally {
	wp_set_current_user( 1 );
	foreach ( $entries as $id ) { GFAPI::delete_entry( $id ); }
	foreach ( $forms as $id ) { GFAPI::delete_form( $id ); }
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	update_option( Settings::OPTION, $original, false );
	$_SERVER = $server;
	$_POST = array();
	wp_set_current_user( 0 );
}
