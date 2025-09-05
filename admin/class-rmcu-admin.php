<?php
/**
 * RMCU Admin Class
 * 
 * @package RMCU
 * @since 1.0.0
 */

namespace RMCU\Admin;

use RMCU\Core\RMCU_Core;
use RMCU\Core\RMCU_Database;
use RMCU\Core\RMCU_Sanitizer;
use RMCU\Core\RMCU_Validator;

if (!defined('ABSPATH')) {
    exit;
}

class RMCU_Admin {
    /**
     * Instance Core
     *
     * @var RMCU_Core
     */
    private $core;

    /**
     * Version du plugin
     *
     * @var string
     */
    private $version;

    /**
     * Slug du menu principal
     *
     * @var string
     */
    private $menu_slug = 'rmcu-admin';

    /**
     * Capability requise
     *
     * @var string
     */
    private $capability = 'manage_options';

    /**
     * Pages d'administration
     *
     * @var array
     */
    private $admin_pages = [];

    /**
     * Constructeur
     */
    public function __construct() {
        $this->core = RMCU_Core::get_instance();
        $this->version = $this->core->get_version();
        
        $this->init();
    }

    /**
     * Initialisation
     */
    private function init() {
        // Hooks admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // AJAX handlers
        add_action('wp_ajax_rmcu_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_rmcu_get_captures', [$this, 'ajax_get_captures']);
        add_action('wp_ajax_rmcu_delete_capture', [$this, 'ajax_delete_capture']);
        add_action('wp_ajax_rmcu_export_data', [$this, 'ajax_export_data']);
        add_action('wp_ajax_rmcu_import_data', [$this, 'ajax_import_data']);
        add_action('wp_ajax_rmcu_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_rmcu_run_diagnostics', [$this, 'ajax_run_diagnostics']);
        
        // Notices admin
        add_action('admin_notices', [$this, 'display_admin_notices']);
        
        // Filtres admin
        add_filter('plugin_action_links_' . plugin_basename(RMCU_PLUGIN_FILE), [$this, 'add_action_links']);
        add_filter('admin_footer_text', [$this, 'admin_footer_text']);
        
        // Colonnes personnalisées
        $this->setup_custom_columns();
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);
    }

    /**
     * Ajouter le menu d'administration
     */
    public function add_admin_menu() {
        // Menu principal
        add_menu_page(
            __('RankMath Content & Media Capture', 'rmcu'),
            __('RMCU', 'rmcu'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_dashboard_page'],
            $this->get_menu_icon(),
            30
        );

        // Dashboard
        $this->admin_pages['dashboard'] = add_submenu_page(
            $this->menu_slug,
            __('Dashboard', 'rmcu'),
            __('Dashboard', 'rmcu'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_dashboard_page']
        );

        // Captures
        $this->admin_pages['captures'] = add_submenu_page(
            $this->menu_slug,
            __('Captures', 'rmcu'),
            __('Captures', 'rmcu'),
            $this->capability,
            'rmcu-captures',
            [$this, 'render_captures_page']
        );

        // Content Analysis
        $this->admin_pages['analysis'] = add_submenu_page(
            $this->menu_slug,
            __('Content Analysis', 'rmcu'),
            __('Analysis', 'rmcu'),
            $this->capability,
            'rmcu-analysis',
            [$this, 'render_analysis_page']
        );

        // Media Library
        $this->admin_pages['media'] = add_submenu_page(
            $this->menu_slug,
            __('Media Library', 'rmcu'),
            __('Media', 'rmcu'),
            $this->capability,
            'rmcu-media',
            [$this, 'render_media_page']
        );

        // Settings
        $this->admin_pages['settings'] = add_submenu_page(
            $this->menu_slug,
            __('Settings', 'rmcu'),
            __('Settings', 'rmcu'),
            $this->capability,
            'rmcu-settings',
            [$this, 'render_settings_page']
        );

        // Tools
        $this->admin_pages['tools'] = add_submenu_page(
            $this->menu_slug,
            __('Tools', 'rmcu'),
            __('Tools', 'rmcu'),
            $this->capability,
            'rmcu-tools',
            [$this, 'render_tools_page']
        );

        // Help
        $this->admin_pages['help'] = add_submenu_page(
            $this->menu_slug,
            __('Help & Support', 'rmcu'),
            __('Help', 'rmcu'),
            $this->capability,
            'rmcu-help',
            [$this, 'render_help_page']
        );

        // Hook pour ajouter des pages personnalisées
        do_action('rmcu_admin_menu', $this->menu_slug, $this->capability);
    }

    /**
     * Enqueue assets admin
     */
    public function enqueue_admin_assets($hook) {
        // Vérifier si on est sur une page RMCU
        if (!in_array($hook, $this->admin_pages)) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'rmcu-admin',
            RMCU_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $this->version
        );

        // JavaScript
        wp_enqueue_script(
            'rmcu-admin',
            RMCU_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api', 'wp-i18n'],
            $this->version,
            true
        );

        // Charger les modules JavaScript spécifiques
        $this->enqueue_javascript_modules();

        // Localization
        wp_localize_script('rmcu-admin', 'RMCU_Admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => rest_url('rmcu/v1/'),
            'nonce' => wp_create_nonce('rmcu_admin_nonce'),
            'version' => $this->version,
            'settings' => $this->get_localized_settings(),
            'i18n' => $this->get_i18n_strings(),
            'current_page' => isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '',
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ]);

        // Media uploader
        if (in_array($hook, [$this->admin_pages['media'], $this->admin_pages['captures']])) {
            wp_enqueue_media();
        }

        // CodeMirror pour l'édition de code
        if ($hook === $this->admin_pages['tools']) {
            wp_enqueue_code_editor(['type' => 'application/json']);
        }

        // Chart.js pour les graphiques
        if ($hook === $this->admin_pages['dashboard']) {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                [],
                '3.9.1',
                true
            );
        }
    }

    /**
     * Charger les modules JavaScript
     */
    private function enqueue_javascript_modules() {
        $modules = [
            'rmcu-logger',
            'rmcu-config', 
            'rmcu-main-controller',
            'rmcu-wordpress-parser',
            'rmcu-rankmath-scanner',
            'capture-video',
            'capture-audio',
            'capture-screen',
            'rmcu-api-client'
        ];

        foreach ($modules as $module) {
            wp_enqueue_script(
                $module,
                RMCU_PLUGIN_URL . "assets/js/modules/{$module}.js",
                [],
                $this->version,
                true
            );
        }
    }

    /**
     * Render Dashboard Page
     */
    public function render_dashboard_page() {
        $stats = $this->get_dashboard_stats();
        $recent_captures = $this->get_recent_captures(5);
        $system_status = $this->get_system_status();
        
        include RMCU_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render Captures Page
     */
    public function render_captures_page() {
        $list_table = new RMCU_Captures_List_Table();
        $list_table->prepare_items();
        
        include RMCU_PLUGIN_DIR . 'admin/views/captures.php';
    }

    /**
     * Render Analysis Page
     */
    public function render_analysis_page() {
        $content_data = $this->get_content_analysis_data();
        
        include RMCU_PLUGIN_DIR . 'admin/views/analysis.php';
    }

    /**
     * Render Media Page
     */
    public function render_media_page() {
        $media_items = $this->get_media_library_items();
        
        include RMCU_PLUGIN_DIR . 'admin/views/media.php';
    }

    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        $settings = $this->core->get_settings();
        $tabs = $this->get_settings_tabs();
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        include RMCU_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render Tools Page
     */
    public function render_tools_page() {
        include RMCU_PLUGIN_DIR . 'admin/views/tools.php';
    }

    /**
     * Render Help Page
     */
    public function render_help_page() {
        $faqs = $this->get_faqs();
        $documentation = $this->get_documentation_links();
        
        include RMCU_PLUGIN_DIR . 'admin/views/help.php';
    }

    /**
     * Enregistrer les settings
     */
    public function register_settings() {
        // General Settings
        register_setting('rmcu_general_settings', 'rmcu_enable_capture');
        register_setting('rmcu_general_settings', 'rmcu_auto_save');
        register_setting('rmcu_general_settings', 'rmcu_compression_level');

        // Capture Settings
        register_setting('rmcu_capture_settings', 'rmcu_video_quality');
        register_setting('rmcu_capture_settings', 'rmcu_audio_bitrate');
        register_setting('rmcu_capture_settings', 'rmcu_screenshot_format');
        
        // SEO Settings
        register_setting('rmcu_seo_settings', 'rmcu_enable_rankmath_integration');
        register_setting('rmcu_seo_settings', 'rmcu_auto_analyze');
        register_setting('rmcu_seo_settings', 'rmcu_seo_threshold');

        // Advanced Settings
        register_setting('rmcu_advanced_settings', 'rmcu_cache_duration');
        register_setting('rmcu_advanced_settings', 'rmcu_api_rate_limit');
        register_setting('rmcu_advanced_settings', 'rmcu_debug_mode');
    }

    /**
     * AJAX: Sauvegarder les settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');

        if (!current_user_can($this->capability)) {
            wp_die(__('Insufficient permissions', 'rmcu'));
        }

        $settings = isset($_POST['settings']) ? $_POST['settings'] : [];
        
        // Sanitizer les settings
        $sanitizer = new RMCU_Sanitizer();
        $sanitized_settings = $sanitizer->sanitize_array($settings);
        
        // Valider les settings
        $validator = new RMCU_Validator();
        $validation_result = $validator->validate_settings($sanitized_settings);
        
        if ($validation_result['valid']) {
            update_option('rmcu_settings', $sanitized_settings);
            wp_send_json_success([
                'message' => __('Settings saved successfully', 'rmcu')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Validation failed', 'rmcu'),
                'errors' => $validation_result['errors']
            ]);
        }
    }

    /**
     * AJAX: Obtenir les captures
     */
    public function ajax_get_captures() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        $filters = isset($_POST['filters']) ? $_POST['filters'] : [];

        $db = new RMCU_Database();
        $captures = $db->get_captures($page, $per_page, $filters);

        wp_send_json_success($captures);
    }

    /**
     * AJAX: Supprimer une capture
     */
    public function ajax_delete_capture() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');

        if (!current_user_can($this->capability)) {
            wp_die(__('Insufficient permissions', 'rmcu'));
        }

        $capture_id = isset($_POST['capture_id']) ? absint($_POST['capture_id']) : 0;
        
        if (!$capture_id) {
            wp_send_json_error(['message' => __('Invalid capture ID', 'rmcu')]);
        }

        $db = new RMCU_Database();
        $result = $db->delete_capture($capture_id);

        if ($result) {
            wp_send_json_success([
                'message' => __('Capture deleted successfully', 'rmcu')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to delete capture', 'rmcu')
            ]);
        }
    }

    /**
     * AJAX: Exporter les données
     */
    public function ajax_export_data() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');

        if (!current_user_can($this->capability)) {
            wp_die(__('Insufficient permissions', 'rmcu'));
        }

        $export_type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'json';

        $data = $this->prepare_export_data($export_type);
        
        if ($format === 'json') {
            $export = json_encode($data, JSON_PRETTY_PRINT);
            $filename = 'rmcu-export-' . date('Y-m-d-H-i-s') . '.json';
        } else {
            $export = $this->convert_to_csv($data);
            $filename = 'rmcu-export-' . date('Y-m-d-H-i-s') . '.csv';
        }

        wp_send_json_success([
            'data' => base64_encode($export),
            'filename' => $filename,
            'mime' => $format === 'json' ? 'application/json' : 'text/csv'
        ]);
    }

    /**
     * AJAX: Importer les données
     */
    public function ajax_import_data() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');

        if (!current_user_can($this->capability)) {
            wp_die(__('Insufficient permissions', 'rmcu'));
        }

        if (!isset($_FILES['import_file'])) {
            wp_send_json_error(['message' => __('No file uploaded', 'rmcu')]);
        }

        $file = $_FILES['import_file'];
        $content = file_get_contents($file['tmp_name']);
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('Invalid JSON file', 'rmcu')]);
        }

        $result = $this->process_import_data($data);

        if ($result['success']) {
            wp_send_json_success([
                'message' => __('Data imported successfully', 'rmcu'),
                'stats' => $result['stats']
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Import failed', 'rmcu'),
                'errors' => $result['errors']
            ]);
        }
    }

    /**
     * AJAX: Vider le cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');

        if (!current_user_can($this->capability)) {
            wp_die(__('Insufficient permissions', 'rmcu'));
        }

        $cache_type = isset($_POST['cache_type']) ? sanitize_text_field($_POST['cache_type']) : 'all';
        
        $result = $this->clear_cache($cache_type);

        if ($result) {
            wp_send_json_success([
                'message' => __('Cache cleared successfully', 'rmcu')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to clear cache', 'rmcu')
            ]);
        }
    }

    /**
     * AJAX: Exécuter les diagnostics
     */
    public function ajax_run_diagnostics() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');

        if (!current_user_can($this->capability)) {
            wp_die(__('Insufficient permissions', 'rmcu'));
        }

        $diagnostics = $this->run_diagnostics();

        wp_send_json_success($diagnostics);
    }

    /**
     * Obtenir les statistiques du dashboard
     */
    private function get_dashboard_stats() {
        $db = new RMCU_Database();
        
        return [
            'total_captures' => $db->get_total_captures(),
            'total_size' => $db->get_total_storage_size(),
            'today_captures' => $db->get_captures_today(),
            'weekly_trend' => $db->get_weekly_trend(),
            'top_content_types' => $db->get_top_content_types(),
            'seo_scores' => $db->get_average_seo_scores()
        ];
    }

    /**
     * Obtenir les captures récentes
     */
    private function get_recent_captures($limit = 5) {
        $db = new RMCU_Database();
        return $db->get_recent_captures($limit);
    }

    /**
     * Obtenir le statut du système
     */
    private function get_system_status() {
        return [
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => $this->version,
            'memory_limit' => ini_get('memory_limit'),
            'max_upload_size' => size_format(wp_max_upload_size()),
            'rankmath_active' => $this->core->is_rankmath_active(),
            'database_size' => $this->get_database_size()
        ];
    }

    /**
     * Obtenir l'icône du menu
     */
    private function get_menu_icon() {
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                <path fill="white" d="M10 2C5.58 2 2 5.58 2 10s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm3.5 4L9 10.5 6.5 8l-1.5 1.5L9 13.5l5.5-5.5L13.5 6z"/>
            </svg>'
        );
    }

    /**
     * Afficher les notices admin
     */
    public function display_admin_notices() {
        // Vérifier les mises à jour
        if ($this->needs_database_update()) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('RMCU database needs to be updated.', 'rmcu'); ?>
                <a href="<?php echo admin_url('admin.php?page=rmcu-tools&action=update_database'); ?>" class="button">
                    <?php _e('Update Now', 'rmcu'); ?>
                </a></p>
            </div>
            <?php
        }

        // Vérifier RankMath
        if (!$this->core->is_rankmath_active()) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p><?php _e('RankMath SEO is not active. Some features may be limited.', 'rmcu'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Ajouter des liens d'action
     */
    public function add_action_links($links) {
        $action_links = [
            '<a href="' . admin_url('admin.php?page=rmcu-settings') . '">' . __('Settings', 'rmcu') . '</a>',
            '<a href="' . admin_url('admin.php?page=rmcu-help') . '">' . __('Help', 'rmcu') . '</a>'
        ];
        
        return array_merge($action_links, $links);
    }

    /**
     * Modifier le footer admin
     */
    public function admin_footer_text($text) {
        if (isset($_GET['page']) && strpos($_GET['page'], 'rmcu') !== false) {
            $text = sprintf(
                __('Thank you for using <a href="%s" target="_blank">RankMath Content & Media Capture</a>', 'rmcu'),
                'https://yourwebsite.com'
            );
        }
        return $text;
    }

    /**
     * Configurer les colonnes personnalisées
     */
    private function setup_custom_columns() {
        // Colonnes pour les posts
        add_filter('manage_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_posts_custom_column', [$this, 'display_custom_columns'], 10, 2);
        
        // Colonnes pour les pages
        add_filter('manage_pages_columns', [$this, 'add_custom_columns']);
        add_action('manage_pages_custom_column', [$this, 'display_custom_columns'], 10, 2);
    }

    /**
     * Ajouter des colonnes personnalisées
     */
    public function add_custom_columns($columns) {
        $columns['rmcu_seo_score'] = __('SEO Score', 'rmcu');
        $columns['rmcu_captures'] = __('Captures', 'rmcu');
        return $columns;
    }

    /**
     * Afficher les colonnes personnalisées
     */
    public function display_custom_columns($column, $post_id) {
        switch ($column) {
            case 'rmcu_seo_score':
                $score = get_post_meta($post_id, '_rmcu_seo_score', true);
                if ($score) {
                    $color = $score >= 80 ? 'green' : ($score >= 60 ? 'orange' : 'red');
                    echo '<span style="color:' . $color . '">' . $score . '/100</span>';
                }
                break;
                
            case 'rmcu_captures':
                $db = new RMCU_Database();
                $count = $db->get_capture_count_for_post($post_id);
                echo $count;
                break;
        }
    }

    /**
     * Ajouter des widgets au dashboard
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'rmcu_dashboard_widget',
            __('RMCU Overview', 'rmcu'),
            [$this, 'render_dashboard_widget']
        );
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $stats = $this->get_dashboard_stats();
        include RMCU_PLUGIN_DIR . 'admin/partials/dashboard-widget.php';
    }

    /**
     * Helpers et méthodes utilitaires
     */
    
    private function needs_database_update() {
        $current_version = get_option('rmcu_db_version', '0');
        return version_compare($current_version, RMCU_DB_VERSION, '<');
    }

    private function get_database_size() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rmcu_captures';
        
        $size = $wpdb->get_var("
            SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size
            FROM information_schema.TABLES
            WHERE table_schema = '" . DB_NAME . "'
            AND table_name = '{$table_name}'
        ");
        
        return $size ? $size . ' MB' : '0 MB';
    }

    private function clear_cache($type = 'all') {
        $cleared = false;
        
        if ($type === 'all' || $type === 'transients') {
            delete_transient('rmcu_stats_cache');
            delete_transient('rmcu_captures_cache');
            $cleared = true;
        }
        
        if ($type === 'all' || $type === 'files') {
            $cache_dir = WP_CONTENT_DIR . '/cache/rmcu/';
            if (is_dir($cache_dir)) {
                $this->delete_directory($cache_dir);
                $cleared = true;
            }
        }
        
        return $cleared;
    }

    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }

    private function get_settings_tabs() {
        return [
            'general' => __('General', 'rmcu'),
            'capture' => __('Capture', 'rmcu'),
            'seo' => __('SEO Integration', 'rmcu'),
            'media' => __('Media', 'rmcu'),
            'advanced' => __('Advanced', 'rmcu')
        ];
    }

    private function get_localized_settings() {
        return [
            'enable_capture' => get_option('rmcu_enable_capture', true),
            'auto_save' => get_option('rmcu_auto_save', false),
            'compression_level' => get_option('rmcu_compression_level', 80),
            'video_quality' => get_option('rmcu_video_quality', 'high'),
            'debug_mode' => get_option('rmcu_debug_mode', false)
        ];
    }

    private function get_i18n_strings() {
        return [
            'confirm_delete' => __('Are you sure you want to delete this capture?', 'rmcu'),
            'saving' => __('Saving...', 'rmcu'),
            'saved' => __('Saved successfully', 'rmcu'),
            'error' => __('An error occurred', 'rmcu'),
            'loading' => __('Loading...', 'rmcu'),
            'no_data' => __('No data available', 'rmcu')
        ];
    }

    private function run_diagnostics() {
        return [
            'php_extensions' => [
                'gd' => extension_loaded('gd'),
                'imagick' => extension_loaded('imagick'),
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
                'mbstring' => extension_loaded('mbstring')
            ],
            'directories' => [
                'uploads' => wp_upload_dir()['basedir'] . '/rmcu/',
                'cache' => WP_CONTENT_DIR . '/cache/rmcu/'
            ],
            'permissions' => [
                'uploads' => is_writable(wp_upload_dir()['basedir']),
                'cache' => is_writable(WP_CONTENT_DIR)
            ],
            'api' => [
                'rest_enabled' => (bool) get_option('permalink_structure'),
                'endpoints' => $this->test_api_endpoints()
            ]
        ];
    }

    private function test_api_endpoints() {
        $endpoints = [
            'captures' => rest_url('rmcu/v1/captures'),
            'settings' => rest_url('rmcu/v1/settings'),
            'media' => rest_url('rmcu/v1/media')
        ];
        
        $results = [];
        foreach ($endpoints as $name => $url) {
            $response = wp_remote_get($url, [
                'headers' => [
                    'X-WP-Nonce' => wp_create_nonce('wp_rest')
                ]
            ]);
            $results[$name] = !is_wp_error($response);
        }
        
        return $results;
    }

    private function prepare_export_data($type) {
        $data = [];
        
        if ($type === 'all' || $type === 'settings') {
            $data['settings'] = get_option('rmcu_settings', []);
        }
        
        if ($type === 'all' || $type === 'captures') {
            $db = new RMCU_Database();
            $data['captures'] = $db->get_all_captures();
        }
        
        if ($type === 'all' || $type === 'media') {
            $data['media'] = $this->get_all_media_data();
        }
        
        $data['export_info'] = [
            'version' => $this->version,
            'date' => current_time('mysql'),
            'site_url' => get_site_url()
        ];
        
        return $data;
    }

    private function process_import_data($data) {
        $result = [
            'success' => true,
            'stats' => [],
            'errors' => []
        ];
        
        try {
            // Importer les settings
            if (isset($data['settings'])) {
                update_option('rmcu_settings', $data['settings']);
                $result['stats']['settings'] = count($data['settings']);
            }
            
            // Importer les captures
            if (isset($data['captures'])) {
                $db = new RMCU_Database();
                foreach ($data['captures'] as $capture) {
                    $db->insert_capture($capture);
                }
                $result['stats']['captures'] = count($data['captures']);
            }
            
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }

    private function convert_to_csv($data) {
        $output = fopen('php://temp', 'r+');
        
        // Flatten the data for CSV
        $flat_data = [];
        foreach ($data as $section => $items) {
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $item['section'] = $section;
                        $flat_data[] = $item;
                    }
                }
            }
        }
        
        // Write headers
        if (!empty($flat_data)) {
            fputcsv($output, array_keys($flat_data[0]));
            
            // Write data
            foreach ($flat_data as $row) {
                fputcsv($output, $row);
            }
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    private function get_content_analysis_data() {
        return [
            'posts' => $this->analyze_post_type('post'),
            'pages' => $this->analyze_post_type('page'),
            'custom' => $this->analyze_custom_post_types()
        ];
    }

    private function analyze_post_type($post_type) {
        $posts = get_posts([
            'post_type' => $post_type,
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);
        
        $analysis = [
            'total' => count($posts),
            'with_seo' => 0,
            'avg_score' => 0,
            'needs_improvement' => []
        ];
        
        $total_score = 0;
        foreach ($posts as $post) {
            $score = get_post_meta($post->ID, '_rmcu_seo_score', true);
            if ($score) {
                $analysis['with_seo']++;
                $total_score += $score;
                
                if ($score < 60) {
                    $analysis['needs_improvement'][] = [
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'score' => $score
                    ];
                }
            }
        }
        
        if ($analysis['with_seo'] > 0) {
            $analysis['avg_score'] = round($total_score / $analysis['with_seo']);
        }
        
        return $analysis;
    }

    private function analyze_custom_post_types() {
        $custom_types = get_post_types(['public' => true, '_builtin' => false]);
        $analysis = [];
        
        foreach ($custom_types as $type) {
            $analysis[$type] = $this->analyze_post_type($type);
        }
        
        return $analysis;
    }

    private function get_media_library_items() {
        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 50,
            'meta_query' => [
                [
                    'key' => '_rmcu_processed',
                    'value' => '1'
                ]
            ]
        ];
        
        return get_posts($args);
    }

    private function get_all_media_data() {
        $media = [];
        $attachments = get_posts([
            'post_type' => 'attachment',
            'numberposts' => -1
        ]);
        
        foreach ($attachments as $attachment) {
            $media[] = [
                'id' => $attachment->ID,
                'url' => wp_get_attachment_url($attachment->ID),
                'meta' => wp_get_attachment_metadata($attachment->ID)
            ];
        }
        
        return $media;
    }

    private function get_faqs() {
        return [
            [
                'question' => __('How do I capture content?', 'rmcu'),
                'answer' => __('Click the capture button in the editor toolbar...', 'rmcu')
            ],
            [
                'question' => __('What formats are supported?', 'rmcu'),
                'answer' => __('We support WebM, MP4 for video, PNG, JPG for images...', 'rmcu')
            ],
            [
                'question' => __('How to integrate with RankMath?', 'rmcu'),
                'answer' => __('RankMath integration is automatic when both plugins are active...', 'rmcu')
            ]
        ];
    }

    private function get_documentation_links() {
        return [
            'getting_started' => 'https://docs.example.com/getting-started',
            'api_reference' => 'https://docs.example.com/api',
            'video_tutorials' => 'https://docs.example.com/videos',
            'troubleshooting' => 'https://docs.example.com/troubleshooting'
        ];
    }
}

// Initialiser la classe admin
new RMCU_Admin();
?>