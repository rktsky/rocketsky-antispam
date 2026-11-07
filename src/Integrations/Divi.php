<?php
/**
 * Gates Divi's same-page contact POST before module rendering and delivery.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Adds technical protection through documented D4 and D5 output boundaries.
 *
 * The reviewed original frontend transports serialize the form to its own page.
 * A template_redirect gate precedes template/module rendering; the late native
 * et_pb_contact_form_submit action is deliberately not used to stop delivery.
 * No native fields, recipient settings, captcha or mail callbacks are rewritten.
 *
 * @since 0.3.0
 */
final class Divi extends Adapter {

	/**
	 * Registers frontend hosts and the early same-page POST boundary.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_filter( 'et_module_shortcode_output', array( $this, 'render_shortcode' ), 20, 2 );
		add_filter( 'divi_module_wrapper_render', array( $this, 'render_module' ), 20, 2 );
		add_action( 'template_redirect', array( $this, 'validate' ), -1000 );
	}

	/**
	 * Adds descriptors to actual D4 contact forms without modifying builder arrays.
	 *
	 * @since 0.3.0
	 * @param mixed  $output Original frontend HTML or Visual Builder structure.
	 * @param string $slug Original shortcode module slug.
	 * @return mixed Original unrelated/builder output or protected native HTML.
	 */
	public function render_shortcode( $output, string $slug ) {
		return 'et_pb_contact_form' === $slug && is_string( $output ) ? $this->decorate( $output ) : $output;
	}

	/**
	 * Adds descriptors to D5's documented native contact module wrapper.
	 *
	 * @since 0.3.0
	 * @param string $output Original native frontend wrapper HTML.
	 * @param array  $args Native module context containing its registered name.
	 * @return string Original unrelated wrapper or protected contact form HTML.
	 */
	public function render_module( string $output, array $args ): string {
		return 'divi/contact-form' === ( $args['name'] ?? '' ) ? $this->decorate( $output ) : $output;
	}

	/**
	 * Stops a recognized same-page submission before the theme renders any modules.
	 *
	 * D4 uses numeric counters; D5 uses bounded module UUIDs. A fixed signed
	 * contact scope avoids accepting a client-chosen native ID as security scope.
	 * Native submit, field-list and nonce markers identify the same envelope;
	 * multiple distinct targets are rejected so one proof cannot fan out to forms.
	 * Only technical checks run: request-declared field types are not trusted for
	 * content rules or quarantine. Native nonce/captcha validation remains Divi's.
	 *
	 * @since 0.3.0
	 * @return void Successful proof returns; rejection terminates before delivery.
	 */
	public function validate(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( ! $this->settings->enabled( 'divi' ) || 'post' !== $method || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$targets = array();
		// Only names identify the native envelope; contact values are never read.
		foreach ( array_keys( $_POST ) as $name ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This public envelope selects no authority; Guard verifies its own local proof.
			if ( is_string( $name ) && strlen( $name ) <= 110 && preg_match( '/^(?:et_pb_contactform_submit_|et_pb_contact_email_fields_|_wpnonce-et-pb-contact-form-submitted-)([a-zA-Z0-9_-]{1,64})$/D', $name, $matches ) ) {
				$targets[ $matches[1] ] = true;
				if ( count( $targets ) > 1 ) {
					break;
				}
			}
		}
		if ( empty( $targets ) ) {
			return;
		}
		if ( count( $targets ) > 1 ) {
			$this->guard->reject( 'divi', 'invalid' );
			$this->reject( (string) array_key_first( $targets ) );
			return;
		}
		if ( '' !== $this->guard->check( 'divi', 'contact', $this->protection_controls(), array(), true ) ) {
			$this->reject( (string) array_key_first( $targets ) );
		}
	}

	/**
	 * Uses the WordPress HTML API to preserve native form structure and controls.
	 *
	 * The shared bridge creates and restores its own hidden controls inside each
	 * signed host. It handles retries after Divi's whole-container replacements.
	 *
	 * @since 0.3.0
	 * @param string $output Native module HTML.
	 * @return string HTML with escaped descriptors on native contact form tags.
	 */
	private function decorate( string $output ): string {
		$processor = new \WP_HTML_Tag_Processor( $output );
		while ( $processor->next_tag( 'FORM' ) ) {
			if ( ! $processor->has_class( 'et_pb_contact_form' ) ) {
				continue;
			}
			foreach ( $this->fields->attributes( 'divi', 'contact' ) as $name => $value ) {
				$processor->set_attribute( $name, $value );
			}
		}
		return $processor->get_updated_html();
	}

	/**
	 * Returns a visible local failure in the shape Divi's native load selects.
	 *
	 * The native jQuery.load needs successful HTML for its selector extraction;
	 * an AJAX rejection therefore uses HTTP200 with Divi's error marker, preventing
	 * a native success redirect. A normal full-page POST receives HTTP403. Neither
	 * response reflects field contents, request URLs, query strings or referrers.
	 * The reload link is generated from WordPress's resolved page configuration.
	 *
	 * @since 0.3.0
	 * @param string $target Bounded native numeric/UUID container suffix.
	 * @return void Terminates the current native page request through wp_die.
	 */
	private function reject( string $target ): void {
		$ajax    = isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && is_string( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && 'xmlhttprequest' === sanitize_key( wp_unslash( $_SERVER['HTTP_X_REQUESTED_WITH'] ) );
		$post_id = get_queried_object_id();
		$reload  = $post_id > 0 ? get_permalink( $post_id ) : home_url( '/' );
		if ( ! is_string( $reload ) || '' === $reload ) {
			$reload = home_url( '/' );
		}
		$html = '<div id="et_pb_contact_form_' . esc_attr( $target ) . '"><div class="et-pb-contact-message"><p class="et_pb_contact_error_text" role="alert">' . esc_html( $this->guard->message() ) . '</p><p><a href="' . esc_url( $reload ) . '">' . esc_html__( 'Reload the form', 'wp-antispam' ) . '</a></p></div></div>';
		nocache_headers();
		wp_die(
			$html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every dynamic fragment is escaped for its HTML context above.
			'WP Antispam',
			array(
				'response'  => $ajax ? 200 : 403,
				'back_link' => false,
			)
		);
	}
}
