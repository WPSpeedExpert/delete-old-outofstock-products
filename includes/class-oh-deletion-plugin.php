<?php
/**
 * Filename: includes/class-oh-deletion-plugin.php
 * Main plugin class for Delete Old Out-of-Stock Products
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.4.1
 */

/**
 * TABLE OF CONTENTS:
 *
 * 1. SETUP & INITIALIZATION
 *    1.1 Class properties
 *    1.2 Singleton pattern
 *    1.3 Constructor
 *
 * 2. PLUGIN LIFECYCLE
 *    2.1 Activation
 *    2.2 Deactivation
 *    2.3 Update cron time
 *
 * 3. PRODUCT DELETION
 *    3.1 Run scheduled deletion
 *    3.2 Handle manual process
 *    3.3 AJAX fallback trigger
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class
 */
class OH_Deletion_Plugin {
    
    // 1. SETUP & INITIALIZATION
    // =================================

    /**
     * 1.1 Class properties
     */
    
    /**
     * Plugin instance
     *
     * @var OH_Deletion_Plugin
     */
    private static $instance = null;

    /**
     * Logger instance
     *
     * @var OH_Logger
     */
    private $logger;
    
    /**
     * Deletion processor instance
     *
     * @var OH_Deletion_Processor
     */
    private $processor;
    
    /**
     * Admin UI instance
     *
     * @var OH_Admin_UI
     */
    private $admin_ui;
    
    /**
     * Last cron execution time
     *
     * @var int
     */
    private $last_cron_time;

    /**
     * 1.2 Singleton pattern - Get single instance of the plugin
     *
     * @return OH_Deletion_Plugin
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 1.3 Constructor
     */
    private function __construct() {
        $this->logger = OH_Logger::get_instance();
        $this->processor = new OH_Deletion_Processor();
        $this->admin_ui = new OH_Admin_UI();
        
        $this->last_cron_time = get_option( 'oh_doop_last_cron_time', 0 );

        // Plugin activation and deactivation hooks
        register_activation_hook( DOOP_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( DOOP_PLUGIN_FILE, array( $this, 'deactivate' ) );
        
        // Handle manual run
        add_action( 'admin_post_oh_run_product_deletion', array( $this, 'handle_manual_run' ) );

        // Cron action
        add_action( DOOP_CRON_HOOK, array( $this, 'run_scheduled_deletion' ) );
        add_action( DOOP_CRON_HOOK, array( $this, 'update_last_cron_time' ) );
        
        // Add AJAX action for fallback deletion trigger
        add_action('wp_ajax_oh_trigger_deletion', array($this, 'ajax_trigger_deletion'));
        add_action('wp_ajax_nopriv_oh_trigger_deletion', array($this, 'ajax_trigger_deletion'));
    }
    
    // 2. PLUGIN LIFECYCLE
    // =================================
    
    /**
     * 2.1 Activate the plugin
     */
    public function activate() {
        // Add default options if they don't exist
        if ( ! get_option( DOOP_OPTIONS_KEY ) ) {
            update_option( DOOP_OPTIONS_KEY, array(
                'product_age' => 18,
                'delete_images' => 'yes',
            ) );
        }

        // Clear any existing scheduled events first to avoid duplicates
        $timestamp = wp_next_scheduled( DOOP_CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, DOOP_CRON_HOOK );
        }

        // Schedule the cron event
        wp_schedule_event( time(), 'daily', DOOP_CRON_HOOK );
        
        // Initialize the last cron time if needed
        if ( ! get_option( 'oh_doop_last_cron_time' ) ) {
            update_option( 'oh_doop_last_cron_time', 0 );
        }
        
        // Make sure status flags are cleared
        delete_option( DOOP_PROCESS_OPTION );
        delete_option( DOOP_RESULT_OPTION );
        delete_option( 'oh_doop_too_many_products' );
        delete_option( 'oh_doop_manual_process' );
        
        $this->logger->log('Plugin activated');
    }

    /**
     * 2.2 Deactivate the plugin
     */
    public function deactivate() {
        // Clear the cron event
        $timestamp = wp_next_scheduled( DOOP_CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, DOOP_CRON_HOOK );
        }
        
        // Clean up any running process flags
        delete_option( DOOP_PROCESS_OPTION );
        delete_option( DOOP_RESULT_OPTION );
        delete_option( 'oh_doop_too_many_products' );
        delete_option( 'oh_doop_manual_process' );
        // Don't delete oh_doop_last_cron_time - keep this record even when deactivated
        
        $this->logger->log('Plugin deactivated');
    }
    
    /**
     * 2.3 Update the last cron execution time
     */
    public function update_last_cron_time() {
        $current_time = time();
        update_option( 'oh_doop_last_cron_time', $current_time );
        $this->last_cron_time = $current_time;
    }
    
    // 3. PRODUCT DELETION
    // =================================
    
    /**
     * 3.1 Run the scheduled deletion process
     * 
     * @return int Number of products deleted
     */
    public function run_scheduled_deletion() {
        $this->logger->log("Starting scheduled deletion process");
        update_option( DOOP_PROCESS_OPTION, time() );
        
        try {
            $deleted = $this->processor->delete_old_out_of_stock_products();
            
            update_option( DOOP_RESULT_OPTION, $deleted );
            update_option( DOOP_PROCESS_OPTION, 0 ); // Mark as completed
            
            $this->logger->log("Scheduled deletion process completed. Deleted $deleted products.");
            return $deleted;
        } catch (Exception $e) {
            $this->logger->log("Error in scheduled deletion process: " . $e->getMessage());
            $this->logger->log("Stack trace: " . $e->getTraceAsString());
            
            // Mark as completed with error to prevent getting stuck
            update_option( DOOP_PROCESS_OPTION, 0 );
            update_option( DOOP_RESULT_OPTION, 0 );
            
            return 0;
        }
    }
    
    /**
     * 3.2 Handle manual run of the product deletion process
     */
    public function handle_manual_run() {
        // Check nonce for security
        if ( 
            ! isset( $_POST['oh_nonce'] ) || 
            ! wp_verify_nonce( $_POST['oh_nonce'], 'oh_run_product_deletion_nonce' ) || 
            ! current_user_can( 'manage_options' )
        ) {
            wp_die( esc_html__( 'Security check failed. Please try again.', 'delete-old-outofstock-products' ) );
        }
        
        // Check if process is already running
        $is_running = get_option( DOOP_PROCESS_OPTION, false );
        if ($is_running && $is_running !== 0) {
            $this->logger->log("Manual deletion process requested but another process is already running.");
            wp_redirect(add_query_arg(
                array(
                    'page' => 'doop-settings',
                    'deletion_status' => 'already_running', 
                    't' => time()
                ),
                admin_url('admin.php')
            ));
            exit;
        }
        
        // Set a flag that the process is starting
        update_option( DOOP_PROCESS_OPTION, time() );
        delete_option( DOOP_RESULT_OPTION ); // Clear previous results
        
        // Log the manual run
        $current_user = wp_get_current_user();
        $this->logger->log("Manual deletion process initiated by user: " . $current_user->user_login);
        
        try {
            // Count eligible products
            $options = get_option( DOOP_OPTIONS_KEY, array(
                'product_age' => 18,
                'delete_images' => 'yes',
            ));
            
            $product_age = isset( $options['product_age'] ) ? absint( $options['product_age'] ) : 18;
            $date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$product_age} months" ) );
            
            $eligible_query = new WP_Query( array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
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
            ) );
            
            $eligible_count = $eligible_query->found_posts;
            $this->logger->log("Products eligible for deletion: " . $eligible_count);
            
            // If there are too many products, log and exit
            if ($eligible_count > 200) {
                $this->logger->log("Too many products ($eligible_count) eligible for deletion. Aborting manual run.");
                update_option( 'oh_doop_too_many_products', $eligible_count );
                
                wp_redirect(add_query_arg(
                    array(
                        'page' => 'doop-settings',
                        'deletion_status' => 'too_many',
                        'count' => $eligible_count,
                        't' => time()
                    ),
                    admin_url('admin.php')
                ));
                exit;
            }
        } catch (Exception $e) {
            $this->logger->log("Error counting eligible products: " . $e->getMessage());
            // Continue with the process, don't abort
        }
        
        // Set a flag to prevent the cron schedule refresh redirect
        update_option('oh_doop_manual_process', true);
        
        // For small batches, run directly for immediate feedback
        if (isset($eligible_count) && $eligible_count < 20) {
            $this->logger->log("Running deletion process directly (small number of products)");
            
            try {
                // Run the process directly
                $deleted = $this->processor->delete_old_out_of_stock_products();
                
                // Update results
                update_option( DOOP_RESULT_OPTION, $deleted );
                update_option( DOOP_PROCESS_OPTION, 0 ); // Mark as complete
                
                $this->logger->log("Manual deletion process completed directly. Deleted $deleted products.");
                
                // Redirect to the completion page
                wp_redirect(add_query_arg(
                    array(
                        'page' => 'doop-settings',
                        'deletion_status' => 'completed',
                        'deleted' => $deleted,
                        't' => time()
                    ),
                    admin_url('admin.php')
                ));
                exit;
            } catch (Exception $e) {
                $this->logger->log("Error in direct deletion process: " . $e->getMessage());
                // Continue with background process instead
            }
        }
        
        // For larger numbers or if direct processing failed, use the background process
        $this->logger->log("Starting deletion process in the background");
        
        // Setup immediate execution
        if (!wp_next_scheduled(DOOP_CRON_HOOK)) {
            // Schedule the event to run immediately
            wp_schedule_single_event(time(), DOOP_CRON_HOOK);
            $this->logger->log("Scheduled immediate deletion event");
        } else {
            $this->logger->log("Deletion event already scheduled");
        }
        
        // Make sure the cron system is triggered
        $this->logger->log("Spawning cron system to run event immediately");
        spawn_cron();
        
        // Add a direct trigger as fallback in case wp-cron is unreliable
        if ($eligible_count > 0 && $eligible_count <= 50) {
            $this->logger->log("Adding immediate fallback trigger for small batch");
            wp_remote_post(admin_url('admin-ajax.php'), array(
                'blocking' => false,
                'sslverify' => false,
                'body' => array(
                    'action' => 'oh_trigger_deletion',
                    'security' => wp_create_nonce('oh_trigger_deletion_nonce')
                )
            ));
        }
        
        // Redirect to the monitoring page with a special parameter
        wp_redirect(add_query_arg(
            array(
                'page' => 'doop-settings',
                'deletion_status' => 'running',
                'manual' => '1',
                't' => time()
            ),
            admin_url('admin.php')
        ));
        exit;
    }
    
    /**
     * 3.3 AJAX handler to directly trigger the deletion process as fallback
     */
    public function ajax_trigger_deletion() {
        // Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'oh_trigger_deletion_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Check if process is already running properly
        $is_running = get_option(DOOP_PROCESS_OPTION, false);
        $start_time = intval($is_running);
        $current_time = time();
        
        // Only run if the process appears to be stuck (started more than 2 minutes ago)
        if ($start_time > 0 && ($current_time - $start_time) > 120) {
            $this->logger->log("Fallback AJAX trigger activated - process appears stuck for " . 
                human_time_diff($start_time, $current_time));
            
            try {
                // Run the deletion process directly
                $deleted = $this->processor->delete_old_out_of_stock_products();
                
                // Update results
                update_option(DOOP_RESULT_OPTION, $deleted);
                update_option(DOOP_PROCESS_OPTION, 0); // Mark as complete
                
                $this->logger->log("AJAX fallback deletion process completed. Deleted $deleted products.");
                wp_send_json_success(array('deleted' => $deleted));
            } catch (Exception $e) {
                $this->logger->log("Error in AJAX fallback deletion: " . $e->getMessage());
                wp_send_json_error('Error: ' . $e->getMessage());
            }
        } else {
            // Process is either not running or started recently
            wp_send_json_success(array('status' => 'Deletion already in progress or not needed'));
        }
        
        die();
    }
}
