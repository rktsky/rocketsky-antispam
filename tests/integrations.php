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
		/** @var array Effective synthetic policy options. @since 0.3.0 */
		public array $options = array( 'privacy_mode' => true, 'allow_pingbacks' => false );
		/**
		 * Returns the fixture's explicit native-protocol policy.
		 *
		 * @since 0.3.0
		 * @return array Effective synthetic options.
		 */
		public function all(): array {
			return $this->options;
		}
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
		/** @var array Native spam observations. @since 0.1.0 */
		public array $observed = array();
		/**
		 * Records adapter arguments without implementing production security.
		 *
		 * @since 0.1.0
		 * @param string $provider Provider ID.
		 * @param string $form_id Native form ID.
		 * @param array  $posted Synthetic submitted controls.
		 * @param array  $fields Selected native content fields.
		 * @param bool $technical_only Whether content rules are excluded.
		 * @return string Configured synthetic verdict.
		 */
		public function check( string $provider, string $form_id, array $posted, array $fields = array(), bool $technical_only = false ): string {
			$this->checks[] = array( $provider, $form_id, $posted, $fields, $technical_only );
			return $technical_only && self::is_content_reason( $this->reason ) ? '' : $this->reason;
		}
		/**
		 * Exposes only the owned fixed classification contract for adapter tests.
		 *
		 * @since 0.5.0
		 * @param string $reason Synthetic guard verdict.
		 * @return bool Whether the configured verdict is a content match.
		 */
		public static function is_content_reason( string $reason ): bool {
			return in_array( $reason, array( 'keyword', 'bbcode', 'pattern', 'language', 'country' ), true );
		}
		/**
		 * Supplies a visible content-rejection fixture message.
		 *
		 * @since 0.5.0
		 * @return string Non-sensitive synthetic rejection.
		 */
		public function spam_message(): string {
			return 'Content rejected <safely>.';
		}
		/**
		 * Records native classification metadata without simulating statistics.
		 *
		 * @since 0.1.0
		 * @param string $provider Provider ID.
		 * @param string $form_id Native form ID.
		 * @param array  $posted Synthetic protection controls.
		 * @return void
		 */
		public function observe_spam( string $provider, string $form_id, array $posted ): void {
			$this->observed[] = array( $provider, $form_id, $posted );
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

	/** Opt-in quarantine stub; cryptography is tested by the native service suite. @since 0.1.0 */
	final class Quarantine {
		/** @var bool Synthetic retention permission. @since 0.1.0 */
		public bool $active = true;
		/** @var array Captured synthetic records. @since 0.1.0 */
		public array $captured = array();
		/**
		 * Returns synthetic retention permission.
		 *
		 * @since 0.1.0
		 * @return bool Whether the stub permits capture.
		 */
		public function enabled(): bool { return $this->active; }
		/**
		 * Records the adapter's selected data contract.
		 *
		 * @since 0.1.0
		 * @param string $provider Provider ID.
		 * @param string $form_id Native form ID.
		 * @param string $reason Fixed reason.
		 * @param array  $fields Selected native string fields.
		 * @param string $email Declared email value.
		 * @return void
		 */
		public function capture( string $provider, string $form_id, string $reason, array $fields, string $email = '' ): void {
			$this->captured[] = array( $provider, $form_id, $reason, $fields, $email );
		}
	}

	/** Metadata-only tracking stub; actual ledger SQL has native tests. @since 0.2.0 */
	final class Feedback {
		/** @var array Observed native prediction records. @since 0.2.0 */
		public array $tracked = array();
		/**
		 * Records one synthetic native prediction.
		 *
		 * @since 0.2.0
		 * @param string $source Native source category.
		 * @param int|string $id Native object ID.
		 * @param bool $predicted_spam Original prediction.
		 * @return void
		 */
		public function track( string $source, $id, bool $predicted_spam ): void {
			$this->tracked[] = array( $source, $id, $predicted_spam );
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
	 * Escapes the adapter's native confirmation identifier in this isolated stub.
	 *
	 * @since 0.5.0
	 * @param string $value Fixture attribute value.
	 * @return string Quoted-attribute-safe markup.
	 */
	function esc_attr( string $value ): string {
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
	 * Provides plain labels for the extraction contract tests.
	 *
	 * @since 0.1.0
	 * @param string $value Candidate label.
	 * @return string Label without HTML.
	 */
	function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }

	/**
	 * Repairs only synthetic UTF-8 fixture data.
	 *
	 * @since 0.1.0
	 * @param string $value Candidate text.
	 * @param bool   $strip Whether invalid sequences may be removed.
	 * @return string Valid UTF-8 fixture data.
	 */
	function wp_check_invalid_utf8( string $value, bool $strip = false ): string {
		return 1 === preg_match( '//u', $value ) ? $value : '';
	}

	/**
	 * Normalizes synthetic email values without changing the address.
	 *
	 * @since 0.1.0
	 * @param string $value Candidate address.
	 * @return string Trimmed candidate.
	 */
	function sanitize_email( string $value ): string { return trim( $value ); }

	/**
	 * Sanitizes the synthetic early-rejection text fixtures.
	 *
	 * @since 0.2.0
	 * @param string $value Raw synthetic value.
	 * @return string Plain synthetic text.
	 */
	function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }

	/**
	 * Preserves line breaks in the synthetic textarea fixtures.
	 *
	 * @since 0.2.0
	 * @param string $value Raw synthetic value.
	 * @return string Plain multiline synthetic text.
	 */
	function sanitize_textarea_field( string $value ): string { return trim( strip_tags( $value ) ); }

	/**
	 * Returns the synthetic native actor.
	 *
	 * @since 0.2.0
	 * @return int Synthetic actor ID.
	 */
	function get_current_user_id(): int { return $GLOBALS['integration_actor'] ?? 0; }

	/**
	 * Reports the synthetic native AJAX context.
	 *
	 * @since 0.2.0
	 * @return bool Whether AJAX is active.
	 */
	function wp_doing_ajax(): bool { return $GLOBALS['integration_ajax'] ?? false; }

	/**
	 * Reports only the exact synthetic native reply hook.
	 *
	 * @since 0.2.0
	 * @param string $hook Native hook name.
	 * @return bool Whether the exact synthetic hook is active.
	 */
	function doing_action( string $hook ): bool { return ( $GLOBALS['integration_action'] ?? '' ) === $hook; }

	/**
	 * Provides a deliberately narrow synthetic target capability.
	 *
	 * @since 0.2.0
	 * @param string $capability Native capability.
	 * @param int $post_id Target post.
	 * @return bool Fixture authorization.
	 */
	function current_user_can( string $capability, int $post_id ): bool {
		return 'edit_post' === $capability && 91 === $post_id && ( $GLOBALS['integration_can_reply'] ?? false );
	}

	/**
	 * Verifies the synthetic native reply intent without implementing WordPress nonces.
	 *
	 * @since 0.2.0
	 * @param string $nonce Synthetic nonce.
	 * @param string $action Native action.
	 * @return int|false Synthetic match or failure.
	 */
	function wp_verify_nonce( string $nonce, string $action ) {
		return 'native-reply-proof' === $nonce && 'replyto-comment' === $action ? 1 : false;
	}

	/**
	 * Recognizes valid synthetic addresses for the email-index contract.
	 *
	 * @since 0.1.0
	 * @param string $value Candidate address.
	 * @return string|false Address or rejection.
	 */
	function is_email( string $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ); }

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

	integration_expect( array_intersect_key( \Rktsky\Antispam\Integrations::detected(), array_flip( array( 'comments', 'cf7', 'gravityforms', 'wpforms' ) ) ) === array( 'comments' => true, 'cf7' => false, 'gravityforms' => false, 'wpforms' => false ), 'Absent providers must not be detected.' );
	list( $settings, $guard, $fields, $adapter ) = integration_fixture();
	$adapter->register();
	integration_expect( isset( $GLOBALS['integration_hooks']['preprocess_comment'] ) && ! isset( $GLOBALS['integration_hooks']['wpcf7_spam'] ), 'Absent providers must not register hooks.' );
	integration_expect( class_exists( '\\Rktsky\\Antispam\\Integrations\\Comments', false ) && ! class_exists( '\\Rktsky\\Antispam\\Integrations\\ContactForm7', false ) && ! class_exists( '\\Rktsky\\Antispam\\Integrations\\WooCommerceCheckout', false ), 'Unavailable integration modules must remain unloaded.' );

	// Conditional declarations make provider availability change at runtime.
	if ( true ) {
		/** Native form stub exposing only the documented current-form API. @since 0.1.0 */
		final class WPCF7_ContactForm {
			/** @var self|null Currently rendered form. @since 0.1.0 */
			public static $current = null;
			/** @var int Form ID. @since 0.1.0 */
			public int $form_id = 51;
			/** @var array Declared native form tags. @since 0.1.0 */
			public array $tags = array();
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
			/**
			 * Returns synthetic declared form tags.
			 *
			 * @since 0.1.0
			 * @return array Tag objects.
			 */
			public function scan_form_tags(): array { return $this->tags; }
		}
		/** Native submission stub that records fixed spam diagnostics. @since 0.1.0 */
		final class WPCF7_Submission {
			/** @var array Synthetic diagnostic logs. @since 0.1.0 */
			public array $logs = array();
			/** @var array Synthetic processed content. @since 0.1.0 */
			public array $posted = array();
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
			/**
			 * Returns processed fixture values, never raw POST.
			 *
			 * @since 0.1.0
			 * @return array Processed content.
			 */
			public function get_posted_data(): array { return $this->posted; }
		}
		/** GF availability stub, not a form-processing simulation. @since 0.1.0 */
		final class GFForms {
			/** @var string Fixture version. @since 0.1.0 */
			public static string $version = '3.2.2';
		}
		/** GF display availability stub. @since 0.1.0 */
		final class GFFormDisplay {}
		/** Declared GF field stub used to exercise allowlisted native input IDs. @since 0.1.0 */
		final class GF_Field_Contract_Stub {
			/** @var int Native field ID. @since 0.1.0 */
			public int $id = 1;
			/** @var string Native field type. @since 0.1.0 */
			public string $type = 'text';
			/** @var string Field label. @since 0.1.0 */
			public string $label = 'Message';
			/** @var bool Administrative field flag. @since 0.1.0 */
			public bool $administrative = false;
			/** @var array|null Compound input definitions. @since 0.1.0 */
			public ?array $inputs = null;
			/**
			 * Returns the declared native input type.
			 *
			 * @since 0.1.0
			 * @return string Field type.
			 */
			public function get_input_type(): string { return $this->type; }
			/**
			 * Returns the administrative-only flag.
			 *
			 * @since 0.1.0
			 * @return bool Administrative-only state.
			 */
			public function is_administrative(): bool { return $this->administrative; }
			/**
			 * Returns declared compound inputs.
			 *
			 * @since 0.1.0
			 * @return array|null Declared compound inputs.
			 */
			public function get_entry_inputs(): ?array { return $this->inputs; }
		}
		/**
		 * Returns a WPForms process-container fixture.
		 *
		 * @since 0.1.0
		 * @return object Synthetic provider container.
		 */
		function wpforms(): object { return $GLOBALS['integration_wpforms']; }
	}
	$GLOBALS['integration_wpforms'] = (object) array( 'process' => (object) array( 'errors' => array() ) );
	integration_expect( ! in_array( false, array_intersect_key( \Rktsky\Antispam\Integrations::detected(), array_flip( array( 'comments', 'cf7', 'gravityforms', 'wpforms' ) ) ), true ), 'Loaded supported providers must be detected.' );
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
	integration_expect( true === $adapter->check_cf7( true, $submission ) && count( $guard->checks ) === $count + 1 && end( $guard->checks )[3] === array() && true === end( $guard->checks )[4], 'CF7 must preserve earlier spam while checking technical proof before native storage.' );
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

	// Quarantine contracts exercise only selected native fields, never a raw entry dump.
	$quarantine = new \Rktsky\Antispam\Quarantine();
	$feedback = new \Rktsky\Antispam\Feedback();
	$guard = new \Rktsky\Antispam\Guard();
	$guard->reason = 'missing';
	$adapter = new \Rktsky\Antispam\Integrations( $settings, $guard, $fields, $quarantine, $feedback );
	$comment = array( 'comment_type' => 'comment', 'comment_post_ID' => 91, 'comment_author' => 'Synthetic author', 'comment_author_email' => 'fixture@example.invalid', 'comment_author_url' => 'https://example.invalid/', 'comment_content' => 'Synthetic message', 'comment_author_IP' => '192.0.2.1', 'comment_agent' => 'private-agent' );
	try {
		$adapter->check_comment( $comment );
	} catch ( \RuntimeException $error ) {
		integration_expect( 403 === $error->getCode(), 'Retaining a rejected comment must not authorize insertion.' );
	}
	$capture = end( $quarantine->captured );
	integration_expect( $capture[3] === array( 'content' => 'Synthetic message', 'name' => 'Synthetic author', 'email' => 'fixture@example.invalid', 'url' => 'https://example.invalid/' ) && $capture[4] === 'fixture@example.invalid', 'Comment capture must exclude IP, user agent and technical controls.' );
	integration_expect( end( $guard->checks )[3] === $capture[3], 'Comment content rules and retention must use the same selected field contract.' );
	$count = count( $quarantine->captured );
	try {
		$adapter->check_comment( array( 'comment_type' => 'trackback', 'comment_post_ID' => 91, 'comment_content' => 'Must not be retained' ) );
	} catch ( \RuntimeException $error ) {
		integration_expect( count( $quarantine->captured ) === $count, 'Ping and trackback content must never enter quarantine.' );
	}
	$quarantine->active = false;
	$adapter->check_rest_comment( $comment, new WP_REST_Request() );
	integration_expect( count( $quarantine->captured ) === $count, 'The quarantine service opt-out must prevent capture.' );
	$quarantine->active = true;
	$guard->reason = '';
	$_POST = array( 'rktsky_antispam_token' => 'native-comment-proof' );
	$adapter->check_comment( $comment );
	$inserted = (object) ( $comment + array( 'comment_approved' => 'spam' ) );
	$count = count( $quarantine->captured );
	$adapter->observe_comment_spam( 123, $inserted );
	integration_expect( end( $guard->observed )[2] === array( 'rktsky_antispam_token' => 'native-comment-proof' ) && count( $quarantine->captured ) === $count, 'Native comment spam must use its exact proof receipt and avoid duplicate quarantine copies.' );

	WPCF7_ContactForm::$current = new WPCF7_ContactForm();
	WPCF7_ContactForm::$current->tags = array( (object) array( 'basetype' => 'textarea', 'name' => 'message' ), (object) array( 'basetype' => 'email', 'name' => 'email' ), (object) array( 'basetype' => 'hidden', 'name' => 'private-hidden' ), (object) array( 'basetype' => 'file', 'name' => 'upload' ), (object) array( 'basetype' => 'text', 'name' => 'rktsky_antispam_token' ) );
	$submission->posted = array( 'message' => 'Synthetic CF7 message', 'email' => 'fixture@example.invalid', 'private-hidden' => 'hidden-secret', 'upload' => '/private/path', 'rktsky_antispam_token' => 'secret-token', 'undeclared' => 'secret' );
	$guard->reason = 'pattern';
	$adapter->check_cf7( false, $submission );
	integration_expect( end( $quarantine->captured )[3] === array( 'message' => 'Synthetic CF7 message', 'email' => 'fixture@example.invalid' ), 'CF7 must capture declared allowed tags only, excluding hidden/file/technical/unknown fields.' );
	$count = count( $guard->checks );
	$adapter->check_cf7( true, $submission );
	integration_expect( count( $guard->checks ) === $count + 1 && end( $quarantine->captured )[2] === 'native_spam', 'Earlier CF7 spam must retain its verdict after a technical proof check.' );

	$text_field = new GF_Field_Contract_Stub();
	$email_field = clone $text_field;
	$email_field->id = 2;
	$email_field->type = 'email';
	$name_field = clone $text_field;
	$name_field->id = 3;
	$name_field->type = 'name';
	$name_field->inputs = array( array( 'id' => '3.3' ), array( 'id' => '3.6' ) );
	$hidden_field = clone $text_field;
	$hidden_field->id = 4;
	$hidden_field->type = 'hidden';
	$payment_field = clone $text_field;
	$payment_field->id = 5;
	$payment_field->type = 'creditcard';
	$admin_field = clone $text_field;
	$admin_field->id = 6;
	$admin_field->administrative = true;
	$gf_form = array( 'id' => 17, 'fields' => array( $text_field, $email_field, $name_field, $hidden_field, $payment_field, $admin_field ) );
	$_POST = array( 'input_1' => 'Visible message', 'input_2' => 'fixture@example.invalid', 'input_3_3' => 'First', 'input_3_6' => 'Last', 'input_4' => 'hidden-secret', 'input_5' => 'payment-secret', 'input_6' => 'admin-secret', 'input_999' => 'unknown-secret' );
	$adapter->check_gravity( array( 'is_valid' => true, 'form' => $gf_form ), 'form-submit' );
	$selected = end( $quarantine->captured )[3];
	integration_expect( count( $selected ) === 4 && in_array( 'First', $selected, true ) && ! in_array( 'hidden-secret', $selected, true ) && ! in_array( 'payment-secret', $selected, true ) && ! in_array( 'admin-secret', $selected, true ), 'GF must use declared scalar/compound input IDs and exclude hidden/payment/admin/unknown fields.' );
	$adapter->check_gravity( array( 'is_valid' => false, 'form' => $gf_form ), 'form-submit' );
	$count = count( $quarantine->captured );
	integration_expect( true === $adapter->observe_gravity_spam( true, $gf_form, array( '1' => 'Native GF message', '2' => 'fixture@example.invalid', '4' => 'hidden-secret' ) ), 'Native GF spam must remain true.' );
	integration_expect( count( $quarantine->captured ) === $count, 'Persisted native GF spam must not create a duplicate quarantine payload.' );
	$adapter->observe_gravity_spam( true, $gf_form, array() );
	$adapter->abort_gravity( true, $gf_form );
	integration_expect( count( $quarantine->captured ) === $count + 1, 'A native abort before persistence may retain one optional selected-data quarantine object.' );
	$guard->reason = '';
	$_POST['rktsky_antispam_token'] = 'new-gf-proof';
	$adapter->check_gravity( array( 'is_valid' => true, 'form' => $gf_form ), 'form-submit' );
	$adapter->abort_gravity( false, $gf_form );
	$adapter->track_gravity_entry( array( 'id' => 731, 'status' => 'spam' ), $gf_form );
	integration_expect( end( $feedback->tracked ) === array( 'gravityforms', 731, true ), 'Native GF feedback must use final persisted spam status for its observed token-bound lifecycle.' );
	$count = count( $feedback->tracked );
	$adapter->track_gravity_entry( array( 'id' => 732, 'status' => 'active' ), $gf_form );
	integration_expect( count( $feedback->tracked ) === $count, 'Unknown or repeated native GF creation events must not invent quality predictions.' );
	$adapter->check_gravity( array( 'is_valid' => true, 'form' => $gf_form ), 'api-validate' );
	$adapter->track_gravity_entry( array( 'id' => 733, 'status' => 'active' ), $gf_form );
	integration_expect( count( $feedback->tracked ) === $count, 'GF validation-only contexts must not invent a persisted quality observation.' );
	$guard->reason = 'keyword';

	$wp_form = array( 'id' => 21, 'fields' => array( 1 => array( 'type' => 'textarea', 'label' => 'Message' ), 2 => array( 'type' => 'email', 'label' => 'Email' ), 3 => array( 'type' => 'hidden', 'label' => 'Internal' ), 4 => array( 'type' => 'password', 'label' => 'Password' ), 5 => array( 'type' => 'name', 'label' => 'Name' ) ) );
	$wp_fields = array( 1 => array( 'type' => 'textarea', 'value' => 'Formatted message', 'raw' => 'raw-secret' ), 2 => array( 'type' => 'email', 'value' => 'fixture@example.invalid' ), 3 => array( 'type' => 'hidden', 'value' => 'hidden-secret' ), 4 => array( 'type' => 'password', 'value' => 'password-secret' ) );
	$adapter->check_wpforms( $wp_fields, array( 'raw' => 'secret' ), $wp_form );
	integration_expect( end( $quarantine->captured )[3] === array( '1: Message' => 'Formatted message', '2: Email' => 'fixture@example.invalid' ), 'WPForms capture must use safe matching native types and the formatted value property only.' );
	$adapter->begin_wpforms( $wp_form, array() );
	integration_expect( 'native rejection' === $adapter->observe_wpforms_honeypot( 'native rejection', $wp_fields, array(), $wp_form ), 'WPForms original honeypot verdict must remain unchanged.' );
	$count = count( $quarantine->captured );
	$adapter->observe_wpforms_antispam( false, $wp_fields, array(), $wp_form );
	integration_expect( count( $quarantine->captured ) === $count, 'WPForms native observers must deduplicate the same lifecycle.' );
	$adapter->begin_wpforms( $wp_form, array() );
	$_POST['wpforms']['fields'] = array( 1 => '<b>Early</b> message', 2 => 'fixture@example.invalid', 3 => 'hidden-secret', 4 => 'password-secret', 5 => array( 'first' => 'Synthetic', 'last' => 'Author', 'private' => 'nested-secret', 'middle' => array( 'must' => 'skip' ) ), 999 => 'unknown-secret' );
	wpforms()->process->spam_errors[21] = array( 'header' => 'Native V3 early rejection' );
	$adapter->observe_wpforms_spam( array(), 0, $wp_form );
	integration_expect( end( $quarantine->captured )[3] === array( '1: Message' => 'Early message', '2: Email' => 'fixture@example.invalid', '5: Name' => 'Synthetic, Author' ), 'Early native WPForms rejection must sanitize only declared text IDs and known flat compound parts.' );

	$GLOBALS['integration_actor'] = 7;
	$GLOBALS['integration_ajax'] = true;
	$GLOBALS['integration_action'] = 'wp_ajax_replyto-comment';
	$GLOBALS['integration_can_reply'] = true;
	$_POST = array( '_ajax_nonce-replyto-comment' => 'native-reply-proof' );
	$reply = $comment + array( 'user_id' => 7 );
	$count = count( $guard->checks );
	integration_expect( $adapter->check_comment( $reply ) === $reply && count( $guard->checks ) === $count, 'The exact authorized native backend reply must retain Core processing without a protection-field requirement.' );
	$GLOBALS['integration_action'] = '';
	$guard->reason = '';
	$adapter->check_comment( $reply );
	integration_expect( count( $guard->checks ) === $count + 1, 'A logged-in frontend actor with a reply nonce must still require the guard.' );
	$GLOBALS['integration_action'] = 'wp_ajax_replyto-comment';
	$_POST['_ajax_nonce-replyto-comment'] = 'forged';
	$adapter->check_comment( $reply );
	integration_expect( count( $guard->checks ) === $count + 2, 'A forged backend reply nonce must not gain the exception.' );

	echo 'Integration adapter stub tests: ' . $GLOBALS['integration_assertions'] . " assertions passed.\n";
}
