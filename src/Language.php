<?php
/**
 * Estimates supported comment languages locally without storing text or profiles.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Uses small common-word vocabularies and abstains on short or ambiguous text.
 *
 * This heuristic is not a linguistic classifier or a guarantee of language.
 *
 * @since 0.2.0
 */
final class Language {
	/**
	 * Returns a supported language only when enough distinguishing words occur.
	 *
	 * @since 0.2.0
	 * @param string $text Bounded comment text; never transmitted or retained here.
	 * @return string de/en/fr/it/es/nl/pt, or empty for uncertain input.
	 */
	public function detect( string $text ): string {
		$text = wp_check_invalid_utf8( substr( wp_strip_all_tags( $text ), 0, 16384 ), true );
		preg_match_all( '/\p{L}+/u', $text, $matches );
		$words = array_map( 'strtolower', $matches[0] ?? array() );
		if ( count( $words ) < 12 ) {
			return '';
		}
		$vocabularies = array(
			'de' => 'der die das und ist sind nicht ich wir haben eine einen einem einer mit für auf auch diese dieser dieses wird werden aber oder wenn dass zum zur bei sich noch können kann sehr aus',
			'en' => 'the and is are not have has this that these those with for from your you they their would should could about which what when where there because more than our',
			'fr' => 'le la les des une un et est sont dans pour avec nous vous votre cette ces pas que qui sur mais comme plus sans aux du ne merci',
			'it' => 'il lo gli delle della degli una uno che sono siamo questo questa questi queste per con non nel nella anche come quando grazie voi mio',
			'es' => 'el las los una unos unas que para por con no como pero del esto esta estos estas muy gracias cuando porque nosotros nuestro sobre sin',
			'nl' => 'het een deze dit dat zijn niet voor met van wij jullie mijn jouw onze ook maar als omdat welke worden hebben heel meer bedankt naar',
			'pt' => 'os as uma uns umas que para por com não como mas dos das isto este esta estes estas muito obrigado quando porque nós nosso sobre sem',
		);
		$scores = array();
		foreach ( $vocabularies as $language => $vocabulary ) {
			$hits = array_intersect( array_unique( $words ), explode( ' ', $vocabulary ) );
			$scores[ $language ] = count( $hits );
		}
		arsort( $scores );
		$ordered = array_values( $scores );
		return $ordered[0] >= 4 && $ordered[0] - $ordered[1] >= 2 ? (string) array_key_first( $scores ) : '';
	}
}
