<?php
/**
 * Main Configuration File
 * 
 * @package RMCU
 * @subpackage Config
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RMCU Configuration Class
 */
class RMCU_Config {
    
    /**
     * Plugin settings
     */
    private static $settings = [];
    
    /**
     * Configuration cache
     */
    private static $cache = [];
    
    /**
     * Initialize configuration
     */
    public static function init() {
        self::load_settings();
        self::setup_environment();
        self::register_hooks();
    }
    
    /**
     * Load all plugin settings
     */
    private static function load_settings() {
        // Load from database
        self::$settings = [
            'general' => get_option('rmcu_general_settings', self::get_defaults('general')),
            'capture' => get_option('rmcu_capture_settings', self::get_defaults('capture')),
            'seo' => get_option('rmcu_seo_settings', self::get_defaults('seo')),
            'media' => get_option('rmcu_media_settings', self::get_defaults('media')),
            'advanced' => get_option('rmcu_advanced_settings', self::get_defaults('advanced'))
        ];
        
        // Apply filters
        self::$settings = apply_filters('rmcu_config_settings', self::$settings);
    }
    
    /**
     * Setup environment
     */
    private static function setup_environment() {
        // Set memory limit for media processing
        if (self::get('advanced.increase_memory')) {
            @ini_set('memory_limit', '256M');
        }
        
        // Set max execution time for long operations
        if (self::get('advanced.increase_time_limit')) {
            @set_time_limit(300);
        }
        
        // Error reporting
        if (self::get('advanced.debug_mode')) {
            error_reporting(E_ALL);
            @ini_set('display_errors', 1);
            @ini_set('log_errors', 1);
            @ini_set('error_log', WP_CONTENT_DIR . '/rmcu-logs/error.log');
        }
    }
    
    /**
     * Register configuration hooks
     */
    private static function register_hooks() {
        // Update configuration cache when options are updated
        add_action('update_option_rmcu_general_settings', [__CLASS__, 'clear_cache']);
        add_action('update_option_rmcu_capture_settings', [__CLASS__, 'clear_cache']);
        add_action('update_option_rmcu_seo_settings', [__CLASS__, 'clear_cache']);
        add_action('update_option_rmcu_media_settings', [__CLASS__, 'clear_cache']);
        add_action('update_option_rmcu_advanced_settings', [__CLASS__, 'clear_cache']);
        
        // Add custom cron schedules
        add_filter('cron_schedules', [__CLASS__, 'add_cron_schedules']);
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Dot notation key (e.g., 'capture.enable_video')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get($key, $default = null) {
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        // Parse key
        $keys = explode('.', $key);
        $value = self::$settings;
        
        // Navigate through array
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                $value = $default;
                break;
            }
        }
        
        // Cache the result
        self::$cache[$key] = $value;
        
        return $value;
    }
    
    /**
     * Set configuration value
     * 
     * @param string $key Dot notation key
     * @param mixed $value Value to set
     * @return bool
     */
    public static function set($key, $value) {
        $keys = explode('.', $key);
        
        if (count($keys) < 2) {
            return false;
        }
        
        $section = array_shift($keys);
        $option_name = "rmcu_{$section}_settings";
        $settings = get_option($option_name, []);
        
        // Navigate to the correct position
        $current = &$settings;
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        
        // Set the value
        $current = $value;
        
        // Update database
        $result = update_option($option_name, $settings);
        
        // Clear cache
        if ($result) {
            self::clear_cache();
            self::load_settings();
        }
        
        return $result;
    }
    
    /**
     * Get all settings for a section
     * 
     * @param string $section Section name
     * @return array
     */
    public static function get_section($section) {
        return isset(self::$settings[$section]) ? self::$settings[$section] : [];
    }
    
    /**
     * Get default values
     * 
     * @param string $section Section name
     * @return array
     */
    public static function get_defaults($section = null) {
        require_once RMCU_PLUGIN_DIR . 'config/defaults.php';
        $defaults = RMCU_Defaults::get_all();
        
        if ($section) {
            return isset($defaults[$section]) ? $defaults[$section] : [];
        }
        
        return $defaults;
    }
    
    /**
     * Clear configuration cache
     */
    public static function clear_cache() {
        self::$cache = [];
        
        // Clear transients
        delete_transient('rmcu_config_cache');
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('rmcu_config');
        }
    }
    
    /**
     * Add custom cron schedules
     */
    public static function add_cron_schedules($schedules) {
        $schedules['rmcu_every_5_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes (RMCU)', 'rmcu')
        ];
        
        $schedules['rmcu_every_30_minutes'] = [
            'interval' => 1800,
            'display' => __('Every 30 Minutes (RMCU)', 'rmcu')
        ];
        
        return $schedules;
    }
    
    /**
     * Check if a feature is enabled
     * 
     * @param string $feature Feature key
     * @return bool
     */
    public static function is_enabled($feature) {
        $feature_map = [
            'video' => 'capture.enable_video',
            'audio' => 'capture.enable_audio',
            'screen' => 'capture.enable_screen',
            'n8n' => 'advanced.n8n_webhook_url',
            'rankmath' => 'seo.rankmath_integration',
            'debug' => 'advanced.debug_mode',
            'cache' => 'advanced.cache_enable',
            'async' => 'advanced.async_processing'
        ];
        
        $key = isset($feature_map[$feature]) ? $feature_map[$feature] : $feature;
        $value = self::get($key);
        
        return !empty($value);
    }
    
    /**
     * Get plugin mode (development/production)
     * 
     * @return string
     */
    public static function get_mode() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }
        
        if (self::get('advanced.debug_mode')) {
            return 'debug';
        }
        
        return 'production';
    }
    
    /**
     * Get API endpoints
     * 
     * @param string $endpoint Specific endpoint
     * @return string|array
     */
    public static function get_api_endpoint($endpoint = null) {
        $endpoints = [
            'n8n' => self::get('advanced.n8n_webhook_url'),
            'capture' => home_url('/wp-json/rmcu/v1/capture'),
            'webhook' => home_url('/rmcu-webhook/'),
            'ajax' => admin_url('admin-ajax.php')
        ];
        
        if ($endpoint) {
            return isset($endpoints[$endpoint]) ? $endpoints[$endpoint] : '';
        }
        
        return $endpoints;
    }
    
    /**
     * Get storage paths
     * 
     * @param string $type Path type
     * @return string
     */
    public static function get_path($type = 'uploads') {
        $upload_dir = wp_upload_dir();
        
        $paths = [
            'uploads' => $upload_dir['basedir'] . '/rmcu-captures/',
            'uploads_url' => $upload_dir['baseurl'] . '/rmcu-captures/',
            'logs' => WP_CONTENT_DIR . '/rmcu-logs/',
            'cache' => WP_CONTENT_DIR . '/cache/rmcu/',
            'temp' => sys_get_temp_dir() . '/rmcu/'
        ];
        
        $path = isset($paths[$type]) ? $paths[$type] : $paths['uploads'];
        
        // Create directory if it doesn't exist
        if (!strpos($type, '_url') && !file_exists($path)) {
            wp_mkdir_p($path);
            
            // Add .htaccess for security
            if ($type === 'logs') {
                file_put_contents($path . '.htaccess', 'Deny from all');
            }
        }
        
        return $path;
    }
    
    /**
     * Validate configuration
     * 
     * @return array Validation errors
     */
    public static function validate() {
        $errors = [];
        
        // Check required settings
        if (self::is_enabled('n8n') && !filter_var(self::get('advanced.n8n_webhook_url'), FILTER_VALIDATE_URL)) {
            $errors[] = __('Invalid n8n webhook URL', 'rmcu');
        }
        
        // Check write permissions
        $paths = ['uploads', 'logs', 'cache'];
        foreach ($paths as $path) {
            $dir = self::get_path($path);
            if (!is_writable(dirname($dir))) {
                $errors[] = sprintf(__('Directory not writable: %s', 'rmcu'), $dir);
            }
        }
        
        // Check PHP extensions
        $required_extensions = ['gd', 'curl'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $errors[] = sprintf(__('Required PHP extension missing: %s', 'rmcu'), $ext);
            }
        }
        
        return $errors;
    }
    
    /**
     * Export configuration
     * 
     * @return string JSON encoded configuration
     */
    public static function export() {
        $export = [
            'version' => RMCU_VERSION,
            'settings' => self::$settings,
            'timestamp' => current_time('mysql'),
            'site_url' => get_site_url()
        ];
        
        return json_encode($export, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import configuration
     * 
     * @param string $json JSON encoded configuration
     * @return bool
     */
    public static function import($json) {
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['settings'])) {
            return false;
        }
        
        // Validate version compatibility
        if (version_compare($data['version'], '1.0.0', '<')) {
            return false;
        }
        
        // Import settings
        foreach ($data['settings'] as $section => $settings) {
            update_option("rmcu_{$section}_settings", $settings);
        }
        
        // Reload configuration
        self::clear_cache();
        self::load_settings();
        
        return true;
    }
}

// Initialize configuration
add_action('init', ['RMCU_Config', 'init'], 1);