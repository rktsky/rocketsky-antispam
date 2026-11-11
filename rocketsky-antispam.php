<?php
/**
 * Plugin Name: Rocketsky Antispam
 * Plugin URI: https://github.com/rktsky/rocketsky-antispam
 * Update URI: https://github.com/rktsky/rocketsky-antispam
 * Description: Free local spam protection for comments and forms. No added CAPTCHA or external spam service.
 * Version: 0.5.4
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Author: Rocketsky
 * Author URI: https://www.rocketsky.ch/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rocketsky-antispam
 * Domain Path: /languages
 *
 * Boots the plugin services without runtime dependencies.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

// The renamed copy must not redefine services while a legacy copy is still active.
if ( ! class_exists( Rktsky\Antispam\Installation::class, false ) ) {
	require_once __DIR__ . '/src/Installation.php';
}
if ( ! ( new Rktsky\Antispam\Installation( __FILE__ ) )->register() ) {
	return;
}

define( 'RKTSKY_ANTISPAM_VERSION', '0.5.4' );
define( 'RKTSKY_ANTISPAM_FILE', __FILE__ );

// Explicit local includes avoid filesystem discovery and third-party autoloaders.
foreach ( array( 'Providers', 'Settings', 'Dates', 'Country', 'Language', 'ContentRules', 'Token', 'Store', 'Quarantine', 'Feedback', 'NativeFeedback', 'WpFormsFeedback', 'Guard', 'Fields', 'Integrations', 'SpamHandling', 'ContextNotices', 'Admin', 'Dashboard', 'Digest', 'Maintenance', 'Network', 'Plugin' ) as $rktsky_antispam_class ) {
	require_once __DIR__ . '/src/' . $rktsky_antispam_class . '.php';
}
unset( $rktsky_antispam_class );

register_activation_hook( __FILE__, array( Rktsky\Antispam\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Rktsky\Antispam\Plugin::class, 'deactivate' ) );
add_action( 'plugins_loaded', array( Rktsky\Antispam\Plugin::class, 'boot' ) );
