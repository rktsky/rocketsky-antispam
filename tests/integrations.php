<?php
/**
 * Isolated adapter contract tests using explicit WordPress/provider stubs.
 *
 * Run this file in its own PHP process. These stubs establish control-flow
 * invariants only; native.php and native-providers.php exercise installed code.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

namespace {
	if ( PHP_SAPI !== 'cli' ) {
		exit;
	}
}

namespace Rktsky\Antispam {
	/** Dependency stub for explicit integration enablement. @since 0.1.0 */
	final class Settings {
		/** @var array Disabled providers. @since 0.1.0 */
		public array $disabled = array();
		/**
		 * Returns the fixture's configured enablement.
		 *
		 * @since 0.1.0
		 * @param string $provider Provider ID.
		 * @return bool Whether the fixture enables the provider.
		 */
		public function enabled( string $provider ): bool {
			return ! in_array( $provider, $this->disabled, true );
		}
	}

	/** Guard stub; it does not validate cryptographic proof. @since 0.1.0 */
	final class Guard {
		/** @var string Configured synthetic verdict. @since 0.1.0 */
		public string $reason = '';
		/** @var array Synthetic guard calls. @since 0.1.0 */
		public array $checks = array();
		/** @var array Fixed policy rejections. @since 0.1.0 */
		public array $rejects = array();
		/**
		 * Records adapter arguments without implementing production security.
		 *
		 * @since 0.1.0
		 * @param string $provider Provider ID.
		 * @param string $form_id Native form ID.
		 * @param array  $posted Synthetic submitted controls.
		 * @return string Configured synthetic verdict.
		 */
		public function check( string $provider, string $form_id, array $posted ): string {
			$this->checks[] = array( $provider, $form_id, $posted );
			return $this->reason;
		}
		/**
		 * Records fixed policy rejections without simulating security checks.
		 *
		 * @since 0.1.0
		 * @param string $provider Provider ID.
		 * @param string $reason Fixed policy reason.
		 * @return void
		 */
		public function reject( string $provider, string $reason ): void {
			$this->rejects[] = array( $provider, $reason );
		}
		/**
		 * Supplies a message with HTML metacharacters to exercise escaping.
		 *
		 * @since 0.1.0
		 * @return string Synthetic public message.
		 */
		public function message(): string {
			return 'Retry <safely> & reload';
		}
	}

	/** Rendering stub limited to provider and form scope. @since 0.1.0 */
	final class Fields {
		/**
		 * Makes the scope observable without implementing the production protocol.
		 *
		 * @since 0.1.0
		 * @param string $provider Provider ID.
		 * @param string $form_id Native form ID.
		 * @return string Synthetic inside-form marker.
		 */
		public function markup( string $provider, string $form_id ): string {
			return '<input data-provider="' . $provider . '" value="' . $form_id . '">';
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	$GLOBALS['integration_hooks'] = array();
	$GLOBALS['integration_assertions'] = 0;

	/** Minimal error stub retaining code/data identity. @since 0.1.0 */
	final class WP_Error {
		/** @var string Error code. @since 0.1.0 */
		public string $code;
		/** @var array Error data. @since 0.1.0 */
		public array $data;
		/**
		 * Creates a synthetic REST error.
		 *
		 * @since 0.1.0
		 * @param string $code Error code.
		 * @param string $message Error message.
		 * @param array  $data Error data.
		 * @return void
		 */
		public function __construct( string $code, string $message, array $data = array() ) {
			$this->code = $code;
			$this->data = $data;
		}
	}

	/** REST request stub using ArrayObject's native parameter access. @since 0.1.0 */
	final class WP_REST_Request extends \ArrayObject {
		/**
		 * Returns synthetic, already-unslashed REST parameters.
		 *
		 * @since 0.1.0
		 * @return array Fixture parameters.
		 */
		public function get_params(): array {
			return $this->getArrayCopy();
		}
	}

	/**
	 * Records a WordPress filter registration without dispatching providers.
	 *
	 * @since 0.1.0
	 * @param string   $hook Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Callback priority.
	 * @param int      $args Accepted argument count.
	 * @return void
	 */
	function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		$GLOBALS['integration_hooks'][ $hook ] = array( $callback, $priority, $args );
	}

	/**
	 * Records an action registration using the same fixture registry.
	 *
	 * @since 0.1.0
	 * @param string   $hook Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Callback priority.
	 * @param int      $args Accepted argument count.
	 * @return void
	 */
	function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_filter( $hook, $callback, $priority, $args );
	}

	/**
	 * Reproduces WordPress's integer normalization for synthetic IDs.
	 *
	 * @since 0.1.0
	 * @param mixed $value Candidate integer.
	 * @return int Absolute integer.
	 */
	function absint( $value ): int {
		return abs( (int) $value );
	}

	/**
	 * Removes WordPress request slashes from fixture values recursively.
	 *
	 * @since 0.1.0
	 * @param mixed $value Synthetic request data.
	 * @return mixed Unslashed request data.
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
	}

	/**
	 * Escapes fixture message content as WordPress's HTML API would.
	 *
	 * @since 0.1.0
	 * @param string $value Unescaped content.
	 * @return string Escaped HTML text.
	 */
	function esc_html( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Provides untranslated escaped fixture copy.
	 *
	 * @since 0.1.0
	 * @param string $value Message.
	 * @param string $domain Text domain.
	 * @return string Escaped message.
	 */
	function esc_html__( string $value, string $domain ): string {
		return esc_html( $value );
	}

	/**
	 * Recognizes the explicit error stub.
	 *
	 * @since 0.1.0
	 * @param mixed $value Candidate error.
	 * @return bool Whether the value is a fixture error.
	 */
	function is_wp_error( $value ): bool {
		return $value instanceof WP_Error;
	}

	/**
	 * Makes a wp_die rejection testable without terminating the test process.
	 *
	 * @since 0.1.0
	 * @param string $message Public error.
	 * @param string $title Error title.
	 * @param array  $args Error response configuration.
	 * @return never
	 */
	function wp_die( string $message, string $title, array $args ): never {
		$GLOBALS['integration_die_args'] = $args;
		throw new \RuntimeException( $message, $args['response'] );
	}

	/**
	 * Asserts an observable adapter contract.
	 *
	 * @since 0.1.0
	 * @param bool   $condition Assertion result.
	 * @param string $description Failure description.
	 * @return void
	 */
	function integration_expect( bool $condition, string $description ): void {
		++$GLOBALS['integration_assertions'];
		if ( ! $condition ) {
			throw new \RuntimeException( $description );
		}
	}

	/**
	 * Creates an isolated adapter fixture without production dependencies.
	 *
	 * @since 0.1.0
	 * @return array Settings, guard, renderer and adapter fixture.
	 */
	function integration_fixture(): array {
		$settings = new \Rktsky\Antispam\Settings();
		$guard = new \Rktsky\Antispam\Guard();
		$fields = new \Rktsky\Antispam\Fields();
		return array( $settings, $guard, $fields, new \Rktsky\Antispam\Integrations( $settings, $guard, $fields ) );
	}

	require dirname( __DIR__ ) . '/src/Integrations.php';

	integration_expect( \Rktsky\Antispam\Integrations::detected() === array( 'comments' => true, 'cf7' => false, 'gravityforms' => false, 'wpforms' => false ), 'Absent providers must not be detected.' );
	list( $settings, $guard, $fields, $adapter ) = integration_fixture();
	$adapter->register();
	integration_expect( isset( $GLOBALS['integration_hooks']['preprocess_comment'] ) && ! isset( $GLOBALS['integration_hooks']['wpcf7_spam'] ), 'Absent providers must not register hooks.' );

	// Conditional declarations make provider availability change at runtime.
	if ( true ) {
		/** Native form stub exposing only the documented current-form API. @since 0.1.0 */
		final class WPCF7_ContactForm {
			/** @var self|null Currently rendered form. @since 0.1.0 */
			public static $current = null;
			/** @var int Form ID. @since 0.1.0 */
			public int $form_id = 51;
			/**
			 * Returns the fixture's active form.
			 *
			 * @since 0.1.0
			 * @return self|null Active fixture form.
			 */
			public static function get_current() { return self::$current; }
			/**
			 * Returns the fixture's native form ID.
			 *
			 * @since 0.1.0
			 * @return int Native ID.
			 */
			public function id(): int { return $this->form_id; }
		}
		/** Native submission stub that records fixed spam diagnostics. @since 0.1.0 */
		final class WPCF7_Submission {
			/** @var array Synthetic diagnostic logs. @since 0.1.0 */
			public array $logs = array();
			/**
			 * Returns the fixture's active form.
			 *
			 * @since 0.1.0
			 * @return WPCF7_ContactForm Active form.
			 */
			public function get_contact_form() { return WPCF7_ContactForm::$current; }
			/**
			 * Records a fixed diagnostic object.
			 *
			 * @since 0.1.0
			 * @param array $log Diagnostic metadata.
			 * @return void
			 */
			public function add_spam_log( array $log ): void { $this->logs[] = $log; }
		}
		/** GF availability stub, not a form-processing simulation. @since 0.1.0 */
		final class GFForms {
			/** @var string Fixture version. @since 0.1.0 */
			public static string $version = '3.2.2';
		}
		/** GF display availability stub. @since 0.1.0 */
		final class GFFormDisplay {}
		/**
		 * Returns a WPForms process-container fixture.
		 *
		 * @since 0.1.0
		 * @return object Synthetic provider container.
		 */
		function wpforms(): object { return $GLOBALS['integration_wpforms']; }
	}
	$GLOBALS['integration_wpforms'] = (object) array( 'process' => (object) array( 'errors' => array() ) );
	integration_expect( ! in_array( false, \Rktsky\Antispam\Integrations::detected(), true ), 'Loaded supported providers must be detected.' );
	GFForms::$version = '2.6.9';
	integration_expect( false === \Rktsky\Antispam\Integrations::detected()['gravityforms'], 'GF without the before-persistence abort hook must not be supported.' );
	GFForms::$version = '3.2.2';
	$GLOBALS['integration_hooks'] = array();
	$settings->disabled = array( 'cf7', 'wpforms' );
	$adapter->register();
	integration_expect( ! isset( $GLOBALS['integration_hooks']['wpcf7_spam'] ) && ! isset( $GLOBALS['integration_hooks']['wpforms_process'] ) && isset( $GLOBALS['integration_hooks']['gform_abort_submission_with_confirmation'] ), 'Disabled providers must not register; supported GF must guard draft persistence.' );

	$_POST = array( 'rktsky_antispam_token' => 'synthetic\\"proof' );
	$comment = array( 'comment_type' => 'comment', 'comment_post_ID' => 91, 'comment_content' => 'Fixture text' );
	integration_expect( $adapter->check_comment( $comment ) === $comment && $guard->checks[0][1] === '91' && $guard->checks[0][2]['rktsky_antispam_token'] === 'synthetic"proof', 'Native comments must bind the actual post ID and unslash proof.' );
	$review = array( 'comment_type' => 'review', 'comment_post_ID' => 91 );
	integration_expect( $adapter->check_comment( $review ) === $review && end( $guard->checks )[1] === '91', 'Custom comment types must use proof instead of a blanket rejection or bypass.' );
	ob_start();
	$adapter->comment_fields( 93 );
	integration_expect( strpos( ob_get_clean(), 'value="93"' ) !== false, 'Comment fields must use the hook post ID.' );
	$count = count( $guard->checks );
	try {
		$adapter->check_comment( array( 'comment_type' => 'pingback', 'comment_post_ID' => 91 ) );
		integration_expect( false, 'Pingbacks must reject.' );
	} catch ( \RuntimeException $error ) {
		integration_expect( 403 === $error->getCode() && true === $GLOBALS['integration_die_args']['back_link'] && count( $guard->checks ) === $count && $guard->rejects === array( array( 'comments', 'pingback' ) ), 'Pingbacks must reject before guard evaluation with a return link and a fixed policy count.' );
	}
	$guard->reason = 'missing';
	try {
		$adapter->check_comment( $comment );
		integration_expect( false, 'Unprotected comments must reject.' );
	} catch ( \RuntimeException $error ) {
		integration_expect( 403 === $error->getCode() && strpos( $error->getMessage(), '&lt;safely&gt;' ) !== false, 'Native rejection must be escaped and use HTTP 403.' );
	}
	$prior = new WP_Error( 'prior', 'Prior failure' );
	integration_expect( $adapter->check_rest_comment( $prior, new WP_REST_Request() ) === $prior, 'REST must preserve earlier errors.' );
	$count = count( $guard->checks );
	integration_expect( $adapter->check_rest_comment( $comment, new WP_REST_Request( array( 'id' => 17 ) ) ) === $comment && count( $guard->checks ) === $count, 'Authorized REST edits must retain their separate native path.' );
	$error = $adapter->check_rest_comment( $comment, new WP_REST_Request() );
	integration_expect( $error instanceof WP_Error && 403 === $error->data['status'], 'REST creation without proof must return a native 403 error.' );
	$note = array( 'comment_type' => 'note', 'comment_post_ID' => 91 );
	integration_expect( $adapter->check_rest_comment( $note, new WP_REST_Request() ) instanceof WP_Error, 'Editor note creation must not gain an undocumented proof bypass.' );
	$guard->reason = '';
	integration_expect( $adapter->check_rest_comment( $comment, new WP_REST_Request( array( 'rktsky_antispam_token' => 'already\\unslashed' ) ) ) === $comment && end( $guard->checks )[2]['rktsky_antispam_token'] === 'already\\unslashed', 'REST parameters must not be unslashed a second time.' );

	WPCF7_ContactForm::$current = new WPCF7_ContactForm();
	$submission = new WPCF7_Submission();
	integration_expect( strpos( $adapter->cf7_fields( '<p>Native</p>' ), '<p>Native</p><input' ) === 0, 'CF7 markup must preserve native fields.' );
	$count = count( $guard->checks );
	integration_expect( true === $adapter->check_cf7( true, $submission ) && count( $guard->checks ) === $count, 'CF7 must preserve earlier spam decisions without evaluating again.' );
	$guard->reason = 'replay';
	integration_expect( true === $adapter->check_cf7( false, $submission ) && end( $guard->checks )[1] === '51' && count( $submission->logs ) === 1, 'CF7 must bind its native form and record a fixed diagnostic when rejecting.' );
	integration_expect( $adapter->cf7_message( 'Native', 'spam' ) === $guard->message() && $adapter->cf7_message( 'Native', 'mail_sent' ) === 'Native', 'CF7 retry copy must apply only to a local spam decision.' );
	$technical = array( 'rktsky_antispam_token' => 'secret', 'rktsky_as_0123456789abcdef0123' => '', 'rktsky_as_custom_field' => 'retained', 'your-message' => 'retained' );
	integration_expect( $adapter->cf7_processed_data( $technical ) === array( 'rktsky_as_custom_field' => 'retained', 'your-message' => 'retained' ), 'CF7 processed data must discard only reserved technical controls.' );
	WPCF7_ContactForm::$current->form_id = 52;
	integration_expect( $adapter->cf7_message( 'Native', 'spam' ) === 'Native', 'CF7 rejection copy must not leak to a different form.' );

	list( $settings, $guard, $fields, $adapter ) = integration_fixture();
	integration_expect( '' === $adapter->gravity_fields( '', array( 'id' => 81 ) ), 'GF must preserve form tags removed by another integration.' );
	integration_expect( strpos( $adapter->gravity_fields( '<form>', array( 'id' => 81 ) ), '<form><input' ) === 0, 'GF protection must be inside its opening form tag.' );
	$result = array( 'is_valid' => false, 'form' => array( 'id' => 81 ) );
	integration_expect( $adapter->check_gravity( $result, 'form-submit' ) === $result && count( $guard->checks ) === 0, 'GF must preserve existing validation failures.' );
	$result['is_valid'] = true;
	$guard->reason = 'missing';
	$_POST = array( 'gform_target_page_number_81' => '999' );
	integration_expect( false === $adapter->check_gravity( $result, 'api-submit' )['is_valid'] && count( $guard->checks ) === 1, 'Client page targets and API context must not bypass protection.' );
	integration_expect( strpos( $adapter->gravity_message( '<p>Native error</p>', array( 'id' => 81 ) ), '<p>Native error</p>' ) === 0 && strpos( $adapter->gravity_message( '', array( 'id' => 81 ) ), '&lt;safely&gt;' ) !== false, 'GF must preserve native messages and escape local retry copy.' );
	$count = count( $guard->checks );
	integration_expect( true === $adapter->abort_gravity( true, array( 'id' => 81 ) ) && count( $guard->checks ) === $count, 'GF must retain an earlier abort without checking again.' );
	integration_expect( true === $adapter->abort_gravity( false, array( 'id' => 82 ) ), 'GF draft-saving paths must reject without needing ordinary validation.' );
	integration_expect( is_string( $adapter->gravity_confirmation( array( 'redirect' => 'https://example.invalid/' ), array( 'id' => 82 ) ) ) && strpos( $adapter->gravity_confirmation( 'Success', array( 'id' => 82 ) ), '&lt;safely&gt;' ) !== false, 'Local GF aborts must not show a successful-looking confirmation or redirect.' );
	integration_expect( $adapter->gravity_confirmation( array( 'redirect' => 'https://example.invalid/' ), array( 'id' => 83 ) ) === array( 'redirect' => 'https://example.invalid/' ), 'Unrelated GF confirmations must remain native.' );
	$guard->reason = '';
	integration_expect( false === $adapter->abort_gravity( false, array( 'id' => 84 ) ), 'Valid GF proof must retain native persistence.' );

	list( $settings, $guard, $fields, $adapter ) = integration_fixture();
	$_POST = array( 'rktsky_antispam_token' => 'synthetic-permit' );
	$valid = array( 'is_valid' => true, 'form' => array( 'id' => 85 ) );
	integration_expect( true === $adapter->check_gravity( $valid, 'form-submit' )['is_valid'], 'GF submission validation must accept a successful guard verdict.' );
	$guard->reason = 'replay';
	integration_expect( false === $adapter->abort_gravity( false, array( 'id' => 85 ) ) && count( $guard->checks ) === 1, 'One matching validation-to-abort lifecycle must not consume its token twice.' );
	integration_expect( true === $adapter->abort_gravity( false, array( 'id' => 85 ) ) && count( $guard->checks ) === 2, 'A second independent GF abort must not reuse a consumed permit.' );
	$guard->reason = '';
	integration_expect( true === $adapter->check_gravity( $valid, 'api-validate' )['is_valid'], 'GF API-only validation must still run the guard.' );
	$guard->reason = 'replay';
	integration_expect( true === $adapter->abort_gravity( false, array( 'id' => 85 ) ) && count( $guard->checks ) === 4, 'GF API-only validation must not grant a later persistence permit.' );
	$guard->reason = '';
	$adapter->check_gravity( $valid, 'api-submit' );
	$_POST['rktsky_antispam_token'] = 'different-token';
	$guard->reason = 'missing';
	integration_expect( true === $adapter->abort_gravity( false, array( 'id' => 85 ) ), 'A GF lifecycle permit must bind the exact token.' );
	$_POST['rktsky_antispam_token'] = 'synthetic-permit';
	$guard->reason = '';
	$adapter->check_gravity( $valid, 'form-submit' );
	$guard->reason = 'replay';
	integration_expect( true === $adapter->abort_gravity( false, array( 'id' => 86 ) ), 'A GF lifecycle permit must bind the native form.' );
	integration_expect( true === $adapter->abort_gravity( true, array( 'id' => 85 ) ), 'A pre-existing GF abort must remain true.' );
	integration_expect( true === $adapter->abort_gravity( false, array( 'id' => 85 ) ), 'Even a pre-existing GF abort must consume the pending lifecycle permit.' );
	$guard->reason = '';
	$adapter->check_gravity( $valid, 'form-submit' );
	$invalid = $valid;
	$invalid['is_valid'] = false;
	$adapter->check_gravity( $invalid, 'form-submit' );
	$guard->reason = 'replay';
	integration_expect( true === $adapter->abort_gravity( false, array( 'id' => 85 ) ), 'A new validation invocation must invalidate the previous lifecycle permit.' );
	$guard->reason = '';
	$adapter->check_gravity( $valid, 'api-submit' );
	integration_expect( $adapter->gravity_confirmation( 'Native success', array( 'id' => 85 ) ) === 'Native success' && $adapter->gravity_message( 'Native error', array( 'id' => 85 ) ) === 'Native error', 'A new GF validation must clear stale local failure messages.' );

	$guard->reason = 'missing';
	$adapter->check_wpforms( array(), array(), array( 'id' => 61 ) );
	integration_expect( wpforms()->process->errors[61]['header'] === $guard->message() && end( $guard->checks )[1] === '61', 'WPForms must use its native error bucket and native form ID.' );
	wpforms()->process->errors[61]['header'] = 'Earlier provider error';
	$adapter->check_wpforms( array(), array(), array( 'id' => 61 ) );
	integration_expect( wpforms()->process->errors[61]['header'] === 'Earlier provider error', 'WPForms must preserve an earlier header error.' );
	$guard->reason = '';
	$adapter->check_wpforms( array(), array(), array( 'id' => 62 ) );
	integration_expect( ! isset( wpforms()->process->errors[62] ), 'Valid WPForms proof must not create native errors.' );
	ob_start();
	$adapter->wpforms_fields( array( 'id' => 63 ) );
	integration_expect( strpos( ob_get_clean(), 'value="63"' ) !== false, 'WPForms rendering must use the native form scope.' );

	echo 'Integration adapter stub tests: ' . $GLOBALS['integration_assertions'] . " assertions passed.\n";
}
