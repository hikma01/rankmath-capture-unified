<?php
/**
 * Plugin Name: RankMath Content & Media Capture Unified
 * Plugin URI: https://your-website.com/rmcu
 * Description: Capture, analyze, and optimize your WordPress content with powerful media tools and RankMath SEO integration.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rmcu
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * 
 * @package RMCU
 * @author Your Name
 * @copyright 2024 Your Company
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Définir les constantes du plugin
 */
define('RMCU_VERSION', '1.0.0');
define('RMCU_PLUGIN_FILE', __FILE__);
define('RMCU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RMCU_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RMCU_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('RMCU_DB_VERSION', '1.0.0');
define('RMCU_MINIMUM_WP_VERSION', '5.0');
define('RMCU_MINIMUM_PHP_VERSION', '7.4');

/**
 * Vérifier les prérequis
 */
function rmcu_check_requirements() {
    $errors = [];

    // Vérifier la version PHP
    if (version_compare(PHP_VERSION, RMCU_MINIMUM_PHP_VERSION, '<')) {
        $errors[] = sprintf(
            __('RMCU requires PHP version %s or higher. Your current version is %s.', 'rmcu'),
            RMCU_MINIMUM_PHP_VERSION,
            PHP_VERSION
        );
    }

    // Vérifier la version WordPress
    if (version_compare(get_bloginfo('version'), RMCU_MINIMUM_WP_VERSION, '<')) {
        $errors[] = sprintf(
            __('RMCU requires WordPress version %s or higher. Your current version is %s.', 'rmcu'),
            RMCU_MINIMUM_WP_VERSION,
            get_bloginfo('version')
        );
    }

    // Vérifier HTTPS (recommandé mais pas obligatoire pour l'activation)
    if (!is_ssl() && !defined('RMCU_ALLOW_HTTP')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p>';
            _e('RMCU: HTTPS is required for media capture features to work properly.', 'rmcu');
            echo '</p></div>';
        });
    }

    return $errors;
}

/**
 * Autoloader pour les classes du plugin
 */
spl_autoload_register(function ($class) {
    // Projet-specific namespace prefix
    $prefix = 'RMCU\\';

    // Base directory for the namespace prefix
    $base_dir = RMCU_PLUGIN_DIR . 'includes/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    // Replace underscores with hyphens in the filename
    $file_parts = explode('\\', $relative_class);
    $file_name = 'class-' . strtolower(str_replace('_', '-', array_pop($file_parts))) . '.php';
    
    // Construct the full path
    $path = $base_dir;
    if (!empty($file_parts)) {
        $path .= strtolower(implode('/', $file_parts)) . '/';
    }
    
    $file = $path . $file_name;

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Activation du plugin
 */
function rmcu_activate() {
    // Vérifier les prérequis
    $errors = rmcu_check_requirements();
    if (!empty($errors)) {
        deactivate_plugins(RMCU_PLUGIN_BASENAME);
        wp_die(
            implode('<br>', $errors),
            __('Plugin Activation Error', 'rmcu'),
            ['back_link' => true]
        );
    }

    // Créer les tables de base de données
    require_once RMCU_PLUGIN_DIR . 'includes/core/class-rmcu-database.php';
    $database = new RMCU\Core\RMCU_Database();
    $database->create_tables();

    // Créer les rôles et capacités
    rmcu_create_roles();

    // Créer les dossiers nécessaires
    rmcu_create_directories();

    // Définir les options par défaut
    rmcu_set_default_options();

    // Programmer les tâches cron
    rmcu_schedule_cron_jobs();

    // Flush rewrite rules pour les endpoints API
    flush_rewrite_rules();

    // Marquer l'activation
    update_option('rmcu_activated', time());
    update_option('rmcu_version', RMCU_VERSION);
}
register_activation_hook(__FILE__, 'rmcu_activate');

/**
 * Désactivation du plugin
 */
function rmcu_deactivate() {
    // Nettoyer les tâches cron
    wp_clear_scheduled_hook('rmcu_daily_cleanup');
    wp_clear_scheduled_hook('rmcu_hourly_stats');
    wp_clear_scheduled_hook('rmcu_weekly_report');

    // Flush rewrite rules
    flush_rewrite_rules();

    // Log la désactivation
    if (class_exists('RMCU\RMCU_Logger')) {
        $logger = new RMCU\RMCU_Logger();
        $logger->info('Plugin deactivated');
    }

    // Marquer la désactivation
    update_option('rmcu_deactivated', time());
}
register_deactivation_hook(__FILE__, 'rmcu_deactivate');

/**
 * Créer les rôles et capacités
 */
function rmcu_create_roles() {
    // Obtenir le rôle administrateur
    $admin = get_role('administrator');
    if ($admin) {
        // Ajouter toutes les capacités RMCU à l'admin
        $admin->add_cap('rmcu_manage_settings');
        $admin->add_cap('rmcu_view_captures');
        $admin->add_cap('rmcu_create_captures');
        $admin->add_cap('rmcu_edit_captures');
        $admin->add_cap('rmcu_delete_captures');
        $admin->add_cap('rmcu_export_data');
        $admin->add_cap('rmcu_view_analytics');
    }

    // Ajouter des capacités aux éditeurs
    $editor = get_role('editor');
    if ($editor) {
        $editor->add_cap('rmcu_view_captures');
        $editor->add_cap('rmcu_create_captures');
        $editor->add_cap('rmcu_edit_captures');
    }

    // Ajouter des capacités aux auteurs
    $author = get_role('author');
    if ($author) {
        $author->add_cap('rmcu_create_captures');
        $author->add_cap('rmcu_view_captures');
    }
}

/**
 * Créer les dossiers nécessaires
 */
function rmcu_create_directories() {
    $upload_dir = wp_upload_dir();
    $directories = [
        $upload_dir['basedir'] . '/rmcu',
        $upload_dir['basedir'] . '/rmcu/captures',
        $upload_dir['basedir'] . '/rmcu/exports',
        $upload_dir['basedir'] . '/rmcu/temp',
        $upload_dir['basedir'] . '/rmcu-logs',
        RMCU_PLUGIN_DIR . 'cache'
    ];

    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
            
            // Créer un fichier .htaccess pour protéger les logs
            if (strpos($dir, 'logs') !== false) {
                file_put_contents($dir . '/.htaccess', 'Deny from all');
            }
            
            // Créer un index.php vide pour la sécurité
            file_put_contents($dir . '/index.php', '<?php // Silence is golden');
        }
    }
}

/**
 * Définir les options par défaut
 */
function rmcu_set_default_options() {
    $defaults = [
        'rmcu_settings' => [
            'enable_plugin' => true,
            'enable_capture' => true,
            'auto_save' => false,
            'compression_level' => 80,
            'max_file_size' => 10485760, // 10MB
            'allowed_capture_types' => ['screenshot', 'video', 'audio', 'screen'],
            'storage_location' => 'uploads',
            'enable_rankmath_integration' => true,
            'enable_widget' => true,
            'widget_position' => 'bottom-right',
            'enable_public_features' => true,
            'allow_guest_capture' => false,
            'enable_notifications' => false,
            'debug_mode' => false,
            'cache_duration' => 3600,
            'api_rate_limit' => 100
        ],
        'rmcu_db_version' => RMCU_DB_VERSION,
        'rmcu_first_install' => current_time('mysql'),
        'rmcu_stats' => [
            'total_captures' => 0,
            'total_size' => 0
        ]
    ];

    foreach ($defaults as $option_name => $option_value) {
        if (get_option($option_name) === false) {
            add_option($option_name, $option_value);
        }
    }
}

/**
 * Programmer les tâches cron
 */
function rmcu_schedule_cron_jobs() {
    // Nettoyage quotidien
    if (!wp_next_scheduled('rmcu_daily_cleanup')) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'rmcu_daily_cleanup');
    }

    // Stats horaires
    if (!wp_next_scheduled('rmcu_hourly_stats')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'rmcu_hourly_stats');
    }

    // Rapport hebdomadaire
    if (!wp_next_scheduled('rmcu_weekly_report')) {
        wp_schedule_event(time() + WEEK_IN_SECONDS, 'weekly', 'rmcu_weekly_report');
    }
}

/**
 * Initialisation du plugin
 */
function rmcu_init() {
    // Vérifier les prérequis
    $errors = rmcu_check_requirements();
    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors) {
            echo '<div class="notice notice-error"><p>';
            echo implode('</p><p>', $errors);
            echo '</p></div>';
        });
        return;
    }

    // Charger les textdomains pour la traduction
    load_plugin_textdomain('rmcu', false, dirname(RMCU_PLUGIN_BASENAME) . '/languages');

    // Charger les classes principales
    require_once RMCU_PLUGIN_DIR . 'includes/core/class-rmcu-core.php';
    require_once RMCU_PLUGIN_DIR . 'includes/class-rmcu-logger.php';

    // Initialiser le core
    $core = RMCU\Core\RMCU_Core::get_instance();
    $core->init();

    // Charger l'admin si on est dans l'administration
    if (is_admin()) {
        require_once RMCU_PLUGIN_DIR . 'admin/class-rmcu-admin.php';
        new RMCU\Admin\RMCU_Admin();
    }

    // Charger le frontend
    if (!is_admin()) {
        require_once RMCU_PLUGIN_DIR . 'public/class-rmcu-public.php';
        new RMCU\Pub\RMCU_Public();
    }

    // Enregistrer les hooks pour les tâches cron
    add_action('rmcu_daily_cleanup', 'rmcu_perform_daily_cleanup');
    add_action('rmcu_hourly_stats', 'rmcu_calculate_hourly_stats');
    add_action('rmcu_weekly_report', 'rmcu_send_weekly_report');

    // Hook pour la mise à jour
    add_action('plugins_loaded', 'rmcu_check_version');
}
add_action('init', 'rmcu_init');

/**
 * Vérifier et gérer les mises à jour de version
 */
function rmcu_check_version() {
    $current_version = get_option('rmcu_version', '0');
    
    if (version_compare($current_version, RMCU_VERSION, '<')) {
        // Exécuter les migrations si nécessaire
        rmcu_upgrade_database();
        
        // Mettre à jour la version
        update_option('rmcu_version', RMCU_VERSION);
        
        // Log la mise à jour
        if (class_exists('RMCU\RMCU_Logger')) {
            $logger = new RMCU\RMCU_Logger();
            $logger->info('Plugin updated to version ' . RMCU_VERSION);
        }
    }
}

/**
 * Mettre à jour la base de données si nécessaire
 */
function rmcu_upgrade_database() {
    $current_db_version = get_option('rmcu_db_version', '0');
    
    if (version_compare($current_db_version, RMCU_DB_VERSION, '<')) {
        require_once RMCU_PLUGIN_DIR . 'includes/core/class-rmcu-database.php';
        $database = new RMCU\Core\RMCU_Database();
        $database->upgrade_tables();
        
        update_option('rmcu_db_version', RMCU_DB_VERSION);
    }
}

/**
 * Tâches cron : Nettoyage quotidien
 */
function rmcu_perform_daily_cleanup() {
    if (class_exists('RMCU\Core\RMCU_Database')) {
        $database = new RMCU\Core\RMCU_Database();
        
        // Supprimer les captures temporaires de plus de 7 jours
        $database->delete_old_temp_captures(7);
        
        // Nettoyer les logs de plus de 30 jours
        $database->clean_old_logs(30);
        
        // Optimiser les tables
        $database->optimize_tables();
    }
    
    // Nettoyer les fichiers temporaires
    $temp_dir = wp_upload_dir()['basedir'] . '/rmcu/temp';
    if (is_dir($temp_dir)) {
        $files = glob($temp_dir . '/*');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) > 86400)) { // 24 heures
                unlink($file);
            }
        }
    }
}

/**
 * Tâches cron : Calculer les statistiques horaires
 */
function rmcu_calculate_hourly_stats() {
    if (class_exists('RMCU\Core\RMCU_Database')) {
        $database = new RMCU\Core\RMCU_Database();
        
        // Calculer les stats
        $stats = [
            'total_captures' => $database->get_total_captures(),
            'total_size' => $database->get_total_storage_size(),
            'hourly_captures' => $database->get_captures_last_hour(),
            'average_seo_score' => $database->get_average_seo_score()
        ];
        
        // Sauvegarder les stats
        update_option('rmcu_hourly_stats_' . date('Y-m-d-H'), $stats);
        
        // Mettre à jour les stats globales
        update_option('rmcu_stats', [
            'total_captures' => $stats['total_captures'],
            'total_size' => $stats['total_size'],
            'last_updated' => current_time('mysql')
        ]);
    }
}

/**
 * Tâches cron : Envoyer le rapport hebdomadaire
 */
function rmcu_send_weekly_report() {
    $settings = get_option('rmcu_settings', []);
    
    if (empty($settings['enable_notifications']) || empty($settings['notification_email'])) {
        return;
    }
    
    if (class_exists('RMCU\Core\RMCU_Database')) {
        $database = new RMCU\Core\RMCU_Database();
        
        // Collecter les données pour le rapport
        $report_data = [
            'total_captures_week' => $database->get_captures_last_week(),
            'top_users' => $database->get_top_users_week(),
            'popular_types' => $database->get_popular_capture_types(),
            'average_seo_score' => $database->get_average_seo_score_week(),
            'storage_used' => size_format($database->get_total_storage_size())
        ];
        
        // Préparer l'email
        $to = $settings['notification_email'];
        $subject = sprintf(__('[RMCU] Weekly Report - %s', 'rmcu'), get_bloginfo('name'));
        
        ob_start();
        include RMCU_PLUGIN_DIR . 'templates/email-weekly-report.php';
        $message = ob_get_clean();
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        // Envoyer l'email
        wp_mail($to, $subject, $message, $headers);
    }
}

/**
 * Ajouter les liens dans la page des plugins
 */
function rmcu_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=rmcu-settings') . '">' . __('Settings', 'rmcu') . '</a>';
    $docs_link = '<a href="https://docs.rmcu-plugin.com" target="_blank">' . __('Docs', 'rmcu') . '</a>';
    
    array_unshift($links, $settings_link);
    $links[] = $docs_link;
    
    return $links;
}
add_filter('plugin_action_links_' . RMCU_PLUGIN_BASENAME, 'rmcu_plugin_action_links');

/**
 * Ajouter des meta liens dans la page des plugins
 */
function rmcu_plugin_row_meta($links, $file) {
    if ($file == RMCU_PLUGIN_BASENAME) {
        $row_meta = [
            'docs' => '<a href="https://docs.rmcu-plugin.com" target="_blank">' . __('Documentation', 'rmcu') . '</a>',
            'support' => '<a href="https://support.rmcu-plugin.com" target="_blank">' . __('Support', 'rmcu') . '</a>',
            'pro' => '<a href="https://rmcu-plugin.com/pro" target="_blank" style="color: #39a94e; font-weight: bold;">' . __('Go Pro', 'rmcu') . '</a>'
        ];
        
        return array_merge($links, $row_meta);
    }
    
    return $links;
}
add_filter('plugin_row_meta', 'rmcu_plugin_row_meta', 10, 2);

/**
 * Fonction helper pour obtenir l'instance du plugin
 */
function rmcu() {
    return RMCU\Core\RMCU_Core::get_instance();
}

/**
 * Vérifier si RankMath est actif
 */
function rmcu_is_rankmath_active() {
    return defined('RANK_MATH_VERSION') || class_exists('RankMath');
}

/**
 * Debug helper
 */
if (!function_exists('rmcu_debug')) {
    function rmcu_debug($data, $label = '') {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('RMCU_DEBUG') && RMCU_DEBUG) {
            error_log('[RMCU Debug] ' . $label . ': ' . print_r($data, true));
        }
    }
}