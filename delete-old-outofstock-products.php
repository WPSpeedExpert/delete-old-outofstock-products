<?php
/**
 * Plugin Name:        Delete Old Out-of-Stock Products
 * Plugin URI:         https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * Description:        Automatically deletes WooCommerce products that are out of stock and older than 1.5 years, including their images.
 * Version:            2.4.2
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
 * 2. PLUGIN CLASS (Implemented in includes/class-oh-deletion-plugin.php)
 *    2.1 Constructor
 *    2.2 Activation/Deactivation hooks
 *    2.3 Update cron time
 * 
 * 3. CORE FUNCTIONALITY (Implemented in includes/class-oh-deletion-processor.php)
 *    3.1 Main clean-up process - delete_old_out_of_stock_products()
 *    3.2 Background processing - handle_manual_run() in class-oh-deletion-plugin.php
 */

// 1. SETUP & INITIALIZATION
// ====================================

// 1.1 Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1.2 Define constants - Use plugin header version for DOOP_VERSION
$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), 'plugin');
define( 'DOOP_VERSION', $plugin_data['Version'] );
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

// 1.4 Initialize plugin
function oh_init_plugin() {
    // This instantiates the OH_Deletion_Plugin class (implements Section 2)
    return OH_Deletion_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'oh_init_plugin' );

// This is needed for backward compatibility
function oh_handle_manual_run() {
    // This calls the handle_manual_run method (implements Section 3.2)
    $instance = OH_Deletion_Plugin::get_instance();
    $instance->handle_manual_run();
}

// Note: The following sections are implemented in separate class files:
// - Section 2 (PLUGIN CLASS) is implemented in includes/class-oh-deletion-plugin.php
//   - 2.1 Constructor - __construct() method
//   - 2.2 Activation/Deactivation hooks - activate() and deactivate() methods
//   - 2.3 Update cron time - update_last_cron_time() method
//
// - Section 3 (CORE FUNCTIONALITY) is implemented in includes/class-oh-deletion-processor.php
//   - 3.1 Main clean-up process - delete_old_out_of_stock_products() method
//   - 3.2 Background processing - handle_manual_run() method in class-oh-deletion-plugin.php
