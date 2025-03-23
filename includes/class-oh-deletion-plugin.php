<?php
/**
 * Filename: includes/class-oh-deletion-plugin.php
 * Main plugin class for Delete Old Out-of-Stock Products
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.3.0
 * @since 2.2.3
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
 *    3.3 Background processing
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
        
        $deleted = $this->processor->delete_old_out_of_stock_products();
        
        update_option( DOOP_RESULT_OPTION, $deleted );
        update_option( DOOP_PROCESS_OPTION, 0 ); // Mark as completed
        
        $this->logger->log("Scheduled deletion process completed. Deleted $deleted products.");
        return $deleted;
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
            wp_redirect( admin_url( 'admin.php?page=doop-settings' ) );
            exit;
        }
        
        // Log the manual run
        $current_user = wp_get_current_user();
        $this->logger->log("Manual deletion process initiated by user: " . $current_user->user_login);
        
        // Run the deletion process directly
        $this->logger->log("Running deletion process directly");
        $deleted = $this->processor->delete_old_out_of_stock_products();
        $this->logger->log("Deletion process completed. Deleted $deleted products.");
        
        // Store the result
        update_option( DOOP_RESULT_OPTION, $deleted );
        
        // Simple redirect back to the settings page
        wp_redirect( admin_url( 'admin.php?page=doop-settings' ) );
        exit;
    }
