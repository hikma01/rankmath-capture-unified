<?php
/**
 * Plugin Requirements Checker
 * 
 * @package RMCU
 * @subpackage Config
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RMCU Requirements Class
 */
class RMCU_Requirements {
    
    /**
     * Minimum requirements
     */
    const MIN_PHP_VERSION = '7.4';
    const MIN_WP_VERSION = '5.8';
    const MIN_MYSQL_VERSION = '5.6';
    const MIN_MEMORY_LIMIT = '128M';
    
    /**
     * Required PHP extensions
     */
    const REQUIRED_EXTENSIONS = [
        'curl',
        'gd',
        'json',
        'mbstring',
        'dom',
        'fileinfo'
    ];
    
    /**
     * Optional but recommended extensions
     */
    const OPTIONAL_EXTENSIONS = [
        'imagick',
        'zip',
        'openssl',
        'exif',
        'sodium'
    ];
    
    /**
     * Check results cache
     */
    private static $check_results = null;
    
    /**
     * Check all requirements
     * 
     * @return array
     */
    public static function check_all() {
        if (self::$check_results !== null) {
            return self::$check_results;
        }
        
        self::$check_results = [
            'passed' => true,
            'errors' => [],
            'warnings' => [],
            'info' => [],
            'details' => [
                'php' => self::check_php(),
                'wordpress' => self::check_wordpress(),
                'mysql' => self::check_mysql(),
                'memory' => self::check_memory(),
                'extensions' => self::check_extensions(),
                'permissions' => self::check_permissions(),
                'plugins' => self::check_plugins(),
                'server' => self::check_server()
            ]
        ];
        
        // Compile errors and warnings
        foreach (self::$check_results['details'] as $category => $check) {
            if (!$check['passed']) {
                self::$check_results['passed'] = false;
                if ($check['critical']) {
                    self::$check_results['errors'][] = $check['message'];
                } else {
                    self::$check_results['warnings'][] = $check['message'];
                }
            }
            
            if (!empty($check['warnings'])) {
                self::$check_results['warnings'] = array_merge(
                    self::$check_results['warnings'],
                    $check['warnings']
                );
            }
            
            if (!empty($check['info'])) {
                self::$check_results['info'] = array_merge(
                    self::$check_results['info'],
                    $check['info']
                );
            }
        }
        
        return self::$check_results;
    }
    
    /**
     * Check PHP version
     * 
     * @return array
     */
    public static function check_php() {
        $current = PHP_VERSION;
        $required = self::MIN_PHP_VERSION;
        $passed = version_compare($current, $required, '>=');
        
        return [
            'passed' => $passed,
            'critical' => true,
            'current' => $current,
            'required' => $required,
            'message' => $passed 
                ? sprintf(__('PHP version %s is compatible', 'rmcu'), $current)
                : sprintf(__('PHP version %s or higher is required. You have %s', 'rmcu'), $required, $current),
            'warnings' => [],
            'info' => []
        ];
    }
    
    /**
     * Check WordPress version
     * 
     * @return array
     */
    public static function check_wordpress() {
        global $wp_version;
        $current = $wp_version;
        $required = self::MIN_WP_VERSION;
        $passed = version_compare($current, $required, '>=');
        
        $result = [
            'passed' => $passed,
            'critical' => true,
            'current' => $current,
            'required' => $required,
            'message' => $passed
                ? sprintf(__('WordPress version %s is compatible', 'rmcu'), $current)
                : sprintf(__('WordPress version %s or higher is required. You have %s', 'rmcu'), $required, $current),
            'warnings' => [],
            'info' => []
        ];
        
        // Check for multisite
        if (is_multisite()) {
            $result['info'][] = __('WordPress Multisite detected', 'rmcu');
        }
        
        return $result;
    }
    
    /**
     * Check MySQL version
     * 
     * @return array
     */
    public static function check_mysql() {
        global $wpdb;
        $current = $wpdb->db_version();
        $required = self::MIN_MYSQL_VERSION;
        $passed = version_compare($current, $required, '>=');
        
        return [
            'passed' => $passed,
            'critical' => true,
            'current' => $current,
            'required' => $required,
            'message' => $passed
                ? sprintf(__('MySQL version %s is compatible', 'rmcu'), $current)
                : sprintf(__('MySQL version %s or higher is required. You have %s', 'rmcu'), $required, $current),
            'warnings' => [],
            'info' => []
        ];
    }
    
    /**
     * Check memory limit
     * 
     * @return array
     */
    public static function check_memory() {
        $current = ini_get('memory_limit');
        $required = self::MIN_MEMORY_LIMIT;
        $current_bytes = wp_convert_hr_to_bytes($current);
        $required_bytes = wp_convert_hr_to_bytes($required);
        $passed = $current_bytes >= $required_bytes;
        
        $result = [
            'passed' => $passed,
            'critical' => false,
            'current' => $current,
            'required' => $required,
            'message' => $passed
                ? sprintf(__('Memory limit %s is sufficient', 'rmcu'), $current)
                : sprintf(__('Memory limit should be at least %s. You have %s', 'rmcu'), $required, $current),
            'warnings' => [],
            'info' => []
        ];
        
        // Recommend higher memory for video processing
        if ($current_bytes < wp_convert_hr_to_bytes('256M')) {
            $result['warnings'][] = __('Consider increasing memory limit to 256M for better video processing performance', 'rmcu');
        }
        
        return $result;
    }
    
    /**
     * Check PHP extensions
     * 
     * @return array
     */
    public static function check_extensions() {
        $missing_required = [];
        $missing_optional = [];
        $loaded = [];
        
        // Check required extensions
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            if (!extension_loaded($ext)) {
                $missing_required[] = $ext;
            } else {
                $loaded[] = $ext;
            }
        }
        
        // Check optional extensions
        foreach (self::OPTIONAL_EXTENSIONS as $ext) {
            if (!extension_loaded($ext)) {
                $missing_optional[] = $ext;
            } else {
                $loaded[] = $ext;
            }
        }
        
        $passed = empty($missing_required);
        
        $result = [
            'passed' => $passed,
            'critical' => true,
            'loaded' => $loaded,
            'missing_required' => $missing_required,
            'missing_optional' => $missing_optional,
            'message' => $passed
                ? __('All required PHP extensions are installed', 'rmcu')
                : sprintf(__('Missing required PHP extensions: %s', 'rmcu'), implode(', ', $missing_required)),
            'warnings' => [],
            'info' => []
        ];
        
        if (!empty($missing_optional)) {
            $result['warnings'][] = sprintf(
                __('Optional PHP extensions not installed: %s. Some features may be limited.', 'rmcu'),
                implode(', ', $missing_optional)
            );
        }
        
        // Check GD capabilities
        if (extension_loaded('gd')) {
            $gd_info = gd_info();
            $result['info'][] = sprintf(__('GD Version: %s', 'rmcu'), $gd_info['GD Version']);
        }
        
        return $result;
    }
    
    /**
     * Check file permissions
     * 
     * @return array
     */
    public static function check_permissions() {
        $upload_dir = wp_upload_dir();
        $paths_to_check = [
            'uploads' => $upload_dir['basedir'],
            'rmcu_uploads' => $upload_dir['basedir'] . '/rmcu-captures',
            'logs' => WP_CONTENT_DIR . '/rmcu-logs',
            'cache' => WP_CONTENT_DIR . '/cache/rmcu'
        ];
        
        $not_writable = [];
        $created = [];
        
        foreach ($paths_to_check as $name => $path) {
            $dir = dirname($path);
            
            // Try to create directory if it doesn't exist
            if (!file_exists($path)) {
                if (wp_mkdir_p($path)) {
                    $created[] = $name;
                }
            }
            
            // Check if writable
            if (!is_writable($dir)) {
                $not_writable[] = $path;
            }
        }
        
        $passed = empty($not_writable);
        
        $result = [
            'passed' => $passed,
            'critical' => false,
            'writable' => array_keys(array_diff_key($paths_to_check, array_flip($not_writable))),
            'not_writable' => $not_writable,
            'created' => $created,
            'message' => $passed
                ? __('All required directories are writable', 'rmcu')
                : sprintf(__('The following directories are not writable: %s', 'rmcu'), implode(', ', $not_writable)),
            'warnings' => [],
            'info' => []
        ];
        
        if (!empty($created)) {
            $result['info'][] = sprintf(__('Created directories: %s', 'rmcu'), implode(', ', $created));
        }
        
        return $result;
    }
    
    /**
     * Check plugin dependencies
     * 
     * @return array
     */
    public static function check_plugins() {
        $result = [
            'passed' => true,
            'critical' => false,
            'active' => [],
            'inactive' => [],
            'missing' => [],
            'message' => '',
            'warnings' => [],
            'info' => []
        ];
        
        // Check for RankMath
        if (class_exists('RankMath')) {
            $result['active'][] = 'RankMath SEO';
            $result['info'][] = __('RankMath SEO is active - full integration enabled', 'rmcu');
        } else {
            $result['warnings'][] = __('RankMath SEO is not active. Some features will be limited.', 'rmcu');
        }
        
        // Check for caching plugins
        $caching_plugins = [
            'wp-rocket/wp-rocket.php' => 'WP Rocket',
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php' => 'WP Super Cache',
            'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache'
        ];
        
        foreach ($caching_plugins as $plugin => $name) {
            if (is_plugin_active($plugin)) {
                $result['active'][] = $name;
                $result['info'][] = sprintf(__('%s detected - cache may need to be cleared after captures', 'rmcu'), $name);
            }
        }
        
        // Check for security plugins that might interfere
        $security_plugins = [
            'wordfence/wordfence.php' => 'Wordfence',
            'sucuri-scanner/sucuri.php' => 'Sucuri',
            'all-in-one-wp-security-and-firewall/wp-security.php' => 'All In One WP Security'
        ];
        
        foreach ($security_plugins as $plugin => $name) {
            if (is_plugin_active($plugin)) {
                $result['active'][] = $name;
                $result['warnings'][] = sprintf(
                    __('%s is active. You may need to whitelist RMCU endpoints.', 'rmcu'),
                    $name
                );
            }
        }
        
        $result['message'] = count($result['active']) > 0
            ? sprintf(__('Detected plugins: %s', 'rmcu'), implode(', ', $result['active']))
            : __('No conflicting plugins detected', 'rmcu');
        
        return $result;
    }
    
    /**
     * Check server environment
     * 
     * @return array
     */
    public static function check_server() {
        $result = [
            'passed' => true,
            'critical' => false,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_sapi' => PHP_SAPI,
            'message' => '',
            'warnings' => [],
            'info' => []
        ];
        
        // Check if running on localhost
        if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1'])) {
            $result['info'][] = __('Running on localhost/development environment', 'rmcu');
        }
        
        // Check max upload size
        $max_upload = min(
            wp_convert_hr_to_bytes(ini_get('upload_max_filesize')),
            wp_convert_hr_to_bytes(ini_get('post_max_size'))
        );
        
        $result['info'][] = sprintf(__('Maximum upload size: %s', 'rmcu'), size_format($max_upload));
        
        if ($max_upload < wp_convert_hr_to_bytes('100M')) {
            $result['warnings'][] = __('Maximum upload size is less than 100MB. Large video captures may fail.', 'rmcu');
        }
        
        // Check execution time
        $max_execution = ini_get('max_execution_time');
        if ($max_execution > 0 && $max_execution < 60) {
            $result['warnings'][] = sprintf(
                __('Maximum execution time is %d seconds. Consider increasing for video processing.', 'rmcu'),
                $max_execution
            );
        }
        
        // Check if WP-Cron is disabled
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $result['info'][] = __('WP-Cron is disabled. Using system cron is recommended.', 'rmcu');
        }
        
        // Check SSL
        if (is_ssl()) {
            $result['info'][] = __('SSL is enabled', 'rmcu');
        } else {
            $result['warnings'][] = __('SSL is not enabled. Media capture requires HTTPS in modern browsers.', 'rmcu');
        }
        
        $result['message'] = sprintf(__('Server: %s, PHP SAPI: %s', 'rmcu'), $result['server_software'], $result['php_sapi']);
        
        return $result;
    }
    
    /**
     * Display requirements notice
     */
    public static function display_notice() {
        $check = self::check_all();
        
        if (!$check['passed']) {
            $class = !empty($check['errors']) ? 'notice-error' : 'notice-warning';
            ?>
            <div class="notice <?php echo $class; ?> is-dismissible">
                <h3><?php _e('RMCU Plugin Requirements Check', 'rmcu'); ?></h3>
                
                <?php if (!empty($check['errors'])): ?>
                    <h4><?php _e('Critical Issues:', 'rmcu'); ?></h4>
                    <ul>
                        <?php foreach ($check['errors'] as $error): ?>
                            <li style="color: red;">✗ <?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (!empty($check['warnings'])): ?>
                    <h4><?php _e('Warnings:', 'rmcu'); ?></h4>
                    <ul>
                        <?php foreach ($check['warnings'] as $warning): ?>
                            <li style="color: orange;">⚠ <?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if (!empty($check['info'])): ?>
                    <h4><?php _e('Information:', 'rmcu'); ?></h4>
                    <ul>
                        <?php foreach ($check['info'] as $info): ?>
                            <li style="color: blue;">ℹ <?php echo esc_html($info); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <p>
                    <a href="<?php echo admin_url('admin.php?page=rmcu-requirements'); ?>" class="button button-primary">
                        <?php _e('View Detailed Requirements Report', 'rmcu'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Can activate plugin
     * 
     * @return bool
     */
    public static function can_activate() {
        $check = self::check_all();
        return empty($check['errors']);
    }
    
    /**
     * Get system info for support
     * 
     * @return string
     */
    public static function get_system_info() {
        $check = self::check_all();
        
        $info = "=== RMCU System Information ===\n\n";
        $info .= "Plugin Version: " . RMCU_VERSION . "\n";
        $info .= "WordPress Version: " . $check['details']['wordpress']['current'] . "\n";
        $info .= "PHP Version: " . $check['details']['php']['current'] . "\n";
        $info .= "MySQL Version: " . $check['details']['mysql']['current'] . "\n";
        $info .= "Memory Limit: " . $check['details']['memory']['current'] . "\n";
        $info .= "Server: " . $check['details']['server']['server_software'] . "\n";
        $info .= "\n";
        $info .= "PHP Extensions:\n";
        $info .= "- Loaded: " . implode(', ', $check['details']['extensions']['loaded']) . "\n";
        
        if (!empty($check['details']['extensions']['missing_required'])) {
            $info .= "- Missing Required: " . implode(', ', $check['details']['extensions']['missing_required']) . "\n";
        }
        
        if (!empty($check['details']['extensions']['missing_optional'])) {
            $info .= "- Missing Optional: " . implode(', ', $check['details']['extensions']['missing_optional']) . "\n";
        }
        
        $info .= "\n";
        $info .= "Active Plugins:\n";
        foreach ($check['details']['plugins']['active'] as $plugin) {
            $info .= "- " . $plugin . "\n";
        }
        
        return $info;
    }
}

// Check requirements on admin init
add_action('admin_init', function() {
    if (!RMCU_Requirements::can_activate()) {
        RMCU_Requirements::display_notice();
    }
});