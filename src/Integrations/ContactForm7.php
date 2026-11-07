<?php
/**
 * Connects local protection to the native ContactForm7 lifecycle.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

use Rktsky\Antispam\{Settings, Guard, Fields, Quarantine, Feedback};

defined( 'ABSPATH' ) || exit;

/**
 * Owns only the ContactForm7 integration and its request-local state.
 *
 * @since 0.3.0
 */
final class ContactForm7 extends Adapter {

	/**
	 * Contact Form 7 forms rejected during the current request.
	 *
	 * @since 0.1.0
	 * @var array<string, bool>
	 */
	private $cf7_errors = array();

	/**
	 * Registers native hooks after provider detection and explicit enablement.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wpcf7_form_elements', array( $this, 'cf7_fields' ) );
		add_filter( 'wpcf7_posted_data', array( $this, 'cf7_processed_data' ) );
		add_filter( 'wpcf7_spam', array( $this, 'check_cf7' ), 20, 2 );
		add_filter( 'wpcf7_display_message', array( $this, 'cf7_message' ), 20, 2 );
	}

	/**
	 * Appends protection through Contact Form 7's native form-content filter.
	 *
	 * @since 0.1.0
	 * @param string $html Original rendered field markup.
	 * @return string Original markup followed by local protection fields.
	 */
	public function cf7_fields( string $html ): string {
		$form = \WPCF7_ContactForm::get_current();

		return $form ? $html . $this->fields->markup( 'cf7', (string) $form->id() ) : $html;
	}

	/**
	 * Removes only local technical controls from CF7's processed data collection.
	 *
	 * CF7 copies unknown top-level fields into posted_data. Removing this plugin's
	 * reserved names prevents token retention in mail/storage consumers while
	 * leaving raw POST intact for the independent guard check.
	 *
	 * @since 0.1.0
	 * @param array $posted Provider-sanitized submission data.
	 * @return array Processed data without local protection metadata.
	 */
	public function cf7_processed_data( array $posted ): array {
		unset( $posted['rktsky_antispam_token'] );

		foreach ( array_keys( $posted ) as $name ) {
			if ( is_string( $name ) && 1 === preg_match( '/^rktsky_as_[a-f0-9]{20}$/D', $name ) ) {
				unset( $posted[ $name ] );
			}
		}

		return $posted;
	}

	/**
	 * Preserves earlier spam decisions and blocks local proof failures.
	 *
	 * Raw POST is intentional: Contact Form 7 excludes technical metadata from
	 * its processed posted_data, making that collection unsuitable for this check.
	 *
	 * @since 0.1.0
	 * @param bool                   $spam       Existing provider spam decision.
	 * @param \WPCF7_Submission|null $submission Native submission object.
	 * @return bool Combined spam decision.
	 */
	public function check_cf7( $spam, $submission = null ): bool {
		if ( ! $submission ) {
			$submission = \WPCF7_Submission::get_instance();
		}
		$form    = $submission ? $submission->get_contact_form() : null;
		$form_id = $form ? (string) $form->id() : '0';
		unset( $this->cf7_errors[ $form_id ] );

		if ( $spam ) {
			$this->guard->observe_spam( 'cf7', $form_id, $this->posted() );
			if ( $this->retention_enabled() ) {
				$this->retain( 'cf7', $form_id, 'native_spam', $this->cf7_content( $form, $submission ) );
			}
			return true;
		}

		$data   = $this->cf7_content( $form, $submission );
		$reason = $this->guard->check( 'cf7', $form_id, $this->posted(), $data['fields'] );

		if ( '' === $reason ) {
			return false;
		}

		$this->cf7_errors[ $form_id ] = true;
		$this->retain( 'cf7', $form_id, $reason, $data );

		if ( $submission ) {
			$submission->add_spam_log(
				array(
					'agent'  => 'wp-antispam',
					'reason' => 'Local protection check failed.',
				)
			);
		}

		return true;
	}

	/**
	 * Offers a retry message only for Contact Form 7 submissions rejected here.
	 *
	 * @since 0.1.0
	 * @param string $message Native provider message.
	 * @param string $status  Provider message key.
	 * @return string Native message or a neutral local retry instruction.
	 */
	public function cf7_message( string $message, string $status ): string {
		$form = \WPCF7_ContactForm::get_current();

		if ( 'spam' === $status && $form && ! empty( $this->cf7_errors[ (string) $form->id() ] ) ) {
			return $this->guard->message();
		}

		return $message;
	}

	/**
	 * Selects CF7 content using declared native form tags and processed values.
	 *
	 * Only text, email, URL, telephone, textarea, selection, number and date tags
	 * participate. Unknown POST keys and all technical fields remain excluded.
	 *
	 * @since 0.2.0
	 * @param \WPCF7_ContactForm|null $form       Native form definition.
	 * @param \WPCF7_Submission|null  $submission Current native submission.
	 * @return array{fields:array<string,string>,email:string} Bounded selected data.
	 */
	private function cf7_content( $form, $submission ): array {
		$data = array(
			'fields' => array(),
			'email'  => '',
		);
		if ( ! $form || ! $submission ) {
			return $data;
		}
		$posted = $submission->get_posted_data();
		if ( ! is_array( $posted ) ) {
			return $data;
		}
		$allowed = array( 'text', 'email', 'url', 'tel', 'textarea', 'select', 'checkbox', 'radio', 'number', 'date' );
		foreach ( $form->scan_form_tags() as $tag ) {
			if ( ! is_object( $tag ) || ! isset( $tag->basetype, $tag->name ) || ! in_array( $tag->basetype, $allowed, true ) || ! is_string( $tag->name ) ) {
				continue;
			}
			$name = $tag->name;
			if ( isset( $posted[ $name ] ) ) {
				$this->add_content( $data, $name, $posted[ $name ], 'email' === $tag->basetype );
			}
			if ( count( $data['fields'] ) >= 30 ) {
				break;
			}
		}

		return $data;
	}

}
