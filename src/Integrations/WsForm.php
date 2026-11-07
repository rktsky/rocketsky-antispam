<?php
/**
 * Protects WS Form through native render descriptors and pre-action validation.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Survives WS Form's client-rendered canvas without changing stored form definitions.
 *
 * @since 0.3.0
 */
final class WsForm extends Adapter {
	/**
	 * Registers retained host descriptors and the native pre-persistence validator.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wsf_shortcode', array( $this, 'render' ), PHP_INT_MAX );
		add_filter( 'wsf_submit_validate', array( $this, 'validate' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Adds escaped signed attributes to actual WS form hosts.
	 *
	 * WS replaces the inner canvas on render/reload. The shared browser service
	 * recreates inside-form controls from these persistent host attributes. The
	 * native HTML processor handles attribute escaping without reparsing values.
	 *
	 * @since 0.3.0
	 * @param string $html Native shortcode output, potentially containing more than one form.
	 * @return string Native markup with signed descriptors on actual form elements only.
	 */
	public function render( string $html ): string {
		$processor = new \WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag(
			array(
				'tag_name'   => 'FORM',
				'class_name' => 'wsf-form',
			)
		) ) {
			$id = $processor->get_attribute( 'data-id' );
			if ( ! is_string( $id ) || 1 !== preg_match( '/^[1-9][0-9]{0,18}$/D', $id ) ) {
				continue;
			}
			foreach ( $this->fields->attributes( 'wsform', $id ) as $name => $value ) {
				$processor->set_attribute( $name, $value );
			}
		}
		return $processor->get_updated_html();
	}

	/**
	 * Returns native visible validation actions before submission persistence/actions.
	 *
	 * Earlier actions remain intact. The same guard is required for submit and
	 * save paths when the provider calls this filter; no authenticated API bypass
	 * is added. Original Lite's browser path is the verified submission contract.
	 *
	 * @since 0.3.0
	 * @param array  $errors Earlier native validation actions.
	 * @param string $mode Native posting mode, deliberately not used to skip protection.
	 * @param object $submission Native prepared submission and stored form definition.
	 * @return array Existing actions plus a local visible retry message on rejection.
	 */
	public function validate( array $errors, $mode, $submission ): array {
		if ( $errors || ! is_object( $submission ) || ! isset( $submission->form_id, $submission->form_object ) || ! is_numeric( $submission->form_id ) || (int) $submission->form_id < 1 || ! is_object( $submission->form_object ) ) {
			return $errors;
		}
		$id     = (string) (int) $submission->form_id;
		$posted = $this->posted();
		$data   = $this->content( $submission );
		$reason = $this->guard->check( 'wsform', $id, $posted, $data['fields'] );
		if ( '' !== $reason ) {
			$this->retain( 'wsform', $id, $reason, $data );
			$errors[] = array(
				'action'    => 'message',
				'message'   => $this->guard->message(),
				'type'      => 'danger',
				'form_hide' => false,
				'duration'  => '',
			);
		}
		return $errors;
	}

	/**
	 * Matches native stored definitions to prepared public field metadata.
	 *
	 * Protected metadata is never inspected. Hidden, password, upload, signature
	 * and payment types are excluded. Repeating values require declared matching
	 * IDs/types and are bounded; provider fallback duplicates are not copied.
	 *
	 * @since 0.3.0
	 * @param object $submission Native prepared submission.
	 * @return array{fields:array<string,string>,email:string} Selected public text values.
	 */
	private function content( $submission ): array {
		$data        = array(
			'fields' => array(),
			'email'  => '',
		);
		$definitions = \WS_Form_Common::get_fields_from_form( $submission->form_object, true );
		if ( ! is_array( $definitions ) || ! isset( $submission->meta ) || ! is_array( $submission->meta ) ) {
			return $data;
		}
		$allowed = array();
		foreach ( array_slice( $definitions, 0, 200, true ) as $field ) {
			if ( is_object( $field ) && isset( $field->id, $field->type ) && is_numeric( $field->id ) && in_array( $field->type, array( 'text', 'textarea', 'email', 'url', 'tel', 'number', 'datetime', 'select', 'radio', 'checkbox' ), true ) ) {
				$allowed[ (int) $field->id ] = $field;
			}
		}
		foreach ( array_slice( $submission->meta, 0, 200, true ) as $key => $meta ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^field_([1-9][0-9]{0,18})(?:_([0-9]+))?$/D', $key, $parts ) || ! is_array( $meta ) || ! isset( $meta['id'], $meta['type'], $meta['value'] ) || ( ! is_int( $meta['id'] ) && ! is_string( $meta['id'] ) ) || ! empty( $meta['db_ignore'] ) || (string) $parts[1] !== (string) $meta['id'] || ! isset( $allowed[ (int) $meta['id'] ] ) ) {
				continue;
			}
			$field = $allowed[ (int) $meta['id'] ];
			if ( $field->type !== $meta['type'] ) {
				continue;
			}
			$value = $meta['value'];
			if ( is_array( $value ) ) {
				if ( ! in_array( $field->type, array( 'checkbox', 'select' ), true ) ) {
					continue;
				}
				$choices = array();
				foreach ( array_slice( $value, 0, 30, true ) as $index => $choice ) {
					if ( ctype_digit( (string) $index ) && is_scalar( $choice ) ) {
						$choices[] = (string) $choice;
					}
				}
				$value = $choices;
			} elseif ( ! is_scalar( $value ) ) {
				continue;
			}
			$label = isset( $field->label ) && is_string( $field->label ) ? $field->label : 'Field';
			$this->add_content( $data, $key . ': ' . $label, $value, 'email' === $field->type );
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}
		return $data;
	}
}
