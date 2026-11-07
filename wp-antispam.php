<?php
/**
 * Plugin Name: WP Antispam
 * Plugin URI: https://github.com/rktsky/wp-antispam
 * Description: Local spam protection for comments, Contact Form 7, Gravity Forms and WPForms. No external services.
 * Version: 0.1.0
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Author: rktsky
 * Author URI: https://github.com/rktsky
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-antispam
 * Domain Path: /languages
 *
 * Boots the independently authored plugin without runtime dependencies.
 *
 * @package RktskyAntispam
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

define( 'RKTSKY_ANTISPAM_VERSION', '0.1.0' );
define( 'RKTSKY_ANTISPAM_FILE', __FILE__ );

// Explicit local includes avoid filesystem discovery and third-party autoloaders.
foreach ( array( 'Settings', 'Token', 'Store', 'Guard', 'Fields', 'Integrations', 'Admin', 'Plugin' ) as $rktsky_antispam_class ) {
	require_once __DIR__ . '/src/' . $rktsky_antispam_class . '.php';
}
unset( $rktsky_antispam_class );

register_activation_hook( __FILE__, array( Rktsky\Antispam\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Rktsky\Antispam\Plugin::class, 'deactivate' ) );
add_action( 'plugins_loaded', array( Rktsky\Antispam\Plugin::class, 'boot' ) );
