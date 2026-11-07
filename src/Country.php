<?php
/**
 * Resolves country metadata from local GeoIP or explicitly trusted infrastructure.
 *
 * @package RktskyAntispam
 * @since 0.2.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Never calls an external IP lookup and never persists an address or country.
 *
 * @since 0.2.0
 */
final class Country {
	/**
	 * Local country configuration.
	 *
	 * @since 0.2.0
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Receives validated per-site infrastructure settings.
	 *
	 * @since 0.2.0
	 * @param Settings $settings Local source and trusted proxy configuration.
	 * @return void
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Reports configured source capability without claiming every address can be located.
	 *
	 * @since 0.2.0
	 * @return bool Whether a local source or explicit trusted-header configuration exists.
	 */
	public function available(): bool {
		$options = $this->settings->all();
		return 'geoip' === $options['country_source'] ? extension_loaded( 'geoip' ) && function_exists( 'geoip_country_code_by_name' ) : '' !== $options['trusted_proxies'];
	}

	/**
	 * Resolves a two-letter country only from a local database or a verified peer.
	 *
	 * No raw address, header or inferred country is included in statistics.
	 *
	 * @since 0.2.0
	 * @return string Uppercase country code, or empty for absent/untrusted/unknown data.
	 */
	public function detect(): string {
		$options = $this->settings->all();
		if ( $options['privacy_mode'] || 'off' === $options['country_mode'] ) {
			return '';
		}
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! filter_var( $remote, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		if ( 'trusted_header' === $options['country_source'] ) {
			if ( ! $this->trusted( $remote, $options['trusted_proxies'] ) ) {
				return '';
			}
			$code = isset( $_SERVER[ $options['country_header'] ] ) && is_string( $_SERVER[ $options['country_header'] ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $options['country_header'] ] ) ) : '';
		} elseif ( extension_loaded( 'geoip' ) && function_exists( 'geoip_country_code_by_name' ) ) {
			// Only numeric peer addresses enter the local extension: no DNS lookup.
			$code = @geoip_country_code_by_name( $remote ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Missing local GeoIP records are represented as unknown, not a public warning.
		} else {
			return '';
		}
		return is_string( $code ) && preg_match( '/^[A-Z]{2}$/D', $code ) && ! in_array( $code, array( 'XX', 'ZZ', 'T1', 'EU' ), true ) ? $code : '';
	}

	/**
	 * Validates one exact IP or CIDR without accepting hostnames or wide malformed masks.
	 *
	 * @since 0.2.0
	 * @param string $cidr Administrator supplied proxy address or CIDR.
	 * @return bool Whether the address and optional mask are syntactically valid.
	 */
	public static function valid_cidr( string $cidr ): bool {
		$parts = explode( '/', $cidr );
		$ip = @inet_pton( $parts[0] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid administrator input is a normal validation failure.
		if ( false === $ip || count( $parts ) > 2 ) {
			return false;
		}
		return ! isset( $parts[1] ) || ( ctype_digit( $parts[1] ) && (int) $parts[1] <= strlen( $ip ) * 8 );
	}

	/**
	 * Matches a peer against bounded explicitly configured IPv4/IPv6 networks.
	 *
	 * @since 0.2.0
	 * @param string $address Validated numeric direct peer address.
	 * @param string $networks Newline-delimited validated IPs/CIDRs.
	 * @return bool Whether a configured network includes the peer.
	 */
	private function trusted( string $address, string $networks ): bool {
		$address = inet_pton( $address );
		foreach ( explode( "\n", $networks ) as $network ) {
			if ( ! self::valid_cidr( $network ) ) {
				continue;
			}
			$parts = explode( '/', $network );
			$base = inet_pton( $parts[0] );
			if ( strlen( $address ) !== strlen( $base ) ) {
				continue;
			}
			$bits = isset( $parts[1] ) ? (int) $parts[1] : strlen( $base ) * 8;
			$bytes = intdiv( $bits, 8 );
			$remainder = $bits % 8;
			if ( substr( $address, 0, $bytes ) === substr( $base, 0, $bytes ) && ( 0 === $remainder || ( ord( $address[ $bytes ] ) & ( 255 << ( 8 - $remainder ) ) ) === ( ord( $base[ $bytes ] ) & ( 255 << ( 8 - $remainder ) ) ) ) ) {
				return true;
			}
		}
		return false;
	}
}
