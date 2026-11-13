<?php
/**
 * Shows local protection and review guidance on verified provider admin screens.
 *
 * Notice preferences use WordPress's per-user, per-site options. No frontend
 * branding, submission query, browser storage or remote call is added.
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
	 * Native user-option suffix; WordPress supplies the current blog's prefix.
	 *
	 * @since 0.5.5
	 * @var string
	 */
	public const USER_OPTION = 'rktsky_antispam_hidden_notices';

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
		add_action( 'admin_post_rktsky_antispam_notice_hide', array( $this, 'hide' ) );
		add_action( 'admin_post_rktsky_antispam_notice_restore', array( $this, 'restore' ) );
		add_action( 'show_user_profile', array( $this, 'profile' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile' ) );
	}

	/**
	 * Reads only the current user's bounded preferences for the current website.
	 *
	 * The site stamp rejects WordPress's unprefixed global user-option fallback.
	 * Unknown provider keys and malformed values cannot hide additional notices.
	 * No preference is created for guests or while merely viewing a screen.
	 *
	 * @since 0.5.5
	 * @return string[] Known provider IDs explicitly hidden by the current user.
	 */
	public static function hidden(): array {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return array();
		}
		$value = get_user_option( self::USER_OPTION, $user_id );
		if ( ! is_array( $value ) || ( $value['site'] ?? null ) !== get_current_blog_id() || ! isset( $value['providers'] ) || ! is_array( $value['providers'] ) ) {
			return array();
		}
		$hidden = array();
		foreach ( Providers::ids() as $provider ) {
			if ( true === ( $value['providers'][ $provider ] ?? false ) ) {
				$hidden[] = $provider;
			}
		}
		return $hidden;
	}

	/**
	 * Indicates whether the current account has notices to restore on this website.
	 *
	 * @since 0.5.5
	 * @return bool Whether at least one known notice is hidden, even if its provider is currently unavailable.
	 */
	public static function has_hidden(): bool {
		return ! empty( self::hidden() );
	}

	/**
	 * Shares the native nonce action between the integrations and own-profile forms.
	 *
	 * WordPress additionally binds its nonce to the current user and login session.
	 * The blog ID prevents replaying an otherwise valid preference across websites.
	 *
	 * @since 0.5.5
	 * @return string Current-site restore action, without a generated nonce or side effects.
	 */
	public static function restore_nonce_action(): string {
		return 'rktsky_antispam_notice_restore|' . get_current_blog_id();
	}

	/**
	 * Hides the displayed provider notice only for the authenticated current user.
	 *
	 * admin-post.php does not initialize provider menus. The form therefore binds
	 * the exact server-resolved native capability and provider list into a Core
	 * nonce before either value can be trusted. Current capabilities, configuration
	 * and availability are checked again after nonce verification. Revocation still
	 * denies the action; no submitted user ID, menu hook or custom signature is used.
	 *
	 * @since 0.5.5
	 * @return void Redirects after native persistence, or terminates invalid requests.
	 */
	public function hide(): void {
		$this->require_post();
		$provider   = isset( $_POST['provider'] ) && is_string( $_POST['provider'] ) && strlen( $_POST['provider'] ) <= 256 ? wp_unslash( $_POST['provider'] ) : '';
		$capability = isset( $_POST['capability'] ) && is_string( $_POST['capability'] ) && strlen( $_POST['capability'] ) <= 256 ? wp_unslash( $_POST['capability'] ) : '';
		$providers  = explode( ',', $provider );
		$known      = Providers::ids();
		$canonical  = array_values( array_unique( $providers ) );
		sort( $canonical, SORT_STRING );
		if ( '' === $capability || count( $providers ) > count( $known ) || implode( ',', $canonical ) !== $provider || ! empty( array_diff( $providers, $known ) ) ) {
			wp_die( esc_html__( 'Invalid notice preference request.', 'rocketsky-antispam' ), '', array( 'response' => 400 ) );
		}
		check_admin_referer( $this->hide_nonce_action( $provider, $capability ), 'rktsky_antispam_notices_nonce' );
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You cannot change these notice preferences.', 'rocketsky-antispam' ), '', array( 'response' => 403 ) );
		}
		$options  = $this->settings->all();
		$detected = Providers::detected();
		foreach ( $providers as $id ) {
			if ( empty( $options[ $id ] ) || empty( $detected[ $id ] ) ) {
				wp_die( esc_html__( 'You cannot change these notice preferences.', 'rocketsky-antispam' ), '', array( 'response' => 403 ) );
			}
		}
		$this->save_hidden( array_merge( self::hidden(), $providers ) );
		$this->redirect_back();
	}

	/**
	 * Restores this user's notices without modifying protection or another account.
	 *
	 * Own-profile permission keeps this preference available to moderators and form
	 * managers who cannot open the plugin's manage_options settings page.
	 *
	 * @since 0.5.5
	 * @return void Redirects after persistence, or terminates an unauthorized request.
	 */
	public function restore(): void {
		$this->require_post();
		if ( ! current_user_can( 'edit_user', get_current_user_id() ) ) {
			wp_die( esc_html__( 'You cannot change these notice preferences.', 'rocketsky-antispam' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::restore_nonce_action(), 'rktsky_antispam_notices_nonce' );
		$this->save_hidden( array() );
		$this->redirect_back();
	}

	/**
	 * Adds a compact restore checkbox inside the current user's native profile form.
	 *
	 * Other users' profiles and accounts without hidden preferences remain unchanged.
	 * The existing Core form owns submission, so this partial adds no nested form.
	 *
	 * @since 0.5.5
	 * @param \WP_User $user Native profile owner supplied by show_user_profile.
	 * @return void
	 */
	public function profile( \WP_User $user ): void {
		if ( $user->ID !== get_current_user_id() || ! current_user_can( 'edit_user', $user->ID ) || ! self::has_hidden() ) {
			return;
		}
		require dirname( RKTSKY_ANTISPAM_FILE ) . '/templates/context-notices-profile.php';
	}

	/**
	 * Applies an explicit restore checkbox during the current user's profile save.
	 *
	 * Core validates its own profile nonce and edit permission before this hook.
	 * The additional account equality, native permission and preference nonce keep
	 * direct calls constrained; no edit_user_profile_update hook is registered.
	 *
	 * @since 0.5.5
	 * @param int $user_id Native target profile ID, which must match the current user.
	 * @return void Leaves unchecked or other-account profile saves untouched.
	 */
	public function save_profile( int $user_id ): void {
		if ( $user_id !== get_current_user_id() || ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['rktsky_antispam_restore_notices'] ) || '1' !== $_POST['rktsky_antispam_restore_notices'] ) {
			return;
		}
		$this->require_post( 'rktsky_antispam_notices_nonce' );
		check_admin_referer( self::restore_nonce_action(), 'rktsky_antispam_notices_nonce' );
		$this->save_hidden( array() );
	}

	/**
	 * Writes only bounded display preferences through the per-site Core user API.
	 *
	 * Reading back handles update_user_option's false return for unchanged values.
	 * The option remains site-prefixed even after restoring an empty provider set.
	 *
	 * @since 0.5.5
	 * @param string[] $providers Known provider IDs to hide for the current account.
	 * @return void Terminates with an actionable retry message if persistence fails.
	 */
	private function save_hidden( array $providers ): void {
		$hidden = array();
		foreach ( Providers::ids() as $provider ) {
			if ( in_array( $provider, $providers, true ) ) {
				$hidden[ $provider ] = true;
			}
		}
		update_user_option( get_current_user_id(), self::USER_OPTION, array( 'site' => get_current_blog_id(), 'providers' => $hidden ), false );
		if ( self::hidden() !== array_keys( $hidden ) ) {
			wp_die( esc_html__( 'Could not save your notice preferences. Please try again.', 'rocketsky-antispam' ), '', array( 'response' => 500 ) );
		}
	}

	/**
	 * Requires a logged-in POST with a bounded scalar nonce before Core consumes it.
	 *
	 * Core reads the nonce from REQUEST; matching the POST field prevents a GET-only
	 * nonce and rejects malformed values before wp_verify_nonce casts its argument.
	 *
	 * @since 0.5.5
	 * @param string $nonce_key Fixed nonce field used by the handler, never a request-selected name.
	 * @return void Terminates malformed or unauthenticated requests without metadata writes or PHP warnings.
	 */
	private function require_post( string $nonce_key = 'rktsky_antispam_notices_nonce' ): void {
		if ( ! is_user_logged_in() || get_current_user_id() < 1 || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST[ $nonce_key ], $_REQUEST[ $nonce_key ] ) || ! is_string( $_POST[ $nonce_key ] ) || '' === $_POST[ $nonce_key ] || strlen( $_POST[ $nonce_key ] ) > 256 || ! is_string( $_REQUEST[ $nonce_key ] ) || $_POST[ $nonce_key ] !== $_REQUEST[ $nonce_key ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Bounds and type-checks the exact nonce before its caller performs native verification; no input is output or persisted here.
			wp_die( esc_html__( 'Invalid notice preference request.', 'rocketsky-antispam' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Binds the rendered native capability and provider scope to the current site.
	 *
	 * @since 0.5.5
	 * @param string $providers Canonical comma-separated provider IDs from a trusted screen.
	 * @param string $capability Exact capability resolved from the server's native menu registry.
	 * @return string Action for Core's user/session-bound nonce.
	 */
	private function hide_nonce_action( string $providers, string $capability ): string {
		return 'rktsky_antispam_notice_hide|' . get_current_blog_id() . '|' . $providers . '|' . $capability;
	}

	/**
	 * Returns only to the current website's administration, never another origin.
	 *
	 * Native redirect validation is retained, with stricter origin and admin-path
	 * comparisons so an allowed_redirect_hosts filter cannot widen this UI flow.
	 * The fallback remains accessible to users without plugin-settings permission.
	 *
	 * @since 0.5.5
	 * @return void Redirects and ends the successful POST request.
	 */
	private function redirect_back(): void {
		$fallback = current_user_can( 'manage_options' ) ? admin_url( 'options-general.php?page=rocketsky-antispam&tab=integrations' ) : admin_url( 'profile.php' );
		$referer  = wp_get_referer();
		$target   = $referer ? wp_validate_redirect( $referer, $fallback ) : $fallback;
		$base     = wp_parse_url( admin_url() );
		$url      = wp_parse_url( $target );
		$relative = is_array( $url ) && ! isset( $url['host'] ) && ! isset( $url['scheme'] ) && str_starts_with( $target, '/' ) && ! str_starts_with( $target, '//' );
		$same     = is_array( $base ) && is_array( $url ) && strtolower( $url['scheme'] ?? '' ) === strtolower( $base['scheme'] ?? '' ) && strtolower( $url['host'] ?? '' ) === strtolower( $base['host'] ?? '' ) && ( $url['port'] ?? ( 'https' === ( $url['scheme'] ?? '' ) ? 443 : 80 ) ) === ( $base['port'] ?? ( 'https' === ( $base['scheme'] ?? '' ) ? 443 : 80 ) );
		$path     = is_array( $url ) ? ( $url['path'] ?? '' ) : '';
		$prefix   = is_array( $base ) ? ( $base['path'] ?? '/wp-admin/' ) : '/wp-admin/';
		if ( ! is_array( $base ) || ! is_array( $url ) || isset( $url['user'] ) || isset( $url['pass'] ) || ( ! $relative && ! $same ) || ! str_starts_with( $path, $prefix ) || ! preg_match( '/\A[a-z0-9-]+\.php\z/', substr( $path, strlen( $prefix ) ) ) ) {
			$target = $fallback;
		}
		wp_safe_redirect( $target );
		exit;
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
		$hidden   = self::hidden();
		foreach ( $contexts as $provider ) {
			if ( ! empty( $options[ $provider ] ) && ! empty( $detected[ $provider ] ) && ! in_array( $provider, $hidden, true ) && $this->screen_access( $screen, $provider ) ) {
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
		$hidden   = self::hidden();
		$notices  = array();
		foreach ( $this->providers as $provider ) {
			if ( ! empty( $options[ $provider ] ) && ! empty( $detected[ $provider ] ) && ! in_array( $provider, $hidden, true ) ) {
				$route = $this->handling->describe( $provider );
				if ( in_array( $route['mode'], array( 'native', 'conditional' ), true ) && 'discard' === $this->handling->local_retention()['mode'] ) {
					$route['description'] .= ' ' . __( 'Verification failures do not create an entry.', 'rocketsky-antispam' );
				}
				$notices[] = array(
					'provider' => $provider,
					'name'     => $labels[ $provider ],
					'handling' => $this->accessible_links( $provider, $route ),
				);
			}
		}
		if ( empty( $notices ) ) {
			return;
		}
		$dismiss_ids        = array_column( $notices, 'provider' );
		sort( $dismiss_ids, SORT_STRING );
		$dismiss_providers  = implode( ',', $dismiss_ids );
		$dismiss_capability = $this->screen_capability( get_current_screen(), $dismiss_ids[0] );
		$dismiss_action     = $this->hide_nonce_action( $dismiss_providers, $dismiss_capability );
		ob_start();
		require dirname( RKTSKY_ANTISPAM_FILE ) . '/templates/context-notices.php';
		$message = (string) ob_get_clean();
		$classes = array( 'rktsky-antispam-context-notice' );
		// Gravity Forms keeps only gf-notice callbacks and places them in its own
		// native notice wrapper. This marks our output without changing the vendor.
		if ( in_array( 'gravityforms', $this->providers, true ) ) {
			$classes[] = 'gf-notice';
		}
		// wp_admin_notice() applies the post-content allowlist, which removes forms.
		// Preserve only our escaped controls through a local, presentation-only list.
		echo wp_kses(
			wp_get_admin_notice(
				$message,
				array(
					'type'               => 'info',
					'dismissible'        => false,
					'paragraph_wrap'     => false,
					'additional_classes' => $classes,
				)
			),
			array(
				'div'    => array( 'class' => true, 'id' => true, 'role' => true ),
				'p'      => array( 'class' => true, 'id' => true ),
				'strong' => array(),
				'a'      => array( 'href' => true ),
				'span'   => array( 'class' => true, 'aria-hidden' => true ),
				'form'   => array( 'action' => true, 'method' => true, 'class' => true ),
				'input'  => array( 'type' => true, 'name' => true, 'id' => true, 'value' => true ),
				'button' => array( 'type' => true, 'class' => true, 'aria-describedby' => true ),
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
		$visible = array_diff( $this->providers, self::hidden() );
		if ( empty( $visible ) || ! $screen instanceof \WP_Screen || $this->screen_id !== $screen->id || ! $screen->in_admin( 'site' ) || ! is_user_logged_in() || wp_doing_ajax() ) {
			return false;
		}
		foreach ( $visible as $provider ) {
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
		$capability = $this->screen_capability( $screen, $provider );
		return '' !== $capability && current_user_can( $capability );
	}

	/**
	 * Resolves the exact native screen capability before creating a dismissal form.
	 *
	 * Custom provider menu capabilities remain supported. The returned value is
	 * bound into the nonce and checked again after verification in admin-post.php,
	 * where these menu entries are not initialized by WordPress.
	 *
	 * @since 0.5.5
	 * @param \WP_Screen $screen Current initialized site screen.
	 * @param string     $provider Fixed integration already matched to this screen.
	 * @return string Registered native capability, or an empty string when unavailable.
	 */
	private function screen_capability( \WP_Screen $screen, string $provider ): string {
		if ( 'comments' === $provider ) {
			return 'moderate_comments';
		}
		if ( 'users' === $screen->id ) {
			return 'list_users';
		}
		if ( 'options-general' === $screen->id ) {
			return 'manage_options';
		}
		if ( 'edit-shop_order' === $screen->id ) {
			$type = get_post_type_object( 'shop_order' );
			return null !== $type && isset( $type->cap->edit_posts ) && is_string( $type->cap->edit_posts ) ? $type->cap->edit_posts : '';
		}
		if ( 'wc_registration' === $provider && $this->matches( $screen, 'wc-settings', 'woocommerce' ) ) {
			return $this->menu_capability( 'wc-settings', 'woocommerce' );
		}
		foreach ( self::MENUS[ $provider ] ?? array() as $page ) {
			if ( $this->matches( $screen, $page[0], $page[1] ) ) {
				return $this->menu_capability( $page[0], $page[1] );
			}
		}
		return '';
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
		$capability = $this->menu_capability( $page, $parent_slug );
		return '' !== $capability && current_user_can( $capability );
	}

	/**
	 * Reads one bounded capability name from an exact registered native menu entry.
	 *
	 * @since 0.5.5
	 * @global array $menu Native top-level menu registry.
	 * @global array $submenu Native submenu registry.
	 * @param string $page Fixed original provider menu slug.
	 * @param string $parent_slug Fixed original parent-menu slug.
	 * @return string Native capability of at most 256 bytes, or an empty miss.
	 */
	private function menu_capability( string $page, string $parent_slug ): string {
		global $menu, $submenu;
		foreach ( $submenu[ $parent_slug ] ?? array() as $entry ) {
			if ( isset( $entry[1], $entry[2] ) && $page === $entry[2] ) {
				return is_string( $entry[1] ) && strlen( $entry[1] ) <= 256 ? $entry[1] : '';
			}
		}
		if ( $page === $parent_slug ) {
			foreach ( $menu ?? array() as $entry ) {
				if ( isset( $entry[1], $entry[2] ) && $page === $entry[2] ) {
					return is_string( $entry[1] ) && strlen( $entry[1] ) <= 256 ? $entry[1] : '';
				}
			}
		}
		return '';
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
