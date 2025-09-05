<?php
/**
 * Plugin Constants Definition
 * 
 * @package RMCU
 * @subpackage Config
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define plugin constants
 */
class RMCU_Constants {
    
    /**
     * Define all constants
     */
    public static function define() {
        // Plugin Version
        self::define_constant('RMCU_VERSION', '2.0.0');
        
        // Plugin Paths
        self::define_constant('RMCU_PLUGIN_FILE', dirname(dirname(__FILE__)) . '/rankmath-capture-unified.php');
        self::define_constant('RMCU_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
        self::define_constant('RMCU_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));
        self::define_constant('RMCU_PLUGIN_BASENAME', plugin_basename(RMCU_PLUGIN_FILE));
        
        // Directories
        self::define_constant('RMCU_INCLUDES_DIR', RMCU_PLUGIN_DIR . 'includes/');
        self::define_constant('RMCU_ADMIN_DIR', RMCU_PLUGIN_DIR . 'admin/');
        self::define_constant('RMCU_PUBLIC_DIR', RMCU_PLUGIN_DIR . 'public/');
        self::define_constant('RMCU_ASSETS_DIR', RMCU_PLUGIN_DIR . 'assets/');
        self::define_constant('RMCU_TEMPLATES_DIR', RMCU_PLUGIN_DIR . 'templates/');
        self::define_constant('RMCU_LANGUAGES_DIR', RMCU_PLUGIN_DIR . 'languages/');
        self::define_constant('RMCU_VENDOR_DIR', RMCU_PLUGIN_DIR . 'vendor/');
        
        // URLs
        self::define_constant('RMCU_ASSETS_URL', RMCU_PLUGIN_URL . 'assets/');
        self::define_constant('RMCU_CSS_URL', RMCU_ASSETS_URL . 'css/');
        self::define_constant('RMCU_JS_URL', RMCU_ASSETS_URL . 'js/');
        self::define_constant('RMCU_IMAGES_URL', RMCU_ASSETS_URL . 'images/');
        
        // Database
        self::define_constant('RMCU_DB_VERSION', '1.0.0');
        self::define_constant('RMCU_TABLE_PREFIX', 'rmcu_');
        
        // API
        self::define_constant('RMCU_API_VERSION', 'v1');
        self::define_constant('RMCU_API_NAMESPACE', 'rmcu/' . RMCU_API_VERSION);
        
        // Capture Types
        self::define_constant('RMCU_CAPTURE_VIDEO', 'video');
        self::define_constant('RMCU_CAPTURE_AUDIO', 'audio');
        self::define_constant('RMCU_CAPTURE_SCREEN', 'screen');
        self::define_constant('RMCU_CAPTURE_SCREENSHOT', 'screenshot');
        
        // File Upload
        self::define_constant('RMCU_MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB in bytes
        self::define_constant('RMCU_CHUNK_SIZE', 1024 * 1024); // 1MB chunks
        self::define_constant('RMCU_ALLOWED_MIME_TYPES', [
            'video/webm',
            'video/mp4',
            'audio/webm',
            'audio/mpeg',
            'audio/wav',
            'image/png',
            'image/jpeg',
            'image/gif'
        ]);
        
        // Cache
        self::define_constant('RMCU_CACHE_GROUP', 'rmcu');
        self::define_constant('RMCU_CACHE_EXPIRE', 3600); // 1 hour
        
        // Queue Processing
        self::define_constant('RMCU_QUEUE_BATCH_SIZE', 5);
        self::define_constant('RMCU_QUEUE_MAX_ATTEMPTS', 3);
        self::define_constant('RMCU_QUEUE_TIMEOUT', 30);
        
        // Media Processing
        self::define_constant('RMCU_VIDEO_QUALITY', [
            'low' => ['width' => 640, 'height' => 480, 'bitrate' => '500k'],
            'medium' => ['width' => 1280, 'height' => 720, 'bitrate' => '1500k'],
            'high' => ['width' => 1920, 'height' => 1080, 'bitrate' => '3000k'],
            'ultra' => ['width' => 3840, 'height' => 2160, 'bitrate' => '6000k']
        ]);
        
        self::define_constant('RMCU_AUDIO_QUALITY', [
            'low' => ['sample_rate' => 8000, 'bitrate' => '64k'],
            'medium' => ['sample_rate' => 22050, 'bitrate' => '128k'],
            'high' => ['sample_rate' => 44100, 'bitrate' => '192k']
        ]);
        
        // Thumbnail Settings
        self::define_constant('RMCU_THUMBNAIL_WIDTH', 1200);
        self::define_constant('RMCU_THUMBNAIL_HEIGHT', 630);
        self::define_constant('RMCU_THUMBNAIL_QUALITY', 85);
        
        // Logging
        self::define_constant('RMCU_LOG_LEVELS', [
            'debug' => 0,
            'info' => 1,
            'warning' => 2,
            'error' => 3,
            'critical' => 4
        ]);
        
        // Security
        self::define_constant('RMCU_NONCE_ACTION', 'rmcu_action');
        self::define_constant('RMCU_NONCE_NAME', 'rmcu_nonce');
        self::define_constant('RMCU_TOKEN_EXPIRY', 86400); // 24 hours
        
        // n8n Integration
        self::define_constant('RMCU_N8N_TIMEOUT', 30);
        self::define_constant('RMCU_N8N_RETRY_COUNT', 3);
        self::define_constant('RMCU_N8N_RETRY_DELAY', 5); // seconds
        
        // User Capabilities
        self::define_constant('RMCU_CAP_MANAGE', 'manage_rmcu');
        self::define_constant('RMCU_CAP_CAPTURE', 'create_rmcu_captures');
        self::define_constant('RMCU_CAP_EDIT', 'edit_rmcu_captures');
        self::define_constant('RMCU_CAP_DELETE', 'delete_rmcu_captures');
        self::define_constant('RMCU_CAP_VIEW', 'view_rmcu_captures');
        
        // Shortcodes
        self::define_constant('RMCU_SHORTCODE_CAPTURE', 'rmcu_capture');
        self::define_constant('RMCU_SHORTCODE_GALLERY', 'rmcu_gallery');
        self::define_constant('RMCU_SHORTCODE_PLAYER', 'rmcu_player');
        
        // Action Hooks
        self::define_constant('RMCU_HOOK_INIT', 'rmcu_init');
        self::define_constant('RMCU_HOOK_CAPTURE_CREATED', 'rmcu_capture_created');
        self::define_constant('RMCU_HOOK_CAPTURE_PROCESSED', 'rmcu_capture_processed');
        self::define_constant('RMCU_HOOK_N8N_SENT', 'rmcu_n8n_sent');
        
        // Filter Hooks
        self::define_constant('RMCU_FILTER_SETTINGS', 'rmcu_filter_settings');
        self::define_constant('RMCU_FILTER_CAPTURE_DATA', 'rmcu_filter_capture_data');
        self::define_constant('RMCU_FILTER_ALLOWED_TYPES', 'rmcu_filter_allowed_types');
        
        // Text Domain
        self::define_constant('RMCU_TEXT_DOMAIN', 'rmcu');
        
        // Environment
        self::define_constant('RMCU_ENV', self::get_environment());
        self::define_constant('RMCU_DEBUG', self::is_debug_mode());
    }
    
    /**
     * Define a constant if not already defined
     * 
     * @param string $name Constant name
     * @param mixed $value Constant value
     */
    private static function define_constant($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }
    
    /**
     * Get environment type
     * 
     * @return string
     */
    private static function get_environment() {
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return WP_ENVIRONMENT_TYPE;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }
        
        return 'production';
    }
    
    /**
     * Check if debug mode is enabled
     * 
     * @return bool
     */
    private static function is_debug_mode() {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * Get all defined constants
     * 
     * @return array
     */
    public static function get_all() {
        $constants = [];
        $all = get_defined_constants(true);
        
        if (isset($all['user'])) {
            foreach ($all['user'] as $name => $value) {
                if (strpos($name, 'RMCU_') === 0) {
                    $constants[$name] = $value;
                }
            }
        }
        
        return $constants;
    }
    
    /**
     * Check if a constant is defined
     * 
     * @param string $name Constant name
     * @return bool
     */
    public static function has($name) {
        return defined($name);
    }
    
    /**
     * Get constant value
     * 
     * @param string $name Constant name
     * @param mixed $default Default value
     * @return mixed
     */
    public static function get($name, $default = null) {
        return defined($name) ? constant($name) : $default;
    }
}

// Define constants on plugin load
RMCU_Constants::define();