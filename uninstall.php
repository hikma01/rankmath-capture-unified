<?php
/**
 * RMCU Uninstall Script
 *
 * Ce fichier est exécuté automatiquement lorsque le plugin est supprimé.
 * Il nettoie complètement la base de données et les fichiers créés par le plugin.
 *
 * @package RMCU
 * @since 1.0.0
 */

// Si le fichier n'est pas appelé par WordPress, arrêter l'exécution
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Charger les constantes du plugin si nécessaire
if (!defined('RMCU_PLUGIN_DIR')) {
    define('RMCU_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

/**
 * Classe de désinstallation RMCU
 */
class RMCU_Uninstall {
    
    /**
     * Exécuter la désinstallation complète
     */
    public static function uninstall() {
        // Vérifier les permissions
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Obtenir les paramètres pour vérifier si on doit tout supprimer
        $settings = get_option('rmcu_settings', []);
        $delete_data = isset($settings['delete_on_uninstall']) ? $settings['delete_on_uninstall'] : false;
        
        // Si l'option de suppression complète n'est pas activée, conserver les données
        if (!$delete_data) {
            // Marquer seulement la désinstallation sans supprimer les données
            update_option('rmcu_uninstalled', current_time('mysql'));
            return;
        }
        
        // Procéder à la suppression complète
        self::delete_database_tables();
        self::delete_options();
        self::delete_user_meta();
        self::delete_transients();
        self::delete_files();
        self::remove_capabilities();
        self::clear_scheduled_hooks();
        self::cleanup_cache();
        
        // Pour le multisite
        if (is_multisite()) {
            self::multisite_cleanup();
        }
    }
    
    /**
     * Supprimer les tables de la base de données
     */
    private static function delete_database_tables() {
        global $wpdb;
        
        // Liste des tables à supprimer
        $tables = [
            $wpdb->prefix . 'rmcu_captures',
            $wpdb->prefix . 'rmcu_analytics',
            $wpdb->prefix . 'rmcu_logs',
            $wpdb->prefix . 'rmcu_webhooks',
            $wpdb->prefix . 'rmcu_exports',
            $wpdb->prefix . 'rmcu_sessions'
        ];
        
        // Désactiver la vérification des clés étrangères
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        // Réactiver la vérification des clés étrangères
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        
        // Supprimer les meta de posts liées à RMCU
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'rmcu_%'");
        
        // Supprimer les meta de commentaires liées à RMCU
        $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE meta_key LIKE 'rmcu_%'");
        
        // Supprimer les termmeta liées à RMCU
        $wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE 'rmcu_%'");
    }
    
    /**
     * Supprimer toutes les options du plugin
     */
    private static function delete_options() {
        // Options principales
        $options = [
            'rmcu_settings',
            'rmcu_version',
            'rmcu_db_version',
            'rmcu_activated',
            'rmcu_deactivated',
            'rmcu_uninstalled',
            'rmcu_first_install',
            'rmcu_stats',
            'rmcu_hourly_stats',
            'rmcu_daily_stats',
            'rmcu_weekly_stats',
            'rmcu_monthly_stats',
            'rmcu_cache_version',
            'rmcu_api_keys',
            'rmcu_webhook_endpoints',
            'rmcu_export_settings',
            'rmcu_import_settings',
            'rmcu_widget_options',
            'rmcu_shortcode_defaults',
            'rmcu_custom_css',
            'rmcu_custom_js',
            'rmcu_license_key',
            'rmcu_license_status'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Supprimer toutes les options qui commencent par rmcu_
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'rmcu_%'");
        
        // Supprimer les options de widget
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'widget_rmcu_%'");
    }
    
    /**
     * Supprimer les métadonnées utilisateur
     */
    private static function delete_user_meta() {
        global $wpdb;
        
        // Métadonnées utilisateur à supprimer
        $user_meta_keys = [
            'rmcu_dismissed_notices',
            'rmcu_dashboard_widgets_order',
            'rmcu_preferences',
            'rmcu_last_capture',
            'rmcu_capture_count',
            'rmcu_api_token',
            'rmcu_tutorial_completed',
            'rmcu_export_history',
            'rmcu_favorite_settings'
        ];
        
        foreach ($user_meta_keys as $meta_key) {
            $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = '{$meta_key}'");
        }
        
        // Supprimer toutes les métadonnées qui commencent par rmcu_
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'rmcu_%'");
    }
    
    /**
     * Supprimer les transients
     */
    private static function delete_transients() {
        global $wpdb;
        
        // Supprimer tous les transients RMCU
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rmcu_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_rmcu_%'");
        
        // Pour les sites multisite
        if (is_multisite()) {
            $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_rmcu_%'");
            $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_rmcu_%'");
        }
    }
    
    /**
     * Supprimer les fichiers et dossiers créés par le plugin
     */
    private static function delete_files() {
        // Obtenir le dossier uploads
        $upload_dir = wp_upload_dir();
        
        // Dossiers à supprimer
        $directories = [
            $upload_dir['basedir'] . '/rmcu',
            $upload_dir['basedir'] . '/rmcu-logs',
            $upload_dir['basedir'] . '/rmcu-exports',
            $upload_dir['basedir'] . '/rmcu-temp',
            WP_CONTENT_DIR . '/cache/rmcu'
        ];
        
        foreach ($directories as $dir) {
            if (file_exists($dir) && is_dir($dir)) {
                self::delete_directory($dir);
            }
        }
        
        // Supprimer les fichiers de log individuels s'ils existent
        $log_files = glob(WP_CONTENT_DIR . '/rmcu-*.log');
        if ($log_files) {
            foreach ($log_files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Fonction récursive pour supprimer un dossier et son contenu
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Supprimer les capacités (capabilities) ajoutées aux rôles
     */
    private static function remove_capabilities() {
        // Liste des capacités RMCU
        $capabilities = [
            'rmcu_manage_settings',
            'rmcu_view_captures',
            'rmcu_create_captures',
            'rmcu_edit_captures',
            'rmcu_delete_captures',
            'rmcu_export_data',
            'rmcu_view_analytics',
            'rmcu_manage_webhooks',
            'rmcu_access_api',
            'rmcu_moderate_captures'
        ];
        
        // Obtenir tous les rôles
        $roles = wp_roles()->roles;
        
        foreach ($roles as $role_name => $role_info) {
            $role = get_role($role_name);
            
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Nettoyer les tâches cron programmées
     */
    private static function clear_scheduled_hooks() {
        // Liste des hooks cron RMCU
        $cron_hooks = [
            'rmcu_daily_cleanup',
            'rmcu_hourly_stats',
            'rmcu_weekly_report',
            'rmcu_monthly_backup',
            'rmcu_optimize_database',
            'rmcu_clear_temp_files',
            'rmcu_send_notifications',
            'rmcu_sync_rankmath',
            'rmcu_process_queue',
            'rmcu_check_updates'
        ];
        
        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
            
            // Nettoyer tous les événements de ce hook
            wp_clear_scheduled_hook($hook);
        }
    }
    
    /**
     * Nettoyer le cache
     */
    private static function cleanup_cache() {
        // Nettoyer le cache d'objets WordPress
        wp_cache_flush();
        
        // Nettoyer les caches de plugins populaires si présents
        
        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        
        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
        }
        
        // LiteSpeed Cache
        if (class_exists('LiteSpeed_Cache_API')) {
            LiteSpeed_Cache_API::purge_all();
        }
    }
    
    /**
     * Nettoyage spécifique pour multisite
     */
    private static function multisite_cleanup() {
        global $wpdb;
        
        // Obtenir tous les sites
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
        $original_blog_id = get_current_blog_id();
        
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            
            // Supprimer les options spécifiques au site
            self::delete_options();
            
            // Supprimer les transients spécifiques au site
            self::delete_transients();
            
            // Supprimer les tables spécifiques au site
            $tables = [
                $wpdb->prefix . 'rmcu_captures',
                $wpdb->prefix . 'rmcu_analytics',
                $wpdb->prefix . 'rmcu_logs'
            ];
            
            foreach ($tables as $table) {
                $wpdb->query("DROP TABLE IF EXISTS {$table}");
            }
            
            // Supprimer les fichiers spécifiques au site
            $upload_dir = wp_upload_dir();
            $site_dirs = [
                $upload_dir['basedir'] . '/rmcu',
                $upload_dir['basedir'] . '/rmcu-logs'
            ];
            
            foreach ($site_dirs as $dir) {
                if (file_exists($dir)) {
                    self::delete_directory($dir);
                }
            }
        }
        
        // Retourner au blog original
        switch_to_blog($original_blog_id);
        
        // Supprimer les métadonnées réseau
        $network_options = [
            'rmcu_network_settings',
            'rmcu_network_license',
            'rmcu_network_activated'
        ];
        
        foreach ($network_options as $option) {
            delete_site_option($option);
        }
    }
    
    /**
     * Créer une sauvegarde avant la suppression (optionnel)
     */
    private static function create_backup() {
        // Cette fonction peut être utilisée pour créer une sauvegarde
        // avant de supprimer toutes les données
        
        $backup_data = [
            'settings' => get_option('rmcu_settings'),
            'stats' => get_option('rmcu_stats'),
            'version' => get_option('rmcu_version'),
            'timestamp' => current_time('mysql')
        ];
        
        // Sauvegarder dans un fichier si nécessaire
        $backup_file = WP_CONTENT_DIR . '/rmcu-backup-' . date('Y-m-d-His') . '.json';
        file_put_contents($backup_file, json_encode($backup_data));
    }
}

// Exécuter la désinstallation
RMCU_Uninstall::uninstall();