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
	 * Successful proof fingerprints awaiting actual native entry persistence.
	 *
	 * @since 0.5.0
	 * @var array<int,string>
	 */
	private array $wpforms_evidence = array();

	/**
	 * Native spam IDs persisted during this exact form lifecycle.
	 *
	 * @since 0.5.0
	 * @var array<int,int>
	 */
	private array $wpforms_native_stored = array();

	/**
	 * Explicit native entry-storage vetoes observed in this form lifecycle.
	 *
	 * @since 0.5.0
	 * @var array<int,bool>
	 */
	private array $wpforms_storage_denied = array();

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
		add_action( 'wpforms_process_entry_saved', array( $this, 'observe_wpforms_entry' ), PHP_INT_MAX, 4 );
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
	 * Checks native spam-storage support without enabling a provider setting.
	 *
	 * The original Pro saver treats any set disable_entries key as disabled,
	 * including a stored zero. A missing form checks capability only; callers
	 * must still describe the per-form storage decision as conditional.
	 *
	 * @since 0.5.0
	 * @param array|null $form Optional original native form settings.
	 * @return bool Whether the public native spam workflow is available.
	 */
	public static function native_available( ?array $form = null ): bool {
		if ( ! function_exists( 'wpforms' ) || ! defined( 'WPFORMS_VERSION' ) || version_compare( WPFORMS_VERSION, '1.8.3', '<' ) || ! is_callable( array( wpforms(), 'is_pro' ) ) || ! is_callable( array( wpforms(), 'obj' ) ) || ! wpforms()->is_pro() ) {
			return false;
		}
		$process = wpforms()->obj( 'process' );
		$spam    = wpforms()->obj( 'spam_entry' );
		if ( ! is_callable( array( $process, 'entry_save' ) ) || ! is_callable( array( $spam, 'add_meta_data' ) ) || false === has_filter( 'wpforms_entry_save_args', array( $spam, 'add_spam_status' ) ) ) {
			return false;
		}
		return null === $form || ( ! empty( $form['settings']['store_spam_entries'] ) && ! isset( $form['settings']['disable_entries'] ) );
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
			$proof = $this->wpforms_fingerprint();
			if ( '' !== $proof ) {
				$this->wpforms_evidence[ $form_id ] = $proof;
			}
			return;
		}

		$this->wpforms_captured[ $form_id ] = true;
		$process                            = wpforms()->process;
		$prior_error                        = ! empty( $process->errors[ $form_id ] );

		// Preserve an earlier provider or integration error at the same location.
		if ( empty( $process->errors[ $form_id ]['header'] ) ) {
			$process->errors[ $form_id ]['header'] = Guard::is_content_reason( $reason ) ? $this->guard->spam_message() : $this->guard->message();
		}

		// Set the documented native error before invoking storage: the original
		// Stripe/Square callbacks run later on this same action and honour it.
		if ( ! $prior_error && Guard::is_content_reason( $reason ) && $this->store_native_spam( $fields, $form ) ) {
			return;
		}
		if ( ! isset( $form['settings']['disable_entries'] ) && empty( $this->wpforms_storage_denied[ $form_id ] ) ) {
			$this->retain( 'wpforms', (string) $form_id, $reason, $data );
		}
	}

	/**
	 * Saves selected content through the original public entry/spam workflow.
	 *
	 * No process-entry-saved event is synthesized: that event also invokes
	 * payment/upload/task callbacks. The specific native spam metadata callback
	 * receives empty raw data, and ordinary processing stops on native errors.
	 *
	 * @since 0.5.0
	 * @param array $fields Already validated provider fields.
	 * @param array $form Original native form configuration.
	 * @return bool Whether one native spam entry was persisted.
	 */
	private function store_native_spam( array $fields, array $form ): bool {
		if ( ! self::native_available( $form ) ) {
			return false;
		}
		$selected = array();
		$bytes    = 0;
		foreach ( $fields as $id => $field ) {
			$definition = $form['fields'][ $id ] ?? null;
			if ( ! is_array( $field ) || ! is_array( $definition ) || ! isset( $field['type'], $definition['type'], $field['value'] ) || $definition['type'] !== $field['type'] || ! in_array( $definition['type'], self::WPFORMS_CONTENT_TYPES, true ) ) {
				continue;
			}
			$content = array(
				'fields' => array(),
				'email'  => '',
			);
			$this->add_content( $content, (string) $id, $field['value'] );
			$value = $content['fields'][ (string) $id ] ?? '';
			if ( $bytes + strlen( $value ) > 16384 || count( $selected ) >= 30 ) {
				break;
			}
			$bytes          += strlen( $value );
			$selected[ $id ] = array(
				'id'    => $id,
				'name'  => isset( $definition['label'] ) && is_string( $definition['label'] ) ? wp_check_invalid_utf8( substr( wp_strip_all_tags( $definition['label'] ), 0, 160 ), true ) : 'Field',
				'type'  => $definition['type'],
				'value' => $value,
			);
		}
		$native                  = $form;
		$native['spam_reason']   = 'Rocketsky Antispam';
		$native['post_data_raw'] = array();
		// Respect the documented policy before calling the public saver. Native
		// Pro 1.9.7.3 does not stop its saver when preparation is vetoed. Its
		// original workflow independently evaluates this same policy again.
		if ( ! apply_filters( 'wpforms_entry_save', true, $selected, array(), $native ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Evaluates WPForms' documented native storage policy without changing its result.
			$this->wpforms_storage_denied[ absint( $form['id'] ) ] = true;
			return false;
		}
		$process  = wpforms()->obj( 'process' );
		$previous = absint( $process->entry_id );
		$id       = absint( $process->entry_save( $selected, array(), absint( $form['id'] ), $native ) );
		if ( $id < 1 || $id === $previous ) {
			return false;
		}
		$record = wpforms()->obj( 'entry' )->get( $id, array( 'cap' => '' ) );
		if ( ! is_object( $record ) || absint( $form['id'] ) !== (int) $record->form_id || 'spam' !== $record->status ) {
			return false;
		}
		wpforms()->obj( 'spam_entry' )->add_meta_data(
			array(),
			array(),
			array(
				'id'            => absint( $form['id'] ),
				'spam_reason'   => 'Rocketsky Antispam',
				'post_data_raw' => array(),
			),
			$id
		);
		$this->wpforms_native_stored[ absint( $form['id'] ) ] = $id;
		if ( null !== $this->feedback ) {
			$this->feedback->track( 'wpforms', $id, true );
		}
		return true;
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
		unset( $this->wpforms_captured[ $form_id ], $this->wpforms_evidence[ $form_id ], $this->wpforms_native_stored[ $form_id ], $this->wpforms_storage_denied[ $form_id ] );

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
	 * Tracks actual native persistence only after this lifecycle verified proof.
	 *
	 * @since 0.5.0
	 * @param array $fields Native formatted fields, not read here.
	 * @param array $entry Raw entry, deliberately not read.
	 * @param array $form Native persisted form context.
	 * @param int   $entry_id Native entry ID after the original saver returned.
	 * @return void
	 */
	public function observe_wpforms_entry( array $fields, array $entry, array $form, $entry_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- Native callback parameters are deliberately not inspected.
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		$proof   = $this->wpforms_fingerprint();
		if ( absint( $entry_id ) < 1 || '' === $proof || ! isset( $this->wpforms_evidence[ $form_id ] ) || ! hash_equals( $this->wpforms_evidence[ $form_id ], $proof ) ) {
			return;
		}
		unset( $this->wpforms_evidence[ $form_id ] );
		$spam = ! empty( $form['spam_reason'] );
		if ( $spam ) {
			$this->wpforms_native_stored[ $form_id ] = absint( $entry_id );
		}
		if ( null !== $this->feedback ) {
			$this->feedback->track( 'wpforms', absint( $entry_id ), $spam );
		}
	}

	/**
	 * Binds final native bookkeeping to the exact verified local token.
	 *
	 * @since 0.5.0
	 * @return string Request-local digest or empty for malformed controls.
	 */
	private function wpforms_fingerprint(): string {
		$controls = $this->protection_controls();
		$token    = $controls['rktsky_antispam_token'] ?? null;
		return is_string( $token ) && '' !== $token ? hash( 'sha256', $token ) : '';
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
		if ( $this->retention_enabled() && ! isset( $form['settings']['disable_entries'] ) && empty( $this->wpforms_native_stored[ $form_id ] ) && ! self::native_available( $form ) ) {
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
