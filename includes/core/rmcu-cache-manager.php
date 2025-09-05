<?php
/**
 * RMCU Cache Manager
 * Gestionnaire de cache pour optimiser les performances
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour gérer le cache
 */
class RMCU_Cache_Manager {
    
    /**
     * Préfixe des clés de cache
     */
    const CACHE_PREFIX = 'rmcu_';
    
    /**
     * Groupe de cache
     */
    const CACHE_GROUP = 'rmcu';
    
    /**
     * Durée par défaut du cache (en secondes)
     */
    const DEFAULT_EXPIRATION = 3600; // 1 heure
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Statistiques de cache
     */
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];
    
    /**
     * Configuration
     */
    private $config = [
        'enabled' => true,
        'expiration' => self::DEFAULT_EXPIRATION,
        'use_transients' => true,
        'use_object_cache' => true,
        'compress' => true,
        'max_size' => 1048576 // 1MB
    ];
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->logger = new RMCU_Logger('Cache_Manager');
        $this->load_config();
        $this->init_hooks();
    }
    
    /**
     * Charger la configuration
     */
    private function load_config() {
        $settings = get_option('rmcu_settings', []);
        
        if (isset($settings['cache_enabled'])) {
            $this->config['enabled'] = (bool) $settings['cache_enabled'];
        }
        
        if (isset($settings['cache_duration'])) {
            $this->config['expiration'] = intval($settings['cache_duration']);
        }
        
        // Détecter si un cache objet est disponible
        $this->config['use_object_cache'] = wp_using_ext_object_cache();
    }
    
    /**
     * Initialiser les hooks
     */
    private function init_hooks() {
        // Nettoyer le cache lors de certains événements
        add_action('save_post', [$this, 'clear_post_cache'], 10, 1);
        add_action('delete_post', [$this, 'clear_post_cache'], 10, 1);
        add_action('rmcu_optimization_completed', [$this, 'clear_optimization_cache'], 10, 1);
        
        // Cron pour nettoyer le cache expiré
        add_action('rmcu_cleanup_cache', [$this, 'cleanup_expired_cache']);
        
        if (!wp_next_scheduled('rmcu_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'rmcu_cleanup_cache');
        }
    }
    
    /**
     * Obtenir une valeur du cache
     */
    public function get($key, $default = null) {
        if (!$this->config['enabled']) {
            $this->stats['misses']++;
            return $default;
        }
        
        $cache_key = $this->build_cache_key($key);
        $value = false;
        
        // Essayer d'abord le cache objet si disponible
        if ($this->config['use_object_cache']) {
            $value = wp_cache_get($cache_key, self::CACHE_GROUP);
        }
        
        // Fallback sur les transients
        if ($value === false && $this->config['use_transients']) {
            $value = get_transient($cache_key);
        }
        
        if ($value === false) {
            $this->stats['misses']++;
            $this->logger->debug('Cache miss', ['key' => $key]);
            return $default;
        }
        
        // Décompresser si nécessaire
        if ($this->config['compress'] && is_string($value) && $this->is_compressed($value)) {
            $value = $this->decompress($value);
        }
        
        // Désérialiser si nécessaire
        if ($this->is_serialized($value)) {
            $value = unserialize($value);
        }
        
        $this->stats['hits']++;
        $this->logger->debug('Cache hit', ['key' => $key]);
        
        return $value;
    }
    
    /**
     * Définir une valeur dans le cache
     */
    public function set($key, $value, $expiration = null) {
        if (!$this->config['enabled']) {
            return false;
        }
        
        if ($expiration === null) {
            $expiration = $this->config['expiration'];
        }
        
        $cache_key = $this->build_cache_key($key);
        
        // Sérialiser si nécessaire
        if (is_array($value) || is_object($value)) {
            $value = serialize($value);
        }
        
        // Compresser si nécessaire et si la taille le justifie
        if ($this->config['compress'] && strlen($value) > 1024) {
            $compressed = $this->compress($value);
            if ($compressed && strlen($compressed) < strlen($value)) {
                $value = $compressed;
            }
        }
        
        // Vérifier la taille maximale
        if (strlen($value) > $this->config['max_size']) {
            $this->logger->warning('Cache value too large', [
                'key' => $key,
                'size' => strlen($value)
            ]);
            return false;
        }
        
        $success = false;
        
        // Utiliser le cache objet si disponible
        if ($this->config['use_object_cache']) {
            $success = wp_cache_set($cache_key, $value, self::CACHE_GROUP, $expiration);
        }
        
        // Fallback sur les transients
        if (!$success && $this->config['use_transients']) {
            $success = set_transient($cache_key, $value, $expiration);
        }
        
        if ($success) {
            $this->stats['sets']++;
            $this->logger->debug('Cache set', [
                'key' => $key,
                'expiration' => $expiration
            ]);
        }
        
        return $success;
    }
    
    /**
     * Supprimer une valeur du cache
     */
    public function delete($key) {
        $cache_key = $this->build_cache_key($key);
        $success = false;
        
        // Supprimer du cache objet
        if ($this->config['use_object_cache']) {
            $success = wp_cache_delete($cache_key, self::CACHE_GROUP);
        }
        
        // Supprimer des transients
        if ($this->config['use_transients']) {
            $success = delete_transient($cache_key) || $success;
        }
        
        if ($success) {
            $this->stats['deletes']++;
            $this->logger->debug('Cache deleted', ['key' => $key]);
        }
        
        return $success;
    }
    
    /**
     * Vider tout le cache
     */
    public function flush() {
        // Vider le cache objet pour notre groupe
        if ($this->config['use_object_cache']) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
        
        // Supprimer tous les transients RMCU
        if ($this->config['use_transients']) {
            global $wpdb;
            
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_" . self::CACHE_PREFIX . "%' 
                 OR option_name LIKE '_transient_timeout_" . self::CACHE_PREFIX . "%'"
            );
        }
        
        $this->logger->info('Cache flushed');
        
        // Déclencher un événement
        do_action('rmcu_cache_flushed');
        
        return true;
    }
    
    /**
     * Nettoyer le cache d'un post
     */
    public function clear_post_cache($post_id) {
        $patterns = [
            'post_' . $post_id,
            'optimization_' . $post_id,
            'analysis_' . $post_id,
            'history_' . $post_id
        ];
        
        foreach ($patterns as $pattern) {
            $this->delete($pattern);
        }
        
        $this->logger->debug('Post cache cleared', ['post_id' => $post_id]);
    }
    
    /**
     * Nettoyer le cache d'optimisation
     */
    public function clear_optimization_cache($post_id) {
        $this->delete('optimization_' . $post_id);
        $this->delete('queue_stats');
        $this->delete('queue_count');
    }
    
    /**
     * Mémoriser une fonction
     */
    public function remember($key, $callback, $expiration = null) {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = call_user_func($callback);
            $this->set($key, $value, $expiration);
        }
        
        return $value;
    }
    
    /**
     * Mémoriser pour toujours (jusqu'à flush)
     */
    public function forever($key, $value) {
        return $this->set($key, $value, 0);
    }
    
    /**
     * Incrémenter une valeur
     */
    public function increment($key, $by = 1) {
        $value = $this->get($key, 0);
        $new_value = intval($value) + $by;
        $this->set($key, $new_value);
        return $new_value;
    }
    
    /**
     * Décrémenter une valeur
     */
    public function decrement($key, $by = 1) {
        return $this->increment($key, -$by);
    }
    
    /**
     * Obtenir plusieurs valeurs
     */
    public function get_multiple($keys, $default = null) {
        $values = [];
        
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        
        return $values;
    }
    
    /**
     * Définir plusieurs valeurs
     */
    public function set_multiple($values, $expiration = null) {
        $success = true;
        
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $expiration)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Construire une clé de cache
     */
    private function build_cache_key($key) {
        // Ajouter le préfixe
        $cache_key = self::CACHE_PREFIX . $key;
        
        // S'assurer que la clé est valide
        $cache_key = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cache_key);
        
        // Limiter la longueur pour les transients (max 172 caractères)
        if (strlen($cache_key) > 172) {
            $cache_key = substr($cache_key, 0, 140) . '_' . md5($cache_key);
        }
        
        return $cache_key;
    }
    
    /**
     * Compresser des données
     */
    private function compress($data) {
        if (!function_exists('gzcompress')) {
            return false;
        }
        
        $compressed = gzcompress($data, 6);
        
        if ($compressed === false) {
            return false;
        }
        
        // Ajouter un marqueur pour identifier les données compressées
        return 'RMCU_COMPRESSED:' . base64_encode($compressed);
    }
    
    /**
     * Décompresser des données
     */
    private function decompress($data) {
        if (!function_exists('gzuncompress')) {
            return $data;
        }
        
        // Retirer le marqueur
        $data = substr($data, 15); // Longueur de 'RMCU_COMPRESSED:'
        $data = base64_decode($data);
        
        if ($data === false) {
            return false;
        }
        
        return gzuncompress($data);
    }
    
    /**
     * Vérifier si des données sont compressées
     */
    private function is_compressed($data) {
        return strpos($data, 'RMCU_COMPRESSED:') === 0;
    }
    
    /**
     * Vérifier si des données sont sérialisées
     */
    private function is_serialized($data) {
        if (!is_string($data)) {
            return false;
        }
        
        $data = trim($data);
        
        if ('N;' === $data) {
            return true;
        }
        
        if (strlen($data) < 4) {
            return false;
        }
        
        if (':' !== $data[1]) {
            return false;
        }
        
        $lastc = substr($data, -1);
        if (';' !== $lastc && '}' !== $lastc) {
            return false;
        }
        
        $token = $data[0];
        switch ($token) {
            case 's':
                if ('"' !== substr($data, -2, 1)) {
                    return false;
                }
                // no break
            case 'a':
            case 'O':
                return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
            case 'b':
            case 'i':
            case 'd':
                return (bool) preg_match("/^{$token}:[0-9.E+-]+;$/", $data);
        }
        
        return false;
    }
    
    /**
     * Nettoyer le cache expiré
     */
    public function cleanup_expired_cache() {
        if (!$this->config['use_transients']) {
            return;
        }
        
        global $wpdb;
        
        // Supprimer les transients expirés
        $deleted = $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
             WHERE a.option_name LIKE '_transient_" . self::CACHE_PREFIX . "%'
             AND a.option_name NOT LIKE '_transient_timeout_%'
             AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
             AND b.option_value < UNIX_TIMESTAMP()"
        );
        
        if ($deleted > 0) {
            $this->logger->info('Expired cache cleaned', ['count' => $deleted]);
        }
        
        return $deleted;
    }
    
    /**
     * Obtenir les statistiques du cache
     */
    public function get_stats() {
        $stats = $this->stats;
        
        // Calculer le taux de hit
        $total_requests = $stats['hits'] + $stats['misses'];
        $stats['hit_rate'] = $total_requests > 0 
            ? round(($stats['hits'] / $total_requests) * 100, 2) 
            : 0;
        
        // Ajouter des infos sur la configuration
        $stats['config'] = $this->config;
        
        // Ajouter la taille du cache si possible
        if ($this->config['use_transients']) {
            $stats['size'] = $this->get_cache_size();
        }
        
        return $stats;
    }
    
    /**
     * Obtenir la taille du cache
     */
    private function get_cache_size() {
        global $wpdb;
        
        $size = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_" . self::CACHE_PREFIX . "%'"
        );
        
        return $size ?: 0;
    }
    
    /**
     * Cache avec tags
     */
    public function tag($tags) {
        return new RMCU_Tagged_Cache($this, $tags);
    }
    
    /**
     * Invalider par tag
     */
    public function invalidate_tag($tag) {
        $tagged_keys = get_option('rmcu_cache_tags', []);
        
        if (isset($tagged_keys[$tag])) {
            foreach ($tagged_keys[$tag] as $key) {
                $this->delete($key);
            }
            
            unset($tagged_keys[$tag]);
            update_option('rmcu_cache_tags', $tagged_keys);
            
            $this->logger->debug('Tag invalidated', ['tag' => $tag]);
        }
    }
    
    /**
     * Warmer le cache
     */
    public function warm($keys_and_callbacks) {
        $warmed = 0;
        
        foreach ($keys_and_callbacks as $key => $callback) {
            if (!$this->get($key)) {
                $value = call_user_func($callback);
                if ($this->set($key, $value)) {
                    $warmed++;
                }
            }
        }
        
        $this->logger->info('Cache warmed', ['count' => $warmed]);
        
        return $warmed;
    }
    
    /**
     * Activer/désactiver le cache
     */
    public function enable() {
        $this->config['enabled'] = true;
        update_option('rmcu_cache_enabled', true);
    }
    
    public function disable() {
        $this->config['enabled'] = false;
        update_option('rmcu_cache_enabled', false);
        $this->flush();
    }
    
    /**
     * Vérifier si le cache est activé
     */
    public function is_enabled() {
        return $this->config['enabled'];
    }
}

/**
 * Classe pour le cache avec tags
 */
class RMCU_Tagged_Cache {
    
    private $cache;
    private $tags;
    
    public function __construct($cache, $tags) {
        $this->cache = $cache;
        $this->tags = (array) $tags;
    }
    
    public function get($key, $default = null) {
        return $this->cache->get($key, $default);
    }
    
    public function set($key, $value, $expiration = null) {
        $result = $this->cache->set($key, $value, $expiration);
        
        if ($result) {
            $this->store_tags($key);
        }
        
        return $result;
    }
    
    private function store_tags($key) {
        $tagged_keys = get_option('rmcu_cache_tags', []);
        
        foreach ($this->tags as $tag) {
            if (!isset($tagged_keys[$tag])) {
                $tagged_keys[$tag] = [];
            }
            
            if (!in_array($key, $tagged_keys[$tag])) {
                $tagged_keys[$tag][] = $key;
            }
        }
        
        update_option('rmcu_cache_tags', $tagged_keys);
    }
}