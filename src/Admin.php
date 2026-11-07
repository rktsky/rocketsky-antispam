<?php
/**
 * Registers the plugin settings screen and its authenticated statistics action.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares the administrator view without changing other WordPress screens.
 *
 * @since 0.1.0
 */
final class Admin {

	/**
	 * Validated site settings.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Aggregate statistics repository.
	 *
	 * @since 0.1.0
	 * @var Store
	 */
	private Store $store;

	/**
	 * Screen hook returned by WordPress.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private string $screen_hook = '';

	/**
	 * Receives the services needed by the settings screen.
	 *
	 * @since 0.1.0
	 * @param Settings $settings Site settings service.
	 * @param Store    $store Aggregate statistics repository.
	 * @return void
	 */
	public function __construct( Settings $settings, Store $store ) {
		$this->settings = $settings;
		$this->store    = $store;
	}

	/**
	 * Attaches administrator hooks without enqueuing public-facing assets.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_rktsky_antispam_clear', array( $this, 'clear' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RKTSKY_ANTISPAM_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds one settings submenu so the plugin does not take over the dashboard.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function menu(): void {
		$this->screen_hook = (string) add_options_page(
			__( 'WP Antispam', 'wp-antispam' ),
			__( 'WP Antispam', 'wp-antispam' ),
			'manage_options',
			'wp-antispam',
			array( $this, 'render' )
		);
	}

	/**
	 * Loads the local stylesheet only on the plugin settings screen.
	 *
	 * @since 0.1.0
	 * @param string $hook Current WordPress administration screen hook.
	 * @return void
	 */
	public function assets( string $hook ): void {
		if ( '' === $this->screen_hook || $hook !== $this->screen_hook ) {
			return;
		}
		wp_enqueue_style( 'rktsky-antispam-admin', plugins_url( 'assets/admin.css', RKTSKY_ANTISPAM_FILE ), array(), RKTSKY_ANTISPAM_VERSION );
	}

	/**
	 * Adds a direct settings link within the plugin's existing action row.
	 *
	 * @since 0.1.0
	 * @param string[] $links Existing plugin action links.
	 * @return string[] Existing links with the local settings link prepended.
	 */
	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=wp-antispam' ) ) . '">' . esc_html__( 'Settings', 'wp-antispam' ) . '</a>' );
		return $links;
	}

	/**
	 * Builds the bounded view model after checking administrator access.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$options      = $this->settings->all();
		$summary      = $this->store->summary( 7 );
		$detected     = Integrations::detected();
		$integrations = array(
			'comments'     => array( 'name' => __( 'WordPress comments', 'wp-antispam' ), 'detail' => __( 'Protects the native comment form and rejects trackbacks and pingbacks.', 'wp-antispam' ), 'mark' => 'W' ),
			'cf7'          => array( 'name' => 'Contact Form 7', 'detail' => __( 'Adds protection through the native form and spam hooks.', 'wp-antispam' ), 'mark' => 'C7' ),
			'gravityforms' => array( 'name' => 'Gravity Forms', 'detail' => __( 'Validates submissions before entries, drafts and notifications are created.', 'wp-antispam' ), 'mark' => 'G' ),
			'wpforms'      => array( 'name' => 'WPForms', 'detail' => __( 'Checks submissions before the form is processed.', 'wp-antispam' ), 'mark' => 'WP' ),
		);
		$active_count = 0;
		foreach ( $integrations as $provider => $integration ) {
			if ( ! empty( $detected[ $provider ] ) && ! empty( $options[ $provider ] ) ) {
				++$active_count;
			}
		}
		$reason_labels = array(
			'honeypot'       => __( 'Honeypot filled', 'wp-antispam' ),
			'missing'        => __( 'Missing protection token', 'wp-antispam' ),
			'invalid'        => __( 'Invalid protection token', 'wp-antispam' ),
			'expired'        => __( 'Expired protection token', 'wp-antispam' ),
			'replay'         => __( 'Token already used', 'wp-antispam' ),
			'too_fast'       => __( 'Submitted too quickly', 'wp-antispam' ),
			'pingback'       => __( 'Trackback or pingback', 'wp-antispam' ),
			'storage'        => __( 'Protection storage unavailable', 'wp-antispam' ),
			'malformed'      => __( 'Malformed protection fields', 'wp-antispam' ),
			'context'        => __( 'Form does not match token', 'wp-antispam' ),
			'rest'           => __( 'Unprotected comment request', 'wp-antispam' ),
		);
		$privacy_url   = admin_url( 'options-privacy.php' );
		$clear_url     = admin_url( 'admin-post.php' );
		require dirname( RKTSKY_ANTISPAM_FILE ) . '/templates/settings.php';
	}

	/**
	 * Clears anonymous statistics only after a capability and POST nonce check.
	 *
	 * Replay protection remains the repository's responsibility and must survive
	 * this action. A redirect prevents refresh from repeating the POST.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function clear(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'wp-antispam' ), '', array( 'response' => 403 ) );
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( esc_html__( 'This action requires a form submission.', 'wp-antispam' ), '', array( 'response' => 405 ) );
		}
		check_admin_referer( 'rktsky_antispam_clear' );
		$this->store->clear();
		wp_safe_redirect( admin_url( 'options-general.php?page=wp-antispam&statistics-cleared=1' ) );
		exit;
	}
}
