<?php
/**
 * Applies bounded local content rules after technical submission proof succeeds.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates explicitly configured rules without saving submitted field values.
 *
 * Rules classify content, not people. A match is a configured rejection reason,
 * not proof that every matching submission is unwanted.
 *
 * @since 0.2.0
 */
final class ContentRules {

	/**
	 * Normalized site settings.
	 *
	 * @since 0.2.0
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Local language classifier.
	 *
	 * @since 0.2.0
	 * @var Language
	 */
	private Language $language;

	/**
	 * Local country resolver with no external lookup.
	 *
	 * @since 0.2.0
	 * @var Country
	 */
	private Country $country;

	/**
	 * Receives local policy services without initiating a visitor lookup.
	 *
	 * @since 0.2.0
	 * @param Settings $settings Normalized policy settings.
	 * @param Language $language Local language classifier.
	 * @param Country  $country  Local country resolver.
	 * @return void
	 */
	public function __construct( Settings $settings, Language $language, Country $country ) {
		$this->settings = $settings;
		$this->language = $language;
		$this->country  = $country;
	}

	/**
	 * Checks selected native content fields with bounded work and explicit unknowns.
	 *
	 * Only comments use language/country policy. Invalid or resource-limited regex
	 * evaluations do not establish a content match. A runtime evaluation failure
	 * rejects as a technical pattern_limit reason, never as confirmed spam.
	 * This method never mutates options or stores the submitted text.
	 *
	 * @since 0.2.0
	 * @param string               $provider Native provider identifier.
	 * @param array<string,string> $fields   Allowlisted content fields.
	 * @return string Fixed rejection reason, or empty when no enabled rule matched.
	 */
	public function check( string $provider, array $fields ): string {
		$options = $this->settings->all();
		$text    = $this->text( $fields );
		$visible = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$words   = is_string( $options['spam_keywords'] ?? null ) ? explode( "\n", $options['spam_keywords'] ) : array();

		foreach ( array_slice( $words, 0, 50 ) as $word ) {
			$word = wp_check_invalid_utf8( trim( $word ), true );
			if ( '' === $word || strlen( $word ) > 320 ) {
				continue;
			}
			$pattern = '~' . preg_quote( $word, '~' ) . '~iu';
			if ( 1 === preg_match( $pattern, $text ) || 1 === preg_match( $pattern, $visible ) ) {
				return 'keyword';
			}
		}

		// URL tags alone are the signal; ordinary BBCode formatting is unaffected.
		if ( ! empty( $options['bbcode_filter'] ) && ( 1 === preg_match( '~\[url(?:=[^\]\r\n]{1,1024})?\]~iu', $text ) || 1 === preg_match( '~\[url(?:=[^\]\r\n]{1,1024})?\]~iu', $visible ) ) ) {
			return 'bbcode';
		}

		$patterns = is_string( $options['spam_patterns'] ?? null ) ? explode( "\n", $options['spam_patterns'] ) : array();
		foreach ( array_slice( $patterns, 0, 10 ) as $body ) {
			$body    = trim( $body );
			$pattern = Settings::regex( $body );
			if ( null === $pattern ) {
				continue;
			}
			// Settings validate syntax; runtime limits or externally modified options can still fail.
			$match = @preg_match( $pattern, $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid or resource-limited rules must not emit public PHP warnings.
			if ( 1 === $match ) {
				return 'pattern';
			}
			if ( false === $match ) {
				return 'pattern_limit';
			}
		}

		if ( 'comments' !== $provider ) {
			return '';
		}

		$languages = is_array( $options['allowed_languages'] ?? null ) ? $options['allowed_languages'] : array();
		if ( ! empty( $languages ) ) {
			// Names, addresses and URLs must not influence comment-language detection.
			$content  = isset( $fields['content'] ) && is_string( $fields['content'] ) ? $this->text( array( $fields['content'] ) ) : '';
			$language = $this->language->detect( html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( ( '' === $language && ! ( $options['unknown_language_allowed'] ?? true ) ) || ( '' !== $language && ! in_array( $language, $languages, true ) ) ) {
				return 'language';
			}
		}

		$mode = $options['country_mode'] ?? 'off';
		if ( in_array( $mode, array( 'block', 'allow' ), true ) ) {
			$country   = $this->country->detect();
			$countries = is_array( $options['countries'] ?? null ) ? $options['countries'] : array();
			if ( '' === $country ) {
				return ! empty( $options['country_unknown_allowed'] ?? true ) ? '' : 'country';
			}
			$listed = in_array( $country, $countries, true );
			if ( ( 'block' === $mode && $listed ) || ( 'allow' === $mode && ! $listed ) ) {
				return 'country';
			}
		}

		return '';
	}

	/**
	 * Builds a bounded UTF-8 subject without accepting nested objects or arrays.
	 *
	 * At most thirty string values and eight KiB reach the pattern engine.
	 * Truncation can leave later text unchecked; no complete-content guarantee is
	 * derived from a non-match.
	 *
	 * @since 0.2.0
	 * @param array<string,string> $fields Allowlisted content values.
	 * @return string Valid UTF-8 subject, bounded to eight KiB before repair.
	 */
	private function text( array $fields ): string {
		$text = '';
		$seen = 0;
		foreach ( $fields as $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}
			$separator = '' === $text ? '' : "\n";
			$remaining = 8192 - strlen( $text ) - strlen( $separator );
			if ( $remaining <= 0 ) {
				break;
			}
			$text .= $separator . substr( $value, 0, $remaining );
			if ( strlen( $text ) >= 8192 || ++$seen >= 30 ) {
				break;
			}
		}

		return wp_check_invalid_utf8( $text, true );
	}
}
