<?php
/**
 * Evaluates local protection evidence without reading submission content.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Composes signed challenge, dynamic honeypot, optional timing and atomic replay checks.
 *
 * @since 0.1.0
 */
final class Guard {
	/**
	 * Validated per-site settings.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private Settings $settings;
	/**
	 * Stateless challenge authentication.
	 *
	 * @since 0.1.0
	 * @var Token
	 */
	private Token $token;
	/**
	 * Replay claims and aggregate counters.
	 *
	 * @since 0.1.0
	 * @var Store
	 */
	private Store $store;

	/**
	 * Receives explicit dependencies so security state has one owner per request.
	 *
	 * @since 0.1.0
	 * @param Settings $settings Validated settings.
	 * @param Token    $token Token protocol.
	 * @param Store    $store Durable replay ledger.
	 * @return void
	 */
	public function __construct( Settings $settings, Token $token, Store $store ) {
		$this->settings = $settings;
		$this->token    = $token;
		$this->store    = $store;
	}

	/**
	 * Checks raw unslashed POST protection controls; other fields are never persisted.
	 *
	 * A pass means this guard passed, not that native provider validation accepted mail.
	 *
	 * @since 0.1.0
	 * @param string $provider Expected integration.
	 * @param string $form_id Expected native form ID.
	 * @param array  $posted Unslashed submitted controls; never an authorization source.
	 * @return string Empty for a pass, otherwise a fixed internal reason code.
	 */
	public function check( string $provider, string $form_id, array $posted ): string {
		if ( ! $this->settings->enabled( $provider ) ) {
			return '';
		}
		$value = $posted['rktsky_antispam_token'] ?? '';
		$value = is_string( $value ) && strlen( $value ) <= 400 ? $value : '';
		$reason = '';
		$proof  = '' !== $value ? $this->token->verify( $value, $provider, $form_id ) : false;
		if ( '' === $value ) {
			$reason = 'missing';
		} elseif ( false === $proof ) {
			$reason = 'invalid';
		} elseif ( ! isset( $posted[ $proof['trap'] ] ) || ! is_string( $posted[ $proof['trap'] ] ) || '' !== $posted[ $proof['trap'] ] ) {
			$reason = 'honeypot';
		} elseif ( time() - $proof['issued'] < $this->settings->all()['minimum_seconds'] ) {
			$reason = 'too_fast';
		} elseif ( ! $this->store->claim( $proof['digest'], $proof['expires'] ) ) {
			$reason = $this->store->claim_failure();
		}
		$this->store->record( $provider, $reason );
		return $reason;
	}

	/**
	 * Counts an explicit integration policy rejection without reading visitor data.
	 *
	 * @since 0.1.0
	 * @param string $provider Known integration.
	 * @param string $reason Fixed policy code, validated again by the store.
	 * @return void
	 */
	public function reject( string $provider, string $reason ): void {
		$this->store->record( $provider, $reason );
	}

	/**
	 * Provides an actionable error without exposing which protection failed.
	 *
	 * @since 0.1.0
	 * @return string Localized public error.
	 */
	public function message(): string {
		return __( 'The spam protection could not verify this submission. Please wait a moment and try again. If it still fails, reload the page. JavaScript must be enabled.', 'wp-antispam' );
	}
}
