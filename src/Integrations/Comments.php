<?php
/**
 * Connects local protection to the native Comments lifecycle.
 *
 * @package RktskyAntispam
 * @since 0.3.0
 */

namespace Rktsky\Antispam\Integrations;

use Rktsky\Antispam\{Settings, Guard, Fields, Quarantine, Feedback};

defined( 'ABSPATH' ) || exit;

/**
 * Owns only the Comments integration and its request-local state.
 *
 * @since 0.3.0
 */
final class Comments extends Adapter {

	/**
	 * Own token values linking native/REST comments to their final insert status.
	 *
	 * @since 0.2.0
	 * @var array<string,array<string,string>>
	 */
	private array $comment_evidence = array();

	/**
	 * Content decisions awaiting Core's approval filter in the same native call.
	 *
	 * This is a spam label, never permission to bypass technical verification.
	 * REST assigns its prepared status directly because Core approves it earlier.
	 *
	 * @since 0.5.0
	 * @var array<string,bool>
	 */
	private array $comment_spam = array();

	/**
	 * Exact native XML-RPC server owning the current single-use pingback intent.
	 *
	 * @since 0.3.0
	 * @var int
	 */
	private int $pingback_server = 0;

	/**
	 * Final source URL selected by Core's pingback filter, retained in memory only.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	private string $pingback_source = '';

	/**
	 * One native trackback intent bound to its target and source URL.
	 *
	 * @since 0.3.0
	 * @var array{post:int,source:string}|null
	 */
	private ?array $trackback_intent = null;

	/**
	 * Registers native hooks after provider detection and explicit enablement.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'comment_form', array( $this, 'comment_fields' ) );
		add_filter( 'preprocess_comment', array( $this, 'check_comment' ) );
		add_filter( 'pre_comment_approved', array( $this, 'approve_comment' ), PHP_INT_MAX, 2 );
		add_filter( 'rest_pre_insert_comment', array( $this, 'check_rest_comment' ), 10, 2 );
		add_action( 'wp_insert_comment', array( $this, 'observe_comment_spam' ), PHP_INT_MAX, 2 );
		add_action( 'xmlrpc_call', array( $this, 'begin_xmlrpc' ), PHP_INT_MAX, 3 );
		add_filter( 'pingback_ping_source_uri', array( $this, 'pingback_policy' ), PHP_INT_MIN );
		add_filter( 'pingback_ping_source_uri', array( $this, 'pingback_source' ), PHP_INT_MAX );
		add_action( 'pre_trackback_post', array( $this, 'begin_trackback' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Renders protection inside the native comment form for its actual post ID.
	 *
	 * @since 0.1.0
	 * @param int $post_id Post receiving comments.
	 * @return void
	 */
	public function comment_fields( $post_id ): void {
		// Fields escapes its own fixed markup and does not include user content.
		echo $this->fields->markup( 'comments', (string) absint( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by Fields::markup().
	}

	/**
	 * Rejects unverifiable requests and routes verified content matches to Core spam.
	 *
	 * @since 0.1.0
	 * @since 0.5.0 Keeps reviewable policy matches in the native moderation queue.
	 * @param array $comment Native comment data.
	 * @return array Unmodified accepted comment data.
	 */
	public function check_comment( array $comment ): array {
		$type = isset( $comment['comment_type'] ) && is_string( $comment['comment_type'] ) ? $comment['comment_type'] : '';
		$post = isset( $comment['comment_post_ID'] ) ? absint( $comment['comment_post_ID'] ) : 0;
		unset( $this->comment_spam[ (string) $post ] );
		if ( in_array( $type, array( 'pingback', 'trackback' ), true ) ) {
			unset( $this->comment_evidence[ (string) $post ] );
			if ( $this->consume_native_ping( $type, $post, $comment['comment_author_url'] ?? '' ) ) {
				return $comment;
			}
			$this->guard->reject( 'comments', 'pingback' );
			wp_die(
				esc_html( $this->guard->message() ),
				esc_html__( 'Comment not submitted', 'wp-antispam' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
			return $comment;
		}
		$this->reset_ping_intents();
		if ( $this->authorized_backend_reply( $comment, $post ) ) {
			unset( $this->comment_evidence[ (string) $post ] );
			return $comment;
		}
		$data                                     = $this->comment_content( $comment, true );
		$posted                                   = $this->posted();
		$this->comment_evidence[ (string) $post ] = $this->token_evidence( $posted );
		$reason                                   = $this->guard->check( 'comments', (string) $post, $posted, $data['fields'] );
		if ( Guard::is_content_reason( $reason ) ) {
			$this->comment_spam[ (string) $post ] = true;
			return $comment;
		}

		if ( '' !== $reason ) {
			unset( $this->comment_evidence[ (string) $post ] );
			if ( 'pingback' === $reason ) {
				$this->guard->reject( 'comments', 'pingback' );
			} else {
				$this->retain( 'comments', (string) $post, $reason, $data );
			}

			wp_die(
				esc_html( $this->guard->message() ),
				esc_html__( 'Comment not submitted', 'wp-antispam' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		return $comment;
	}

	/**
	 * Labels one verified native comment without reversing earlier Core rejections.
	 *
	 * WordPress still owns duplicate/flood checks, persistence, notifications,
	 * moderation, retention and privacy tools. No second content copy is retained.
	 *
	 * @since 0.5.0
	 * @param int|string|\WP_Error $approved Earlier native approval or rejection.
	 * @param array                $comment Native comment data for this call.
	 * @return int|string|\WP_Error Original result or the native spam status.
	 */
	public function approve_comment( $approved, array $comment ) {
		$post = isset( $comment['comment_post_ID'] ) ? (string) absint( $comment['comment_post_ID'] ) : '0';
		$spam = ! empty( $this->comment_spam[ $post ] );
		if ( ! $spam || is_wp_error( $approved ) || in_array( $approved, array( 'spam', 'trash' ), true ) ) {
			return $approved;
		}
		return 'spam';
	}

	/**
	 * Protects REST comment creation, which bypasses preprocess_comment in Core.
	 *
	 * Existing-comment edits retain Core's independent authorization path.
	 * Request parameters are already unslashed by the REST server.
	 *
	 * @since 0.1.0
	 * @param array|\WP_Error  $comment Prepared comment or an earlier rejection.
	 * @param \WP_REST_Request $request REST request being processed.
	 * @return array|\WP_Error Accepted data or a neutral creation error.
	 */
	public function check_rest_comment( $comment, $request ) {
		if ( is_wp_error( $comment ) || ! empty( $request['id'] ) ) {
			return $comment;
		}

		$post = isset( $comment['comment_post_ID'] ) ? absint( $comment['comment_post_ID'] ) : 0;
		$type = isset( $comment['comment_type'] ) && is_string( $comment['comment_type'] ) ? $comment['comment_type'] : '';
		unset( $this->comment_spam[ (string) $post ] );
		$this->reset_ping_intents();
		if ( in_array( $type, array( 'pingback', 'trackback' ), true ) ) {
			unset( $this->comment_evidence[ (string) $post ] );
			$this->guard->reject( 'comments', 'pingback' );
			return new \WP_Error( 'wp_antispam_rejected', $this->guard->message(), array( 'status' => 403 ) );
		}
		$params                                   = $request->get_params();
		$data                                     = $this->comment_content( $comment, false );
		$this->comment_evidence[ (string) $post ] = $this->token_evidence( is_array( $params ) ? $params : array() );
		$reason                                   = $this->guard->check( 'comments', (string) $post, is_array( $params ) ? $params : array(), $data['fields'] );
		if ( Guard::is_content_reason( $reason ) ) {
			if ( ! isset( $comment['comment_approved'] ) || 'trash' !== $comment['comment_approved'] ) {
				$comment['comment_approved'] = 'spam';
			}
			return $comment;
		}

		if ( '' !== $reason ) {
			unset( $this->comment_evidence[ (string) $post ] );
			if ( 'pingback' === $reason ) {
				$this->guard->reject( 'comments', 'pingback' );
			} else {
				$this->retain( 'comments', (string) $post, $reason, $data );
			}

			return new \WP_Error( 'wp_antispam_rejected', $this->guard->message(), array( 'status' => 403 ) );
		}

		return $comment;
	}

	/**
	 * Observes actual native spam/trash insertion without changing its status.
	 *
	 * The supplied comment is already stored by Core. No historical query or
	 * manual trash transition is treated as a newly received spam submission.
	 *
	 * @since 0.2.0
	 * @param int         $comment_id Newly inserted native comment ID.
	 * @param \WP_Comment $comment    Native inserted comment object.
	 * @return void
	 */
	public function observe_comment_spam( $comment_id, $comment ): void {
		$post_id = (string) absint( $comment->comment_post_ID );
		unset( $this->comment_spam[ $post_id ] );
		if ( ! array_key_exists( $post_id, $this->comment_evidence ) ) {
			return;
		}
		$evidence = $this->comment_evidence[ $post_id ];
		unset( $this->comment_evidence[ $post_id ] );

		if ( null !== $this->feedback && ! in_array( $comment->comment_type, array( 'pingback', 'trackback' ), true ) ) {
			$this->feedback->track( 'comments', $comment_id, in_array( $comment->comment_approved, array( 'spam', 'trash' ), true ) );
		}
		if ( ! in_array( $comment->comment_approved, array( 'spam', 'trash' ), true ) || in_array( $comment->comment_type, array( 'pingback', 'trackback' ), true ) ) {
			return;
		}

		$this->guard->observe_spam( 'comments', $post_id, $evidence );
		// Core already retains native spam; a second quarantine copy would duplicate quality data.
	}

	/**
	 * Resets intents on every XML-RPC method and arms only Core's native pingback.
	 *
	 * The arguments are deliberately not retained. Method names alone cannot
	 * exempt a frontend submission or a different XML-RPC method.
	 *
	 * @since 0.3.0
	 * @param string $method Native XML-RPC method name.
	 * @param mixed  $args Native method arguments, deliberately not inspected.
	 * @param object $server Native XML-RPC server instance.
	 * @return void
	 */
	public function begin_xmlrpc( $method, $args, $server ): void {
		$this->reset_ping_intents();
		if ( 'pingback.ping' === $method && defined( 'XMLRPC_REQUEST' ) && true === XMLRPC_REQUEST && $server instanceof \wp_xmlrpc_server && doing_action( 'xmlrpc_call' ) ) {
			$this->pingback_server = spl_object_id( $server );
		}
	}

	/**
	 * Stops disallowed native pingbacks before other source filters or remote lookup.
	 *
	 * Returning an empty source keeps Core's own protocol error path. This early
	 * gate also prevents Core's URL validation from resolving a source hostname.
	 *
	 * @since 0.3.0
	 * @param mixed $source Native source URI or an earlier filter result.
	 * @return mixed Unmodified source or an empty disabled source.
	 */
	public function pingback_policy( $source ) {
		if ( ! $this->allow_native_pings() || 0 === $this->pingback_server || ! defined( 'XMLRPC_REQUEST' ) || true !== XMLRPC_REQUEST ) {
			return '';
		}
		return $source;
	}

	/**
	 * Binds the final source selected by native filters to one Core pingback call.
	 *
	 * Disabled requests are counted once and stop before Core fetches the source.
	 * Allowed requests retain Core's source, target, duplicate and moderation checks.
	 *
	 * @since 0.3.0
	 * @param mixed $source Final native source URI or an earlier rejection.
	 * @return mixed Unmodified accepted source, otherwise an empty rejection.
	 */
	public function pingback_source( $source ) {
		if ( 0 === $this->pingback_server || ! defined( 'XMLRPC_REQUEST' ) || true !== XMLRPC_REQUEST ) {
			return '';
		}
		if ( ! $this->allow_native_pings() ) {
			$this->reset_ping_intents();
			$this->guard->reject( 'comments', 'pingback' );
			return '';
		}
		$this->pingback_source = $this->ping_source( $source );
		return '' === $this->pingback_source ? '' : $source;
	}

	/**
	 * Arms one trackback only from Core's endpoint, bound to target and source.
	 *
	 * @since 0.3.0
	 * @param int    $post_id Native target post ID.
	 * @param string $source Native sanitized trackback source URL.
	 * @return void
	 */
	public function begin_trackback( $post_id, $source ): void {
		$this->reset_ping_intents();
		if ( doing_action( 'pre_trackback_post' ) && function_exists( 'trackback_response' ) && $this->allow_native_pings() && is_numeric( $post_id ) && (int) $post_id > 0 ) {
			$source = $this->ping_source( $source );
			if ( '' !== $source ) {
				$this->trackback_intent = array(
					'post'   => (int) $post_id,
					'source' => $source,
				);
			}
		}
	}

	/**
	 * Consumes the exact native protocol intent before any ordinary comment data copy.
	 *
	 * Every attempt consumes both intent types. No source, payload or accepted
	 * protocol entry enters our quarantine, guard counters or quality ledger.
	 *
	 * @since 0.3.0
	 * @param string $type Native pingback or trackback type.
	 * @param int    $post_id Actual target selected by Core.
	 * @param mixed  $source Actual native author/source URL.
	 * @return bool Whether Core owns the one permitted protocol submission.
	 */
	private function consume_native_ping( string $type, int $post_id, $source ): bool {
		$server    = $this->pingback_server;
		$pingback  = $this->pingback_source;
		$trackback = $this->trackback_intent;
		$this->reset_ping_intents();
		if ( ! $this->allow_native_pings() || $post_id < 1 ) {
			return false;
		}
		$source = $this->ping_source( $source );
		if ( 'pingback' === $type ) {
			return defined( 'XMLRPC_REQUEST' ) && true === XMLRPC_REQUEST && $server > 0 && '' !== $pingback && $source === $pingback;
		}
		return function_exists( 'trackback_response' ) && null !== $trackback && $post_id === $trackback['post'] && $source === $trackback['source'];
	}

	/**
	 * Enforces the effective option independently of the administration interface.
	 *
	 * @since 0.3.0
	 * @return bool Whether protocol handling is explicitly allowed outside privacy mode.
	 */
	private function allow_native_pings(): bool {
		$options = $this->settings->all();
		return ! empty( $options['allow_pingbacks'] ) && empty( $options['privacy_mode'] );
	}

	/**
	 * Compares bounded source URLs without storing a message or querying a source.
	 *
	 * @since 0.3.0
	 * @param mixed $source Native URL, possibly WordPress-slashed and entity encoded.
	 * @return string Comparable source, or empty for invalid shape/size.
	 */
	private function ping_source( $source ): string {
		return is_string( $source ) && strlen( $source ) <= 4096 ? html_entity_decode( wp_unslash( $source ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
	}

	/**
	 * Clears both request-local permits without retaining protocol contents.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	private function reset_ping_intents(): void {
		$this->pingback_server  = 0;
		$this->pingback_source  = '';
		$this->trackback_intent = null;
	}

	/**
	 * Selects only the public comment fields needed to review a rejected message.
	 *
	 * @since 0.2.0
	 * @param array $comment  Native comment data.
	 * @param bool  $slashed  Whether the native caller supplies WordPress slashes.
	 * @return array{fields:array<string,string>,email:string} Bounded selected data.
	 */
	private function comment_content( array $comment, bool $slashed ): array {
		$data = array(
			'fields' => array(),
			'email'  => '',
		);
		foreach ( array(
			'comment_content'      => 'content',
			'comment_author'       => 'name',
			'comment_author_email' => 'email',
			'comment_author_url'   => 'url',
		) as $key => $name ) {
			if ( isset( $comment[ $key ] ) && is_string( $comment[ $key ] ) ) {
				$value = $slashed ? wp_unslash( $comment[ $key ] ) : $comment[ $key ];
				$this->add_content( $data, $name, $value, 'email' === $name );
			}
		}

		return $data;
	}

	/**
	 * Recognizes Core's authenticated backend reply intent without a frontend bypass.
	 *
	 * Core binds this AJAX action to the actual actor, post-edit capability and
	 * its reply nonce. No other admin request, role or frontend form is exempted.
	 *
	 * @since 0.2.0
	 * @param array $comment Native comment data including the actor set by Core.
	 * @param int   $post_id Target native post.
	 * @return bool Whether this is the narrowly authenticated Core backend reply.
	 */
	private function authorized_backend_reply( array $comment, int $post_id ): bool {
		$actor = get_current_user_id();
		if ( ! wp_doing_ajax() || ! doing_action( 'wp_ajax_replyto-comment' ) || $actor <= 0 || ! isset( $comment['user_id'] ) || (int) $comment['user_id'] !== $actor || ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		$nonce = isset( $_POST['_ajax_nonce-replyto-comment'] ) && is_string( $_POST['_ajax_nonce-replyto-comment'] ) ? sanitize_text_field( wp_unslash( $_POST['_ajax_nonce-replyto-comment'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The exact native reply intent is verified on the following line.
		return '' !== $nonce && false !== wp_verify_nonce( $nonce, 'replyto-comment' );
	}
}
