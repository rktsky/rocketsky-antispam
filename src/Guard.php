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
	 * @since 0.5.0 Claims authentic proof before classifying content for native spam storage.
	 * @param string               $provider Expected integration.
	 * @param string               $form_id Expected native form ID.
	 * @param array                $posted Unslashed submitted controls; never an authorization source.
	 * @param array<string,string> $fields Whitelisted native text fields for local rules.
	 * @param bool                 $technical_only Whether to exclude all content and country policies.
	 * @since 0.3.0 Supports technical-only checks for account and payment workflows.
	 * @return string Empty for a pass, otherwise a fixed internal reason code.
	 */
	public function check( string $provider, string $form_id, array $posted, array $fields = array(), bool $technical_only = false ): string {
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
		if ( '' === $reason && ! $this->store->claim( $proof['digest'], $proof['expires'] ) ) {
			$reason = $this->store->claim_failure();
		}
		// A native spam entry is still persistence: content matches never bypass
		// atomic proof consumption, even when the visitor submits the same text again.
		if ( '' === $reason && ! $technical_only && $this->rules ) {
			$reason = $this->rules->check( $provider, $fields );
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
	 * Distinguishes configured content matches from unverifiable technical failures.
	 *
	 * Only these verdicts can use a provider's native spam workflow after check()
	 * has authenticated and consumed proof. A PCRE processing limit is technical;
	 * it does not establish a match and must never authorize native persistence.
	 *
	 * @since 0.5.0
	 * @param string $reason Fixed verdict returned by this guard.
	 * @return bool Whether the verdict describes a configured content policy match.
	 */
	public static function is_content_reason( string $reason ): bool {
		return in_array( $reason, array( 'keyword', 'bbcode', 'pattern', 'language', 'country' ), true );
	}

	/**
	 * Describes a content rejection without promising delivery or retained contents.
	 *
	 * Native spam persistence and optional local quarantine have different storage
	 * owners. This public message reveals neither matched rules nor storage details.
	 *
	 * @since 0.5.0
	 * @return string Localized visible rejection suitable for native confirmations.
	 */
	public function spam_message(): string {
		return __( 'The spam protection rejected this submission. Please check your message and try again.', 'rocketsky-antispam' );
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
		return __( 'Your submission could not be verified. Try again. If the error persists, reload the page and check that JavaScript is enabled.', 'rocketsky-antispam' );
	}
}
