<?php
/**
 * Loads unchanged Core KSES for isolated adapter contracts, without a database.
 *
 * Existing suites explicitly stub provider services and WordPress hooks. KSES
 * itself is never replaced: its parser, policy checks and URL filtering come
 * from the supplied original Core file. The support fixtures allow only HTTP(S)
 * links and empty KSES hook registries. Native output-boundaries.php separately
 * boots complete Core with its real hooks and full protocol configuration.
 *
 * @package RktskyAntispamTests
 * @since 0.5.6
 */

if ( PHP_SAPI !== 'cli' ) {
	throw new RuntimeException( 'Core KSES contract fixtures are CLI-only.' );
}

if ( ! function_exists( 'wp_allowed_protocols' ) ) {
	/**
	 * Restricts isolated fixtures to their local HTTP(S) reload link contract.
	 *
	 * @since 0.5.6
	 * @return string[] Explicit fixture protocol policy, narrower than Core defaults.
	 */
	function wp_allowed_protocols(): array { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required native function signature in an explicitly isolated dependency fixture.
		return array( 'http', 'https' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Supports the older native HTML parser with valid UTF-8 fixture attributes.
	 *
	 * @since 0.5.6
	 * @param string $value Explicit fixture attribute text.
	 * @return string Quoted-attribute-safe synthetic value.
	 */
	function esc_attr( $value ): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Explicit dependency fixture; complete native escaping is tested in output-boundaries.php.
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Requires an empty, recognized KSES hook context in isolated stub suites.
	 *
	 * No filter callback is silently bypassed. Full native hook execution is
	 * covered by output-boundaries.php in the installed WordPress fixture.
	 *
	 * @since 0.5.6
	 * @param string $hook Fixed Core KSES filter name.
	 * @param mixed  $value Unfiltered original value when no callback is installed.
	 * @return mixed Original value for a demonstrably empty hook registry.
	 * @throws RuntimeException When an unsupported hook or callback is present.
	 */
	function apply_filters( string $hook, $value ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required native hook signature; rejects any nonempty or unsupported hook context.
		if ( ! in_array( $hook, array( 'pre_kses', 'wp_kses_allowed_html', 'wp_kses_uri_attributes' ), true ) ) {
			throw new RuntimeException( 'The isolated fixture cannot model this filter.' );
		}
		foreach ( array( 'extra_hooks', 'integration_hooks', 'rktsky_divi_hooks' ) as $registry ) {
			if ( ! empty( $GLOBALS[ $registry ][ $hook ] ) ) {
				throw new RuntimeException( 'Use native Core for registered KSES filters.' );
			}
		}
		return $value;
	}
}

$rktsky_kses_root = getenv( 'RKTSKY_ANTISPAM_WP_ROOT' );
if ( ! is_string( $rktsky_kses_root ) || ! is_file( $rktsky_kses_root . '/wp-includes/kses.php' ) ) {
	throw new RuntimeException( 'An unchanged original WordPress KSES source directory is required.' );
}
if ( is_file( $rktsky_kses_root . '/wp-includes/utf8.php' ) ) {
	require_once $rktsky_kses_root . '/wp-includes/utf8.php';
}
foreach ( array( 'span', 'attribute-token', 'text-replacement', 'decoder', 'tag-processor' ) as $rktsky_kses_dependency ) {
	require_once $rktsky_kses_root . '/wp-includes/html-api/class-wp-html-' . $rktsky_kses_dependency . '.php';
}
require_once $rktsky_kses_root . '/wp-includes/kses.php';
