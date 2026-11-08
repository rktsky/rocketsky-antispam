<?php
/**
 * Creates synthetic provider forms and a browser page in a disposable WordPress site.
 *
 * @package RktskyAntispamTests
 * @since 0.1.0
 */

require __DIR__ . '/bootstrap.php';
wp_set_current_user( 1 );

// Native-provider fixtures opt in explicitly, independently of product defaults.
$settings = new \Rktsky\Antispam\Settings();
update_option( \Rktsky\Antispam\Settings::OPTION, array_replace( $settings->all(), array( 'cf7' => true, 'gravityforms' => true, 'wpforms' => true ) ), false );

$forms = get_option( 'rktsky_antispam_test_forms', array() );
if ( ! empty( $forms ) ) {
	echo wp_json_encode( $forms ) . "\n";
	return;
}

$forms = array();
if ( class_exists( 'WPCF7_ContactForm' ) ) {
	$form = WPCF7_ContactForm::get_template();
	$form->set_title( 'Synthetic Rocketsky Antispam CF7 fixture' );
	$form->set_properties( array( 'form' => '<label>Name [text* your-name]</label><label>Email [email* your-email]</label><label>Message [textarea your-message]</label>[submit "Submit synthetic CF7"]', 'additional_settings' => 'skip_mail: on' ) );
	$forms['cf7'] = $form->save();
}

if ( class_exists( 'GFAPI' ) ) {
	$forms['gravityforms'] = GFAPI::add_form( array(
		'title' => 'Synthetic Rocketsky Antispam GF fixture',
		'description' => 'Disposable local test data only.',
		'fields' => array( new GF_Field_Text( array( 'id' => 1, 'label' => 'Name', 'isRequired' => true, 'formId' => 0 ) ) ),
		'button' => array( 'type' => 'text', 'text' => 'Submit synthetic GF' ),
		'confirmations' => array( array( 'id' => 'test-confirmation', 'name' => 'Default', 'isDefault' => true, 'type' => 'message', 'message' => 'Synthetic GF accepted.' ) ),
		'save' => array( 'enabled' => true, 'button' => array( 'text' => 'Save synthetic draft' ) ),
		'notifications' => array(),
		'enableHoneypot' => true,
	) );
	$forms['gravityforms_multi'] = GFAPI::add_form( array(
		'title' => 'Synthetic Rocketsky Antispam GF multipage fixture',
		'fields' => array(
			new GF_Field_Text( array( 'id' => 1, 'label' => 'First page name', 'isRequired' => true, 'pageNumber' => 1 ) ),
			new GF_Field_Page( array( 'id' => 2, 'label' => 'Second page', 'pageNumber' => 2, 'nextButton' => array( 'type' => 'text', 'text' => 'Next' ), 'previousButton' => array( 'type' => 'text', 'text' => 'Previous' ) ) ),
			new GF_Field_Text( array( 'id' => 3, 'label' => 'Second page message', 'isRequired' => true, 'pageNumber' => 2 ) ),
		),
		'pagination' => array( 'type' => 'steps', 'pages' => array( 'Name', 'Message' ), 'firstPageCssClass' => '', 'lastPageButton' => array( 'type' => 'text', 'text' => 'Previous' ) ),
		'button' => array( 'type' => 'text', 'text' => 'Submit synthetic multipage GF' ),
		'confirmations' => array( array( 'id' => 'test-multi-confirmation', 'name' => 'Default', 'isDefault' => true, 'type' => 'message', 'message' => 'Synthetic multipage GF accepted.' ) ),
		'notifications' => array(),
		'enableHoneypot' => true,
	) );
}

if ( function_exists( 'wpforms' ) ) {
	$forms['wpforms'] = wpforms()->obj( 'form' )->add( 'Synthetic Rocketsky Antispam WPForms fixture', array( 'post_content' => wp_json_encode( array(
		'field_id' => 2,
		'fields' => array( 1 => array( 'id' => '1', 'type' => 'text', 'label' => 'Name', 'required' => '1', 'size' => 'medium' ) ),
		'settings' => array( 'form_title' => 'Synthetic Rocketsky Antispam WPForms fixture', 'submit_text' => 'Submit synthetic WPForms', 'notification_enable' => '0', 'ajax_submit' => '0', 'antispam_v3' => '1', 'confirmations' => array( 1 => array( 'type' => 'message', 'message' => 'Synthetic WPForms accepted.' ) ) ),
	) ) ) );
	if ( $forms['wpforms'] ) {
		$definition = wpforms_decode( get_post_field( 'post_content', $forms['wpforms'] ) );
		$definition['id'] = $forms['wpforms'];
		wpforms()->obj( 'form' )->update( $forms['wpforms'], $definition );
	}
}

$content = '<h1>Rocketsky Antispam synthetic forms</h1>';
foreach ( $forms as $provider => $id ) {
	if ( is_wp_error( $id ) || ! $id ) {
		throw new RuntimeException( 'Synthetic form creation failed for ' . $provider );
	}
	$content .= '<h2>' . esc_html( $provider ) . '</h2>';
	if ( 'cf7' === $provider ) {
		$content .= '[contact-form-7 id="' . (int) $id . '"]';
	} elseif ( str_starts_with( $provider, 'gravityforms' ) ) {
		$content .= '[gravityform id="' . (int) $id . '" title="false" description="false" ajax="true"]';
	} elseif ( 'wpforms' === $provider ) {
		$content .= '[wpforms id="' . (int) $id . '" title="false"]';
	}
}
$forms['page'] = wp_insert_post( array( 'post_title' => 'Synthetic Rocketsky Antispam forms', 'post_content' => $content, 'post_status' => 'publish', 'post_type' => 'page', 'comment_status' => 'open' ) );
update_option( 'rktsky_antispam_test_forms', $forms, false );
echo wp_json_encode( $forms ) . "\n";
