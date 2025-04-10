<?php
/**
 * Filename: includes/class-oh-deletion-processor.php
 * Deletion Processor class for Delete Old Out-of-Stock Products
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.4.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class to handle product deletion logic
 */
class OH_Deletion_Processor {
    /**
     * Logger instance
     *
     * @var OH_Logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = OH_Logger::get_instance();
    }
    
    /**
     * Delete out-of-stock WooCommerce products older than the configured age, including images.
     * 
     * @param int $batch_size Optional batch size to limit number of products processed
     * @return int Number of products deleted
     */
    public function delete_old_out_of_stock_products( $batch_size = 50 ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            $this->logger->log("WooCommerce is not active. Aborting deletion.");
            return 0;
        }

        // Get options
        $options = get_option( DOOP_OPTIONS_KEY, array(
            'product_age' => 18,
            'delete_images' => 'yes',
            'enable_410' => 'yes',
        ) );

        $product_age = isset( $options['product_age'] ) ? absint( $options['product_age'] ) : 18;
        $delete_images = isset( $options['delete_images'] ) ? $options['delete_images'] : 'yes';
        $enable_410 = isset( $options['enable_410'] ) ? $options['enable_410'] : 'yes';

        $date_threshold = date( 'Y-m-d H:i:s', strtotime( "-{$product_age} months" ) );
        
        $this->logger->log("Starting product deletion process with age threshold: $product_age months");
        $this->logger->log("Date threshold: $date_threshold");
        $this->logger->log("Delete images setting: $delete_images");
        $this->logger->log("Enable 410 tracking: $enable_410");
        $this->logger->log("Batch size: $batch_size");

        // Get tracker instance if 410 tracking is enabled
        $tracker = null;
        if ('yes' === $enable_410) {
            $tracker = new OH_Deleted_Products_Tracker();
        }

        // Process in smaller batches to reduce memory usage
        $offset = 0;
        $deleted = 0;
        $total_processed = 0;
        
        try {
            do {
                $this->logger->log("Processing batch with offset: $offset");
                
                // Update process timestamp to show we're still alive
                update_option( DOOP_PROCESS_OPTION, time() );
                
                // Store the current progress count for status updates
                update_option( 'oh_doop_products_processed', $total_processed );
                update_option( 'oh_doop_products_deleted', $deleted );
                
                // Get a batch of products
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
                    $this->logger->log("No more products found to process");
                    break;
                }
                
                $this->logger->log("Found " . count($products) . " products to process in current batch");
                
                foreach ( $products as $product_id ) {
                    try {
                        $product = wc_get_product( $product_id );

                        if ( ! $product ) {
                            $this->logger->log("Could not get product with ID: $product_id");
                            continue;
                        }
                        
                        $product_name = $product->get_name();
                        $this->logger->log("Processing product #$product_id: $product_name");

                        // Process product images if enabled
                        if ( 'yes' === $delete_images ) {
                            $this->process_product_images($product, $product_id);
                        }
                        
                        // Track product for 410 responses if enabled
                        if ('yes' === $enable_410 && $tracker) {
                            $product_slug = $product->get_slug();
                            $tracker->track_deleted_product($product_id, $product_slug);
                        }

                        // Delete the product
                        $this->logger->log("Deleting product #$product_id: $product_name");
                        $result = wp_delete_post( $product_id, true );
                        
                        if ( $result ) {
                            $this->logger->log("Successfully deleted product #$product_id");
                            $deleted++;
                            // Update progress counter
                            update_option( 'oh_doop_products_deleted', $deleted );
                        } else {
                            $this->logger->log("Failed to delete product #$product_id");
                        }
                        
                        $total_processed++;
                        // Update progress counter
                        update_option( 'oh_doop_products_processed', $total_processed );
                        
                        // Add a small delay every 10 products to prevent timeouts
                        if ($total_processed % 10 === 0) {
                            usleep(50000); // 50ms pause
                            // Update process timestamp periodically to show we're still alive
                            update_option( DOOP_PROCESS_OPTION, time() );
                        }
                    } catch (Exception $e) {
                        $this->logger->log("Error processing product #$product_id: " . $e->getMessage());
                        continue; // Skip to next product on error
                    }
                }
                
                $offset += $batch_size;
                
                // Free up memory
                wp_cache_flush();
                
                $this->logger->log("Completed batch. Total processed: $total_processed, Deleted: $deleted");
                
                // Update the process option to show we're still alive
                update_option( DOOP_PROCESS_OPTION, time() );
                
            } while ( count( $products ) === $batch_size );
            
            // Clean up old records if 410 tracking is enabled
            if ('yes' === $enable_410 && $tracker) {
                $tracker->cleanup_old_records();
            }
            
        } catch (Exception $e) {
            $this->logger->log("Fatal error in deletion process: " . $e->getMessage());
            $this->logger->log("Stack trace: " . $e->getTraceAsString());
        }
        
        $this->logger->log("Deletion process complete. Total deleted: $deleted");
        
        // Final update of the progress counters
        update_option( 'oh_doop_products_processed', $total_processed );
        update_option( 'oh_doop_products_deleted', $deleted );
        
        return $deleted;
    }
    
    /**
     * Process product images for deletion
     * 
     * @param WC_Product $product The product object
     * @param int $product_id The product ID
     * @return void
     */
    private function process_product_images($product, $product_id) {
        $attachment_ids = array();

        // Featured image
        $featured_image_id = $product->get_image_id();
        if ( $featured_image_id ) {
            $attachment_ids[] = $featured_image_id;
        }

        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        if (!empty($gallery_ids)) {
            $attachment_ids = array_merge( $attachment_ids, $gallery_ids );
        }
        
        $this->logger->log("Found " . count($attachment_ids) . " images for product #$product_id");

        foreach ( $attachment_ids as $attachment_id ) {
            if ( $attachment_id ) {
                try {
                    // Get attachment details for logging
                    $attachment_path = get_attached_file($attachment_id);
                    $attachment_url = wp_get_attachment_url($attachment_id);
                    $file_name = basename($attachment_path);
                    
                    // Skip placeholder images
                    if ( $attachment_url && $this->is_placeholder_image( $attachment_url ) ) {
                        $this->logger->log("Skipping placeholder image #$attachment_id: $file_name");
                        continue;
                    }
                    
                    // Check if the image is used by other products or posts
                    if ( $this->is_attachment_used_elsewhere( $attachment_id, $product_id ) ) {
                        $this->logger->log("Skipping image #$attachment_id ($file_name) - used by other posts");
                        continue;
                    }
                    
                    // Delete the attachment
                    $this->logger->log("Deleting image #$attachment_id: $file_name");
                    $result = wp_delete_attachment( $attachment_id, true );
                    
                    if ($result) {
                        $this->logger->log("Successfully deleted image #$attachment_id");
                    } else {
                        $this->logger->log("Failed to delete image #$attachment_id");
                    }
                } catch (Exception $e) {
                    $this->logger->log("Error processing image #$attachment_id: " . $e->getMessage());
                }
            }
        }
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
