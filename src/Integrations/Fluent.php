<?php
/**
 * Protects Fluent Forms before its submission storage and integration actions.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

use Rktsky\Antispam\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps serialized technical proof separate from Fluent's persisted field collection.
 *
 * @since 0.3.0
 */
final class Fluent extends Adapter {
	/**
	 * One-use permits containing only selected text and bounded technical evidence.
	 *
	 * @since 0.5.0
	 * @var array<int,array{proof:string,data:array,reason:string}>
	 */
	private array $native_spam = array();

	/**
	 * Validated native submissions awaiting a real persisted entry identifier.
	 *
	 * @since 0.5.0
	 * @var array<int,string> Form ID to one exact proof digest.
	 */
	private array $observed = array();

	/**
	 * Detects the reviewed, documented provider model API without issuing queries.
	 *
	 * @since 0.5.0
	 * @return bool Whether a native spam record can be saved through the provider.
	 */
	public static function native_available(): bool {
		return defined( 'FLUENTFORM_VERSION' ) && version_compare( FLUENTFORM_VERSION, '6.2.17', '>=' ) && class_exists( '\\FluentForm\\App\\Models\\Submission' ) && method_exists( '\\FluentForm\\App\\Models\\Submission', 'save' ) && class_exists( '\\FluentForm\\Framework\\Validator\\ValidationException' );
	}
	/**
	 * Registers native rendering and the shared final validation filter.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'fluentform/form_element_start', array( $this, 'render' ) );
		add_filter( 'fluentform/validation_errors', array( $this, 'validate' ), PHP_INT_MAX, 4 );
		add_action( 'fluentform/before_insert_submission', array( $this, 'store_spam' ), PHP_INT_MAX, 3 );
		add_action( 'fluentform/submission_inserted', array( $this, 'created' ), PHP_INT_MAX, 3 );
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
		$id = (string) (int) $form->id;
		unset( $this->native_spam[ (int) $id ], $this->observed[ (int) $id ] );
		$posted = $this->proof( $this->posted() );
		$data   = $this->content( $definitions, $form_data );
		$reason = $this->guard->check( 'fluentforms', $id, $posted, $data['fields'] );
		if ( '' === $reason && $this->settings->enabled( 'fluentforms' ) ) {
			$this->observed[ (int) $id ] = $this->fingerprint( $id, $posted );
		}
		if ( '' !== $reason ) {
			if ( Guard::is_content_reason( $reason ) && self::native_available() ) {
				$this->native_spam[ (int) $id ] = array(
					'proof'  => $this->fingerprint( $id, $posted ),
					'data'   => $data,
					'reason' => $reason,
				);
				return $errors;
			}
			$this->retain( 'fluentforms', $id, $reason, $data );
			$errors['rktsky_antispam'] = array( Guard::is_content_reason( $reason ) ? $this->guard->spam_message() : $this->guard->message() );
		}
		return $errors;
	}

	/**
	 * Observes accepted native entries only after this exact proof passed the guard.
	 *
	 * Historical imports and unrelated API creations have no local observation and
	 * remain outside the dataset. The provider's public read API supplies final status.
	 *
	 * @since 0.5.0
	 * @param int    $entry_id Persisted native ID.
	 * @param array  $data Provider values, deliberately not copied.
	 * @param object $form Native form definition.
	 * @return void
	 */
	public function created( $entry_id, array $data, $form ): void {
		if ( ! self::native_available() ) {
			return;
		}
		$id    = is_object( $form ) && isset( $form->id ) ? (int) $form->id : 0;
		$proof = $this->observed[ $id ] ?? '';
		unset( $this->observed[ $id ] );
		if ( '' === $proof || ! hash_equals( $proof, $this->fingerprint( (string) $id, $this->proof( $this->posted() ) ) ) || ! function_exists( 'fluentFormApi' ) ) {
			return;
		}
		$entry = fluentFormApi( 'submissions' )->find( (int) $entry_id );
		if ( ! is_object( $entry ) || (int) $entry->form_id !== $id ) {
			return;
		}
		$spam = 'spam' === $entry->status;
		if ( $spam ) {
			$this->guard->observe_spam( 'fluentforms', (string) $id, $this->token_evidence( $this->proof( $this->posted() ) ) );
		}
		if ( $this->feedback ) {
			$this->feedback->track( 'fluentforms', (int) $entry_id, $spam );
		}
	}

	/**
	 * Saves a content rejection after native honeypot/token checks and before actions.
	 *
	 * Fluent's documented before_insert_submission action follows its priority-nine
	 * native checks and precedes payment, notification and integration dispatch.
	 * Its documented model save API stores selected declared response fields only;
	 * no query-builder table access, raw SQL, provider impersonation or normal
	 * submission-complete hooks are used. Throwing the native validation exception
	 * stops the ordinary insert and returns an actual error instead of a redirect.
	 *
	 * @since 0.5.0
	 * @param array  $insert_data Prepared native metadata, never persisted wholesale.
	 * @param array  $raw Native parsed controls including technical proof.
	 * @param object $form Server-selected form.
	 * @return void Unrelated valid submissions continue without modification.
	 * @throws \FluentForm\Framework\Validator\ValidationException For this content rejection or changed proof.
	 */
	public function store_spam( array $insert_data, array $raw, $form ): void {
		$id = is_object( $form ) && isset( $form->id ) ? (int) $form->id : 0;
		if ( ! isset( $this->native_spam[ $id ] ) ) {
			return;
		}
		$permit = $this->native_spam[ $id ];
		unset( $this->native_spam[ $id ] );
		if ( ! hash_equals( $permit['proof'], $this->fingerprint( (string) $id, $raw ) ) || (int) ( $insert_data['form_id'] ?? 0 ) !== $id ) {
			throw new \FluentForm\Framework\Validator\ValidationException( '', 422, null, array( 'errors' => array( 'rktsky_antispam' => array( esc_html( $this->guard->message() ) ) ) ) );
		}
		$entry                = new \FluentForm\App\Models\Submission();
		$entry->form_id       = $id;
		$entry->serial_number = isset( $insert_data['serial_number'] ) ? absint( $insert_data['serial_number'] ) : null;
		$entry->response      = wp_json_encode( $permit['data']['response'] );
		$entry->status        = 'spam';
		$entry->created_at    = current_time( 'mysql' );
		$entry->updated_at    = $entry->created_at;
		$saved                = false;
		try {
			$saved = $entry->save();
		} catch ( \Throwable $error ) {
			// No exception contents enter output or logs; a populated ID prevents duplicates.
			$saved = (int) $entry->id > 0;
		}
		if ( $saved && (int) $entry->id > 0 ) {
			if ( $this->feedback ) {
				$this->feedback->track( 'fluentforms', (int) $entry->id, true );
			}
		} else {
			$this->retain( 'fluentforms', (string) $id, $permit['reason'], $permit['data'] );
		}
		throw new \FluentForm\Framework\Validator\ValidationException( '', 422, null, array( 'errors' => array( 'rktsky_antispam' => array( esc_html( $this->guard->spam_message() ) ) ) ) );
	}

	/**
	 * Matches a later native insert callback to one exact validated token and form.
	 *
	 * @since 0.5.0
	 * @param string $id Native form ID.
	 * @param array  $controls Parsed technical controls.
	 * @return string Request-local digest, or empty for a malformed token shape.
	 */
	private function fingerprint( string $id, array $controls ): string {
		$token = $controls['rktsky_antispam_token'] ?? '';
		return is_string( $token ) && '' !== $token && strlen( $token ) <= 400 ? hash( 'sha256', $id . '|' . $token ) : '';
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
	 * @return array{fields:array<string,string>,email:string,response:array} Bounded policy and native response values.
	 */
	private function content( array $definitions, array $values ): array {
		$data = array(
			'fields'   => array(),
			'email'    => '',
			'response' => array(),
		);
		foreach ( array_slice( $definitions, 0, 200, true ) as $name => $field ) {
			if ( ! is_string( $name ) || strlen( $name ) > 160 || str_contains( $name, 'rktsky_' ) || ! is_array( $field ) || ! isset( $field['element'], $field['raw']['element'], $values[ $name ] ) || $field['element'] !== $field['raw']['element'] || ! in_array( $field['element'], array( 'input_text', 'textarea', 'input_email', 'input_url', 'input_name', 'input_number', 'input_date', 'select', 'input_radio', 'input_checkbox' ), true ) ) {
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
						$parts[ $key ] = wp_check_invalid_utf8( substr( (string) $part, 0, 4096 ), true );
					}
				}
				$value = $parts;
			} elseif ( ! is_scalar( $value ) ) {
				continue;
			} else {
				$value = wp_check_invalid_utf8( substr( (string) $value, 0, 4096 ), true );
			}
			$data['response'][ $name ] = $value;
			$label                     = isset( $field['raw']['settings']['label'] ) && is_string( $field['raw']['settings']['label'] ) ? $field['raw']['settings']['label'] : $name;
			$this->add_content( $data, $name . ': ' . $label, $value, 'input_email' === $type );
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}
		return $data;
	}
}
