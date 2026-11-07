<?php
/**
 * Creates native Divi 4 shortcode forms through WordPress APIs in a marked fixture.
 *
 * The theme and standalone-builder installations are tested independently.
 * No original provider file or Core/provider database table is edited directly.
 *
 * @package RktskyAntispamTests
 * @since 0.3.1
 */

require __DIR__ . '/bootstrap.php';
wp_set_current_user( 1 );
if ( ! defined( 'ET_BUILDER_VERSION' ) || '4.27.9' !== ET_BUILDER_VERSION ) {
	throw new RuntimeException( 'The unchanged Divi 4.27.9 theme or builder is required.' );
}

if ( 'observer' === ( $argv[1] ?? '' ) ) {
	$log_directory = defined( 'RKTSKY_DIVI4_FIXTURE_LOG' ) ? realpath( dirname( RKTSKY_DIVI4_FIXTURE_LOG ) ) : false;
	$public_root   = realpath( ABSPATH );
	if ( false === $log_directory || false === $public_root || $log_directory === $public_root || str_starts_with( $log_directory, $public_root . DIRECTORY_SEPARATOR ) || is_link( RKTSKY_DIVI4_FIXTURE_LOG ) ) {
		throw new RuntimeException( 'Configure RKTSKY_DIVI4_FIXTURE_LOG in a private existing directory outside the fixture public root.' );
	}
	$destination = WPMU_PLUGIN_DIR . '/rktsky-antispam-divi4-observer.php';
	$source      = file_get_contents( __DIR__ . '/fixtures/divi4-observer.php' );
	if ( is_link( $destination ) || ( is_file( $destination ) && ! str_contains( file_get_contents( $destination ), 'function rktsky_antispam_divi4_fixture_event' ) ) ) {
		throw new RuntimeException( 'Refusing to replace an unknown existing MU plugin.' );
	}
	if ( false === $source || ! wp_mkdir_p( WPMU_PLUGIN_DIR ) || false === file_put_contents( $destination, $source ) ) {
		throw new RuntimeException( 'The owned disposable fixture observer could not be installed.' );
	}
	echo wp_json_encode( array( 'observer' => true ) ) . "\n";
	return;
}

if ( 'minimum' === ( $argv[1] ?? '' ) ) {
	$options                    = ( new \Rktsky\Antispam\Settings() )->all();
	$options['minimum_seconds'] = min( 10, max( 0, (int) ( $argv[2] ?? 0 ) ) );
	update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
	echo wp_json_encode( array( 'minimum' => $options['minimum_seconds'] ) ) . "\n";
	return;
}
if ( 'enabled' === ( $argv[1] ?? '' ) ) {
	$options         = ( new \Rktsky\Antispam\Settings() )->all();
	$options['divi'] = '1' === ( $argv[2] ?? '' );
	update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
	echo wp_json_encode( array( 'enabled' => $options['divi'] ) ) . "\n";
	return;
}
if ( 'state' === ( $argv[1] ?? '' ) ) {
	echo wp_json_encode(
		array(
			'pages'   => get_option( 'rktsky_antispam_divi4_forms', array() ),
			'origin'  => home_url(),
			'minimum' => ( new \Rktsky\Antispam\Settings() )->all()['minimum_seconds'],
		)
	) . "\n";
	return;
}

/**
 * Builds synthetic shortcode content accepted by the original native renderer.
 *
 * @since 0.3.1
 * @param string $id Optional administrator-selected native CSS ID.
 * @param string $title Synthetic form title.
 * @return string Own test content, using only documented shortcode attributes.
 */
function rktsky_antispam_divi4_form( string $id = '', string $title = 'Synthetic Divi form' ): string {
	return '[et_pb_contact_form title="' . esc_attr( $title ) . '" module_id="' . esc_attr( $id ) . '" email="fixture@example.invalid" captcha="off" use_spam_service="off" success_message="Synthetic Divi accepted." submit_button_text="Send synthetic"]'
		. '[et_pb_contact_field field_id="name" field_title="Name" field_type="input" required_mark="on"][/et_pb_contact_field]'
		. '[et_pb_contact_field field_id="email" field_title="Email" field_type="email" required_mark="on"][/et_pb_contact_field]'
		. '[et_pb_contact_field field_id="message" field_title="Message" field_type="text" required_mark="on"][/et_pb_contact_field]'
		. '[/et_pb_contact_form]';
}

$options = ( new \Rktsky\Antispam\Settings() )->defaults();
foreach ( \Rktsky\Antispam\Providers::ids() as $provider ) {
	$options[ $provider ] = false;
}
$options['divi']            = true;
$options['minimum_seconds'] = 0;
update_option( \Rktsky\Antispam\Settings::OPTION, $options, false );
update_option( 'blog_public', 0 );
$forms = get_option( 'rktsky_antispam_divi4_forms', array() );
foreach ( array(
	'plain'    => rktsky_antispam_divi4_form(),
	'custom'   => rktsky_antispam_divi4_form( 'rk-fixture-sales' ),
	'multiple' => rktsky_antispam_divi4_form( '', 'Synthetic first form' ) . rktsky_antispam_divi4_form( '', 'Synthetic second form' ),
) as $name => $content ) {
	$content = '[et_pb_section][et_pb_row][et_pb_column type="4_4"]' . $content . '[/et_pb_column][/et_pb_row][/et_pb_section]';
	$post    = array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'post_title'     => 'Synthetic Divi 4 ' . $name,
		'post_content'   => $content,
		'comment_status' => 'closed',
	);
	if ( isset( $forms[ $name ] ) && get_post( (int) $forms[ $name ] ) ) {
		$post['ID'] = (int) $forms[ $name ];
	}
	$id = wp_insert_post( $post, true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'Synthetic page creation failed.' );
	}
	update_post_meta( $id, '_et_pb_use_builder', 'on' );
	update_post_meta( $id, '_et_pb_page_layout', 'et_full_width_page' );
	$forms[ $name ] = $id;
}
update_option( 'rktsky_antispam_divi4_forms', $forms, false );
echo wp_json_encode(
	array(
		'version' => ET_BUILDER_VERSION,
		'origin'  => home_url(),
		'pages'   => $forms,
	)
) . "\n";
