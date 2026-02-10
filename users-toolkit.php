<?php
/**
 * Plugin Name: Users Toolkit
 * Plugin URI: https://wordpress.org/plugins/users-toolkit
 * Description: Herramientas para buscar, borrar y optimizar la lista de usuarios y la base de datos de WordPress.
 * Version: 1.1.0
 * Author: Alfonso Fernández (alfonso@cientifi.ca)
 * Author URI: https://cientifi.ca
 * Text Domain: users-toolkit
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'USERS_TOOLKIT_VERSION', '1.1.0' );
define( 'USERS_TOOLKIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'USERS_TOOLKIT_URL', plugin_dir_url( __FILE__ ) );
define( 'USERS_TOOLKIT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_users_toolkit() {
	require_once USERS_TOOLKIT_PATH . 'includes/class-activator.php';
	Users_Toolkit_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_users_toolkit() {
	require_once USERS_TOOLKIT_PATH . 'includes/class-deactivator.php';
	Users_Toolkit_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_users_toolkit' );
register_deactivation_hook( __FILE__, 'deactivate_users_toolkit' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require USERS_TOOLKIT_PATH . 'includes/class-users-toolkit.php';

/**
 * Begins execution of the plugin.
 */
function run_users_toolkit() {
	$plugin = new Users_Toolkit();
	$plugin->run();
}
run_users_toolkit();
