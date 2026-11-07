<?php
/**
 * Evaluates local protection evidence and explicitly configured bounded content rules.
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
	 * Optional local content policy; never substitutes for token verification.
	 *
	 * @since 0.2.0
	 * @var ContentRules|null
	 */
	private ?ContentRules $rules;
	/**
	 * Statistical receipts for native reclassification, never security permits.
	 *
	 * @since 0.2.0
	 * @var array<string,array{reason:string,day:string}>
	 */
	private array $receipts = array();

	/**
	 * Receives explicit dependencies so security state has one owner per request.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Receives an optional local content-rule service.
	 * @param Settings          $settings Validated settings.
	 * @param Token             $token Token protocol.
	 * @param Store             $store Durable replay ledger.
	 * @param ContentRules|null $rules Optional bounded local content policy.
	 * @return void
	 */
	public function __construct( Settings $settings, Token $token, Store $store, ?ContentRules $rules = null ) {
		$this->settings = $settings;
		$this->token    = $token;
		$this->store    = $store;
		$this->rules    = $rules;
	}

	/**
	 * Checks raw unslashed POST protection controls; other fields are never persisted.
	 *
	 * A pass means this guard passed, not that native provider validation accepted mail.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Evaluates allowlisted fields through the optional local content policy.
	 * @param string               $provider Expected integration.
	 * @param string               $form_id Expected native form ID.
	 * @param array                $posted Unslashed submitted controls; never an authorization source.
	 * @param array<string,string> $fields Whitelisted native text fields for local rules.
	 * @return string Empty for a pass, otherwise a fixed internal reason code.
	 */
	public function check( string $provider, string $form_id, array $posted, array $fields = array() ): string {
		if ( ! $this->settings->enabled( $provider ) ) {
			return '';
		}
		$value  = $posted['rktsky_antispam_token'] ?? '';
		$value  = is_string( $value ) && strlen( $value ) <= 400 ? $value : '';
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
		}
		if ( '' === $reason && $this->rules ) {
			$reason = $this->rules->check( $provider, $fields );
		}
		if ( '' === $reason && ! $this->store->claim( $proof['digest'], $proof['expires'] ) ) {
			$reason = $this->store->claim_failure();
		}
		$day = gmdate( 'Y-m-d' );
		$this->store->record( $provider, $reason, $day );
		$this->receipts[ hash( 'sha256', $provider . '|' . $form_id . '|' . $value ) ] = array(
			'reason' => $reason,
			'day'    => $day,
		);
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
	 * Counts a native provider spam result once, replacing an earlier local pass.
	 *
	 * Statistical receipts cannot bypass any subsequent security check.
	 *
	 * @since 0.2.0
	 * @param string $provider Known provider.
	 * @param string $form_id Native form ID.
	 * @param array  $posted Raw protection controls only; contents are ignored here.
	 * @return void
	 */
	public function observe_spam( string $provider, string $form_id, array $posted ): void {
		$token = $posted['rktsky_antispam_token'] ?? '';
		$token = is_string( $token ) && strlen( $token ) <= 400 ? $token : '';
		$key   = hash( 'sha256', $provider . '|' . $form_id . '|' . $token );
		if ( isset( $this->receipts[ $key ] ) ) {
			if ( '' === $this->receipts[ $key ]['reason'] ) {
				$this->store->reclassify( $provider, $this->receipts[ $key ]['day'] );
				$this->receipts[ $key ]['reason'] = 'native_spam';
			}
			return;
		}
		$this->store->record( $provider, 'native_spam' );
		$this->receipts[ $key ] = array(
			'reason' => 'native_spam',
			'day'    => gmdate( 'Y-m-d' ),
		);
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
