<?php
/**
 * Filename: includes/class-oh-deletion-plugin.php
 * Main plugin class for Delete Old Out-of-Stock Products
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.2.3
 * @since 2.2.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class
 */
class OH_Deletion_Plugin {
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
     * Get single instance of the plugin
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
     * Constructor
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
    
    /**
     * Update the last cron execution time
     */
    public function update_last_cron_time() {
        $current_time = time();
        update_option( 'oh_doop_last_cron_time', $current_time );
        $this->last_cron_time = $current_time;
    }

    /**
     * Activate the plugin
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
        
        $this->logger->log('Plugin activated');
    }

    /**
     * Deactivate the plugin
     */
    public function deactivate() {
        // Clear the cron event
        $timestamp = wp_next_scheduled( DOOP_CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, DOOP_CRON_HOOK );
        }
        
        // Clean up any running process flags
        delete_option( 'oh_doop_deletion_running' );
        delete_option( 'oh_doop_last_run_count' );
        delete_option( 'oh_doop_too_many_products' );
        // Don't delete oh_doop_last_cron_time - keep this record even when deactivated
        
        $this->logger->log('Plugin deactivated');
    }
    
    /**
     * Handle manual run of the product deletion process
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
        
        // Set a flag that the process is starting
        update_option( 'oh_doop_deletion_running', time() );
        
        // Log the manual run
        $this->logger->log("Manual deletion process initiated by user: " . wp_get_current_user()->user_login);
        
        // Check eligible products before running
        $options = get_option( DOOP_OPTIONS_KEY, array(
            'product_age' => 18,
            'delete_images' => 'yes',
        ));
        
        $product_age = isset( $options['product_age'] ) ? absint( $options['product_age'] ) : 18;
        $date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$product_age} months" ) );
        
        // Count eligible products
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
            update_option( 'oh_doop_deletion_running', 0 ); // Clear the running flag
            update_option( 'oh_doop_last_run_count', 0 );
            update_option( 'oh_doop_too_many_products', $eligible_count );
            
            // Redirect to the too many page
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
        
        // Run the deletion directly
        $this->logger->log("Starting manual deletion run");
        
        // Call our deletion function directly
        $result = $this->run_scheduled_deletion();
        
        // Update the stats and status
        update_option('oh_doop_last_run_count', $result);
        update_option('oh_doop_deletion_running', 0); // Clear the running flag
        
        $this->logger->log("Manual deletion process completed. Deleted $result products.");
        
        // Redirect to the results page
        wp_redirect(add_query_arg(
            array(
                'page' => 'doop-settings',
                'deletion_status' => 'completed',
                'deleted' => $result,
                't' => time()
            ),
            admin_url('admin.php')
        ));
        exit;
    }
    
    /**
     * Run the scheduled deletion process
     * 
     * @return int Number of products deleted
     */
    public function run_scheduled_deletion() {
        $this->logger->log("Starting scheduled deletion process");
        $deleted = $this->processor->delete_old_out_of_stock_products();
        $this->update_last_cron_time();
        $this->logger->log("Scheduled deletion process completed. Deleted $deleted products.");
        return $deleted;
    }
}
