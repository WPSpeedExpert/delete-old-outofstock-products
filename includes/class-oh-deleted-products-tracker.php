<?php
/**
 * Filename: includes/class-oh-deleted-products-tracker.php
 * Deleted Products Tracker class for Delete Old Out-of-Stock Products
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.4.0
 * @since 2.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class to handle tracking and responding with 410 for deleted products
 */
class OH_Deleted_Products_Tracker {
    /**
     * Logger instance
     *
     * @var OH_Logger
     */
    private $logger;
    
    /**
     * Option name for storing deleted product slugs
     *
     * @var string
     */
    private $option_name = 'oh_doop_deleted_products';
    
    /**
     * Maximum number of deleted products to track
     *
     * @var int
     */
    private $max_products = 1000;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = OH_Logger::get_instance();
        
        // Register hooks for tracking and handling deleted products
        add_action('template_redirect', array($this, 'check_for_deleted_product'), 10);
        
        // Initialize options if needed
        if (!get_option($this->option_name)) {
            add_option($this->option_name, array());
        }
    }
    
    /**
     * Track a deleted product by storing its slug
     *
     * @param int    $product_id The product ID
     * @param string $product_slug The product slug
     * @return bool Whether the product was tracked
     */
    public function track_deleted_product($product_id, $product_slug) {
        if (empty($product_slug)) {
            $this->logger->log("Cannot track deleted product #$product_id: Empty slug");
            return false;
        }
        
        $deleted_products = get_option($this->option_name, array());
        
        // Add timestamp to the product entry
        $deleted_products[$product_slug] = array(
            'id' => $product_id,
            'deleted_at' => time()
        );
        
        // If we're over the limit, remove oldest entries
        if (count($deleted_products) > $this->max_products) {
            // Sort by timestamp (oldest first)
            uasort($deleted_products, function($a, $b) {
                return $a['deleted_at'] - $b['deleted_at'];
            });
            
            // Remove oldest entries to get back under the limit
            $deleted_products = array_slice($deleted_products, count($deleted_products) - $this->max_products, null, true);
        }
        
        // Save the updated list
        update_option($this->option_name, $deleted_products);
        $this->logger->log("Tracked deleted product #$product_id with slug '$product_slug'");
        
        return true;
    }
    
    /**
     * Check if the current request is for a deleted product and return 410 if so
     */
    public function check_for_deleted_product() {
        // Only check on single product pages with 404s
        if (!is_404()) {
            return;
        }
        
        // Get the requested path
        $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $path_parts = explode('/', $request_path);
        
        // Get base product slug - we assume it's the last part of the URL for products
        $potential_slug = end($path_parts);
        
        // For performance, quickly check if this even looks like a product page
        $shop_page_uri = get_post_field('post_name', wc_get_page_id('shop'));
        $product_base = get_option('woocommerce_product_slug', 'product');
        
        // Check if this is a product URL structure
        $is_product_url = (in_array($shop_page_uri, $path_parts) || in_array($product_base, $path_parts));
        
        if (!$is_product_url) {
            // This doesn't appear to be a product URL
            return;
        }
        
        // Get our stored deleted products
        $deleted_products = get_option($this->option_name, array());
        
        // Check if the requested slug matches a deleted product
        if (isset($deleted_products[$potential_slug])) {
            $this->logger->log("410 response for deleted product: $potential_slug");
            
            // Set the 410 Gone status
            status_header(410);
            
            // You may want to load a custom template here
            include_once(DOOP_PLUGIN_DIR . 'templates/410.php');
            exit;
        }
    }
    
    /**
     * Clean up old deleted product records (older than X days)
     * 
     * @param int $days Number of days to keep records (default 365)
     */
    public function cleanup_old_records($days = 365) {
        $deleted_products = get_option($this->option_name, array());
        $threshold = time() - (86400 * $days); // 86400 seconds = 1 day
        $removed = 0;
        
        foreach ($deleted_products as $slug => $data) {
            if ($data['deleted_at'] < $threshold) {
                unset($deleted_products[$slug]);
                $removed++;
            }
        }
        
        if ($removed > 0) {
            update_option($this->option_name, $deleted_products);
            $this->logger->log("Cleaned up $removed old deleted product records");
        }
        
        return $removed;
    }
}
