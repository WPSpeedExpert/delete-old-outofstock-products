<?php
/**
 * Main plugin class file
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
 *
 * Handles all the plugin functionality in an OOP way
 *
 * @since 2.0.0
 */
class OH_Deletion_Plugin {

    /**
     * Plugin version
     *
     * @var string
     */
    private $version = '2.3.0';

    /**
     * Cron hook name
     *
     * @var string
     */
    private $cron_hook = 'oh_cron_delete_old_products';

    /**
     * Admin UI instance
     * 
     * @var OH_Admin_UI
     */
    private $admin_ui;

    /**
     * Logger instance
     * 
     * @var OH_Logger
     */
    private $logger;

    /**
     * Plugin instance
     *
     * @var OH_Deletion_Plugin
     */
    private static $instance = null;

    /**
     * Get plugin instance
     * 
     * @return OH_Deletion_Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Class constructor
     */
    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
        $this->include_files();
        $this->init_components();
    }

    /**
     * Define plugin constants
     */
    private function define_constants() {
        if ( ! defined( 'DOOP_VERSION' ) ) {
            define( 'DOOP_VERSION', $this->version );
        }
        
        if ( ! defined( 'DOOP_CRON_HOOK' ) ) {
            define( 'DOOP_CRON_HOOK', $this->cron_hook );
        }

        if ( ! defined( 'DOOP_PLUGIN_DIR' ) ) {
            define( 'DOOP_PLUGIN_DIR', plugin_dir_path( DOOP_PLUGIN_FILE ) );
        }

        if ( ! defined( 'DOOP_PLUGIN_URL' ) ) {
            define( 'DOOP_PLUGIN_URL', plugin_dir_url( DOOP_PLUGIN_FILE ) );
        }

        if ( ! defined( 'DOOP_OPTIONS_KEY' ) ) {
            define( 'DOOP_OPTIONS_KEY', 'oh_doop_options' );
        }

        if ( ! defined( 'DOOP_PROCESS_OPTION' ) ) {
            define( 'DOOP_PROCESS_OPTION', 'oh_doop_process_running' );
        }

        if ( ! defined( 'DOOP_RESULT_OPTION' ) ) {
            define( 'DOOP_RESULT_OPTION', 'oh_doop_process_result' );
        }
    }

    /**
     * Include required files
     */
    private function include_files() {
        require_once DOOP_PLUGIN_DIR . 'includes/class-oh-logger.php';
        require_once DOOP_PLUGIN_DIR . 'includes/class-oh-admin-ui.php';
    }

    /**
     * Initialize components
     */
    private function init_components() {
        $this->logger = OH_Logger::get_instance();
        $this->admin_ui = new OH_Admin_UI();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Add cron action
        add_action( $this->cron_hook, array( $this, 'cron_deletion_handler' ) );
        
        // Register activation and deactivation hooks
        register_activation_hook( DOOP_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( DOOP_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // AJAX endpoints for manual run
        add_action( 'wp_ajax_oh_run_manual_cleanup', array( $this, 'ajax_run_manual_cleanup' ) );
    }

    /**
     * Plugin activation hook
     */
    public function activate() {
        // Schedule cron if not already scheduled
        if ( ! wp_next_scheduled( $this->cron_hook ) ) {
            wp_schedule_event( time(), 'daily', $this->cron_hook );
        }

        // Create default options if they don't exist
        if ( ! get_option( DOOP_OPTIONS_KEY ) ) {
            update_option( DOOP_OPTIONS_KEY, array(
                'product_age'   => 18,
                'delete_images' => 'yes'
            ));
        }
    }

    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Clear scheduled cron event
        $timestamp = wp_next_scheduled( $this->cron_hook );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $this->cron_hook );
        }
    }

    /**
     * AJAX handler for manual cleanup
     */
    public function ajax_run_manual_cleanup() {
        // Check nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'oh_run_product_deletion_nonce' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'delete-old-outofstock-products' )
            ) );
        }

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'delete-old-outofstock-products' )
            ) );
        }

        // Check if a process is already running
        if ( get_option( DOOP_PROCESS_OPTION, false ) && get_option( DOOP_PROCESS_OPTION, false ) !== 0 ) {
            wp_send_json_error( array(
                'message' => __( 'A cleanup process is already running. Please wait for it to complete.', 'delete-old-outofstock-products' )
            ) );
        }

        // Set manual process flag
        update_option( 'oh_doop_manual_process', true );

        // Check if there are too many products
        $options = get_option( DOOP_OPTIONS_KEY, array( 'product_age' => 18 ) );
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
        
        // If there are too many products, don't run manually
        if ( $eligible_count > 200 ) {
            update_option( 'oh_doop_too_many_products', $eligible_count );
            delete_option( 'oh_doop_manual_process' );
            
            wp_send_json_success( array(
                'message' => __( 'Too many products to delete manually. Refresh the page to see details.', 'delete-old-outofstock-products' ),
                'redirect' => add_query_arg( 
                    array(
                        'page' => 'doop-settings',
                        'deletion_status' => 'too_many',
                        'count' => $eligible_count
                    ),
                    admin_url( 'admin.php' )
                )
            ) );
        }

        // Start the process
        update_option( DOOP_PROCESS_OPTION, time() );
        delete_option( DOOP_RESULT_OPTION );

        // Run the deletion process in background via wp_cron
        wp_schedule_single_event( time(), 'oh_manual_run_deletion', array( 'manual' ) );
        
        // Return success
        wp_send_json_success( array(
            'message' => __( 'Product cleanup process started. You will be notified when it completes.', 'delete-old-outofstock-products' ),
            'redirect' => add_query_arg( 
                array(
                    'page' => 'doop-settings',
                    'deletion_status' => 'running',
                    'manual' => 1
                ),
                admin_url( 'admin.php' )
            )
        ) );
    }

    /**
     * Handler for cron execution
     */
    public function cron_deletion_handler() {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            $this->logger->log( 'Cron job ran but WooCommerce is not active.' );
            return;
        }

        // Get options
        $options = get_option( DOOP_OPTIONS_KEY, array(
            'product_age'   => 18,
            'delete_images' => 'yes'
        ));

        // Set current time as the start of the process
        update_option( 'oh_doop_last_cron_time', time() );

        // Mark as running
        update_option( DOOP_PROCESS_OPTION, time() );

        $this->logger->log( 'Starting automatic deletion process via cron.' );
        
        // Run the deletion
        $count = $this->delete_old_out_of_stock_products( $options );
        
        // Save the result
        update_option( DOOP_RESULT_OPTION, $count );
        
        // Mark as completed
        update_option( DOOP_PROCESS_OPTION, 0 );
        
        $this->logger->log( "Automatic deletion completed. $count products deleted." );
    }

    /**
     * Delete out-of-stock WooCommerce products older than specified age, including images if specified.
     * 
     * @param array $options Plugin options array
     * @return int Number of deleted products
     */
    public function delete_old_out_of_stock_products( $options ) {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            $this->logger->log( 'WooCommerce is not active. Deletion process aborted.' );
            return 0;
        }

        // Get settings
        $product_age = isset( $options['product_age'] ) ? absint( $options['product_age'] ) : 18;
        $delete_images = isset( $options['delete_images'] ) ? $options['delete_images'] === 'yes' : true;
        
        // Set date threshold
        $date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$product_age} months" ) );
        $this->logger->log( "Looking for products older than $product_age months (before $date_threshold)" );
        
        // Get old out-of-stock products
        $query_args = array(
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
        );
        
        $products = get_posts( $query_args );
        $total_products = count( $products );
        
        $this->logger->log( "Found $total_products products to delete" );
        
        if ( $total_products === 0 ) {
            return 0;
        }
        
        $deleted_count = 0;
        
        // Process each product
        foreach ( $products as $product_id ) {
            $product = wc_get_product( $product_id );

            if ( ! $product ) {
                $this->logger->log( "Product ID $product_id not found. Skipping." );
                continue;
            }

            $this->logger->log( "Processing product: $product_id - " . $product->get_name() );
            
            // Get product images if needed
            if ( $delete_images ) {
                $attachment_ids = $this->get_product_image_ids( $product );
                
                if ( ! empty( $attachment_ids ) ) {
                    $this->logger->log( "Found " . count( $attachment_ids ) . " images for product $product_id" );
                    
                    // Delete each attachment
                    foreach ( $attachment_ids as $attachment_id ) {
                        if ( $attachment_id ) {
                            // Check if image is used elsewhere
                            if ( $this->is_attachment_used_elsewhere( $attachment_id, $product_id ) ) {
                                $this->logger->log( "Image ID $attachment_id is used elsewhere. Skipping deletion." );
                                continue;
                            }
                            
                            $result = wp_delete_attachment( $attachment_id, true );
                            if ( $result ) {
                                $this->logger->log( "Deleted image ID $attachment_id" );
                            } else {
                                $this->logger->log( "Failed to delete image ID $attachment_id" );
                            }
                        }
                    }
                } else {
                    $this->logger->log( "No images found for product $product_id" );
                }
            } else {
                $this->logger->log( "Image deletion is disabled. Skipping image deletion for product $product_id" );
            }

            // Delete the product
            $result = wp_delete_post( $product_id, true );
            if ( $result ) {
                $this->logger->log( "Successfully deleted product $product_id" );
                $deleted_count++;
            } else {
                $this->logger->log( "Failed to delete product $product_id" );
            }
        }

        $this->logger->log( "Deletion process completed. Deleted $deleted_count out of $total_products products." );
        
        return $deleted_count;
    }

    /**
     * Check if an attachment is used in any other posts
     * 
     * @param int $attachment_id Attachment ID to check
     * @param int $product_id Current product ID to exclude from check
     * @return bool True if used elsewhere, false if not
     */
    private function is_attachment_used_elsewhere( $attachment_id, $product_id ) {
        global $wpdb;
        
        // Check featured images
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->postmeta 
             WHERE meta_key = '_thumbnail_id' 
             AND meta_value = %d 
             AND post_id != %d",
            $attachment_id,
            $product_id
        );
        
        $featured_image_count = $wpdb->get_var( $query );
        
        if ( $featured_image_count > 0 ) {
            return true;
        }
        
        // Check gallery images
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->postmeta 
             WHERE meta_key = '_product_image_gallery' 
             AND meta_value LIKE %s 
             AND post_id != %d",
            '%' . $wpdb->esc_like( $attachment_id ) . '%',
            $product_id
        );
        
        $gallery_image_count = $wpdb->get_var( $query );
        
        if ( $gallery_image_count > 0 ) {
            return true;
        }
        
        // Check content references (images inserted in product description, etc.)
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->posts 
             WHERE post_content LIKE %s 
             AND ID != %d",
            '%wp-image-' . $wpdb->esc_like( $attachment_id ) . '%',
            $product_id
        );
        
        $content_references = $wpdb->get_var( $query );
        
        return $content_references > 0;
    }

    /**
     * Get all image IDs associated with a product
     *
     * @param WC_Product $product WooCommerce product object
     * @return array Array of attachment IDs
     */
    private function get_product_image_ids( $product ) {
        $attachment_ids = array();

        // Featured image
        $featured_image_id = $product->get_image_id();
        if ( $featured_image_id ) {
            $attachment_ids[] = $featured_image_id;
        }

        // Gallery images
        $gallery_image_ids = $product->get_gallery_image_ids();
        if ( ! empty( $gallery_image_ids ) ) {
            $attachment_ids = array_merge( $attachment_ids, $gallery_image_ids );
        }

        return array_unique( array_filter( $attachment_ids ) );
    }
}
