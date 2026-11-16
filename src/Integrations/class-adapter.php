<?php
/**
 * Shares bounded content selection and injected services between native adapters.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

use Rktsky\Antispam\{Settings, Guard, Fields, Quarantine, Feedback};

defined( 'ABSPATH' ) || exit;

/**
 * Supplies shared services without registering provider hooks.
 *
 * @since 0.3.0
 */
abstract class Adapter {

	/**
	 * Plugin settings.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Submission guard.
	 *
	 * @since 0.1.0
	 * @var Guard
	 */
	protected $guard;

	/**
	 * Protection-field renderer.
	 *
	 * @since 0.1.0
	 * @var Fields
	 */
	protected $fields;

	/**
	 * Optional encrypted quarantine; its own opt-in remains authoritative.
	 *
	 * @since 0.2.0
	 * @var Quarantine|null
	 */
	protected ?Quarantine $quarantine;

	/**
	 * Optional metadata-only human feedback ledger.
	 *
	 * @since 0.2.0
	 * @var Feedback|null
	 */
	protected ?Feedback $feedback;

	/**
	 * Supplies the integration dependencies without changing provider settings.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Added the optional encrypted quarantine dependency.
	 * @param Settings        $settings   Plugin settings.
	 * @param Guard           $guard      Local submission guard.
	 * @param Fields          $fields     Protection-field renderer.
	 * @param Quarantine|null $quarantine Optional encrypted, opt-in retention.
	 * @param Feedback|null   $feedback   Optional reviewed-prediction ledger.
	 * @return void
	 */
	public function __construct( Settings $settings, Guard $guard, Fields $fields, ?Quarantine $quarantine = null, ?Feedback $feedback = null ) {
		$this->settings   = $settings;
		$this->guard      = $guard;
		$this->fields     = $fields;
		$this->quarantine = $quarantine;
		$this->feedback   = $feedback;
	}

	/**
	 * Adds a bounded plain value while excluding reserved protection names.
	 *
	 * Flat multi-choice arrays are supported; nested data and objects are ignored.
	 * Only a declared email field can populate the email export/erasure index.
	 *
	 * @since 0.2.0
	 * @param array  $data  Selected values and optional email index, updated in place.
	 * @param string $name  Native field name or label.
	 * @param mixed  $value Provider value for an explicitly allowed field.
	 * @param bool   $email Whether the native type is email.
	 * @return void
	 */
	protected function add_content( array &$data, string $name, $value, bool $email = false ): void {
		if ( count( $data['fields'] ) >= 30 || str_contains( $name, 'rktsky_antispam' ) || str_contains( $name, 'rktsky_as_' ) ) {
			return;
		}
		$name = wp_check_invalid_utf8( substr( wp_strip_all_tags( $name ), 0, 160 ), true );
		if ( '' === $name ) {
			return;
		}
		if ( is_array( $value ) ) {
			$parts = array();
			foreach ( array_slice( $value, 0, 30 ) as $part ) {
				if ( is_scalar( $part ) ) {
					$parts[] = substr( (string) $part, 0, 4096 );
				}
			}
			$value = implode( ', ', $parts );
		}
		if ( ! is_scalar( $value ) ) {
			return;
		}
		$value                   = wp_check_invalid_utf8( substr( (string) $value, 0, 4096 ), true );
		$data['fields'][ $name ] = $value;
		if ( $email && '' === $data['email'] ) {
			if ( is_email( $value ) ) {
				$data['email'] = sanitize_email( $value );
			}
		}
	}

	/**
	 * Defers the privacy-mode, opt-in and cryptographic availability decision.
	 *
	 * @since 0.2.0
	 * @return bool Whether the quarantine service permits local retention.
	 */
	protected function retention_enabled(): bool {
		return null !== $this->quarantine && $this->quarantine->enabled();
	}

	/**
	 * Hands only selected values to the independently bounded encrypted store.
	 *
	 * @since 0.2.0
	 * @param string $provider Native provider ID.
	 * @param string $form_id  Native form ID.
	 * @param string $reason   Fixed local or native rejection reason.
	 * @param array  $data     Selected field map and optional declared email value.
	 * @return void
	 */
	protected function retain( string $provider, string $form_id, string $reason, array $data ): void {
		if ( $this->retention_enabled() ) {
			$this->quarantine->capture( $provider, $form_id, $reason, $data['fields'], $data['email'] );
		}
	}

	/**
	 * Retains only the plugin's own bounded token for final-status bookkeeping.
	 *
	 * @since 0.2.0
	 * @param array $posted Unslashed native or REST controls.
	 * @return array<string,string> Own technical token only; never form content.
	 */
	protected function token_evidence( array $posted ): array {
		$token = $posted['rktsky_antispam_token'] ?? '';

		return is_string( $token ) && strlen( $token ) <= 400 ? array( 'rktsky_antispam_token' => $token ) : array();
	}

	/**
	 * Returns WordPress-unslashed raw POST for guard-side shape validation.
	 *
	 * Only own protection fields and separately selected native content fields
	 * reach Guard. Raw provider controls remain untouched and are never dumped.
	 *
	 * @since 0.1.0
	 * @return array Submitted form controls or an empty collection.
	 */
	protected function posted(): array {
		// Public anti-spam proof is verified by Guard; a WP nonce is not a bot check.
		return isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Guard validates a site-local protection token.
	}

	/**
	 * Reads bounded protection keys without copying account or payment controls.
	 *
	 * Malformed values remain malformed for Guard's own shape checks. Reading
	 * technical evidence authenticates no account action or user identity.
	 *
	 * @since 0.3.0
	 * @return array Own unslashed controls only.
	 */
	protected function protection_controls(): array {
		$proof   = array();
		$visited = 0;
		foreach ( $_POST as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Guard verifies the local protection proof; native action nonces remain provider-owned.
			if ( ++$visited > 2000 ) {
				break;
			}
			if ( 'rktsky_antispam_token' === $key || ( is_string( $key ) && 1 === preg_match( '/^rktsky_as_[a-f0-9]{20}$/D', $key ) ) ) {
				$proof[ $key ] = is_string( $value ) && strlen( $value ) <= 400 ? wp_unslash( $value ) : null;
			}
		}
		return $proof;
	}

	/**
	 * Connects this adapter to its native provider.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	abstract public function register(): void;
}
