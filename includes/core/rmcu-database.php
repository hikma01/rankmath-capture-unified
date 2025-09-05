<?php
/**
 * RMCU Database Handler
 * Gestionnaire de base de données pour le plugin
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de gestion de la base de données
 */
class RMCU_Database {
    
    /**
     * Version du schéma de base de données
     */
    const DB_VERSION = '2.0.0';
    
    /**
     * Instance wpdb
     */
    private $wpdb;
    
    /**
     * Nom de la table des captures
     */
    private $table_captures;
    
    /**
     * Nom de la table des logs
     */
    private $table_logs;
    
    /**
     * Nom de la table de queue webhook
     */
    private $table_webhook_queue;
    
    /**
     * Nom de la table des médias
     */
    private $table_media;
    
    /**
     * Nom de la table des analytics
     */
    private $table_analytics;
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Cache
     */
    private $cache = [];
    
    /**
     * Constructeur
     */
    public function __construct() {
        global $wpdb;
        
        $this->wpdb = $wpdb;
        
        // Définir les noms de tables
        $this->table_captures = $wpdb->prefix . 'rmcu_captures';
        $this->table_logs = $wpdb->prefix . 'rmcu_logs';
        $this->table_webhook_queue = $wpdb->prefix . 'rmcu_webhook_queue';
        $this->table_media = $wpdb->prefix . 'rmcu_media';
        $this->table_analytics = $wpdb->prefix . 'rmcu_analytics';
        
        // Initialiser le logger
        if (class_exists('RMCU_Logger')) {
            $this->logger = new RMCU_Logger('Database');
        }
    }
    
    /**
     * Créer toutes les tables
     */
    public function create_tables() {
        $this->create_captures_table();
        $this->create_logs_table();
        $this->create_webhook_queue_table();
        $this->create_media_table();
        $this->create_analytics_table();
        
        // Sauvegarder la version du schéma
        update_option('rmcu_db_version', self::DB_VERSION);
        
        $this->logger->info('Database tables created', [
            'version' => self::DB_VERSION
        ]);
    }
    
    /**
     * Créer la table des captures
     */
    private function create_captures_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_captures} (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) DEFAULT NULL,
            post_id int(11) DEFAULT NULL,
            capture_type varchar(50) NOT NULL,
            capture_data longtext,
            metadata longtext,
            status varchar(20) DEFAULT 'active',
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY post_id (post_id),
            KEY capture_type (capture_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        $this->execute_dbdelta($sql);
    }
    
    /**
     * Créer la table des logs
     */
    private function create_logs_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_logs} (
            id int(11) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            context varchar(100) DEFAULT NULL,
            message text NOT NULL,
            data longtext,
            user_id int(11) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY context (context),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        $this->execute_dbdelta($sql);
    }
    
    /**
     * Créer la table de queue webhook
     */
    private function create_webhook_queue_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_webhook_queue} (
            id int(11) NOT NULL AUTO_INCREMENT,
            capture_id int(11) NOT NULL,
            webhook_url text NOT NULL,
            data longtext,
            attempts int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            next_retry datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY capture_id (capture_id),
            KEY status (status),
            KEY next_retry (next_retry)
        ) $charset_collate;";
        
        $this->execute_dbdelta($sql);
    }
    
    /**
     * Créer la table des médias
     */
    private function create_media_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_media} (
            id int(11) NOT NULL AUTO_INCREMENT,
            capture_id int(11) NOT NULL,
            attachment_id int(11) DEFAULT NULL,
            media_type varchar(50) NOT NULL,
            file_path text,
            file_url text,
            file_size int(11) DEFAULT NULL,
            duration float DEFAULT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY capture_id (capture_id),
            KEY attachment_id (attachment_id),
            KEY media_type (media_type)
        ) $charset_collate;";
        
        $this->execute_dbdelta($sql);
    }
    
    /**
     * Créer la table analytics
     */
    private function create_analytics_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_analytics} (
            id int(11) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            user_id int(11) DEFAULT NULL,
            post_id int(11) DEFAULT NULL,
            capture_id int(11) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY post_id (post_id),
            KEY capture_id (capture_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        $this->execute_dbdelta($sql);
    }
    
    /**
     * Exécuter dbDelta
     */
    private function execute_dbdelta($sql) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Supprimer toutes les tables
     */
    public function drop_tables() {
        $tables = [
            $this->table_captures,
            $this->table_logs,
            $this->table_webhook_queue,
            $this->table_media,
            $this->table_analytics
        ];
        
        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('rmcu_db_version');
        
        $this->logger->info('Database tables dropped');
    }
    
    // ========== CAPTURES ==========
    
    /**
     * Insérer une capture
     */
    public function insert_capture($data) {
        $defaults = [
            'user_id' => get_current_user_id(),
            'post_id' => null,
            'capture_type' => 'video',
            'capture_data' => '',
            'metadata' => '',
            'status' => 'active',
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Encoder les données JSON si nécessaire
        if (is_array($data['capture_data']) || is_object($data['capture_data'])) {
            $data['capture_data'] = json_encode($data['capture_data']);
        }
        
        if (is_array($data['metadata']) || is_object($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        
        $result = $this->wpdb->insert(
            $this->table_captures,
            $data,
            [
                '%d', // user_id
                '%d', // post_id
                '%s', // capture_type
                '%s', // capture_data
                '%s', // metadata
                '%s', // status
                '%s', // ip_address
                '%s', // user_agent
                '%s'  // created_at
            ]
        );
        
        if ($result === false) {
            $this->logger->error('Failed to insert capture', [
                'error' => $this->wpdb->last_error
            ]);
            return false;
        }
        
        $capture_id = $this->wpdb->insert_id;
        
        // Enregistrer l'événement
        $this->track_event('capture_created', [
            'capture_id' => $capture_id,
            'type' => $data['capture_type']
        ], $data['user_id'], $data['post_id'], $capture_id);
        
        // Hook après insertion
        do_action('rmcu_capture_created', $capture_id, $data);
        
        return $capture_id;
    }
    
    /**
     * Obtenir une capture
     */
    public function get_capture($capture_id) {
        // Vérifier le cache
        if (isset($this->cache['capture_' . $capture_id])) {
            return $this->cache['capture_' . $capture_id];
        }
        
        $capture = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_captures} WHERE id = %d",
                $capture_id
            )
        );
        
        if ($capture) {
            // Mettre en cache
            $this->cache['capture_' . $capture_id] = $capture;
        }
        
        return $capture;
    }
    
    /**
     * Obtenir plusieurs captures
     */
    public function get_captures($filters = [], $limit = 20, $offset = 0) {
        $where = ['1=1'];
        $values = [];
        
        if (isset($filters['user_id'])) {
            $where[] = "user_id = %d";
            $values[] = $filters['user_id'];
        }
        
        if (isset($filters['post_id'])) {
            $where[] = "post_id = %d";
            $values[] = $filters['post_id'];
        }
        
        if (isset($filters['capture_type'])) {
            $where[] = "capture_type = %s";
            $values[] = $filters['capture_type'];
        }
        
        if (isset($filters['status'])) {
            $where[] = "status = %s";
            $values[] = $filters['status'];
        }
        
        if (isset($filters['date_from'])) {
            $where[] = "created_at >= %s";
            $values[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $where[] = "created_at <= %s";
            $values[] = $filters['date_to'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT * FROM {$this->table_captures} 
                  WHERE $where_clause 
                  ORDER BY created_at DESC 
                  LIMIT %d OFFSET %d";
        
        $values[] = $limit;
        $values[] = $offset;
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($query, $values)
        );
    }
    
    /**
     * Compter les captures
     */
    public function count_captures($filters = []) {
        $where = ['1=1'];
        $values = [];
        
        if (isset($filters['user_id'])) {
            $where[] = "user_id = %d";
            $values[] = $filters['user_id'];
        }
        
        if (isset($filters['post_id'])) {
            $where[] = "post_id = %d";
            $values[] = $filters['post_id'];
        }
        
        if (isset($filters['capture_type'])) {
            $where[] = "capture_type = %s";
            $values[] = $filters['capture_type'];
        }
        
        if (isset($filters['status'])) {
            $where[] = "status = %s";
            $values[] = $filters['status'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT COUNT(*) FROM {$this->table_captures} WHERE $where_clause";
        
        if (!empty($values)) {
            return $this->wpdb->get_var($this->wpdb->prepare($query, $values));
        }
        
        return $this->wpdb->get_var($query);
    }
    
    /**
     * Mettre à jour une capture
     */
    public function update_capture($capture_id, $data) {
        // Encoder les données JSON si nécessaire
        if (isset($data['capture_data']) && (is_array($data['capture_data']) || is_object($data['capture_data']))) {
            $data['capture_data'] = json_encode($data['capture_data']);
        }
        
        if (isset($data['metadata']) && (is_array($data['metadata']) || is_object($data['metadata']))) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        
        $data['updated_at'] = current_time('mysql');
        
        $result = $this->wpdb->update(
            $this->table_captures,
            $data,
            ['id' => $capture_id]
        );
        
        if ($result === false) {
            $this->logger->error('Failed to update capture', [
                'capture_id' => $capture_id,
                'error' => $this->wpdb->last_error
            ]);
            return false;
        }
        
        // Invalider le cache
        unset($this->cache['capture_' . $capture_id]);
        
        // Hook après mise à jour
        do_action('rmcu_capture_updated', $capture_id, $data);
        
        return true;
    }
    
    /**
     * Supprimer une capture
     */
    public function delete_capture($capture_id) {
        // Supprimer les médias associés
        $this->delete_capture_media($capture_id);
        
        // Supprimer la capture
        $result = $this->wpdb->delete(
            $this->table_captures,
            ['id' => $capture_id],
            ['%d']
        );
        
        if ($result === false) {
            $this->logger->error('Failed to delete capture', [
                'capture_id' => $capture_id,
                'error' => $this->wpdb->last_error
            ]);
            return false;
        }
        
        // Invalider le cache
        unset($this->cache['capture_' . $capture_id]);
        
        // Hook après suppression
        do_action('rmcu_capture_deleted', $capture_id);
        
        return true;
    }
    
    // ========== MEDIA ==========
    
    /**
     * Ajouter un média
     */
    public function add_media($capture_id, $media_data) {
        $defaults = [
            'capture_id' => $capture_id,
            'attachment_id' => null,
            'media_type' => 'video',
            'file_path' => '',
            'file_url' => '',
            'file_size' => 0,
            'duration' => null,
            'metadata' => '',
            'created_at' => current_time('mysql')
        ];
        
        $data = wp_parse_args($media_data, $defaults);
        
        if (is_array($data['metadata']) || is_object($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        
        $result = $this->wpdb->insert(
            $this->table_media,
            $data,
            ['%d', '%d', '%s', '%s', '%s', '%d', '%f', '%s', '%s']
        );
        
        if ($result === false) {
            $this->logger->error('Failed to add media', [
                'capture_id' => $capture_id,
                'error' => $this->wpdb->last_error
            ]);
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Obtenir les médias d'une capture
     */
    public function get_capture_media($capture_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_media} WHERE capture_id = %d ORDER BY created_at ASC",
                $capture_id
            )
        );
    }
    
    /**
     * Supprimer les médias d'une capture
     */
    public function delete_capture_media($capture_id) {
        // Récupérer les médias pour supprimer les fichiers
        $media_items = $this->get_capture_media($capture_id);
        
        foreach ($media_items as $media) {
            // Supprimer l'attachment WordPress si présent
            if ($media->attachment_id) {
                wp_delete_attachment($media->attachment_id, true);
            }
            
            // Supprimer le fichier physique si pas un attachment
            if ($media->file_path && !$media->attachment_id) {
                if (file_exists($media->file_path)) {
                    unlink($media->file_path);
                }
            }
        }
        
        // Supprimer de la base de données
        return $this->wpdb->delete(
            $this->table_media,
            ['capture_id' => $capture_id],
            ['%d']
        );
    }
    
    // ========== ANALYTICS ==========
    
    /**
     * Enregistrer un événement
     */
    public function track_event($event_type, $event_data = [], $user_id = null, $post_id = null, $capture_id = null) {
        $data = [
            'event_type' => $event_type,
            'event_data' => json_encode($event_data),
            'user_id' => $user_id ?: get_current_user_id(),
            'post_id' => $post_id,
            'capture_id' => $capture_id,
            'ip_address' => $this->get_client_ip(),
            'created_at' => current_time('mysql')
        ];
        
        $result = $this->wpdb->insert(
            $this->table_analytics,
            $data,
            ['%s', '%s', '%d', '%d', '%d', '%s', '%s']
        );
        
        if ($result === false) {
            $this->logger->error('Failed to track event', [
                'event_type' => $event_type,
                'error' => $this->wpdb->last_error
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * Obtenir les statistiques
     */
    public function get_statistics($period = 'all') {
        $stats = [];
        
        // Total des captures
        $stats['total_captures'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_captures}"
        );
        
        // Captures par type
        $stats['by_type'] = $this->wpdb->get_results(
            "SELECT capture_type, COUNT(*) as count 
             FROM {$this->table_captures} 
             GROUP BY capture_type"
        );
        
        // Captures par utilisateur (top 10)
        $stats['top_users'] = $this->wpdb->get_results(
            "SELECT user_id, COUNT(*) as count 
             FROM {$this->table_captures} 
             GROUP BY user_id 
             ORDER BY count DESC 
             LIMIT 10"
        );
        
        // Captures récentes
        if ($period !== 'all') {
            $date_limit = $this->get_date_limit($period);
            
            $stats['recent_captures'] = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_captures} WHERE created_at >= %s",
                    $date_limit
                )
            );
        }
        
        // Événements populaires
        $stats['popular_events'] = $this->wpdb->get_results(
            "SELECT event_type, COUNT(*) as count 
             FROM {$this->table_analytics} 
             GROUP BY event_type 
             ORDER BY count DESC 
             LIMIT 10"
        );
        
        return $stats;
    }
    
    // ========== MAINTENANCE ==========
    
    /**
     * Nettoyer les anciennes données
     */
    public function cleanup_old_data($days = 30) {
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Nettoyer les captures
        $deleted_captures = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_captures} 
                 WHERE created_at < %s AND status = 'deleted'",
                $date_limit
            )
        );
        
        // Nettoyer les logs
        $deleted_logs = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_logs} 
                 WHERE created_at < %s AND level != 'error' AND level != 'critical'",
                $date_limit
            )
        );
        
        // Nettoyer les analytics
        $deleted_analytics = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_analytics} WHERE created_at < %s",
                $date_limit
            )
        );
        
        // Nettoyer la queue webhook
        $deleted_queue = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_webhook_queue} 
                 WHERE created_at < %s AND status IN ('completed', 'failed')",
                $date_limit
            )
        );
        
        $this->logger->info('Cleanup completed', [
            'deleted_captures' => $deleted_captures,
            'deleted_logs' => $deleted_logs,
            'deleted_analytics' => $deleted_analytics,
            'deleted_queue' => $deleted_queue,
            'days' => $days
        ]);
        
        return [
            'captures' => $deleted_captures,
            'logs' => $deleted_logs,
            'analytics' => $deleted_analytics,
            'queue' => $deleted_queue
        ];
    }
    
    /**
     * Optimiser les tables
     */
    public function optimize_tables() {
        $tables = [
            $this->table_captures,
            $this->table_logs,
            $this->table_webhook_queue,
            $this->table_media,
            $this->table_analytics
        ];
        
        foreach ($tables as $table) {
            $this->wpdb->query("OPTIMIZE TABLE $table");
        }
        
        $this->logger->info('Tables optimized');
    }
    
    /**
     * Vérifier l'intégrité des tables
     */
    public function check_tables_integrity() {
        $issues = [];
        
        $tables = [
            $this->table_captures,
            $this->table_logs,
            $this->table_webhook_queue,
            $this->table_media,
            $this->table_analytics
        ];
        
        foreach ($tables as $table) {
            $result = $this->wpdb->get_row("CHECK TABLE $table");
            
            if ($result && $result->Msg_text !== 'OK') {
                $issues[$table] = $result->Msg_text;
            }
        }
        
        if (!empty($issues)) {
            $this->logger->warning('Table integrity issues found', $issues);
        }
        
        return $issues;
    }
    
    /**
     * Migration depuis la v1
     */
    public function migrate_from_v1() {
        // Vérifier si les anciennes tables existent
        $old_table = $this->wpdb->prefix . 'rankmath_capture_data';
        
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$old_table'") === $old_table) {
            // Migrer les données
            $old_data = $this->wpdb->get_results("SELECT * FROM $old_table");
            
            foreach ($old_data as $row) {
                $this->insert_capture([
                    'user_id' => $row->user_id ?? 0,
                    'post_id' => $row->post_id ?? 0,
                    'capture_type' => $row->type ?? 'video',
                    'capture_data' => $row->data ?? '',
                    'metadata' => json_encode([
                        'migrated' => true,
                        'old_id' => $row->id
                    ]),
                    'created_at' => $row->created ?? current_time('mysql')
                ]);
            }
            
            $this->logger->info('Migration from v1 completed', [
                'records_migrated' => count($old_data)
            ]);
        }
    }
    
    // ========== HELPERS ==========
    
    /**
     * Obtenir l'IP du client
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Obtenir la limite de date selon la période
     */
    private function get_date_limit($period) {
        switch ($period) {
            case 'day':
                return date('Y-m-d H:i:s', strtotime('-1 day'));
            case 'week':
                return date('Y-m-d H:i:s', strtotime('-1 week'));
            case 'month':
                return date('Y-m-d H:i:s', strtotime('-1 month'));
            case 'year':
                return date('Y-m-d H:i:s', strtotime('-1 year'));
            default:
                return '1970-01-01 00:00:00';
        }
    }
    
    /**
     * Réinitialiser le cache
     */
    public function flush_cache() {
        $this->cache = [];
    }
}