<?php
/**
 * Plugin Name:        Delete Old Out-of-Stock Products
 * Plugin URI:         https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * Description:        Automatically deletes WooCommerce products that are out of stock and older than a configurable time period, including their images.
 * Version:            2.2.4
 * Author:             OctaHexa
 * Author URI:         https://octahexa.com
 * Text Domain:        delete-old-outofstock-products
 * License:            GPL-3.0+
 * License URI:        https://www.gnu.org/licenses/gpl-3.0.html
 * GitHub Plugin URI:  https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * GitHub Branch:      main
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'DOOP_VERSION', '2.2.3' );
define( 'DOOP_PLUGIN_FILE', __FILE__ );
define( 'DOOP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DOOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DOOP_CRON_HOOK', 'doop_cron_delete_old_products' );
define( 'DOOP_OPTIONS_KEY', 'oh_doop_options' );

/**
 * Include required files
 */
require_once DOOP_PLUGIN_DIR . 'includes/class-oh-logger.php';
require_once DOOP_PLUGIN_DIR . 'includes/class-oh-deletion-processor.php';
require_once DOOP_PLUGIN_DIR . 'includes/class-oh-admin-ui.php';
require_once DOOP_PLUGIN_DIR . 'includes/class-oh-deletion-plugin.php';

/**
 * Check if WooCommerce is active
 * 
 * @return bool True if WooCommerce is active
 */
function oh_doop_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active
 */
function oh_doop_admin_notice() {
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><?php esc_html_e( 'Delete Old Out-of-Stock Products requires WooCommerce to be installed and activated.', 'delete-old-outofstock-products' ); ?></p>
    </div>
    <?php
}

/**
 * Check for deletion results when admin page loads
 */
function oh_doop_check_deletion_results() {
    $screen = get_current_screen();
    
    // Only on our settings page
    if ( isset( $screen->id ) && 'woocommerce_page_doop-settings' === $screen->id ) {
        // Check if there was a running process that's now complete
        $was_running = get_option( 'oh_doop_deletion_running', false );
        $last_run_count = get_option( 'oh_doop_last_run_count', false );
        
        // If the process was running but now we have results and not showing any status
        if ( $was_running === 0 && false !== $last_run_count && ! isset( $_GET['deletion_status'] ) ) {
            // Redirect to show results
            wp_safe_redirect( add_query_arg( 
                array(
                    'deletion_status' => 'completed',
                    'deleted' => $last_run_count,
                    't' => time() // Add timestamp to prevent caching
                ),
                admin_url( 'admin.php?page=doop-settings' )
            ));
            exit;
        }
    }
}
add_action( 'current_screen', 'oh_doop_check_deletion_results' );

/**
 * Initialize the plugin
 */
function oh_doop_init() {
    // Check if WooCommerce is active
    if ( ! oh_doop_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'oh_doop_admin_notice' );
        return;
    }
    
    // Initialize the plugin
    OH_Deletion_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'oh_doop_init' );
