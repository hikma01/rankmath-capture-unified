<?php
/**
 * RMCU Utils
 * Fonctions utilitaires pour le plugin
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe utilitaire avec méthodes statiques
 */
class RMCU_Utils {
    
    /**
     * Obtenir la version du plugin
     */
    public static function get_plugin_version() {
        return defined('RMCU_VERSION') ? RMCU_VERSION : '2.0.0';
    }
    
    /**
     * Obtenir l'URL du plugin
     */
    public static function get_plugin_url($path = '') {
        $url = defined('RMCU_PLUGIN_URL') ? RMCU_PLUGIN_URL : plugin_dir_url(__FILE__);
        return $url . ltrim($path, '/');
    }
    
    /**
     * Obtenir le chemin du plugin
     */
    public static function get_plugin_path($path = '') {
        $dir = defined('RMCU_PLUGIN_DIR') ? RMCU_PLUGIN_DIR : plugin_dir_path(__FILE__);
        return $dir . ltrim($path, '/');
    }
    
    /**
     * Vérifier si on est en mode debug
     */
    public static function is_debug_mode() {
        $settings = get_option('rmcu_settings', []);
        return (defined('WP_DEBUG') && WP_DEBUG) || !empty($settings['debug_mode']);
    }
    
    /**
     * Logger un message si en mode debug
     */
    public static function debug_log($message, $data = null) {
        if (!self::is_debug_mode()) {
            return;
        }
        
        if (is_array($message) || is_object($message)) {
            error_log('[RMCU Debug] ' . print_r($message, true));
        } else {
            error_log('[RMCU Debug] ' . $message);
        }
        
        if ($data !== null) {
            error_log('[RMCU Debug Data] ' . print_r($data, true));
        }
    }
    
    /**
     * Formater une date
     */
    public static function format_date($date, $format = null) {
        if (empty($date)) {
            return '';
        }
        
        if ($format === null) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }
        
        if (!is_numeric($date)) {
            $date = strtotime($date);
        }
        
        return date_i18n($format, $date);
    }
    
    /**
     * Formater une durée relative
     */
    public static function human_time_diff($from, $to = null) {
        if ($to === null) {
            $to = current_time('timestamp');
        }
        
        if (!is_numeric($from)) {
            $from = strtotime($from);
        }
        
        if (!is_numeric($to)) {
            $to = strtotime($to);
        }
        
        return human_time_diff($from, $to);
    }
    
    /**
     * Obtenir l'adresse IP du client
     */
    public static function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Prendre la première IP si plusieurs
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                $ip = trim($ip);
                
                // Valider l'IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Générer un UUID v4
     */
    public static function generate_uuid() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Tronquer un texte
     */
    public static function truncate($text, $length = 100, $suffix = '...') {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
    }
    
    /**
     * Nettoyer et formater un titre
     */
    public static function clean_title($title) {
        $title = strip_tags($title);
        $title = trim($title);
        $title = preg_replace('/\s+/', ' ', $title);
        return $title;
    }
    
    /**
     * Extraire le texte d'un contenu HTML
     */
    public static function strip_html($html, $preserve_breaks = true) {
        if ($preserve_breaks) {
            // Remplacer les balises de rupture par des sauts de ligne
            $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
            $html = preg_replace('/<\/p>/i', "\n\n", $html);
            $html = preg_replace('/<\/h[1-6]>/i', "\n\n", $html);
        }
        
        // Supprimer les scripts et styles
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Supprimer les balises HTML
        $text = strip_tags($html);
        
        // Nettoyer les espaces multiples
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Calculer le temps de lecture
     */
    public static function reading_time($content, $wpm = 200) {
        $word_count = str_word_count(self::strip_html($content));
        $minutes = ceil($word_count / $wpm);
        
        if ($minutes < 1) {
            return __('Less than 1 minute', 'rankmath-capture-unified');
        } elseif ($minutes == 1) {
            return __('1 minute', 'rankmath-capture-unified');
        } else {
            return sprintf(__('%d minutes', 'rankmath-capture-unified'), $minutes);
        }
    }
    
    /**
     * Compter les mots
     */
    public static function word_count($content) {
        $text = self::strip_html($content);
        return str_word_count($text);
    }
    
    /**
     * Formater une taille de fichier
     */
    public static function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Vérifier si une URL est valide
     */
    public static function is_valid_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Obtenir le domaine d'une URL
     */
    public static function get_domain($url) {
        $parts = parse_url($url);
        return isset($parts['host']) ? $parts['host'] : '';
    }
    
    /**
     * Vérifier si une URL est interne
     */
    public static function is_internal_url($url) {
        $domain = self::get_domain($url);
        $site_domain = self::get_domain(home_url());
        
        return $domain === $site_domain || empty($domain);
    }
    
    /**
     * Générer une URL avec des paramètres
     */
    public static function build_url($base, $params = []) {
        if (empty($params)) {
            return $base;
        }
        
        $separator = strpos($base, '?') !== false ? '&' : '?';
        return $base . $separator . http_build_query($params);
    }
    
    /**
     * Obtenir les types de post supportés
     */
    public static function get_supported_post_types() {
        $default = ['post', 'page'];
        return apply_filters('rmcu_supported_post_types', $default);
    }
    
    /**
     * Vérifier si un type de post est supporté
     */
    public static function is_supported_post_type($post_type) {
        $supported = self::get_supported_post_types();
        return in_array($post_type, $supported);
    }
    
    /**
     * Obtenir le score SEO d'un post
     */
    public static function get_seo_score($post_id) {
        // RankMath
        $score = get_post_meta($post_id, 'rank_math_seo_score', true);
        
        // Yoast fallback
        if (!$score && defined('WPSEO_VERSION')) {
            $score = get_post_meta($post_id, '_yoast_wpseo_content_score', true);
        }
        
        return intval($score);
    }
    
    /**
     * Obtenir le mot-clé focus d'un post
     */
    public static function get_focus_keyword($post_id) {
        // RankMath
        $keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        
        // Yoast fallback
        if (!$keyword && defined('WPSEO_VERSION')) {
            $keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        }
        
        return $keyword;
    }
    
    /**
     * Créer un slug à partir d'un texte
     */
    public static function create_slug($text) {
        $slug = sanitize_title($text);
        $slug = str_replace('_', '-', $slug);
        return $slug;
    }
    
    /**
     * Encoder en base64 URL-safe
     */
    public static function base64_url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Décoder depuis base64 URL-safe
     */
    public static function base64_url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    /**
     * Générer un token sécurisé
     */
    public static function generate_token($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        }
        
        return wp_generate_password($length, false, false);
    }
    
    /**
     * Hacher un mot de passe ou un token
     */
    public static function hash_token($token) {
        return wp_hash($token);
    }
    
    /**
     * Vérifier un token hashé
     */
    public static function verify_token($token, $hash) {
        return wp_hash($token) === $hash;
    }
    
    /**
     * Obtenir les capabilities requises
     */
    public static function get_required_capability($action = 'manage') {
        $capabilities = [
            'manage' => 'manage_options',
            'edit' => 'edit_posts',
            'view' => 'read'
        ];
        
        return isset($capabilities[$action]) ? $capabilities[$action] : 'manage_options';
    }
    
    /**
     * Vérifier si l'utilisateur peut effectuer une action
     */
    public static function user_can($action = 'manage') {
        $capability = self::get_required_capability($action);
        return current_user_can($capability);
    }
    
    /**
     * Obtenir l'URL d'administration du plugin
     */
    public static function get_admin_url($page = 'settings', $params = []) {
        $base = admin_url('admin.php?page=rmcu-' . $page);
        return self::build_url($base, $params);
    }
    
    /**
     * Rediriger avec un message
     */
    public static function redirect_with_message($url, $message, $type = 'success') {
        $url = add_query_arg([
            'rmcu_message' => urlencode($message),
            'rmcu_type' => $type
        ], $url);
        
        wp_safe_redirect($url);
        exit;
    }
    
    /**
     * Afficher un message admin
     */
    public static function show_admin_notice($message, $type = 'info') {
        $class = 'notice notice-' . $type;
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
    
    /**
     * Obtenir les données de performance
     */
    public static function get_performance_data() {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'queries' => get_num_queries()
        ];
    }
    
    /**
     * Mesurer le temps d'exécution
     */
    public static function benchmark($callback, $label = '') {
        $start = microtime(true);
        $result = call_user_func($callback);
        $duration = microtime(true) - $start;
        
        if ($label && self::is_debug_mode()) {
            self::debug_log(sprintf('Benchmark [%s]: %f seconds', $label, $duration));
        }
        
        return [
            'result' => $result,
            'duration' => $duration
        ];
    }
    
    /**
     * Obtenir les infos système
     */
    public static function get_system_info() {
        global $wp_version, $wpdb;
        
        return [
            'wordpress_version' => $wp_version,
            'php_version' => phpversion(),
            'mysql_version' => $wpdb->db_version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'operating_system' => PHP_OS,
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'timezone' => date_default_timezone_get(),
            'debug_mode' => WP_DEBUG,
            'multisite' => is_multisite(),
            'language' => get_locale()
        ];
    }
}