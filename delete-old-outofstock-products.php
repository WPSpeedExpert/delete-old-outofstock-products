<?php
/**
 * Plugin Name:        Delete Old Out-of-Stock Products
 * Plugin URI:         https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * Description:        Automatically deletes WooCommerce products that are out of stock and older than 1.5 years, including their images. Returns 410 Gone for deleted product URLs.
 * Version:            2.5.2
 * Author:             OctaHexa
 * Author URI:         https://octahexa.com
 * Text Domain:        delete-old-outofstock-products
 * License:            GPL-2.0+
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI:  https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * GitHub Branch:      main
 */

/**
 * Main plugin file: /delete-old-outofstock-products.php
 *
 * This is the main plugin file that initializes all functionality for 
 * Delete Old Out-of-Stock Products plugin.
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.4.8
 */

/**
 * TABLE OF CONTENTS:
 * 
 * 1. SETUP & INITIALIZATION
 *    1.1 Exit if accessed directly
 *    1.2 Define constants
 *    1.3 Require files
 *    1.4 Initialize plugin
 * 
 * 2. PLUGIN CLASS
 *    2.1 Constructor
 *    2.2 Activation/Deactivation hooks
 *    2.3 Load text domain
 * 
 * 3. CORE FUNCTIONALITY
 *    3.1 Main clean-up process
 *    3.2 Background processing
 *    3.3 410 Gone handling
 */

// 1. SETUP & INITIALIZATION
// ====================================

// 1.1 Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1.2 Define constants
define( 'DOOP_VERSION', '2.4.8' );
define( 'DOOP_PLUGIN_FILE', __FILE__ );
define( 'DOOP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DOOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DOOP_CRON_HOOK', 'doop_cron_delete_old_products' );
define( 'DOOP_OPTIONS_KEY', 'oh_doop_options' );
define( 'DOOP_PROCESS_OPTION', 'oh_doop_process_running' );
define( 'DOOP_RESULT_OPTION', 'oh_doop_last_run_count' );

// 1.3 Require files
require_once DOOP_PLUGIN_DIR . 'includes/class-oh-logger.php';
require_once DOOP_PLUGIN_DIR . 'includes/class-oh-deletion-processor.php';
require_once DOOP_PLUGIN_DIR . 'includes/class-oh-admin-ui.php';
require_once DOOP_PLUGIN_DIR . 'includes/class-oh-deletion-plugin.php';
require_once DOOP_PLUGIN_DIR . 'includes/class-oh-deleted-products-tracker.php';

// 1.4 Initialize plugin
function oh_init_plugin() {
    return OH_Deletion_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'oh_init_plugin' );

// This is needed for backward compatibility
function oh_handle_manual_run() {
    $instance = OH_Deletion_Plugin::get_instance();
    $instance->handle_manual_run();
}

// Initialize 410 tracker if enabled
function oh_init_410_tracker() {
    $options = get_option(DOOP_OPTIONS_KEY, array('enable_410' => 'yes'));
    
    if (isset($options['enable_410']) && $options['enable_410'] === 'yes') {
        new OH_Deleted_Products_Tracker();
    }
}
add_action('init', 'oh_init_410_tracker');
