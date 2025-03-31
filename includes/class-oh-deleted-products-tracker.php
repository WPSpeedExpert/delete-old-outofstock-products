<?php
/**
 * Filename: includes/class-oh-deleted-products-tracker.php
 * Deleted Products Tracker class for Delete Old Out-of-Stock Products
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.4.5
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
        
        // Store both the permalink and product base URL to help with various permalink structures
        $permalink = get_permalink($product_id);
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop');
        $product_base_url = trailingslashit($shop_url) . $product_slug;
        
        // Add timestamp to the product entry
        $deleted_products[$product_slug] = array(
            'id' => $product_id,
            'deleted_at' => time(),
            'url' => $permalink,
            'base_url' => $product_base_url
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
        $this->logger->log("Tracked deleted product #$product_id with slug '$product_slug', URL: $permalink");
        
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
        
        // First approach: use a similar approach to the theme function - check for /product/ in URL
        $has_product_path = (strpos($request_url, '/product/') !== false);
        
        // Products that don't use the default permalink structure
        if (!$has_product_path) {
            // Get shop page slug if WooCommerce is active
            if (function_exists('wc_get_page_id')) {
                $shop_page_uri = get_post_field('post_name', wc_get_page_id('shop'));
                // Check for shop/{product-slug} pattern
                $has_product_path = (strpos($request_url, '/' . $shop_page_uri . '/') !== false);
            }
        }
        
        // Get slug - the last part of the URL
        $path_parts = explode('/', $request_path);
        $potential_slug = end($path_parts);
        
        // 1. Direct slug match
        $deleted_products = get_option($this->option_name, array());
        if (isset($deleted_products[$potential_slug])) {
            $this->logger->log("410 response for deleted product: $potential_slug (direct slug match)");
            $this->send_410_response();
            return;
        }
        
        // If this doesn't appear to be a product URL, don't waste time on further checks
        if (!$has_product_path) {
            return;
        }
        
        // 2. Check for URL pattern matches
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
            "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            
        foreach ($deleted_products as $slug => $data) {
            // Skip if we don't have URL data (old records)
            if (!isset($data['url']) && !isset($data['base_url'])) {
                continue;
            }
            
            // Check if current URL contains the slug
            if (strpos($current_url, "/$slug/") !== false || 
                strpos($current_url, "/$slug") !== false) {
                $this->logger->log("410 response for deleted product: $slug (URL contains slug)");
                $this->send_410_response();
                return;
            }
            
            // Check if URL matches stored URL
            if (isset($data['url']) && (
                rtrim($current_url, '/') === rtrim($data['url'], '/') ||
                strpos($current_url, $data['url']) !== false
            )) {
                $this->logger->log("410 response for deleted product: $slug (URL match)");
                $this->send_410_response();
                return;
            }
            
            // Check if URL matches base URL
            if (isset($data['base_url']) && (
                rtrim($current_url, '/') === rtrim($data['base_url'], '/') ||
                strpos($current_url, $data['base_url']) !== false
            )) {
                $this->logger->log("410 response for deleted product: $slug (base URL match)");
                $this->send_410_response();
                return;
            }
        }
    }
    
    /**
     * Send a 410 Gone response
     */
    private function send_410_response() {
        status_header(410);
        nocache_headers(); // Prevent caching of 410 responses
        
        // Check if template exists
        $template_path = DOOP_PLUGIN_DIR . 'templates/410.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback if template is missing
            get_header();
            echo '<div style="text-align:center; padding:50px 20px;">';
            echo '<h1>' . esc_html__('Product No Longer Available', 'delete-old-outofstock-products') . '</h1>';
            echo '<p>' . esc_html__('This product has been removed or is no longer available.', 'delete-old-outofstock-products') . '</p>';
            echo '</div>';
            get_footer();
        }
        exit;
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
