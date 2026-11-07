<?php
/**
 * Exercises real local policy classes with an isolated in-memory option stub.
 *
 * No WordPress fixture, database, mail or HTTP route is used or changed.
 *
 * @package RktskyAntispamTests
 * @since 0.2.0
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ );
$GLOBALS['rktsky_rules_options'] = array();
$GLOBALS['rktsky_rules_count'] = 0;

/**
 * Supplies settings solely from this process's synthetic in-memory option.
 *
 * @since 0.2.0
 * @param string $name Option name.
 * @param mixed  $default Fallback value.
 * @return mixed Synthetic option or fallback.
 */
function get_option( string $name, $default = false ) {
	return 'rktsky_antispam_settings' === $name ? $GLOBALS['rktsky_rules_options'] : $default;
}

/**
 * Provides tag stripping for the isolated policy contract.
 *
 * @since 0.2.0
 * @param string $text Fixture text.
 * @return string Text without HTML tags.
 */
function wp_strip_all_tags( string $text ): string { return strip_tags( $text ); }

/**
 * Sanitizes synthetic numeric address/header text without a WordPress bootstrap.
 *
 * @since 0.2.0
 * @param string $text Synthetic request value.
 * @return string Plain text.
 */
function sanitize_text_field( string $text ): string { return trim( strip_tags( $text ) ); }

/**
 * Removes WordPress slashes from synthetic scalar request values.
 *
 * @since 0.2.0
 * @param string $text Synthetic slashed value.
 * @return string Unslashed value.
 */
function wp_unslash( string $text ): string { return stripslashes( $text ); }

/**
 * Keeps valid synthetic UTF-8 and declines malformed fixture strings.
 *
 * @since 0.2.0
 * @param string $text Fixture text.
 * @param bool   $strip Whether malformed data may be removed.
 * @return string Valid text or an empty value for invalid fixture bytes.
 */
function wp_check_invalid_utf8( string $text, bool $strip = false ): string {
	return 1 === preg_match( '//u', $text ) ? $text : '';
}

/**
 * Reports a failed observable policy result without reading website settings.
 *
 * @since 0.2.0
 * @param bool   $condition Expected result.
 * @param string $message Failure description.
 * @return void
 */
function rktsky_rules_expect( bool $condition, string $message ): void {
	++$GLOBALS['rktsky_rules_count'];
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Builds real policy services against this process's synthetic option values.
 *
 * @since 0.2.0
 * @param array $options Synthetic administrator settings.
 * @return Rktsky\Antispam\ContentRules Policy service under test.
 */
function rktsky_rules_fixture( array $options ): Rktsky\Antispam\ContentRules {
	$GLOBALS['rktsky_rules_options'] = $options;
	$settings = new Rktsky\Antispam\Settings();
	return new Rktsky\Antispam\ContentRules( $settings, new Rktsky\Antispam\Language(), new Rktsky\Antispam\Country( $settings ) );
}

foreach ( array( 'Providers', 'Settings', 'Language', 'Country', 'ContentRules' ) as $class ) {
	require dirname( __DIR__ ) . '/src/' . $class . '.php';
}

$rules = rktsky_rules_fixture( array() );
rktsky_rules_expect( '' === $rules->check( 'comments', array( 'content' => 'Ordinary synthetic message.' ) ), 'Defaults must not add an implicit content rejection.' );
$rules = rktsky_rules_fixture( array( 'spam_keywords' => 'test.word' ) );
rktsky_rules_expect( 'keyword' === $rules->check( 'cf7', array( 'message' => 'A TEST.WORD match' ) ) && '' === $rules->check( 'cf7', array( 'message' => 'testXword' ) ), 'Keyword rules must be case-insensitive literal text, not administrator-supplied regex.' );
$rules = rktsky_rules_fixture( array( 'spam_keywords' => "offer\nüber" ) );
rktsky_rules_expect( 'keyword' === $rules->check( 'comments', array( 'content' => 'An of<b>fe</b>r' ) ), 'HTML fragments must not hide a configured literal keyword.' );
rktsky_rules_expect( 'keyword' === $rules->check( 'comments', array( 'content' => 'An of&#102;er' ) ), 'Visible HTML entities must not hide a configured keyword.' );
rktsky_rules_expect( 'keyword' === $rules->check( 'wpforms', array( 'message' => 'ÜBER this subject' ) ), 'Literal matching must support UTF-8 case folding.' );
$rules = rktsky_rules_fixture( array( 'bbcode_filter' => true ) );
rktsky_rules_expect( 'bbcode' === $rules->check( 'gravityforms', array( 'message' => '[url=https://example.invalid/]Example[/url]' ) ), 'Configured BBCode URL tags must reject.' );
rktsky_rules_expect( 'bbcode' === $rules->check( 'comments', array( 'content' => '&#91;url=https://example.invalid/&#93;Example[/url]' ) ), 'Entity-encoded visible URL tags must not bypass the configured BBCode rule.' );
rktsky_rules_expect( '' === $rules->check( 'gravityforms', array( 'message' => '[b]An ordinary formatting example[/b]' ) ), 'The BBCode option must not reject unrelated formatting tags.' );
$rules = rktsky_rules_fixture( array( 'spam_patterns' => '^buy\s+now$' ) );
rktsky_rules_expect( 'pattern' === $rules->check( 'cf7', array( 'message' => 'BUY   NOW' ) ) && '' === $rules->check( 'cf7', array( 'message' => 'A normal message' ) ), 'Configured regex bodies must retain their explicit matching semantics.' );
$rules = rktsky_rules_fixture( array( 'spam_patterns' => "[invalid\nunsafe~delimiter" ) );
rktsky_rules_expect( '' === $rules->check( 'comments', array( 'content' => 'A normal message' ) ), 'Invalid administrator patterns must not become implicit matches.' );
$rules = rktsky_rules_fixture( array( 'spam_patterns' => '(a+)+$' ) );
$start = hrtime( true );
$verdict = $rules->check( 'cf7', array( 'message' => str_repeat( 'a', 8000 ) . '!' ) );
rktsky_rules_expect( 'pattern_limit' === $verdict && ( hrtime( true ) - $start ) < 500000000, 'A pathological configured pattern must terminate and reject technically under the resource-limited subject contract.' );

$english = 'The readers would appreciate this article because they have more information about what should happen when their questions are answered.';
$german = 'Wir haben diese Frage und können mit dieser Antwort auch eine andere Lösung finden, wenn die Informationen für uns noch nicht klar sind.';
$rules = rktsky_rules_fixture( array( 'allowed_languages' => array( 'de' ) ) );
rktsky_rules_expect( 'language' === $rules->check( 'comments', array( 'content' => $english ) ) && '' === $rules->check( 'comments', array( 'content' => $german ) ), 'Comment language policy must use the actual local classifier.' );
rktsky_rules_expect( '' === $rules->check( 'cf7', array( 'message' => $english ) ), 'Language restriction must not silently affect contact forms.' );
rktsky_rules_expect( '' === $rules->check( 'comments', array( 'name' => $english, 'url' => $english, 'content' => 'Danke!' ) ), 'Unknown short comments must stay allowed by default and author metadata must not determine their language.' );
$rules = rktsky_rules_fixture( array( 'allowed_languages' => array( 'de' ), 'unknown_language_allowed' => false ) );
rktsky_rules_expect( 'language' === $rules->check( 'comments', array( 'content' => 'Danke!' ) ), 'Explicitly rejecting unknown language must remain an administrator policy.' );

$_SERVER['REMOTE_ADDR'] = '192.0.2.5';
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'CH';
$country_settings = array( 'privacy_mode' => false, 'country_source' => 'trusted_header', 'trusted_proxies' => '192.0.2.0/24', 'country_mode' => 'block', 'countries' => array( 'CH' ) );
$rules = rktsky_rules_fixture( $country_settings );
rktsky_rules_expect( 'country' === $rules->check( 'comments', array( 'content' => 'Synthetic country policy test' ) ) && '' === $rules->check( 'wpforms', array( 'message' => 'Synthetic country policy test' ) ), 'Country rules must use trusted local metadata for comments only.' );
$rules = rktsky_rules_fixture( array_replace( $country_settings, array( 'privacy_mode' => true ) ) );
rktsky_rules_expect( '' === $rules->check( 'comments', array( 'content' => 'Synthetic privacy policy test' ) ), 'Privacy mode must enforce the disabled country rule server-side.' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$rules = rktsky_rules_fixture( $country_settings );
rktsky_rules_expect( '' === $rules->check( 'comments', array( 'content' => 'Untrusted header fixture' ) ), 'An untrusted country header must remain unknown and allowed by default.' );
$rules = rktsky_rules_fixture( array_replace( $country_settings, array( 'country_unknown_allowed' => false ) ) );
rktsky_rules_expect( 'country' === $rules->check( 'comments', array( 'content' => 'Untrusted header fixture' ) ), 'An explicit unknown-country rejection must not trust spoofed metadata.' );

$rules = rktsky_rules_fixture( array( 'spam_keywords' => 'tailword' ) );
rktsky_rules_expect( '' === $rules->check( 'cf7', array( 'message' => str_repeat( 'a', 8192 ) . 'tailword' ) ), 'The documented subject limit must bound work without claiming a full-content scan.' );
echo 'Local content policy tests: ' . $GLOBALS['rktsky_rules_count'] . " assertions passed.\n";
