/**
 * Filename: assets/js/admin.js
 * Admin JavaScript for Delete Old Out-of-Stock Products
 *
 * Handles AJAX status monitoring and UI updates for the product deletion process.
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.3.6
 */

/**
 * TABLE OF CONTENTS:
 *
 * 1. INITIALIZATION
 *    1.1 Document ready handler
 *    1.2 Global variables
 *
 * 2. STATUS MONITORING
 *    2.1 Status check function
 *    2.2 UI updates
 *
 * 3. LOG FUNCTIONALITY
 *    3.1 Log viewer toggle
 *    3.2 Log content loading
 */

(function($) {
    'use strict';
    
    // 1. INITIALIZATION
    // ====================================
    
    // 1.1 Global variables
    let checkInterval = null;
    let isPolling = false;
    let viewingLog = false;
    
    // 1.2 Document ready handler
    $(document).ready(function() {
        // Initialize log viewer
        initLogViewer();
        
        // Check initial status based on URL parameters or data attribute
        const deletionStatus = ohDoopData.deletionStatus;
        
        // Start status monitoring if process is running or marked as running
        if (ohDoopData.isRunning || deletionStatus === 'running') {
            initStatusMonitoring();
        }
        
        // Handle manual run button click
        $('form[action*="oh_run_product_deletion"]').on('submit', function() {
            // Show spinner next to button
            $('.oh-status-indicator').addClass('running').html('<span class="spinner is-active" style="float:none; margin:0;"></span>');
            
            // Don't disable the button here - let the form submit normally
            // After redirect, the status will be monitored via AJAX
        });
    });
    
    // 2. STATUS MONITORING
    // ====================================
    
    // 2.1 Initialize status monitoring
    function initStatusMonitoring() {
        // Create status container if not present
        let statusEl = $('#oh-process-status');
        if (statusEl.length === 0) {
            $('.wrap').prepend('<div id="oh-process-status"></div>');
            statusEl = $('#oh-process-status');
        }
        
        // Show initial status
        statusEl.removeClass('success error').show().html(
            '<p><strong>' + ohDoopData.strings.running + '</strong></p>' +
            '<p>' + ohDoopData.strings.navigateAway + '</p>'
        );
        
        // Add spinner to button
        $('.oh-status-indicator').addClass('running').html('<span class="spinner is-active" style="float:none; margin:0;"></span>');
        
        // Start checking status
        checkStatus();
        
        // Set up interval (check every 5 seconds)
        if (checkInterval) {
            clearInterval(checkInterval);
        }
        checkInterval = setInterval(checkStatus, 5000);
    }
    
    // 2.2 Check current status via AJAX
    function checkStatus() {
        // Prevent multiple simultaneous requests
        if (isPolling) {
            return;
        }
        
        isPolling = true;
        
        $.ajax({
            url: ohDoopData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'oh_check_deletion_status',
                security: ohDoopData.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateUI(response.data);
                } else {
                    console.error('AJAX response unsuccessful', response);
                    // Still mark as not polling so we can try again
                    isPolling = false;
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                
                // Handle error - show message in status area
                let statusEl = $('#oh-process-status');
                statusEl.addClass('error').html(
                    '<p><strong>Error checking status</strong></p>' +
                    '<p>There was a problem communicating with the server. Will try again shortly.</p>'
                );
                
                // Mark as not polling so we can try again
                isPolling = false;
            },
            complete: function() {
                // Always ensure isPolling is reset, even if there's another error
                isPolling = false;
            }
        });
    }
    
    // 2.3 Update UI based on status response
    function updateUI(data) {
        let statusEl = $('#oh-process-status');
        
        // Update status message based on process state
        if (data.is_running) {
            // Process is running
            statusEl.removeClass('success error').show().html(
                '<p><strong>' + ohDoopData.strings.running + '</strong>' + 
                (data.time_elapsed ? ' (' + data.time_elapsed + ' ' + ohDoopData.strings.ago + ')' : '') + '</p>' +
                '<p>' + ohDoopData.strings.navigateAway + '</p>'
            );
            
            // Add log button if available
            if (data.has_log) {
                statusEl.append('<p><button type="button" class="button oh-view-log-btn">' + 
                    (viewingLog ? ohDoopData.strings.hideLog : ohDoopData.strings.viewLog) + 
                    '</button></p>');
                bindLogButton();
            }
            
            // Disable run button
            $('button[name="run_now"]').prop('disabled', true);
            $('.oh-status-indicator').addClass('running').html('<span class="spinner is-active" style="float:none; margin:0;"></span>');
        } else if (data.is_completed) {
            // Process completed
            statusEl.removeClass('error').addClass('success').show().html(
                '<p><strong>' + ohDoopData.strings.completed + '</strong></p>' +
                '<p>' + data.deleted_count + ' ' + ohDoopData.strings.productsDeleted + '</p>'
            );
            
            // Add log button if available
            if (data.has_log) {
                statusEl.append('<p><button type="button" class="button oh-view-log-btn">' + 
                    (viewingLog ? ohDoopData.strings.hideLog : ohDoopData.strings.viewLog) + 
                    '</button></p>');
                bindLogButton();
            }
            
            // Enable run button
            $('button[name="run_now"]').prop('disabled', false);
            $('.oh-status-indicator').removeClass('running').html('');
            
            // Stop checking status frequently
            clearInterval(checkInterval);
            
            // Reload page after a short delay to show updated stats
            setTimeout(function() {
                window.location.href = window.location.href.split('?')[0] + 
                    '?page=doop-settings&deletion_status=completed&deleted=' + 
                    data.deleted_count + '&t=' + Date.now();
            }, 3000);
        } else if (data.too_many) {
            // Too many products
            statusEl.removeClass('success').addClass('error').show().html(
                '<p><strong>' + ohDoopData.strings.tooMany + '</strong></p>' +
                '<p>' + data.too_many_count + ' ' + ohDoopData.strings.tooManyMsg + '</p>'
            );
            
            // Enable run button
            $('button[name="run_now"]').prop('disabled', false);
            $('.oh-status-indicator').removeClass('running').html('');
            
            // Stop checking status
            clearInterval(checkInterval);
            
            // Reload page after a short delay
            setTimeout(function() {
                window.location.href = window.location.href.split('?')[0] + 
                    '?page=doop-settings&deletion_status=too_many&count=' + 
                    data.too_many_count + '&t=' + Date.now();
            }, 3000);
        } else {
            // No process running
            $('button[name="run_now"]').prop('disabled', false);
            $('.oh-status-indicator').removeClass('running').html('');
            
            // Stop frequent checking
            clearInterval(checkInterval);
        }
    }
    
    // 3. LOG FUNCTIONALITY
    // ====================================
    
    // 3.1 Initialize log viewer
    function initLogViewer() {
        bindLogButton();
    }
    
    // 3.2 Bind log button click event
    function bindLogButton() {
        $('.oh-view-log-btn').off('click').on('click', function() {
            toggleLogView();
        });
    }
    
    // 3.3 Toggle log view
    function toggleLogView() {
        let logEl = $('#oh-deletion-log');
        
        if (logEl.is(':visible')) {
            logEl.hide();
            $('.oh-view-log-btn').text(ohDoopData.strings.viewLog);
            viewingLog = false;
            return;
        }
        
        // Show loading indicator
        logEl.show().html(ohDoopData.strings.loadingLog);
        $('.oh-view-log-btn').text(ohDoopData.strings.hideLog);
        viewingLog = true;
        
        // Load log content via AJAX
        $.ajax({
            url: ohDoopData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'oh_get_deletion_log',
                security: ohDoopData.nonce
            },
            success: function(response) {
                if (response.success && response.data.log_content) {
                    logEl.html(response.data.log_content);
                } else {
                    logEl.html(ohDoopData.strings.noLog);
                }
                
                // Scroll to bottom of log
                logEl.scrollTop(logEl[0].scrollHeight);
            },
            error: function(xhr, status, error) {
                console.error('Error loading log:', status, error);
                logEl.html(ohDoopData.strings.errorLog);
            }
        });
    }
    
})(jQuery);
