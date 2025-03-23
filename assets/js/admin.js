/**
 * Filename: assets/js/admin.js
 * 
 * Admin JavaScript for Delete Old Out-of-Stock Products
 * Handles AJAX status monitoring and UI updates for the product deletion process.
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.3.0
 */

(function($) {
    'use strict';
    
    // Document ready
    $(document).ready(function() {
        // Initialize log viewer
        initLogViewer();
        
        // Start status monitoring if process is running
        if (ohDoopData.isRunning || ohDoopData.deletionStatus === 'running') {
            initStatusMonitoring();
        }
    });
    
    // Global variables
    let checkInterval = null;
    let isPolling = false;
    let viewingLog = false;
    
    /**
     * Initialize status monitoring
     */
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
        checkInterval = setInterval(checkStatus, 5000);
    }
    
    /**
     * Check current status via AJAX
     */
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
                action: ohDoopData.action,
                security: ohDoopData.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateUI(response.data);
                }
                isPolling = false;
            },
            error: function() {
                isPolling = false;
            }
        });
    }
    
    /**
     * Update UI based on status response
     */
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
    
    /**
     * Initialize log viewer
     */
    function initLogViewer() {
        bindLogButton();
    }
    
    /**
     * Bind log button click event
     */
    function bindLogButton() {
        $('.oh-view-log-btn').off('click').on('click', function() {
            toggleLogView();
        });
    }
    
    /**
     * Toggle log view
     */
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
            error: function() {
                logEl.html(ohDoopData.strings.errorLog);
            }
        });
    }
    
})(jQuery);
