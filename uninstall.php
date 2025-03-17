<?php
/**
 * Uninstall Delete Old Out-of-Stock Products
 *
 * Cleanup when the plugin is deleted.
 *
 * @version 1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove the cron event.
$timestamp = wp_next_scheduled( 'doop_cron_delete_old_products' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'doop_cron_delete_old_products' );
}

// Delete plugin options
delete_option( 'oh_doop_options' );
