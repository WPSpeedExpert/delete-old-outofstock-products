<?php
/**
 * Filename: includes/class-oh-deletion-plugin.php
 * Main plugin class for Delete Old Out-of-Stock Products
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class
 */
class OH_Deletion_Plugin {
    
    private static $instance = null;
    private $logger;
    private $processor;
    private $admin_ui;
    private $last_cron_time;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

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
        
        $this->logger->log('Plugin activated');
    }

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
        // Don't delete oh_doop_last_cron_time - keep this record even when deactivated
        
        $this->logger->log('Plugin deactivated');
    }
    
    public function update_last_cron_time() {
        $current_time = time();
        update_option( 'oh_doop_last_cron_time', $current_time );
        $this->last_cron_time = $current_time;
    }
    
    public function run_scheduled_deletion() {
        $this->logger->log("Starting scheduled deletion process");
        update_option( DOOP_PROCESS_OPTION, time() );
        
        $deleted = $this->processor->delete_old_out_of_stock_products();
        
        update_option( DOOP_RESULT_OPTION, $deleted );
        update_option( DOOP_PROCESS_OPTION, 0 ); // Mark as completed
        
        $this->logger->log("Scheduled deletion process completed. Deleted $deleted products.");
        return $deleted;
    }
    
    public function handle_manual_run() {
        // Basic emergency handler
        if (!current_user_can('manage_options')) {
            wp_die('Not authorized.');
        }
        
        try {
            $this->logger->log("Manual deletion process initiated");
            $deleted = $this->processor->delete_old_out_of_stock_products();
            $this->logger->log("Manual deletion completed. Deleted $deleted products.");
            update_option(DOOP_RESULT_OPTION, $deleted);
        } catch (Exception $e) {
            $this->logger->log("Error: " . $e->getMessage());
        }
        
        wp_redirect(admin_url('admin.php?page=doop-settings'));
        exit;
    }
}
