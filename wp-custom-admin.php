<?php
/**
 * Plugin Name:       WP Custom Admin
 * Plugin URI:        https://github.com/AmirDezyani/wp-custom-admin
 * Description:       Reskin wp-admin into a bespoke, brandable control panel. Configurable logo, colors, login page, admin menu, and white-labeling — reusable across many sites.
 * Version:           0.6.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Amir Dezyani
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-custom-admin
 * Domain Path:       /languages
 * Update URI:        false
 *
 * @package WPCustomAdmin
 */

declare( strict_types=1 );

namespace WPCustomAdmin;

defined( 'ABSPATH' ) || exit;

define( 'WPCA_VERSION', '0.6.0' );
define( 'WPCA_FILE', __FILE__ );
define( 'WPCA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPCA_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCA_BASENAME', plugin_basename( __FILE__ ) );

require_once WPCA_PATH . 'src/autoload.php';

register_activation_hook( __FILE__, array( Activation::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Activation::class, 'deactivate' ) );

/**
 * Boot the plugin once WordPress and all plugins are loaded.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);
