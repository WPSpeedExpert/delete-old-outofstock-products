<?php
/**
 * Plugin Name:        Delete Old Out-of-Stock Products
 * Plugin URI:         https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * Description:        Automatically deletes WooCommerce products that are out of stock and older than a configurable time period, including their images.
 * Version:            2.2.1
 * Author:             OctaHexa
 * Author URI:         https://octahexa.com
 * Text Domain:        delete-old-outofstock-products
 * License:            GPL-3.0+
 * License URI:        https://www.gnu.org/licenses/gpl-3.0.html
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
define( 'DOOP_VERSION', '2.2.1' );
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
    
    /**
     * Last cron execution time
     *
     * @var int
     */
    private $last_cron_time;

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
        add_filter( 'plugin_action_links_' . plugin_basename( DOOP_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );

        // Handle manual run
        add_action( 'admin_post_oh_run_product_deletion', array( $this, 'handle_manual_run' ) );

        // Schedule action to process background deletion
        add_action( 'oh_doop_process_deletion', array( $this, 'process_background_deletion' ) );

        // Cron action
        add_action( DOOP_CRON_HOOK, array( $this, 'delete_old_out_of_stock_products' ) );
        add_action( DOOP_CRON_HOOK, array( $this, 'update_last_cron_time' ) );
        
        // Add admin styles
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
        
        // AJAX endpoint for checking status
        add_action( 'wp_ajax_oh_check_deletion_status', array( $this, 'ajax_check_deletion_status' ) );
    }
    
    /**
     * AJAX handler to check deletion status
     */
    public function ajax_check_deletion_status() {
        // Check nonce for security
        check_ajax_referer( 'oh_doop_ajax_nonce', 'security' );
        
        $is_running = get_option( 'oh_doop_deletion_running', false );
        $last_run_count = get_option( 'oh_doop_last_run_count', false );
        $too_many_count = get_option( 'oh_doop_too_many_products', false );
        
        $response = array(
            'is_running' => ($is_running && $is_running !== 0),
            'is_completed' => ($is_running === 0 && $last_run_count !== false),
            'too_many' => ($too_many_count !== false),
            'deleted_count' => $last_run_count !== false ? intval($last_run_count) : 0,
            'too_many_count' => $too_many_count !== false ? intval($too_many_count) : 0,
            'time_elapsed' => $is_running ? human_time_diff(intval($is_running), time()) : '',
        );
        
        wp_send_json_success( $response );
    }
    
    /**
     * Add admin styles
     */
    public function admin_styles( $hook ) {
        if ( 'woocommerce_page_doop-settings' !== $hook ) {
            return;
        }
        
        wp_add_inline_style( 'wp-admin', '
            .oh-doop-stats table {
                width: 50%;
                max-width: 600px;
                margin-bottom: 15px;
            }
            .oh-doop-stats td {
                padding: 10px 15px;
            }
            .oh-doop-description {
                max-width: 600px;
                line-height: 1.5;
                margin-top: 6px;
            }
            .oh-doop-cron-info h4 {
                margin-top: 15px;
                margin-bottom: 10px;
            }
            .oh-doop-cron-info table {
                width: 100%;
                max-width: 100%;
            }
            .oh-doop-manual-run p {
                margin-top: 15px;
                margin-bottom: 15px;
            }
            #oh-process-status {
                display: none;
                padding: 15px;
                margin: 15px 0;
                border-left: 4px solid #00a0d2;
                background-color: #f7fcff;
            }
            #oh-process-status.success {
                border-left-color: #46b450;
                background-color: #f7fff7;
            }
            #oh-process-status.error {
                border-left-color: #dc3232;
                background-color: #fff7f7;
            }
        ' );
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
        $this->last_cron_time = get_option( 'oh_doop_last_cron_time', 0 );
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
        // Don't delete oh_doop_last_cron_time - keep this record even when deactivated
    }

    //----------------------------------------//
    // 2.4 Admin Interface
    //----------------------------------------//
    
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
     * @return array Modified plugin action links.
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=doop-settings' ) ),
            esc_html__( 'Settings', 'delete-old-outofstock-products' )
        );
        array_unshift( $links, $settings_link );
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
        <p class="description oh-doop-description">
            <?php esc_html_e( 'This will delete featured images and gallery images associated with the product. The plugin will automatically skip placeholder images and any images that are used by other products or posts.', 'delete-old-outofstock-products' ); ?>
        </p>
        <?php
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
        
        // Schedule immediate background processing
        wp_schedule_single_event( time(), 'oh_doop_process_deletion' );
        
        // Redirect immediately to the status page
        wp_redirect( add_query_arg(
            array(
                'page' => 'doop-settings',
                'deletion_status' => 'running',
                't' => time() // Add timestamp to prevent caching
            ),
            admin_url( 'admin.php' )
        ));
        exit;
    }
    
    /**
     * Process background deletion
     */
    public function process_background_deletion() {
        // Get options
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
        
        // If there are too many products, log and exit
        if ($eligible_count > 200) {
            update_option( 'oh_doop_deletion_running', 0 ); // Clear the running flag
            update_option( 'oh_doop_last_run_count', 0 );
            update_option( 'oh_doop_too_many_products', $eligible_count );
            error_log('Too many products (' . $eligible_count . ') eligible for deletion. Aborting manual run.');
            return;
        }
        
        // Set a reasonable max execution time
        $original_max_time = ini_get('max_execution_time');
        if ($original_max_time < 300 && $original_max_time != 0) {
            @ini_set('max_execution_time', 300); // 5 minutes should be enough
        }
        
        // Run the deletion process
        $deleted_count = $this->delete_old_out_of_stock_products(100);
        
        // Store the results
        update_option( 'oh_doop_last_run_count', $deleted_count );
        update_option( 'oh_doop_deletion_running', 0 ); // Clear the running flag
        
        // Restore original max execution time
        if ($original_max_time != ini_get('max_execution_time') && $original_max_time != 0) {
            @ini_set('max_execution_time', $original_max_time);
        }
        
        error_log('Deleted ' . $deleted_count . ' products via manual run.');
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Check if process is running
        $is_running = get_option( 'oh_doop_deletion_running', false );
        $deletion_status = isset( $_GET['deletion_status'] ) ? sanitize_text_field( $_GET['deletion_status'] ) : '';
        $deleted_count = isset( $_GET['deleted'] ) ? intval( $_GET['deleted'] ) : false;
        $last_run_count = get_option( 'oh_doop_last_run_count', false );
        $too_many_count = get_option( 'oh_doop_too_many_products', false );
        
        // Clear the too many products flag if it exists
        if ($too_many_count !== false && $deletion_status !== 'too_many') {
            $too_many_count = false;
            delete_option('oh_doop_too_many_products');
        }
        
        // Check if a completed process needs to be shown
        if ($is_running === 0 && $last_run_count !== false && $deletion_status === 'running') {
            // Process completed while we were on the page, redirect to completion
            wp_redirect(add_query_arg(
                array(
                    'page' => 'doop-settings',
                    'deletion_status' => 'completed',
                    'deleted' => $last_run_count,
                    't' => time()
                ),
                admin_url('admin.php')
            ));
            exit;
        }
        
        // Check if too many products were found during a background process
        if ($too_many_count !== false && $deletion_status !== 'too_many') {
            wp_redirect(add_query_arg(
                array(
                    'page' => 'doop-settings',
                    'deletion_status' => 'too_many',
                    'count' => $too_many_count,
                    't' => time()
                ),
                admin_url('admin.php')
            ));
            exit;
        }
        
        // Enqueue WordPress's Ajax API
        wp_enqueue_script('jquery');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div id="oh-process-status">
                <!-- Will be filled by JavaScript -->
            </div>
            
            <?php
            // Show appropriate notices based on status
            if ( $is_running && $is_running !== 0 ) {
                // If it's been running for more than 10 minutes, assume it's done or failed
                $time_elapsed = time() - intval( $is_running );
                
                if ( $time_elapsed > 600 ) { // 10 minutes
                    delete_option( 'oh_doop_deletion_running' );
                    ?>
                    <div class="notice notice-warning">
                        <p><?php esc_html_e( 'The previous cleanup process may have timed out or completed without updating its status. You can try running it again if needed.', 'delete-old-outofstock-products' ); ?></p>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="notice notice-info">
                        <p>
                            <strong><?php esc_html_e( 'Product cleanup is running in the background.', 'delete-old-outofstock-products' ); ?></strong>
                            <?php esc_html_e( 'Please wait while products are being deleted. The status will update automatically.', 'delete-old-outofstock-products' ); ?>
                        </p>
                        <p>
                            <?php esc_html_e( 'Started: ', 'delete-old-outofstock-products' ); ?>
                            <?php echo esc_html( human_time_diff( intval( $is_running ), time() ) ); ?>
                            <?php esc_html_e( ' ago', 'delete-old-outofstock-products' ); ?>
                        </p>
                    </div>
                    <?php
                }
            } elseif ( 'running' === $deletion_status ) {
                ?>
                <div class="notice notice-info">
                    <p>
                        <strong><?php esc_html_e( 'Product cleanup has started.', 'delete-old-outofstock-products' ); ?></strong>
                        <?php esc_html_e( 'Please wait while products are being deleted. The status will update automatically.', 'delete-old-outofstock-products' ); ?>
                    </p>
                </div>
                <?php
            } elseif ( 'completed' === $deletion_status ) {
                // Show completion message with count from URL parameter or last stored count
                $count = false !== $deleted_count ? $deleted_count : $last_run_count;
                if (false !== $count) {
                    ?>
                    <div class="notice notice-success">
                        <p>
                            <?php 
                            printf( 
                                esc_html__( 'Product cleanup completed. %d products were deleted.', 'delete-old-outofstock-products' ), 
                                intval( $count )
                            ); 
                            ?>
                        </p>
                    </div>
                    <?php
                    // Clear the last run count after showing it
                    delete_option( 'oh_doop_last_run_count' );
                }
            } elseif ( 'too_many' === $deletion_status ) {
                $count = isset( $_GET['count'] ) ? intval( $_GET['count'] ) : ($too_many_count !== false ? $too_many_count : 0);
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php esc_html_e( 'Too many products eligible for deletion', 'delete-old-outofstock-products' ); ?></strong>
                    </p>
                    <p>
                        <?php 
                        printf( 
                            esc_html__( 'There are %d products eligible for deletion, which exceeds the safe limit for manual deletion (200). The automatic daily cron job will handle these deletions gradually. Please wait for the automatic process to run, or adjust your age settings to reduce the number of products being deleted at once.', 'delete-old-outofstock-products' ), 
                            $count
                        ); 
                        ?>
                    </p>
                </div>
                <?php
                // Clear the too many products flag
                delete_option('oh_doop_too_many_products');
            }
            ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'doop_settings_group' );
                do_settings_sections( 'doop-settings' );
                submit_button();
                ?>
            </form>
            
            <div class="oh-doop-manual-run card">
                <h2><?php esc_html_e( 'Manual Run', 'delete-old-outofstock-products' ); ?></h2>
                
                <?php
                // Display cron schedule information
                $next_scheduled = wp_next_scheduled( DOOP_CRON_HOOK );
                $last_cron_time = get_option( 'oh_doop_last_cron_time', 0 );
                ?>
                
                <div class="oh-doop-cron-info">
                    <h4><?php esc_html_e( 'Scheduled Cleanup Information', 'delete-old-outofstock-products' ); ?></h4>
                    <table class="widefat striped">
                        <tr>
                            <td><strong><?php esc_html_e( 'Next Scheduled Run:', 'delete-old-outofstock-products' ); ?></strong></td>
                            <td>
                                <?php 
                                if ($next_scheduled) {
                                    echo esc_html( get_date_from_gmt( date( 'Y-m-d H:i:s', $next_scheduled ), 'F j, Y, g:i a' ) );
                                    if (isset($_GET['freshly_scheduled'])) {
                                        echo ' <em>' . esc_html__( '(just scheduled)', 'delete-old-outofstock-products' ) . '</em>';
                                    }
                                } else {
                                    // Force reschedule if not found
                                    wp_schedule_event( time(), 'daily', DOOP_CRON_HOOK );
                                    $next_scheduled = wp_next_scheduled( DOOP_CRON_HOOK );
                                    
                                    if ($next_scheduled) {
                                        echo esc_html( get_date_from_gmt( date( 'Y-m-d H:i:s', $next_scheduled ), 'F j, Y, g:i a' ) );
                                        echo ' <em>' . esc_html__( '(just scheduled)', 'delete-old-outofstock-products' ) . '</em>';
                                        
                                        // Refresh the page to update the UI
                                        echo '<meta http-equiv="refresh" content="0;URL=\'' . 
                                            esc_url(add_query_arg('freshly_scheduled', '1', admin_url('admin.php?page=doop-settings'))) . 
                                            '\'" />';
                                    } else {
                                        esc_html_e( 'Unable to schedule cron - please check your WordPress configuration', 'delete-old-outofstock-products' );
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                        
                        <?php if ($next_scheduled): ?>
                        <tr>
                            <td><strong><?php esc_html_e( 'Time Until Next Run:', 'delete-old-outofstock-products' ); ?></strong></td>
                            <td>
                                <?php 
                                $time_diff = $next_scheduled - time();
                                if ( $time_diff > 0 ) {
                                    echo esc_html( human_time_diff( time(), $next_scheduled ) );
                                } else {
                                    esc_html_e( 'Overdue - Will run on next site visit', 'delete-old-outofstock-products' );
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td><strong><?php esc_html_e( 'Last Automatic Run:', 'delete-old-outofstock-products' ); ?></strong></td>
                            <td>
                                <?php 
                                if ( $last_cron_time > 0 ) {
                                    echo esc_html( get_date_from_gmt( date( 'Y-m-d H:i:s', $last_cron_time ), 'F j, Y, g:i a' ) );
                                    echo ' (' . esc_html( human_time_diff( $last_cron_time, time() ) ) . ' ' . esc_html__( 'ago', 'delete-old-outofstock-products' ) . ')';
                                } else {
                                    esc_html_e( 'Not yet run', 'delete-old-outofstock-products' );
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p><?php esc_html_e( 'Click the button below to manually run the deletion process.', 'delete-old-outofstock-products' ); ?></p>
                <p><em><?php esc_html_e( 'Note: The cleanup process runs immediately when using this button.', 'delete-old-outofstock-products' ); ?></em></p>
                
                <?php if ( $is_running && $is_running !== 0 ) : ?>
                    <p><strong><?php esc_html_e( 'A cleanup process is already running. Please wait for it to complete.', 'delete-old-outofstock-products' ); ?></strong></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=doop-settings' ) ); ?>" class="button"><?php esc_html_e( 'Refresh Status', 'delete-old-outofstock-products' ); ?></a>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="oh_run_product_deletion">
                        <?php wp_nonce_field( 'oh_run_product_deletion_nonce', 'oh_nonce' ); ?>
                        <?php submit_button( __( 'Run Product Cleanup Now', 'delete-old-outofstock-products' ), 'primary', 'run_now', false ); ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ( $is_running && $is_running !== 0 || 'running' === $deletion_status ) : ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Create a status container if it doesn't exist
            var statusEl = $('#oh-process-status');
            if (statusEl.length === 0) {
                $('.wrap').prepend('<div id="oh-process-status"></div>');
                statusEl = $('#oh-process-status');
            }
            
            // Set up the AJAX status checker
            function checkStatus() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'oh_check_deletion_status',
                        security: '<?php echo wp_create_nonce('oh_doop_ajax_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            
                            // Update the status message
                            if (data.is_running) {
                                statusEl.removeClass('success error').show().html(
                                    '<p><strong><?php esc_html_e('Product cleanup is in progress...', 'delete-old-outofstock-products'); ?></strong></p>' +
                                    '<p><?php esc_html_e('Started:', 'delete-old-outofstock-products'); ?> ' + data.time_elapsed + ' <?php esc_html_e('ago', 'delete-old-outofstock-products'); ?></p>' +
                                    '<p><?php esc_html_e('You can navigate away from this page. The process will continue in the background.', 'delete-old-outofstock-products'); ?></p>'
                                );
                                
                                // Schedule another check in a few seconds
                                setTimeout(checkStatus, 5000);
                            } else if (data.is_completed) {
                                // Process completed
                                statusEl.removeClass('error').addClass('success').show().html(
                                    '<p><strong><?php esc_html_e('Product cleanup completed!', 'delete-old-outofstock-products'); ?></strong></p>' +
                                    '<p>' + data.deleted_count + ' <?php esc_html_e('products were deleted.', 'delete-old-outofstock-products'); ?></p>'
                                );
                                
                                // Reload the page to show the updated UI without auto-refresh
                                setTimeout(function() {
                                    window.location.href = '<?php echo esc_url(add_query_arg(array('page' => 'doop-settings', 'deletion_status' => 'completed'), admin_url('admin.php'))); ?>&deleted=' + data.deleted_count + '&t=' + Date.now();
                                }, 2000);
                            } else if (data.too_many) {
                                // Too many products
                                statusEl.removeClass('success').addClass('error').show().html(
                                    '<p><strong><?php esc_html_e('Too many products eligible for deletion', 'delete-old-outofstock-products'); ?></strong></p>' +
                                    '<p><?php esc_html_e('There are', 'delete-old-outofstock-products'); ?> ' + data.too_many_count + ' <?php esc_html_e('products eligible for deletion, which exceeds the safe limit for manual deletion (200).', 'delete-old-outofstock-products'); ?></p>'
                                );
                                
                                // Reload the page to show the updated UI without auto-refresh
                                setTimeout(function() {
                                    window.location.href = '<?php echo esc_url(add_query_arg(array('page' => 'doop-settings', 'deletion_status' => 'too_many'), admin_url('admin.php'))); ?>&count=' + data.too_many_count + '&t=' + Date.now();
                                }, 2000);
                            }
                        }
                    }
                });
            }
            
            // Start checking status
            checkStatus();
        });
        </script>
        <?php endif; ?>
        
        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                margin-top: 20px;
                padding: 15px 20px;
            }
            .card h2 {
                margin-top: 0;
            }
            .oh-doop-manual-run p {
                margin-top: 15px;
                margin-bottom: 15px;
            }
            .oh-doop-cron-info {
                margin-bottom: 30px;
            }
            .oh-doop-cron-info h4 {
                margin-top: 0;
                margin-bottom: 10px;
            }
        </style>
        <?php
    }

    //----------------------------------------//
    // 2.5 Product Deletion Logic
    //----------------------------------------//
    
    /**
     * Delete out-of-stock WooCommerce products older than the configured age, including images.
     * 
     * @param int $batch_size Optional batch size to limit number of products processed
     * @return int Number of products deleted
     */
    public function delete_old_out_of_stock_products( $batch_size = 50 ) {
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
        $offset = 0;
        $deleted = 0;
        $total_processed = 0;
        
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
                $result = wp_delete_post( $product_id, true );
                if ( $result ) {
                    $deleted++;
                }
                
                $total_processed++;
                
                // Add a small delay every 10 products to prevent timeouts
                if ($total_processed % 10 === 0) {
                    usleep(50000); // 50ms pause
                }
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
    OH_Delete_Old_Outofstock_Products::get_instance();
}
add_action( 'plugins_loaded', 'oh_doop_init' );
