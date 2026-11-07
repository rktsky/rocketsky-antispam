<?php
/**
 * Creates synthetic forms through unchanged free-provider APIs on a disposable site.
 *
 * @package RktskyAntispamTests
 * @since 0.3.0
 */
require __DIR__ . '/bootstrap.php';
wp_set_current_user( 1 );
if ( ! class_exists( 'FrmForm' ) || ! class_exists( '\FluentForm\App\Models\Form' ) || ! class_exists( 'WS_Form_Form' ) ) {
	throw new RuntimeException( 'Install the three original free providers before setup.' );
}
FrmAppController::install();
$existing = get_option( 'rktsky_antispam_extra_test_forms', array() );
if ( $existing ) {
	echo "PASS: existing synthetic extra-form fixture.\n";
	return;
}
$forms               = array();
$forms['formidable'] = FrmForm::create(
	array(
		'form_key'    => 'rktsky-native-fixture',
		'name'        => 'Synthetic adapter fixture',
		'description' => 'Synthetic only',
		'options'     => array( 'ajax_submit' => 0 ),
	)
);
foreach ( array(
	'Name'    => 'text',
	'Email'   => 'email',
	'Message' => 'textarea',
) as $label => $type ) {
	$forms['formidable_fields'][ $type ] = FrmField::create(
		array(
			'form_id'     => $forms['formidable'],
			'type'        => $type,
			'name'        => $label,
			'description' => '',
			'required'    => 0,
			'field_order' => count( $forms['formidable_fields'] ?? array() ) + 1,
		)
	);
}
$components = array();
foreach ( array(
	'name'    => 'input_text',
	'email'   => 'input_email',
	'message' => 'textarea',
) as $name => $element ) {
	$components[] = array(
		'element'    => $element,
		'attributes' => array(
			'name'        => $name,
			'type'        => 'input_email' === $element ? 'email' : 'text',
			'value'       => '',
			'placeholder' => '',
			'class'       => '',
		),
		'settings'   => array(
			'label'              => ucfirst( $name ),
			'validation_rules'   => array(
				'required' => array(
					'value'   => false,
					'message' => 'Required',
				),
			),
			'conditional_logics' => array(
				'status'     => false,
				'conditions' => array(),
				'type'       => 'all',
			),
		),
		'index'      => count( $components ),
	);
}
$fluent               = \FluentForm\App\Models\Form::create(
	array(
		'title'       => 'Synthetic adapter fixture',
		'status'      => 'published',
		'form_fields' => wp_json_encode(
			array(
				'fields'       => $components,
				'submitButton' => array(
					'element'    => 'button',
					'attributes' => array( 'type' => 'submit' ),
					'settings'   => array( 'button_ui' => array( 'type' => 'default', 'text' => 'Send', 'img_url' => '' ) ),
				),
			)
		),
		'created_by'  => 1,
		'created_at'  => current_time( 'mysql' ),
		'updated_at'  => current_time( 'mysql' ),
		'type'        => 'form',
	)
);
$forms['fluentforms'] = (int) $fluent->id;
\FluentForm\App\Models\FormMeta::persist( $fluent->id, 'formSettings', \FluentForm\App\Models\Form::getFormsDefaultSettings() );
$ws              = new WS_Form_Form();
$forms['wsform'] = $ws->db_create();
$structure       = $ws->db_read( true, true );
$section         = $structure->groups[0]->sections[0]->id;
foreach ( array( 'text', 'email', 'textarea' ) as $type ) {
	$field                           = new WS_Form_Field();
	$field->form_id                  = $ws->id;
	$field->section_id               = $section;
	$field->type                     = $type;
	$forms['wsform_fields'][ $type ] = $field->db_create();
}
$ws->db_create_blank_submit();
$ws->db_publish();
update_option( 'rktsky_antispam_extra_test_forms', $forms, false );
echo "PASS: synthetic Formidable, Fluent and WS Form fixtures created.\n";
