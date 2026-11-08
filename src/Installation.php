<?php
/**
 * Separates the renamed installation from older copies sharing the same data.
 *
 * @package RktskyAntispam
 * @since 0.5.1
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Prevents duplicate loaders and destructive removal during the slug transition.
 *
 * Activation state is read through native options only. This class never changes
 * another installation, its activation state, or the shared persistence format.
 *
 * @since 0.5.1
 */
final class Installation {

	/**
	 * Entrypoint of this specific copy, independent of an already loaded version.
	 *
	 * @since 0.5.1
	 * @var string
	 */
	private string $file;

	/**
	 * Request-local header checks for the two known entrypoint filenames.
	 *
	 * @since 0.5.1
	 * @var array<string,bool>
	 */
	private static array $owned = array();

	/**
	 * Binds native lifecycle safeguards to one installed copy.
	 *
	 * @since 0.5.1
	 * @param string $file Absolute owned plugin entrypoint.
	 * @return void
	 */
	public function __construct( string $file ) {
		$this->file = $file;
	}

	/**
	 * Registers activation validation before allowing shared service definitions.
	 *
	 * An active legacy copy is detected before it loads, so either native plugin
	 * order remains safe. Only the already active legacy copy provides protection
	 * until the administrator completes the documented deactivate/activate switch.
	 * Externally enabled renamed duplicates are refused, not elected as a winner.
	 *
	 * @since 0.5.1
	 * @return bool Whether this copy may define and boot the shared services.
	 */
	public function register(): bool {
		add_filter( 'lang_dir_for_domain', array( $this, 'translation_directory' ), 10, 3 );
		register_activation_hook( $this->file, array( $this, 'activate' ) );
		if ( $this->conflicts() ) {
			add_action( 'admin_notices', array( $this, 'notice' ) );
			add_action( 'network_admin_notices', array( $this, 'notice' ) );
			return false;
		}
		add_action( 'pre_uninstall_plugin', array( $this, 'protect_removal' ) );
		add_filter( 'map_meta_cap', array( $this, 'activation_capabilities' ), 10, 4 );
		add_filter( 'validate_plugin_requirements', array( $this, 'validate_activation' ), 10, 2 );
		return true;
	}

	/**
	 * Offers bundled catalogues only when native just-in-time discovery has no path.
	 *
	 * The public WordPress 6.6 filter preserves language-pack and custom paths. No
	 * catalogue is loaded here: Core chooses the current locale and loads it on
	 * demand. Registration precedes conflict detection so activation safeguards
	 * remain translated even when this copy may not boot its other services.
	 *
	 * @since 0.5.2
	 * @link https://developer.wordpress.org/reference/hooks/lang_dir_for_domain/
	 * @param string|false $path   Directory already found by Core or another filter.
	 * @param string       $domain Text domain requested by native translation loading.
	 * @param string       $locale Native locale, possibly supplied by a locale filter.
	 * @return string|false Existing path, readable owned fallback, or the original miss.
	 */
	public function translation_directory( string|false $path, string $domain, string $locale ): string|false {
		if ( $path || 'rocketsky-antispam' !== $domain || ! preg_match( '/\A[A-Za-z0-9_@-]{1,64}\z/', $locale ) ) {
			return $path;
		}
		$directory = dirname( $this->file ) . '/languages/';
		return is_readable( $directory . 'rocketsky-antispam-' . $locale . '.mo' ) ? $directory : $path;
	}

	/**
	 * Blocks native UI, bulk and REST activation before an older loader is included.
	 *
	 * Core's activation action runs after the target file is included. The singular
	 * capability check instead precedes inclusion on the supported native screens
	 * and REST controller, including WordPress versions before the requirements filter.
	 *
	 * @since 0.5.1
	 * @param string[] $caps    Originally required primitive capabilities.
	 * @param string   $cap     Native capability being checked.
	 * @param int      $user_id Native user ID; the conflict applies to every user.
	 * @param mixed[]  $args    Native context, starting with the plugin basename.
	 * @return string[] Required capabilities, denying only another owned entrypoint.
	 */
	public function activation_capabilities( array $caps, string $cap, int $user_id, array $args ): array {
		if ( 'activate_plugin' === $cap && isset( $args[0] ) && is_string( $args[0] ) && $this->active_entrypoint( array( $args[0] ) ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	/**
	 * Rejects direct native activation before the target include on WordPress 6.9+.
	 *
	 * Older Core versions do not apply this filter; their native administration and
	 * REST activation remain covered by the singular capability boundary above.
	 * Existing requirement errors are preserved without replacing their explanation.
	 *
	 * @since 0.5.1
	 * @param bool|\WP_Error $result Original native requirements result.
	 * @param string         $plugin Native basename selected for activation.
	 * @return bool|\WP_Error Original result or an actionable installation conflict.
	 */
	public function validate_activation( $result, string $plugin ) {
		if ( is_wp_error( $result ) || ! $this->active_entrypoint( array( $plugin ) ) ) {
			return $result;
		}
		return new \WP_Error( 'rktsky_antispam_installation_conflict', $this->conflict_message() );
	}

	/**
	 * Refuses activation while another copy owns the shared plugin lifecycle.
	 *
	 * Core has not recorded activation when this callback runs. A native error
	 * therefore leaves the existing active copy and all its data untouched.
	 *
	 * @since 0.5.1
	 * @param bool $network_wide Whether Core requested network activation.
	 * @return void
	 */
	public function activate( bool $network_wide = false ): void {
		if ( $this->conflicts() || ( $network_wide && is_multisite() && $this->network_conflicts( get_current_network_id() ) ) ) {
			wp_die(
				esc_html( $this->conflict_message() ),
				'',
				array(
					'response'  => 409,
					'back_link' => true,
				)
			);
		}
	}

	/**
	 * Explains an interrupted handover only to users who can manage activation.
	 *
	 * @since 0.5.1
	 * @return void
	 */
	public function notice(): void {
		if ( current_user_can( 'activate_plugins' ) || current_user_can( 'manage_network_plugins' ) ) {
			wp_admin_notice( esc_html( $this->conflict_message() ), array( 'type' => 'error' ) );
		}
	}

	/**
	 * Stops Core from running an older copy's destructive uninstall script.
	 *
	 * The pre_uninstall_plugin action is not a short-circuit filter: termination is
	 * required before the legacy script can remove the shared tables and settings.
	 * It covers the same known entrypoint names in arbitrary installation folders.
	 *
	 * @since 0.5.1
	 * @param string $plugin Native basename selected for uninstall.
	 * @return void
	 */
	public function protect_removal( string $plugin ): void {
		if ( $this->active_entrypoint( array( $plugin ) ) ) {
			$this->stop_removal();
		}
	}

	/**
	 * Checks all native activation scopes before this copy's uninstall can mutate data.
	 *
	 * A site-inactive copy can still share data with another installation elsewhere
	 * in a network. Only uninstall performs this bounded native site enumeration;
	 * ordinary requests inspect their own site and network activation options.
	 *
	 * @since 0.5.1
	 * @return void
	 */
	public function uninstall(): void {
		if ( $this->conflicts() || $this->active_entrypoint( (array) get_option( 'active_plugins', array() ), true ) ) {
			$this->stop_removal();
		}
		if ( ! is_multisite() ) {
			return;
		}
		$offset = 0;
		do {
			$networks = get_networks(
				array(
					'fields' => 'ids',
					'number' => 100,
					'offset' => $offset,
				)
			);
			foreach ( $networks as $network_id ) {
				if ( $this->active_entrypoint( array_keys( (array) get_network_option( (int) $network_id, 'active_sitewide_plugins', array() ) ), true ) ) {
					$this->stop_removal();
				}
			}
			$count   = count( $networks );
			$offset += 100;
		} while ( 100 === $count );
		$offset = 0;
		do {
			$sites = get_sites(
				array(
					'fields' => 'ids',
					'number' => 100,
					'offset' => $offset,
				)
			);
			foreach ( $sites as $site_id ) {
				if ( $this->active_entrypoint( (array) get_blog_option( (int) $site_id, 'active_plugins', array() ), true ) ) {
					$this->stop_removal();
				}
			}
			$count   = count( $sites );
			$offset += 100;
		} while ( 100 === $count );
	}

	/**
	 * Checks native site/network activation and any previously loaded service copy.
	 *
	 * @since 0.5.1
	 * @return bool Whether another installation must be deactivated first.
	 */
	private function conflicts(): bool {
		if ( defined( 'RKTSKY_ANTISPAM_FILE' ) && realpath( RKTSKY_ANTISPAM_FILE ) !== realpath( $this->file ) ) {
			return true;
		}
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_network_option( get_current_network_id(), 'active_sitewide_plugins', array() ) ) );
		}
		return $this->active_entrypoint( $active );
	}

	/**
	 * Finds an owned copy in a native site's or network's activation list.
	 *
	 * @since 0.5.1
	 * @param mixed[] $active       Native active plugin basenames.
	 * @param bool    $include_self Whether uninstall must also protect this same path.
	 * @return bool Whether a matching entrypoint owns shared data.
	 */
	private function active_entrypoint( array $active, bool $include_self = false ): bool {
		foreach ( $active as $plugin ) {
			if ( is_string( $plugin ) && ( $include_self || plugin_basename( $this->file ) !== $plugin ) && self::owned_entrypoint( $plugin ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Refuses network activation while a subsite still uses another owned copy.
	 *
	 * This paginated read runs only during explicit network activation. Ordinary
	 * requests never enumerate sites or alter their individual activation choices.
	 *
	 * @since 0.5.1
	 * @param int $network_id Native network selected for activation.
	 * @return bool Whether any site in that network still uses another installation.
	 */
	private function network_conflicts( int $network_id ): bool {
		$offset = 0;
		do {
			$sites = get_sites(
				array(
					'network_id' => $network_id,
					'fields'     => 'ids',
					'number'     => 100,
					'offset'     => $offset,
				)
			);
			foreach ( $sites as $site_id ) {
				if ( $this->active_entrypoint( (array) get_blog_option( (int) $site_id, 'active_plugins', array() ) ) ) {
					return true;
				}
			}
			$count   = count( $sites );
			$offset += 100;
		} while ( 100 === $count );
		return false;
	}

	/**
	 * Stops native deletion before any shared data or maintenance state is changed.
	 *
	 * @since 0.5.1
	 * @return void
	 */
	private function stop_removal(): void {
		wp_die(
			esc_html__( 'This copy shares data with another active installation of Rocketsky Antispam. Uninstalling it could delete shared settings and retained entries. Remove only the obsolete plugin files through your hosting file manager; do not run its WordPress Delete action.', 'rocketsky-antispam' ),
			'',
			array(
				'response'  => 409,
				'back_link' => true,
			)
		);
	}

	/**
	 * Confirms a known entrypoint's bounded native headers before treating it as owned.
	 *
	 * Filenames alone can match an unrelated plugin. Only the original or renamed
	 * product header and publisher identify our copies, including the earliest
	 * rktsky author spelling. Core reads at most eight KiB; results are reused within
	 * this request so network checks do not repeatedly read the same plugin files.
	 *
	 * @since 0.5.1
	 * @param string $plugin Native relative plugin basename.
	 * @return bool Whether the basename belongs to the old or current installation.
	 */
	private static function owned_entrypoint( string $plugin ): bool {
		if ( ! in_array( basename( $plugin ), array( 'wp-antispam.php', 'rocketsky-antispam.php' ), true ) || 0 !== validate_file( $plugin ) ) {
			return false;
		}
		if ( isset( self::$owned[ $plugin ] ) ) {
			return self::$owned[ $plugin ];
		}
		$file = WP_PLUGIN_DIR . '/' . $plugin;
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			self::$owned[ $plugin ] = false;
			return false;
		}
		$headers                = get_file_data(
			$file,
			array(
				'Name'       => 'Plugin Name',
				'Author'     => 'Author',
				'TextDomain' => 'Text Domain',
			)
		);
		$legacy                 = 'wp-antispam.php' === basename( $plugin ) && 'WP Antispam' === $headers['Name'] && 'wp-antispam' === $headers['TextDomain'];
		$current                = 'rocketsky-antispam.php' === basename( $plugin ) && 'Rocketsky Antispam' === $headers['Name'] && 'rocketsky-antispam' === $headers['TextDomain'];
		self::$owned[ $plugin ] = ( $legacy || $current ) && in_array( $headers['Author'], array( 'Rocketsky', 'rktsky' ), true );
		return self::$owned[ $plugin ];
	}

	/**
	 * Supplies one actionable explanation for activation and interrupted loading.
	 *
	 * @since 0.5.1
	 * @return string Localized plain-text instruction.
	 */
	private function conflict_message(): string {
		return __( 'Deactivate the previous installation before activating Rocketsky Antispam. Do not delete it: the installations share settings and retained data. Network-active copies must be deactivated in Network Admin.', 'rocketsky-antispam' );
	}
}
