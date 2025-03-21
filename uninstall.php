<?php
/**
 * Uninstall cleanup script for Delete Old Out-of-Stock Products
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.2.3
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Define option names
define( 'DOOP_OPTIONS_KEY', 'oh_doop_options' );
define( 'DOOP_CRON_HOOK', 'doop_cron_delete_old_products' );

// Remove the cron event.
$timestamp = wp_next_scheduled( DOOP_CRON_HOOK );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, DOOP_CRON_HOOK );
}

// Remove all plugin options
delete_option( DOOP_OPTIONS_KEY );
delete_option( 'oh_doop_last_cron_time' );
delete_option( 'oh_doop_deletion_running' );
delete_option( 'oh_doop_last_run_count' );
delete_option( 'oh_doop_too_many_products' );
