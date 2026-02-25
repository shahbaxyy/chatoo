<?php
/**
 * Plugin Name:       MyHelpDesk Chat
 * Plugin URI:        https://myhelpdesk.chat
 * Description:       A full-featured Live Chat & Help Desk plugin for WordPress. Manage conversations, tickets, knowledge base, and more — all from your dashboard.
 * Version:           1.0.0
 * Author:            MyHelpDesk
 * Author URI:        https://myhelpdesk.chat
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       myhelpdesk-chat
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package MyHelpDesk_Chat
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin version.
 */
define( 'MHD_VERSION', '1.0.0' );

/**
 * Plugin base file.
 */
define( 'MHD_PLUGIN_FILE', __FILE__ );

/**
 * Plugin base directory path.
 */
define( 'MHD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin base URL.
 */
define( 'MHD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'MHD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Database table prefix for plugin tables.
 */
define( 'MHD_TABLE_PREFIX', 'mhd_' );

/**
 * Text domain.
 */
define( 'MHD_TEXT_DOMAIN', 'myhelpdesk-chat' );

/*
|--------------------------------------------------------------------------
| Include Required Files
|--------------------------------------------------------------------------
*/
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-loader.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-activator.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-deactivator.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-ajax.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-conversations.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-messages.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-agents.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-departments.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-tickets.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-notifications.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-email.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-knowledge-base.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-automations.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-reports.php';
require_once MHD_PLUGIN_DIR . 'includes/class-mhd-rest-api.php';
require_once MHD_PLUGIN_DIR . 'admin/class-mhd-admin.php';
require_once MHD_PLUGIN_DIR . 'public/class-mhd-public.php';

// Conditionally load WooCommerce integration.
if ( class_exists( 'WooCommerce' ) || function_exists( 'WC' ) ) {
	require_once MHD_PLUGIN_DIR . 'includes/class-mhd-woocommerce.php';
}

/*
|--------------------------------------------------------------------------
| Activation / Deactivation / Uninstall Hooks
|--------------------------------------------------------------------------
*/
register_activation_hook( __FILE__, array( 'MHD_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MHD_Deactivator', 'deactivate' ) );

/*
|--------------------------------------------------------------------------
| Initialize the Plugin
|--------------------------------------------------------------------------
*/

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function mhd_run() {
	$loader = new MHD_Loader();
	$loader->run();
}
mhd_run();

/*
|--------------------------------------------------------------------------
| Plugin Action Links
|--------------------------------------------------------------------------
*/

/**
 * Add Settings and Docs links to the plugins list page.
 *
 * @since 1.0.0
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function mhd_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=myhelpdesk-settings' ) ) . '">' . esc_html__( 'Settings', 'myhelpdesk-chat' ) . '</a>';
	$docs_link     = '<a href="https://myhelpdesk.chat/docs" target="_blank">' . esc_html__( 'Docs', 'myhelpdesk-chat' ) . '</a>';
	array_unshift( $links, $settings_link, $docs_link );
	return $links;
}
add_filter( 'plugin_action_links_' . MHD_PLUGIN_BASENAME, 'mhd_plugin_action_links' );
