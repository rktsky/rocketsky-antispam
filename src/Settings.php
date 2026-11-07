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
	 * @return array<string,bool|int> Settings defaults.
	 */
	public function defaults(): array {
		return array(
			'comments'            => true,
			'cf7'                 => true,
			'gravityforms'        => true,
			'wpforms'             => true,
			'minimum_seconds'     => 0,
			'token_ttl'           => 7200,
			'retention_days'      => 30,
			'delete_on_uninstall' => true,
		);
	}

	/**
	 * Reads only known settings and repairs malformed values in memory.
	 *
	 * @since 0.1.0
	 * @return array<string,bool|int> Validated configuration.
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
		return in_array( $provider, array( 'comments', 'cf7', 'gravityforms', 'wpforms' ), true ) && ! empty( $this->all()[ $provider ] );
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
	 * @return array<string,bool|int> Persistable known values only.
	 */
	public function sanitize( $input ): array {
		return $this->normalize( is_array( $input ) ? $input : array(), true );
	}

	/**
	 * Normalizes stored or submitted values without accepting arbitrary keys.
	 *
	 * @since 0.1.0
	 * @param array $input Candidate values.
	 * @param bool  $submitted Whether missing checkboxes mean false.
	 * @return array<string,bool|int> Complete settings.
	 */
	private function normalize( array $input, bool $submitted ): array {
		$result = $this->defaults();
		foreach ( array( 'comments', 'cf7', 'gravityforms', 'wpforms', 'delete_on_uninstall' ) as $key ) {
			if ( array_key_exists( $key, $input ) || $submitted ) {
				$result[ $key ] = in_array( $input[ $key ] ?? false, array( true, 1, '1' ), true );
			}
		}
		foreach ( array(
			'minimum_seconds' => array( 0, 10 ),
			'retention_days'  => array( 1, 90 ),
		) as $key => $bounds ) {
			if ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) && preg_match( '/^\d{1,3}$/D', (string) $input[ $key ] ) ) {
				$result[ $key ] = max( $bounds[0], min( $bounds[1], (int) $input[ $key ] ) );
			}
		}
		return $result;
	}
}
