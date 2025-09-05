<?php
/**
 * Default Settings and Values
 * 
 * @package RMCU
 * @subpackage Config
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RMCU Default Settings
 */
class RMCU_Defaults {
    
    /**
     * Get all default settings
     * 
     * @return array
     */
    public static function get_all() {
        return [
            'general' => self::get_general_defaults(),
            'capture' => self::get_capture_defaults(),
            'seo' => self::get_seo_defaults(),
            'media' => self::get_media_defaults(),
            'advanced' => self::get_advanced_defaults()
        ];
    }
    
    /**
     * General settings defaults
     * 
     * @return array
     */
    public static function get_general_defaults() {
        return [
            'enable_plugin' => true,
            'show_in_admin_bar' => true,
            'show_in_dashboard' => true,
            'enable_gutenberg_blocks' => true,
            'enable_classic_editor' => true,
            'enable_frontend_capture' => false,
            'default_capture_position' => 'after_content',
            'capture_button_text' => __('Start Capture', 'rmcu'),
            'success_message' => __('Capture saved successfully!', 'rmcu'),
            'error_message' => __('An error occurred. Please try again.', 'rmcu'),
            'loading_text' => __('Processing...', 'rmcu'),
            'privacy_notice' => __('By using this feature, you agree to our privacy policy.', 'rmcu'),
            'show_powered_by' => false
        ];
    }
    
    /**
     * Capture settings defaults
     * 
     * @return array
     */
    public static function get_capture_defaults() {
        return [
            'enable_video' => true,
            'enable_audio' => true,
            'enable_screen' => true,
            'enable_screenshot' => true,
            'max_time' => 120, // seconds
            'video_quality' => 'medium',
            'audio_quality' => 'medium',
            'format' => 'webm',
            'auto_save' => false,
            'show_countdown' => true,
            'countdown_duration' => 3,
            'max_file_size' => 100, // MB
            'enable_preview' => true,
            'allow_retake' => true,
            'require_permission' => true,
            'auto_stop_on_tab_change' => true,
            'show_timer' => true,
            'enable_effects' => false,
            'enable_filters' => false,
            'mirror_video' => true,
            'mute_by_default' => false,
            'default_camera' => 'user', // user or environment
            'resolution' => [
                'width' => 1280,
                'height' => 720
            ],
            'frame_rate' => 30,
            'audio_channels' => 2,
            'audio_sample_rate' => 44100,
            'video_bitrate' => 2500000, // 2.5 Mbps
            'audio_bitrate' => 128000 // 128 kbps
        ];
    }
    
    /**
     * SEO settings defaults
     * 
     * @return array
     */
    public static function get_seo_defaults() {
        return [
            'enable_schema' => true,
            'auto_thumbnail' => true,
            'thumbnail_time' => 2, // seconds into video
            'add_sitemap' => false,
            'rankmath_integration' => true,
            'use_as_featured' => false,
            'content_ai' => false,
            'title_template' => '%title% - Video',
            'description_template' => 'Watch this video capture from %title% on %site_name%',
            'og_tags' => true,
            'twitter_card_type' => 'summary_large_image',
            'lazy_load' => true,
            'video_schema_type' => 'VideoObject',
            'add_transcript' => false,
            'generate_captions' => false,
            'auto_generate_tags' => false,
            'social_share_buttons' => true,
            'embed_allowed' => true,
            'embed_domains' => '*', // * for all, or comma-separated list
            'canonical_url' => true,
            'noindex_captures' => false,
            'structured_data' => [
                'enable' => true,
                'type' => 'VideoObject',
                'publisher' => get_bloginfo('name'),
                'author' => 'User'
            ]
        ];
    }
    
    /**
     * Media settings defaults
     * 
     * @return array
     */
    public static function get_media_defaults() {
        return [
            'storage_location' => 'uploads',
            'custom_path' => '/rmcu-captures/',
            'organize_by_date' => true,
            'file_naming' => 'capture-{type}-{date}-{time}',
            'compression' => 70,
            'generate_sizes' => ['thumbnail', 'medium', 'large'],
            'watermark' => false,
            'watermark_text' => get_bloginfo('name'),
            'watermark_position' => 'bottom-right',
            'watermark_opacity' => 50,
            'watermark_size' => 12,
            'cdn_enable' => false,
            'cdn_url' => '',
            'retention_days' => 0, // 0 = never delete
            'backup_service' => '',
            'optimize_images' => true,
            'convert_to_webp' => false,
            'max_dimensions' => [
                'width' => 1920,
                'height' => 1080
            ],
            'thumbnail_dimensions' => [
                'width' => RMCU_THUMBNAIL_WIDTH,
                'height' => RMCU_THUMBNAIL_HEIGHT
            ],
            'allowed_mime_types' => RMCU_ALLOWED_MIME_TYPES,
            'chunk_upload' => true,
            'chunk_size' => RMCU_CHUNK_SIZE,
            'parallel_uploads' => 3,
            'resume_uploads' => true,
            'auto_rotate' => true,
            'strip_metadata' => false,
            'preserve_original' => false
        ];
    }
    
    /**
     * Advanced settings defaults
     * 
     * @return array
     */
    public static function get_advanced_defaults() {
        return [
            'debug_mode' => false,
            'log_level' => 'error',
            'api_timeout' => 30,
            'n8n_webhook_url' => '',
            'n8n_api_key' => '',
            'n8n_auto_send' => false,
            'n8n_retry_failed' => true,
            'cache_enable' => true,
            'cache_duration' => 3600,
            'async_processing' => false,
            'batch_size' => 5,
            'allowed_roles' => ['administrator', 'editor'],
            'custom_css' => '',
            'custom_js' => '',
            'uninstall_cleanup' => false,
            'increase_memory' => false,
            'increase_time_limit' => false,
            'disable_wp_cron' => false,
            'use_real_cron' => false,
            'api_rate_limit' => 60, // requests per minute
            'enable_rest_api' => true,
            'rest_api_auth' => 'cookie', // cookie, oauth, jwt, basic
            'webhook_secret' => wp_generate_password(32, true, true),
            'enable_metrics' => false,
            'metrics_endpoint' => '',
            'error_reporting' => 'production', // production, development, none
            'enable_beta_features' => false,
            'experimental_features' => [],
            'performance_mode' => 'balanced', // balanced, performance, economy
            'database_optimization' => true,
            'cleanup_interval' => 'daily',
            'max_log_size' => 10, // MB
            'log_rotation' => true,
            'log_retention_days' => 30,
            'enable_auto_updates' => false,
            'update_channel' => 'stable', // stable, beta, dev
            'license_key' => '',
            'telemetry' => false
        ];
    }
    
    /**
     * Get default value for a specific setting
     * 
     * @param string $key Dot notation key (e.g., 'capture.enable_video')
     * @return mixed
     */
    public static function get($key) {
        $defaults = self::get_all();
        $keys = explode('.', $key);
        $value = $defaults;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    /**
     * Reset settings to defaults
     * 
     * @param string $section Section to reset (null for all)
     * @return bool
     */
    public static function reset($section = null) {
        $defaults = self::get_all();
        
        if ($section) {
            if (isset($defaults[$section])) {
                return update_option("rmcu_{$section}_settings", $defaults[$section]);
            }
            return false;
        }
        
        // Reset all sections
        foreach ($defaults as $section => $settings) {
            update_option("rmcu_{$section}_settings", $settings);
        }
        
        return true;
    }
    
    /**
     * Get default capabilities for roles
     * 
     * @return array
     */
    public static function get_capabilities() {
        return [
            'administrator' => [
                'manage_rmcu' => true,
                'create_rmcu_captures' => true,
                'edit_rmcu_captures' => true,
                'delete_rmcu_captures' => true,
                'view_rmcu_captures' => true,
                'manage_rmcu_settings' => true,
                'view_rmcu_logs' => true,
                'export_rmcu_data' => true
            ],
            'editor' => [
                'create_rmcu_captures' => true,
                'edit_rmcu_captures' => true,
                'delete_rmcu_captures' => true,
                'view_rmcu_captures' => true
            ],
            'author' => [
                'create_rmcu_captures' => true,
                'edit_rmcu_captures' => true,
                'view_rmcu_captures' => true
            ],
            'contributor' => [
                'create_rmcu_captures' => true,
                'view_rmcu_captures' => true
            ],
            'subscriber' => [
                'view_rmcu_captures' => true
            ]
        ];
    }
    
    /**
     * Get default database schema
     * 
     * @return array
     */
    public static function get_database_schema() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . RMCU_TABLE_PREFIX;
        
        return [
            'captures' => "CREATE TABLE IF NOT EXISTS {$prefix}captures (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                title varchar(255) DEFAULT NULL,
                capture_type varchar(50) NOT NULL,
                status varchar(50) DEFAULT 'active',
                file_url text,
                thumbnail_url text,
                metadata longtext,
                post_id bigint(20) UNSIGNED DEFAULT NULL,
                user_id bigint(20) UNSIGNED NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_post_id (post_id),
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_type (capture_type),
                INDEX idx_created (created_at)
            ) $charset_collate;",
            
            'queue' => "CREATE TABLE IF NOT EXISTS {$prefix}queue (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                capture_id bigint(20) UNSIGNED DEFAULT NULL,
                url text NOT NULL,
                capture_type varchar(50) NOT NULL,
                status varchar(50) DEFAULT 'pending',
                priority int(11) DEFAULT 10,
                attempts int(11) DEFAULT 0,
                error_message text,
                metadata longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                processed_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                INDEX idx_capture_id (capture_id),
                INDEX idx_status (status),
                INDEX idx_priority (priority),
                INDEX idx_created (created_at)
            ) $charset_collate;",
            
            'logs' => "CREATE TABLE IF NOT EXISTS {$prefix}logs (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                level varchar(20) NOT NULL,
                message text NOT NULL,
                context longtext,
                user_id bigint(20) UNSIGNED DEFAULT NULL,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_level (level),
                INDEX idx_user_id (user_id),
                INDEX idx_created (created_at)
            ) $charset_collate;"
        ];
    }
}

// Make defaults available globally
global $rmcu_defaults;
$rmcu_defaults = RMCU_Defaults::get_all();