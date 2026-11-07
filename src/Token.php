<?php
/**
 * Issues purpose-bound HMAC challenges without visitor identifiers.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Authenticates public form intents and short-lived submission tokens.
 *
 * Tokens establish integrity and freshness, not human identity or authorization.
 *
 * @since 0.1.0
 */
final class Token {
	/**
	 * Signs a cache-safe form descriptor that grants no privileged authority.
	 *
	 * @since 0.1.0
	 * @param string $provider Provider identifier.
	 * @param string $form_id Native form identifier.
	 * @return string HMAC descriptor or empty for an invalid scope.
	 */
	public function intent( string $provider, string $form_id ): string {
		return $this->scope_valid( $provider, $form_id ) ? $this->mac( 'intent|' . $provider . '|' . $form_id ) : '';
	}

	/**
	 * Checks that a descriptor was generated for this site and exact form.
	 *
	 * @since 0.1.0
	 * @param string $intent Public descriptor.
	 * @param string $provider Provider identifier.
	 * @param string $form_id Native form identifier.
	 * @return bool Whether the bounded scope and descriptor match.
	 */
	public function valid_intent( string $intent, string $provider, string $form_id ): bool {
		$expected = $this->intent( $provider, $form_id );
		return '' !== $expected && 64 === strlen( $intent ) && hash_equals( $expected, $intent );
	}

	/**
	 * Creates a random challenge; issuance stores nothing and makes no network call.
	 *
	 * @since 0.1.0
	 * @param string $provider Provider identifier.
	 * @param string $form_id Native form identifier.
	 * @return array{token:string,trap:string} Signed token and dynamic honeypot name.
	 */
	public function issue( string $provider, string $form_id ): array {
		if ( ! $this->scope_valid( $provider, $form_id ) ) {
			return array(
				'token' => '',
				'trap'  => '',
			);
		}
		$payload = implode( '|', array( '1', $provider, $form_id, (string) time(), bin2hex( random_bytes( 16 ) ) ) );
		$token   = base64_encode( $payload ) . '.' . $this->mac( 'token|' . $payload ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Readable signed protocol data, never executable code.
		return array(
			'token' => $token,
			'trap'  => $this->trap( $token ),
		);
	}

	/**
	 * Verifies exact framing, signature, scope and the fixed two-hour lifetime.
	 *
	 * @since 0.1.0
	 * @param string $token Submitted challenge, bounded before decoding.
	 * @param string $provider Expected provider.
	 * @param string $form_id Expected native form.
	 * @return array{issued:int,expires:int,trap:string,digest:string}|false Verified metadata or false.
	 */
	public function verify( string $token, string $provider, string $form_id ) {
		if ( strlen( $token ) > 400 || ! $this->scope_valid( $provider, $form_id ) ) {
			return false;
		}
		$parts = explode( '.', $token );
		if ( 2 !== count( $parts ) || 64 !== strlen( $parts[1] ) ) {
			return false;
		}
		$payload = base64_decode( $parts[0], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Strict decoding of bounded signed data, never evaluated.
		if ( false === $payload || base64_encode( $payload ) !== $parts[0] || ! hash_equals( $this->mac( 'token|' . $payload ), $parts[1] ) ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Canonical framing prevents alternate-encoding replay.
			return false;
		}
		$data = explode( '|', $payload );
		if ( 5 !== count( $data ) || '1' !== $data[0] || $provider !== $data[1] || $form_id !== $data[2] || ! ctype_digit( $data[3] ) || ! preg_match( '/^[a-f0-9]{32}$/D', $data[4] ) ) {
			return false;
		}
		$issued = (int) $data[3];
		if ( $issued > time() || $issued + 7200 < time() ) {
			return false;
		}
		return array(
			'issued'  => $issued,
			'expires' => $issued + 7200,
			'trap'    => $this->trap( $token ),
			'digest'  => $this->mac( 'used|' . $token ),
		);
	}

	/**
	 * Derives an unpredictable field name without embedding visitor data.
	 *
	 * @since 0.1.0
	 * @param string $token Signed challenge.
	 * @return string Safe HTML field name.
	 */
	private function trap( string $token ): string {
		return 'rktsky_as_' . substr( $this->mac( 'trap|' . $token ), 0, 20 );
	}

	/**
	 * Separates this plugin's signatures across site IDs and protocol purposes.
	 *
	 * @since 0.1.0
	 * @param string $value Domain-separated message.
	 * @return string Hexadecimal SHA-256 HMAC.
	 */
	private function mac( string $value ): string {
		return hash_hmac( 'sha256', get_current_blog_id() . '|' . $value, wp_salt( 'auth' ) );
	}

	/**
	 * Rejects unsupported scopes before using them in signed messages.
	 *
	 * @since 0.1.0
	 * @param string $provider Candidate provider.
	 * @param string $form_id Candidate native form ID.
	 * @return bool Whether both identifiers use the supported bounded alphabet.
	 */
	private function scope_valid( string $provider, string $form_id ): bool {
		return in_array( $provider, array( 'comments', 'cf7', 'gravityforms', 'wpforms' ), true ) && 1 === preg_match( '/^[a-zA-Z0-9_-]{1,64}$/D', $form_id );
	}
}
