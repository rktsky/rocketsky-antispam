<?php
/**
 * Validates the complete settings boundary with conservative defaults.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Owns per-site settings without modifying provider configuration.
 *
 * @since 0.1.0
 */
final class Settings {
	/**
	 * Per-site option name.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const OPTION = 'rktsky_antispam_settings';

	/**
	 * Returns explicit defaults, including fixed protection-token lifetime.
	 *
	 * @since 0.1.0
	 * @return array<string,mixed> Settings defaults, including bounded local policies.
	 */
	public function defaults(): array {
		return array(
			'comments'                 => true,
			'allow_pingbacks'          => false,
			'cf7'                      => true,
			'gravityforms'             => true,
			'wpforms'                  => true,
			'registration' => false,
			'formidable' => false,
			'fluentforms' => false,
			'wsform' => false,
			'elementor' => false,
			'divi' => false,
			'wc_checkout' => false,
			'wc_registration' => false,
			'minimum_seconds'          => 0,
			'token_ttl'                => 7200,
			'retention_days'           => 30,
			'delete_on_uninstall'      => true,
			'privacy_mode'             => true,
			'quarantine_enabled'       => false,
			'quarantine_days'          => 7,
			'spam_keywords'            => '',
			'bbcode_filter'            => false,
			'spam_patterns'            => '',
			'allowed_languages'        => array(),
			'unknown_language_allowed' => true,
			'country_mode'             => 'off',
			'countries'                => array(),
			'country_unknown_allowed'  => true,
			'country_source'           => 'geoip',
			'country_header'           => 'HTTP_CF_IPCOUNTRY',
			'trusted_proxies'          => '',
			'notify_admin'             => false,
			'comment_spam_days'        => 0,
		);
	}

	/**
	 * Reads only known settings and repairs malformed values in memory.
	 *
	 * @since 0.1.0
	 * @return array<string,mixed> Validated effective configuration.
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );
		return $this->normalize( is_array( $stored ) ? $stored : array(), false );
	}

	/**
	 * Checks an explicit provider ID; unknown integrations remain disabled.
	 *
	 * @since 0.1.0
	 * @param string $provider Provider identifier.
	 * @return bool Whether the provider is enabled.
	 */
	public function enabled( string $provider ): bool {
		return in_array( $provider, Providers::ids(), true ) && ! empty( $this->all()[ $provider ] );
	}

	/**
	 * Registers native Settings API persistence; options.php enforces capability and nonce.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register(): void {
		register_setting(
			'rktsky_antispam',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
				'default'           => $this->defaults(),
			)
		);
	}

	/**
	 * Applies checkbox semantics and bounds to privileged settings submissions.
	 *
	 * @since 0.1.0
	 * @param mixed $input Untrusted submitted option.
	 * @return array<string,mixed> Persistable known values only.
	 */
	public function sanitize( $input ): array {
		// Each visible checkbox has a hidden zero. Omitted tabs preserve their settings.
		return $this->normalize( array_replace( $this->all(), is_array( $input ) ? $input : array() ), true );
	}

	/**
	 * Compiles a bounded administrator pattern with local PCRE resource limits.
	 *
	 * @since 0.2.0
	 * @param string $body Pattern body without delimiters; one rule per line.
	 * @return string|null Safe compiled pattern or null for invalid input.
	 */
	public static function regex( string $body ): ?string {
		if ( '' === $body || strlen( $body ) > 256 || str_contains( $body, '~' ) || str_contains( $body, "\0" ) ) {
			return null;
		}
		$pattern = '~(*LIMIT_MATCH=10000)(*LIMIT_DEPTH=100)(?:' . $body . ')~iu';
		return false === @preg_match( $pattern, '' ) ? null : $pattern; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid admin patterns must not emit PHP warnings or leak input.
	}

	/**
	 * Normalizes stored or submitted values without accepting arbitrary keys.
	 *
	 * @since 0.1.0
	 * @param array $input Candidate values.
	 * @param bool  $submitted Whether validation errors should be added to the settings UI.
	 * @return array<string,mixed> Complete effective settings; strict mode overrides optional processing.
	 */
	private function normalize( array $input, bool $submitted ): array {
		$result = $this->defaults();
		foreach ( array_merge( Providers::ids(), array( 'allow_pingbacks', 'delete_on_uninstall', 'privacy_mode', 'quarantine_enabled', 'bbcode_filter', 'unknown_language_allowed', 'country_unknown_allowed', 'notify_admin' ) ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$result[ $key ] = in_array( $input[ $key ] ?? false, array( true, 1, '1' ), true );
			}
		}
		foreach ( array(
			'minimum_seconds'   => array( 0, 10 ),
			'retention_days'    => array( 1, 90 ),
			'quarantine_days'   => array( 1, 30 ),
			'comment_spam_days' => array( 0, 90 ),
		) as $key => $bounds ) {
			if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) && preg_match( '/^\d{1,3}$/D', (string) $input[ $key ] ) ) {
				$result[ $key ] = max( $bounds[0], min( $bounds[1], (int) $input[ $key ] ) );
			}
		}
		foreach ( array(
			'country_mode'   => array( 'off', 'allow', 'block' ),
			'country_source' => array( 'geoip', 'trusted_header' ),
			'country_header' => array( 'HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE' ),
		) as $key => $allowed ) {
			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) && in_array( $input[ $key ], $allowed, true ) ) {
				$result[ $key ] = $input[ $key ];
			}
		}
		$languages                   = isset( $input['allowed_languages'] ) && is_array( $input['allowed_languages'] ) ? $input['allowed_languages'] : array();
		$result['allowed_languages'] = array_values( array_intersect( array( 'de', 'en', 'fr', 'it', 'es', 'nl', 'pt' ), array_filter( $languages, 'is_string' ) ) );
		$countries                   = $input['countries'] ?? array();
		$countries                   = is_string( $countries ) ? preg_split( '/[\s,;]+/', substr( $countries, 0, 1500 ) ) : $countries;
		foreach ( is_array( $countries ) ? array_slice( $countries, 0, 249 ) : array() as $country ) {
			if ( is_string( $country ) && preg_match( '/^[a-z]{2}$/iD', $country ) ) {
				$result['countries'][] = strtoupper( $country );
			}
		}
		$result['countries'] = array_values( array_unique( $result['countries'] ) );
		foreach ( array(
			'spam_keywords'   => array( 50, 80 ),
			'spam_patterns'   => array( 10, 256 ),
			'trusted_proxies' => array( 20, 80 ),
		) as $key => $limits ) {
			$raw   = isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? wp_check_invalid_utf8( substr( $input[ $key ], 0, 8192 ), true ) : '';
			$lines = preg_split( '/\R/u', $raw );
			$clean = array();
			foreach ( array_slice( (array) $lines, 0, $limits[0] ) as $line ) {
				$line = trim( $line );
				if ( '' === $line || strlen( $line ) > $limits[1] ) {
					continue;
				}
				if ( 'spam_patterns' === $key && null === self::regex( $line ) ) {
					if ( $submitted ) {
						add_settings_error( self::OPTION, 'pattern', __( 'An invalid or oversized regular expression was omitted. Enter pattern bodies without delimiters, one per line.', 'wp-antispam' ) );
					}
					continue;
				}
				if ( 'trusted_proxies' === $key && ! Country::valid_cidr( $line ) ) {
					continue;
				}
				$clean[] = $line;
			}
			$result[ $key ] = implode( "\n", array_unique( $clean ) );
		}
		if ( $result['privacy_mode'] ) {
			$result['allow_pingbacks']    = false;
			$result['quarantine_enabled'] = false;
			$result['notify_admin']       = false;
			$result['country_mode']       = 'off';
		}
		return $result;
	}
}
