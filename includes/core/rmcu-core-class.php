<?php
/**
 * RMCU Core Class
 * Classe principale singleton qui orchestre tout le plugin
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principale du plugin (Singleton)
 */
class RMCU_Core {
    
    /**
     * Instance unique
     */
    private static $instance = null;
    
    /**
     * Version du plugin
     */
    private $version = '2.0.0';
    
    /**
     * Container pour les services
     */
    private $container = [];
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Modules chargés
     */
    private $modules = [];
    
    /**
     * État d'initialisation
     */
    private $initialized = false;
    
    /**
     * Obtenir l'instance singleton
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur privé
     */
    private function __construct() {
        // Définir les constantes si nécessaire
        $this->define_constants();
        
        // Initialiser le logger en premier
        $this->init_logger();
        
        // Logger le démarrage
        $this->logger->info('RMCU Core initialized', [
            'version' => $this->version,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        ]);
    }
    
    /**
     * Empêcher le clonage
     */
    private function __clone() {}
    
    /**
     * Empêcher la désérialisation
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Initialiser le plugin
     */
    public function init() {
        if ($this->initialized) {
            return;
        }
        
        try {
            // Vérifier les prérequis
            if (!$this->check_requirements()) {
                return false;
            }
            
            // Charger les dépendances
            $this->load_dependencies();
            
            // Initialiser les modules
            $this->init_modules();
            
            // Enregistrer les hooks
            $this->register_hooks();
            
            // Initialiser les services
            $this->init_services();
            
            // Marquer comme initialisé
            $this->initialized = true;
            
            // Hook pour permettre des extensions
            do_action('rmcu_core_initialized', $this);
            
            $this->logger->info('RMCU Core fully initialized');
            
        } catch (Exception $e) {
            $this->logger->critical('Failed to initialize RMCU Core', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Afficher une notice admin
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>';
                echo esc_html(sprintf(
                    __('RMCU Plugin Error: %s', 'rmcu'),
                    $e->getMessage()
                ));
                echo '</p></div>';
            });
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Définir les constantes
     */
    private function define_constants() {
        // Version
        if (!defined('RMCU_VERSION')) {
            define('RMCU_VERSION', $this->version);
        }
        
        // Chemins
        if (!defined('RMCU_PLUGIN_FILE')) {
            define('RMCU_PLUGIN_FILE', dirname(dirname(__FILE__)) . '/rankmath-capture-unified.php');
        }
        
        if (!defined('RMCU_PLUGIN_DIR')) {
            define('RMCU_PLUGIN_DIR', plugin_dir_path(RMCU_PLUGIN_FILE));
        }
        
        if (!defined('RMCU_PLUGIN_URL')) {
            define('RMCU_PLUGIN_URL', plugin_dir_url(RMCU_PLUGIN_FILE));
        }
        
        if (!defined('RMCU_INCLUDES_DIR')) {
            define('RMCU_INCLUDES_DIR', RMCU_PLUGIN_DIR . 'includes/');
        }
        
        if (!defined('RMCU_ADMIN_DIR')) {
            define('RMCU_ADMIN_DIR', RMCU_PLUGIN_DIR . 'admin/');
        }
        
        if (!defined('RMCU_PUBLIC_DIR')) {
            define('RMCU_PUBLIC_DIR', RMCU_PLUGIN_DIR . 'public/');
        }
        
        if (!defined('RMCU_ASSETS_URL')) {
            define('RMCU_ASSETS_URL', RMCU_PLUGIN_URL . 'assets/');
        }
        
        // Debug
        if (!defined('RMCU_DEBUG')) {
            define('RMCU_DEBUG', WP_DEBUG);
        }
    }
    
    /**
     * Initialiser le logger
     */
    private function init_logger() {
        require_once RMCU_INCLUDES_DIR . 'class-rmcu-logger.php';
        $this->logger = new RMCU_Logger('Core');
        $this->container['logger'] = $this->logger;
    }
    
    /**
     * Vérifier les prérequis
     */
    private function check_requirements() {
        $errors = [];
        
        // Version PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = sprintf(
                __('RMCU requires PHP 7.4 or higher. Your version: %s', 'rmcu'),
                PHP_VERSION
            );
        }
        
        // Version WordPress
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            $errors[] = sprintf(
                __('RMCU requires WordPress 5.8 or higher. Your version: %s', 'rmcu'),
                get_bloginfo('version')
            );
        }
        
        // Extensions PHP requises
        $required_extensions = ['json', 'curl', 'mbstring'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $errors[] = sprintf(
                    __('RMCU requires PHP extension: %s', 'rmcu'),
                    $ext
                );
            }
        }
        
        // Vérifier RankMath (optionnel mais recommandé)
        if (!class_exists('RankMath')) {
            $this->logger->warning('RankMath not detected. Some features will be limited.');
        }
        
        // Si des erreurs, les afficher
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->logger->error('Requirement check failed', ['error' => $error]);
            }
            
            // Désactiver le plugin
            add_action('admin_notices', function() use ($errors) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>' . __('RMCU Plugin cannot run:', 'rmcu') . '</strong><br>';
                echo implode('<br>', array_map('esc_html', $errors));
                echo '</p></div>';
            });
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Charger les dépendances
     */
    private function load_dependencies() {
        // Classes Core
        $core_classes = [
            'class-rmcu-settings-page.php',
            'class-rmcu-api-handler.php',
            'class-rmcu-webhook-handler.php',
            'class-rmcu-database.php',
            'class-rmcu-capture-handler.php',
            'class-rmcu-sanitizer.php',
            'class-rmcu-validator.php',
            'class-rmcu-rankmath-integration.php',
            'class-rmcu-media-handler.php',
            'class-rmcu-shortcode.php',
            'class-rmcu-ajax.php',
            'class-rmcu-cron.php'
        ];
        
        foreach ($core_classes as $class_file) {
            $file_path = RMCU_INCLUDES_DIR . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->logger->debug('Loaded dependency', ['file' => $class_file]);
            } else {
                $this->logger->warning('Dependency not found', ['file' => $class_file]);
            }
        }
        
        // Classes Admin (si on est dans l'admin)
        if (is_admin()) {
            $admin_classes = [
                'class-rmcu-admin.php',
                'class-rmcu-admin-notices.php',
                'class-rmcu-admin-menu.php',
                'class-rmcu-admin-ajax.php'
            ];
            
            foreach ($admin_classes as $class_file) {
                $file_path = RMCU_ADMIN_DIR . $class_file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
        }
        
        // Classes Public (si on est sur le front)
        if (!is_admin()) {
            $public_classes = [
                'class-rmcu-public.php',
                'class-rmcu-capture-widget.php'
            ];
            
            foreach ($public_classes as $class_file) {
                $file_path = RMCU_PUBLIC_DIR . $class_file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
        }
    }
    
    /**
     * Initialiser les modules
     */
    private function init_modules() {
        // Module Settings
        if (class_exists('RMCU_Settings_Page')) {
            $this->modules['settings'] = RMCU_Settings_Page::get_instance();
            $this->container['settings'] = $this->modules['settings'];
        }
        
        // Module Database
        if (class_exists('RMCU_Database')) {
            $this->modules['database'] = new RMCU_Database();
            $this->container['database'] = $this->modules['database'];
        }
        
        // Module API
        if (class_exists('RMCU_API_Handler')) {
            $this->modules['api'] = new RMCU_API_Handler();
            $this->container['api'] = $this->modules['api'];
        }
        
        // Module Webhook
        if (class_exists('RMCU_Webhook_Handler')) {
            $this->modules['webhook'] = new RMCU_Webhook_Handler();
            $this->container['webhook'] = $this->modules['webhook'];
        }
        
        // Module Capture
        if (class_exists('RMCU_Capture_Handler')) {
            $this->modules['capture'] = new RMCU_Capture_Handler();
            $this->container['capture'] = $this->modules['capture'];
        }
        
        // Module RankMath Integration
        if (class_exists('RMCU_RankMath_Integration')) {
            $this->modules['rankmath'] = new RMCU_RankMath_Integration();
            $this->container['rankmath'] = $this->modules['rankmath'];
        }
        
        // Module Sanitizer
        if (class_exists('RMCU_Sanitizer')) {
            $this->modules['sanitizer'] = new RMCU_Sanitizer();
            $this->container['sanitizer'] = $this->modules['sanitizer'];
        }
        
        // Module Validator
        if (class_exists('RMCU_Validator')) {
            $this->modules['validator'] = new RMCU_Validator();
            $this->container['validator'] = $this->modules['validator'];
        }
        
        // Module Shortcode
        if (class_exists('RMCU_Shortcode')) {
            $this->modules['shortcode'] = new RMCU_Shortcode();
            $this->container['shortcode'] = $this->modules['shortcode'];
        }
        
        // Module AJAX
        if (class_exists('RMCU_Ajax')) {
            $this->modules['ajax'] = new RMCU_Ajax();
            $this->container['ajax'] = $this->modules['ajax'];
        }
        
        // Module Cron
        if (class_exists('RMCU_Cron')) {
            $this->modules['cron'] = new RMCU_Cron();
            $this->container['cron'] = $this->modules['cron'];
        }
        
        // Module Admin
        if (is_admin() && class_exists('RMCU_Admin')) {
            $this->modules['admin'] = new RMCU_Admin();
            $this->container['admin'] = $this->modules['admin'];
        }
        
        // Module Public
        if (!is_admin() && class_exists('RMCU_Public')) {
            $this->modules['public'] = new RMCU_Public();
            $this->container['public'] = $this->modules['public'];
        }
        
        $this->logger->info('Modules initialized', [
            'modules' => array_keys($this->modules)
        ]);
    }
    
    /**
     * Enregistrer les hooks WordPress
     */
    private function register_hooks() {
        // Activation/Désactivation
        register_activation_hook(RMCU_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(RMCU_PLUGIN_FILE, [$this, 'deactivate']);
        
        // Init
        add_action('init', [$this, 'wp_init']);
        add_action('plugins_loaded', [$this, 'plugins_loaded']);
        
        // Scripts et styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX
        add_action('wp_ajax_rmcu_heartbeat', [$this, 'ajax_heartbeat']);
        add_action('wp_ajax_nopriv_rmcu_heartbeat', [$this, 'ajax_heartbeat']);
        
        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Widgets
        add_action('widgets_init', [$this, 'register_widgets']);
        
        // Filtres
        add_filter('plugin_action_links_' . plugin_basename(RMCU_PLUGIN_FILE), [$this, 'add_action_links']);
        add_filter('plugin_row_meta', [$this, 'add_row_meta'], 10, 2);
    }
    
    /**
     * Initialiser les services
     */
    private function init_services() {
        // Service de cache
        $this->container['cache'] = new stdClass();
        
        // Service de configuration
        $this->container['config'] = get_option('rmcu_settings', []);
        
        // Service de session
        if (!session_id()) {
            session_start();
        }
        
        // Service de traduction
        load_plugin_textdomain('rmcu', false, dirname(plugin_basename(RMCU_PLUGIN_FILE)) . '/languages');
    }
    
    /**
     * Hook WordPress init
     */
    public function wp_init() {
        // Enregistrer les post types personnalisés si nécessaire
        $this->register_post_types();
        
        // Enregistrer les taxonomies si nécessaire
        $this->register_taxonomies();
        
        // Flush rewrite rules si nécessaire
        if (get_option('rmcu_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('rmcu_flush_rewrite_rules');
        }
    }
    
    /**
     * Hook plugins_loaded
     */
    public function plugins_loaded() {
        // Vérifier les mises à jour
        $this->check_updates();
        
        // Hook pour les extensions
        do_action('rmcu_plugins_loaded', $this);
    }
    
    /**
     * Enregistrer les post types
     */
    private function register_post_types() {
        // Si on veut un post type pour les captures
        /*
        register_post_type('rmcu_capture', [
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'supports' => ['title', 'editor', 'custom-fields']
        ]);
        */
    }
    
    /**
     * Enregistrer les taxonomies
     */
    private function register_taxonomies() {
        // Si on veut des taxonomies pour organiser les captures
    }
    
    /**
     * Enqueue assets publics
     */
    public function enqueue_public_assets() {
        // CSS
        wp_enqueue_style(
            'rmcu-public',
            RMCU_ASSETS_URL . 'css/public.css',
            [],
            RMCU_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'rmcu-public',
            RMCU_ASSETS_URL . 'js/capture.js',
            ['jquery'],
            RMCU_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('rmcu-public', 'rmcuPublic', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiUrl' => rest_url('rmcu/v1/'),
            'nonce' => wp_create_nonce('rmcu_public_nonce'),
            'settings' => $this->get_public_settings()
        ]);
    }
    
    /**
     * Enqueue assets admin
     */
    public function enqueue_admin_assets($hook) {
        // Seulement sur nos pages
        if (strpos($hook, 'rmcu') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'rmcu-admin',
            RMCU_ASSETS_URL . 'css/admin.css',
            [],
            RMCU_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'rmcu-admin',
            RMCU_ASSETS_URL . 'js/admin.js',
            ['jquery'],
            RMCU_VERSION,
            true
        );
    }
    
    /**
     * AJAX Heartbeat
     */
    public function ajax_heartbeat() {
        check_ajax_referer('rmcu_public_nonce', 'nonce');
        
        wp_send_json_success([
            'status' => 'alive',
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * Enregistrer les routes REST
     */
    public function register_rest_routes() {
        // Déléguer aux modules concernés
        if (isset($this->modules['api'])) {
            $this->modules['api']->register_routes();
        }
    }
    
    /**
     * Enregistrer les widgets
     */
    public function register_widgets() {
        if (class_exists('RMCU_Capture_Widget')) {
            register_widget('RMCU_Capture_Widget');
        }
    }
    
    /**
     * Ajouter des liens d'action
     */
    public function add_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=rmcu-settings'),
            __('Settings', 'rmcu')
        );
        
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    /**
     * Ajouter des meta
     */
    public function add_row_meta($links, $file) {
        if (plugin_basename(RMCU_PLUGIN_FILE) === $file) {
            $links[] = sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://docs.example.com/rmcu',
                __('Documentation', 'rmcu')
            );
            
            $links[] = sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://support.example.com',
                __('Support', 'rmcu')
            );
        }
        
        return $links;
    }
    
    /**
     * Activation du plugin
     */
    public function activate() {
        $this->logger->info('Plugin activated');
        
        // Créer les tables
        if (isset($this->modules['database'])) {
            $this->modules['database']->create_tables();
        }
        
        // Créer les options par défaut
        $this->create_default_options();
        
        // Planifier les tâches cron
        if (isset($this->modules['cron'])) {
            $this->modules['cron']->schedule_events();
        }
        
        // Marquer pour flush rewrite rules
        update_option('rmcu_flush_rewrite_rules', true);
        
        // Hook d'activation
        do_action('rmcu_activated', $this);
    }
    
    /**
     * Désactivation du plugin
     */
    public function deactivate() {
        $this->logger->info('Plugin deactivated');
        
        // Supprimer les tâches cron
        if (isset($this->modules['cron'])) {
            $this->modules['cron']->unschedule_events();
        }
        
        // Nettoyer les transients
        $this->clean_transients();
        
        // Hook de désactivation
        do_action('rmcu_deactivated', $this);
    }
    
    /**
     * Créer les options par défaut
     */
    private function create_default_options() {
        $defaults = [
            'enable_plugin' => 1,
            'enable_video' => 1,
            'enable_audio' => 1,
            'enable_screen' => 1,
            'max_duration' => 300,
            'video_quality' => 'high',
            'auto_send_n8n' => 1,
            'retention_days' => 30,
            'allowed_roles' => ['administrator'],
            'enable_debug' => RMCU_DEBUG
        ];
        
        $existing = get_option('rmcu_settings', []);
        $merged = array_merge($defaults, $existing);
        
        update_option('rmcu_settings', $merged);
    }
    
    /**
     * Vérifier les mises à jour
     */
    private function check_updates() {
        $current_version = get_option('rmcu_version', '0.0.0');
        
        if (version_compare($current_version, RMCU_VERSION, '<')) {
            $this->run_updates($current_version);
            update_option('rmcu_version', RMCU_VERSION);
        }
    }
    
    /**
     * Exécuter les mises à jour
     */
    private function run_updates($from_version) {
        $this->logger->info('Running updates', [
            'from' => $from_version,
            'to' => RMCU_VERSION
        ]);
        
        // Migrations spécifiques par version
        if (version_compare($from_version, '2.0.0', '<')) {
            $this->update_to_2_0_0();
        }
    }
    
    /**
     * Mise à jour vers 2.0.0
     */
    private function update_to_2_0_0() {
        // Migration des anciennes données si nécessaire
        if (isset($this->modules['database'])) {
            $this->modules['database']->migrate_from_v1();
        }
    }
    
    /**
     * Nettoyer les transients
     */
    private function clean_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_rmcu_%' 
             OR option_name LIKE '_transient_timeout_rmcu_%'"
        );
    }
    
    /**
     * Obtenir les settings publics
     */
    private function get_public_settings() {
        $settings = get_option('rmcu_settings', []);
        
        // Ne retourner que les settings nécessaires côté public
        return [
            'enable_video' => isset($settings['enable_video']) ? $settings['enable_video'] : true,
            'enable_audio' => isset($settings['enable_audio']) ? $settings['enable_audio'] : true,
            'enable_screen' => isset($settings['enable_screen']) ? $settings['enable_screen'] : true,
            'max_duration' => isset($settings['max_duration']) ? $settings['max_duration'] : 300,
            'video_quality' => isset($settings['video_quality']) ? $settings['video_quality'] : 'high'
        ];
    }
    
    /**
     * Obtenir un service du container
     */
    public function get($service) {
        return isset($this->container[$service]) ? $this->container[$service] : null;
    }
    
    /**
     * Ajouter un service au container
     */
    public function set($service, $value) {
        $this->container[$service] = $value;
    }
    
    /**
     * Vérifier si un module est chargé
     */
    public function has_module($module) {
        return isset($this->modules[$module]);
    }
    
    /**
     * Obtenir un module
     */
    public function get_module($module) {
        return isset($this->modules[$module]) ? $this->modules[$module] : null;
    }
    
    /**
     * Obtenir la version
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Obtenir le logger
     */
    public function get_logger() {
        return $this->logger;
    }
}

// Fonction helper globale
if (!function_exists('rmcu')) {
    /**
     * Obtenir l'instance du plugin
     */
    function rmcu() {
        return RMCU_Core::get_instance();
    }
}