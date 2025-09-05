<?php
/**
 * RMCU Dispatcher
 * Orchestrateur des communications entre WordPress et n8n
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe Dispatcher pour orchestrer les flux de données
 */
class RMCU_Dispatcher {
    
    /**
     * Instance de l'API Handler
     */
    private $api_handler;
    
    /**
     * Instance du Queue Manager
     */
    private $queue_manager;
    
    /**
     * Instance du Cache Manager
     */
    private $cache_manager;
    
    /**
     * Instance du Logger
     */
    private $logger;
    
    /**
     * Configuration
     */
    private $config;
    
    /**
     * Statut actuel
     */
    private $status = [
        'is_processing' => false,
        'current_job' => null,
        'jobs_in_queue' => 0,
        'last_error' => null
    ];
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->logger = new RMCU_Logger('Dispatcher');
        
        // Initialiser les composants
        $this->init_components();
        
        // Charger la configuration
        $this->load_config();
        
        // Enregistrer les hooks
        $this->register_hooks();
        
        // Démarrer le processeur de queue si activé
        if ($this->config['auto_process']) {
            $this->start_queue_processor();
        }
    }
    
    /**
     * Initialiser les composants
     */
    private function init_components() {
        // Les instances seront injectées depuis le conteneur principal
        if (class_exists('RMCU_API_Handler')) {
            $this->api_handler = new RMCU_API_Handler();
        }
        
        if (class_exists('RMCU_Queue_Manager')) {
            $this->queue_manager = new RMCU_Queue_Manager();
        }
        
        if (class_exists('RMCU_Cache_Manager')) {
            $this->cache_manager = new RMCU_Cache_Manager();
        }
    }
    
    /**
     * Charger la configuration
     */
    private function load_config() {
        $settings = get_option('rmcu_settings', []);
        
        $this->config = [
            'auto_process' => $settings['auto_process'] ?? true,
            'batch_size' => $settings['batch_size'] ?? 5,
            'retry_attempts' => $settings['retry_attempts'] ?? 3,
            'retry_delay' => $settings['retry_delay'] ?? 60, // secondes
            'process_interval' => $settings['process_interval'] ?? 300, // 5 minutes
            'max_execution_time' => $settings['max_execution_time'] ?? 120, // secondes
            'priority_mode' => $settings['priority_mode'] ?? 'score', // 'score', 'date', 'manual'
        ];
    }
    
    /**
     * Enregistrer les hooks
     */
    private function register_hooks() {
        // Hooks d'actions programmées
        add_action('rmcu_process_queue', [$this, 'process_queue']);
        add_action('rmcu_retry_failed', [$this, 'retry_failed_jobs']);
        add_action('rmcu_cleanup_old_jobs', [$this, 'cleanup_old_jobs']);
        
        // Hooks de post
        add_action('save_post', [$this, 'on_post_save'], 10, 3);
        add_action('trash_post', [$this, 'on_post_trash']);
        
        // Hooks admin
        add_action('admin_init', [$this, 'maybe_process_manual']);
        
        // Hooks AJAX
        add_action('wp_ajax_rmcu_dispatch_job', [$this, 'ajax_dispatch_job']);
        add_action('wp_ajax_rmcu_get_queue_status', [$this, 'ajax_get_queue_status']);
        add_action('wp_ajax_rmcu_cancel_job', [$this, 'ajax_cancel_job']);
    }
    
    /**
     * Démarrer le processeur de queue
     */
    private function start_queue_processor() {
        if (!wp_next_scheduled('rmcu_process_queue')) {
            wp_schedule_event(
                time(), 
                'rmcu_process_interval', 
                'rmcu_process_queue'
            );
        }
        
        if (!wp_next_scheduled('rmcu_retry_failed')) {
            wp_schedule_event(
                time() + 3600, 
                'hourly', 
                'rmcu_retry_failed'
            );
        }
        
        if (!wp_next_scheduled('rmcu_cleanup_old_jobs')) {
            wp_schedule_event(
                time() + 86400, 
                'daily', 
                'rmcu_cleanup_old_jobs'
            );
        }
    }
    
    /**
     * Dispatcher une tâche d'optimisation
     */
    public function dispatch_optimization($post_id, $data = [], $priority = 'normal') {
        $this->logger->info('Dispatching optimization job', [
            'post_id' => $post_id,
            'priority' => $priority
        ]);
        
        // Vérifier si une tâche existe déjà
        if ($this->queue_manager->has_pending_job($post_id)) {
            $this->logger->warning('Job already in queue', ['post_id' => $post_id]);
            return [
                'success' => false,
                'message' => 'A job for this post is already in queue'
            ];
        }
        
        // Valider les données
        $validated_data = $this->validate_optimization_data($data);
        if (!$validated_data) {
            return [
                'success' => false,
                'message' => 'Invalid optimization data'
            ];
        }
        
        // Ajouter à la queue
        $job_id = $this->queue_manager->add_job([
            'type' => 'optimization',
            'post_id' => $post_id,
            'data' => $validated_data,
            'priority' => $priority,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => current_time('mysql'),
            'scheduled_at' => current_time('mysql')
        ]);
        
        if (!$job_id) {
            $this->logger->error('Failed to add job to queue', ['post_id' => $post_id]);
            return [
                'success' => false,
                'message' => 'Failed to queue the job'
            ];
        }
        
        // Traiter immédiatement si en mode priorité haute
        if ($priority === 'high' && !$this->status['is_processing']) {
            $this->process_single_job($job_id);
        }
        
        // Déclencher un événement
        do_action('rmcu_job_dispatched', $job_id, $post_id, $data);
        
        return [
            'success' => true,
            'job_id' => $job_id,
            'message' => 'Job queued successfully'
        ];
    }
    
    /**
     * Valider les données d'optimisation
     */
    private function validate_optimization_data($data) {
        // Structure minimale requise
        $required_fields = [
            'wordpress' => ['title', 'content', 'meta'],
            'rankmath' => ['score', 'keyword', 'analysis']
        ];
        
        foreach ($required_fields as $section => $fields) {
            if (!isset($data[$section])) {
                $this->logger->error('Missing section in data', ['section' => $section]);
                return false;
            }
            
            foreach ($fields as $field) {
                if (!isset($data[$section][$field])) {
                    $this->logger->error('Missing field in data', [
                        'section' => $section,
                        'field' => $field
                    ]);
                    return false;
                }
            }
        }
        
        // Valider le score
        $score = intval($data['rankmath']['score']);
        if ($score < 0 || $score > 100) {
            $this->logger->error('Invalid score', ['score' => $score]);
            return false;
        }
        
        // Valider le contenu
        if (empty($data['wordpress']['content'])) {
            $this->logger->error('Empty content');
            return false;
        }
        
        // Ajouter des métadonnées système
        $data['system'] = [
            'site_url' => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'plugin_version' => RMCU_VERSION,
            'timestamp' => current_time('c')
        ];
        
        return $data;
    }
    
    /**
     * Traiter la queue
     */
    public function process_queue() {
        if ($this->status['is_processing']) {
            $this->logger->warning('Queue processor already running');
            return;
        }
        
        $this->status['is_processing'] = true;
        $start_time = time();
        
        $this->logger->info('Starting queue processing');
        
        // Obtenir les jobs en attente
        $jobs = $this->queue_manager->get_pending_jobs($this->config['batch_size']);
        
        if (empty($jobs)) {
            $this->logger->info('No jobs in queue');
            $this->status['is_processing'] = false;
            return;
        }
        
        $processed = 0;
        $failed = 0;
        
        foreach ($jobs as $job) {
            // Vérifier le temps d'exécution
            if (time() - $start_time > $this->config['max_execution_time']) {
                $this->logger->warning('Execution time limit reached');
                break;
            }
            
            // Traiter le job
            $result = $this->process_single_job($job->id);
            
            if ($result['success']) {
                $processed++;
            } else {
                $failed++;
            }
            
            // Pause entre les jobs pour ne pas surcharger
            if ($processed + $failed < count($jobs)) {
                sleep(2);
            }
        }
        
        $this->logger->info('Queue processing completed', [
            'processed' => $processed,
            'failed' => $failed,
            'duration' => time() - $start_time
        ]);
        
        $this->status['is_processing'] = false;
        
        // Déclencher un événement
        do_action('rmcu_queue_processed', $processed, $failed);
    }
    
    /**
     * Traiter un seul job
     */
    private function process_single_job($job_id) {
        $job = $this->queue_manager->get_job($job_id);
        
        if (!$job) {
            return [
                'success' => false,
                'error' => 'Job not found'
            ];
        }
        
        $this->status['current_job'] = $job_id;
        
        $this->logger->info('Processing job', [
            'job_id' => $job_id,
            'post_id' => $job->post_id,
            'attempts' => $job->attempts
        ]);
        
        // Mettre à jour le statut
        $this->queue_manager->update_job_status($job_id, 'processing');
        
        try {
            // Dispatcher vers n8n via l'API Handler
            $result = $this->send_to_n8n($job);
            
            if ($result['success']) {
                // Marquer comme complété
                $this->queue_manager->update_job_status($job_id, 'completed', [
                    'completed_at' => current_time('mysql'),
                    'result' => json_encode($result['data'])
                ]);
                
                // Mettre en cache le résultat
                $this->cache_manager->set(
                    'optimization_' . $job->post_id,
                    $result['data'],
                    3600 // 1 heure
                );
                
                // Déclencher un événement
                do_action('rmcu_job_completed', $job_id, $job->post_id, $result['data']);
                
                $this->logger->info('Job completed successfully', ['job_id' => $job_id]);
                
                return [
                    'success' => true,
                    'data' => $result['data']
                ];
                
            } else {
                throw new Exception($result['error']);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Job processing failed', [
                'job_id' => $job_id,
                'error' => $e->getMessage()
            ]);
            
            // Incrémenter les tentatives
            $attempts = $job->attempts + 1;
            
            if ($attempts >= $this->config['retry_attempts']) {
                // Marquer comme échoué
                $this->queue_manager->update_job_status($job_id, 'failed', [
                    'attempts' => $attempts,
                    'error' => $e->getMessage(),
                    'failed_at' => current_time('mysql')
                ]);
                
                // Notifier l'administrateur
                $this->notify_admin_failure($job, $e->getMessage());
                
            } else {
                // Reprogrammer pour retry
                $retry_at = date('Y-m-d H:i:s', time() + ($this->config['retry_delay'] * $attempts));
                
                $this->queue_manager->update_job_status($job_id, 'retry', [
                    'attempts' => $attempts,
                    'scheduled_at' => $retry_at,
                    'last_error' => $e->getMessage()
                ]);
            }
            
            $this->status['last_error'] = $e->getMessage();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
            
        } finally {
            $this->status['current_job'] = null;
        }
    }
    
    /**
     * Envoyer vers n8n
     */
    private function send_to_n8n($job) {
        // Utiliser une méthode personnalisée ou déléguer à l'API Handler
        $data = json_decode($job->data, true);
        
        if (!$data) {
            return [
                'success' => false,
                'error' => 'Invalid job data'
            ];
        }
        
        // Enrichir avec les métadonnées du job
        $data['job'] = [
            'id' => $job->id,
            'post_id' => $job->post_id,
            'attempts' => $job->attempts,
            'priority' => $job->priority,
            'created_at' => $job->created_at
        ];
        
        // Configuration n8n
        $settings = get_option('rmcu_settings', []);
        $webhook_url = trailingslashit($settings['n8n_url']) . 'webhook/rmcu-optimize';
        
        // Préparer la requête
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data)
        ];
        
        if (!empty($settings['api_key'])) {
            $args['headers']['Authorization'] = 'Bearer ' . $settings['api_key'];
        }
        
        // Envoyer la requête
        $response = wp_remote_post($webhook_url, $args);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200 && $response_code !== 201) {
            return [
                'success' => false,
                'error' => 'n8n returned error: ' . $response_code
            ];
        }
        
        $result = json_decode($response_body, true);
        
        if (!$result) {
            return [
                'success' => false,
                'error' => 'Invalid response from n8n'
            ];
        }
        
        return [
            'success' => true,
            'data' => $result
        ];
    }
    
    /**
     * Réessayer les jobs échoués
     */
    public function retry_failed_jobs() {
        $this->logger->info('Starting failed jobs retry');
        
        $jobs = $this->queue_manager->get_jobs_by_status('retry', 10);
        
        $retried = 0;
        foreach ($jobs as $job) {
            // Vérifier si c'est le moment de réessayer
            if (strtotime($job->scheduled_at) <= time()) {
                $this->queue_manager->update_job_status($job->id, 'pending');
                $retried++;
            }
        }
        
        $this->logger->info('Failed jobs retry completed', ['retried' => $retried]);
    }
    
    /**
     * Nettoyer les anciens jobs
     */
    public function cleanup_old_jobs() {
        $this->logger->info('Starting old jobs cleanup');
        
        // Supprimer les jobs complétés de plus de 30 jours
        $days_to_keep = 30;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
        
        $deleted = $this->queue_manager->delete_old_jobs($cutoff_date);
        
        $this->logger->info('Old jobs cleanup completed', ['deleted' => $deleted]);
    }
    
    /**
     * Handler pour save_post
     */
    public function on_post_save($post_id, $post, $update) {
        // Ignorer les auto-saves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Vérifier le type de post
        $allowed_post_types = apply_filters('rmcu_allowed_post_types', ['post', 'page']);
        if (!in_array($post->post_status, $allowed_post_types)) {
            return;
        }
        
        // Vérifier si l'auto-optimisation est activée
        $settings = get_option('rmcu_settings', []);
        if (empty($settings['auto_optimize'])) {
            return;
        }
        
        // Vérifier le score SEO actuel
        $current_score = get_post_meta($post_id, 'rank_math_seo_score', true);
        $target_score = $settings['target_score'] ?? 90;
        
        if ($current_score >= $target_score) {
            return;
        }
        
        // Programmer une optimisation
        wp_schedule_single_event(
            time() + 60, // Délai de 1 minute
            'rmcu_auto_optimize_post',
            [$post_id]
        );
    }
    
    /**
     * Handler pour trash_post
     */
    public function on_post_trash($post_id) {
        // Annuler tous les jobs en attente pour ce post
        $this->queue_manager->cancel_jobs_for_post($post_id);
    }
    
    /**
     * Notifier l'admin en cas d'échec
     */
    private function notify_admin_failure($job, $error) {
        $to = get_option('admin_email');
        $subject = '[RMCU] Optimization Job Failed';
        $post_title = get_the_title($job->post_id);
        
        $message = "An optimization job has failed after {$job->attempts} attempts.\n\n";
        $message .= "Post: {$post_title} (ID: {$job->post_id})\n";
        $message .= "Job ID: {$job->id}\n";
        $message .= "Error: {$error}\n\n";
        $message .= "Please check the job queue in the RMCU admin panel.";
        
        wp_mail($to, $subject, $message);
    }
    
    /**
     * AJAX: Dispatcher un job
     */
    public function ajax_dispatch_job() {
        check_ajax_referer('rmcu_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $data = json_decode(stripslashes($_POST['data'] ?? '{}'), true);
        $priority = sanitize_text_field($_POST['priority'] ?? 'normal');
        
        $result = $this->dispatch_optimization($post_id, $data, $priority);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Obtenir le statut de la queue
     */
    public function ajax_get_queue_status() {
        check_ajax_referer('rmcu_nonce', 'nonce');
        
        $stats = $this->queue_manager->get_queue_stats();
        
        wp_send_json_success([
            'status' => $this->status,
            'stats' => $stats
        ]);
    }
    
    /**
     * AJAX: Annuler un job
     */
    public function ajax_cancel_job() {
        check_ajax_referer('rmcu_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $job_id = intval($_POST['job_id'] ?? 0);
        
        if ($this->queue_manager->cancel_job($job_id)) {
            wp_send_json_success(['message' => 'Job cancelled']);
        } else {
            wp_send_json_error(['message' => 'Failed to cancel job']);
        }
    }
    
    /**
     * Obtenir le statut actuel
     */
    public function get_status() {
        $this->status['jobs_in_queue'] = $this->queue_manager->get_queue_count();
        return $this->status;
    }
    
    /**
     * Forcer le traitement manuel de la queue
     */
    public function force_process() {
        if (!$this->status['is_processing']) {
            $this->process_queue();
            return true;
        }
        return false;
    }
}