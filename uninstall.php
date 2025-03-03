<?php
// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove the cron event.
$timestamp = wp_next_scheduled( 'doop_cron_delete_old_products' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'doop_cron_delete_old_products' );
}
