<?php
/**
 * RMCU Queue Manager
 * Gestionnaire de file d'attente pour les tâches d'optimisation
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour gérer la file d'attente des optimisations
 */
class RMCU_Queue_Manager {
    
    /**
     * Nom de la table
     */
    private $table_name;
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Instance wpdb
     */
    private $wpdb;
    
    /**
     * Statuts valides pour les jobs
     */
    const VALID_STATUSES = [
        'pending',
        'processing',
        'completed',
        'failed',
        'cancelled',
        'retry'
    ];
    
    /**
     * Priorités valides
     */
    const VALID_PRIORITIES = [
        'low' => 0,
        'normal' => 5,
        'high' => 10
    ];
    
    /**
     * Constructeur
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'rmcu_optimization_queue';
        $this->logger = new RMCU_Logger('Queue_Manager');
    }
    
    /**
     * Créer les tables nécessaires
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority varchar(10) NOT NULL DEFAULT 'normal',
            target_score int(3) DEFAULT 90,
            current_score int(3) DEFAULT 0,
            iterations int(11) DEFAULT 0,
            attempts int(11) DEFAULT 0,
            data longtext,
            result longtext,
            error text,
            scheduled_at datetime DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            failed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_post_id (post_id),
            INDEX idx_status (status),
            INDEX idx_priority_scheduled (priority, scheduled_at),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->logger->info('Queue tables created/updated');
    }
    
    /**
     * Ajouter un job à la queue
     */
    public function add_job($job_data) {
        // Valider les données
        $validated = $this->validate_job_data($job_data);
        
        if (is_wp_error($validated)) {
            $this->logger->error('Invalid job data', [
                'error' => $validated->get_error_message()
            ]);
            return false;
        }
        
        // Préparer les données pour l'insertion
        $insert_data = [
            'post_id' => $validated['post_id'],
            'status' => $validated['status'] ?? 'pending',
            'priority' => $validated['priority'] ?? 'normal',
            'target_score' => $validated['target_score'] ?? 90,
            'current_score' => $validated['current_score'] ?? 0,
            'iterations' => $validated['iterations'] ?? 0,
            'attempts' => $validated['attempts'] ?? 0,
            'scheduled_at' => $validated['scheduled_at'] ?? current_time('mysql'),
            'created_at' => current_time('mysql')
        ];
        
        // Sérializer les données complexes
        if (isset($validated['data'])) {
            $insert_data['data'] = json_encode($validated['data']);
        }
        
        // Insérer dans la base de données
        $result = $this->wpdb->insert($this->table_name, $insert_data);
        
        if ($result === false) {
            $this->logger->error('Failed to insert job', [
                'error' => $this->wpdb->last_error
            ]);
            return false;
        }
        
        $job_id = $this->wpdb->insert_id;
        
        $this->logger->info('Job added to queue', [
            'job_id' => $job_id,
            'post_id' => $validated['post_id'],
            'priority' => $validated['priority']
        ]);
        
        // Déclencher un événement
        do_action('rmcu_job_queued', $job_id, $validated);
        
        return $job_id;
    }
    
    /**
     * Obtenir un job par ID
     */
    public function get_job($job_id) {
        $job = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $job_id
        ));
        
        if ($job && !empty($job->data)) {
            $job->data = json_decode($job->data, true);
        }
        
        if ($job && !empty($job->result)) {
            $job->result = json_decode($job->result, true);
        }
        
        return $job;
    }
    
    /**
     * Obtenir les jobs en attente
     */
    public function get_pending_jobs($limit = 10) {
        $jobs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status IN ('pending', 'retry') 
             AND scheduled_at <= NOW()
             ORDER BY 
                FIELD(priority, 'high', 'normal', 'low'),
                scheduled_at ASC
             LIMIT %d",
            $limit
        ));
        
        foreach ($jobs as $job) {
            if (!empty($job->data)) {
                $job->data = json_decode($job->data, true);
            }
        }
        
        return $jobs;
    }
    
    /**
     * Obtenir les jobs par statut
     */
    public function get_jobs_by_status($status, $limit = 100) {
        if (!in_array($status, self::VALID_STATUSES)) {
            return [];
        }
        
        $jobs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status = %s 
             ORDER BY created_at DESC 
             LIMIT %d",
            $status, $limit
        ));
        
        return $jobs;
    }
    
    /**
     * Obtenir les jobs pour un post
     */
    public function get_jobs_for_post($post_id, $include_completed = false) {
        $statuses = $include_completed 
            ? self::VALID_STATUSES 
            : array_diff(self::VALID_STATUSES, ['completed']);
        
        $placeholders = array_fill(0, count($statuses), '%s');
        $query = "SELECT * FROM {$this->table_name} 
                  WHERE post_id = %d 
                  AND status IN (" . implode(',', $placeholders) . ")
                  ORDER BY created_at DESC";
        
        $params = array_merge([$post_id], $statuses);
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($query, $params)
        );
    }
    
    /**
     * Mettre à jour le statut d'un job
     */
    public function update_job_status($job_id, $status, $additional_data = []) {
        if (!in_array($status, self::VALID_STATUSES)) {
            $this->logger->error('Invalid job status', ['status' => $status]);
            return false;
        }
        
        $update_data = [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ];
        
        // Ajouter les timestamps selon le statut
        switch ($status) {
            case 'processing':
                $update_data['started_at'] = current_time('mysql');
                break;
                
            case 'completed':
                $update_data['completed_at'] = current_time('mysql');
                break;
                
            case 'failed':
                $update_data['failed_at'] = current_time('mysql');
                break;
        }
        
        // Ajouter les données supplémentaires
        foreach ($additional_data as $key => $value) {
            if ($key === 'result' || $key === 'data') {
                $update_data[$key] = json_encode($value);
            } else {
                $update_data[$key] = $value;
            }
        }
        
        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $job_id]
        );
        
        if ($result === false) {
            $this->logger->error('Failed to update job status', [
                'job_id' => $job_id,
                'error' => $this->wpdb->last_error
            ]);
            return false;
        }
        
        $this->logger->info('Job status updated', [
            'job_id' => $job_id,
            'status' => $status
        ]);
        
        // Déclencher un événement
        do_action('rmcu_job_status_changed', $job_id, $status, $additional_data);
        
        return true;
    }
    
    /**
     * Annuler un job
     */
    public function cancel_job($job_id) {
        $job = $this->get_job($job_id);
        
        if (!$job) {
            return false;
        }
        
        if (in_array($job->status, ['completed', 'cancelled'])) {
            // Déjà terminé ou annulé
            return false;
        }
        
        return $this->update_job_status($job_id, 'cancelled');
    }
    
    /**
     * Annuler tous les jobs pour un post
     */
    public function cancel_jobs_for_post($post_id) {
        $result = $this->wpdb->update(
            $this->table_name,
            [
                'status' => 'cancelled',
                'updated_at' => current_time('mysql')
            ],
            [
                'post_id' => $post_id,
                'status' => ['pending', 'retry']
            ]
        );
        
        $this->logger->info('Jobs cancelled for post', [
            'post_id' => $post_id,
            'count' => $result
        ]);
        
        return $result;
    }
    
    /**
     * Réessayer un job échoué
     */
    public function retry_job($job_id, $delay = 60) {
        $job = $this->get_job($job_id);
        
        if (!$job || $job->status !== 'failed') {
            return false;
        }
        
        $scheduled_at = date('Y-m-d H:i:s', time() + $delay);
        $attempts = intval($job->attempts) + 1;
        
        return $this->update_job_status($job_id, 'retry', [
            'scheduled_at' => $scheduled_at,
            'attempts' => $attempts
        ]);
    }
    
    /**
     * Vérifier si un post a des jobs en attente
     */
    public function has_pending_job($post_id) {
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE post_id = %d 
             AND status IN ('pending', 'processing', 'retry')",
            $post_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Obtenir la queue complète
     */
    public function get_queue($limit = 100, $offset = 0) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT j.*, p.post_title 
             FROM {$this->table_name} j
             LEFT JOIN {$this->wpdb->posts} p ON j.post_id = p.ID
             WHERE j.status IN ('pending', 'processing', 'retry')
             ORDER BY 
                FIELD(j.priority, 'high', 'normal', 'low'),
                j.scheduled_at ASC
             LIMIT %d OFFSET %d",
            $limit, $offset
        ));
    }
    
    /**
     * Obtenir le nombre de jobs dans la queue
     */
    public function get_queue_count() {
        return $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE status IN ('pending', 'processing', 'retry')"
        );
    }
    
    /**
     * Obtenir les statistiques de la queue
     */
    public function get_queue_stats() {
        $stats = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as count 
             FROM {$this->table_name} 
             GROUP BY status",
            OBJECT_K
        );
        
        $result = [
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'retry' => 0
        ];
        
        foreach ($stats as $status => $data) {
            if (isset($result[$status])) {
                $result[$status] = intval($data->count);
                $result['total'] += intval($data->count);
            }
        }
        
        // Statistiques supplémentaires
        $result['avg_score_improvement'] = $this->get_average_score_improvement();
        $result['success_rate'] = $this->get_success_rate();
        $result['avg_processing_time'] = $this->get_average_processing_time();
        
        return $result;
    }
    
    /**
     * Obtenir l'amélioration moyenne du score
     */
    private function get_average_score_improvement() {
        $improvement = $this->wpdb->get_var(
            "SELECT AVG(current_score - 
                (SELECT current_score 
                 FROM {$this->table_name} j2 
                 WHERE j2.post_id = j1.post_id 
                 AND j2.created_at < j1.created_at 
                 ORDER BY j2.created_at DESC 
                 LIMIT 1)
             ) as improvement
             FROM {$this->table_name} j1
             WHERE status = 'completed'"
        );
        
        return round($improvement ?: 0, 2);
    }
    
    /**
     * Obtenir le taux de succès
     */
    private function get_success_rate() {
        $total = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE status IN ('completed', 'failed')"
        );
        
        if ($total == 0) {
            return 0;
        }
        
        $successful = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE status = 'completed'"
        );
        
        return round(($successful / $total) * 100, 2);
    }
    
    /**
     * Obtenir le temps de traitement moyen
     */
    private function get_average_processing_time() {
        $avg_time = $this->wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) 
             FROM {$this->table_name} 
             WHERE status = 'completed' 
             AND started_at IS NOT NULL 
             AND completed_at IS NOT NULL"
        );
        
        return $avg_time ? round($avg_time) : 0;
    }
    
    /**
     * Supprimer les anciens jobs
     */
    public function delete_old_jobs($older_than) {
        $deleted = $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE status IN ('completed', 'cancelled', 'failed') 
             AND created_at < %s",
            $older_than
        ));
        
        $this->logger->info('Old jobs deleted', [
            'count' => $deleted,
            'older_than' => $older_than
        ]);
        
        return $deleted;
    }
    
    /**
     * Nettoyer les jobs bloqués
     */
    public function cleanup_stuck_jobs($timeout = 3600) {
        // Jobs en processing depuis trop longtemps
        $stuck_time = date('Y-m-d H:i:s', time() - $timeout);
        
        $updated = $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET status = 'retry', 
                 attempts = attempts + 1,
                 scheduled_at = NOW() + INTERVAL 5 MINUTE,
                 error = 'Job timeout',
                 updated_at = NOW()
             WHERE status = 'processing' 
             AND started_at < %s",
            $stuck_time
        ));
        
        if ($updated > 0) {
            $this->logger->warning('Stuck jobs cleaned up', ['count' => $updated]);
        }
        
        return $updated;
    }
    
    /**
     * Réinitialiser la queue
     */
    public function reset_queue() {
        $result = $this->wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        $this->logger->warning('Queue reset');
        
        return $result !== false;
    }
    
    /**
     * Valider les données d'un job
     */
    private function validate_job_data($data) {
        $errors = new WP_Error();
        
        // Post ID requis
        if (empty($data['post_id']) || !is_numeric($data['post_id'])) {
            $errors->add('invalid_post_id', 'Valid post ID is required');
        } else {
            // Vérifier que le post existe
            $post = get_post($data['post_id']);
            if (!$post) {
                $errors->add('post_not_found', 'Post does not exist');
            }
        }
        
        // Valider le statut
        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES)) {
            $errors->add('invalid_status', 'Invalid job status');
        }
        
        // Valider la priorité
        if (isset($data['priority']) && !array_key_exists($data['priority'], self::VALID_PRIORITIES)) {
            $errors->add('invalid_priority', 'Invalid job priority');
        }
        
        // Valider le score cible
        if (isset($data['target_score'])) {
            $target = intval($data['target_score']);
            if ($target < 0 || $target > 100) {
                $errors->add('invalid_target_score', 'Target score must be between 0 and 100');
            }
        }
        
        // Valider le score actuel
        if (isset($data['current_score'])) {
            $current = intval($data['current_score']);
            if ($current < 0 || $current > 100) {
                $errors->add('invalid_current_score', 'Current score must be between 0 and 100');
            }
        }
        
        if ($errors->has_errors()) {
            return $errors;
        }
        
        return $data;
    }
    
    /**
     * Obtenir le prochain job à traiter
     */
    public function get_next_job() {
        $job = $this->wpdb->get_row(
            "SELECT * FROM {$this->table_name} 
             WHERE status IN ('pending', 'retry') 
             AND scheduled_at <= NOW()
             ORDER BY 
                FIELD(priority, 'high', 'normal', 'low'),
                scheduled_at ASC
             LIMIT 1
             FOR UPDATE"
        );
        
        if ($job) {
            // Marquer comme en cours de traitement
            $this->update_job_status($job->id, 'processing');
            
            if (!empty($job->data)) {
                $job->data = json_decode($job->data, true);
            }
        }
        
        return $job;
    }
    
    /**
     * Obtenir l'historique d'un post
     */
    public function get_post_history($post_id, $limit = 10) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE post_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $post_id, $limit
        ));
    }
    
    /**
     * Exporter les jobs
     */
    public function export_jobs($filters = []) {
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $where .= " AND status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['from_date'])) {
            $where .= " AND created_at >= %s";
            $params[] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $where .= " AND created_at <= %s";
            $params[] = $filters['to_date'];
        }
        
        $query = "SELECT * FROM {$this->table_name} $where ORDER BY created_at DESC";
        
        if (!empty($params)) {
            $query = $this->wpdb->prepare($query, $params);
        }
        
        return $this->wpdb->get_results($query, ARRAY_A);
    }
}