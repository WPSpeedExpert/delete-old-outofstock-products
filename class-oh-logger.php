<?php
/**
 * Filename: class-oh-logger.php
 * Logger class for Delete Old Out-of-Stock Products
 *
 * @package Delete_Old_Outofstock_Products
 * @since 2.2.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class to handle logging functions
 */
class OH_Logger {
    /**
     * Logger instance
     *
     * @var OH_Logger
     */
    private static $instance = null;
    
    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;
    
    /**
     * Get single instance of the logger
     *
     * @return OH_Logger
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->log_file = $this->get_log_file_path();
    }
    
    /**
     * Get the log file path
     *
     * @return string The log file path
     */
    public function get_log_file_path() {
        $upload_dir = wp_upload_dir();
        $log_dir = trailingslashit($upload_dir['basedir']) . 'doop-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Create .htaccess to protect log files
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents(trailingslashit($log_dir) . '.htaccess', $htaccess_content);
        }
        
        return trailingslashit($log_dir) . 'deletion-log.txt';
    }
    
    /**
     * Add a message to the log
     *
     * @param string $message The message to log
     */
    public function log($message) {
        $log_file = $this->get_log_file_path();
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message" . PHP_EOL;
        
        // Create log directory if it doesn't exist
        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // Check if log file exists and is too large
        $max_size = 1048576; // 1MB
        if (file_exists($log_file) && filesize($log_file) > $max_size) {
            // Rotate log file
            $backup_log = str_replace('.txt', '-' . date('Y-m-d') . '.txt', $log_file);
            rename($log_file, $backup_log);
            
            // Delete old logs (keep only last 5)
            $log_files = glob(trailingslashit(dirname($log_file)) . 'deletion-log-*.txt');
            if ($log_files && count($log_files) > 5) {
                usort($log_files, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                
                // Delete oldest files
                $files_to_delete = array_slice($log_files, 0, count($log_files) - 5);
                foreach ($files_to_delete as $file) {
                    @unlink($file);
                }
            }
        }
        
        // Append to log
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
    
    /**
     * Get the content of the log file
     *
     * @return string The log content
     */
    public function get_log_content() {
        $log_path = $this->get_log_file_path();
        $log_content = '';
        
        if (file_exists($log_path)) {
            $log_content = file_get_contents($log_path);
        }
        
        return $log_content;
    }
    
    /**
     * Check if the log file exists
     *
     * @return bool True if log file exists
     */
    public function log_exists() {
        return file_exists($this->get_log_file_path());
    }
}
