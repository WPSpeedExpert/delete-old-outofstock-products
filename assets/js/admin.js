/**
 * Filename: assets/js/admin.js
 * Admin JavaScript for Delete Old Out-of-Stock Products
 *
 * Handles AJAX status monitoring and UI updates for the product deletion process.
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.3.8
 */

(function($) {
    'use strict';
    
    // Global variables
    let checkInterval = null;
    let isPolling = false;
    let viewingLog = false;
    let debug = true; // Enable for debugging
    
    // Document ready handler
    $(document).ready(function() {
        if (debug) console.log('DOOP Admin JS initialized');
        
        // Initialize log viewer
        initLogViewer();
        
        // Track manual run form submission
        $('form[action*="admin-post.php"]').on('submit', function(e) {
            if (debug) console.log('Manual run form submitted');
            localStorage.setItem('oh_doop_manual_run', 'initiated');
            localStorage.setItem('oh_doop_manual_run_time', Date.now());
            // Let the form submit normally
        });
        
        // Check if we just came back from a form submission
        if (localStorage.getItem('oh_doop_manual_run') === 'initiated') {
            if (debug) console.log('Detected previous form submission, starting monitoring');
            // Remove the flag but start monitoring
            localStorage.removeItem('oh_doop_manual_run');
            initStatusMonitoring();
        }
        
        // Check URL parameters for status indications
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('manual') === '1' || urlParams.get('deletion_status') === 'running') {
            if (debug) console.log('Status monitoring started based on URL parameters');
            initStatusMonitoring();
        }
        
        // Also check the data attribute from PHP
        if (typeof ohDoopData !== 'undefined' && 
            (ohDoopData.isRunning || ohDoopData.deletionStatus === 'running')) {
            if (debug) console.log('Status monitoring started based on ohDoopData');
            initStatusMonitoring();
        }
    });
    
    // Initialize status monitoring
    function initStatusMonitoring() {
        if (debug) console.log('Initializing status monitoring');
        
        // Create status container if not present
        let statusEl = $('#oh-process-status');
        if (statusEl.length === 0) {
            $('.wrap').prepend('<div id="oh-process-status"></div>');
            statusEl = $('#oh-process-status');
        }
        
        // Show initial status
        statusEl.removeClass('success error').show().html(
            '<p><strong>Process is running...</strong></p>' +
            '<p>You can navigate away from this page. The process will continue in the background.</p>'
        );
        
        // Add spinner to button
        $('.oh-status-indicator').addClass('running').html('<span class="spinner is-active" style="float:none; margin:0;"></span>');
        
        // Start checking status immediately
        checkStatus();
        
        // Clear any existing interval
        if (checkInterval) {
            clearInterval(checkInterval);
        }
        
        // Set up interval (check every 3 seconds)
        checkInterval = setInterval(checkStatus, 3000);
    }
    
    // Check current status via AJAX
    function checkStatus() {
        // Prevent multiple simultaneous requests
        if (isPolling) {
            if (debug) console.log('Already polling, skipping this check');
            return;
        }
        
        if (debug) console.log('Checking deletion status via AJAX');
        isPolling = true;
        
        $.ajax({
            url: ajaxurl, // WordPress global variable
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'oh_check_deletion_status',
                security: ohDoopData.nonce,
                _: Date.now() // Prevent caching
            },
            success: function(response) {
                if (debug) console.log('AJAX response:', response);
                
                if (response && response.success) {
                    updateUI(response.data);
                } else {
                    if (debug) console.error('AJAX response unsuccessful:', response);
                    
                    // Show error in UI
                    let statusEl = $('#oh-process-status');
                    statusEl.addClass('error').html(
                        '<p><strong>Error checking status</strong></p>' +
                        '<p>There was an issue checking the deletion status. Will try again shortly.</p>'
                    );
                }
            },
            error: function(xhr, status, error) {
                if (debug) console.error('AJAX error:', status, error);
                
                // Show error in UI
                let statusEl = $('#oh-process-status');
                statusEl.addClass('error').html(
                    '<p><strong>Error communicating with server</strong></p>' +
                    '<p>There was a problem checking the deletion status: ' + status + '</p>'
                );
            },
            complete: function() {
                // Always mark as not polling so future checks can run
                isPolling = false;
            }
        });
    }
    
    // Update UI based on status response
    function updateUI(data) {
        if (debug) console.log('Updating UI with data:', data);
        
        let statusEl = $('#oh-process-status');
        
        // Process is running
        if (data.is_running) {
            if (debug) console.log('Process is running');
            
            statusEl.removeClass('success error').show().html(
                '<p><strong>Process is running...</strong>' + 
                (data.time_elapsed ? ' (' + data.time_elapsed + ' ago)' : '') + '</p>' +
                '<p>You can navigate away from this page. The process will continue in the background.</p>'
            );
            
            // Add log button if available
            if (data.has_log) {
                statusEl.append('<p><button type="button" class="button oh-view-log-btn">' + 
                    (viewingLog ? 'Hide Log' : 'View Log') + 
                    '</button></p>');
                bindLogButton();
            }
            
            // Disable run button
            $('button[name="run_now"]').prop('disabled', true);
            $('.oh-status-indicator').addClass('running').html('<span class="spinner is-active" style="float:none; margin:0;"></span>');
        }
        // Process completed
        else if (data.is_completed) {
            if (debug) console.log('Process completed, deleted:', data.deleted_count);
            
            statusEl.removeClass('error').addClass('success').show().html(
                '<p><strong>Process completed!</strong></p>' +
                '<p>' + data.deleted_count + ' products were deleted.</p>'
            );
            
            // Add log button if available
            if (data.has_log) {
                statusEl.append('<p><button type="button" class="button oh-view-log-btn">' + 
                    (viewingLog ? 'Hide Log' : 'View Log') + 
                    '</button></p>');
                bindLogButton();
            }
            
            // Enable run button
            $('button[name="run_now"]').prop('disabled', false);
            $('.oh-status-indicator').removeClass('running').html('');
            
            // Stop checking status
            clearInterval(checkInterval);
            
            // Reload page after a short delay to show updated stats
            setTimeout(function() {
                if (debug) console.log('Reloading page with completion parameters');
                window.location.href = window.location.href.split('?')[0] + 
                    '?page=doop-settings&deletion_status=completed&deleted=' + 
                    data.deleted_count + '&t=' + Date.now();
            }, 3000);
        }
        // Too many products
        else if (data.too_many) {
            if (debug) console.log('Too many products:', data.too_many_count);
            
            statusEl.removeClass('success').addClass('error').show().html(
                '<p><strong>Too many products eligible for deletion</strong></p>' +
                '<p>' + data.too_many_count + ' products eligible for deletion, which exceeds the safe limit for manual deletion (200).</p>'
            );
            
            // Enable run button
            $('button[name="run_now"]').prop('disabled', false);
            $('.oh-status-indicator').removeClass('running').html('');
            
            // Stop checking status
            clearInterval(checkInterval);
            
            // Reload page after a short delay
            setTimeout(function() {
                if (debug) console.log('Reloading page with too_many parameters');
                window.location.href = window.location.href.split('?')[0] + 
                    '?page=doop-settings&deletion_status=too_many&count=' + 
                    data.too_many_count + '&t=' + Date.now();
            }, 3000);
        }
        // No process running
        else {
            if (debug) console.log('No process running');
            
            $('button[name="run_now"]').prop('disabled', false);
            $('.oh-status-indicator').removeClass('running').html('');
            
            // Keep the status area visible if we previously showed something
            if (statusEl.html()) {
                statusEl.removeClass('error success').html(
                    '<p>No deletion process is currently running.</p>'
                );
            }
            
            // Stop frequent checking
            clearInterval(checkInterval);
        }
    }
    
    // Initialize log viewer
    function initLogViewer() {
        bindLogButton();
    }
    
    // Bind log button click event
    function bindLogButton() {
        $('.oh-view-log-btn').off('click').on('click', function() {
            toggleLogView();
        });
    }
    
    // Toggle log view
    function toggleLogView() {
        let logEl = $('#oh-deletion-log');
        
        if (logEl.is(':visible')) {
            logEl.hide();
            $('.oh-view-log-btn').text('View Log');
            viewingLog = false;
            return;
        }
        
        // Show loading indicator
        logEl.show().html('Loading log...');
        $('.oh-view-log-btn').text('Hide Log');
        viewingLog = true;
        
        // Load log content via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'oh_get_deletion_log',
                security: ohDoopData.nonce,
                _: Date.now() // Prevent caching
            },
            success: function(response) {
                if (debug) console.log('Log content response:', response);
                
                if (response && response.success && response.data.log_content) {
                    logEl.html(response.data.log_content);
                } else {
                    logEl.html('No log content available');
                }
                
                // Scroll to bottom of log
                logEl.scrollTop(logEl[0].scrollHeight);
            },
            error: function(xhr, status, error) {
                if (debug) console.error('Error loading log:', status, error);
                logEl.html('Error loading log content');
            }
        });
    }
    
})(jQuery);
