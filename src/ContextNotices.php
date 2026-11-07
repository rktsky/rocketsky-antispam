<?php
/**
 * Shows local protection and review guidance on verified provider admin screens.
 *
 * No frontend branding, submission query, dismissal record or remote call is added.
 *
 * @package RktskyAntispam
 * @since 0.5.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Limits one native notice to relevant screens and currently active integrations.
 *
 * @since 0.5.0
 */
final class ContextNotices {

	/**
	 * Native menu slugs and their documented original parent menus.
	 *
	 * WordPress resolves their actual registered hooks, including translated menu
	 * titles. These fixed pairs never come from submitted form or query values.
	 * Gravity Forms chooses a different top-level parent for entry-only roles.
	 *
	 * @since 0.5.0
	 * @var array<string,array<int,array{0:string,1:string}>> Provider menu inventory.
	 */
	private const MENUS = array(
		'cf7'          => array( array( 'wpcf7', 'wpcf7-dashboard' ), array( 'wpcf7', 'wpcf7' ), array( 'wpcf7-dashboard', 'wpcf7-dashboard' ), array( 'flamingo_inbound', 'flamingo' ) ),
		'gravityforms' => array( array( 'gf_edit_forms', 'gf_edit_forms' ), array( 'gf_entries', 'gf_edit_forms' ), array( 'gf_edit_forms', 'gf_entries' ), array( 'gf_entries', 'gf_entries' ), array( 'gf_edit_forms', 'gf_new_form' ), array( 'gf_entries', 'gf_new_form' ) ),
		'wpforms'      => array( array( 'wpforms-overview', 'wpforms-overview' ), array( 'wpforms-dashboard', 'wpforms-overview' ), array( 'wpforms-entries', 'wpforms-overview' ), array( 'wpforms-settings', 'wpforms-overview' ) ),
		'formidable'   => array( array( 'formidable', 'formidable' ), array( 'formidable-entries', 'formidable' ) ),
		'fluentforms'  => array( array( 'fluent_forms', 'fluent_forms' ), array( 'fluent_forms_all_entries', 'fluent_forms' ) ),
		'wsform'       => array( array( 'ws-form', 'ws-form' ), array( 'ws-form-submit', 'ws-form' ) ),
		'elementor'    => array( array( 'e-form-submissions', 'elementor' ) ),
		'divi'         => array( array( 'et_divi_options', 'et_divi_options' ) ),
		'wc_checkout'  => array( array( 'wc-orders', 'woocommerce' ), array( 'wc-orders', 'admin.php' ) ),
	);

	/**
	 * Current per-site integration switches.
	 *
	 * @since 0.5.0
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Shared review metadata, without visitor or submission data.
	 *
	 * @since 0.5.0
	 * @var SpamHandling
	 */
	private SpamHandling $handling;

	/**
	 * Exact native screen bound by the current-screen action.
	 *
	 * @since 0.5.0
	 * @var string
	 */
	private string $screen_id = '';

	/**
	 * Fixed active provider IDs applicable to this screen, at most two on Users.
	 *
	 * @since 0.5.0
	 * @var string[]
	 */
	private array $providers = array();

	/**
	 * Prevents duplicate lifecycle registration on the same service instance.
	 *
	 * @since 0.5.0
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Receives read-only site policy without inspecting providers or entries yet.
	 *
	 * @since 0.5.0
	 * @param Settings     $settings Per-site integration settings.
	 * @param SpamHandling $handling Shared review destination descriptions.
	 * @return void
	 */
	public function __construct( Settings $settings, SpamHandling $handling ) {
		$this->settings = $settings;
		$this->handling = $handling;
	}

	/**
	 * Registers native administration hooks; no public rendering hook is attached.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;
		add_action( 'current_screen', array( $this, 'prepare' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 10, 0 );
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'wpforms_admin_header_after', array( $this, 'render' ) );
	}

	/**
	 * Binds relevant authorized site screens to enabled, detected providers.
	 *
	 * Native menus are already registered when current_screen runs. Resolving their
	 * hooks avoids assumptions about translated parent-menu prefixes. Unknown,
	 * network, user-account and AJAX contexts never receive a protection claim.
	 *
	 * @since 0.5.0
	 * @param \WP_Screen $screen Native screen object supplied by WordPress.
	 * @return void
	 */
	public function prepare( \WP_Screen $screen ): void {
		$this->screen_id = '';
		$this->providers = array();
		if ( ! $screen->in_admin( 'site' ) || ! is_user_logged_in() || wp_doing_ajax() ) {
			return;
		}
		$contexts = $this->screen_providers( $screen );
		if ( empty( $contexts ) ) {
			return;
		}
		$options  = $this->settings->all();
		$detected = Providers::detected();
		foreach ( $contexts as $provider ) {
			if ( ! empty( $options[ $provider ] ) && ! empty( $detected[ $provider ] ) && $this->screen_access( $screen, $provider ) ) {
				$this->providers[] = $provider;
			}
		}
		if ( ! empty( $this->providers ) ) {
			$this->screen_id = $screen->id;
		}
	}

	/**
	 * Loads only the small scoped notice typography on a bound native screen.
	 *
	 * The full settings stylesheet and its administration script are not loaded.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function assets(): void {
		if ( $this->eligible() ) {
			wp_enqueue_style( 'rktsky-antispam-context-notices', plugins_url( 'assets/context-notices.css', RKTSKY_ANTISPAM_FILE ), array(), RKTSKY_ANTISPAM_VERSION );
		}
	}

	/**
	 * Outputs one escaped native information notice for the current trusted context.
	 *
	 * Configuration and availability are checked again before output. No entries,
	 * counts, form definitions, credentials or retention payloads are queried.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function render(): void {
		if ( ! $this->eligible() ) {
			return;
		}
		// WPForms removes unrelated WordPress notice callbacks. Its own documented
		// header action provides a visible location without changing that policy.
		if ( in_array( 'wpforms', $this->providers, true ) && 'admin_notices' === current_filter() ) {
			return;
		}
		$options  = $this->settings->all();
		$detected = Providers::detected();
		$labels   = Providers::labels();
		$notices  = array();
		foreach ( $this->providers as $provider ) {
			if ( ! empty( $options[ $provider ] ) && ! empty( $detected[ $provider ] ) ) {
				$route = $this->handling->describe( $provider );
				if ( in_array( $route['mode'], array( 'native', 'conditional' ), true ) && 'discard' === $this->handling->local_retention()['mode'] ) {
					$route['description'] .= ' ' . __( 'WP Antispam keeps no content copy of requests stopped before native storage.', 'wp-antispam' );
				}
				$notices[] = array(
					'name'     => $labels[ $provider ],
					'handling' => $this->accessible_links( $provider, $route ),
				);
			}
		}
		if ( empty( $notices ) ) {
			return;
		}
		ob_start();
		require dirname( RKTSKY_ANTISPAM_FILE ) . '/templates/context-notices.php';
		$message = (string) ob_get_clean();
		$classes = array( 'rktsky-antispam-context-notice' );
		// Gravity Forms keeps only gf-notice callbacks and places them in its own
		// native notice wrapper. This marks our output without changing the vendor.
		if ( in_array( 'gravityforms', $this->providers, true ) ) {
			$classes[] = 'gf-notice';
		}
		wp_admin_notice(
			$message,
			array(
				'type'               => 'info',
				'paragraph_wrap'     => false,
				'additional_classes' => $classes,
			)
		);
	}

	/**
	 * Rechecks the exact site screen and native capability before assets or output.
	 *
	 * @since 0.5.0
	 * @return bool Whether the prepared notice still belongs to this request context.
	 */
	private function eligible(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( empty( $this->providers ) || ! $screen instanceof \WP_Screen || $this->screen_id !== $screen->id || ! $screen->in_admin( 'site' ) || ! is_user_logged_in() || wp_doing_ajax() ) {
			return false;
		}
		foreach ( $this->providers as $provider ) {
			if ( ! $this->screen_access( $screen, $provider ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Matches native screens and a bounded inventory of registered provider pages.
	 *
	 * No post/form ID or page slug from the request becomes an authority. The sole
	 * query check narrows WooCommerce's shared settings page to its Accounts tab.
	 * Fullscreen editors and frontend visual builders deliberately receive no overlay.
	 *
	 * @since 0.5.0
	 * @param \WP_Screen $screen Already initialized site-administration screen.
	 * @return string[] Applicable fixed provider scopes, regardless of their settings.
	 */
	private function screen_providers( \WP_Screen $screen ): array {
		if ( 'edit-comments' === $screen->id ) {
			return array( 'comments' );
		}
		if ( 'users' === $screen->id ) {
			return array( 'registration', 'wc_registration' );
		}
		if ( 'options-general' === $screen->id ) {
			return array( 'registration' );
		}
		if ( 'edit-shop_order' === $screen->id && 'shop_order' === $screen->post_type && 'edit' === $screen->base ) {
			return array( 'wc_checkout' );
		}
		foreach ( self::MENUS as $provider => $pages ) {
			foreach ( $pages as $page ) {
				if ( $this->matches( $screen, $page[0], $page[1] ) ) {
					return array( $provider );
				}
			}
		}
		$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) && strlen( $_GET['tab'] ) <= 32 ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw byte length is bounded before unslashing; the sanitized value only narrows a fixed read-only subtab.
		if ( 'account' === $tab && $this->matches( $screen, 'wc-settings', 'woocommerce' ) ) {
			return array( 'wc_registration' );
		}
		return array();
	}

	/**
	 * Requires the owning screen's native capability rather than a site-admin role.
	 *
	 * Provider menu capabilities can be customized by their official role APIs.
	 * Reading the registered menu preserves those choices without loading entries.
	 *
	 * @since 0.5.0
	 * @param \WP_Screen $screen Current site-administration screen.
	 * @param string     $provider Fixed integration scope already matched to the screen.
	 * @return bool Whether the current user can access the matching native screen.
	 */
	private function screen_access( \WP_Screen $screen, string $provider ): bool {
		if ( 'comments' === $provider ) {
			return current_user_can( 'moderate_comments' );
		}
		if ( 'users' === $screen->id ) {
			return current_user_can( 'list_users' );
		}
		if ( 'options-general' === $screen->id ) {
			return current_user_can( 'manage_options' );
		}
		if ( 'edit-shop_order' === $screen->id ) {
			$type = get_post_type_object( 'shop_order' );
			return null !== $type && current_user_can( $type->cap->edit_posts );
		}
		if ( 'wc_registration' === $provider && $this->matches( $screen, 'wc-settings', 'woocommerce' ) ) {
			return $this->menu_access( 'wc-settings', 'woocommerce' );
		}
		foreach ( self::MENUS[ $provider ] ?? array() as $page ) {
			if ( $this->matches( $screen, $page[0], $page[1] ) && $this->menu_access( $page[0], $page[1] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Removes destinations the current native manager cannot open.
	 *
	 * Local quarantine, settings and activity require manage_options. Native spam
	 * links instead follow the capability registered for that provider's inbox.
	 * Descriptive text remains visible when there is no accessible destination.
	 *
	 * @since 0.5.0
	 * @param string $provider Fixed active provider ID.
	 * @param array  $route Shared translated destination metadata.
	 * @return array Metadata with inaccessible links omitted, without new claims.
	 */
	private function accessible_links( string $provider, array $route ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			$route['secondary_url'] = '';
			if ( ! in_array( $route['mode'], array( 'native', 'conditional' ), true ) ) {
				$route['url'] = '';
			}
		}
		if ( in_array( $route['mode'], array( 'native', 'conditional' ), true ) ) {
			$targets = array(
				'cf7'          => array( array( 'flamingo_inbound', 'flamingo' ) ),
				'gravityforms' => array( array( 'gf_entries', 'gf_edit_forms' ), array( 'gf_entries', 'gf_entries' ), array( 'gf_entries', 'gf_new_form' ) ),
				'formidable'   => array( array( 'formidable-entries', 'formidable' ) ),
				'fluentforms'  => array( array( 'fluent_forms_all_entries', 'fluent_forms' ) ),
				'wsform'       => array( array( 'ws-form-submit', 'ws-form' ) ),
				'wpforms'      => array( array( 'wpforms-entries', 'wpforms-overview' ) ),
			);
			$allowed = 'comments' === $provider && current_user_can( 'moderate_comments' );
			foreach ( $targets[ $provider ] ?? array() as $target ) {
				$allowed = $allowed || $this->menu_access( $target[0], $target[1] );
			}
			if ( ! $allowed ) {
				$route['url'] = '';
			}
		}
		return $route;
	}

	/**
	 * Checks the capability on a fixed currently registered native menu entry.
	 *
	 * This reads WordPress's administration menu registry only, not stored content.
	 * An absent entry fails closed, including provider pages hidden from this user.
	 *
	 * @since 0.5.0
	 * @global array $menu Native top-level menu registry.
	 * @global array $submenu Native submenu registry.
	 * @param string $page Fixed original menu slug.
	 * @param string $parent_slug Fixed original parent-menu slug.
	 * @return bool Whether a matching registered menu capability is granted.
	 */
	private function menu_access( string $page, string $parent_slug ): bool {
		global $menu, $submenu;
		foreach ( $submenu[ $parent_slug ] ?? array() as $entry ) {
			if ( isset( $entry[1], $entry[2] ) && $page === $entry[2] ) {
				return is_string( $entry[1] ) && current_user_can( $entry[1] );
			}
		}
		if ( $page === $parent_slug ) {
			foreach ( $menu ?? array() as $entry ) {
				if ( isset( $entry[1], $entry[2] ) && $page === $entry[2] ) {
					return is_string( $entry[1] ) && current_user_can( $entry[1] );
				}
			}
		}
		return false;
	}

	/**
	 * Compares a fixed page against its actual currently registered WordPress hook.
	 *
	 * @since 0.5.0
	 * @param \WP_Screen $screen Current native screen.
	 * @param string     $page Fixed original provider menu slug.
	 * @param string     $parent_slug Fixed original parent-menu slug.
	 * @return bool Whether that registered native page owns the screen.
	 */
	private function matches( \WP_Screen $screen, string $page, string $parent_slug ): bool {
		$hook = get_plugin_page_hook( $page, $parent_slug );
		return is_string( $hook ) && $hook === $screen->id;
	}
}
