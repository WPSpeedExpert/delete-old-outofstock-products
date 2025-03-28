<?php
/**
 * Filename: includes/class-oh-admin-ui.php
 * Admin UI class for Delete Old Out-of-Stock Products
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
 *    1.2 Constructor
 *    1.3 Default options
 *
 * 2. ADMIN INTERFACE
 *    2.1 Settings page
 *    2.2 Settings fields and sections
 *    2.3 Settings sanitization
 *    2.4 Admin menu
 *
 * 3. AJAX & STATUS HANDLING
 *    3.1 AJAX handlers
 *    3.2 Status checking
 *
 * 4. RENDER FUNCTIONS
 *    4.1 Main page
 *    4.2 Stats section
 *    4.3 Settings sections
 *    4.4 UI utilities
 *    4.5 Product age field callback
 *    4.6 Delete images checkbox callback
 *    4.7 Render settings page
 *    4.8 410 Gone status checkbox callback
 *    4.9 410 Section description callback
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class to handle admin UI
 */
class OH_Admin_UI {
    
    // 1. SETUP & INITIALIZATION
    // =================================
    
    /**
     * 1.1 Class properties
     */
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Logger instance
     *
     * @var OH_Logger
     */
    private $logger;
    
    /**
     * 1.2 Constructor
     */
    public function __construct() {
        $this->set_default_options();
        $this->logger = OH_Logger::get_instance();
        
        // Add admin page and menu
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // Add settings link to plugins page
        add_filter( 'plugin_action_links_' . plugin_basename( DOOP_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
        
        // Add admin styles and scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
        
        // AJAX endpoints
        add_action( 'wp_ajax_oh_check_deletion_status', array( $this, 'ajax_check_deletion_status' ) );
        add_action( 'wp_ajax_oh_get_deletion_log', array( $this, 'ajax_get_deletion_log' ) );
    }
    
    /**
     * 1.3 Set default options
     */
    private function set_default_options() {
        $default_options = array(
            'product_age' => 18, // Default: 18 months
            'delete_images' => 'yes', // Default: Yes
            'enable_410' => 'yes', // Default: Yes
        );
    
        $this->options = get_option( DOOP_OPTIONS_KEY, $default_options );
    }
    
    // 2. ADMIN INTERFACE
    // =================================
    
    /**
     * 2.1 Add settings page
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
     * 2.2 Register settings - Add new 410 setting field
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
        
        add_settings_field(
            'enable_410',
            __( 'Enable 410 Gone Status', 'delete-old-outofstock-products' ),
            array( $this, 'enable_410_callback' ),
            'doop-settings',
            'doop_main_section'
        );
        
        // Add 410 stats section if enabled
        if (isset($this->options['enable_410']) && $this->options['enable_410'] === 'yes') {
            add_settings_section(
                'doop_410_section',
                __( '410 Gone Status', 'delete-old-outofstock-products' ),
                array( $this, 'section_410_callback' ),
                'doop-settings'
            );
        }
    }
    
    /**
     * 2.3 Sanitize options - Add new 410 option
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
        
        // Sanitize enable 410 option
        $output['enable_410'] = isset( $input['enable_410'] ) ? 'yes' : 'no';
        
        return $output;
    }
    
    /**
     * 2.4 Add settings link to plugin action links
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
    
    // 3. AJAX & STATUS HANDLING
    // =================================
    
    /**
     * 3.1 AJAX handler to check deletion status
     */
    public function ajax_check_deletion_status() {
        // Check nonce for security
        check_ajax_referer( 'oh_doop_ajax_nonce', 'security' );
        
        $is_running = get_option( DOOP_PROCESS_OPTION, false );
        $last_run_count = get_option( DOOP_RESULT_OPTION, false );
        $too_many_count = get_option( 'oh_doop_too_many_products', false );
        
        // Get the product progress counters
        $products_processed = get_option( 'oh_doop_products_processed', 0 );
        $products_deleted = get_option( 'oh_doop_products_deleted', 0 );
        
        // Make sure we're interpreting the values correctly
        $is_running_value = ($is_running && $is_running !== 0);
        $is_completed_value = ($is_running === 0 && $last_run_count !== false);
        $too_many_value = ($too_many_count !== false && $too_many_count > 0);
        
        // Calculate elapsed time if running and check for stuck processes
        $time_elapsed = '';
        $is_stuck = false;
        if ($is_running_value && is_numeric($is_running)) {
            $time_elapsed = human_time_diff(intval($is_running), current_time('timestamp'));
            
            // Check if process might be stuck (running for more than 10 minutes)
            if ((current_time('timestamp') - intval($is_running)) > 600) {
                $is_stuck = true;
            }
        }
        
        // When a process completes, use the stored deletion count
        if ($is_completed_value && $last_run_count !== false) {
            $products_deleted = intval($last_run_count);
        }
        
        $response = array(
            'is_running' => $is_running_value,
            'is_completed' => $is_completed_value, 
            'too_many' => $too_many_value,
            'deleted_count' => $last_run_count !== false ? intval($last_run_count) : 0,
            'too_many_count' => $too_many_count !== false ? intval($too_many_count) : 0,
            'time_elapsed' => $time_elapsed,
            'is_stuck' => $is_stuck,
            'has_log' => $this->logger->log_exists(),
            'products_processed' => intval($products_processed),
            'products_deleted' => intval($products_deleted),
            // Add debug information
            'debug' => array(
                'is_running_raw' => $is_running,
                'last_run_count_raw' => $last_run_count,
                'too_many_count_raw' => $too_many_count,
                'current_time' => current_time('timestamp'),
                'process_time' => is_numeric($is_running) ? intval($is_running) : 0,
                'time_difference' => is_numeric($is_running) ? (current_time('timestamp') - intval($is_running)) : 0,
                'timestamp' => time()
            ),
            'server_time' => current_time('mysql'),
        );
        
        wp_send_json_success( $response );
    }
    
    /**
     * 3.2 AJAX handler to get deletion log content
     */
    public function ajax_get_deletion_log() {
        // Check nonce for security
        check_ajax_referer( 'oh_doop_ajax_nonce', 'security' );
        
        $log_content = $this->logger->get_log_content();
        
        wp_send_json_success(array(
            'log_content' => $log_content ?: __('No log entries found.', 'delete-old-outofstock-products'),
            'timestamp' => time()
        ));
    }
    
    // 4. RENDER FUNCTIONS
    // =================================
    
    /**
     * 4.1 Add admin styles
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
            #oh-deletion-log {
                background: #f8f8f8;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                max-height: 350px;
                overflow-y: auto;
                margin-top: 10px;
                display: none;
                font-family: monospace;
                font-size: 12px;
                line-height: 1.4;
                white-space: pre-wrap;
            }
            .oh-view-log-btn {
                margin-top: 10px !important;
            }
            .oh-status-indicator {
                display: inline-block;
                margin-left: 10px;
            }
            .oh-status-indicator.running {
                color: #00a0d2;
                animation: oh-blink 1s linear infinite;
            }
            @keyframes oh-blink {
                50% { opacity: 0.5; }
            }
        ' );
    }
    
    /**
     * 4.2 Add admin scripts
     */
    public function admin_scripts( $hook ) {
        if ( 'woocommerce_page_doop-settings' !== $hook ) {
            return;
        }
        
        wp_enqueue_script(
            'oh-admin-scripts',
            DOOP_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            DOOP_VERSION . '.' . time(), // Add timestamp to force cache refresh
            true
        );
        
        // Check if process is running to pass to JavaScript
        $is_running = get_option( DOOP_PROCESS_OPTION, false );
        $is_running_value = ($is_running && $is_running !== 0);
        
        wp_localize_script(
            'oh-admin-scripts',
            'ohDoopData',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'oh_doop_ajax_nonce' ),
                'strings' => array(
                    'running' => __( 'Process is running...', 'delete-old-outofstock-products' ),
                    'completed' => __( 'Process completed!', 'delete-old-outofstock-products' ),
                    'loadingLog' => __( 'Loading log...', 'delete-old-outofstock-products' ),
                    'noLog' => __( 'No log content available', 'delete-old-outofstock-products' ),
                    'errorLog' => __( 'Error loading log content', 'delete-old-outofstock-products' ),
                    'ago' => __( 'ago', 'delete-old-outofstock-products' ),
                    'productsDeleted' => __( 'products were deleted.', 'delete-old-outofstock-products' ),
                    'tooMany' => __( 'Too many products eligible for deletion', 'delete-old-outofstock-products' ),
                    'tooManyMsg' => __( 'products eligible for deletion, which exceeds the safe limit for manual deletion (200).', 'delete-old-outofstock-products' ),
                    'viewLog' => __( 'View Log', 'delete-old-outofstock-products' ),
                    'hideLog' => __( 'Hide Log', 'delete-old-outofstock-products' ),
                    'navigateAway' => __( 'You can navigate away from this page. The process will continue in the background.', 'delete-old-outofstock-products' ),
                    'alreadyRunning' => __( 'A deletion process is already running!', 'delete-old-outofstock-products' ),
                    'waitForCompletion' => __( 'Please wait for the current process to complete before starting a new one.', 'delete-old-outofstock-products' ),
                ),
                'isRunning' => $is_running_value,
                'deletionStatus' => isset( $_GET['deletion_status'] ) ? sanitize_text_field( $_GET['deletion_status'] ) : '',
                'manualRun' => isset( $_GET['manual'] ) ? '1' : '0',
                'processTime' => $is_running ? human_time_diff(intval($is_running), current_time('timestamp')) : '',
                'processStarted' => $is_running ? intval($is_running) : 0,
                'currentTime' => current_time('timestamp'),
                'debug' => WP_DEBUG,
                'timestamp' => time()
            )
        );
    }
    
    /**
     * 4.3 Section description callback
     */
    public function section_callback() {
        echo '<p>' . esc_html__( 'Configure the settings for automatic deletion of old out-of-stock products.', 'delete-old-outofstock-products' ) . '</p>';
    }

    /**
     * 4.4 Stats section description callback
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
     * 4.5 Product age field callback
     */
    public function product_age_callback() {
        $product_age = isset( $this->options['product_age'] ) ? $this->options['product_age'] : 18;
        ?>
        <input type="number" id="product_age" name="<?php echo esc_attr( DOOP_OPTIONS_KEY ); ?>[product_age]" value="<?php echo esc_attr( $product_age ); ?>" min="1" step="1" />
        <p class="description"><?php esc_html_e( 'Products older than this many months will be deleted if they are out of stock.', 'delete-old-outofstock-products' ); ?></p>
        <?php
    }

    /**
     * 4.6 Delete images checkbox callback
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
     * 4.7 Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Check if process is running
        $is_running = get_option( DOOP_PROCESS_OPTION, false );
        $deletion_status = isset( $_GET['deletion_status'] ) ? sanitize_text_field( $_GET['deletion_status'] ) : '';
        $deleted_count = isset( $_GET['deleted'] ) ? intval( $_GET['deleted'] ) : false;
        $last_run_count = get_option( DOOP_RESULT_OPTION, false );
        $too_many_count = get_option( 'oh_doop_too_many_products', false );
        
        // Clear the too many products flag if it exists
        if ($too_many_count !== false && $deletion_status !== 'too_many') {
            delete_option('oh_doop_too_many_products');
            $too_many_count = false;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div id="oh-process-status">
                <!-- Will be filled by JavaScript -->
            </div>
            
            <div id="oh-deletion-log">
                <!-- Will be filled by JavaScript when available -->
            </div>
            
            <?php
            // Only show one status message, prioritizing AJAX updates
            if (($is_running && $is_running !== 0) || 'running' === $deletion_status) {
                // Don't show any message - AJAX will handle it
            } elseif ('completed' === $deletion_status) {
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
                            
                            if ($this->logger->log_exists()) {
                                ?>
                                <button type="button" class="button oh-view-log-btn"><?php esc_html_e('View Log', 'delete-old-outofstock-products'); ?></button>
                                <?php
                            }
                            ?>
                        </p>
                    </div>
                    <?php
                    // Clear the last run count after showing it
                    delete_option( DOOP_RESULT_OPTION );
                }
            } elseif ('too_many' === $deletion_status) {
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
            } elseif ('already_running' === $deletion_status) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php esc_html_e( 'A cleanup process is already running.', 'delete-old-outofstock-products' ); ?></strong>
                    </p>
                    <p>
                        <?php esc_html_e( 'Please wait for the current process to complete before starting a new one.', 'delete-old-outofstock-products' ); ?>
                    </p>
                </div>
                <?php
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
                                        
                                    // Only refresh if not a manual process and not on a running status page
                                    if (!isset($_GET['manual']) && !isset($_GET['deletion_status']) && !get_option('oh_doop_manual_process', false)) {
                                        // Refresh the page to update the UI
                                        echo '<meta http-equiv="refresh" content="0;URL=\'' . 
                                            esc_url(add_query_arg('freshly_scheduled', '1', admin_url('admin.php?page=doop-settings'))) . 
                                            '\'" />';
                                    } else {
                                        // Clear the manual process flag
                                        delete_option('oh_doop_manual_process');
                                    }
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
                        
                        <?php if ($this->logger->log_exists()): ?>
                        <tr>
                            <td><strong><?php esc_html_e( 'Log File:', 'delete-old-outofstock-products' ); ?></strong></td>
                            <td>
                                <button type="button" class="button oh-view-log-btn"><?php esc_html_e('View Log', 'delete-old-outofstock-products'); ?></button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <p><?php esc_html_e( 'Click the button below to manually run the deletion process.', 'delete-old-outofstock-products' ); ?></p>
                <p><em><?php esc_html_e( 'Note: The cleanup process runs in the background and you can navigate away after starting it.', 'delete-old-outofstock-products' ); ?></em></p>
                
                <?php if ( $is_running && $is_running !== 0 ) : ?>
                    <p><strong><?php esc_html_e( 'A cleanup process is already running. Please wait for it to complete.', 'delete-old-outofstock-products' ); ?></strong></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=doop-settings' ) ); ?>" class="button"><?php esc_html_e( 'Refresh Status', 'delete-old-outofstock-products' ); ?></a>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="oh_run_product_deletion">
                        <?php wp_nonce_field( 'oh_run_product_deletion_nonce', 'oh_nonce' ); ?>
                        <?php submit_button( __( 'Run Product Cleanup Now', 'delete-old-outofstock-products' ), 'primary', 'run_now', false ); ?>
                        <span class="oh-status-indicator"></span>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

    /**
     * 4.8 410 Gone status checkbox callback
     */
    public function enable_410_callback() {
        $enable_410 = isset( $this->options['enable_410'] ) ? $this->options['enable_410'] : 'yes';
        ?>
        <label for="enable_410">
            <input type="checkbox" id="enable_410" name="<?php echo esc_attr( DOOP_OPTIONS_KEY ); ?>[enable_410]" <?php checked( $enable_410, 'yes' ); ?> />
            <?php esc_html_e( 'Return 410 Gone status for deleted products', 'delete-old-outofstock-products' ); ?>
        </label>
        <p class="description oh-doop-description">
            <?php esc_html_e( 'This will track deleted product URLs and return a 410 Gone status code when those URLs are accessed. This helps search engines understand that these products have been permanently removed.', 'delete-old-outofstock-products' ); ?>
        </p>
        <?php
    }
    
    /**
     * 4.9 410 Section description callback
     */
    public function section_410_callback() {
        // Get count of tracked deleted products
        $deleted_products = get_option('oh_doop_deleted_products', array());
        $count = count($deleted_products);
        
        ?>
        <div class="oh-doop-stats">
            <table class="widefat striped">
                <tr>
                    <td><strong><?php esc_html_e( 'Tracked Deleted Products:', 'delete-old-outofstock-products' ); ?></strong></td>
                    <td><?php echo esc_html( $count ); ?></td>
                </tr>
            </table>
            <p class="description">
                <?php esc_html_e( 'Number of deleted products being tracked for 410 Gone status responses. Old records are automatically cleared after one year.', 'delete-old-outofstock-products' ); ?>
            </p>
        </div>
        <?php
    }
