<?php
/**
 * Plugin Name:        Delete Old Out-of-Stock Products
 * Plugin URI:         https://github.com/WPSpeedExpert/delete-old-outofstock-products
 * Description:        Automatically deletes WooCommerce products that are out of stock and older than 1.5 years, including their images.
 * Version:            1.0.0
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

define( 'DOOP_CRON_HOOK', 'doop_cron_delete_old_products' );

/**
 * Delete out-of-stock WooCommerce products older than 1.5 years, including images.
 */
function doop_delete_old_out_of_stock_products() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $date_threshold = date( 'Y-m-d H:i:s', strtotime( '-18 months' ) );

    $products = get_posts( array(
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
    ) );

    foreach ( $products as $product_id ) {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            continue;
        }

        $attachment_ids = array();

        // Featured image.
        $featured_image_id = $product->get_image_id();
        if ( $featured_image_id ) {
            $attachment_ids[] = $featured_image_id;
        }

        // Gallery images.
        $attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );

        foreach ( $attachment_ids as $attachment_id ) {
            if ( $attachment_id ) {
                wp_delete_attachment( $attachment_id, true );
            }
        }

        wp_delete_post( $product_id, true );
    }
}

/**
 * Schedule the cron event on plugin activation.
 */
function doop_activate() {
    if ( ! wp_next_scheduled( DOOP_CRON_HOOK ) ) {
        wp_schedule_event( time(), 'daily', DOOP_CRON_HOOK );
    }
}
register_activation_hook( __FILE__, 'doop_activate' );

/**
 * Clear the cron event on plugin deactivation.
 */
function doop_deactivate() {
    $timestamp = wp_next_scheduled( DOOP_CRON_HOOK );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, DOOP_CRON_HOOK );
    }
}
register_deactivation_hook( __FILE__, 'doop_deactivate' );

/**
 * Run the product deletion on cron.
 */
add_action( DOOP_CRON_HOOK, 'doop_delete_old_out_of_stock_products' );
