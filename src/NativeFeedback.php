<?php
/**
 * Connects observed provider entries to native moderation and privacy lifecycles.
 *
 * @package RktskyAntispam
 * @since 0.5.0
 */

namespace Rktsky\Antispam;

defined( 'ABSPATH' ) || exit;

/**
 * Removes source links on native content changes without retaining field values.
 *
 * Only adapters create observations. These callbacks never import historical
 * entries into the dataset. Provider reads use their public entry/privacy APIs.
 *
 * @since 0.5.0
 */
final class NativeFeedback {

	/**
	 * Existing owned prediction ledger; provider storage stays provider-owned.
	 *
	 * @since 0.5.0
	 * @var Feedback
	 */
	private Feedback $feedback;

	/**
	 * Original native exporter callback, retained only for the current request.
	 *
	 * @since 0.5.0
	 * @var callable|null
	 */
	private $ws_exporter = null;

	/**
	 * A sparse native search page may require another page without any exact match.
	 *
	 * @since 0.5.0
	 * @var bool
	 */
	private bool $more = false;

	/**
	 * Shares the already configured prediction service without querying providers.
	 *
	 * @since 0.5.0
	 * @param Feedback $feedback Owned metadata service.
	 * @return void
	 */
	public function __construct( Feedback $feedback ) {
		$this->feedback = $feedback;
	}

	/**
	 * Registers native lifecycle hooks and augments existing WordPress privacy APIs.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function register(): void {
		add_action( 'frm_entry_marked_spam', array( $this, 'formidable_spam' ) );
		add_action( 'frm_entry_marked_not_spam', array( $this, 'formidable_normal' ) );
		add_action( 'frm_after_destroy_entry', array( $this, 'forget_formidable' ) );
		add_action( 'frm_after_update_entry', array( $this, 'forget_formidable' ) );
		add_action( 'frm_after_entry_meta_change', array( $this, 'forget_formidable' ) );
		add_action( 'fluentform/after_submission_status_update', array( $this, 'fluent_review' ), 10, 2 );
		add_action( 'fluentform/after_deleting_submissions', array( $this, 'forget_fluent' ) );
		add_action( 'fluentform/submission_user_changed', array( $this, 'fluent_user_changed' ) );
		add_action( 'fluentform/before_form_delete', array( $this, 'forget_fluent_form' ) );
		add_action( 'fluentform/before_updating_form', array( $this, 'fluent_form_update' ), 10, 2 );
		add_action( 'wsf_submit_status', array( $this, 'ws_review' ), 10, 2 );
		add_action( 'wsf_submit_delete_permanent', array( $this, 'forget_ws' ) );
		add_action( 'wsf_submit_update', array( $this, 'forget_ws' ) );
		add_action( 'wsf_form_delete', array( $this, 'forget_ws_form' ) );
		add_filter( 'rktsky_antispam_feedback_can_review', array( $this, 'can_review' ), 10, 3 );
		add_filter( 'rktsky_antispam_feedback_reference', array( $this, 'reference' ), 10, 2 );
		add_filter( 'rest_dispatch_request', array( $this, 'ws_editor' ), PHP_INT_MAX, 4 );
		add_filter( 'rktsky_antispam_feedback_subjects', array( $this, 'subjects' ), 10, 3 );
		add_filter( 'rktsky_antispam_feedback_subjects_done', array( $this, 'subjects_done' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ), PHP_INT_MAX );
	}

	/**
	 * Labels only existing predictions after a native moderator marks spam.
	 *
	 * @since 0.5.0
	 * @param int $id Native Formidable entry ID.
	 * @return void
	 */
	public function formidable_spam( $id ): void {
		$this->feedback->review( 'formidable', $id, true );
	}

	/**
	 * Applies a native not-spam correction without changing the original prediction.
	 *
	 * @since 0.5.0
	 * @param int $id Restored native Formidable entry ID.
	 * @return void
	 */
	public function formidable_normal( $id ): void {
		$this->feedback->review( 'formidable', $id, false );
	}

	/**
	 * Detaches deleted, edited or natively anonymized Formidable content.
	 *
	 * Initial field writes happen before the adapter tracks the persisted entry.
	 * Unknown IDs are harmless; this callback cannot create an observation.
	 *
	 * @since 0.5.0
	 * @param int $id Native entry affected by a completed provider write.
	 * @return void
	 */
	public function forget_formidable( $id ): void {
		$this->feedback->forget( 'formidable', $id );
	}

	/**
	 * Classifies native spam/read/unread changes after a scoped authorized action.
	 *
	 * Other statuses, including trash and favourite flags, are not human spam labels.
	 *
	 * @since 0.5.0
	 * @param int    $id Actually updated native Fluent submission ID.
	 * @param string $status Final native status.
	 * @return void
	 */
	public function fluent_review( $id, string $status ): void {
		$observation = $this->feedback->status( 'fluentforms', $id );
		if ( 'spam' === $status || ( in_array( $status, array( 'read', 'unread' ), true ) && $observation && ( $observation['predicted_spam'] || true === $observation['actual_spam'] ) ) ) {
			$this->feedback->review( 'fluentforms', $id, 'spam' === $status );
		}
	}

	/**
	 * Detaches the exact IDs reported after native single or bulk deletion.
	 *
	 * @since 0.5.0
	 * @param int[]|int $ids Native IDs actually passed through provider deletion.
	 * @return void
	 */
	public function forget_fluent( $ids ): void {
		foreach ( (array) $ids as $id ) {
			$this->feedback->forget( 'fluentforms', $id );
		}
	}

	/**
	 * Detaches a changed native subject association before later privacy lookups.
	 *
	 * @since 0.5.0
	 * @param object $entry Native Fluent submission whose associated user changed.
	 * @return void
	 */
	public function fluent_user_changed( $entry ): void {
		if ( is_object( $entry ) && isset( $entry->id ) ) {
			$this->feedback->forget( 'fluentforms', (int) $entry->id );
		}
	}

	/**
	 * Detaches form-owned links before Fluent removes rows without per-entry hooks.
	 *
	 * Native fifty-row pages are read without mutating provider content. Only IDs
	 * are used; source deletion begins after this callback and cannot shift offsets.
	 *
	 * @since 0.5.0
	 * @param int $form_id Authoritative native form scheduled for deletion.
	 * @return void
	 */
	public function forget_fluent_form( $form_id ): void {
		if ( ! function_exists( 'fluentFormApi' ) ) {
			return;
		}
		$page = 1;
		do {
			$result = fluentFormApi( 'submissions' )->get(
				array(
					'form_ids'   => array( (int) $form_id ),
					'per_page'   => 50,
					'page'       => $page,
					'sort_type'  => 'ASC',
					'entry_type' => 'all',
				)
			);
			$rows   = $result['data'] ?? array();
			foreach ( $rows as $entry ) {
				$this->feedback->forget( 'fluentforms', (int) $entry->id );
			}
			$more = count( $rows ) >= 50;
			++$page;
		} while ( $more );
	}

	/**
	 * Detaches before a form edit removes or renames native privacy email fields.
	 *
	 * Visual form changes preserve feedback. A changed identification-field set
	 * would make older responses unreachable through the native privacy mapping.
	 *
	 * @since 0.5.0
	 * @param object $form Original stored form before the native update.
	 * @param array  $data Native sanitized replacement values.
	 * @return void
	 */
	public function fluent_form_update( $form, array $data ): void {
		if ( is_object( $form ) && isset( $form->id, $form->form_fields, $data['form_fields'] ) && $this->fluent_email_names( $form->form_fields ) !== $this->fluent_email_names( $data['form_fields'] ) ) {
			$this->forget_fluent_form( (int) $form->id );
		}
	}

	/**
	 * Reads only native email input names from stored/sanitized form structures.
	 *
	 * @since 0.5.0
	 * @param mixed $definition Native JSON or decoded form definition.
	 * @return string[] Sorted unique email input names, without labels or values.
	 */
	private function fluent_email_names( $definition ): array {
		$decoded = is_string( $definition ) ? json_decode( $definition, true ) : $definition;
		$pending = is_array( $decoded ) ? array( $decoded ) : array();
		$names   = array();
		while ( $pending ) {
			$item = array_pop( $pending );
			if ( 'input_email' === ( $item['element'] ?? '' ) && is_string( $item['attributes']['name'] ?? null ) ) {
				$names[] = $item['attributes']['name'];
			}
			foreach ( $item as $value ) {
				if ( is_array( $value ) ) {
					$pending[] = $value;
				}
			}
		}
		$names = array_values( array_unique( $names ) );
		sort( $names, SORT_STRING );
		return $names;
	}

	/**
	 * Detaches before WS removes field definitions needed by native privacy export.
	 *
	 * The native export service accepts structured status filters and bounded pages;
	 * this adapter constructs no SQL. Form deletion otherwise preserves submissions
	 * after removing their fields, making an email export unreliable for those rows.
	 *
	 * @since 0.5.0
	 * @param int $form_id Existing native form about to be permanently deleted.
	 * @return void
	 */
	public function forget_ws_form( $form_id ): void {
		if ( ! class_exists( '\WS_Form_Submit_Export' ) ) {
			return;
		}
		$export = new \WS_Form_Submit_Export( (int) $form_id );
		foreach ( array( 'publish', 'draft', 'spam', 'trash', 'error' ) as $status ) {
			$offset = 0;
			do {
				$ids = $export->get_ids(
					50,
					$offset,
					'',
					array(
						array(
							'field'    => 'status',
							'operator' => '==',
							'value'    => $status,
						),
					),
					'id',
					'ASC'
				);
				foreach ( $ids as $id ) {
					$this->feedback->forget( 'wsform', (int) $id );
				}
				$more    = count( $ids ) >= 50;
				$offset += 50;
			} while ( $more );
		}
	}

	/**
	 * Uses only explicit native WS spam/submitted transitions as human labels.
	 *
	 * @since 0.5.0
	 * @param int    $id Updated native submission ID.
	 * @param string $status Final native status; not_spam is normalized to publish.
	 * @return void
	 */
	public function ws_review( $id, string $status ): void {
		$observation = $this->feedback->status( 'wsform', $id );
		if ( 'spam' === $status || ( 'publish' === $status && $observation && ( $observation['predicted_spam'] || true === $observation['actual_spam'] ) ) ) {
			$this->feedback->review( 'wsform', $id, 'spam' === $status );
		}
	}

	/**
	 * Removes links after native WS content updates or successful permanent deletion.
	 *
	 * Native privacy erasure and automatic spam purging emit the same deletion
	 * action. Original creation updates finish before the adapter starts tracking.
	 *
	 * @since 0.5.0
	 * @param object $entry Native submission object containing the affected ID.
	 * @return void
	 */
	public function forget_ws( $entry ): void {
		if ( is_object( $entry ) && isset( $entry->id ) ) {
			$reference = isset( $entry->hash ) && is_string( $entry->hash ) && 1 === preg_match( '/^[a-f0-9]{32}$/D', $entry->hash ) ? $entry->hash : (int) $entry->id;
			$this->feedback->forget( 'wsform', $reference );
		}
	}

	/**
	 * Resolves authorized WS numeric IDs to the native hash used by its privacy API.
	 *
	 * Adapter and deletion callbacks already hold the authoritative hash. Numeric
	 * references are accepted only through the provider's capability-checked read;
	 * no hash is invented when that object no longer exists or is inaccessible.
	 *
	 * @since 0.5.0
	 * @param mixed  $id Existing native ID or opaque hash.
	 * @param string $source Native provider category.
	 * @return mixed Original non-WS reference, resolved native hash or empty failure.
	 */
	public function reference( $id, string $source ) {
		if ( 'wsform' !== $source || ( is_string( $id ) && 1 === preg_match( '/^[a-f0-9]{32}$/D', $id ) ) ) {
			return $id;
		}
		if ( ! ( is_int( $id ) || is_string( $id ) ) || 1 !== preg_match( '/^[1-9][0-9]{0,19}$/D', (string) $id ) || ! class_exists( '\WS_Form_Submit' ) ) {
			return '';
		}
		try {
			$reader     = new \WS_Form_Submit();
			$reader->id = (int) $id;
			$entry      = $reader->db_read( false, false );
			return is_string( $entry->hash ?? null ) && 1 === preg_match( '/^[a-f0-9]{32}$/D', $entry->hash ) ? $entry->hash : '';
		} catch ( \Throwable $error ) {
			return '';
		}
	}

	/**
	 * Detaches before the native WS editor can replace content or its stable hash.
	 *
	 * The original editor uses db_update_from_object, which omits its regular update
	 * action. Core invokes this gate after native permission checks. A failed later
	 * edit may conservatively remove the reference, never retain a stale linkage.
	 *
	 * @since 0.5.0
	 * @param mixed            $result Earlier native dispatch result, retained unchanged.
	 * @param \WP_REST_Request $request Authorized native REST request.
	 * @param string           $route Matched native route expression.
	 * @param array            $handler Actual endpoint callbacks selected by Core.
	 * @return mixed Earlier dispatch result without changing provider behavior.
	 */
	public function ws_editor( $result, \WP_REST_Request $request, string $route, array $handler ) {
		$callback = $handler['callback'] ?? null;
		if ( null !== $result || 'POST' !== $request->get_method() || ! defined( 'WS_FORM_RESTFUL_NAMESPACE' ) || ! is_array( $callback ) || ! isset( $callback[0], $callback[1] ) || ! $callback[0] instanceof \WS_Form_API_Submit || 'api_put' !== $callback[1] || 1 !== preg_match( '#^/' . preg_quote( WS_FORM_RESTFUL_NAMESPACE, '#' ) . '/submit/([1-9][0-9]*)/put/?$#D', $request->get_route(), $matches ) ) {
			return $result;
		}
		$object = \WS_Form_Common::get_query_var_nonce( 'submit', false, $request );
		if ( is_object( $object ) && isset( $object->id ) && ( is_int( $object->id ) || is_string( $object->id ) ) && 1 === preg_match( '/^[1-9][0-9]{0,19}$/D', (string) $object->id ) ) {
			$this->feedback->forget( 'wsform', $object->id );
		}
		return $result;
	}

	/**
	 * Accepts native moderator capabilities without widening other source policies.
	 *
	 * Fluent's ACL is evaluated against the authoritative saved entry's form, not
	 * an HTTP parameter. A read-only entry viewer cannot classify predictions.
	 *
	 * @since 0.5.0
	 * @param bool       $allowed Existing site-administrator authorization.
	 * @param string     $source Native provider category.
	 * @param int|string $id Native entry identifier.
	 * @return bool Whether the current native actor may moderate that source.
	 */
	public function can_review( bool $allowed, string $source, $id ): bool {
		if ( $allowed ) {
			return true;
		}
		if ( 'formidable' === $source ) {
			return is_callable( array( '\FrmSpamEntriesController', 'current_user_can_moderate' ) ) && \FrmSpamEntriesController::current_user_can_moderate();
		}
		if ( 'wsform' === $source && class_exists( '\WS_Form_Common' ) ) {
			return \WS_Form_Common::can_user( 'edit_submission' );
		}
		if ( 'fluentforms' !== $source || ! function_exists( 'fluentFormApi' ) || ! class_exists( '\FluentForm\App\Modules\Acl\Acl' ) ) {
			return false;
		}
		try {
			$entry = fluentFormApi( 'submissions' )->find( (int) $id );
			return is_object( $entry ) && \FluentForm\App\Modules\Acl\Acl::hasPermission( 'fluentform_manage_entries', (int) $entry->form_id );
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	/**
	 * Maps privacy subjects through existing native exporters and paginated APIs.
	 *
	 * Only request-local IDs survive the call; messages and email addresses never
	 * enter the ledger. Formidable's native page contains at most 400 distinct IDs.
	 * Fluent's fifty-row approximate search is checked against declared email fields.
	 *
	 * @since 0.5.0
	 * @param array<string,int[]> $sources Existing subject IDs by native category.
	 * @param string              $email Confirmed email supplied by WordPress privacy tools.
	 * @param int                 $page Positive native privacy page.
	 * @return array<string,int[]> Existing sources and supported native subject pages.
	 */
	public function subjects( array $sources, string $email, int $page ): array {
		$this->more = false;
		if ( ! is_email( $email ) || $page < 1 || ! ( current_user_can( 'export_others_personal_data' ) || current_user_can( 'erase_others_personal_data' ) ) ) {
			return $sources;
		}
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reads the existing documented Core exporter registry, including the provider's own subject mapping.
		$callback  = $exporters['formidable-exporter']['callback'] ?? null;
		if ( is_callable( $callback ) ) {
			$result                = call_user_func( $callback, $email, $page );
			$this->more            = $this->more || ( is_array( $result ) && empty( $result['done'] ) );
			$sources['formidable'] = array();
			foreach ( is_array( $result ) ? ( $result['data'] ?? array() ) : array() as $item ) {
				if ( is_array( $item ) && 1 === preg_match( '/^entry-([1-9][0-9]*)$/D', (string) ( $item['item_id'] ?? '' ), $matches ) ) {
					$sources['formidable'][] = (int) $matches[1];
				}
			}
		}
		if ( function_exists( 'fluentFormApi' ) && class_exists( '\FluentForm\App\Api\Submission' ) ) {
			$sources['fluentforms'] = $this->fluent_subjects( $email, $page );
		}
		return $sources;
	}

	/**
	 * Keeps privacy pagination open when a native approximate search has more rows.
	 *
	 * @since 0.5.0
	 * @param bool $done Whether all other subject sources have completed.
	 * @return bool Whether this callback also reached the final native search page.
	 */
	public function subjects_done( bool $done ): bool {
		return $done && ! $this->more;
	}

	/**
	 * Resolves exact email-field matches inside one native Fluent search page.
	 *
	 * @since 0.5.0
	 * @param string $email Confirmed privacy subject email.
	 * @param int    $page Native page number.
	 * @return int[] Exact native subject IDs; unrelated free-text matches are excluded.
	 */
	private function fluent_subjects( string $email, int $page ): array {
		$result     = fluentFormApi( 'submissions' )->get(
			array(
				'search'     => $email,
				'per_page'   => 50,
				'page'       => $page,
				'sort_type'  => 'ASC',
				'entry_type' => 'all',
			)
		);
		$rows       = $result['data'] ?? array();
		$this->more = $this->more || count( $rows ) >= 50;
		$ids        = array();
		$fields     = array();
		foreach ( $rows as $entry ) {
			$form_id = (int) $entry->form_id;
			if ( ! isset( $fields[ $form_id ] ) ) {
				$fields[ $form_id ] = fluentFormApi( 'forms' )->form( $form_id )->inputs();
			}
			foreach ( $fields[ $form_id ] as $name => $field ) {
				$value = $entry->response[ $name ] ?? null;
				if ( 'input_email' === ( $field['element'] ?? '' ) && is_string( $value ) && 0 === strcasecmp( trim( $value ), $email ) ) {
					$ids[] = (int) $entry->id;
					break;
				}
			}
		}
		return $ids;
	}

	/**
	 * Decorates WS's existing exporter without running an additional full-email scan.
	 *
	 * WS's current native exporter is unpaginated. Its original result is used once;
	 * no private field is cached and no alternate provider query is introduced.
	 *
	 * @since 0.5.0
	 * @param array $exporters Registered WordPress privacy exporters.
	 * @return array Same exporters with the recognized native WS callback decorated.
	 */
	public function exporters( array $exporters ): array {
		$callback = $exporters['ws-form']['callback'] ?? null;
		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) && $callback[0] instanceof \WS_Form_Action_Data_Export_Request && 'exporter' === $callback[1] ) {
			$this->ws_exporter                = $callback;
			$exporters['ws-form']['callback'] = array( $this, 'ws_export' );
		}
		return $exporters;
	}

	/**
	 * Appends existing labels to the exact native WS privacy export items.
	 *
	 * The native hash directly addresses its source-separated HMAC observation,
	 * including native trash entries. No extra provider read or token validation
	 * occurs. Native erasure removes references through its permanent-delete hook.
	 *
	 * @since 0.5.0
	 * @param string $email Confirmed subject email supplied to the original exporter.
	 * @param int    $page Native exporter page, passed through unchanged.
	 * @return mixed Unchanged native failure or native data with classification metadata.
	 */
	public function ws_export( string $email, int $page = 1 ) {
		if ( ! is_callable( $this->ws_exporter ) || ! current_user_can( 'export_others_personal_data' ) ) {
			return new \WP_Error( 'rktsky_antispam_feedback_permission', __( 'You cannot export this personal data.', 'wp-antispam' ) );
		}
		$result = call_user_func( $this->ws_exporter, $email, $page );
		if ( ! is_array( $result ) || ! isset( $result['data'] ) || ! is_array( $result['data'] ) ) {
			return $result;
		}
		$metadata = array();
		foreach ( $result['data'] as $item ) {
			if ( ! is_array( $item ) || 1 !== preg_match( '/^ws-form-([a-f0-9]{32})$/D', (string) ( $item['item_id'] ?? '' ), $matches ) ) {
				continue;
			}
			$label = $this->feedback->export_item( 'wsform', $matches[1] );
			if ( null !== $label ) {
				$metadata[] = $label;
			}
		}
		$result['data'] = array_merge( $result['data'], $metadata );
		return $result;
	}
}
