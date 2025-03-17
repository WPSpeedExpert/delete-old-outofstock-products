/**
     * Delete out-of-stock WooCommerce products older than the configured age, including images.
     */
    public function delete_old_out_of_stock_products() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Get options
        $options = get_option( DOOP_OPTIONS_KEY, array(
            'product_age' => 18,
            'delete_images' => 'yes',
        ) );

        $product_age = isset( $options['product_age'] ) ? absint( $options['product_age'] ) : 18;
        $delete_images = isset( $options['delete_images'] ) ? $options['delete_images'] : 'yes';

        $date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$product_age} months" ) );

        // Process in smaller batches to reduce memory usage
        $batch_size = 50;
        $offset = 0;
        $processed = 0;
        $deleted = 0;
        
        // Get total for manual run
        $total_count = 0;
        if ( $this->is_manual_run ) {
            $count_query = new WP_Query( array(
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
            $total_count = $count_query->found_posts;
            
            // Update progress with total
            $this->update_progress( array(
                'total' => $total_count,
                'status' => 'processing',
                'message' => sprintf( 
                    __( 'Processing %d eligible products...', 'delete-old-outofstock-products' ),
                    $total_count
                )
            ) );
        }
        
        do {
            $products = get_posts( array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => $batch_size,
                'offset'         => $offset,
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
            
            if ( empty( $products ) ) {
                break;
            }
            
            foreach ( $products as $product_id ) {
                $product = wc_get_product( $product_id );

                if ( ! $product ) {
                    $processed++;
                    continue;
                }

                // Process product images if enabled
                if ( 'yes' === $delete_images ) {
                    $attachment_ids = array();

                    // Featured image
                    $featured_image_id = $product->get_image_id();
                    if ( $featured_image_id ) {
                        $attachment_ids[] = $featured_image_id;
                    }

                    // Gallery images
                    $attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );

                    foreach ( $attachment_ids as $attachment_id ) {
                        if ( $attachment_id ) {
                            // Skip placeholder images
                            $attachment_url = wp_get_attachment_url( $attachment_id );
                            if ( $attachment_url && $this->is_placeholder_image( $attachment_url ) ) {
                                continue;
                            }
                            
                            // Check if the image is used by other products or posts
                            if ( $this->is_attachment_used_elsewhere( $attachment_id, $product_id ) ) {
                                continue;
                            }
                            
                            // Delete the attachment
                            wp_delete_attachment( $attachment_id, true );
                        }
                    }
                }

                // Delete the product
                wp_delete_post( $product_id, true );
                $deleted++;
                
                // Update progress for manual run
                if ( $this->is_manual_run && $deleted % 5 === 0 ) { // Update every 5 deletions
                    $processed += 5;
                    $this->update_progress( array(
                        'processed' => $processed,
                        'deleted' => $deleted,
                        'message' => sprintf( 
                            __( 'Processed %1$d of %2$d products. Deleted %3$d products.', 'delete-old-outofstock-products' ),
                            $processed,
                            $total_count,
                            $deleted
                        )
                    ) );
                }
            }
            
            $processed = $offset + count( $products );
            
            // Update progress for manual run
            if ( $this->is_manual_run ) {
                $this->update_progress( array(
                    'processed' => $processed,
                    'deleted' => $deleted,
                    'message' => sprintf( 
                        __( 'Processed %1$d of %2$d products. Deleted %3$d products.', 'delete-old-outofstock-products' ),
                        $processed,
                        $total_count,
                        $deleted
                    )
                ) );
            }
            
            $offset += $batch_size;
            
            // Free up memory
            wp_cache_flush();
            
        } while ( count( $products ) === $batch_size );
        
        // Final update for manual run
        if ( $this->is_manual_run ) {
            $this->update_progress( array(
                'processed' => $total_count,
                'deleted' => $deleted,
                'percentage' => 100,
                'status' => 'complete',
                'message' => sprintf( 
                    __( 'Cleanup complete! Processed %1$d products. Deleted %2$d products.', 'delete-old-outofstock-products' ),
                    $total_count,
                    $deleted
                )
            ) );
        }
        
        return $deleted;
    }run ) {
            $count_query = new WP_Query( array(
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
            $total_count = $count_query->found_posts;
            
            // Update progress with total
            $this->update_progress( array(
                'total' => $total_count,
                'status' => 'processing',
                'message' => sprintf( 
                    __( 'Processing %d eligible products...', 'delete-old-outofstock-products' ),
                    $total_count
                )
            ) );
        }
        
        do {
            $products = get_posts( array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => $batch_size,
                'offset'         => $offset,
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
            
            if ( empty( $products ) ) {
                break;
            }
            
            foreach ( $products as $product_id ) {
                $product = wc_get_product( $product_id );

                if ( ! $product ) {
                    $processed++;
                    continue;
                }

                // Process product images if enabled
                if ( 'yes' === $delete_images ) {
                    $attachment_ids = array();

                    // Featured image
                    $featured_image_id = $product->get_image_id();
                    if ( $featured_image_id ) {
                        $attachment_ids[] = $featured_image_id;
                    }

                    // Gallery images
                    $attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );

                    foreach ( $attachment_ids as $attachment_id ) {
                        if ( $attachment_id ) {
                            // Skip placeholder images
                            $attachment_url = wp_get_attachment_url( $attachment_id );
                            if ( $attachment_url && $this->is_placeholder_image( $attachment_url ) ) {
                                continue;
                            }
                            
                            // Check if the image is used by other products or posts
                            if ( $this->is_attachment_used_elsewhere( $attachment_id, $product_id ) ) {
                                continue;
                            }
                            
                            // Delete the attachment
                            wp_delete_attachment( $attachment_id, true );
                        }
                    }
                }

                // Delete the product
                wp_delete_post( $product_id, true );
                $deleted++;
                
                // Update progress for manual run
                if ( $this->is_manual_<?php
/**
 * Plugin Name:        Delete Old Out-of-Stock Products
 * Plugin URI:         https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * Description:        Automatically deletes WooCommerce products that are out of stock and older than a configurable time period, including their images.
 * Version:            1.3.0
 * Author:             OctaHexa
 * Author URI:         https://octahexa.com
 * Text Domain:        delete-old-outofstock-products
 * License:            GPL-2.0+
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI:  https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * GitHub Branch:      main
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DOOP_VERSION', '1.3.0' );
define( 'DOOP_PLUGIN_FILE', __FILE__ );
define( 'DOOP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DOOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DOOP_CRON_HOOK', 'doop_cron_delete_old_products' );
define( 'DOOP_OPTIONS_KEY', 'oh_doop_options' );

/**
 * Class to manage plugin functionality
 */
class OH_Delete_Old_Outofstock_Products {

    /**
     * Plugin instance
     *
     * @var OH_Delete_Old_Outofstock_Products
     */
    private static $instance = null;

    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Flag to track if a manual process is running
     *
     * @var bool
     */
    private $is_manual_run = false;
    
    /**
     * Task ID for tracking progress
     *
     * @var string
     */
    private $task_id = '';

    /**
     * Get single instance of the plugin
     *
     * @return OH_Delete_Old_Outofstock_Products
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
        $this->set_default_options();

        // Plugin activation and deactivation hooks
        register_activation_hook( DOOP_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( DOOP_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Add settings page and menu
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Cron action
        add_action( DOOP_CRON_HOOK, array( $this, 'delete_old_out_of_stock_products' ) );
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $default_options = array(
            'product_age' => 18, // Default: 18 months
            'delete_images' => 'yes', // Default: Yes
        );

        $this->options = get_option( DOOP_OPTIONS_KEY, $default_options );
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

        // Schedule the cron event
        if ( ! wp_next_scheduled( DOOP_CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', DOOP_CRON_HOOK );
        }

        // Create required directories
        $this->create_plugin_directories();
    }

    /**
     * Create required plugin directories
     */
    private function create_plugin_directories() {
        // Create assets directory structure
        $dirs = array(
            DOOP_PLUGIN_DIR . 'assets',
            DOOP_PLUGIN_DIR . 'assets/js',
            DOOP_PLUGIN_DIR . 'assets/css',
        );

        foreach ( $dirs as $dir ) {
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }
        }

        // Create admin.js if it doesn't exist
        $js_file = DOOP_PLUGIN_DIR . 'assets/js/admin.js';
        if ( ! file_exists( $js_file ) ) {
            $js_content = <<<'EOT'
jQuery(document).ready(function($) {
    // Progress bar functionality
    if (typeof ohDoopTaskId !== 'undefined') {
        var progressInterval;
        var taskCompleted = false;
        
        function updateProgress() {
            $.ajax({
                url: ohDoopAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'oh_doop_deletion_progress',
                    nonce: ohDoopAdmin.nonce,
                    task_id: ohDoopTaskId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var data = response.data;
                        
                        // Update the progress bar
                        $('.oh-doop-progress-bar').css('width', data.percentage + '%').text(data.percentage + '%');
                        
                        // Update status text
                        $('.status-text').text(data.status);
                        
                        // Update counters
                        $('.products-processed').text(data.processed);
                        $('.products-total').text(data.total);
                        $('.products-deleted').text(data.deleted);
                        
                        // Update message
                        $('.oh-doop-progress-message').text(data.message);
                        
                        // Check if process is complete
                        if (data.status === 'complete' || data.status === 'error') {
                            clearInterval(progressInterval);
                            taskCompleted = true;
                            $('.oh-doop-progress-buttons').show();
                            
                            if (data.status === 'complete') {
                                $('.oh-doop-progress-bar').addClass('complete');
                            } else {
                                $('.oh-doop-progress-bar').addClass('error');
                            }
                        }
                    }
                },
                error: function() {
                    // Handle error
                    $('.oh-doop-progress-message').text(ohDoopAdmin.error);
                    $('.oh-doop-progress-bar').addClass('error');
                    clearInterval(progressInterval);
                    $('.oh-doop-progress-buttons').show();
                }
            });
        }
        
        // Start progress updates
        updateProgress(); // Initial update
        progressInterval = setInterval(function() {
            if (!taskCompleted) {
                updateProgress();
            } else {
                clearInterval(progressInterval);
            }
        }, 2000); // Update every 2 seconds
    }
});
EOT;
            file_put_contents($js_file, $js_content);
        }

        // Create admin.css if it doesn't exist
        $css_file = DOOP_PLUGIN_DIR . 'assets/css/admin.css';
        if ( ! file_exists( $css_file ) ) {
            $css_content = <<<'EOT'
/* Progress bar styles */
.oh-doop-progress-container {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.oh-doop-progress-bar-container {
    width: 100%;
    height: 25px;
    background-color: #f0f0f0;
    border-radius: 4px;
    margin: 15px 0;
    overflow: hidden;
}

.oh-doop-progress-bar {
    height: 100%;
    background-color: #2271b1;
    border-radius: 4px;
    text-align: center;
    line-height: 25px;
    color: white;
    font-weight: bold;
    transition: width 0.5s;
}

.oh-doop-progress-bar.complete {
    background-color: #46b450;
}

.oh-doop-progress-bar.error {
    background-color: #dc3232;
}

.oh-doop-progress-status {
    margin: 15px 0;
}

.oh-doop-progress-message {
    margin: 15px 0;
    font-style: italic;
}

.oh-doop-progress-buttons {
    margin: 15px 0;
}

/* Stats table styles */
.oh-doop-stats table {
    width: auto;
    min-width: 50%;
    margin-bottom: 15px;
}

.oh-doop-stats td {
    padding: 10px 15px;
}

/* Manual run section */
.oh-doop-manual-run {
    margin-top: 30px;
    padding-top: 10px;
}
EOT;
            file_put_contents($css_file, $css_content);
        }
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
        
        // Clear any manual run schedules
        $timestamp = wp_next_scheduled( 'oh_doop_manual_run' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'oh_doop_manual_run' );
        }
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __( 'Delete Old Products', 'delete-old-outofstock-products' ),
            __( 'Delete Old Products', 'delete-old-outofstock-products' ),
            'manage_options',
            'doop-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'doop_settings_group',
            DOOP_OPTIONS_KEY,
            array( $this, 'sanitize_options' )
        );

        add_settings_section(
            'doop_stats_section',
            __( 'Product Statistics', 'delete-old-outofstock-products' ),
            array( $this, 'stats_section_callback' ),
            'doop-settings'
        );

        add_settings_section(
            'doop_main_section',
            __( 'Product Deletion Settings', 'delete-old-outofstock-products' ),
            array( $this, 'section_callback' ),
            'doop-settings'
        );

        add_settings_field(
            'product_age',
            __( 'Product Age (months)', 'delete-old-outofstock-products' ),
            array( $this, 'product_age_callback' ),
            'doop-settings',
            'doop_main_section'
        );

        add_settings_field(
            'delete_images',
            __( 'Delete Product Images', 'delete-old-outofstock-products' ),
            array( $this, 'delete_images_callback' ),
            'doop-settings',
            'doop_main_section'
        );
    }

    /**
     * Sanitize options
     *
     * @param array $input The input options.
     * @return array
     */
    public function sanitize_options( $input ) {
        $output = array();
        
        // Sanitize product age (ensure it's a positive integer)
        $output['product_age'] = isset( $input['product_age'] ) ? absint( $input['product_age'] ) : 18;
        if ( $output['product_age'] < 1 ) {
            $output['product_age'] = 18;
        }
        
        // Sanitize delete images option
        $output['delete_images'] = isset( $input['delete_images'] ) ? 'yes' : 'no';
        
        return $output;
    }

    /**
     * Stats section description callback
     */
    public function stats_section_callback() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<div class="notice notice-warning inline"><p>';
            esc_html_e( 'WooCommerce is not active. Statistics are only available when WooCommerce is active.', 'delete-old-outofstock-products' );
            echo '</p></div>';
            return;
        }

        $options = get_option( DOOP_OPTIONS_KEY, array( 'product_age' => 18 ) );
        $product_age = isset( $options['product_age'] ) ? absint( $options['product_age'] ) : 18;
        $date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$product_age} months" ) );

        // Get total number of products
        $total_products = wp_count_posts( 'product' );
        $total_published = $total_products->publish;

        // Get number of out of stock products
        $out_of_stock_query = new WP_Query( array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_stock_status',
                    'value' => 'outofstock',
                ),
            ),
        ) );
        $out_of_stock_count = $out_of_stock_query->found_posts;

        // Get number of old products
        $old_products_query = new WP_Query( array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'date_query'     => array(
                array(
                    'before' => $date_threshold,
                ),
            ),
        ) );
        $old_products_count = $old_products_query->found_posts;

        // Get eligible for deletion
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
        
        // Display the stats
        ?>
        <div class="oh-doop-stats">
            <table class="widefat striped">
                <tr>
                    <td><strong><?php esc_html_e( 'Total Products:', 'delete-old-outofstock-products' ); ?></strong></td>
                    <td><?php echo esc_html( $total_published ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Out of Stock Products:', 'delete-old-outofstock-products' ); ?></strong></td>
                    <td><?php echo esc_html( $out_of_stock_count ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php 
                        /* translators: %d: product age in months */
                        printf( esc_html__( 'Products Older Than %d Months:', 'delete-old-outofstock-products' ), $product_age ); 
                    ?></strong></td>
                    <td><?php echo esc_html( $old_products_count ); ?></td>
                </tr>
                <tr>
                    <td style="background-color: #fef1f1;"><strong><?php esc_html_e( 'Products Eligible for Deletion:', 'delete-old-outofstock-products' ); ?></strong></td>
                    <td style="background-color: #fef1f1;"><strong><?php echo esc_html( $eligible_count ); ?></strong></td>
                </tr>
            </table>
            <p class="description">
                <?php esc_html_e( 'The "Products Eligible for Deletion" count shows how many products will be deleted on the next automatic run.', 'delete-old-outofstock-products' ); ?>
            </p>
        </div>
        <style>
            .oh-doop-stats table {
                width: auto;
                min-width: 50%;
                margin-bottom: 15px;
            }
            .oh-doop-stats td {
                padding: 10px 15px;
            }
        </style>
        <?php
    }

    /**
     * Section description callback
     */
    public function section_callback() {
        echo '<p>' . esc_html__( 'Configure the settings for automatic deletion of old out-of-stock products.', 'delete-old-outofstock-products' ) . '</p>';
    }

    /**
     * Product age field callback
     */
    public function product_age_callback() {
        $product_age = isset( $this->options['product_age'] ) ? $this->options['product_age'] : 18;
        ?>
        <input type="number" id="product_age" name="<?php echo esc_attr( DOOP_OPTIONS_KEY ); ?>[product_age]" value="<?php echo esc_attr( $product_age ); ?>" min="1" step="1" />
        <p class="description"><?php esc_html_e( 'Products older than this many months will be deleted if they are out of stock.', 'delete-old-outofstock-products' ); ?></p>
        <?php
    }

    /**
     * Delete images checkbox callback
     */
    public function delete_images_callback() {
        $delete_images = isset( $this->options['delete_images'] ) ? $this->options['delete_images'] : 'yes';
        ?>
        <label for="delete_images">
            <input type="checkbox" id="delete_images" name="<?php echo esc_attr( DOOP_OPTIONS_KEY ); ?>[delete_images]" <?php checked( $delete_images, 'yes' ); ?> />
            <?php esc_html_e( 'Delete associated product images when deleting products', 'delete-old-outofstock-products' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'This will delete featured images and gallery images associated with the product.', 'delete-old-outofstock-products' ); ?></p>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php if ( isset( $_GET['manual_run'] ) && $_GET['manual_run'] == 'true' ) : ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e( 'Product cleanup has been manually triggered. Eligible products will be deleted shortly.', 'delete-old-outofstock-products' ); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ( isset( $_GET['manual_run'] ) && $_GET['manual_run'] == 'progress' && isset( $_GET['task_id'] ) ) : ?>
                <div class="oh-doop-progress-container">
                    <h2><?php esc_html_e( 'Product Cleanup Progress', 'delete-old-outofstock-products' ); ?></h2>
                    <div class="oh-doop-progress-bar-container">
                        <div class="oh-doop-progress-bar" style="width: 0%;">0%</div>
                    </div>
                    <div class="oh-doop-progress-status">
                        <p><strong><?php esc_html_e( 'Status:', 'delete-old-outofstock-products' ); ?></strong> <span class="status-text"><?php esc_html_e( 'Initializing...', 'delete-old-outofstock-products' ); ?></span></p>
                        <p><strong><?php esc_html_e( 'Products Processed:', 'delete-old-outofstock-products' ); ?></strong> <span class="products-processed">0</span> / <span class="products-total">0</span></p>
                        <p><strong><?php esc_html_e( 'Products Deleted:', 'delete-old-outofstock-products' ); ?></strong> <span class="products-deleted">0</span></p>
                    </div>
                    <p class="oh-doop-progress-message"><?php esc_html_e( 'Please wait while we process your request...', 'delete-old-outofstock-products' ); ?></p>
                    <div class="oh-doop-progress-buttons" style="display: none;">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=doop-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Back to Settings', 'delete-old-outofstock-products' ); ?></a>
                    </div>
                    <script>
                        var ohDoopTaskId = '<?php echo esc_js( $_GET['task_id'] ); ?>';
                    </script>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'doop_settings_group' );
                do_settings_sections( 'doop-settings' );
                submit_button();
                ?>
            </form>
            
            <div class="oh-doop-manual-run">
                <hr />
                <h2><?php esc_html_e( 'Manual Run', 'delete-old-outofstock-products' ); ?></h2>
                <p><?php esc_html_e( 'Click the button below to manually run the deletion process right now.', 'delete-old-outofstock-products' ); ?></p>
                <p><?php esc_html_e( 'The process will run in the background and you can view the progress in real-time.', 'delete-old-outofstock-products' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="oh_run_product_deletion">
                    <?php wp_nonce_field( 'oh_run_product_deletion_nonce', 'oh_nonce' ); ?>
                    <?php submit_button( __( 'Run Product Cleanup Now', 'delete-old-outofstock-products' ), 'primary', 'run_now', false ); ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Add settings link to plugin action links
     *
     * @param array $links Existing plugin action links.
     * @return array Modified plugin action links.
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=doop-settings' ) ),
            esc_html__( 'Settings', 'delete-old-outofstock-products' )
        );
        return array_merge( array( $settings_link ), $links );
    }

    /**
     * Process manual run with progress tracking
     * 
     * @param string $task_id The unique task ID for tracking progress.
     */
    public function process_manual_run( $task_id ) {
        // Flag this as a manual run
        $this->is_manual_run = true;
        $this->task_id = $task_id;
        
        // Initialize progress
        $this->update_progress( array(
            'percentage' => 0,
            'processed' => 0,
            'total' => 0,
            'deleted' => 0,
            'status' => 'starting',
            'message' => __( 'Starting product cleanup process...', 'delete-old-outofstock-products' )
        ) );
        
        // Run the deletion process
        $this->delete_old_out_of_stock_products();
    }
    
    /**
     * Update progress data for manual run
     * 
     * @param array $data The progress data to update.
     */
    private function update_progress( $data ) {
        if ( ! $this->is_manual_run || empty( $this->task_id ) ) {
            return;
        }
        
        $current = get_transient( 'oh_doop_deletion_progress_' . $this->task_id );
        
        if ( false === $current ) {
            $current = array(
                'percentage' => 0,
                'processed' => 0,
                'total' => 0,
                'deleted' => 0,
                'status' => 'starting',
                'message' => __( 'Starting process...', 'delete-old-outofstock-products' )
            );
        }
        
        // Merge new data with current data
        $updated = wp_parse_args( $data, $current );
        
        // Calculate percentage if not provided
        if ( ! isset( $data['percentage'] ) && $updated['total'] > 0 ) {
            $updated['percentage'] = round( ( $updated['processed'] / $updated['total'] ) * 100 );
        }
        
        // Update the transient
        set_transient( 'oh_doop_deletion_progress_' . $this->task_id, $updated, HOUR_IN_SECONDS );
    }
    }

    /**
     * Enqueue admin scripts
     * 
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_doop-settings' !== $hook ) {
            return;
        }
        
        wp_enqueue_script(
            'oh-doop-admin',
            DOOP_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            DOOP_VERSION,
            true
        );
        
        wp_localize_script(
            'oh-doop-admin',
            'ohDoopAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'oh_doop_ajax_nonce' ),
                'processing' => __( 'Processing...', 'delete-old-outofstock-products' ),
                'complete' => __( 'Complete!', 'delete-old-outofstock-products' ),
                'error' => __( 'Error', 'delete-old-outofstock-products' )
            )
        );
        
        wp_enqueue_style(
            'oh-doop-admin-css',
            DOOP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DOOP_VERSION
        );
    }

    /**
     * AJAX handler for deletion progress
     */
    public function ajax_deletion_progress() {
        check_ajax_referer( 'oh_doop_ajax_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        // Get the task ID
        $task_id = isset( $_POST['task_id'] ) ? sanitize_text_field( $_POST['task_id'] ) : '';
        
        if ( empty( $task_id ) ) {
            wp_send_json_error( 'Invalid task ID' );
        }
        
        // Get the progress data
        $progress = get_transient( 'oh_doop_deletion_progress_' . $task_id );
        
        if ( false === $progress ) {
            wp_send_json_error( 'No progress data found' );
        }
        
        wp_send_json_success( $progress );
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
        
        // Generate a unique task ID
        $task_id = uniqid( 'task_' );
        
        // Initialize progress
        set_transient( 'oh_doop_deletion_progress_' . $task_id, array(
            'percentage' => 0,
            'processed' => 0,
            'total' => 0,
            'deleted' => 0,
            'status' => 'starting',
            'message' => __( 'Starting process...', 'delete-old-outofstock-products' )
        ), HOUR_IN_SECONDS );
        
        // Schedule the immediate execution
        wp_schedule_single_event( time(), 'oh_doop_manual_run', array( $task_id ) );
        
        // Redirect back to the settings page with the task ID
        wp_safe_redirect( add_query_arg( 
            array(
                'page' => 'doop-settings',
                'manual_run' => 'progress',
                'task_id' => $task_id
            ), 
            admin_url( 'admin.php' ) 
        ) );
        exit;
    }

    /**
     * Check if the given URL is a placeholder image
     *
     * @param string $url The image URL to check
     * @return bool
     */
    private function is_placeholder_image( $url ) {
        // Check if URL contains 'woocommerce-placeholder'
        if ( false !== strpos( $url, 'woocommerce-placeholder' ) ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if an attachment is used by other posts/products
     *
     * @param int $attachment_id The attachment ID to check
     * @param int $excluded_product_id The product ID to exclude from the check
     * @return bool
     */
    private function is_attachment_used_elsewhere( $attachment_id, $excluded_product_id ) {
        global $wpdb;
        
        // Check if the attachment is used as featured image for other posts
        $query = $wpdb->prepare(
            "SELECT COUNT(meta_id) FROM $wpdb->postmeta 
            WHERE meta_key = '_thumbnail_id' 
            AND meta_value = %d 
            AND post_id != %d",
            $attachment_id,
            $excluded_product_id
        );
        
        $count = $wpdb->get_var( $query );
        
        if ( $count > 0 ) {
            return true;
        }
        
        // Check if attachment is used in product galleries
        $query = $wpdb->prepare(
            "SELECT COUNT(meta_id) FROM $wpdb->postmeta 
            WHERE meta_key = '_product_image_gallery' 
            AND meta_value LIKE %s 
            AND post_id != %d",
            '%' . $wpdb->esc_like( $attachment_id ) . '%',
            $excluded_product_id
        );
        
        $count = $wpdb->get_var( $query );
        
        if ( $count > 0 ) {
            return true;
        }
        
        // Check for usage in post content
        $attachment_url = wp_get_attachment_url( $attachment_id );
        
        if ( $attachment_url ) {
            $attachment_url_parts = explode( '/', $attachment_url );
            $attachment_file = end( $attachment_url_parts );
            
            $query = $wpdb->prepare(
                "SELECT COUNT(ID) FROM $wpdb->posts 
                WHERE post_content LIKE %s 
                AND ID != %d",
                '%' . $wpdb->esc_like( $attachment_file ) . '%',
                $excluded_product_id
            );
            
            $count = $wpdb->get_var( $query );
            
            if ( $count > 0 ) {
                return true;
            }
        }
        
        return false;
    }
}

// Initialize the plugin
function oh_doop_init() {
    OH_Delete_Old_Outofstock_Products::get_instance();
    
    // Register scheduled task for manual run
    add_action( 'oh_doop_manual_run', array( OH_Delete_Old_Outofstock_Products::get_instance(), 'process_manual_run' ) );
}
add_action( 'plugins_loaded', 'oh_doop_init' );
