<?php
/**
 * Filename: includes/class-oh-deleted-products-tracker.php
 * Deleted Products Tracker class for Delete Old Out-of-Stock Products
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.4.4
 * @since 2.4.4
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
            'deleted_at' => time(),
            'url' => get_permalink($product_id)
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
        // Only check on 404 pages
        if (!is_404()) {
            return;
        }
        
        // Get the full requested URL and path
        $request_url = $_SERVER['REQUEST_URI'];
        $request_path = trim(parse_url($request_url, PHP_URL_PATH), '/');
        
        // First, use a similar approach to the theme function - check for /product/ in URL
        $has_product_path = (strpos($request_url, '/product/') !== false);
        
        // Products that don't use the default permalink structure
        if (!$has_product_path) {
            // Get shop page slug
            $shop_page_uri = get_post_field('post_name', wc_get_page_id('shop'));
            // Check for shop/{product-slug} pattern
            $has_product_path = (strpos($request_url, '/' . $shop_page_uri . '/') !== false);
        }
        
        // Skip if not a product URL
        if (!$has_product_path) {
            return;
        }
        
        // Get slug - the last part of the URL
        $path_parts = explode('/', $request_path);
        $potential_slug = end($path_parts);
        
        // Check if this is a tracked deleted product
        $deleted_products = get_option($this->option_name, array());
        
        if (isset($deleted_products[$potential_slug])) {
            $this->logger->log("410 response for deleted product: $potential_slug");
            status_header(410);
            
            // Load the 410 template
            include_once(DOOP_PLUGIN_DIR . 'templates/410.php');
            exit;
        }
        
        // Additional check by trying to match full URLs (in case of non-standard permalinks)
        foreach ($deleted_products as $slug => $data) {
            if (isset($data['url']) && strpos($data['url'], $potential_slug) !== false) {
                $this->logger->log("410 response for deleted product by URL match: $slug");
                status_header(410);
                
                // Load the 410 template
                include_once(DOOP_PLUGIN_DIR . 'templates/410.php');
                exit;
            }
        }
    }
    
    /**
     * Clean up old deleted product records (older than X days)
     * 
     * @param int $days Number of days to keep records (default 365)
     * @return int Number of removed records
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
