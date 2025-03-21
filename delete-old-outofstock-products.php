<?php
/**
 * Plugin Name:        Delete Old Out-of-Stock Products
 * Plugin URI:         https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * Description:        Automatically deletes WooCommerce products that are out of stock and older than 1.5 years, including their images.
 * Version:            2.3.0
 * Author:             OctaHexa
 * Author URI:         https://octahexa.com
 * Text Domain:        delete-old-outofstock-products
 * License:            GPL-2.0+
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI:  https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * GitHub Branch:      main
 */

/**
 * TABLE OF CONTENTS:
 * 
 * 1. SETUP & INITIALIZATION
 *    1.1 Exit if accessed directly
 *    1.2 Define constants
 * 
 * 2. CORE FUNCTIONALITY
 *    2.1 Delete old out-of-stock products
 *    2.2 Status tracking & logging
 * 
 * 3. HOOKS & EVENTS
 *    3.1 Register activation hook
 *    3.2 Register deactivation hook
 *    3.3 Register cron hook
 * 
 * 4. ADMIN INTERFACE
 *    4.1 Admin menu
 *    4.2 AJAX handlers
 */

// 1. SETUP & INITIALIZATION
// ====================================

// 1.1 Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1.2 Define constants
define( 'DOOP_CRON_HOOK', 'doop_cron_delete_old_products' );
define( 'DOOP_AJAX_ACTION', 'oh_doop_process_status' );
define( 'DOOP_PROCESS_OPTION', 'oh_doop_process_running' );
define( 'DOOP_RESULT_OPTION', 'oh_doop_last_process_result' );
define( 'DOOP_LOG_OPTION', 'oh_doop_deletion_log' );
define( 'DOOP_VERSION', '2.3.0' );

// 2. CORE FUNCTIONALITY
// ====================================

/**
 * Delete out-of-stock WooCommerce products older than 1.5 years, including images.
 * 
 * @return array Statistics about deleted products
 */
function oh_delete_old_out_of_stock_products() {
    $start_time = microtime(true);
    $result = array(
        'products_found' => 0,
        'products_deleted' => 0,
        'images_deleted' => 0,
        'errors' => 0,
        'execution_time' => 0,
    );
    
    // Set process as running
    update_option(DOOP_PROCESS_OPTION, true);
    oh_log_deletion_message('Starting deletion process...');
    
    if ( ! class_exists( 'WooCommerce' ) ) {
        oh_log_deletion_message('WooCommerce not found - process aborted.');
        update_option(DOOP_PROCESS_OPTION, false);
        $result['errors']++;
        return $result;
    }

    $date_threshold = date( 'Y-m-d H:i:s', strtotime( '-18 months' ) );

    $products = get_posts( array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'date_query'     => array(
            array(
                'before' => $date_threshold,
            ),
        ),
        'meta_query'     => array(
            array(
                'key'   => '_stock_status',
                'value' => 'outofstock',
            ),
        ),
        'fields' => 'ids',
    ) );

    $result['products_found'] = count($products);
    oh_log_deletion_message(sprintf('Found %d products to delete.', $result['products_found']));

    foreach ( $products as $product_id ) {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            $result['errors']++;
            continue;
        }

        $attachment_ids = array();

        // Featured image
        $featured_image_id = $product->get_image_id();
        if ( $featured_image_id ) {
            $attachment_ids[] = $featured_image_id;
        }

        // Gallery images
        $attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );
        $image_count = count($attachment_ids);

        // Delete images
        foreach ( $attachment_ids as $attachment_id ) {
            if ( $attachment_id ) {
                if (wp_delete_attachment( $attachment_id, true )) {
                    $result['images_deleted']++;
                } else {
                    $result['errors']++;
                }
            }
        }

        // Delete product
        if (wp_delete_post( $product_id, true )) {
            $result['products_deleted']++;
            oh_log_deletion_message(sprintf('Deleted product #%d with %d images.', $product_id, $image_count));
        } else {
            $result['errors']++;
            oh_log_deletion_message(sprintf('Failed to delete product #%d.', $product_id));
        }
    }
    
    $end_time = microtime(true);
    $result['execution_time'] = round($end_time - $start_time, 2);
    
    // Mark process as complete
    update_option(DOOP_PROCESS_OPTION, false);
    update_option(DOOP_RESULT_OPTION, $result);
    
    oh_log_deletion_message(sprintf(
        'Process completed in %s seconds. Deleted %d/%d products and %d images. Errors: %d',
        $result['execution_time'],
        $result['products_deleted'],
        $result['products_found'],
        $result['images_deleted'],
        $result['errors']
    ));
    
    return $result;
}

/**
 * Log a message to the deletion log
 * 
 * @param string $message The message to log
 */
function oh_log_deletion_message($message) {
    $log = get_option(DOOP_LOG_OPTION, array());
    $log[] = array(
        'time' => current_time('mysql'),
        'message' => $message
    );
    
    // Keep only the last 100 log entries
    if (count($log) > 100) {
        $log = array_slice($log, -100);
    }
    
    update_option(DOOP_LOG_OPTION, $log);
}

/**
 * Get process status for AJAX requests
 * 
 * @return array Process status information
 */
function oh_get_process_status() {
    $is_running = get_option(DOOP_PROCESS_OPTION, false);
    $result = get_option(DOOP_RESULT_OPTION, array());
    $log = get_option(DOOP_LOG_OPTION, array());
    
    return array(
        'is_running' => $is_running,
        'result' => $result,
        'log' => array_slice($log, -10), // Return only the last 10 log entries
    );
}

// 3. HOOKS & EVENTS
// ====================================

/**
 * Schedule the cron event on plugin activation.
 */
function oh_activate() {
    if ( ! wp_next_scheduled( DOOP_CRON_HOOK ) ) {
        wp_schedule_event( time(), 'daily', DOOP_CRON_HOOK );
    }
    
    // Initialize options
    update_option(DOOP_PROCESS_OPTION, false);
    add_option(DOOP_LOG_OPTION, array());
    add_option(DOOP_RESULT_OPTION, array());
}
register_activation_hook( __FILE__, 'oh_activate' );

/**
 * Clear the cron event on plugin deactivation.
 */
function oh_deactivate() {
    $timestamp = wp_next_scheduled( DOOP_CRON_HOOK );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, DOOP_CRON_HOOK );
    }
}
register_deactivation_hook( __FILE__, 'oh_deactivate' );

/**
 * Run the product deletion on cron.
 */
add_action( DOOP_CRON_HOOK, 'oh_delete_old_out_of_stock_products' );

// 4. ADMIN INTERFACE
// ====================================

/**
 * Add AJAX handler for status updates
 */
add_action('wp_ajax_' . DOOP_AJAX_ACTION, function() {
    wp_send_json(oh_get_process_status());
});

/**
 * Initialize admin functionality 
 */
function oh_admin_init() {
    require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
}
add_action('admin_init', 'oh_admin_init');

/**
 * Add admin menu item
 */
function oh_add_admin_menu() {
    add_submenu_page(
        'tools.php',
        'Delete Old Products',
        'Delete Old Products',
        'manage_options',
        'delete-old-products',
        'oh_render_admin_page'
    );
}
add_action('admin_menu', 'oh_add_admin_menu');

/**
 * Enqueue admin scripts and styles
 */
function oh_enqueue_admin_scripts($hook) {
    if ('tools_page_delete-old-products' !== $hook) {
        return;
    }

    wp_enqueue_script(
        'oh-doop-admin', 
        plugin_dir_url(__FILE__) . 'assets/js/admin.js',
        array('jquery'),
        DOOP_VERSION,
        true
    );

    wp_localize_script('oh-doop-admin', 'ohDoopData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'action' => DOOP_AJAX_ACTION,
        'nonce' => wp_create_nonce(DOOP_AJAX_ACTION),
    ));
}
add_action('admin_enqueue_scripts', 'oh_enqueue_admin_scripts');

/**
 * Handle manual run triggering
 */
function oh_handle_manual_run() {
    if (isset($_POST['oh_run_deletion']) && 
        check_admin_referer('oh_manual_deletion') && 
        current_user_can('manage_options')) {
        
        // Don't run if already running
        if (get_option(DOOP_PROCESS_OPTION, false)) {
            add_settings_error(
                'oh_doop_messages',
                'process_already_running',
                'A cleanup process is already running. Please wait for it to complete.',
                'error'
            );
            return;
        }
        
        // Trigger background process
        wp_schedule_single_event(time(), DOOP_CRON_HOOK);
        
        // Force immediate cron execution
        spawn_cron();
        
        // Add success message
        add_settings_error(
            'oh_doop_messages',
            'process_started',
            'Product cleanup process has been started in the background. You can stay on this page to monitor progress or navigate away - the process will continue running.',
            'success'
        );
    }
}
add_action('admin_init', 'oh_handle_manual_run');
