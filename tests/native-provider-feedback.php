<?php
/**
 * Verifies free-provider moderation, edit invalidation and native privacy boundaries.
 *
 * The routing suite separately proves observations from real browser transports.
 * Formidable/WS setup here uses their public entry APIs to isolate feedback and
 * privacy pagination. Fluent records use the unchanged native submission handler.
 *
 * @package RktskyAntispamTests
 * @since 0.5.0
 */

use Rktsky\Antispam\Feedback;
use Rktsky\Antispam\Settings;

require __DIR__ . '/bootstrap.php';

if ( 'core_only' === ( $argv[1] ?? '' ) ) {
	wp_set_current_user( 1 );
	if ( class_exists( 'FrmEntry' ) || function_exists( 'fluentFormApi' ) || class_exists( 'WS_Form_Submit' ) || function_exists( 'wpforms' ) ) {
		throw new RuntimeException( 'The Core-only privacy case requires all provider plugins to be inactive.' );
	}
	$core_feedback = new Feedback();
	$core_email    = 'core-only-' . bin2hex( random_bytes( 6 ) ) . '@example.invalid';
	$core_export   = $core_feedback->export( $core_email, 1 );
	$core_erase    = $core_feedback->erase( $core_email, 1 );
	if ( is_wp_error( $core_export ) || is_wp_error( $core_erase ) || ! $core_export['done'] || ! $core_erase['done'] || $core_export['data'] ) {
		throw new RuntimeException( 'Absent provider APIs must preserve the native Core-only privacy workflow.' );
	}
	echo "PASS: Core-only privacy completed with every provider inactive.\n";
	return;
}

// The unchanged WS REST writer exits after JSON output, so its lifecycle runs
// in a separate CLI request with a shutdown assertion after the actual write.
if ( in_array( $argv[1] ?? '', array( 'ws_editor', 'ws_denied' ), true ) ) {
	wp_set_current_user( 1 );
	$ws_reader         = new WS_Form_Submit();
	$ws_reader->id     = (int) $argv[2];
	$ws_original       = $ws_reader->db_read( true, false );
	$ws_old_hash       = $ws_original->hash;
	$ws_original->hash = md5( 'Synthetic edited WS hash ' . $ws_old_hash );
	$ws_target         = $ws_original->hash;
	$ws_denied         = 'ws_denied' === $argv[1];
	wp_set_current_user( $ws_denied ? 0 : 1 );
	$request = new WP_REST_Request( 'POST', '/' . WS_FORM_RESTFUL_NAMESPACE . '/submit/' . $ws_reader->id . '/put' );
	$request->set_body_params(
		array(
			'submit'  => $ws_original,
			'form_id' => $ws_original->form_id,
		)
	);
	if ( ! $ws_denied ) {
		register_shutdown_function(
			static function () use ( $ws_reader, $ws_old_hash, $ws_target ): void {
				$written   = $ws_reader->db_read( false, false );
				$forgotten = null === ( new Feedback() )->status( 'wsform', $ws_old_hash );
				while ( ob_get_level() > 0 ) {
					ob_end_clean(); }
				if ( $written->hash !== $ws_target || ! $forgotten ) {
					fwrite( STDERR, "Native WS REST edit must persist its update and detach the previous hash before mutation.\n" );
					exit( 1 );
				}
				echo "PASS: native WS editor persisted the update and detached its original reference.\n";
			}
		);
		ob_start( static fn( string $output ): string => '' );
	}
	$response = rest_do_request( $request );
	if ( $ws_denied ) {
		wp_set_current_user( 1 );
		$unchanged = $ws_reader->db_read( false, false );
		if ( ! in_array( $response->get_status(), array( 401, 403 ), true ) || $unchanged->hash !== $ws_old_hash || null === ( new Feedback() )->status( 'wsform', $ws_old_hash ) ) {
			throw new RuntimeException( 'Denied WS REST edits must preserve source content and the feedback link.' );
		}
		echo "PASS: native WS permissions rejected the edit before detachment.\n";
	}
	return;
}

$feedback              = new Feedback();
$settings              = new Settings();
$original              = $settings->all();
$forms                 = get_option( 'rktsky_antispam_extra_test_forms', array() );
$created               = array(
	'formidable'  => array(),
	'fluentforms' => array(),
	'wsform'      => array(),
);
$assertions            = 0;
$original_post         = $_POST;
$original_user         = get_current_user_id();
$original_plugins      = get_option( 'active_plugins', array() );
$fluent_temporary_form = 0;
$ws_temporary_form     = 0;
$ws_temporary_entry    = 0;
$ws_purge_original     = WS_Form_Common::option_get( 'submit_spam_purge', '' );
$ws_duration_original  = WS_Form_Common::option_get( 'submit_spam_purge_duration', 30 );

/**
 * Counts assertions against an observable native provider or metadata result.
 *
 * @since 0.5.0
 * @param bool   $condition Required outcome.
 * @param string $message Specific failed boundary.
 * @return void
 */
function rktsky_provider_feedback_expect( bool $condition, string $message ): void {
	global $assertions;
	++$assertions;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Runs an original Fluent submission in a fresh request with guarded rendered proof.
 *
 * @since 0.5.0
 * @param string $case Existing native valid or keyword case.
 * @param string $email Dedicated synthetic native email value.
 * @param int    $form_id Optional dedicated native form, otherwise the shared fixture.
 * @return int The newly persisted, already observed native entry ID.
 */
function rktsky_provider_feedback_fluent( string $case, string $email, int $form_id = 0 ): int {
	$command = array( PHP_BINARY );
	if ( is_file( '/tmp/rktsky-antispam-runtime/iconv.so' ) ) {
		$command = array_merge( $command, array( '-d', 'extension=/tmp/rktsky-antispam-runtime/iconv.so' ) );
	}
	$command = array_merge( $command, array( __DIR__ . '/native-extra-forms.php', 'fluentforms', $case, 'observe-id', $email ) );
	if ( $form_id > 0 ) {
		$command[] = (string) $form_id; }
	$pipes   = array();
	$process = proc_open(
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
	rktsky_provider_feedback_expect( 0 === $status && 1 === preg_match( '/NATIVE-ID: ([1-9][0-9]*)/', $output, $match ), 'The original Fluent handler must create and observe one fresh native object: ' . $error );
	return (int) $match[1];
}

/**
 * Executes an unchanged WS REST editor callback in a fresh native request.
 *
 * @since 0.5.0
 * @param int  $id Synthetic native submission to edit.
 * @param bool $denied Whether an anonymous actor must be rejected before editing.
 * @return void
 */
function rktsky_provider_feedback_ws_editor( int $id, bool $denied ): void {
	$command = array( PHP_BINARY );
	if ( is_file( '/tmp/rktsky-antispam-runtime/iconv.so' ) ) {
		$command = array_merge( $command, array( '-d', 'extension=/tmp/rktsky-antispam-runtime/iconv.so' ) );
	}
	$command = array_merge( $command, array( __FILE__, $denied ? 'ws_denied' : 'ws_editor', (string) $id ) );
	$pipes   = array();
	$process = proc_open(
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
	rktsky_provider_feedback_expect( 0 === proc_close( $process ) && str_contains( $output, 'PASS: native WS' ), 'The original WS REST editor/permission boundary must pass: ' . $error );
}

wp_set_current_user( 1 );
// Formidable registers its native privacy callbacks during admin_init in wp-admin.
new FrmPersonalData();
$options = $original;
foreach ( array_keys( $created ) as $provider ) {
	$options[ $provider ] = true;
}
update_option( Settings::OPTION, $options, false );
try {
	$email = 'feedback-pagination-' . bin2hex( random_bytes( 6 ) ) . '@example.invalid';
	foreach ( range( 1, 202 ) as $index ) {
		$id = FrmEntry::create(
			array(
				'form_id'   => $forms['formidable'],
				'user_id'   => 0,
				'item_meta' => array(
					$forms['formidable_fields']['email'] => $email,
					$forms['formidable_fields']['textarea'] => 'Synthetic native privacy case ' . $index,
				),
			)
		);
		rktsky_provider_feedback_expect( (int) $id > 0, 'The native Formidable API must create the synthetic privacy entry.' );
		$created['formidable'][] = (int) $id;
		if ( $index <= 201 ) {
			$feedback->track( 'formidable', (int) $id, false );
		}
	}
	$first   = $created['formidable'][0];
	$unknown = $created['formidable'][201];
	wp_set_current_user( 0 );
	FrmSpamEntriesHelper::mark_as_spam( $first );
	wp_set_current_user( 1 );
	rktsky_provider_feedback_expect( ! $feedback->status( 'formidable', $first )['reviewed'], 'A background Formidable status write cannot impersonate a moderator.' );
	FrmSpamEntriesHelper::restore_from_spam( $first );
	FrmSpamEntriesHelper::mark_as_spam( $first );
	rktsky_provider_feedback_expect( true === $feedback->status( 'formidable', $first )['actual_spam'], 'The native Formidable moderation hook must label the original normal prediction as missed spam.' );
	FrmSpamEntriesHelper::mark_as_spam( $unknown );
	rktsky_provider_feedback_expect( null === $feedback->status( 'formidable', $unknown ), 'Native moderation must not invent observations for historical entries.' );
	$one = $feedback->export( $email, 1 );
	$two = $feedback->export( $email, 2 );
	rktsky_provider_feedback_expect( 200 === count( $one['data'] ) && ! $one['done'] && 1 === count( $two['data'] ) && $two['done'], 'Native Formidable privacy pages must export all 201 tracked rows across the 200-row native boundary: ' . wp_json_encode( array( count( $one['data'] ), $one['done'], count( $two['data'] ), $two['done'] ) ) );
	$one = $feedback->erase( $email, 1 );
	$two = $feedback->erase( $email, 2 );
	rktsky_provider_feedback_expect( $one['items_removed'] && ! $one['done'] && $two['items_removed'] && $two['done'], 'Native privacy erasure must retain stable source pagination until every reference is removed.' );
	rktsky_provider_feedback_expect( null === $feedback->status( 'formidable', $first ) && null === $feedback->status( 'formidable', $created['formidable'][200] ), 'Reviewed and pending Formidable source links must disappear after privacy erasure.' );
	$edited = $created['formidable'][2];
	$feedback->track( 'formidable', $edited, false );
	FrmEntryMeta::update_entry_meta( $edited, $forms['formidable_fields']['email'], '', 'anonymized@example.invalid' );
	rktsky_provider_feedback_expect( null === $feedback->status( 'formidable', $edited ), 'Native Formidable field edits/anonymization must remove the original prediction link.' );
	$deleted = $created['formidable'][3];
	$feedback->track( 'formidable', $deleted, true );
	FrmEntry::destroy( $deleted );
	rktsky_provider_feedback_expect( null === $feedback->status( 'formidable', $deleted ), 'Successful native Formidable deletion must remove its classification link.' );
	$ws_email  = 'ws-feedback-' . bin2hex( random_bytes( 6 ) ) . '@example.invalid';
	$ws_hashes = array();
	foreach ( range( 1, 6 ) as $index ) {
		$entry          = new WS_Form_Submit();
		$entry->form_id = $forms['wsform'];
		$entry->status  = $index >= 5 ? 'spam' : 'publish';
		if ( $index >= 5 ) {
			$entry->date_added = gmdate( 'Y-m-d H:i:s', time() - 2000 * DAY_IN_SECONDS );
		}
		$entry->db_create();
		$entry->meta = array(
			'field_' . $forms['wsform_fields']['email'] => array(
				'id'    => $forms['wsform_fields']['email'],
				'value' => $ws_email,
			),
		);
		$entry->db_update();
		$created['wsform'][] = (int) $entry->id;
		$ws_hashes[]         = $entry->hash;
		if ( 3 !== $index ) {
			$feedback->track( 'wsform', $entry->hash, 2 === $index || $index >= 5 );
		}
	}
	$ws     = new WS_Form_Submit();
	$ws->id = $created['wsform'][0];
	$ws->db_read( false, false );
	$ws->db_set_status( 'spam' );
	rktsky_provider_feedback_expect( true === $feedback->status( 'wsform', $ws->id )['actual_spam'], 'Native WS spam moderation must classify an existing normal observation.' );
	$ws->db_set_status( 'not_spam' );
	rktsky_provider_feedback_expect( false === $feedback->status( 'wsform', $ws->id )['actual_spam'], 'Native WS not-spam correction must update the same reviewed observation.' );
	$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
	$ws_export = call_user_func( $exporters['ws-form']['callback'], $ws_email, 1 );
	$labels    = array_filter( $ws_export['data'], static fn( array $item ): bool => 'rktsky-antispam-feedback' === $item['group_id'] );
	rktsky_provider_feedback_expect( 5 === count( $labels ), 'The existing native WS exporter must include only tracked classification metadata.' );
	$erasers  = apply_filters( 'wp_privacy_personal_data_erasers', array() );
	$ws_erase = call_user_func( $erasers['ws-form']['callback'], $ws_email, 1 );
	rktsky_provider_feedback_expect( $ws_erase['items_removed'] && null === $feedback->status( 'wsform', $ws_hashes[0] ), 'Native WS erasure must delete the source and detach its reviewed reference through the original delete hook.' );
	$ws->id = $created['wsform'][1];
	$ws->db_read( true, false );
	$ws->meta[ 'field_' . $forms['wsform_fields']['email'] ]['value'] = 'anonymized@example.invalid';
	$ws->db_update();
	rktsky_provider_feedback_expect( null === $feedback->status( 'wsform', $ws_hashes[1] ), 'Native WS content edits must detach its prediction before the old subject becomes unfindable.' );
	rktsky_provider_feedback_ws_editor( $created['wsform'][3], true );
	rktsky_provider_feedback_ws_editor( $created['wsform'][3], false );
	$ws->id = $created['wsform'][2];
	$ws->db_read( false, false );
	$ws->db_set_status( 'spam' );
	rktsky_provider_feedback_expect( null === $feedback->status( 'wsform', $ws_hashes[2] ), 'Native WS moderation cannot create a historical observation.' );
	WS_Form_Common::option_set( 'submit_spam_purge_duration', 1000 );
	WS_Form_Common::option_set( 'submit_spam_purge', 'on' );
	$purge_limit = static fn(): int => 1;
	add_filter( 'wsf_submit_spam_purge_limit', $purge_limit );
	$purge       = new WS_Form_Submit();
	rktsky_provider_feedback_expect( 1 === $purge->db_delete_spam(), 'The original bounded WS automatic purge must move one expired spam row to trash.' );
	$ws->id = $created['wsform'][4];
	rktsky_provider_feedback_expect( 'trash' === $ws->db_read( false, false )->status && null !== $feedback->status( 'wsform', $ws_hashes[4] ), 'Native bulk trashing must preserve a hash-addressable observation.' );
	WS_Form_Common::option_set( 'submit_spam_purge', 'delete' );
	rktsky_provider_feedback_expect( 1 === $purge->db_delete_spam() && null === $feedback->status( 'wsform', $ws_hashes[5] ), 'Native permanent spam purge must detach the exact hash after successful source deletion.' );
	remove_filter( 'wsf_submit_spam_purge_limit', $purge_limit );
	$ws_export = call_user_func( $exporters['ws-form']['callback'], $ws_email, 1 );
	$labels    = array_filter( $ws_export['data'], static fn( array $item ): bool => 'rktsky-antispam-feedback' === $item['group_id'] );
	rktsky_provider_feedback_expect( 1 === count( $labels ), 'Native WS privacy export must retain classification metadata for its automatically trashed source.' );
	for ( $page = 1; $page <= 6; ++$page ) {
		$result = call_user_func( $erasers['ws-form']['callback'], $ws_email, $page );
		if ( $result['done'] ) {
			break; }
	}
	rktsky_provider_feedback_expect( $result['done'] && null === $feedback->status( 'wsform', $ws_hashes[4] ), 'Native WS privacy erasure must also detach automatically trashed hash references.' );
	$fluent_email             = 'feedback-' . bin2hex( random_bytes( 6 ) ) . '@example.invalid';
	$fluent_normal            = rktsky_provider_feedback_fluent( 'valid', $fluent_email );
	$created['fluentforms'][] = $fluent_normal;
	$fluent_spam              = rktsky_provider_feedback_fluent( 'keyword', $fluent_email );
	$created['fluentforms'][] = $fluent_spam;
	$service                  = new \FluentForm\App\Services\Submission\SubmissionService();
	$service->updateStatus(
		array(
			'entry_id' => $fluent_normal,
			'status'   => 'read',
		)
	);
	rktsky_provider_feedback_expect( ! $feedback->status( 'fluentforms', $fluent_normal )['reviewed'], 'A native read action on a normal entry must not invent a human spam assessment.' );
	$service->updateStatus(
		array(
			'entry_id' => $fluent_normal,
			'status'   => 'spam',
		)
	);
	$service->updateStatus(
		array(
			'entry_id' => $fluent_spam,
			'status'   => 'unread',
		)
	);
	rktsky_provider_feedback_expect( true === $feedback->status( 'fluentforms', $fluent_normal )['actual_spam'] && false === $feedback->status( 'fluentforms', $fluent_spam )['actual_spam'], 'Native Fluent single-entry moderation must distinguish missed spam and a corrected false positive.' );
	$export = $feedback->export( $fluent_email, 1 );
	rktsky_provider_feedback_expect( false !== array_search( 'fluentforms-' . $fluent_normal, array_column( $export['data'], 'item_id' ), true ) && $export['done'], 'Declared Fluent email fields must resolve native metadata with a finite final privacy page: ' . wp_json_encode( array( $fluent_normal, array_column( $export['data'], 'item_id' ), $export['done'] ) ) );
	$disabled                = $options;
	$disabled['fluentforms'] = false;
	update_option( Settings::OPTION, $disabled, false );
	$export = $feedback->export( $fluent_email, 1 );
	rktsky_provider_feedback_expect( false !== array_search( 'fluentforms-' . $fluent_spam, array_column( $export['data'], 'item_id' ), true ), 'Disabling protection must preserve native privacy access to existing Fluent references.' );
	$service->deleteEntries( array( $fluent_normal ), $forms['fluentforms'] );
	rktsky_provider_feedback_expect( null === $feedback->status( 'fluentforms', $fluent_normal ), 'Native Fluent bulk deletion must detach its classification link.' );
	$feedback->erase( $fluent_email, 1 );
	rktsky_provider_feedback_expect( null === $feedback->status( 'fluentforms', $fluent_spam ), 'Native Fluent subject erasure must detach the corrected prediction while leaving provider storage native.' );
	update_option( Settings::OPTION, $options, false );
	$form_service          = new \FluentForm\App\Services\Form\FormService();
	$copy                  = $form_service->duplicate( array( 'form_id' => $forms['fluentforms'] ) );
	$fluent_temporary_form = (int) $copy->id;
	$before_update         = rktsky_provider_feedback_fluent( 'valid', $fluent_email, $fluent_temporary_form );
	$definition            = json_decode( $copy->form_fields, true );
	$definition['submitButton']['settings']['button_ui']['text'] = 'Synthetic updated button';
	$form_service->update(
		array(
			'form_id'    => $fluent_temporary_form,
			'title'      => 'Synthetic form privacy update',
			'status'     => 'published',
			'formFields' => wp_json_encode( $definition ),
		)
	);
	rktsky_provider_feedback_expect( null !== $feedback->status( 'fluentforms', $before_update ), 'A visual Fluent form edit must preserve existing observations.' );
	foreach ( $definition['fields'] as &$field ) {
		if ( 'input_email' === $field['element'] ) {
			$field['attributes']['name'] = 'email_renamed'; }
	}
	unset( $field );
	$form_service->update(
		array(
			'form_id'    => $fluent_temporary_form,
			'title'      => 'Synthetic form privacy update',
			'status'     => 'published',
			'formFields' => wp_json_encode( $definition ),
		)
	);
	rktsky_provider_feedback_expect( null === $feedback->status( 'fluentforms', $before_update ), 'Renaming the native identifying email field must detach references before the old email mapping disappears.' );
	$before_delete = rktsky_provider_feedback_fluent( 'valid', $fluent_email, $fluent_temporary_form );
	$form_service->delete( $fluent_temporary_form );
	rktsky_provider_feedback_expect( null === $feedback->status( 'fluentforms', $before_delete ), 'Native Fluent whole-form deletion must detach entries even though it omits per-entry delete hooks.' );
	$fluent_temporary_form = 0;
	$temporary             = new WS_Form_Form();
	$ws_temporary_form     = (int) $temporary->db_create();
	$entry                 = new WS_Form_Submit();
	$entry->form_id        = $ws_temporary_form;
	$entry->status         = 'spam';
	$entry->db_create();
	$ws_temporary_entry = (int) $entry->id;
	$entry_hash         = $entry->hash;
	$feedback->track( 'wsform', $entry_hash, true );
	$temporary->db_delete( true );
	rktsky_provider_feedback_expect( null === $feedback->status( 'wsform', $entry_hash ), 'Native WS whole-form deletion must detach classification before removing native field definitions.' );
	$ws_temporary_form = 0;
	$only_owned        = array_values( array_filter( $original_plugins, static fn( string $plugin ): bool => 'wp-antispam.php' === basename( $plugin ) ) );
	rktsky_provider_feedback_expect( 1 === count( $only_owned ), 'The Core-only subprocess must retain exactly the owned plugin.' );
	update_option( 'active_plugins', $only_owned, false );
	$command = array( PHP_BINARY, __FILE__, 'core_only' );
	$pipes   = array();
	$process = proc_open(
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
	rktsky_provider_feedback_expect( 0 === proc_close( $process ) && str_contains( $output, 'PASS: Core-only privacy' ), 'The new global feedback callbacks must preserve Core-only privacy: ' . $error );
	update_option( 'active_plugins', $original_plugins, false );
} finally {
	wp_set_current_user( 1 );
	foreach ( $created['formidable'] as $id ) {
		if ( FrmEntry::getOne( $id ) ) {
			FrmEntry::destroy( $id );
		}
	}
	foreach ( $created['wsform'] as $id ) {
		$entry     = new WS_Form_Submit();
		$entry->id = $id;
		try {
			$entry->db_read( false, false );
			$entry->db_delete( true );
		} catch ( Throwable $error ) {
			// The native privacy eraser may already have removed this synthetic row.
			continue;
		}
	}
	if ( $created['fluentforms'] ) {
		( new \FluentForm\App\Services\Submission\SubmissionService() )->deleteEntries( $created['fluentforms'], $forms['fluentforms'] );
	}
	if ( $fluent_temporary_form > 0 ) {
		( new \FluentForm\App\Services\Form\FormService() )->delete( $fluent_temporary_form );
	}
	if ( $ws_temporary_entry > 0 ) {
		$entry     = new WS_Form_Submit();
		$entry->id = $ws_temporary_entry;
		$entry->db_delete( true );
	}
	if ( $ws_temporary_form > 0 ) {
		$temporary     = new WS_Form_Form();
		$temporary->id = $ws_temporary_form;
		$temporary->db_delete( true );
	}
	update_option( Settings::OPTION, $original, false );
	update_option( 'active_plugins', $original_plugins, false );
	WS_Form_Common::option_set( 'submit_spam_purge', $ws_purge_original );
	WS_Form_Common::option_set( 'submit_spam_purge_duration', $ws_duration_original );
	$_POST = $original_post;
	wp_set_current_user( $original_user );
}
echo 'PASS: ' . $assertions . " native provider feedback/privacy assertions.\n";
