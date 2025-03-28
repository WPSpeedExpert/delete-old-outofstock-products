<?php
/**
 * Filename: uninstall.php
 * Uninstall cleanup script for Delete Old Out-of-Stock Products
 *
 * This file is executed when the plugin is uninstalled to clean up any data created by the plugin.
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.4.4
 */

/**
 * TABLE OF CONTENTS:
 *
 * 1. INITIALIZATION
 *    1.1 Security check
 *    1.2 Define constants
 *
 * 2. CLEANUP
 *    2.1 Remove cron events
 *    2.2 Remove options
 *    2.3 Remove log files
 */

// 1. INITIALIZATION
// ====================================

// 1.1 Security check - Exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1.2 Define constants (for backwards compatibility)
define( 'DOOP_OPTIONS_KEY', 'oh_doop_options' );
define( 'DOOP_CRON_HOOK', 'doop_cron_delete_old_products' );
define( 'DOOP_PROCESS_OPTION', 'oh_doop_process_running' );
define( 'DOOP_RESULT_OPTION', 'oh_doop_last_run_count' );

// 2. CLEANUP
// ====================================

// 2.1 Remove the cron event
$timestamp = wp_next_scheduled( DOOP_CRON_HOOK );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, DOOP_CRON_HOOK );
}

// 2.2 Remove all plugin options
delete_option( DOOP_OPTIONS_KEY );
delete_option( 'oh_doop_last_cron_time' );
delete_option( DOOP_PROCESS_OPTION );
delete_option( DOOP_RESULT_OPTION );
delete_option( 'oh_doop_too_many_products' );
delete_option( 'oh_doop_deleted_products' ); // Remove the 410 tracking data

// 2.3 Remove log files
$upload_dir = wp_upload_dir();
$log_dir = trailingslashit($upload_dir['basedir']) . 'doop-logs';

if (file_exists($log_dir) && is_dir($log_dir)) {
    // Remove all log files
    $log_files = glob(trailingslashit($log_dir) . '*.txt');
    if ($log_files) {
        foreach ($log_files as $file) {
            @unlink($file);
        }
    }
    
    // Remove .htaccess protection file
    $htaccess_file = trailingslashit($log_dir) . '.htaccess';
    if (file_exists($htaccess_file)) {
        @unlink($htaccess_file);
    }
    
    // Remove log directory
    @rmdir($log_dir);
}
