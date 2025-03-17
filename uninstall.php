<?php
/**
 * Uninstall file for Delete Old Out-of-Stock Products
 * 
 * Removes plugin data when uninstalled.
 * 
 * @package Delete Old Out-of-Stock Products
 * @since 2.1.0
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

// Remove any background deletion actions
$timestamp = wp_next_scheduled('oh_doop_background_deletion');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'oh_doop_background_deletion');
}

// Remove the plugin options
delete_option('oh_doop_options');
delete_option('oh_doop_deletion_running');
delete_option('oh_doop_last_run_count');
