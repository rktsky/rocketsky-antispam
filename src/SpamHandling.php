<?php
/**
 * Describes spam review destinations without reading submissions or provider data.
 *
 * This presentation service does not classify, store, copy or redirect a request.
 * Provider-owned retention remains independent of Rocketsky Antispam quarantine policy.
 *
 * @package RktskyAntispam
 * @since 0.5.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Gives integration rows, review navigation and contextual notices one vocabulary.
 *
 * @since 0.5.0
 */
final class SpamHandling {

	/**
	 * Per-site integration settings, read through the native option service.
	 *
	 * @since 0.5.0
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Optional retention capability; null never implies that storage is available.
	 *
	 * @since 0.5.0
	 * @var Quarantine|null
	 */
	private ?Quarantine $quarantine;

	/**
	 * Receives policy services without reading entries or registering hooks.
	 *
	 * @since 0.5.0
	 * @param Settings        $settings Current per-site configuration.
	 * @param Quarantine|null $quarantine Optional encrypted-retention capability.
	 * @return void
	 */
	public function __construct( Settings $settings, ?Quarantine $quarantine = null ) {
		$this->settings   = $settings;
		$this->quarantine = $quarantine;
	}

	/**
	 * Returns truthful review metadata for a fixed provider scope.
	 *
	 * Callers claiming active protection must also require enabled and detected.
	 * A native destination does not guarantee storage of every rejected request;
	 * invalid technical evidence can stop processing before a native entry exists.
	 * Descriptions are plain translated text and require contextual output escaping.
	 *
	 * @since 0.5.0
	 * @param string $provider Stable owned provider ID; unknown IDs have no review claim.
	 * @return array{mode:string,label:string,description:string,url:string,secondary_label?:string,secondary_url?:string} Fixed local review destinations, never submission data.
	 */
	public function describe( string $provider ): array {
		switch ( $provider ) {
			case 'comments':
				return $this->native_route( __( 'WordPress comment spam', 'rocketsky-antispam' ), admin_url( 'edit-comments.php?comment_status=spam' ) );
			case 'gravityforms':
				return $this->native_route( __( 'Gravity Forms spam', 'rocketsky-antispam' ), admin_url( 'admin.php?page=gf_entries&filter=spam' ) );
			case 'formidable':
				if ( is_callable( array( Integrations\Formidable::class, 'native_available' ) ) && Integrations\Formidable::native_available() && is_callable( array( '\FrmSpamEntriesController', 'get_tab_url' ) ) ) {
					return $this->native_route( __( 'Formidable spam', 'rocketsky-antispam' ), \FrmSpamEntriesController::get_tab_url( true ) );
				}
				return $this->quarantine_route();
			case 'fluentforms':
				if ( is_callable( array( Integrations\Fluent::class, 'native_available' ) ) && Integrations\Fluent::native_available() ) {
					return $this->native_route( __( 'Fluent Forms entries', 'rocketsky-antispam' ), admin_url( 'admin.php?page=fluent_forms_all_entries' ) );
				}
				return $this->quarantine_route();
			case 'cf7':
				if ( is_callable( array( Integrations\ContactForm7::class, 'native_available' ) ) && Integrations\ContactForm7::native_available() ) {
					$route                = $this->native_route( __( 'Flamingo spam', 'rocketsky-antispam' ), admin_url( 'admin.php?page=flamingo_inbound&post_status=spam' ) );
					$route['mode']        = 'conditional';
					$route['description'] = __( 'Flamingo stores spam when the form allows it; otherwise Rocketsky Antispam retention applies. A form\'s storage prohibition is respected.', 'rocketsky-antispam' );
					return $route;
				}
				return $this->quarantine_route();
			case 'wsform':
				$route                = $this->native_route( __( 'WS Form submissions', 'rocketsky-antispam' ), admin_url( 'admin.php?page=ws-form-submit&ws-form-status=spam' ) );
				$route['mode']        = 'conditional';
				$route['description'] = __( 'Native spam storage depends on each form\'s settings. Other rejections follow Rocketsky Antispam retention settings.', 'rocketsky-antispam' );
				return $route;
			case 'wpforms':
				if ( is_callable( array( Integrations\WpForms::class, 'native_available' ) ) && Integrations\WpForms::native_available() ) {
					$route                = $this->native_route( __( 'WPForms spam', 'rocketsky-antispam' ), admin_url( 'admin.php?page=wpforms-entries&view=list&status=spam' ) );
					$route['mode']        = 'conditional';
					$route['description'] = __( 'Native spam storage depends on each form\'s settings. Other rejections follow Rocketsky Antispam retention settings.', 'rocketsky-antispam' );
					return $route;
				}
				return $this->quarantine_route();
			case 'elementor':
				return $this->quarantine_route();
			case 'registration':
				return $this->blocked_route( __( 'Rocketsky Antispam does not retain account fields or passwords.', 'rocketsky-antispam' ) );
			case 'wc_registration':
				return $this->blocked_route( __( 'Rocketsky Antispam does not retain account fields or passwords.', 'rocketsky-antispam' ) );
			case 'wc_checkout':
				return $this->blocked_route( __( 'Rocketsky Antispam does not retain order, payment or file contents.', 'rocketsky-antispam' ) );
			case 'divi':
				return $this->blocked_route( __( 'Rocketsky Antispam does not retain message or file contents.', 'rocketsky-antispam' ) );
			default:
				return array(
					'mode'        => 'unavailable',
					'label'       => __( 'Integration settings', 'rocketsky-antispam' ),
					'description' => __( 'No supported review destination is available for this integration.', 'rocketsky-antispam' ),
					'url'         => admin_url( 'options-general.php?page=rocketsky-antispam&tab=integrations' ),
				);
		}
	}

	/**
	 * Lists review destinations only for available, explicitly enabled integrations.
	 *
	 * No form, entry, email, order or spam-count query is performed. Availability
	 * describes the supported API; it does not assert that a site contains forms.
	 *
	 * @since 0.5.0
	 * @return array<string,array> Destination metadata keyed by active provider ID.
	 */
	public function directory(): array {
		$options  = $this->settings->all();
		$detected = Providers::detected();
		$routes   = array();
		foreach ( Providers::ids() as $provider ) {
			if ( ! empty( $options[ $provider ] ) && ! empty( $detected[ $provider ] ) ) {
				$routes[ $provider ] = $this->describe( $provider );
			}
		}
		return $routes;
	}

	/**
	 * Exposes the local fallback policy independently of any provider's native inbox.
	 *
	 * @since 0.5.0
	 * @return array{mode:string,label:string,description:string,url:string} Effective local retention guidance.
	 */
	public function local_retention(): array {
		return $this->quarantine_route();
	}

	/**
	 * Separates provider-owned spam handling from early technical rejection policy.
	 *
	 * The secondary destination is not a duplicate native spam inbox. It identifies
	 * the optional local fallback for requests stopped before native persistence.
	 *
	 * @since 0.5.0
	 * @param string $label Translated native review destination name.
	 * @param string $url Fixed provider administration URL.
	 * @return array Native handling plus a separate local early-rejection destination.
	 */
	private function native_route( string $label, string $url ): array {
		$fallback = $this->quarantine_route();
		return array(
			'mode'            => 'native',
			'label'           => $label,
			'description'     => __( 'Content spam uses this native spam queue and its own retention settings.', 'rocketsky-antispam' ),
			'url'             => $url,
			'secondary_label' => 'quarantine' === $fallback['mode'] ? __( 'Rejected requests in Rocketsky Antispam', 'rocketsky-antispam' ) : __( 'Rocketsky Antispam retention settings', 'rocketsky-antispam' ),
			'secondary_url'   => $fallback['url'],
		);
	}

	/**
	 * Reports actual local retention capability without promising a stored record.
	 *
	 * A configured checkbox alone is insufficient: privacy policy and encryption
	 * readiness are enforced by Quarantine::enabled(). Capture remains bounded.
	 *
	 * @since 0.5.0
	 * @return array{mode:string,label:string,description:string,url:string} Local quarantine or explicit no-content-retention handling.
	 */
	private function quarantine_route(): array {
		if ( null !== $this->quarantine && $this->quarantine->enabled() ) {
			return array(
				'mode'        => 'quarantine',
				'label'       => __( 'Rocketsky Antispam quarantine', 'rocketsky-antispam' ),
				'description' => __( 'Selected rejected message fields can be retained in Rocketsky Antispam quarantine.', 'rocketsky-antispam' ),
				'url'         => admin_url( 'options-general.php?page=rocketsky-antispam&tab=quarantine#rktsky-quarantine-list-heading' ),
			);
		}
		return array(
			'mode'        => 'discard',
			'label'       => __( 'Retention settings', 'rocketsky-antispam' ),
			'description' => __( 'Rocketsky Antispam does not retain rejected message contents.', 'rocketsky-antispam' ),
			'url'         => admin_url( 'options-general.php?page=rocketsky-antispam&tab=settings#retention' ),
		);
	}

	/**
	 * Keeps account, payment and unsupported-content scopes out of quarantine.
	 *
	 * @since 0.5.0
	 * @param string $description Translated scope-specific no-retention explanation.
	 * @return array{mode:string,label:string,description:string,url:string} Aggregate activity destination only.
	 */
	private function blocked_route( string $description ): array {
		return array(
			'mode'        => 'blocked',
			'label'       => __( 'Rocketsky Antispam activity', 'rocketsky-antispam' ),
			'description' => $description,
			'url'         => admin_url( 'options-general.php?page=rocketsky-antispam&tab=overview' ),
		);
	}
}
