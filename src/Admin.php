<?php
/**
 * Registers plugin administration, settings, and authorized review actions.
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
	 * Optional-content quarantine repository; access is restricted by this controller.
	 *
	 * @since 0.2.0
	 * @var Quarantine|null
	 */
	private ?Quarantine $quarantine;

	/**
	 * Reviewed-outcome metrics; absent only during a staged bootstrap.
	 *
	 * @since 0.2.0
	 * @var Feedback|null
	 */
	private ?Feedback $feedback;

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
	 * @since 0.2.0 Accepts the encrypted quarantine and reviewed-outcome services.
	 * @param Settings $settings Site settings service.
	 * @param Store    $store Aggregate statistics repository.
	 * @param Quarantine|null $quarantine Local rejected-submission repository; null keeps the overview available during bootstrap.
	 * @param Feedback|null   $feedback Reviewed-outcome service, without submission content.
	 * @return void
	 */
	public function __construct( Settings $settings, Store $store, ?Quarantine $quarantine = null, ?Feedback $feedback = null ) {
		$this->settings = $settings;
		$this->store    = $store;
		$this->quarantine = $quarantine;
		$this->feedback = $feedback;
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
		add_action( 'admin_post_rktsky_antispam_quarantine_delete', array( $this, 'delete_quarantine' ) );
		add_action( 'admin_post_rktsky_antispam_review', array( $this, 'review' ) );
		add_action( 'admin_post_rktsky_antispam_classify_delivered', array( $this, 'classify_delivered' ) );
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
		wp_enqueue_script( 'rktsky-antispam-admin', plugins_url( 'assets/admin.js', RKTSKY_ANTISPAM_FILE ), array(), RKTSKY_ANTISPAM_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
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
		$requested_tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, fixed allowlisted view selection.
		$tab = in_array( $requested_tab, array( 'rules', 'quarantine' ), true ) ? $requested_tab : 'overview';
		$base_url     = admin_url( 'options-general.php?page=wp-antispam' );
		$tabs         = array( 'overview' => __( 'Overview', 'wp-antispam' ), 'rules' => __( 'Rules', 'wp-antispam' ), 'quarantine' => __( 'Quarantine', 'wp-antispam' ) );
		$reason_labels = $this->reason_labels();
		$privacy_mode = ! array_key_exists( 'privacy_mode', $options ) || ! empty( $options['privacy_mode'] );
		if ( 'rules' === $tab ) {
			$rule_lists = array();
			foreach ( array( 'spam_keywords', 'spam_patterns', 'countries', 'trusted_proxies' ) as $key ) {
				$value = $options[ $key ] ?? '';
				$rule_lists[ $key ] = is_array( $value ) ? implode( 'countries' === $key ? ', ' : "\n", $value ) : (string) $value;
			}
			$languages = array( 'de' => __( 'German', 'wp-antispam' ), 'en' => __( 'English', 'wp-antispam' ), 'fr' => __( 'French', 'wp-antispam' ), 'it' => __( 'Italian', 'wp-antispam' ), 'es' => __( 'Spanish', 'wp-antispam' ), 'nl' => __( 'Dutch', 'wp-antispam' ), 'pt' => __( 'Portuguese', 'wp-antispam' ) );
			$notification_address = get_option( 'admin_email', '' );
			$country_available = class_exists( Country::class ) && ( new Country( $this->settings ) )->available();
			require dirname( RKTSKY_ANTISPAM_FILE ) . '/templates/rules.php';
			return;
		}
		if ( 'quarantine' === $tab ) {
			if ( null === $this->quarantine ) {
				wp_die( esc_html__( 'Quarantine storage is unavailable.', 'wp-antispam' ), '', array( 'response' => 503 ) );
			}
			$entry_id = $this->query_id( 'entry' );
			$entry = $entry_id > 0 ? $this->quarantine->read( $entry_id ) : null;
			$review_status = $entry && $this->feedback ? $this->feedback->status( 'quarantine', (string) $entry_id ) : null;
			$listing = $entry_id > 0 ? array() : $this->quarantine->listing( max( 1, $this->query_id( 'paged' ) ) );
			$provider_labels = array( 'comments' => __( 'WordPress comments', 'wp-antispam' ), 'cf7' => 'Contact Form 7', 'gravityforms' => 'Gravity Forms', 'wpforms' => 'WPForms' );
			$quarantine_url = add_query_arg( 'tab', 'quarantine', $base_url );
			$delete_url = admin_url( 'admin-post.php' );
			require dirname( RKTSKY_ANTISPAM_FILE ) . '/templates/quarantine.php';
			return;
		}
		$summary      = $this->store->summary( 7 );
		$quality = $this->feedback ? $this->feedback->metrics() : array( 'tp' => 0, 'fp' => 0, 'fn' => 0, 'tn' => 0, 'reviewed' => 0, 'pending' => 0, 'precision' => null, 'recall' => null, 'accuracy' => null, 'period_days' => 90 );
		$detected     = Integrations::detected();
		$integrations = array(
			'comments'     => array(
				'name'   => __( 'WordPress comments', 'wp-antispam' ),
				'detail' => __( 'Protects the native comment form and rejects trackbacks and pingbacks.', 'wp-antispam' ),
				'mark'   => 'W',
			),
			'cf7'          => array(
				'name'   => 'Contact Form 7',
				'detail' => __( 'Adds protection through the native form and spam hooks.', 'wp-antispam' ),
				'mark'   => 'C7',
			),
			'gravityforms' => array(
				'name'   => 'Gravity Forms',
				'detail' => __( 'Validates submissions before entries, drafts and notifications are created.', 'wp-antispam' ),
				'mark'   => 'G',
			),
			'wpforms'      => array(
				'name'   => 'WPForms',
				'detail' => __( 'Checks submissions before the form is processed.', 'wp-antispam' ),
				'mark'   => 'WP',
			),
		);
		$active_count = 0;
		foreach ( $integrations as $provider => $integration ) {
			if ( ! empty( $detected[ $provider ] ) && ! empty( $options[ $provider ] ) ) {
				++$active_count;
			}
		}
		$privacy_url   = admin_url( 'options-privacy.php' );
		$clear_url     = admin_url( 'admin-post.php' );
		require dirname( RKTSKY_ANTISPAM_FILE ) . '/templates/settings.php';
	}

	/**
	 * Provides the same fixed reason labels on aggregate and quarantine screens.
	 *
	 * @since 0.2.0
	 * @return array<string,string> Fixed translated reason labels.
	 */
	private function reason_labels(): array {
		return array(
			'honeypot'  => __( 'Honeypot filled', 'wp-antispam' ),
			'missing'   => __( 'Missing protection token', 'wp-antispam' ),
			'invalid'   => __( 'Invalid protection token', 'wp-antispam' ),
			'expired'   => __( 'Expired protection token', 'wp-antispam' ),
			'replay'    => __( 'Token already used', 'wp-antispam' ),
			'too_fast'  => __( 'Submitted too quickly', 'wp-antispam' ),
			'pingback'  => __( 'Trackback or pingback', 'wp-antispam' ),
			'storage'   => __( 'Protection storage unavailable', 'wp-antispam' ),
			'malformed' => __( 'Malformed protection fields', 'wp-antispam' ),
			'context'   => __( 'Form does not match token', 'wp-antispam' ),
			'rest'      => __( 'Unprotected comment request', 'wp-antispam' ),
			'keyword'   => __( 'Blocked keyword', 'wp-antispam' ),
			'bbcode'    => __( 'BBCode link markup', 'wp-antispam' ),
			'pattern'   => __( 'Matched content pattern', 'wp-antispam' ),
			'pattern_limit' => __( 'Content rule processing limit', 'wp-antispam' ),
			'native_spam' => __( 'Native provider spam check', 'wp-antispam' ),
			'language'  => __( 'Comment language rule', 'wp-antispam' ),
			'country'   => __( 'Comment country rule', 'wp-antispam' ),
		);
	}

	/**
	 * Reads a bounded decimal identifier for authorized read-only view navigation.
	 *
	 * @since 0.2.0
	 * @param string $name Allowlisted query key supplied by this controller.
	 * @return int Nonnegative bounded identifier, or zero for malformed input.
	 */
	private function query_id( string $name ): int {
		$value = isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authorized read-only pagination and record lookup.
		return preg_match( '/^[0-9]{1,9}$/D', $value ) ? (int) $value : 0;
	}

	/**
	 * Deletes one quarantined entry after a POST, capability and record-bound nonce check.
	 *
	 * Deletion never resubmits content or changes native provider records. Missing
	 * records are reported without rendering any submitted field values.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function delete_quarantine(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'wp-antispam' ), '', array( 'response' => 403 ) );
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( esc_html__( 'This action requires a form submission.', 'wp-antispam' ), '', array( 'response' => 405 ) );
		}
		$entry_id = isset( $_POST['entry'] ) && is_string( $_POST['entry'] ) ? sanitize_text_field( wp_unslash( $_POST['entry'] ) ) : '';
		if ( ! preg_match( '/^[1-9][0-9]{0,8}$/D', $entry_id ) ) {
			wp_die( esc_html__( 'The quarantine record is unavailable.', 'wp-antispam' ), '', array( 'response' => 400 ) );
		}
		check_admin_referer( 'rktsky_antispam_quarantine_delete_' . $entry_id );
		if ( null === $this->quarantine ) {
			wp_die( esc_html__( 'Quarantine storage is unavailable.', 'wp-antispam' ), '', array( 'response' => 503 ) );
		}
		$deleted = $this->quarantine->delete( (int) $entry_id );
		wp_safe_redirect( add_query_arg( array( 'page' => 'wp-antispam', 'tab' => 'quarantine', 'record-deleted' => $deleted ? '1' : '0' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Records one explicit assessment of an unexpired, already tracked quarantine item.
	 *
	 * This POST changes reviewed-outcome counts only. It never publishes a comment,
	 * resends a form, or creates tracking evidence for an untracked old record.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function review(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'wp-antispam' ), '', array( 'response' => 403 ) );
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( esc_html__( 'This action requires a form submission.', 'wp-antispam' ), '', array( 'response' => 405 ) );
		}
		$entry_id = isset( $_POST['entry'] ) && is_string( $_POST['entry'] ) ? sanitize_text_field( wp_unslash( $_POST['entry'] ) ) : '';
		$assessment = isset( $_POST['assessment'] ) && is_string( $_POST['assessment'] ) ? sanitize_key( wp_unslash( $_POST['assessment'] ) ) : '';
		if ( ! preg_match( '/^[1-9][0-9]{0,8}$/D', $entry_id ) || ! in_array( $assessment, array( 'spam', 'normal' ), true ) ) {
			wp_die( esc_html__( 'The quarantine record is unavailable.', 'wp-antispam' ), '', array( 'response' => 400 ) );
		}
		check_admin_referer( 'rktsky_antispam_review_' . $entry_id );
		$entry = $this->quarantine ? $this->quarantine->read( (int) $entry_id ) : null;
		$reviewed = null !== $entry && $this->feedback && $this->feedback->review( 'quarantine', $entry_id, 'spam' === $assessment );
		wp_safe_redirect( add_query_arg( array( 'page' => 'wp-antispam', 'tab' => 'quarantine', 'entry' => $entry_id, 'review-updated' => $reviewed ? '1' : '0' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Classifies one delivered submission or forgets its opaque stable reference.
	 *
	 * Only the feedback service receives this bounded reference, stores its keyed
	 * digest and enforces duplicate handling. Forgetting detaches reviewed metadata
	 * without deleting provider entries. No reference or message is echoed.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function classify_delivered(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'wp-antispam' ), '', array( 'response' => 403 ) );
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( esc_html__( 'This action requires a form submission.', 'wp-antispam' ), '', array( 'response' => 405 ) );
		}
		check_admin_referer( 'rktsky_antispam_classify_delivered' );
		$provider = isset( $_POST['provider'] ) && is_string( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$reference = isset( $_POST['reference'] ) && is_string( $_POST['reference'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['reference'] ) ) ) : '';
		$assessment = isset( $_POST['assessment'] ) && is_string( $_POST['assessment'] ) ? sanitize_key( wp_unslash( $_POST['assessment'] ) ) : '';
		$valid = in_array( $provider, array( 'comments', 'cf7', 'gravityforms', 'wpforms' ), true ) && '' !== $reference && strlen( $reference ) <= 128 && in_array( $assessment, array( 'spam', 'normal', 'forget' ), true );
		$recorded = false;
		if ( $valid && $this->feedback ) {
			$recorded = 'forget' === $assessment ? $this->feedback->forget_manual( $provider, $reference ) : $this->feedback->report_delivered( $provider, $reference, 'spam' === $assessment );
		}
		$notice = $recorded && 'forget' === $assessment ? 'reference-forgotten' : 'classification-saved';
		wp_safe_redirect( add_query_arg( array( 'page' => 'wp-antispam', $notice => $recorded ? '1' : '0' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Clears anonymous activity counters after a capability and POST nonce check.
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
