<?php
/**
 * Protects WS Form through native render descriptors and pre-action validation.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

use Rktsky\Antispam\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Survives WS Form's client-rendered canvas without changing stored form definitions.
 *
 * @since 0.3.0
 */
final class WsForm extends Adapter {
	/**
	 * Exact native submission objects flagged after successful technical verification.
	 *
	 * @since 0.5.0
	 * @var array<int,array{object:object,form:string,data:array,reason:string}>
	 */
	private array $native_spam = array();

	/**
	 * Validated objects waiting for the provider's actual persistence result.
	 *
	 * @since 0.5.0
	 * @var array<int,array{object:object,form:string,proof:array}>
	 */
	private array $observed = array();

	/**
	 * Checks the actual stored form's enabled native Save Submission action.
	 *
	 * No database action is added or enabled here. The provider resolves its own
	 * event/conditional selection; a missing form never implies queue availability.
	 *
	 * @since 0.5.0
	 * @param object|null $form Authoritative native form object, never request JSON.
	 * @return bool Whether this form can store a native spam submission.
	 */
	public static function native_available( $form = null ): bool {
		if ( ! is_object( $form ) || empty( $form->id ) || ! defined( 'WS_FORM_VERSION' ) || version_compare( WS_FORM_VERSION, '1.12.19', '<' ) || ! class_exists( '\WS_Form_Action' ) || ! isset( \WS_Form_Action::$actions['database'] ) ) {
			return false;
		}
		$database = \WS_Form_Action::$actions['database'];
		if ( ! is_callable( array( $database, 'get_form_actions' ) ) ) {
			return false;
		}
		foreach ( $database->get_form_actions( $form, 'submit' ) as $action ) {
			if ( is_array( $action ) && 'database' === ( $action['id'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}
	/**
	 * Registers retained host descriptors and the native pre-persistence validator.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wsf_shortcode', array( $this, 'render' ), PHP_INT_MAX );
		add_filter( 'wsf_submit_validate', array( $this, 'validate' ), PHP_INT_MAX, 3 );
		add_filter( 'wsf_api_submit_response_data', array( $this, 'response' ), PHP_INT_MAX, 2 );
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
		if ( is_object( $submission ) ) {
			unset( $this->native_spam[ spl_object_id( $submission ) ], $this->observed[ spl_object_id( $submission ) ] );
		}
		if ( $errors || ! is_object( $submission ) || ! isset( $submission->form_id, $submission->form_object ) || ! is_numeric( $submission->form_id ) || (int) $submission->form_id < 1 || ! is_object( $submission->form_object ) ) {
			return $errors;
		}
		$id     = (string) (int) $submission->form_id;
		$posted = $this->posted();
		$data   = $this->content( $submission );
		$reason = $this->guard->check( 'wsform', $id, $posted, $data['fields'] );
		if ( '' === $reason && $this->settings->enabled( 'wsform' ) ) {
			$this->observed[ spl_object_id( $submission ) ] = array(
				'object' => $submission,
				'form'   => $id,
				'proof'  => $this->token_evidence( $posted ),
			);
		}
		if ( '' !== $reason ) {
			if ( Guard::is_content_reason( $reason ) && 'submit' === $mode && empty( $submission->id ) && empty( $submission->row_id_filter ) && self::native_available( $submission->form_object ) ) {
				// Native spam stops every non-database action, including payment/account actions.
				$submission->spam_level                            = 100;
				$submission->actions_stopped                       = true;
				$this->native_spam[ spl_object_id( $submission ) ] = array(
					'object' => $submission,
					'form'   => $id,
					'data'   => $data,
					'reason' => $reason,
				);
				return $errors;
			}
			$this->retain( 'wsform', $id, $reason, $data );
			$errors[] = array(
				'action'    => 'message',
				'message'   => Guard::is_content_reason( $reason ) ? $this->guard->spam_message() : $this->guard->message(),
				'type'      => 'danger',
				'form_hide' => false,
				'duration'  => '',
			);
		}
		return $errors;
	}

	/**
	 * Replaces native success actions only for the exact content-rejected object.
	 *
	 * The original action runner stores its native spam record and handles native
	 * files under the form's own validation/storage contract after valid proof.
	 * All non-database actions are skipped by that runner. This response returns
	 * a visible retry error, omits the native resume hash and never copies files.
	 * A dynamically removed database action falls back to optional local retention.
	 *
	 * @since 0.5.0
	 * @param array  $response Native response containing actions, nonces and status.
	 * @param object $submission Exact native submission object.
	 * @return array Native response or a neutral local rejection without success actions.
	 */
	public function response( array $response, $submission ): array {
		if ( ! is_object( $submission ) ) {
			return $response;
		}
		$observed = $this->observed[ spl_object_id( $submission ) ] ?? null;
		unset( $this->observed[ spl_object_id( $submission ) ] );
		if ( $observed && $observed['object'] === $submission && ! empty( $submission->id ) && (string) $submission->form_id === $observed['form'] ) {
			$spam = 'spam' === ( $submission->status ?? '' );
			if ( $spam ) {
				$this->guard->observe_spam( 'wsform', $observed['form'], $observed['proof'] );
			}
			if ( $this->feedback && is_string( $submission->hash ?? null ) && 1 === preg_match( '/^[a-f0-9]{32}$/D', $submission->hash ) ) {
				$this->feedback->track( 'wsform', $submission->hash, $spam );
			}
		}
		if ( ! isset( $this->native_spam[ spl_object_id( $submission ) ] ) || $this->native_spam[ spl_object_id( $submission ) ]['object'] !== $submission ) {
			return $response;
		}
		$permit = $this->native_spam[ spl_object_id( $submission ) ];
		unset( $this->native_spam[ spl_object_id( $submission ) ] );
		if ( ! empty( $submission->id ) && 'spam' === ( $submission->status ?? '' ) && (string) $submission->form_id === $permit['form'] ) {
			if ( $this->feedback && is_string( $submission->hash ?? null ) && 1 === preg_match( '/^[a-f0-9]{32}$/D', $submission->hash ) ) {
				$this->feedback->track( 'wsform', $submission->hash, true );
			}
		} else {
			$this->retain( 'wsform', $permit['form'], $permit['reason'], $permit['data'] );
		}
		$response['error']            = true;
		$response['error_validation'] = true;
		$response['error_message']    = $this->guard->spam_message();
		$response['data']             = array(
			'js' => array(
				array(
					'action'    => 'message',
					'message'   => $this->guard->spam_message(),
					'type'      => 'danger',
					'form_hide' => false,
					'duration'  => '',
				),
			),
		);
		unset( $response['hash'] );
		return $response;
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
