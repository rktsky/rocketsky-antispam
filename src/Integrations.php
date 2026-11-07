<?php
/**
 * Loads selected native integration modules from an explicit owned class map.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/Providers.php';
require_once __DIR__ . '/Integrations/Adapter.php';

/**
 * Registers supported adapters and preserves existing public callback names.
 *
 * @since 0.1.0
 */
final class Integrations extends Integrations\Adapter {
	/**
	 * Existing callbacks mapped to their sole owning adapter.
	 *
	 * @since 0.3.0
	 * @var array<string,string>
	 */
	private const CALLBACKS = array(
		'comment_fields' => 'comments',
		'check_comment' => 'comments',
		'check_rest_comment' => 'comments',
		'observe_comment_spam' => 'comments',
		'cf7_fields' => 'cf7',
		'cf7_processed_data' => 'cf7',
		'check_cf7' => 'cf7',
		'cf7_message' => 'cf7',
		'gravity_fields' => 'gravityforms',
		'check_gravity' => 'gravityforms',
		'gravity_message' => 'gravityforms',
		'abort_gravity' => 'gravityforms',
		'gravity_confirmation' => 'gravityforms',
		'observe_gravity_spam' => 'gravityforms',
		'track_gravity_entry' => 'gravityforms',
		'wpforms_fields' => 'wpforms',
		'check_wpforms' => 'wpforms',
		'begin_wpforms' => 'wpforms',
		'observe_wpforms_honeypot' => 'wpforms',
		'observe_wpforms_antispam' => 'wpforms',
		'observe_wpforms_spam' => 'wpforms',
	);
	/**
	 * Loaded modules, each retaining its own per-request native lifecycle state.
	 *
	 * @since 0.3.0
	 * @var array<string,Integrations\Adapter>
	 */
	private array $adapters = array();

	/**
	 * Registered providers, kept separate from compatibility-only loaded modules.
	 *
	 * @since 0.3.0
	 * @var array<string,bool>
	 */
	private array $registered = array();

	/**
	 * Reports available provider entry points after all plugins have loaded.
	 *
	 * @since 0.1.0
	 * @return array<string,bool> Native contract availability by provider ID.
	 */
	public static function detected(): array {
		return Providers::detected();
	}

	/**
	 * Registers only selected providers with an available native contract.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register(): void {
		foreach ( self::detected() as $provider => $available ) {
			if ( $available && ! isset( $this->registered[ $provider ] ) && $this->settings->enabled( $provider ) ) {
				$this->adapter( $provider )->register();
				$this->registered[ $provider ] = true;
			}
		}
	}

	/**
	 * Registers theme-owned contracts after WordPress has loaded the active theme.
	 *
	 * Divi themes initialize after plugins_loaded; a one-time early detection
	 * would silently miss them. Multiple native lifecycle attempts remain
	 * idempotent and do not register duplicate validation or rendering hooks.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register_theme(): void {
		if ( isset( $this->registered['divi'] ) || ! $this->settings->enabled( 'divi' ) ) {
			return;
		}
		if ( ! empty( self::detected()['divi'] ) ) {
			$this->adapter( 'divi' )->register();
			$this->registered['divi'] = true;
		}
	}

	/**
	 * Forwards former public callbacks through a fixed compatibility map.
	 *
	 * Private adapter helpers and request-derived method names are not exposed.
	 * Native hooks register directly on their adapters, avoiding this dispatcher.
	 *
	 * @since 0.3.0
	 * @param string $method Former public callback name.
	 * @param array  $arguments Original callback arguments.
	 * @return mixed Native callback result.
	 * @throws \BadMethodCallException When the name was never a public callback.
	 */
	public function __call( string $method, array $arguments ) {
		if ( ! isset( self::CALLBACKS[ $method ] ) ) {
			throw new \BadMethodCallException( 'Unknown integration callback.' );
		}
		return $this->adapter( self::CALLBACKS[ $method ] )->$method( ...$arguments );
	}

	/**
	 * Loads one owned adapter from the immutable provider map.
	 *
	 * @since 0.3.0
	 * @param string $provider Validated internal provider ID.
	 * @return Integrations\Adapter Request-scoped provider adapter.
	 */
	private function adapter( string $provider ): Integrations\Adapter {
		if ( ! isset( $this->adapters[ $provider ] ) ) {
			$name = Providers::ADAPTERS[ $provider ];
			require_once __DIR__ . '/Integrations/' . $name . '.php';
			$class = __NAMESPACE__ . '\\Integrations\\' . $name;
			$this->adapters[ $provider ] = new $class( $this->settings, $this->guard, $this->fields, $this->quarantine, $this->feedback );
		}
		return $this->adapters[ $provider ];
	}
}
