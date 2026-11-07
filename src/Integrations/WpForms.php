<?php
/**
 * Connects local protection to the native WpForms lifecycle.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

use Rktsky\Antispam\{Settings, Guard, Fields, Quarantine, Feedback};

defined( 'ABSPATH' ) || exit;

/**
 * Owns only the WpForms integration and its request-local state.
 *
 * @since 0.3.0
 */
final class WpForms extends Adapter {

	/**
	 * Native WPForms text types whose content can be reviewed locally.
	 *
	 * @since 0.2.0
	 * @var string[]
	 */
	private const WPFORMS_CONTENT_TYPES = array( 'text', 'textarea', 'email', 'url', 'phone', 'number', 'name', 'select', 'checkbox', 'radio', 'date-time' );

	/**
	 * WPForms native captures already handled in the current form lifecycle.
	 *
	 * @since 0.2.0
	 * @var array<int,bool>
	 */
	private array $wpforms_captured = array();

	/**
	 * Registers native hooks after provider detection and explicit enablement.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'wpforms_frontend_output', array( $this, 'wpforms_fields' ), 30 );
		add_action( 'wpforms_process', array( $this, 'check_wpforms' ), 5, 3 );
		add_filter( 'wpforms_process_before_form_data', array( $this, 'begin_wpforms' ), 1, 2 );
		add_filter( 'wpforms_process_honeypot', array( $this, 'observe_wpforms_honeypot' ), PHP_INT_MAX, 4 );
		add_filter( 'wpforms_process_antispam', array( $this, 'observe_wpforms_antispam' ), PHP_INT_MAX, 4 );
		add_action( 'wpforms_process_after', array( $this, 'observe_wpforms_spam' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Renders protection inside WPForms' native form in both Lite and Pro.
	 *
	 * @since 0.1.0
	 * @param array $form Native form definition.
	 * @return void
	 */
	public function wpforms_fields( array $form ): void {
		if ( ! empty( $form['id'] ) ) {
			echo $this->fields->markup( 'wpforms', (string) absint( $form['id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by Fields::markup().
		}
	}

	/**
	 * Stops WPForms before entry persistence and notifications using native errors.
	 *
	 * @since 0.1.0
	 * @param array $fields Provider-sanitized fields, limited by native type below.
	 * @param array $entry  Native submitted entry, deliberately not inspected.
	 * @param array $form   Native form definition.
	 * @return void
	 */
	public function check_wpforms( array $fields, array $entry, array $form ): void {
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		$data    = $this->wpforms_content( $fields, $form );
		$reason  = $this->guard->check( 'wpforms', (string) $form_id, $this->posted(), $data['fields'] );

		if ( '' === $reason ) {
			return;
		}

		$this->retain( 'wpforms', (string) $form_id, $reason, $data );
		$this->wpforms_captured[ $form_id ] = true;
		$process                            = wpforms()->process;

		// Preserve an earlier provider or integration error at the same location.
		if ( empty( $process->errors[ $form_id ]['header'] ) ) {
			$process->errors[ $form_id ]['header'] = $this->guard->message();
		}
	}

	/**
	 * Starts a WPForms lifecycle before its modern early spam check can return.
	 *
	 * @since 0.2.0
	 * @param array $form  Native form definition.
	 * @param array $entry Unvalidated provider entry, deliberately not inspected.
	 * @return array Unmodified native form definition.
	 */
	public function begin_wpforms( array $form, array $entry ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Raw entry is part of the native filter contract and is intentionally not inspected.
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		unset( $this->wpforms_captured[ $form_id ] );

		return $form;
	}

	/**
	 * Observes WPForms' original honeypot verdict before its early return.
	 *
	 * @since 0.2.0
	 * @param bool|string $result Native honeypot rejection or false.
	 * @param array       $fields Provider-sanitized field metadata and values.
	 * @param array       $entry  Raw entry, deliberately not inspected.
	 * @param array       $form   Native form definition.
	 * @return bool|string Unmodified native verdict.
	 */
	public function observe_wpforms_honeypot( $result, array $fields, array $entry, array $form ) {
		if ( $result ) {
			$this->capture_wpforms_native( $fields, $form );
		}

		return $result;
	}

	/**
	 * Observes WPForms' token-filter result without changing its boolean meaning.
	 *
	 * @since 0.2.0
	 * @param bool  $valid  Existing provider proof verdict; false rejects.
	 * @param array $fields Provider-sanitized field metadata and values.
	 * @param array $entry  Raw entry, deliberately not inspected.
	 * @param array $form   Native form definition.
	 * @return bool Unmodified provider verdict.
	 */
	public function observe_wpforms_antispam( $valid, array $fields, array $entry, array $form ): bool {
		if ( ! $valid ) {
			$this->capture_wpforms_native( $fields, $form );
		}

		return (bool) $valid;
	}

	/**
	 * Observes modern WPForms spam errors after processing, including early exits.
	 *
	 * @since 0.2.0
	 * @param array $fields   Provider-sanitized field metadata and values.
	 * @param int   $entry_id Native entry ID, unused for content extraction.
	 * @param array $form     Native form definition.
	 * @return void
	 */
	public function observe_wpforms_spam( array $fields, $entry_id, array $form ): void {
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		$process = wpforms()->process;
		if ( ! empty( $process->spam_errors[ $form_id ] ) ) {
			$this->capture_wpforms_native( $fields, $form );
		}
	}

	/**
	 * Handles one native WPForms rejection per lifecycle using selected fields.
	 *
	 * @since 0.2.0
	 * @param array $fields Provider-sanitized field metadata and values.
	 * @param array $form   Native form definition.
	 * @return void
	 */
	private function capture_wpforms_native( array $fields, array $form ): void {
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		if ( ! empty( $this->wpforms_captured[ $form_id ] ) ) {
			return;
		}
		$this->wpforms_captured[ $form_id ] = true;
		$this->guard->observe_spam( 'wpforms', (string) $form_id, $this->posted() );
		if ( $this->retention_enabled() ) {
			$data = ! empty( $fields ) ? $this->wpforms_content( $fields, $form ) : $this->wpforms_raw_content( $form );
			$this->retain( 'wpforms', (string) $form_id, 'native_spam', $data );
		}
	}

	/**
	 * Selects WPForms values only when both native definitions and metadata agree.
	 *
	 * The value property alone is copied; raw entry data, provider metadata and
	 * hidden/password/payment/upload fields never enter the selected collection.
	 *
	 * @since 0.2.0
	 * @param array $fields Provider-sanitized field metadata and values.
	 * @param array $form   Stored native field definitions.
	 * @return array{fields:array<string,string>,email:string} Bounded selected data.
	 */
	private function wpforms_content( array $fields, array $form ): array {
		$data = array(
			'fields' => array(),
			'email'  => '',
		);
		foreach ( $fields as $id => $field ) {
			$definition = $form['fields'][ $id ] ?? null;
			if ( ! is_array( $field ) || ! is_array( $definition ) || ! isset( $definition['type'], $field['type'], $field['value'] ) || $definition['type'] !== $field['type'] || ! in_array( $definition['type'], self::WPFORMS_CONTENT_TYPES, true ) ) {
				continue;
			}
			$label = isset( $definition['label'] ) && is_string( $definition['label'] ) ? $definition['label'] : 'Field';
			$this->add_content( $data, (string) $id . ': ' . $label, $field['value'], 'email' === $definition['type'] );
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}

		return $data;
	}

	/**
	 * Selects and sanitizes declared WPForms values rejected before formatting.
	 *
	 * Modern native spam protection can return before sanitized fields exist.
	 * This fallback never changes provider validation: only declared text field
	 * IDs are read. Compound values accept known flat parts; objects, nested
	 * values, unknown IDs and hidden/password/payment/file types are excluded.
	 *
	 * @since 0.2.0
	 * @param array $form Stored native field definitions.
	 * @return array{fields:array<string,string>,email:string} Bounded sanitized data.
	 */
	private function wpforms_raw_content( array $form ): array {
		$data = array(
			'fields' => array(),
			'email'  => '',
		);
		// Selection must precede unslashing/sanitizing: only declared scalar controls below are read.
		$raw = isset( $_POST['wpforms']['fields'] ) && is_array( $_POST['wpforms']['fields'] ) ? $_POST['wpforms']['fields'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Observes a native rejection; selected scalar fields are explicitly unslashed and sanitized below.
		foreach ( (array) ( $form['fields'] ?? array() ) as $id => $definition ) {
			if ( ! is_array( $definition ) || ! isset( $definition['type'], $raw[ $id ] ) || ! in_array( $definition['type'], self::WPFORMS_CONTENT_TYPES, true ) ) {
				continue;
			}
			$type  = $definition['type'];
			$value = $raw[ $id ];
			if ( is_array( $value ) ) {
				if ( ! in_array( $type, array( 'name', 'checkbox', 'select', 'date-time' ), true ) ) {
					continue;
				}
				$parts = array();
				foreach ( array_slice( $value, 0, 30, true ) as $part_name => $part ) {
					$known = ( 'name' === $type && in_array( (string) $part_name, array( 'first', 'middle', 'last' ), true ) )
						|| ( 'date-time' === $type && in_array( (string) $part_name, array( 'date', 'time' ), true ) )
						|| ( in_array( $type, array( 'checkbox', 'select' ), true ) && ctype_digit( (string) $part_name ) );
					if ( $known && is_scalar( $part ) ) {
						$parts[] = sanitize_text_field( wp_unslash( substr( (string) $part, 0, 4096 ) ) );
					}
				}
				$value = implode( ', ', $parts );
			} elseif ( is_scalar( $value ) ) {
				$value = wp_unslash( substr( (string) $value, 0, 4096 ) );
				if ( 'textarea' === $type ) {
					$value = sanitize_textarea_field( $value );
				} elseif ( 'email' === $type ) {
					$value = is_email( $value ) ? sanitize_email( $value ) : sanitize_text_field( $value );
				} else {
					$value = sanitize_text_field( $value );
				}
			} else {
				continue;
			}
			$label = isset( $definition['label'] ) && is_string( $definition['label'] ) ? $definition['label'] : 'Field';
			$this->add_content( $data, (string) $id . ': ' . $label, $value, 'email' === $type );
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}

		return $data;
	}
}
