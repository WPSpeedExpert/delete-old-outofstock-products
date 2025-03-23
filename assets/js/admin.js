/**
 * Filename: assets/js/admin.js
 * Admin JavaScript for Delete Old Out-of-Stock Products
 *
 * Handles AJAX status monitoring and UI updates for the product deletion process.
 *
 * @package Delete_Old_Outofstock_Products
 * @version 2.3.11
 */

/**
 * TABLE OF CONTENTS:
 *
 * 1. INITIALIZATION
 *    1.1 Document ready handler
 *    1.2 Global variables
 *    1.3 Initial process check
 *
 * 2. FORM SUBMISSION HANDLING
 *    2.1 Form interception
 *    2.2 AJAX submission
 * 
 * 3. STATUS MONITORING
 *    3.1 Status check function
 *    3.2 UI updates
 *
 * 4. LOG FUNCTIONALITY
 *    4.1 Log viewer toggle
 *    4.2 Log content loading
 */

(function($) {
    'use strict';
    
    // 1. INITIALIZATION
    // ====================================
    
    // 1.1 Global variables
    let checkInterval = null;
    let isPolling = false;
    let viewingLog = false;
    let debug = false; // Set to false for production
    let isProcessRunning = false; // Track if a process is currently running
    
    // 1.2 Document ready handler
    $(document).ready(function() {
        if (debug) console.log('DOOP Admin JS initialized');
        
        // Initialize log viewer
        initLogViewer();
        
        // Bind refresh status button
        bindRefreshStatus();
        
        // 1.3 Initial process check - Check if a process is already running when page loads
        checkIfProcessRunning();
        
        // 2. FORM SUBMISSION HANDLING
        // ====================================
        
        // 2.1 Direct form handling for manual run
        $('form[action*="admin-post.php"]').on('submit', function(e) {
            // If a process is already running, prevent starting another one
            if (isProcessRunning) {
                e.preventDefault();
                
                // Show a message to the user
                let statusEl = $('#oh-process-status');
                if (statusEl.length === 0) {
                    $('.wrap').prepend('<div id="oh-process-status"></div>');
                    statusEl = $('#oh-process-status');
                }
                
                statusEl.removeClass('success').addClass('error').show().html(
                    '<p><strong>A deletion process is already running!</strong></p>' +
                    '<p>Please wait for the current process to complete before starting a new one.</p>' +
                    '<p><a href="javascript:void(0);" class="oh-refresh-status button">Refresh Status</a></p>'
                );
                
                // Bind the refresh button
                bindRefreshStatus();
                
                return false;
            }
            
            e.preventDefault(); // Prevent default form submission
            
            if (debug) console.log('Form submission intercepted');
            
            // Show processing state immediately
            $('.oh-status-indicator').addClass('running').html('<span class="spinner is-active" style="float:none; margin:0;"></span>');
            
            // Create status display if it doesn't exist
            let statusEl = $('#oh-process-status');
            if (statusEl.length === 0) {
                $('.wrap').prepend('<div id="oh-process-status"></div>');
                statusEl = $('#oh-process-status');
            }
            
            statusEl.show().removeClass('success error').html(
                '<p><strong>Starting cleanup process...</strong></p>' +
                '<p>This may take a moment, please wait...</p>'
            );
            
            // Get form data
            var formData = new FormData(this);
            
            // Submit the form via AJAX instead of regular form submission
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (debug) console.log('Manual run form submitted successfully');
                    
                    // Mark process as running
                    isProcessRunning = true;
                    
                    // Start monitoring the deletion process status
                    checkStatus();
                    
                    // Clear existing intervals
                    if (checkInterval) {
                        clearInterval(checkInterval);
                    }
                    
                    // Set interval to check status regularly
                    checkInterval = setInterval(checkStatus, 3000);
                },
                error: function(xhr, status, error) {
                    if (debug) console.error('Form submission error:', error);
                    statusEl.addClass('error').html(
                        '<p><strong>Error starting cleanup</strong></p>' +
                        '<p>There was a problem starting the cleanup process. Please try again.</p>' +
                        '<p><a href="javascript:void(0);" class="oh-refresh-status button">Refresh Status</a></p>'
                    );
                    $('.oh-status-indicator').removeClass('running').html('');
                    bindRefreshStatus();
                    
                    // Double-check the process status to be sure
                    checkIfProcessRunning();
                }
            });
        });
        
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
    
    // Check if a process is already running
    function checkIfProcessRunning() {
        if (debug) console.log('Checking if a process is already running');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'oh_check_deletion_status',
                security: ohDoopData.nonce,
                _: Date.now()
            },
            success: function(response) {
                if (response && response.success) {
                    if (response.data.is_running) {
                        if (debug) console.log('Process is already running');
                        
                        // Mark as running
                        isProcessRunning = true;
                        
                        // Update the UI to show the running process
                        let statusEl = $('#oh-process-status');
                        if (statusEl.length === 0) {
                            $('.wrap').prepend('<div id="oh-process-status"></div>');
                            statusEl = $('#oh-process-status');
                        }
                        
                        statusEl.removeClass('success error').show().html(
                            '<p><strong>A deletion process is already running!</strong>' + 
                            (response.data.time_elapsed ? ' (' + response.data.time_elapsed + ' ago)' : '') + '</p>' +
                            '<p>Please wait for the current process to complete before starting a new one.</p>' +
                            '<p><a href="javascript:void(0);" class="oh-refresh-status button">Refresh Status</a></p>'
                        );
                        
                        // Bind the refresh button
                        bindRefreshStatus();
                        
                        // Disable the run button
                        $('button[name="run_now"]').prop('disabled', true);
                        
                        // Start monitoring
                        initStatusMonitoring();
                    } else {
                        isProcessRunning = false;
                        $('button[name="run_now"]').prop('disabled', false);
                    }
                }
            },
            error: function() {
                if (debug) console.error('Error checking if process is running');
                // Don't update isProcessRunning flag on error
            }
        });
    }
    
    // Bind refresh status button
    function bindRefreshStatus() {
        $('.oh-refresh-status').off('click').on('click', function(e) {
            e.preventDefault();
            
            if (debug) console.log('Refresh status clicked');
            
            // Create a temporary message
            let statusEl = $('#oh-process-status');
            if (statusEl.length === 0) {
                $('.wrap').prepend('<div id="oh-process-status"></div>');
                statusEl = $('#oh-process-status');
            }
            
            statusEl.removeClass('success error').html(
                '<p>Checking status... <span class="spinner is-active" style="float:none; margin:0;"></span></p>'
            );
            
            // Do an immediate status check
            checkStatus();
        });
    }
    
    // 3. STATUS MONITORING
    // ====================================
    
    // 3.1 Initialize status monitoring
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
            '<p>You can navigate away from this page. The process will continue in the background.</p>' +
            '<p><a href="javascript:void(0);" class="oh-refresh-status button">Refresh Status</a></p>'
        );
        
        // Bind the refresh button
        bindRefreshStatus();
        
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
    
    // 3.2 Check current status via AJAX
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
                    // Update process running flag based on response
                    isProcessRunning = response.data.is_running;
                    
                    updateUI(response.data);
                } else {
                    if (debug) console.error('AJAX response unsuccessful:', response);
                    
                    // Show error in UI
                    let statusEl = $('#oh-process-status');
                    statusEl.addClass('error').html(
                        '<p><strong>Error checking status</strong></p>' +
                        '<p>There was an issue checking the deletion status. Please try again.</p>' +
                        '<p><a href="javascript:void(0);" class="oh-refresh-status button">Refresh Status</a></p>'
                    );
                    
                    // Bind the refresh button
                    bindRefreshStatus();
                }
            },
            error: function(xhr, status, error) {
                if (debug) console.error('AJAX error:', status, error);
                
                // Show error in UI
                let statusEl = $('#oh-process-status');
                statusEl.addClass('error').html(
                    '<p><strong>Error communicating with server</strong></p>' +
                    '<p>There was a problem checking the deletion status: ' + status + '</p>' +
                    '<p><a href="javascript:void(0);" class="oh-refresh-status button">Refresh Status</a></p>'
                );
                
                // Bind the refresh button
                bindRefreshStatus();
            },
            complete: function() {
                // Always mark as not polling so future checks can run
                isPolling = false;
            }
        });
    }
    
    // 3.3 Update UI based on status response
    function updateUI(data) {
        if (debug) console.log('Updating UI with data:', data);
        
        let statusEl = $('#oh-process-status');
        
        // Process is running
        if (data.is_running) {
            if (debug) console.log('Process is running');
            
            // Update global flag
            isProcessRunning = true;
            
            statusEl.removeClass('success error').show().html(
                '<p><strong>Process is running...</strong>' + 
                (data.time_elapsed ? ' (' + data.time_elapsed + ' ago)' : '') + '</p>' +
                '<p>You can navigate away from this page. The process will continue in the background.</p>' +
                '<p><a href="javascript:void(0);" class="oh-refresh-status button">Refresh Status</a></p>'
            );
            
            // Bind the refresh button
            bindRefreshStatus();
            
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
            
            // Update global flag
            isProcessRunning = false;
            
            statusEl.removeClass('error').addClass('success').show().html(
                '<p><strong>Process completed!</strong></p>' +
                '<p>' + data.deleted_count + ' products were deleted.</p>' +
                '<p><a href="javascript:void(0);" class="oh-refresh-status button">Refresh Status</a></p>'
            );
            
            // Bind the refresh button
            bindRefreshStatus();
            
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
            
            // Update global flag
            isProcessRunning = false;
            
            statusEl.removeClass('success').addClass('error').show().html(
                '<p><strong>Too many products eligible for deletion</strong></p>' +
                '<p>' + data.too_many_count + ' products eligible for deletion, which exceeds the safe limit for manual deletion (200).</p>' +
                '<p><a href="javascript:void(0);" class="oh-refresh-status button">Refresh Status</a></p>'
            );
            
            // Bind the refresh button
            bindRefreshStatus();
            
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
            
            // Update global flag
            isProcessRunning = false;
            
            $('button[name="run_now"]').prop('disabled', false);
            $('.oh-status-indicator').removeClass('running').html('');
            
            // Keep the status area visible if we previously showed something
            if (statusEl.html()) {
                statusEl.removeClass('error success').html(
                    '<p>No deletion process is currently running.</p>' +
                    '<p><a href="javascript:void(0);" class="oh-refresh-status button">Refresh Status</a></p>'
                );
                
                // Bind the refresh button
                bindRefreshStatus();
            }
            
            // Stop frequent checking
            clearInterval(checkInterval);
        }
    }
    
    // 4. LOG FUNCTIONALITY
    // ====================================
    
    // 4.1 Initialize log viewer
    function initLogViewer() {
        bindLogButton();
    }
    
    // 4.2 Bind log button click event
    function bindLogButton() {
        $('.oh-view-log-btn').off('click').on('click', function() {
            toggleLogView();
        });
    }
    
    // 4.3 Toggle log view
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
