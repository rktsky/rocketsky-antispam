<?php
/**
 * Protects Elementor Pro's native Form widget before submission actions run.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Uses the original widget output and Form_Record validation/process lifecycle.
 *
 * Atomic Form, third-party Elementor widgets and arbitrary server-side action
 * invocations are outside this adapter. No provider controls are rewritten.
 *
 * @since 0.3.0
 */
final class Elementor extends Adapter {

	/**
	 * One-use permits from validation to processing, bound to the record and token.
	 *
	 * Weak keys avoid retaining native records after their lifecycle has ended.
	 * This permit never authorizes another request or a second processing call.
	 *
	 * @since 0.3.0
	 * @var \WeakMap<object,array{form:string,token:array<string,string>}>|null
	 */
	private ?\WeakMap $validated = null;

	/**
	 * Registers frontend rendering and native gates before submission actions.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_filter( 'elementor/widget/render_content', array( $this, 'render' ), 20, 2 );
		add_filter( 'elementor/frontend/the_content', array( $this, 'cached_content' ), 20 );
		add_action( 'elementor_pro/forms/validation', array( $this, 'validate' ), PHP_INT_MAX, 2 );
		add_action( 'elementor_pro/forms/process', array( $this, 'process' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Adds persistent hosts after Elementor's own document cache has been read.
	 *
	 * Cached widget HTML can skip render_content and its script enqueue. The
	 * original frontend still filters its final document output here. Only real
	 * native Form widgets with one bounded form_id control receive descriptors;
	 * the shared bridge can then restore removed controls and enqueue its asset.
	 * Two native HTML-API passes preserve provider markup and avoid DOM reserialization.
	 *
	 * @since 0.3.0
	 * @param string $content Native frontend document HTML, including warm caches.
	 * @return string Original document with descriptors on eligible form elements.
	 */
	public function cached_content( string $content ): string {
		$scan = new \WP_HTML_Tag_Processor( $content );
		$forms = array();
		$index = 0;
		$current = 0;
		while ( $scan->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag = $scan->get_tag();
			if ( 'FORM' === $tag ) {
				if ( $scan->is_tag_closer() ) {
					$current = 0;
				} else {
					++$index;
					$current = $scan->has_class( 'elementor-form' ) ? $index : 0;
				}
			} elseif ( $current && 'INPUT' === $tag && 'form_id' === $scan->get_attribute( 'name' ) ) {
				$id = $scan->get_attribute( 'value' );
				// Duplicate scope controls are ambiguous; never sign either value.
				$forms[ $current ] = isset( $forms[ $current ] ) ? '' : ( is_string( $id ) && preg_match( '/^[a-zA-Z0-9_-]{1,64}$/D', $id ) ? $id : '' );
			}
		}
		if ( empty( $forms ) ) {
			return $content;
		}
		$output = new \WP_HTML_Tag_Processor( $content );
		$index = 0;
		while ( $output->next_tag( 'FORM' ) ) {
			++$index;
			if ( empty( $forms[ $index ] ) ) {
				continue;
			}
			foreach ( $this->fields->attributes( 'elementor', $forms[ $index ] ) as $name => $value ) {
				$output->set_attribute( $name, $value );
			}
		}
		return $output->get_updated_html();
	}

	/**
	 * Inserts escaped controls inside the native Form widget's single HTML form.
	 *
	 * Elementor serializes the full form as FormData. Its Form_Record selects only
	 * declared form_fields, so our top-level controls do not enter native records,
	 * notifications or webhooks. Dynamic widget rendering repeats this filter.
	 *
	 * @since 0.3.0
	 * @param string $content Native widget HTML.
	 * @param object $widget Elementor widget instance.
	 * @return string Unmodified unrelated output or protected native form HTML.
	 */
	public function render( string $content, $widget ): string {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'form' !== $widget->get_name() || ! method_exists( $widget, 'get_id' ) ) {
			return $content;
		}
		$id = $widget->get_id();
		$closing = strripos( $content, '</form>' );
		if ( ! is_string( $id ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/D', $id ) || false === $closing || str_contains( $content, 'data-rk-antispam-provider=' ) ) {
			return $content;
		}
		return substr_replace( $content, $this->fields->markup( 'elementor', $id ), $closing, 0 );
	}

	/**
	 * Validates proof after native field checks and before field processing.
	 *
	 * Earlier native errors preserve both their messages and the unused proof.
	 * Only a successful check creates a one-use record-bound processing permit.
	 *
	 * @since 0.3.0
	 * @param object $record Original Form_Record with server-selected form settings.
	 * @param object $handler Original Ajax_Handler receiving visible native errors.
	 * @return void
	 */
	public function validate( $record, $handler ): void {
		if ( ! $this->ready( $record, $handler ) || ! empty( $handler->errors ) ) {
			return;
		}
		$id = $this->form_id( $record );
		if ( $this->check( $record, $handler, $id ) ) {
			$this->validated ??= new \WeakMap();
			$this->validated[ $record ] = array( 'form' => $id, 'token' => $this->token_evidence( $this->posted() ) );
		}
	}

	/**
	 * Guards actions even when a trusted native skip_all filter bypasses validation.
	 *
	 * Normal validation consumes its permit exactly once. A changed token, form,
	 * record or second process call must pass Guard again. In a trusted skip_all
	 * configuration, native per-field processing precedes this fallback; provider
	 * mail, persistence and submit actions still run only after this hook succeeds.
	 *
	 * @since 0.3.0
	 * @param object $record Original Form_Record being processed.
	 * @param object $handler Original Ajax_Handler receiving native errors.
	 * @return void
	 */
	public function process( $record, $handler ): void {
		if ( ! $this->ready( $record, $handler ) || ! empty( $handler->errors ) ) {
			return;
		}
		$id = $this->form_id( $record );
		$permit = null !== $this->validated && isset( $this->validated[ $record ] ) ? $this->validated[ $record ] : null;
		if ( null !== $permit ) {
			unset( $this->validated[ $record ] );
			if ( $id === $permit['form'] && $this->token_evidence( $this->posted() ) === $permit['token'] ) {
				return;
			}
		}
		$this->check( $record, $handler, $id );
	}

	/**
	 * Checks the provider objects without imposing a hard plugin class dependency.
	 *
	 * @since 0.3.0
	 * @param mixed $record Candidate native record.
	 * @param mixed $handler Candidate native handler.
	 * @return bool Whether the required original API methods are available.
	 */
	private function ready( $record, $handler ): bool {
		return is_object( $record ) && is_object( $handler ) && method_exists( $record, 'get_form_settings' ) && method_exists( $record, 'get' ) && method_exists( $handler, 'add_error' ) && method_exists( $handler, 'add_error_message' );
	}

	/**
	 * Reads the server-selected widget ID instead of trusting submitted form_id.
	 *
	 * @since 0.3.0
	 * @param object $record Native record whose settings the provider resolved.
	 * @return string Bounded scope or empty for a malformed native definition.
	 */
	private function form_id( $record ): string {
		$id = $record->get_form_settings( 'id' );
		return is_string( $id ) && preg_match( '/^[a-zA-Z0-9_-]{1,64}$/D', $id ) ? $id : '';
	}

	/**
	 * Performs one local guard check and adds a visible retry error on rejection.
	 *
	 * @since 0.3.0
	 * @param object $record Original record with declared fields.
	 * @param object $handler Original handler updated only on rejection.
	 * @param string $id Validated server-selected widget scope.
	 * @return bool Whether local proof and configured content rules passed.
	 */
	private function check( $record, $handler, string $id ): bool {
		$data = $this->content( $record );
		$reason = $this->guard->check( 'elementor', $id, $this->posted(), $data['fields'] );
		if ( '' === $reason ) {
			return true;
		}
		$this->retain( 'elementor', $id, $reason, $data );
		$handler->add_error( 'rktsky_antispam', $this->guard->message() );
		$handler->add_error_message( $this->guard->message() );
		return false;
	}

	/**
	 * Selects declared, provider-sanitized contact fields and an email erasure index.
	 *
	 * Only matching stored field definitions and processed metadata qualify.
	 * Passwords, hidden inputs, files, payment widgets, consent and provider meta
	 * never enter local rules or quarantine. Raw values are deliberately ignored.
	 *
	 * @since 0.3.0
	 * @param object $record Original Form_Record resolved by Elementor.
	 * @return array{fields:array<string,string>,email:string} Bounded content only.
	 */
	private function content( $record ): array {
		$data = array( 'fields' => array(), 'email' => '' );
		$fields = $record->get( 'fields' );
		$definitions = $record->get_form_settings( 'form_fields' );
		if ( ! is_array( $fields ) || ! is_array( $definitions ) ) {
			return $data;
		}
		foreach ( array_slice( $definitions, 0, 200 ) as $definition ) {
			if ( ! is_array( $definition ) || ! isset( $definition['custom_id'], $definition['field_type'] ) || ! is_string( $definition['custom_id'] ) || ! in_array( $definition['field_type'], array( 'text', 'textarea', 'email', 'url', 'tel', 'number', 'select', 'radio', 'checkbox', 'date', 'time' ), true ) ) {
				continue;
			}
			$id = $definition['custom_id'];
			$field = $fields[ $id ] ?? null;
			if ( ! is_array( $field ) || ! isset( $field['type'], $field['value'] ) || $definition['field_type'] !== $field['type'] ) {
				continue;
			}
			$label = isset( $definition['field_label'] ) && is_string( $definition['field_label'] ) ? $definition['field_label'] : 'Field';
			$this->add_content( $data, $id . ': ' . $label, $field['value'], 'email' === $definition['field_type'] );
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}
		return $data;
	}
}
