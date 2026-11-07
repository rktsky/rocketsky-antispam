<?php
/**
 * Gates native Divi contact POST before frontend, REST or AJAX rendering.
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
 * The wp_loaded gate precedes native frontend, REST and AJAX rendering; the native
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
		add_action( 'wp_loaded', array( $this, 'validate' ), 100 );
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
	 * Stops native contact submissions before any WordPress rendering path can deliver them.
	 *
	 * D4 uses numeric counters; D5 uses bounded module UUIDs. A fixed signed
	 * contact scope avoids accepting a client-chosen native ID as security scope.
	 * All reserved native prefixes select the guard, even with unsupported IDs.
	 * Native submit, field-list and nonce markers identify the same envelope;
	 * multiple distinct targets are rejected so one proof cannot fan out to forms.
	 * REST method overrides affect the REST object, not the original POST envelope.
	 * No admin/REST exemption can authorize delivery during content rendering.
	 * Only technical checks run: request-declared field types are not trusted for
	 * content rules or quarantine. Native nonce/captcha validation remains Divi's.
	 *
	 * @since 0.3.0
	 * @since 0.3.1 Moved ahead of all native rendering and rejects unsupported envelope IDs.
	 * @return void Successful proof returns; rejection terminates before delivery.
	 */
	public function validate(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( ! $this->settings->enabled( 'divi' ) || 'post' !== $method ) {
			return;
		}
		$targets  = array();
		$invalid  = false;
		$prefixes = array( 'et_pb_contactform_submit_', 'et_pb_contact_email_fields_', '_wpnonce-et-pb-contact-form-submitted-' );
		// Only names identify the native envelope; contact values are never read.
		foreach ( array_keys( $_POST ) as $name ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reserved names select protection, never authorization; Guard verifies the proof.
			if ( ! is_string( $name ) ) {
				continue;
			}
			foreach ( $prefixes as $prefix ) {
				if ( ! str_starts_with( $name, $prefix ) ) {
					continue;
				}
				$length = strlen( $name ) - strlen( $prefix );
				if ( $length < 1 || $length > 64 ) {
					$invalid = true;
					break 2;
				}
				$suffix = substr( $name, strlen( $prefix ) );
				if ( 1 !== preg_match( '/^[a-zA-Z0-9_-]{1,64}$/D', $suffix ) ) {
					$invalid = true;
					break 2;
				}
				$targets[ $suffix ] = true;
				if ( count( $targets ) > 1 ) {
					break 2;
				}
			}
		}
		if ( ! $invalid && empty( $targets ) ) {
			return;
		}
		if ( $invalid || count( $targets ) > 1 ) {
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
		$target    = '';
		while ( $processor->next_tag() ) {
			if ( $processor->has_class( 'et_pb_contact_form_container' ) ) {
				$target = $this->target_id( $processor->get_attribute( 'id' ) );
			}
			if ( 'FORM' !== $processor->get_tag() || ! $processor->has_class( 'et_pb_contact_form' ) ) {
				continue;
			}
			foreach ( $this->fields->attributes( 'divi', 'contact' ) as $name => $value ) {
				$processor->set_attribute( $name, $value );
			}
			if ( '' !== $target ) {
				$processor->set_attribute( 'data-rk-antispam-target', $target );
			}
			$page = get_the_ID();
			if ( $page > 0 && is_post_publicly_viewable( $page ) ) {
				$processor->set_attribute( 'data-rk-antispam-page', (string) $page );
			}
		}
		return $processor->get_updated_html();
	}

	/**
	 * Bounds an HTML identity used solely for native error-fragment selection.
	 *
	 * This presentation value never changes token scope, delivery or permissions.
	 * Contextual output escaping remains necessary even for a valid identity.
	 *
	 * @since 0.3.1
	 * @param mixed $value Native or submitted container identity.
	 * @return string Bounded identity or empty for invalid input.
	 */
	private function target_id( $value ): string {
		if ( ! is_string( $value ) || '' === $value || strlen( $value ) > 256 ) {
			return '';
		}
		if ( wp_check_invalid_utf8( $value, true ) !== $value || 0 !== preg_match( '/[\x00-\x20\x7f]/', $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Bounds a fixed display field before unslashing, without reading contact data.
	 *
	 * The caller still validates its intended shape and escapes at output. These
	 * internal field names affect only error presentation, never authorization.
	 *
	 * @since 0.3.1
	 * @param string $name Fixed owned field name selected by this class.
	 * @param int    $maximum Maximum raw byte length.
	 * @return string Bounded unslashed text, or empty for malformed input.
	 */
	private function display_field( string $name, int $maximum ): string {
		$value = isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ? $_POST[ $name ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Raw size is bounded before unslashing; the caller validates the shape and escapes all output. No authorization uses these display fields.
		return strlen( $value ) <= $maximum ? wp_unslash( $value ) : '';
	}

	/**
	 * Returns a visible local failure in the shape Divi's native load selects.
	 *
	 * The native jQuery.load needs successful HTML for its selector extraction;
	 * an AJAX rejection therefore uses HTTP200 with Divi's error marker, preventing
	 * a native success redirect. A normal full-page POST receives HTTP403. Neither
	 * response reflects field contents, request URLs, query strings or referrers.
	 * The display-only page reference must be publicly viewable before Core generates
	 * its permalink; private slugs are never disclosed by this early response.
	 *
	 * @since 0.3.0
	 * @param string $target Bounded native numeric/UUID container suffix.
	 * @return void Terminates the current native page request through wp_die.
	 */
	private function reject( string $target ): void {
		$ajax             = isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && is_string( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && 'xmlhttprequest' === sanitize_key( wp_unslash( $_SERVER['HTTP_X_REQUESTED_WITH'] ) );
		$submitted_target = $this->display_field( 'rktsky_antispam_target', 512 );
		$container        = $this->target_id( $submitted_target );
		if ( '' === $container ) {
			$container = 'et_pb_contact_form_' . $target;
		}
		$page_value = $this->display_field( 'rktsky_antispam_page', 10 );
		$post_id    = 1 === preg_match( '/^[1-9][0-9]{0,9}$/D', $page_value ) ? absint( $page_value ) : 0;
		$reload     = $post_id > 0 && is_post_publicly_viewable( $post_id ) ? get_permalink( $post_id ) : home_url( '/' );
		if ( ! is_string( $reload ) || '' === $reload ) {
			$reload = home_url( '/' );
		}
		$html = '<div id="' . esc_attr( $container ) . '"><div class="et-pb-contact-message"><p class="et_pb_contact_error_text" role="alert">' . esc_html( $this->guard->message() ) . '</p><p><a href="' . esc_url( $reload ) . '">' . esc_html__( 'Reload the form', 'wp-antispam' ) . '</a></p></div></div>';
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
