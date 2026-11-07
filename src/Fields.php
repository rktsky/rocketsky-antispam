<?php
/**
 * Adds cache-safe protection controls and a public same-site challenge endpoint.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Connects rendered forms to the local challenge protocol without inline scripts.
 *
 * @since 0.1.0
 */
final class Fields {
	/**
	 * Provider enablement.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private Settings $settings;
	/**
	 * Form intent and challenge protocol.
	 *
	 * @since 0.1.0
	 * @var Token
	 */
	private Token $token;

	/**
	 * Receives per-site configuration and stateless challenge authentication.
	 *
	 * @since 0.1.0
	 * @param Settings $settings Provider settings.
	 * @param Token    $token Signed protocol.
	 * @return void
	 */
	public function __construct( Settings $settings, Token $token ) {
		$this->settings = $settings;
		$this->token    = $token;
	}

	/**
	 * Registers the deliberately public challenge issuer with bounded REST arguments.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register(): void {
		register_rest_route(
			'rktsky-antispam/v1',
			'/challenge',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'challenge' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'provider' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'comments', 'cf7', 'gravityforms', 'wpforms' ),
					),
					'form'     => array(
						'required'  => true,
						'type'      => 'string',
						'pattern'   => '^[a-zA-Z0-9_-]{1,64}$',
						'maxLength' => 64,
					),
					'intent'   => array(
						'required'  => true,
						'type'      => 'string',
						'pattern'   => '^[a-f0-9]{64}$',
						'maxLength' => 64,
					),
				),
			)
		);
	}

	/**
	 * Returns a new challenge only for an enabled, signed rendered scope.
	 *
	 * Public issuance authenticates no visitor and performs no database writes.
	 *
	 * @since 0.1.0
	 * @param \WP_REST_Request $request REST-validated public arguments.
	 * @return \WP_REST_Response|\WP_Error Uncacheable challenge or generic failure.
	 */
	public function challenge( \WP_REST_Request $request ) {
		$provider = (string) $request->get_param( 'provider' );
		$form_id  = (string) $request->get_param( 'form' );
		$intent   = (string) $request->get_param( 'intent' );
		if ( ! $this->settings->enabled( $provider ) || ! $this->token->valid_intent( $intent, $provider, $form_id ) ) {
			return new \WP_Error( 'rktsky_antispam_scope', __( 'The form protection is unavailable. Please reload the page.', 'wp-antispam' ), array( 'status' => 403 ) );
		}
		$proof            = $this->token->issue( $provider, $form_id );
		$proof['minimum'] = $this->settings->all()['minimum_seconds'];
		$response         = new \WP_REST_Response( $proof );
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
		return $response;
	}

	/**
	 * Renders only this integration's inside-form controls and enqueues one small asset.
	 *
	 * @since 0.1.0
	 * @param string $provider Native provider identifier.
	 * @param string $form_id Native form identifier.
	 * @return string Escaped controls; empty for unsupported or disabled scopes.
	 */
	public function markup( string $provider, string $form_id ): string {
		$intent = $this->token->intent( $provider, $form_id );
		if ( ! $this->settings->enabled( $provider ) || '' === $intent ) {
			return '';
		}
		wp_enqueue_script(
			'rktsky-antispam',
			plugins_url( 'assets/protection.js', RKTSKY_ANTISPAM_FILE ),
			array(),
			RKTSKY_ANTISPAM_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		$endpoint = rest_url( 'rktsky-antispam/v1/challenge' );
		ob_start();
		include dirname( __DIR__ ) . '/templates/fields.php';
		return (string) ob_get_clean();
	}
}
