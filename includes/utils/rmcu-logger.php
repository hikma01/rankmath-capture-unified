<?php
/**
 * RMCU Logger
 * Système de journalisation pour le plugin
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour gérer les logs
 */
class RMCU_Logger {
    
    /**
     * Niveaux de log
     */
    const DEBUG = 'debug';
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';
    const CRITICAL = 'critical';
    
    /**
     * Priorités des niveaux
     */
    const LEVELS = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::WARNING => 2,
        self::ERROR => 3,
        self::CRITICAL => 4
    ];
    
    /**
     * Contexte du logger
     */
    private $context;
    
    /**
     * Niveau minimum de log
     */
    private $min_level;
    
    /**
     * Chemin du fichier de log
     */
    private $log_file;
    
    /**
     * Si les logs sont activés
     */
    private $enabled;
    
    /**
     * Taille maximale du fichier de log (en bytes)
     */
    private $max_file_size = 10485760; // 10MB
    
    /**
     * Nombre maximum de fichiers de rotation
     */
    private $max_rotation_files = 5;
    
    /**
     * Constructeur
     */
    public function __construct($context = 'General') {
        $this->context = $context;
        $this->load_config();
        $this->setup_log_file();
    }
    
    /**
     * Charger la configuration
     */
    private function load_config() {
        $settings = get_option('rmcu_settings', []);
        
        // Vérifier si les logs sont activés
        $this->enabled = !empty($settings['logging_enabled']) || (defined('WP_DEBUG') && WP_DEBUG);
        
        // Niveau minimum de log
        $this->min_level = $settings['log_level'] ?? self::INFO;
        
        // Taille maximale du fichier
        if (isset($settings['log_max_size'])) {
            $this->max_file_size = intval($settings['log_max_size']) * 1048576; // Convertir MB en bytes
        }
    }
    
    /**
     * Configurer le fichier de log
     */
    private function setup_log_file() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/rmcu-logs/';
        
        // Créer le répertoire s'il n'existe pas
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Ajouter un .htaccess pour protéger les logs
            $htaccess = $log_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }
            
            // Ajouter un index.php vide
            $index = $log_dir . 'index.php';
            if (!file_exists($index)) {
                file_put_contents($index, '<?php // Silence is golden');
            }
        }
        
        // Définir le fichier de log
        $this->log_file = $log_dir . 'rmcu-' . date('Y-m-d') . '.log';
    }
    
    /**
     * Log debug
     */
    public function debug($message, $data = []) {
        $this->log(self::DEBUG, $message, $data);
    }
    
    /**
     * Log info
     */
    public function info($message, $data = []) {
        $this->log(self::INFO, $message, $data);
    }
    
    /**
     * Log warning
     */
    public function warning($message, $data = []) {
        $this->log(self::WARNING, $message, $data);
    }
    
    /**
     * Log error
     */
    public function error($message, $data = []) {
        $this->log(self::ERROR, $message, $data);
    }
    
    /**
     * Log critical
     */
    public function critical($message, $data = []) {
        $this->log(self::CRITICAL, $message, $data);
        
        // Pour les erreurs critiques, envoyer aussi une notification
        $this->notify_critical($message, $data);
    }
    
    /**
     * Méthode principale de logging
     */
    public function log($level, $message, $data = []) {
        // Vérifier si les logs sont activés
        if (!$this->enabled) {
            return;
        }
        
        // Vérifier le niveau de log
        if (!$this->should_log($level)) {
            return;
        }
        
        // Construire l'entrée de log
        $entry = $this->format_log_entry($level, $message, $data);
        
        // Écrire dans le fichier
        $this->write_to_file($entry);
        
        // Écrire dans la base de données pour les erreurs importantes
        if (self::LEVELS[$level] >= self::LEVELS[self::ERROR]) {
            $this->write_to_database($level, $message, $data);
        }
        
        // Hook pour permettre d'autres gestionnaires de logs
        do_action('rmcu_log_entry', $level, $message, $data, $this->context);
    }
    
    /**
     * Vérifier si on doit logger ce niveau
     */
    private function should_log($level) {
        return self::LEVELS[$level] >= self::LEVELS[$this->min_level];
    }
    
    /**
     * Formater une entrée de log
     */
    private function format_log_entry($level, $message, $data) {
        $timestamp = current_time('Y-m-d H:i:s');
        $level_formatted = strtoupper($level);
        $context_formatted = $this->context;
        
        // Message de base
        $entry = sprintf(
            '[%s] [%s] [%s] %s',
            $timestamp,
            $level_formatted,
            $context_formatted,
            $message
        );
        
        // Ajouter les données si présentes
        if (!empty($data)) {
            $entry .= ' | Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        // Ajouter des informations de contexte
        $entry .= $this->get_context_info();
        
        return $entry . PHP_EOL;
    }
    
    /**
     * Obtenir les informations de contexte
     */
    private function get_context_info() {
        $context = [];
        
        // Utilisateur actuel
        $user_id = get_current_user_id();
        if ($user_id) {
            $user = get_userdata($user_id);
            $context[] = 'User: ' . $user->user_login . ' (ID: ' . $user_id . ')';
        }
        
        // URL actuelle
        if (!empty($_SERVER['REQUEST_URI'])) {
            $context[] = 'URL: ' . $_SERVER['REQUEST_URI'];
        }
        
        // IP du client
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $context[] = 'IP: ' . $_SERVER['REMOTE_ADDR'];
        }
        
        // Action/Hook WordPress actuel
        if (doing_action()) {
            $context[] = 'Action: ' . current_action();
        }
        
        return !empty($context) ? ' | Context: [' . implode(', ', $context) . ']' : '';
    }
    
    /**
     * Écrire dans le fichier
     */
    private function write_to_file($entry) {
        if (!$this->log_file) {
            return;
        }
        
        // Vérifier la rotation du fichier
        $this->rotate_log_if_needed();
        
        // Écrire dans le fichier
        $result = file_put_contents($this->log_file, $entry, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            // Fallback sur error_log si l'écriture échoue
            error_log('[RMCU Logger] Failed to write to log file: ' . $entry);
        }
    }
    
    /**
     * Rotation du fichier de log si nécessaire
     */
    private function rotate_log_if_needed() {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        $file_size = filesize($this->log_file);
        
        if ($file_size >= $this->max_file_size) {
            $this->rotate_log();
        }
    }
    
    /**
     * Effectuer la rotation du log
     */
    private function rotate_log() {
        $base_name = pathinfo($this->log_file, PATHINFO_FILENAME);
        $dir = dirname($this->log_file);
        
        // Renommer les anciens fichiers
        for ($i = $this->max_rotation_files - 1; $i > 0; $i--) {
            $old_file = $dir . '/' . $base_name . '.' . $i . '.log';
            $new_file = $dir . '/' . $base_name . '.' . ($i + 1) . '.log';
            
            if (file_exists($old_file)) {
                if ($i == $this->max_rotation_files - 1) {
                    // Supprimer le plus ancien
                    unlink($old_file);
                } else {
                    rename($old_file, $new_file);
                }
            }
        }
        
        // Renommer le fichier actuel
        rename($this->log_file, $dir . '/' . $base_name . '.1.log');
    }
    
    /**
     * Écrire dans la base de données
     */
    private function write_to_database($level, $message, $data) {
        global $wpdb;
        
        // Créer la table si elle n'existe pas
        $this->ensure_log_table_exists();
        
        $table = $wpdb->prefix . 'rmcu_logs';
        
        $wpdb->insert(
            $table,
            [
                'timestamp' => current_time('mysql'),
                'level' => $level,
                'context' => $this->context,
                'message' => $message,
                'data' => json_encode($data),
                'user_id' => get_current_user_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'url' => $_SERVER['REQUEST_URI'] ?? ''
            ]
        );
    }
    
    /**
     * S'assurer que la table de logs existe
     */
    private function ensure_log_table_exists() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_logs';
        
        // Vérifier si la table existe déjà
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL,
            context varchar(100),
            message text NOT NULL,
            data longtext,
            user_id bigint(20) DEFAULT 0,
            ip_address varchar(45),
            url varchar(255),
            PRIMARY KEY (id),
            INDEX idx_timestamp (timestamp),
            INDEX idx_level (level),
            INDEX idx_context (context)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Notifier les erreurs critiques
     */
    private function notify_critical($message, $data) {
        // Obtenir l'email de l'admin
        $admin_email = get_option('admin_email');
        
        // Construire le sujet
        $subject = sprintf(
            '[%s] Critical Error in RMCU: %s',
            get_bloginfo('name'),
            $this->context
        );
        
        // Construire le message
        $body = "A critical error has occurred in the RMCU plugin.\n\n";
        $body .= "Context: " . $this->context . "\n";
        $body .= "Message: " . $message . "\n";
        $body .= "Time: " . current_time('Y-m-d H:i:s') . "\n";
        
        if (!empty($data)) {
            $body .= "\nAdditional Data:\n";
            $body .= print_r($data, true);
        }
        
        $body .= "\n\nPlease check the logs for more information.";
        
        // Envoyer l'email
        wp_mail($admin_email, $subject, $body);
    }
    
    /**
     * Obtenir les logs récents
     */
    public function get_recent_logs($limit = 100, $level = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_logs';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }
        
        $where = $level ? $wpdb->prepare("WHERE level = %s", $level) : '';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table $where 
             ORDER BY timestamp DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }
    
    /**
     * Nettoyer les anciens logs
     */
    public function cleanup_old_logs($days = 30) {
        // Nettoyer les fichiers
        $this->cleanup_old_files($days);
        
        // Nettoyer la base de données
        $this->cleanup_old_database_entries($days);
    }
    
    /**
     * Nettoyer les anciens fichiers de log
     */
    private function cleanup_old_files($days) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/rmcu-logs/';
        
        if (!is_dir($log_dir)) {
            return;
        }
        
        $files = glob($log_dir . '*.log');
        $cutoff = time() - ($days * 86400);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
    
    /**
     * Nettoyer les anciennes entrées de base de données
     */
    private function cleanup_old_database_entries($days) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_logs';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }
        
        $cutoff = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE timestamp < %s",
            $cutoff
        ));
    }
    
    /**
     * Exporter les logs
     */
    public function export_logs($format = 'json', $filters = []) {
        $logs = $this->get_filtered_logs($filters);
        
        switch ($format) {
            case 'csv':
                return $this->export_as_csv($logs);
                
            case 'txt':
                return $this->export_as_text($logs);
                
            case 'json':
            default:
                return json_encode($logs, JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Obtenir les logs filtrés
     */
    private function get_filtered_logs($filters) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_logs';
        
        $where = [];
        $params = [];
        
        if (!empty($filters['level'])) {
            $where[] = "level = %s";
            $params[] = $filters['level'];
        }
        
        if (!empty($filters['context'])) {
            $where[] = "context = %s";
            $params[] = $filters['context'];
        }
        
        if (!empty($filters['from_date'])) {
            $where[] = "timestamp >= %s";
            $params[] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = "timestamp <= %s";
            $params[] = $filters['to_date'];
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT * FROM $table $where_clause ORDER BY timestamp DESC";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Exporter en CSV
     */
    private function export_as_csv($logs) {
        $output = "Timestamp,Level,Context,Message,Data,User ID,IP,URL\n";
        
        foreach ($logs as $log) {
            $output .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $log['timestamp'],
                $log['level'],
                $log['context'],
                str_replace('"', '""', $log['message']),
                str_replace('"', '""', $log['data']),
                $log['user_id'],
                $log['ip_address'],
                $log['url']
            );
        }
        
        return $output;
    }
    
    /**
     * Exporter en texte
     */
    private function export_as_text($logs) {
        $output = '';
        
        foreach ($logs as $log) {
            $output .= sprintf(
                "[%s] [%s] [%s] %s\n",
                $log['timestamp'],
                strtoupper($log['level']),
                $log['context'],
                $log['message']
            );
            
            if (!empty($log['data']) && $log['data'] !== '[]' && $log['data'] !== 'null') {
                $output .= "    Data: " . $log['data'] . "\n";
            }
            
            $output .= "\n";
        }
        
        return $output;
    }
    
    /**
     * Obtenir les statistiques des logs
     */
    public function get_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_logs';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }
        
        $stats = [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'by_level' => [],
            'by_context' => [],
            'recent_errors' => 0,
            'disk_usage' => $this->get_log_disk_usage()
        ];
        
        // Par niveau
        $levels = $wpdb->get_results(
            "SELECT level, COUNT(*) as count 
             FROM $table 
             GROUP BY level",
            ARRAY_A
        );
        
        foreach ($levels as $level) {
            $stats['by_level'][$level['level']] = $level['count'];
        }
        
        // Par contexte
        $contexts = $wpdb->get_results(
            "SELECT context, COUNT(*) as count 
             FROM $table 
             GROUP BY context 
             ORDER BY count DESC 
             LIMIT 10",
            ARRAY_A
        );
        
        foreach ($contexts as $context) {
            $stats['by_context'][$context['context']] = $context['count'];
        }
        
        // Erreurs récentes (dernières 24h)
        $stats['recent_errors'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table 
             WHERE level IN ('error', 'critical') 
             AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        return $stats;
    }
    
    /**
     * Obtenir l'utilisation disque des logs
     */
    private function get_log_disk_usage() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/rmcu-logs/';
        
        if (!is_dir($log_dir)) {
            return 0;
        }
        
        $total_size = 0;
        $files = glob($log_dir . '*.log');
        
        foreach ($files as $file) {
            $total_size += filesize($file);
        }
        
        return $total_size;
    }
}