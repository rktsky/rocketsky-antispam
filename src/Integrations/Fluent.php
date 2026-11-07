<?php
/**
 * Protects Fluent Forms before its submission storage and integration actions.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps serialized technical proof separate from Fluent's persisted field collection.
 *
 * @since 0.3.0
 */
final class Fluent extends Adapter {
	/**
	 * Registers native rendering and the shared final validation filter.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'fluentform/form_element_start', array( $this, 'render' ) );
		add_filter( 'fluentform/validation_errors', array( $this, 'validate' ), PHP_INT_MAX, 4 );
	}

	/**
	 * Adds controls after the actual native opening form tag.
	 *
	 * @since 0.3.0
	 * @param object $form Native form definition.
	 * @return void
	 */
	public function render( $form ): void {
		if ( is_object( $form ) && isset( $form->id ) && is_numeric( $form->id ) && (int) $form->id > 0 ) {
			echo $this->fields->markup( 'fluentforms', (string) (int) $form->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shared renderer escapes its complete inside-form controls.
		}
	}

	/**
	 * Returns native validation errors before insertion, mail and form actions.
	 *
	 * Existing errors are retained. Fluent already removes undeclared controls
	 * from formData, so proof is read only from its original serialized transport.
	 * Proof controls are never added to Fluent's saved response collection.
	 *
	 * @since 0.3.0
	 * @param array  $errors Earlier native validation errors.
	 * @param array  $form_data Native sanitized, declared field values.
	 * @param object $form Native server-selected form definition.
	 * @param array  $definitions Native parsed essential field definitions.
	 * @return array Native errors including any local retry instruction.
	 */
	public function validate( array $errors, array $form_data, $form, array $definitions ): array {
		if ( $errors || ! is_object( $form ) || ! isset( $form->id ) || ! is_numeric( $form->id ) || (int) $form->id < 1 ) {
			return $errors;
		}
		$id     = (string) (int) $form->id;
		$posted = $this->proof( $this->posted() );
		$data   = $this->content( $definitions, $form_data );
		$reason = $this->guard->check( 'fluentforms', $id, $posted, $data['fields'] );
		if ( '' !== $reason ) {
			$this->retain( 'fluentforms', $id, $reason, $data );
			$errors['rktsky_antispam'] = array( $this->guard->message() );
		}
		return $errors;
	}

	/**
	 * Reads only reserved protection controls from Fluent's URL-encoded data field.
	 *
	 * Bounded decoding avoids creating or retaining a second complete payload.
	 * Unknown names and their values are never decoded. Duplicated technical
	 * names are invalid shapes, never an alternative proof-selection mechanism.
	 *
	 * @since 0.3.0
	 * @param array $request Unslashed WordPress POST envelope.
	 * @return array Reserved controls; malformed/oversized envelopes fail closed.
	 */
	private function proof( array $request ): array {
		$raw = $request['data'] ?? '';
		if ( ! is_string( $raw ) || strlen( $raw ) > 1048576 ) {
			return array();
		}
		$proof = array();
		foreach ( explode( '&', $raw, 4097 ) as $pair ) {
			$equals = strpos( $pair, '=' );
			$name   = urldecode( false === $equals ? $pair : substr( $pair, 0, $equals ) );
			if ( str_starts_with( $name, 'rktsky_antispam_token[' ) || 1 === preg_match( '/^rktsky_as_[a-f0-9]{20}\[/D', $name ) ) {
				return array();
			}
			if ( 'rktsky_antispam_token' !== $name && 1 !== preg_match( '/^rktsky_as_[a-f0-9]{20}$/D', $name ) ) {
				continue;
			}
			if ( array_key_exists( $name, $proof ) ) {
				return array();
			}
			$value          = false === $equals ? '' : urldecode( substr( $pair, $equals + 1, 1201 ) );
			$proof[ $name ] = strlen( $value ) <= 400 ? $value : array();
		}
		return $proof;
	}

	/**
	 * Selects values only when parsed native definitions and raw field types agree.
	 *
	 * No unknown, hidden, password, upload, address or payment types are copied.
	 * Name parts are limited to the declared standard keys; choice arrays accept
	 * flat numeric indexes only. Formatting never changes Fluent's own formData.
	 *
	 * @since 0.3.0
	 * @param array $definitions Native essential definitions, including raw metadata.
	 * @param array $values Native sanitized field collection.
	 * @return array{fields:array<string,string>,email:string} Bounded selected values.
	 */
	private function content( array $definitions, array $values ): array {
		$data = array(
			'fields' => array(),
			'email'  => '',
		);
		foreach ( array_slice( $definitions, 0, 200, true ) as $name => $field ) {
			if ( ! is_string( $name ) || ! is_array( $field ) || ! isset( $field['element'], $field['raw']['element'], $values[ $name ] ) || $field['element'] !== $field['raw']['element'] || ! in_array( $field['element'], array( 'input_text', 'textarea', 'input_email', 'input_url', 'input_name', 'input_number', 'input_date', 'select', 'input_radio', 'input_checkbox' ), true ) ) {
				continue;
			}
			$type  = $field['element'];
			$value = $values[ $name ];
			if ( is_array( $value ) ) {
				if ( ! in_array( $type, array( 'input_name', 'select', 'input_checkbox' ), true ) ) {
					continue;
				}
				$parts = array();
				foreach ( array_slice( $value, 0, 30, true ) as $key => $part ) {
					$known = 'input_name' === $type ? in_array( (string) $key, array( 'first_name', 'middle_name', 'last_name' ), true ) : ctype_digit( (string) $key );
					if ( $known && is_scalar( $part ) ) {
						$parts[] = (string) $part;
					}
				}
				$value = $parts;
			} elseif ( ! is_scalar( $value ) ) {
				continue;
			}
			$label = isset( $field['raw']['settings']['label'] ) && is_string( $field['raw']['settings']['label'] ) ? $field['raw']['settings']['label'] : $name;
			$this->add_content( $data, $name . ': ' . $label, $value, 'input_email' === $type );
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}
		return $data;
	}
}
