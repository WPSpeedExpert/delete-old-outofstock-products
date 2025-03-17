<?php
/**
 * Uninstall file for Delete Old Out-of-Stock Products
 * 
 * Removes plugin data when uninstalled.
 * 
 * @package Delete Old Out-of-Stock Products
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove the cron event.
$timestamp = wp_next_scheduled('doop_cron_delete_old_products');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'doop_cron_delete_old_products');
}

// Remove the plugin options
delete_option('oh_doop_options');

// Clean up any transients
delete_transient('oh_doop_products_to_delete');
delete_transient('oh_doop_products_total');
delete_transient('oh_doop_products_deleted');
