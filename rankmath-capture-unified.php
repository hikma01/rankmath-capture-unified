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
 * Fonction utilitaire pour charger les fichiers avec gestion d'erreur
 */
function rmcu_load_file($file_path) {
    // Essayer plusieurs variantes du nom de fichier
    $variations = [
        $file_path,
        str_replace('class-rmcu-', 'rmcu-', $file_path),
        str_replace('rmcu-', 'class-rmcu-', $file_path),
    ];
    
    foreach ($variations as $path) {
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    // Si aucun fichier trouvé, logger l'erreur mais ne pas faire crasher
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('RMCU: Could not load file: ' . $file_path);
    }
    
    return false;
}

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
    // Essayer de charger le fichier avec différents noms possibles
    $database_loaded = false;
    
    // Essayer d'abord avec le nom attendu
    if (file_exists(RMCU_PLUGIN_DIR . 'includes/core/rmcu-database.php')) {
        require_once RMCU_PLUGIN_DIR . 'includes/core/rmcu-database.php';
        $database_loaded = true;
    } elseif (file_exists(RMCU_PLUGIN_DIR . 'includes/core/class-rmcu-database.php')) {
        require_once RMCU_PLUGIN_DIR . 'includes/core/class-rmcu-database.php';
        $database_loaded = true;
    }
    
    if ($database_loaded && class_exists('RMCU_Database')) {
        $database = new RMCU_Database();
        $database->create_tables();
    }

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

    return $errors;
}

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

    // Charger les classes principales avec gestion d'erreur
    $core_files = [
        'includes/core/rmcu-core-class.php',
        'includes/core/class-rmcu-core.php',
        'includes/utils/rmcu-logger.php',
        'includes/class-rmcu-logger.php'
    ];
    
    foreach ($core_files as $file) {
        $file_path = RMCU_PLUGIN_DIR . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
            break; // Stop après avoir trouvé le premier fichier existant
        }
    }

    // Initialiser le core si la classe existe
    if (class_exists('RMCU_Core')) {
        $core = RMCU_Core::get_instance();
        $core->init();
    }

    // Charger l'admin si on est dans l'administration
    if (is_admin()) {
        $admin_file = RMCU_PLUGIN_DIR . 'admin/class-rmcu-admin.php';
        if (file_exists($admin_file)) {
            require_once $admin_file;
            if (class_exists('RMCU_Admin')) {
                new RMCU_Admin();
            }
        }
    }

    // Charger le frontend
    if (!is_admin()) {
        $public_file = RMCU_PLUGIN_DIR . 'public/class-rmcu-public.php';
        if (file_exists($public_file)) {
            require_once $public_file;
            if (class_exists('RMCU_Public')) {
                new RMCU_Public();
            }
        }
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
    }
}

/**
 * Mettre à jour la base de données si nécessaire
 */
function rmcu_upgrade_database() {
    $current_db_version = get_option('rmcu_db_version', '0');
    
    if (version_compare($current_db_version, RMCU_DB_VERSION, '<')) {
        // Charger la classe database si elle n'est pas déjà chargée
        if (!class_exists('RMCU_Database')) {
            rmcu_load_file(RMCU_PLUGIN_DIR . 'includes/core/rmcu-database.php');
        }
        
        if (class_exists('RMCU_Database')) {
            $database = new RMCU_Database();
            if (method_exists($database, 'upgrade_tables')) {
                $database->upgrade_tables();
            } else {
                // Si upgrade_tables n'existe pas, utiliser create_tables
                $database->create_tables();
            }
        }
        
        update_option('rmcu_db_version', RMCU_DB_VERSION);
    }
}

/**
 * Tâches cron : Nettoyage quotidien
 */
function rmcu_perform_daily_cleanup() {
    if (!class_exists('RMCU_Database')) {
        rmcu_load_file(RMCU_PLUGIN_DIR . 'includes/core/rmcu-database.php');
    }
    
    if (class_exists('RMCU_Database')) {
        $database = new RMCU_Database();
        
        // Nettoyer les anciennes données
        if (method_exists($database, 'cleanup_old_data')) {
            $database->cleanup_old_data(30);
        }
        
        // Optimiser les tables
        if (method_exists($database, 'optimize_tables')) {
            $database->optimize_tables();
        }
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
    // Pour le moment, juste sauvegarder un timestamp
    update_option('rmcu_last_stats_calculation', current_time('mysql'));
}

/**
 * Tâches cron : Envoyer le rapport hebdomadaire
 */
function rmcu_send_weekly_report() {
    $settings = get_option('rmcu_settings', []);
    
    if (empty($settings['enable_notifications'])) {
        return;
    }
    
    // Pour le moment, juste logger l'événement
    update_option('rmcu_last_weekly_report', current_time('mysql'));
}

/**
 * Ajouter les liens dans la page des plugins
 */
function rmcu_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=rmcu-settings') . '">' . __('Settings', 'rmcu') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . RMCU_PLUGIN_BASENAME, 'rmcu_plugin_action_links');

/**
 * Fonction helper pour obtenir l'instance du plugin
 */
function rmcu() {
    if (class_exists('RMCU_Core')) {
        return RMCU_Core::get_instance();
    }
    return null;
}

/**
 * Vérifier si RankMath est actif
 */
function rmcu_is_rankmath_active() {
    return defined('RANK_MATH_VERSION') || class_exists('RankMath');
}
