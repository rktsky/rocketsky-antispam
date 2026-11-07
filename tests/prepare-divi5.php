<?php
/**
 * Creates synthetic native Divi 5 blocks and legacy/conversion pages in a marked site.
 *
 * The commercial original theme remains outside the repository and unmodified.
 *
 * @package RktskyAntispamTests
 * @since 0.3.1
 */
require __DIR__ . '/bootstrap.php';
if ( ! class_exists( '\ET\Builder\Packages\ModuleLibrary\ContactForm\ContactFormModule' ) || ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
	throw new RuntimeException( 'The original Divi 5 theme must be active.' );
}

if ( 'observer' === ( $argv[1] ?? '' ) ) {
	$private_directory = defined( 'RKTSKY_DIVI5_FIXTURE_LOG' ) && is_string( RKTSKY_DIVI5_FIXTURE_LOG ) && '' !== RKTSKY_DIVI5_FIXTURE_LOG ? realpath( dirname( RKTSKY_DIVI5_FIXTURE_LOG ) ) : false;
	$public_directory  = realpath( ABSPATH );
	if ( false === $private_directory || false === $public_directory || is_link( RKTSKY_DIVI5_FIXTURE_LOG ) || $private_directory === $public_directory || str_starts_with( $private_directory, $public_directory . DIRECTORY_SEPARATOR ) ) {
		throw new RuntimeException( 'Configure RKTSKY_DIVI5_FIXTURE_LOG in an existing private directory outside the fixture public root.' );
	}
	$destination = WPMU_PLUGIN_DIR . '/rktsky-antispam-divi5-observer.php';
	$source      = file_get_contents( __DIR__ . '/fixtures/divi5-observer.php' );
	if ( is_link( $destination ) || ( is_file( $destination ) && ! str_contains( file_get_contents( $destination ), 'function rktsky_divi5_event' ) ) ) {
		throw new RuntimeException( 'Refusing to replace an unknown existing MU plugin.' );
	}
	if ( false === $source || ! wp_mkdir_p( WPMU_PLUGIN_DIR ) || false === file_put_contents( $destination, $source ) ) {
		throw new RuntimeException( 'The owned disposable fixture observer could not be installed.' );
	}
	echo wp_json_encode( array( 'observer' => true ) ) . "\n";
	return;
}

/**
 * Serializes independently authored synthetic content with WordPress's native API.
 *
 * @since 0.3.1
 * @param string $name Original registered module/block name.
 * @param array  $attrs Native public module settings for this synthetic content.
 * @param array  $children Nested synthetic native block structures.
 * @return array WordPress block structure accepted by serialize_block().
 */
function rktsky_divi5_block( string $name, array $attrs = array(), array $children = array() ): array {
	return array(
		'blockName'    => $name,
		'attrs'        => $attrs,
		'innerBlocks'  => $children,
		'innerHTML'    => '',
		'innerContent' => array_fill( 0, count( $children ), null ),
	);
}

/**
 * Builds only synthetic fields using the original module's published attribute schema.
 *
 * @since 0.3.1
 * @param string $uuid Fixed unique module ID for fixture correlation, not a visitor ID.
 * @param bool   $captcha Whether the original local arithmetic captcha is enabled.
 * @return array Original module block with independently written fixture settings.
 */
function rktsky_divi5_form( string $uuid, bool $captcha = false ): array {
	$fields = array();
	foreach ( array(
		'name'    => array( 'Name', 'input' ),
		'email'   => array( 'Email', 'email' ),
		'message' => array( 'Message', 'text' ),
	) as $id => $field ) {
		$fields[] = rktsky_divi5_block(
			'divi/contact-field',
			array(
				'fieldItem' => array(
					'advanced'     => array(
						'id'       => array( 'desktop' => array( 'value' => $id ) ),
						'type'     => array( 'desktop' => array( 'value' => $field[1] ) ),
						'required' => array( 'desktop' => array( 'value' => 'on' ) ),
					),
					'innerContent' => array( 'desktop' => array( 'value' => $field[0] ) ),
				),
			)
		);
	}
	return rktsky_divi5_block(
		'divi/contact-form',
		array(
			'module' => array(
				'advanced' => array(
					'uniqueId'       => array( 'desktop' => array( 'value' => $uuid ) ),
					'successMessage' => array( 'desktop' => array( 'value' => 'Synthetic Divi 5 accepted.' ) ),
					'spamProtection' => array(
						'desktop' => array(
							'value' => array(
								'enabled'         => 'off',
								'useBasicCaptcha' => $captcha ? 'on' : 'off',
							),
						),
					),
				),
			),
			'title'  => array( 'innerContent' => array( 'desktop' => array( 'value' => 'Synthetic contact form' ) ) ),
			'email'  => array( 'advanced' => array( 'receiver' => array( 'desktop' => array( 'value' => 'fixture@example.invalid' ) ) ) ),
			'button' => array( 'innerContent' => array( 'desktop' => array( 'value' => array( 'text' => 'Submit synthetic Divi' ) ) ) ),
		),
		$fields
	);
}

$options                    = get_option( \Rktsky\Antispam\Settings::OPTION, array() );
$options['divi']            = true;
$options['minimum_seconds'] = 0;
update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
wp_set_current_user( 1 );
$fixtures = get_option( 'rktsky_antispam_test_divi5', array() );
$legacy   = '[et_pb_section][et_pb_row][et_pb_column type="4_4"][et_pb_contact_form email="fixture@example.invalid" captcha="off" title="Synthetic legacy form" success_message="Synthetic legacy accepted."][et_pb_contact_field field_id="Name" field_title="Name" field_type="input" required_mark="on"][/et_pb_contact_field][et_pb_contact_field field_id="Email" field_title="Email" field_type="email" required_mark="on"][/et_pb_contact_field][et_pb_contact_field field_id="Message" field_title="Message" field_type="text" required_mark="on"][/et_pb_contact_field][/et_pb_contact_form][/et_pb_column][/et_pb_row][/et_pb_section]';
$contents = array();
foreach ( array( 'native', 'captcha', 'multiple', 'unusual', 'long', 'duplicate', 'custom' ) as $name ) {
	$uuid  = 'unusual' === $name ? 'native/identifier' : ( 'long' === $name ? str_repeat( 'a', 80 ) : '11111111-1111-4111-8111-111111111111' );
	$forms = array( rktsky_divi5_form( $uuid, 'captcha' === $name ) );
	if ( 'duplicate' === $name ) {
		$forms[] = rktsky_divi5_form( $uuid ); }
	if ( 'custom' === $name ) {
		$forms[0]['attrs']['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = 'rktsky-custom-contact'; }
	if ( 'multiple' === $name ) {
		$forms[] = rktsky_divi5_form( '22222222-2222-4222-8222-222222222222' ); }
	$contents[ $name ] = serialize_block( rktsky_divi5_block( 'divi/section', array(), array( rktsky_divi5_block( 'divi/row', array(), array( rktsky_divi5_block( 'divi/column', array(), $forms ) ) ) ) ) );
}
$contents['legacy']   = $legacy;
$contents['migrated'] = \ET\Builder\Packages\Conversion\Conversion::maybeConvertContent( $legacy );
foreach ( $contents as $name => $content ) {
	$data = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Synthetic Divi 5 ' . $name,
		'post_content' => wp_slash( $content ),
	);
	if ( ! empty( $fixtures[ $name ] ) ) {
		$data['ID'] = $fixtures[ $name ]; }
	$id = wp_insert_post( $data, true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'Synthetic Divi 5 page creation failed.' ); }
	update_post_meta( $id, '_et_pb_use_builder', 'on' );
	update_post_meta( $id, '_et_builder_version', 'legacy' === $name ? 'VB|Divi|4.27.9' : 'VB|Divi|5.19.0' );
	update_post_meta( $id, '_et_pb_page_layout', 'et_full_width_page' );
	update_post_meta( $id, '_wp_page_template', 'page-template-blank.php' );
	$fixtures[ $name ] = (int) $id;
}
update_option( 'rktsky_antispam_test_divi5', $fixtures, false );
echo wp_json_encode(
	array(
		'theme'   => wp_get_theme()->get( 'Version' ),
		'builder' => ET_BUILDER_VERSION,
		'pages'   => $fixtures,
	)
) . "\n";
