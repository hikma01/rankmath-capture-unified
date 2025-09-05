<?php
/**
 * RMCU Health Check
 * Vérification de l'état du système et compatibilité
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour vérifier la santé du système
 */
class RMCU_Health_Check {
    
    /**
     * Résultats des tests
     */
    private $results = [];
    
    /**
     * Statut global
     */
    private $status = 'unknown';
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Tests à effectuer
     */
    private $tests = [
        'php_version' => 'check_php_version',
        'wordpress_version' => 'check_wordpress_version',
        'rankmath_installed' => 'check_rankmath_installed',
        'rankmath_version' => 'check_rankmath_version',
        'database_tables' => 'check_database_tables',
        'file_permissions' => 'check_file_permissions',
        'n8n_connection' => 'check_n8n_connection',
        'memory_limit' => 'check_memory_limit',
        'execution_time' => 'check_execution_time',
        'curl_available' => 'check_curl_available',
        'json_support' => 'check_json_support',
        'cron_working' => 'check_cron_working',
        'ajax_working' => 'check_ajax_working',
        'rest_api_enabled' => 'check_rest_api_enabled',
        'cache_writable' => 'check_cache_writable',
        'logs_writable' => 'check_logs_writable',
        'javascript_files' => 'check_javascript_files',
        'css_files' => 'check_css_files',
        'conflicts' => 'check_plugin_conflicts',
        'security' => 'check_security_settings'
    ];
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->logger = new RMCU_Logger('HealthCheck');
        
        // Ajouter les hooks
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('wp_ajax_rmcu_run_health_check', [$this, 'ajax_run_check']);
        add_action('rmcu_scheduled_health_check', [$this, 'scheduled_check']);
        
        // Ajouter au Site Health de WordPress
        add_filter('site_status_tests', [$this, 'add_site_health_tests']);
    }
    
    /**
     * Exécuter tous les tests
     */
    public function run_check() {
        $this->logger->info('Starting health check');
        
        $this->results = [];
        $critical_errors = 0;
        $warnings = 0;
        $passed = 0;
        
        foreach ($this->tests as $test_name => $test_method) {
            if (method_exists($this, $test_method)) {
                $result = $this->$test_method();
                $this->results[$test_name] = $result;
                
                switch ($result['status']) {
                    case 'critical':
                        $critical_errors++;
                        break;
                    case 'warning':
                        $warnings++;
                        break;
                    case 'good':
                        $passed++;
                        break;
                }
            }
        }
        
        // Déterminer le statut global
        if ($critical_errors > 0) {
            $this->status = 'critical';
        } elseif ($warnings > 0) {
            $this->status = 'warning';
        } else {
            $this->status = 'good';
        }
        
        // Sauvegarder les résultats
        $this->save_results();
        
        $this->logger->info('Health check completed', [
            'status' => $this->status,
            'critical' => $critical_errors,
            'warnings' => $warnings,
            'passed' => $passed
        ]);
        
        return [
            'status' => $this->status,
            'results' => $this->results,
            'summary' => [
                'critical' => $critical_errors,
                'warnings' => $warnings,
                'passed' => $passed,
                'total' => count($this->tests)
            ]
        ];
    }
    
    /**
     * Vérifier la version PHP
     */
    private function check_php_version() {
        $required = '7.4.0';
        $current = phpversion();
        
        if (version_compare($current, $required, '>=')) {
            return [
                'status' => 'good',
                'label' => 'PHP Version',
                'message' => sprintf('PHP %s is installed (minimum required: %s)', $current, $required),
                'value' => $current
            ];
        } else {
            return [
                'status' => 'critical',
                'label' => 'PHP Version',
                'message' => sprintf('PHP %s is installed, but version %s or higher is required', $current, $required),
                'value' => $current,
                'action' => 'Please upgrade your PHP version'
            ];
        }
    }
    
    /**
     * Vérifier la version WordPress
     */
    private function check_wordpress_version() {
        global $wp_version;
        $required = '5.8';
        
        if (version_compare($wp_version, $required, '>=')) {
            return [
                'status' => 'good',
                'label' => 'WordPress Version',
                'message' => sprintf('WordPress %s is installed', $wp_version),
                'value' => $wp_version
            ];
        } else {
            return [
                'status' => 'warning',
                'label' => 'WordPress Version',
                'message' => sprintf('WordPress %s is installed, but version %s or higher is recommended', $wp_version, $required),
                'value' => $wp_version,
                'action' => 'Please update WordPress'
            ];
        }
    }
    
    /**
     * Vérifier si RankMath est installé
     */
    private function check_rankmath_installed() {
        if (class_exists('RankMath')) {
            return [
                'status' => 'good',
                'label' => 'RankMath Installation',
                'message' => 'RankMath SEO is installed and active',
                'value' => true
            ];
        } else {
            // Vérifier si c'est juste désactivé
            $plugins = get_plugins();
            $rankmath_found = false;
            
            foreach ($plugins as $plugin_file => $plugin_data) {
                if (strpos($plugin_file, 'seo-by-rank-math') !== false) {
                    $rankmath_found = true;
                    break;
                }
            }
            
            if ($rankmath_found) {
                return [
                    'status' => 'critical',
                    'label' => 'RankMath Installation',
                    'message' => 'RankMath SEO is installed but not activated',
                    'value' => false,
                    'action' => 'Please activate RankMath SEO plugin'
                ];
            } else {
                return [
                    'status' => 'critical',
                    'label' => 'RankMath Installation',
                    'message' => 'RankMath SEO is not installed',
                    'value' => false,
                    'action' => 'Please install and activate RankMath SEO plugin'
                ];
            }
        }
    }
    
    /**
     * Vérifier la version de RankMath
     */
    private function check_rankmath_version() {
        if (!class_exists('RankMath')) {
            return [
                'status' => 'skip',
                'label' => 'RankMath Version',
                'message' => 'Cannot check version - RankMath not active',
                'value' => null
            ];
        }
        
        // Essayer d'obtenir la version
        $version = defined('RANK_MATH_VERSION') ? RANK_MATH_VERSION : 'Unknown';
        
        return [
            'status' => 'good',
            'label' => 'RankMath Version',
            'message' => sprintf('RankMath version %s is installed', $version),
            'value' => $version
        ];
    }
    
    /**
     * Vérifier les tables de base de données
     */
    private function check_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rmcu_optimization_queue';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($table_exists) {
            // Vérifier la structure
            $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
            $required_columns = ['id', 'post_id', 'status', 'target_score', 'current_score', 'iterations'];
            
            $missing = array_diff($required_columns, $columns);
            
            if (empty($missing)) {
                return [
                    'status' => 'good',
                    'label' => 'Database Tables',
                    'message' => 'All required database tables exist and have correct structure',
                    'value' => true
                ];
            } else {
                return [
                    'status' => 'warning',
                    'label' => 'Database Tables',
                    'message' => 'Database table exists but some columns are missing',
                    'value' => false,
                    'details' => 'Missing columns: ' . implode(', ', $missing),
                    'action' => 'Deactivate and reactivate the plugin to recreate tables'
                ];
            }
        } else {
            return [
                'status' => 'critical',
                'label' => 'Database Tables',
                'message' => 'Required database tables are missing',
                'value' => false,
                'action' => 'Deactivate and reactivate the plugin to create tables'
            ];
        }
    }
    
    /**
     * Vérifier les permissions de fichiers
     */
    private function check_file_permissions() {
        $issues = [];
        
        // Vérifier le répertoire du plugin
        if (!is_readable(RMCU_PLUGIN_DIR)) {
            $issues[] = 'Plugin directory is not readable';
        }
        
        // Vérifier les fichiers JavaScript
        $js_dir = RMCU_PLUGIN_DIR . 'assets/js/';
        if (!is_readable($js_dir)) {
            $issues[] = 'JavaScript directory is not readable';
        }
        
        // Vérifier le répertoire de logs (si utilisé)
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/rmcu-logs/';
        
        if (file_exists($log_dir) && !is_writable($log_dir)) {
            $issues[] = 'Log directory is not writable';
        }
        
        if (empty($issues)) {
            return [
                'status' => 'good',
                'label' => 'File Permissions',
                'message' => 'All file permissions are correct',
                'value' => true
            ];
        } else {
            return [
                'status' => 'warning',
                'label' => 'File Permissions',
                'message' => 'Some file permission issues detected',
                'value' => false,
                'details' => implode(', ', $issues),
                'action' => 'Check file permissions on your server'
            ];
        }
    }
    
    /**
     * Vérifier la connexion n8n
     */
    private function check_n8n_connection() {
        $settings = get_option('rmcu_settings', []);
        
        if (empty($settings['n8n_url'])) {
            return [
                'status' => 'warning',
                'label' => 'n8n Connection',
                'message' => 'n8n URL is not configured',
                'value' => false,
                'action' => 'Configure n8n URL in plugin settings'
            ];
        }
        
        // Tester la connexion
        $test_url = trailingslashit($settings['n8n_url']) . 'health';
        
        $response = wp_remote_get($test_url, [
            'timeout' => 5,
            'headers' => !empty($settings['api_key']) ? [
                'Authorization' => 'Bearer ' . $settings['api_key']
            ] : []
        ]);
        
        if (is_wp_error($response)) {
            return [
                'status' => 'critical',
                'label' => 'n8n Connection',
                'message' => 'Cannot connect to n8n',
                'value' => false,
                'details' => $response->get_error_message(),
                'action' => 'Check n8n URL and network connectivity'
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200 || $code === 204) {
            return [
                'status' => 'good',
                'label' => 'n8n Connection',
                'message' => 'Successfully connected to n8n',
                'value' => true
            ];
        } else {
            return [
                'status' => 'warning',
                'label' => 'n8n Connection',
                'message' => 'n8n connection returned unexpected response',
                'value' => false,
                'details' => 'HTTP ' . $code,
                'action' => 'Check n8n configuration'
            ];
        }
    }
    
    /**
     * Vérifier la limite de mémoire
     */
    private function check_memory_limit() {
        $memory_limit = $this->convert_to_bytes(ini_get('memory_limit'));
        $required = 128 * 1024 * 1024; // 128MB
        $recommended = 256 * 1024 * 1024; // 256MB
        
        if ($memory_limit >= $recommended) {
            return [
                'status' => 'good',
                'label' => 'Memory Limit',
                'message' => sprintf('Memory limit is %s', ini_get('memory_limit')),
                'value' => ini_get('memory_limit')
            ];
        } elseif ($memory_limit >= $required) {
            return [
                'status' => 'warning',
                'label' => 'Memory Limit',
                'message' => sprintf('Memory limit is %s (256M recommended)', ini_get('memory_limit')),
                'value' => ini_get('memory_limit'),
                'action' => 'Consider increasing memory_limit to 256M'
            ];
        } else {
            return [
                'status' => 'critical',
                'label' => 'Memory Limit',
                'message' => sprintf('Memory limit is %s (minimum 128M required)', ini_get('memory_limit')),
                'value' => ini_get('memory_limit'),
                'action' => 'Increase memory_limit to at least 128M'
            ];
        }
    }
    
    /**
     * Vérifier le temps d'exécution maximum
     */
    private function check_execution_time() {
        $max_execution_time = ini_get('max_execution_time');
        
        if ($max_execution_time == 0 || $max_execution_time >= 60) {
            return [
                'status' => 'good',
                'label' => 'Execution Time',
                'message' => sprintf('Max execution time is %s seconds', $max_execution_time ?: 'unlimited'),
                'value' => $max_execution_time
            ];
        } elseif ($max_execution_time >= 30) {
            return [
                'status' => 'warning',
                'label' => 'Execution Time',
                'message' => sprintf('Max execution time is %s seconds (60+ recommended)', $max_execution_time),
                'value' => $max_execution_time,
                'action' => 'Consider increasing max_execution_time'
            ];
        } else {
            return [
                'status' => 'critical',
                'label' => 'Execution Time',
                'message' => sprintf('Max execution time is only %s seconds', $max_execution_time),
                'value' => $max_execution_time,
                'action' => 'Increase max_execution_time to at least 30 seconds'
            ];
        }
    }
    
    /**
     * Vérifier si cURL est disponible
     */
    private function check_curl_available() {
        if (function_exists('curl_version')) {
            $version = curl_version();
            return [
                'status' => 'good',
                'label' => 'cURL Support',
                'message' => sprintf('cURL %s is available', $version['version']),
                'value' => $version['version']
            ];
        } else {
            return [
                'status' => 'critical',
                'label' => 'cURL Support',
                'message' => 'cURL is not available',
                'value' => false,
                'action' => 'Enable cURL extension in PHP'
            ];
        }
    }
    
    /**
     * Vérifier le support JSON
     */
    private function check_json_support() {
        if (function_exists('json_encode') && function_exists('json_decode')) {
            return [
                'status' => 'good',
                'label' => 'JSON Support',
                'message' => 'JSON functions are available',
                'value' => true
            ];
        } else {
            return [
                'status' => 'critical',
                'label' => 'JSON Support',
                'message' => 'JSON functions are not available',
                'value' => false,
                'action' => 'Enable JSON extension in PHP'
            ];
        }
    }
    
    /**
     * Vérifier si WP-Cron fonctionne
     */
    private function check_cron_working() {
        $crons = _get_cron_array();
        $rmcu_crons = 0;
        
        foreach ($crons as $timestamp => $cron) {
            foreach ($cron as $hook => $tasks) {
                if (strpos($hook, 'rmcu_') === 0) {
                    $rmcu_crons++;
                }
            }
        }
        
        if ($rmcu_crons > 0) {
            return [
                'status' => 'good',
                'label' => 'WP-Cron',
                'message' => sprintf('%d RMCU cron jobs are scheduled', $rmcu_crons),
                'value' => $rmcu_crons
            ];
        } else {
            return [
                'status' => 'warning',
                'label' => 'WP-Cron',
                'message' => 'No RMCU cron jobs are scheduled',
                'value' => 0,
                'action' => 'Check if WP-Cron is working properly'
            ];
        }
    }
    
    /**
     * Vérifier si AJAX fonctionne
     */
    private function check_ajax_working() {
        // Vérifier si admin-ajax.php est accessible
        $ajax_url = admin_url('admin-ajax.php');
        $response = wp_remote_get($ajax_url, ['timeout' => 5]);
        
        if (!is_wp_error($response)) {
            return [
                'status' => 'good',
                'label' => 'AJAX Endpoint',
                'message' => 'WordPress AJAX is accessible',
                'value' => true
            ];
        } else {
            return [
                'status' => 'warning',
                'label' => 'AJAX Endpoint',
                'message' => 'Cannot access WordPress AJAX endpoint',
                'value' => false,
                'details' => $response->get_error_message(),
                'action' => 'Check server configuration'
            ];
        }
    }
    
    /**
     * Vérifier si l'API REST est activée
     */
    private function check_rest_api_enabled() {
        $rest_url = rest_url('rmcu/v1/');
        $response = wp_remote_get($rest_url, ['timeout' => 5]);
        
        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            
            if ($code === 200 || $code === 401 || $code === 404) {
                return [
                    'status' => 'good',
                    'label' => 'REST API',
                    'message' => 'WordPress REST API is enabled',
                    'value' => true
                ];
            }
        }
        
        return [
            'status' => 'warning',
            'label' => 'REST API',
            'message' => 'REST API may not be accessible',
            'value' => false,
            'action' => 'Check REST API configuration'
        ];
    }
    
    /**
     * Vérifier si le cache est accessible en écriture
     */
    private function check_cache_writable() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/rmcu-cache/';
        
        // Créer le répertoire s'il n'existe pas
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        if (is_writable($cache_dir)) {
            return [
                'status' => 'good',
                'label' => 'Cache Directory',
                'message' => 'Cache directory is writable',
                'value' => true
            ];
        } else {
            return [
                'status' => 'warning',
                'label' => 'Cache Directory',
                'message' => 'Cache directory is not writable',
                'value' => false,
                'action' => 'Check directory permissions'
            ];
        }
    }
    
    /**
     * Vérifier si les logs sont accessibles en écriture
     */
    private function check_logs_writable() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/rmcu-logs/';
        
        // Créer le répertoire s'il n'existe pas
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        if (is_writable($log_dir)) {
            return [
                'status' => 'good',
                'label' => 'Log Directory',
                'message' => 'Log directory is writable',
                'value' => true
            ];
        } else {
            return [
                'status' => 'warning',
                'label' => 'Log Directory',
                'message' => 'Log directory is not writable',
                'value' => false,
                'action' => 'Check directory permissions'
            ];
        }
    }
    
    /**
     * Vérifier les fichiers JavaScript
     */
    private function check_javascript_files() {
        $required_files = [
            'core/rmcu-config.js',
            'core/rmcu-logger.js',
            'core/rmcu-interfaces.js',
            'extractors/rmcu-wordpress-parser.js',
            'extractors/rmcu-rankmath-scanner.js',
            'rmcu-main-controller.js',
            'rmcu-init.js'
        ];
        
        $missing = [];
        
        foreach ($required_files as $file) {
            $path = RMCU_PLUGIN_DIR . 'assets/js/' . $file;
            if (!file_exists($path)) {
                $missing[] = $file;
            }
        }
        
        if (empty($missing)) {
            return [
                'status' => 'good',
                'label' => 'JavaScript Files',
                'message' => 'All required JavaScript files are present',
                'value' => true
            ];
        } else {
            return [
                'status' => 'critical',
                'label' => 'JavaScript Files',
                'message' => 'Some JavaScript files are missing',
                'value' => false,
                'details' => 'Missing: ' . implode(', ', $missing),
                'action' => 'Reinstall the plugin'
            ];
        }
    }
    
    /**
     * Vérifier les fichiers CSS
     */
    private function check_css_files() {
        $required_files = [
            'admin.css',
            'widget.css'
        ];
        
        $missing = [];
        
        foreach ($required_files as $file) {
            $path = RMCU_PLUGIN_DIR . 'assets/css/' . $file;
            if (!file_exists($path)) {
                $missing[] = $file;
            }
        }
        
        if (empty($missing)) {
            return [
                'status' => 'good',
                'label' => 'CSS Files',
                'message' => 'All required CSS files are present',
                'value' => true
            ];
        } else {
            return [
                'status' => 'warning',
                'label' => 'CSS Files',
                'message' => 'Some CSS files are missing',
                'value' => false,
                'details' => 'Missing: ' . implode(', ', $missing),
                'action' => 'Reinstall the plugin'
            ];
        }
    }
    
    /**
     * Vérifier les conflits avec d'autres plugins
     */
    private function check_plugin_conflicts() {
        $conflicts = [];
        $active_plugins = get_option('active_plugins', []);
        
        // Liste des plugins connus pour causer des conflits
        $known_conflicts = [
            'wordpress-seo/wp-seo.php' => 'Yoast SEO (peut causer des conflits avec RankMath)',
            'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO (peut causer des conflits avec RankMath)',
        ];
        
        foreach ($known_conflicts as $plugin => $message) {
            if (in_array($plugin, $active_plugins)) {
                $conflicts[] = $message;
            }
        }
        
        if (empty($conflicts)) {
            return [
                'status' => 'good',
                'label' => 'Plugin Conflicts',
                'message' => 'No known plugin conflicts detected',
                'value' => true
            ];
        } else {
            return [
                'status' => 'warning',
                'label' => 'Plugin Conflicts',
                'message' => 'Potential plugin conflicts detected',
                'value' => false,
                'details' => implode(', ', $conflicts),
                'action' => 'Consider deactivating conflicting plugins'
            ];
        }
    }
    
    /**
     * Vérifier les paramètres de sécurité
     */
    private function check_security_settings() {
        $issues = [];
        
        // Vérifier SSL
        if (!is_ssl() && strpos(get_option('siteurl'), 'localhost') === false) {
            $issues[] = 'Site is not using HTTPS';
        }
        
        // Vérifier le debug mode
        if (defined('WP_DEBUG') && WP_DEBUG && !defined('WP_DEBUG_DISPLAY') || WP_DEBUG_DISPLAY) {
            $issues[] = 'Debug mode is enabled with display';
        }
        
        if (empty($issues)) {
            return [
                'status' => 'good',
                'label' => 'Security Settings',
                'message' => 'Security settings are appropriate',
                'value' => true
            ];
        } else {
            return [
                'status' => 'warning',
                'label' => 'Security Settings',
                'message' => 'Some security concerns detected',
                'value' => false,
                'details' => implode(', ', $issues),
                'action' => 'Review security settings'
            ];
        }
    }
    
    /**
     * Convertir la taille en bytes
     */
    private function convert_to_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Sauvegarder les résultats
     */
    private function save_results() {
        $results = [
            'timestamp' => current_time('c'),
            'status' => $this->status,
            'results' => $this->results
        ];
        
        update_option('rmcu_health_check_results', $results);
        set_transient('rmcu_health_check_cache', $results, HOUR_IN_SECONDS);
    }
    
    /**
     * Obtenir les résultats mis en cache
     */
    public function get_cached_results() {
        $cached = get_transient('rmcu_health_check_cache');
        
        if ($cached) {
            return $cached;
        }
        
        return get_option('rmcu_health_check_results', []);
    }
    
    /**
     * Ajouter la page admin
     */
    public function add_admin_page() {
        add_submenu_page(
            'rmcu-settings',
            'Health Check',
            'Health Check',
            'manage_options',
            'rmcu-health',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Afficher la page admin
     */
    public function render_admin_page() {
        $results = $this->get_cached_results();
        ?>
        <div class="wrap">
            <h1>RMCU Health Check</h1>
            
            <div class="rmcu-health-check-container">
                <button id="rmcu-run-health-check" class="button button-primary">
                    Run Health Check
                </button>
                
                <div id="rmcu-health-results">
                    <?php if (!empty($results)): ?>
                        <?php $this->render_results($results); ?>
                    <?php else: ?>
                        <p>No health check results available. Please run a check.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#rmcu-run-health-check').click(function() {
                var $button = $(this);
                var $results = $('#rmcu-health-results');
                
                $button.prop('disabled', true).text('Running...');
                $results.html('<p>Running health check...</p>');
                
                $.post(ajaxurl, {
                    action: 'rmcu_run_health_check',
                    nonce: '<?php echo wp_create_nonce('rmcu_health_check'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $results.html('<p class="error">Error: ' + response.data + '</p>');
                    }
                    $button.prop('disabled', false).text('Run Health Check');
                });
            });
        });
        </script>
        
        <style>
        .rmcu-health-check-container {
            background: white;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        
        .health-check-item {
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #ccc;
            background: #f9f9f9;
        }
        
        .health-check-item.good {
            border-color: #46b450;
        }
        
        .health-check-item.warning {
            border-color: #ffb900;
        }
        
        .health-check-item.critical {
            border-color: #dc3232;
        }
        
        .health-check-label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .health-check-message {
            color: #555;
        }
        
        .health-check-action {
            margin-top: 5px;
            color: #0073aa;
            font-style: italic;
        }
        </style>
        <?php
    }
    
    /**
     * Afficher les résultats
     */
    private function render_results($data) {
        if (empty($data['results'])) {
            echo '<p>No results available.</p>';
            return;
        }
        
        echo '<div class="health-check-results">';
        echo '<p><strong>Last check:</strong> ' . $data['timestamp'] . '</p>';
        echo '<p><strong>Overall status:</strong> ' . ucfirst($data['status']) . '</p>';
        
        foreach ($data['results'] as $test => $result) {
            $class = $result['status'];
            echo '<div class="health-check-item ' . $class . '">';
            echo '<div class="health-check-label">' . $result['label'] . '</div>';
            echo '<div class="health-check-message">' . $result['message'] . '</div>';
            
            if (!empty($result['action'])) {
                echo '<div class="health-check-action">Action: ' . $result['action'] . '</div>';
            }
            
            if (!empty($result['details'])) {
                echo '<div class="health-check-details">Details: ' . $result['details'] . '</div>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * AJAX handler pour exécuter le check
     */
    public function ajax_run_health_check() {
        check_ajax_referer('rmcu_health_check', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $results = $this->run_check();
        
        wp_send_json_success($results);
    }
    
    /**
     * Vérification programmée
     */
    public function scheduled_check() {
        $this->run_check();
        
        // Envoyer une notification si critique
        if ($this->status === 'critical') {
            $this->send_critical_notification();
        }
    }
    
    /**
     * Envoyer une notification critique
     */
    private function send_critical_notification() {
        $to = get_option('admin_email');
        $subject = '[RMCU] Critical Health Check Issues';
        
        $critical_issues = array_filter($this->results, function($result) {
            return $result['status'] === 'critical';
        });
        
        $message = "Critical issues detected in RMCU Health Check:\n\n";
        
        foreach ($critical_issues as $test => $result) {
            $message .= $result['label'] . ': ' . $result['message'] . "\n";
            if (!empty($result['action'])) {
                $message .= 'Action required: ' . $result['action'] . "\n";
            }
            $message .= "\n";
        }
        
        $message .= "Please check the RMCU Health Check page for more details.";
        
        wp_mail($to, $subject, $message);
    }
    
    /**
     * Ajouter aux tests Site Health de WordPress
     */
    public function add_site_health_tests($tests) {
        $tests['direct']['rmcu_health'] = [
            'label' => 'RMCU SEO Optimizer Health',
            'test' => [$this, 'site_health_test']
        ];
        
        return $tests;
    }
    
    /**
     * Test pour Site Health
     */
    public function site_health_test() {
        $results = $this->run_check();
        
        $label = 'RMCU SEO Optimizer is functioning properly';
        $status = 'good';
        $badge = [
            'label' => 'SEO',
            'color' => 'green'
        ];
        
        if ($results['status'] === 'critical') {
            $label = 'RMCU SEO Optimizer has critical issues';
            $status = 'critical';
            $badge['color'] = 'red';
        } elseif ($results['status'] === 'warning') {
            $label = 'RMCU SEO Optimizer has some issues';
            $status = 'recommended';
            $badge['color'] = 'orange';
        }
        
        $description = sprintf(
            '<p>Health check found %d passed tests, %d warnings, and %d critical issues.</p>',
            $results['summary']['passed'],
            $results['summary']['warnings'],
            $results['summary']['critical']
        );
        
        $actions = sprintf(
            '<p><a href="%s">View detailed health check</a></p>',
            admin_url('admin.php?page=rmcu-health')
        );
        
        return [
            'label' => $label,
            'status' => $status,
            'badge' => $badge,
            'description' => $description,
            'actions' => $actions,
            'test' => 'rmcu_health'
        ];
    }
}