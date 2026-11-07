<?php
/**
 * Protects final Formidable Forms submissions through native extension points.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Adds local proof without consuming it during Formidable's preliminary validation.
 *
 * @since 0.3.0
 */
final class Formidable extends Adapter {
	/**
	 * Registers inside-form rendering and the final native pre-creation gate.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'frm_entry_form', array( $this, 'render' ) );
		add_filter( 'frm_entries_before_create', array( $this, 'validate' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Renders signed controls inside the native form, including AJAX replacements.
	 *
	 * @since 0.3.0
	 * @param object $form Native stored form definition.
	 * @return void
	 */
	public function render( $form ): void {
		if ( is_object( $form ) && isset( $form->id ) && is_numeric( $form->id ) && (int) $form->id > 0 ) {
			echo $this->fields->markup( 'formidable', (string) (int) $form->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shared renderer escapes its complete inside-form controls.
		}
	}

	/**
	 * Adds a native visible error before final persistence and entry actions.
	 *
	 * Earlier errors stay intact. Preliminary AJAX and multipage validation do
	 * not consume the token; the native processing gate performs the one check.
	 * Direct server-side FrmEntry::create calls are outside this browser adapter.
	 *
	 * @since 0.3.0
	 * @param mixed  $errors Earlier native errors, sometimes an empty legacy string.
	 * @param object $form Native stored form selected by the provider.
	 * @return mixed Earlier errors or a native array containing the local retry error.
	 */
	public function validate( $errors, $form ) {
		if ( ! empty( $errors ) || ! is_object( $form ) || ! isset( $form->id ) || ! is_numeric( $form->id ) || (int) $form->id < 1 ) {
			return $errors;
		}
		$id     = (string) (int) $form->id;
		$posted = $this->posted();
		$data   = $this->content( $id, $posted );
		$reason = $this->guard->check( 'formidable', $id, $posted, $data['fields'] );
		if ( '' === $reason ) {
			return $errors;
		}
		$this->retain( 'formidable', $id, $reason, $data );
		$errors                    = is_array( $errors ) ? $errors : array();
		$errors['rktsky_antispam'] = $this->guard->message();
		return $errors;
	}

	/**
	 * Selects declared text inputs from the native form instead of copying item_meta.
	 *
	 * Embedded/repeating forms and arbitrary compound keys are excluded. Name
	 * parts and flat choice indexes are explicit; hidden/password/file/payment
	 * types and administrator-only fields never enter rules or quarantine.
	 *
	 * @since 0.3.0
	 * @param string $id Native positive form ID.
	 * @param array  $posted Unslashed native request.
	 * @return array{fields:array<string,string>,email:string} Bounded content selection.
	 */
	private function content( string $id, array $posted ): array {
		$data        = array(
			'fields' => array(),
			'email'  => '',
		);
		$values      = isset( $posted['item_meta'] ) && is_array( $posted['item_meta'] ) ? $posted['item_meta'] : array();
		$definitions = \FrmField::get_all_for_form( (int) $id, 200, 'exclude', 'exclude' );
		foreach ( is_array( $definitions ) ? $definitions : array() as $field ) {
			if ( ! is_object( $field ) || ! isset( $field->id, $field->type ) || ! in_array( $field->type, array( 'text', 'textarea', 'email', 'url', 'phone', 'number', 'date', 'time', 'select', 'radio', 'checkbox', 'name' ), true ) || ! empty( $field->field_options['admin_only'] ) || ! isset( $values[ $field->id ] ) ) {
				continue;
			}
			$value = $values[ $field->id ];
			if ( is_array( $value ) ) {
				if ( ! in_array( $field->type, array( 'name', 'checkbox', 'select' ), true ) ) {
					continue;
				}
				$parts = array();
				foreach ( array_slice( $value, 0, 30, true ) as $key => $part ) {
					$known = 'name' === $field->type ? in_array( (string) $key, array( 'first', 'middle', 'last' ), true ) : ctype_digit( (string) $key );
					if ( $known && is_scalar( $part ) ) {
						$parts[] = sanitize_text_field( substr( (string) $part, 0, 4096 ) );
					}
				}
				$value = implode( ', ', $parts );
			} elseif ( is_scalar( $value ) ) {
				$value = 'textarea' === $field->type ? sanitize_textarea_field( substr( (string) $value, 0, 4096 ) ) : sanitize_text_field( substr( (string) $value, 0, 4096 ) );
			} else {
				continue;
			}
			$label = isset( $field->name ) && is_string( $field->name ) ? $field->name : 'Field';
			$this->add_content( $data, (string) $field->id . ': ' . $label, $value, 'email' === $field->type );
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}
		return $data;
	}
}
