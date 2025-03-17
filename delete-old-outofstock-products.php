<?php
/**
 * Plugin Name:        Delete Old Out-of-Stock Products
 * Plugin URI:         https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * Description:        Automatically deletes WooCommerce products that are out of stock and older than a configurable time period, including their images.
 * Version:            1.4.1
 * Author:             OctaHexa
 * Author URI:         https://octahexa.com
 * Text Domain:        delete-old-outofstock-products
 * License:            GPL-2.0+
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI:  https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * GitHub Branch:      main
 *
 * Table of Contents:
 *
 * 1. BASIC SETUP
 *   1.1 Plugin Security
 *   1.2 Constants Definition
 *
 * 2. MAIN PLUGIN CLASS
 *   2.1 Class Properties
 *   2.2 Class Initialization
 *   2.3 Core Setup
 *   2.4 Admin Interface
 *   2.5 Product Deletion Logic
 *   2.6 Attachment Handling Helpers
 *
 * 3. PLUGIN INITIALIZATION
 */

//========================================//
// 1. BASIC SETUP                        //
//========================================//

// 1.1 Plugin Security
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1.2 Constants Definition
define( 'DOOP_VERSION', '1.4.1' );
define( 'DOOP_PLUGIN_FILE', __FILE__ );
define( 'DOOP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DOOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DOOP_CRON_HOOK', 'doop_cron_delete_old_products' );
define( 'DOOP_OPTIONS_KEY', 'oh_doop_options' );

//========================================//
// 2. MAIN PLUGIN CLASS                  //
//========================================//

/**
 * Class to manage plugin functionality
 */
class OH_Delete_Old_Outofstock_Products {

    //----------------------------------------//
    // 2.1 Class Properties
    //----------------------------------------//
    
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

    //----------------------------------------//
    // 2.2 Class Initialization
    //----------------------------------------//
    
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
        
        // Add settings link to plugins page
        add_filter( 'plugin_action_links', array( $this, 'add_settings_link' ), 10, 2 );

        // Handle manual run
        add_action( 'admin_post_oh_run_product_deletion', array( $this, 'handle_manual_run' ) );

        // Cron action
        add_action( DOOP_CRON_HOOK, array( $this, 'delete_old_out_of_stock_products' ) );
        
        // Add admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    //----------------------------------------//
    // 2.3 Core Setup
    //----------------------------------------//
    
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
    }

    //----------------------------------------//
    // 2.4 Admin Interface
    //----------------------------------------//
    
    /**
     * Enqueue admin scripts
     * 
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_doop-settings' !== $hook ) {
            return;
        }
        
        // Inline CSS for admin
        wp_add_inline_style( 'admin-bar', '
            .oh-doop-stats table {
                width: auto;
                min-width: 50%;
                margin-bottom: 15px;
            }
            .oh-doop-stats td {
                padding: 10px 15px;
            }
            .oh-doop-progress-spinner {
                display: none;
                margin-left: 10px;
                vertical-align: middle;
            }
            .oh-doop-progress-status {
                display: none;
                margin-top: 15px;
                padding: 10px;
                background-color: #f8f8f8;
                border-left: 4px solid #2271b1;
            }
            .oh-doop-progress-status.completed {
                border-left: 4px solid #46b450;
            }
            .oh-doop-progress-bar-container {
                width: 100%;
                height: 25px;
                background-color: #f0f0f0;
                border-radius: 4px;
                margin: 10px 0;
                overflow: hidden;
            }
            .oh-doop-progress-bar {
                height: 100%;
                background-color: #2271b1;
                width: 0%;
                text-align: center;
                line-height: 25px;
                color: white;
                font-weight: bold;
                transition: width 0.3s;
            }
            .oh-doop-progress-info {
                margin-top: 5px;
                font-style: italic;
            }
        ' );
        
        // Register and localize the custom script
        wp_register_script(
            'oh-doop-admin',
            '', // Blank source as we're using inline script
            array( 'jquery' ),
            DOOP_VERSION,
            true
        );
        
        // Localize script with translations and settings
        wp_localize_script(
            'oh-doop-admin',
            'ohDoopSettings',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'oh_doop_ajax_nonce' ),
                'strings' => array(
                    'starting' => __( 'Starting cleanup process...', 'delete-old-outofstock-products' ),
                    'analyzing' => __( 'Analyzing products...', 'delete-old-outofstock-products' ),
                    'processing' => __( 'Processing products...', 'delete-old-outofstock-products' ),
                    'deleting' => __( 'Deleting products...', 'delete-old-outofstock-products' ),
                    'deleted' => __( 'Deleted', 'delete-old-outofstock-products' ),
                    'of' => __( 'of', 'delete-old-outofstock-products' ),
                    'products' => __( 'products', 'delete-old-outofstock-products' ),
                    'completed' => __( 'Cleanup completed!', 'delete-old-outofstock-products' ),
                    'error' => __( 'An error occurred.', 'delete-old-outofstock-products' ),
                ),
            )
        );
        
        // Inline JS for admin
        wp_add_inline_script( 'oh-doop-admin', '
            jQuery(document).ready(function($) {
                var progressInterval;
                var $runButton = $("#run_now");
                var $progressSpinner = $(".oh-doop-progress-spinner");
                var $progressStatus = $(".oh-doop-progress-status");
                var $progressBar = $(".oh-doop-progress-bar");
                var $progressInfo = $(".oh-doop-progress-info");
                var requestCount = 0;
                var totalProducts = 0;
                var deletedProducts = 0;
                var isRunning = false;
                
                $("#oh-doop-run-form").on("submit", function(e) {
                    e.preventDefault();
                    
                    if (isRunning) return;
                    
                    if (confirm("Are you sure you want to run the product cleanup now?")) {
                        isRunning = true;
                        $runButton.prop("disabled", true);
                        $progressSpinner.show();
                        $progressStatus.show();
                        $progressStatus.html(
                            "<p><strong>' . esc_js( __( 'Status:', 'delete-old-outofstock-products' ) ) . '</strong> " + ohDoopSettings.strings.starting + "</p>" +
                            "<div class=\'oh-doop-progress-bar-container\'><div class=\'oh-doop-progress-bar\'>0%</div></div>" +
                            "<div class=\'oh-doop-progress-info\'>' . esc_js( __( 'Preparing to process products...', 'delete-old-outofstock-products' ) ) . '</div>"
                        );
                        
                        // First get eligible product count
                        $.ajax({
                            url: ohDoopSettings.ajaxurl,
                            type: "POST",
                            data: {
                                action: "oh_doop_get_eligible_count",
                                nonce: ohDoopSettings.nonce
                            },
                            success: function(response) {
                                if (response.success && response.data) {
                                    totalProducts = parseInt(response.data.count);
                                    deletedProducts = 0;
                                    
                                    // Set initial progress
                                    updateProgressBar(1); // Start with 1% to show movement
                                    
                                    // Update progress bar
                                    $progressStatus.find(".oh-doop-progress-info").text(
                                        ohDoopSettings.strings.processing + " " + 
                                        totalProducts + " " + ohDoopSettings.strings.products
                                    );
                                    
                                    // Start the deletion process
                                    startDeletion();
                                } else {
                                    handleError(response.data ? response.data.message : ohDoopSettings.strings.error);
                                }
                            },
                            error: function() {
                                handleError(ohDoopSettings.strings.error);
                            }
                        });
                    }
                });
                
                function startDeletion() {
                    // Run the actual deletion process
                    $.ajax({
                        url: ohDoopSettings.ajaxurl,
                        type: "POST",
                        data: {
                            action: "oh_run_product_deletion",
                            nonce: $("#oh_nonce").val()
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                completeDeletion(response.data.deleted);
                            } else {
                                handleError(response.data ? response.data.message : ohDoopSettings.strings.error);
                            }
                        },
                        error: function() {
                            handleError(ohDoopSettings.strings.error);
                        }
                    });
                    
                    // Start progress simulation
                    startProgressSimulation();
                }
                
                function startProgressSimulation() {
                    var progress = 0;
                    var increment = totalProducts > 0 ? (5 / totalProducts) * 100 : 5;
                    // Ensure minimum increment
                    if (increment < 1) increment = 1;
                    
                    // Initial update
                    updateProgressBar(progress);
                    
                    progressInterval = setInterval(function() {
                        // Don\'t go all the way to 100%
                        if (progress < 90) {
                            progress += increment;
                            if (progress > 90) progress = 90;
                            
                            // Update progress bar
                            updateProgressBar(progress);
                            
                            // Simulate deleted products count
                            deletedProducts = Math.floor((progress / 100) * totalProducts);
                            updateProgressInfo();
                        }
                    }, 1000);
                }
                
                function updateProgressBar(percentage) {
                    $progressStatus.find(".oh-doop-progress-bar").css("width", percentage + "%").text(Math.floor(percentage) + "%");
                }
                
                function updateProgressInfo() {
                    $progressStatus.find(".oh-doop-progress-info").text(
                        ohDoopSettings.strings.deleted + " " + 
                        deletedProducts + " " + 
                        ohDoopSettings.strings.of + " " + 
                        totalProducts + " " + 
                        ohDoopSettings.strings.products
                    );
                }
                
                function completeDeletion(deleted) {
                    // Clear the interval
                    clearInterval(progressInterval);
                    
                    // Update progress to 100%
                    updateProgressBar(100);
                    
                    // Update status
                    $progressStatus.addClass("completed");
                    $progressStatus.find("p strong").next().text(ohDoopSettings.strings.completed);
                    
                    // Update info text
                    $progressStatus.find(".oh-doop-progress-info").text(
                        ohDoopSettings.strings.deleted + " " + 
                        deleted + " " + 
                        ohDoopSettings.strings.of + " " + 
                        totalProducts + " " + 
                        ohDoopSettings.strings.products
                    );
                    
                    // Reset UI
                    $runButton.prop("disabled", false);
                    $progressSpinner.hide();
                    isRunning = false;
                }
                
                function handleError(message) {
                    clearInterval(progressInterval);
                    $progressStatus.find("p strong").next().text(message || ohDoopSettings.strings.error);
                    $runButton.prop("disabled", false);
                    $progressSpinner.hide();
                    isRunning = false;
                }
            });
        ' );
        
        // Enqueue the script
        wp_enqueue_script( 'oh-doop-admin' );
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
     * Add settings link to plugin action links
     *
     * @param array $links Existing plugin action links.
     * @param string $file Plugin file path.
     * @return array Modified plugin action links.
     */
    public function add_settings_link( $links, $file ) {
        if ( plugin_basename( DOOP_PLUGIN_FILE ) === $file ) {
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                esc_url( admin_url( 'admin.php?page=doop-settings' ) ),
                esc_html__( 'Settings', 'delete-old-outofstock-products' )
            );
            array_unshift( $links, $settings_link );
        }
        return $links;
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
            
            <?php if ( isset( $_GET['deleted'] ) && $_GET['deleted'] > 0 ) : ?>
                <div class="notice notice-success">
                    <p>
                        <?php 
                        /* translators: %d: number of products deleted */
                        printf( 
                            esc_html__( 'Product cleanup completed. %d products were deleted.', 'delete-old-outofstock-products' ), 
                            intval( $_GET['deleted'] ) 
                        ); 
                        ?>
                    </p>
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
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="oh-doop-run-form">
                    <input type="hidden" name="action" value="oh_run_product_deletion">
                    <?php wp_nonce_field( 'oh_run_product_deletion_nonce', 'oh_nonce' ); ?>
                    <?php submit_button( __( 'Run Product Cleanup Now', 'delete-old-outofstock-products' ), 'primary', 'run_now', false ); ?>
                    <span class="spinner oh-doop-progress-spinner"></span>
                </form>
                <div class="oh-doop-progress-status"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle manual run of the product deletion process
     */
    public function handle_manual_run() {
        // Check nonce for security
        if ( 
            ! isset( $_POST['nonce'] ) && 
            ! isset( $_POST['oh_nonce'] ) 
        ) {
            wp_send_json_error( array( 
                'message' => __( 'Security check failed. Please try again.', 'delete-old-outofstock-products' )
            ) );
        }
        
        // Check nonce
        $nonce = isset( $_POST['nonce'] ) ? $_POST['nonce'] : $_POST['oh_nonce'];
        $is_valid = wp_verify_nonce( $nonce, isset( $_POST['nonce'] ) ? 'oh_doop_ajax_nonce' : 'oh_run_product_deletion_nonce' );
        
        if ( ! $is_valid || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 
                'message' => __( 'Security check failed. Please try again.', 'delete-old-outofstock-products' )
            ) );
        }
        
        // Check if this is the eligible count request
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'oh_doop_get_eligible_count' ) {
            $this->get_eligible_products_count();
            return;
        }
        
        // Run the deletion process
        $deleted = $this->delete_old_out_of_stock_products();
        
        wp_send_json_success( array(
            'status' => 'completed',
            'deleted' => $deleted,
            'message' => sprintf(
                /* translators: %d: number of products deleted */
                __( 'Product cleanup completed. %d products were deleted.', 'delete-old-outofstock-products' ),
                $deleted
            )
        ) );
    }
    
    /**
     * Get count of eligible products for deletion
     */
    private function get_eligible_products_count() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_send_json_error( array( 
                'message' => __( 'WooCommerce is not active.', 'delete-old-outofstock-products' )
            ) );
        }
        
        // Get options
        $options = get_option( DOOP_OPTIONS_KEY, array(
            'product_age' => 18,
        ) );

        $product_age = isset( $options['product_age'] ) ? absint( $options['product_age'] ) : 18;
        $date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$product_age} months" ) );
        
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
        
        wp_send_json_success( array(
            'count' => $eligible_count
        ) );
    }

    //----------------------------------------//
    // 2.5 Product Deletion Logic
    //----------------------------------------//
    
    /**
     * Delete out-of-stock WooCommerce products older than the configured age, including images.
     * 
     * @return int Number of products deleted
     */
    public function delete_old_out_of_stock_products() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return 0;
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
        $deleted = 0;
        
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
            }
            
            $offset += $batch_size;
            
            // Free up memory
            wp_cache_flush();
            
        } while ( count( $products ) === $batch_size );
        
        return $deleted;
    }

    //----------------------------------------//
    // 2.6 Attachment Handling Helpers
    //----------------------------------------//
    
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

//========================================//
// 3. PLUGIN INITIALIZATION              //
//========================================//

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
 * Initialize the plugin
 */
function oh_doop_init() {
    // Check if WooCommerce is active
    if ( ! oh_doop_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'oh_doop_admin_notice' );
        return;
    }
    
    // Initialize the plugin
    OH_Delete_Old_Outofstock_Products::get_instance();
    
    // Add AJAX handlers
    add_action( 'wp_ajax_oh_doop_get_eligible_count', array( OH_Delete_Old_Outofstock_Products::get_instance(), 'handle_manual_run' ) );
    add_action( 'wp_ajax_oh_run_product_deletion', array( OH_Delete_Old_Outofstock_Products::get_instance(), 'handle_manual_run' ) );
}
add_action( 'plugins_loaded', 'oh_doop_init' );
