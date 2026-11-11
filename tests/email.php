<?php
/**
 * Verifies localized digest content and native MIME without a mail transport.
 *
 * Requires the explicitly marked disposable site used by tests/bootstrap.php.
 * The native PHPMailer serializer runs, while its final transport is replaced
 * by a recorder. Hook, option, mailer and temporary locale state is restored.
 * No Core/provider records are queried directly and no mail leaves the fixture.
 *
 * @package RktskyAntispamTests
 * @since 0.5.4
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- This standalone native regression runner keeps its non-sending mailer and assertions together.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer exposes the original public Body, AltBody and Subject API.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- The isolated runner snapshots and restores the native mailer and hook registry exactly.

use Rktsky\Antispam\Digest;

// Core only offers installed locales to switch_to_locale(). Supply empty native
// catalogues before bootstrap, preserving any existing real language packs.
if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'RKTSKY_ANTISPAM_ALLOW_TESTS' ) ) {
	throw new RuntimeException( 'Explicit CLI test authorization is required.' );
}
$rktsky_email_root = realpath( (string) getenv( 'RKTSKY_ANTISPAM_WP_ROOT' ) );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads only the required local fixture marker before WordPress exists.
if ( ! $rktsky_email_root || ! is_file( $rktsky_email_root . '/.rktsky-antispam-fixture' ) || 'rktsky-antispam-disposable' !== trim( file_get_contents( $rktsky_email_root . '/.rktsky-antispam-fixture' ) ) ) {
	throw new RuntimeException( 'A marked disposable fixture is required before preparing test locales.' );
}
require_once $rktsky_email_root . '/wp-includes/pomo/mo.php';
$rktsky_email_locales = array( 'en_US', 'de_CH', 'de_DE', 'fr_FR', 'it_IT', 'es_ES' );
$rktsky_email_files   = array();
$rktsky_email_lang    = $rktsky_email_root . '/wp-content/languages';
$rktsky_email_mkdir   = ! is_dir( $rktsky_email_lang );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Native locale markers are created before WordPress filesystem APIs exist.
if ( $rktsky_email_mkdir && ! mkdir( $rktsky_email_lang, 0700, true ) ) {
	throw new RuntimeException( 'Cannot prepare disposable language markers.' );
}
foreach ( array_slice( $rktsky_email_locales, 1 ) as $rktsky_email_locale ) {
	$rktsky_email_file = $rktsky_email_lang . '/' . $rktsky_email_locale . '.mo';
	if ( ! file_exists( $rktsky_email_file ) ) {
		$rktsky_email_marker = new MO();
		$rktsky_email_marker->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
		if ( ! $rktsky_email_marker->export_to_file( $rktsky_email_file ) ) {
			throw new RuntimeException( 'Cannot export a disposable locale marker.' );
		}
		$rktsky_email_files[] = $rktsky_email_file;
	}
}
require __DIR__ . '/bootstrap.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';

/**
 * Serializes real MIME but never invokes mail(), sendmail or an SMTP socket.
 *
 * @since 0.5.4
 */
final class Rktsky_Antispam_Email_Recorder extends PHPMailer\PHPMailer\PHPMailer {

	/**
	 * Serialized native messages and their unencoded bodies.
	 *
	 * @since 0.5.4
	 * @var array<int,array{mime:string,html:string,text:string,subject:string,to:array,attachments:array}>
	 */
	public array $messages = array();

	/**
	 * Fixed internal failure mode; never selects a real transport.
	 *
	 * @since 0.5.4
	 * @var string
	 */
	public string $failure = '';

	/**
	 * Captures the result of native preSend() instead of performing delivery.
	 *
	 * @since 0.5.4
	 * @return bool Whether the synthetic mailer accepted the prepared MIME.
	 * @throws RuntimeException For the explicit unexpected-exception test.
	 * @throws PHPMailer\PHPMailer\Exception For the native failure test.
	 */
	public function postSend(): bool { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Overrides the original public PHPMailer transport boundary.
		if ( 'runtime' === $this->failure ) {
			throw new RuntimeException( 'Synthetic mail integration failure.' );
		}
		if ( 'native' === $this->failure ) {
			throw new PHPMailer\PHPMailer\Exception( 'Synthetic native mailer failure.' );
		}
		$this->messages[] = array(
			'mime'        => $this->getSentMIMEMessage(),
			'html'        => $this->Body,
			'text'        => $this->AltBody,
			'subject'     => $this->Subject,
			'to'          => $this->getToAddresses(),
			'attachments' => $this->getAttachments(),
		);
		return true;
	}
}

$rktsky_email_assertions = 0;

/**
 * Fails on a native behavior regression without exposing submission contents.
 *
 * @since 0.5.4
 * @param bool   $condition Required behavior.
 * @param string $rktsky_email_message Fixed test label.
 * @return void
 * @throws RuntimeException When a required native invariant fails.
 */
function rktsky_email_expect( bool $condition, string $rktsky_email_message ): void {
	++$GLOBALS['rktsky_email_assertions'];
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $rktsky_email_message ) );
	}
}

/**
 * Decodes PHPMailer's actual multipart parts using their declared encodings.
 *
 * @since 0.5.4
 * @param string $mime Native serialized message.
 * @return array<string,string> MIME type to decoded body, or an empty array.
 */
function rktsky_email_parts( string $mime ): array {
	if ( ! preg_match( '/boundary="([^"\r\n]+)"/', $mime, $boundary ) ) {
		return array();
	}
	$rktsky_email_parts = array();
	foreach ( explode( '--' . $boundary[1], $mime ) as $part ) {
		$segments = preg_split( '/\r?\n\r?\n/', ltrim( $part, "\r\n" ), 2 );
		if ( 2 !== count( $segments ) || ! preg_match( '/^Content-Type:\s*(text\/(?:plain|html))/mi', $segments[0], $type ) ) {
			continue;
		}
		$body = rtrim( $segments[1], "\r\n" );
		if ( preg_match( '/Content-Transfer-Encoding:\s*base64/i', $segments[0] ) ) {
			$body = base64_decode( $body, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes the original MIME serializer's declared transfer encoding.
		} elseif ( preg_match( '/Content-Transfer-Encoding:\s*quoted-printable/i', $segments[0] ) ) {
			$body = quoted_printable_decode( $body );
		}
		$rktsky_email_parts[ strtolower( $type[1] ) ] = str_replace( "\r\n", "\n", (string) $body );
	}
	return $rktsky_email_parts;
}

$rktsky_email_options = array();
foreach ( array( 'blogname', 'home', 'siteurl', 'admin_email' ) as $rktsky_email_name ) {
	$rktsky_email_options[ $rktsky_email_name ] = get_option( $rktsky_email_name );
}
$rktsky_email_hooks = array();
foreach ( array( 'pre_wp_mail', 'phpmailer_init', 'wp_mail_content_type', 'wp_mail' ) as $rktsky_email_hook ) {
	$rktsky_email_hooks[ $rktsky_email_hook ] = isset( $GLOBALS['wp_filter'][ $rktsky_email_hook ] ) ? clone $GLOBALS['wp_filter'][ $rktsky_email_hook ] : null;
}
$rktsky_email_previous = $GLOBALS['phpmailer'] ?? null;
$rktsky_email_mailer   = new Rktsky_Antispam_Email_Recorder( true );
$rktsky_email_locale   = get_locale();
$rktsky_email_switched = false;

try {
	// The fixture's global block stays snapshotted. All actual sends below can
	// only reach the non-transport recorder, including unrelated nested mail.
	remove_all_filters( 'pre_wp_mail' );
	remove_all_filters( 'phpmailer_init' );
	$GLOBALS['phpmailer'] = $rktsky_email_mailer;
	update_option( 'blogname', 'R&D / AT&T & "News"' );
	update_option( 'admin_email', 'digest-recipient@example.invalid' );
	update_option( 'home', 'https://website.example/site-a' );
	update_option( 'siteurl', 'https://website.example/site-a/wordpress' );
	$rktsky_email_expected_activity = admin_url( 'options-general.php?page=rocketsky-antispam#rktsky-activity-heading' );
	$rktsky_email_expected_settings = admin_url( 'options-general.php?page=rocketsky-antispam&tab=settings#notifications' );

	foreach ( $rktsky_email_locales as $rktsky_chosen_locale ) {
		$rktsky_email_switched = switch_to_locale( $rktsky_chosen_locale );
		rktsky_email_expect( get_locale() === $rktsky_chosen_locale, 'Every email locale must be selected through the native locale switcher: ' . $rktsky_chosen_locale );
		foreach ( array( 1, 5 ) as $rktsky_email_count ) {
			$rktsky_email_message = Digest::message( $rktsky_email_count, '10 November 2026', 'Europe/Zurich' );
			rktsky_email_expect( array( 'subject', 'html', 'text' ) === array_keys( $rktsky_email_message ), 'The digest must provide exactly the subject and two content alternatives.' );
			rktsky_email_expect( str_contains( $rktsky_email_message['subject'], 'R&D / AT&T & "News"' ) && ! preg_match( '/[\r\n]/', $rktsky_email_message['subject'] ), 'Plain site-title ampersands must survive without creating extra headers.' );
			rktsky_email_expect( str_contains( $rktsky_email_message['html'], 'R&amp;D / AT&amp;T &amp; &quot;News&quot;' ) && str_contains( $rktsky_email_message['text'], 'R&D / AT&T & "News"' ), 'HTML must escape the preserved title once while the text alternative remains readable.' );
			$rktsky_email_label = _n( 'Submission stopped', 'Submissions stopped', $rktsky_email_count, 'rocketsky-antispam' );
			rktsky_email_expect( str_contains( $rktsky_email_message['html'], esc_html( $rktsky_email_label ) ) && str_contains( $rktsky_email_message['text'], $rktsky_email_label ), 'Both alternatives must use the actual singular/plural translation.' );
			rktsky_email_expect( 'en_US' === $rktsky_chosen_locale || ( 'Submission stopped' !== $rktsky_email_label && 'Submissions stopped' !== $rktsky_email_label && ! str_contains( $rktsky_email_message['subject'], 'daily summary for' ) ), 'All supplied non-English mail catalogues must translate the subject and count label.' );
			rktsky_email_expect( str_contains( $rktsky_email_message['text'], $rktsky_email_expected_activity ) && str_contains( $rktsky_email_message['text'], $rktsky_email_expected_settings ) && str_contains( $rktsky_email_expected_settings, '/site-a/wordpress/wp-admin/' ), 'Native links must retain a site subdirectory and distinct WordPress installation directory.' );

			$rktsky_email_document        = new DOMDocument();
			$rktsky_email_previous_errors = libxml_use_internal_errors( true );
			$rktsky_email_loaded          = $rktsky_email_document->loadHTML( $rktsky_email_message['html'] );
			libxml_clear_errors();
			libxml_use_internal_errors( $rktsky_email_previous_errors );
			$rktsky_email_xpath = new DOMXPath( $rktsky_email_document );
			rktsky_email_expect( $rktsky_email_loaded && 1 === $rktsky_email_xpath->query( '//html' )->length && 1 === $rktsky_email_xpath->query( '//h1' )->length && get_bloginfo( 'language' ) === $rktsky_email_xpath->evaluate( 'string(//html/@lang)' ), 'The email must contain a full document with the native document language and one heading.' );
			rktsky_email_expect( 0 === $rktsky_email_xpath->query( '//script|//img|//iframe|//form|//input|//link|//style|//*[@src or @srcset or @background]|//*[@onload or @onclick or @onerror]' )->length && ! preg_match( '/url\s*\(|@import/i', $rktsky_email_message['html'] ), 'No scripts, trackers, remote assets, forms or CSS resource fetches may be embedded.' );
			rktsky_email_expect( 2 === $rktsky_email_xpath->query( '//a' )->length && 2 === $rktsky_email_xpath->query( '//a[@target="_blank" and @rel="noopener noreferrer"]' )->length && $rktsky_email_expected_activity === $rktsky_email_xpath->evaluate( 'string((//a)[1]/@href)' ) && $rktsky_email_expected_settings === $rktsky_email_xpath->evaluate( 'string((//a)[2]/@href)' ), 'Only the actual activity and settings links may be present, with safe new-window attributes.' );
			rktsky_email_expect( 0 === $rktsky_email_xpath->query( '//table[not(@role="presentation")]' )->length && ! preg_match( '/<(?:html|body|p|a|table)\b/i', $rktsky_email_message['text'] ), 'Layout tables must be presentational and the text alternative must contain no HTML layout.' );
			rktsky_email_expect( Digest::send( $rktsky_email_count, '10 November 2026', 'Europe/Zurich' ), 'Native wp_mail must accept the synthetic MIME handoff.' );
			$rktsky_email_sent  = end( $rktsky_email_mailer->messages );
			$rktsky_email_parts = rktsky_email_parts( $rktsky_email_sent['mime'] );
			rktsky_email_expect( str_contains( $rktsky_email_sent['mime'], 'multipart/alternative' ) && trim( $rktsky_email_message['html'] ) === trim( $rktsky_email_parts['text/html'] ?? '' ) && trim( $rktsky_email_message['text'] ) === trim( $rktsky_email_parts['text/plain'] ?? '' ), 'The actual native MIME must contain equivalent decoded HTML and plain-text parts.' );
			rktsky_email_expect( 'digest-recipient@example.invalid' === $rktsky_email_sent['to'][0][0] && 1 === count( $rktsky_email_sent['to'] ) && array() === $rktsky_email_sent['attachments'] && ! str_contains( $rktsky_email_sent['html'] . $rktsky_email_sent['text'], 'digest-recipient@example.invalid' ), 'Native mail must retain the site administrator recipient without attachments or extra recipients.' );
			rktsky_email_expect( '' === $rktsky_email_mailer->AltBody && false === has_filter( 'phpmailer_init' ) && ( null === $rktsky_email_hooks['wp_mail_content_type'] ? ! has_filter( 'wp_mail_content_type' ) : $GLOBALS['wp_filter']['wp_mail_content_type']->callbacks === $rktsky_email_hooks['wp_mail_content_type']->callbacks ), 'The digest must remove its hook and AltBody without installing a global content-type filter.' );
		}
		if ( $rktsky_email_switched ) {
			restore_previous_locale();
			$rktsky_email_switched = false;
		}
	}

	update_option( 'blogname', "<img src=x onerror=alert(1)>R&D\r\nBcc: intruder@example.invalid" );
	$rktsky_email_hostile = Digest::message( 5, '<script>synthetic-date</script>', '" onmouseover="synthetic-calendar' );
	rktsky_email_expect( ! preg_match( '/[\r\n]/', $rktsky_email_hostile['subject'] ) && ! str_contains( $rktsky_email_hostile['html'], '<img' ) && ! str_contains( $rktsky_email_hostile['html'], '<script>' ) && str_contains( $rktsky_email_hostile['html'], '&lt;script&gt;synthetic-date&lt;/script&gt;' ) && str_contains( $rktsky_email_hostile['html'], '&quot; onmouseover=&quot;synthetic-calendar' ), 'Hostile identity and reporting strings must remain text rather than executable HTML or injected headers.' );
	update_option( 'blogname', str_repeat( 'Überlang&R&D', 100 ) );
	$rktsky_email_long = Digest::message( PHP_INT_MAX, str_repeat( 'Long reporting date ', 20 ), 'Asia/Kathmandu' );
	rktsky_email_expect( mb_strlen( substr( $rktsky_email_long['subject'], strlen( 'Rocketsky Antispam: daily summary for ' ) ) ) <= 201 && str_contains( $rktsky_email_long['html'], 'overflow-wrap:anywhere' ) && strlen( $rktsky_email_long['html'] ) < 20000, 'Long site titles must be bounded with wrapping support and a bounded native message.' );
	update_option( 'blogname', '' );
	$rktsky_email_empty = Digest::message( 1, '10 November 2026', 'Europe/Zurich' );
	rktsky_email_expect( str_contains( $rktsky_email_empty['subject'], 'website.example' ), 'An empty title must fall back to the configured site host.' );

	/**
	 * Models another mail integration supplying its own explicit text alternative.
	 *
	 * @since 0.5.4
	 * @param PHPMailer\PHPMailer\PHPMailer $mailer Native message under preparation.
	 * @return void
	 */
	$rktsky_email_existing_alternative = static function ( PHPMailer\PHPMailer\PHPMailer $mailer ): void {
		$mailer->AltBody = 'Existing mail integration text alternative.';
	};
	add_action( 'phpmailer_init', $rktsky_email_existing_alternative, 10 );
	try {
		rktsky_email_expect( Digest::send( 5, '10 November 2026', 'Europe/Zurich' ), 'A digest with an existing native text alternative must still serialize.' );
		$rktsky_email_sent  = end( $rktsky_email_mailer->messages );
		$rktsky_email_parts = rktsky_email_parts( $rktsky_email_sent['mime'] );
		rktsky_email_expect( 'Existing mail integration text alternative.' === ( $rktsky_email_parts['text/plain'] ?? '' ) && 'Existing mail integration text alternative.' === $rktsky_email_mailer->AltBody, 'An earlier explicit text alternative must not be replaced or erased by the digest.' );
	} finally {
		remove_action( 'phpmailer_init', $rktsky_email_existing_alternative, 10 );
		$rktsky_email_mailer->AltBody = '';
	}

	foreach ( array( false, true, 1 ) as $rktsky_email_decision ) {
		$rktsky_email_before = count( $rktsky_email_mailer->messages );
		/**
		 * Models a native mail short circuit without allowing transport fallback.
		 *
		 * @since 0.5.4
		 * @return bool|int Explicit synthetic short-circuit value.
		 */
		$rktsky_email_short_circuit = static fn(): bool|int => $rktsky_email_decision;
		add_filter( 'pre_wp_mail', $rktsky_email_short_circuit, PHP_INT_MAX );
		try {
			rktsky_email_expect( Digest::send( 5, '10 November 2026', 'Europe/Zurich' ) === ( true === $rktsky_email_decision ) && count( $rktsky_email_mailer->messages ) === $rktsky_email_before && ! has_filter( 'phpmailer_init' ), 'Only literal true is acceptance; every pre_wp_mail short circuit must leave no MIME attempt or temporary hook.' );
		} finally {
			remove_filter( 'pre_wp_mail', $rktsky_email_short_circuit, PHP_INT_MAX );
		}
	}

	/**
	 * Models an unrelated integration throwing before mailer initialization.
	 *
	 * @since 0.5.4
	 * @return never
	 * @throws RuntimeException Always, without invoking a transport.
	 */
	$rktsky_email_throwing = static function (): never {
		throw new RuntimeException( 'Synthetic pre-mail failure.' );
	};
	add_filter( 'pre_wp_mail', $rktsky_email_throwing, PHP_INT_MAX );
	try {
		Digest::send( 1, '10 November 2026', 'Europe/Zurich' );
		throw new RuntimeException( 'A pre-mail exception was unexpectedly swallowed.' );
	} catch ( RuntimeException $exception ) {
		rktsky_email_expect( 'Synthetic pre-mail failure.' === $exception->getMessage() && ! has_filter( 'phpmailer_init' ), 'An early mail exception must propagate after temporary hooks are removed.' );
	} finally {
		remove_filter( 'pre_wp_mail', $rktsky_email_throwing, PHP_INT_MAX );
	}
	foreach ( array( 'native', 'runtime' ) as $rktsky_email_failure ) {
		$rktsky_email_mailer->failure = $rktsky_email_failure;
		try {
			$rktsky_email_accepted = Digest::send( 1, '10 November 2026', 'Europe/Zurich' );
			rktsky_email_expect( 'native' === $rktsky_email_failure && false === $rktsky_email_accepted, 'A native PHPMailer failure must be returned as a rejected handoff.' );
		} catch ( RuntimeException $exception ) {
			rktsky_email_expect( 'runtime' === $rktsky_email_failure && 'Synthetic mail integration failure.' === $exception->getMessage(), 'Unexpected transport integration exceptions must remain visible.' );
		} finally {
			$rktsky_email_mailer->failure = '';
		}
		rktsky_email_expect( '' === $rktsky_email_mailer->AltBody && ! has_filter( 'phpmailer_init' ), 'Both initialized failure paths must restore AltBody and remove the temporary hook.' );
	}

	// The native mailer is not reentrant once phpmailer_init has started. A
	// nested wp_mail-filter call is earlier and must remain an ordinary message.
	$rktsky_email_nested = false;
	/**
	 * Sends one unrelated plain-text message before the digest initializes.
	 *
	 * @since 0.5.4
	 * @param array $arguments Native outer or nested mail arguments.
	 * @return array Unchanged original arguments.
	 */
	$rktsky_email_nested_mail = static function ( array $arguments ) use ( &$rktsky_email_nested ): array {
		if ( ! $rktsky_email_nested ) {
			$rktsky_email_nested = true;
			wp_mail( 'nested@example.invalid', 'Unrelated nested mail', 'Plain nested body.' );
		}
		return $arguments;
	};
	add_filter( 'wp_mail', $rktsky_email_nested_mail );
	try {
		$rktsky_email_before = count( $rktsky_email_mailer->messages );
		rktsky_email_expect( Digest::send( 5, '10 November 2026', 'Europe/Zurich' ), 'A nested early WordPress mail must not prevent the outer digest.' );
		$rktsky_email_nested_message = $rktsky_email_mailer->messages[ $rktsky_email_before ];
		rktsky_email_expect( count( $rktsky_email_mailer->messages ) === $rktsky_email_before + 2 && 'Plain nested body.' === $rktsky_email_nested_message['html'] && '' === $rktsky_email_nested_message['text'] && ! str_contains( $rktsky_email_nested_message['mime'], 'multipart/alternative' ), 'An unrelated nested plain-text mail must receive neither the digest HTML nor its AltBody.' );
	} finally {
		remove_filter( 'wp_mail', $rktsky_email_nested_mail );
	}
	rktsky_email_expect( wp_mail( 'ordinary@example.invalid', 'Ordinary next message', 'Ordinary body.' ), 'The following ordinary WordPress message must remain sendable.' );
	$rktsky_email_ordinary = end( $rktsky_email_mailer->messages );
	rktsky_email_expect( 'Ordinary body.' === $rktsky_email_ordinary['html'] && '' === $rktsky_email_ordinary['text'] && str_contains( $rktsky_email_ordinary['mime'], 'Content-Type: text/plain' ) && ! str_contains( $rktsky_email_ordinary['mime'], 'multipart/alternative' ) && ! str_contains( $rktsky_email_ordinary['mime'], 'Rocketsky Antispam' ), 'The next ordinary mail must remain plain text with no leaked digest content or MIME alternative.' );

	if ( is_multisite() ) {
		$rktsky_email_sites = get_sites(
			array(
				'number' => 2,
				'fields' => 'ids',
			)
		);
		foreach ( $rktsky_email_sites as $rktsky_email_site_id ) {
			switch_to_blog( (int) $rktsky_email_site_id );
			try {
				$rktsky_email_message = Digest::message( 1, '10 November 2026', 'Europe/Zurich' );
				rktsky_email_expect( str_contains( $rktsky_email_message['text'], admin_url( 'options-general.php?page=rocketsky-antispam&tab=settings#notifications' ) ), 'A switched network site must use its own native administrator URL.' );
			} finally {
				restore_current_blog();
			}
		}
	}
} finally {
	if ( $rktsky_email_switched ) {
		restore_previous_locale();
	}
	foreach ( $rktsky_email_options as $rktsky_email_name => $rktsky_email_value ) {
		update_option( $rktsky_email_name, $rktsky_email_value );
	}
	foreach ( $rktsky_email_hooks as $rktsky_email_hook => $rktsky_email_previous ) {
		if ( null === $rktsky_email_previous ) {
			unset( $GLOBALS['wp_filter'][ $rktsky_email_hook ] );
		} else {
			$GLOBALS['wp_filter'][ $rktsky_email_hook ] = $rktsky_email_previous;
		}
	}
	if ( null === $rktsky_email_previous ) {
		unset( $GLOBALS['phpmailer'] );
	} else {
		$GLOBALS['phpmailer'] = $rktsky_email_previous;
	}
	foreach ( $rktsky_email_files as $rktsky_email_file ) {
		wp_delete_file( $rktsky_email_file );
	}
	if ( $rktsky_email_mkdir ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only the empty directory created by this isolated runner.
		rmdir( $rktsky_email_lang );
	}
}
foreach ( $rktsky_email_options as $rktsky_email_name => $rktsky_email_value ) {
	rktsky_email_expect( get_option( $rktsky_email_name ) === $rktsky_email_value, 'Every changed native option must be restored exactly.' );
}
	rktsky_email_expect( get_locale() === $rktsky_email_locale && ( $GLOBALS['phpmailer'] ?? null ) === $rktsky_email_previous, 'Native locale and the original global mailer must be restored.' );
echo 'PASS: ' . esc_html( (string) $rktsky_email_assertions ) . " native localized digest and non-sending MIME assertions.\n";
