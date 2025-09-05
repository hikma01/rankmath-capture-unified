<?php
/**
 * RMCU Webhook Handler
 * Gestionnaire des webhooks pour n8n et autres intégrations
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de gestion des webhooks
 */
class RMCU_Webhook_Handler {
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Database
     */
    private $database;
    
    /**
     * URL du webhook n8n
     */
    private $webhook_url;
    
    /**
     * Clé API n8n
     */
    private $api_key;
    
    /**
     * Timeout pour les requêtes
     */
    private $timeout = 30;
    
    /**
     * Nombre de tentatives
     */
    private $max_retries = 3;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->logger = new RMCU_Logger('Webhook_Handler');
        
        // Charger la configuration
        $this->load_config();
        
        // Initialiser les dépendances
        $this->init_dependencies();
        
        // Enregistrer les hooks
        $this->init_hooks();
    }
    
    /**
     * Charger la configuration
     */
    private function load_config() {
        $settings = get_option('rmcu_settings', []);
        
        $this->webhook_url = isset($settings['n8n_webhook_url']) 
            ? $settings['n8n_webhook_url'] 
            : '';
            
        $this->api_key = isset($settings['n8n_api_key']) 
            ? $settings['n8n_api_key'] 
            : '';
            
        $this->timeout = isset($settings['webhook_timeout']) 
            ? intval($settings['webhook_timeout']) 
            : 30;
            
        $this->max_retries = isset($settings['webhook_max_retries']) 
            ? intval($settings['webhook_max_retries']) 
            : 3;
    }
    
    /**
     * Initialiser les dépendances
     */
    private function init_dependencies() {
        if (class_exists('RMCU_Database')) {
            $this->database = new RMCU_Database();
        }
    }
    
    /**
     * Initialiser les hooks
     */
    private function init_hooks() {
        // AJAX pour envoyer manuellement
        add_action('wp_ajax_rmcu_send_webhook', [$this, 'ajax_send_webhook']);
        
        // AJAX pour tester la connexion
        add_action('wp_ajax_rmcu_test_webhook', [$this, 'ajax_test_webhook']);
        
        // Cron pour les envois en attente
        add_action('rmcu_process_webhook_queue', [$this, 'process_queue']);
        
        // Hook après création de capture
        add_action('rmcu_capture_created', [$this, 'on_capture_created'], 10, 2);
        
        // Hook après mise à jour de capture
        add_action('rmcu_capture_updated', [$this, 'on_capture_updated'], 10, 2);
    }
    
    /**
     * Envoyer une capture via webhook
     * 
     * @param int $capture_id ID de la capture
     * @param array $additional_data Données supplémentaires
     * @return bool|WP_Error
     */
    public function send_capture($capture_id, $additional_data = []) {
        try {
            // Vérifier la configuration
            if (empty($this->webhook_url)) {
                $this->logger->warning('No webhook URL configured');
                return new WP_Error('no_webhook_url', __('No webhook URL configured', 'rmcu'));
            }
            
            // Récupérer la capture
            $capture = $this->database->get_capture($capture_id);
            if (!$capture) {
                return new WP_Error('capture_not_found', __('Capture not found', 'rmcu'));
            }
            
            // Préparer les données
            $data = $this->prepare_capture_data($capture, $additional_data);
            
            // Log de début
            $this->logger->info('Sending capture to webhook', [
                'capture_id' => $capture_id,
                'webhook_url' => $this->webhook_url
            ]);
            
            // Envoyer avec retry
            $response = $this->send_with_retry($this->webhook_url, $data);
            
            if (is_wp_error($response)) {
                $this->logger->error('Failed to send webhook', [
                    'capture_id' => $capture_id,
                    'error' => $response->get_error_message()
                ]);
                
                // Ajouter à la queue pour réessayer plus tard
                $this->add_to_queue($capture_id, $data);
                
                return $response;
            }
            
            // Marquer comme envoyé
            $this->mark_as_sent($capture_id, $response);
            
            $this->logger->info('Webhook sent successfully', [
                'capture_id' => $capture_id,
                'response_code' => wp_remote_retrieve_response_code($response)
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Exception in send_capture', [
                'capture_id' => $capture_id,
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error('webhook_exception', $e->getMessage());
        }
    }
    
    /**
     * Envoyer des données personnalisées via webhook
     * 
     * @param array $data Données à envoyer
     * @param string $webhook_url URL du webhook (optionnel)
     * @return bool|WP_Error
     */
    public function send_custom($data, $webhook_url = null) {
        $url = $webhook_url ?: $this->webhook_url;
        
        if (empty($url)) {
            return new WP_Error('no_webhook_url', __('No webhook URL provided', 'rmcu'));
        }
        
        $response = $this->send_with_retry($url, $data);
        
        if (is_wp_error($response)) {
            $this->logger->error('Failed to send custom webhook', [
                'error' => $response->get_error_message()
            ]);
            return $response;
        }
        
        return true;
    }
    
    /**
     * Tester la connexion webhook
     * 
     * @param string $webhook_url URL à tester
     * @return array Résultat du test
     */
    public function test_connection($webhook_url = null) {
        $url = $webhook_url ?: $this->webhook_url;
        
        if (empty($url)) {
            return [
                'success' => false,
                'message' => __('No webhook URL configured', 'rmcu')
            ];
        }
        
        $test_data = [
            'test' => true,
            'plugin' => 'RMCU',
            'version' => RMCU_VERSION,
            'timestamp' => current_time('c'),
            'site_url' => home_url()
        ];
        
        $this->logger->info('Testing webhook connection', ['url' => $url]);
        
        $response = $this->send_request($url, $test_data);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'error_code' => $response->get_error_code()
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $success = $code >= 200 && $code < 300;
        
        return [
            'success' => $success,
            'message' => $success 
                ? __('Connection successful', 'rmcu') 
                : sprintf(__('Server returned %d', 'rmcu'), $code),
            'response_code' => $code,
            'response_body' => $body
        ];
    }
    
    /**
     * Préparer les données de capture pour l'envoi
     */
    private function prepare_capture_data($capture, $additional_data = []) {
        $data = [
            'capture_id' => $capture->id,
            'capture_type' => $capture->capture_type,
            'user_id' => $capture->user_id,
            'post_id' => $capture->post_id,
            'capture_data' => json_decode($capture->capture_data, true),
            'metadata' => json_decode($capture->metadata, true),
            'created_at' => $capture->created_at,
            'site_info' => [
                'url' => home_url(),
                'name' => get_bloginfo('name'),
                'admin_email' => get_bloginfo('admin_email')
            ],
            'timestamp' => current_time('c')
        ];
        
        // Ajouter les informations du post si disponible
        if ($capture->post_id) {
            $post = get_post($capture->post_id);
            if ($post) {
                $data['post_info'] = [
                    'title' => $post->post_title,
                    'url' => get_permalink($post),
                    'status' => $post->post_status,
                    'type' => $post->post_type
                ];
                
                // Ajouter le score RankMath si disponible
                if (class_exists('RMCU_RankMath_Integration')) {
                    $rankmath = new RMCU_RankMath_Integration();
                    $data['rankmath_score'] = $rankmath->get_score($capture->post_id);
                }
            }
        }
        
        // Ajouter les informations de l'utilisateur
        if ($capture->user_id) {
            $user = get_userdata($capture->user_id);
            if ($user) {
                $data['user_info'] = [
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                    'role' => implode(', ', $user->roles)
                ];
            }
        }
        
        // Fusionner avec les données additionnelles
        $data = array_merge($data, $additional_data);
        
        // Appliquer les filtres
        return apply_filters('rmcu_webhook_data', $data, $capture);
    }
    
    /**
     * Envoyer avec retry
     */
    private function send_with_retry($url, $data) {
        $attempt = 0;
        $last_error = null;
        
        while ($attempt < $this->max_retries) {
            $attempt++;
            
            $response = $this->send_request($url, $data);
            
            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                
                // Succès
                if ($code >= 200 && $code < 300) {
                    return $response;
                }
                
                // Ne pas réessayer pour les erreurs client (4xx)
                if ($code >= 400 && $code < 500) {
                    return new WP_Error(
                        'webhook_client_error',
                        sprintf(__('Client error: %d', 'rmcu'), $code)
                    );
                }
            }
            
            $last_error = $response;
            
            // Attendre avant de réessayer (exponential backoff)
            if ($attempt < $this->max_retries) {
                $wait = pow(2, $attempt - 1);
                $this->logger->debug('Retrying webhook', [
                    'attempt' => $attempt,
                    'wait' => $wait
                ]);
                sleep($wait);
            }
        }
        
        return $last_error ?: new WP_Error(
            'webhook_max_retries',
            __('Maximum retry attempts reached', 'rmcu')
        );
    }
    
    /**
     * Envoyer une requête HTTP
     */
    private function send_request($url, $data) {
        $headers = [
            'Content-Type' => 'application/json'
        ];
        
        // Ajouter l'authentification si configurée
        if (!empty($this->api_key)) {
            $headers['Authorization'] = 'Bearer ' . $this->api_key;
            $headers['X-API-Key'] = $this->api_key;
        }
        
        // Ajouter des headers personnalisés
        $headers['X-RMCU-Version'] = RMCU_VERSION;
        $headers['X-RMCU-Site'] = home_url();
        
        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => $this->timeout,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'sslverify' => apply_filters('rmcu_webhook_sslverify', true),
            'data_format' => 'body'
        ];
        
        // Appliquer les filtres
        $args = apply_filters('rmcu_webhook_request_args', $args, $url, $data);
        
        return wp_remote_post($url, $args);
    }
    
    /**
     * Ajouter à la queue
     */
    private function add_to_queue($capture_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_webhook_queue';
        
        $wpdb->insert(
            $table,
            [
                'capture_id' => $capture_id,
                'webhook_url' => $this->webhook_url,
                'data' => json_encode($data),
                'attempts' => 1,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'next_retry' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s']
        );
        
        $this->logger->info('Added to webhook queue', [
            'capture_id' => $capture_id,
            'queue_id' => $wpdb->insert_id
        ]);
        
        // Planifier le traitement si pas déjà planifié
        if (!wp_next_scheduled('rmcu_process_webhook_queue')) {
            wp_schedule_single_event(time() + 300, 'rmcu_process_webhook_queue');
        }
    }
    
    /**
     * Traiter la queue
     */
    public function process_queue() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_webhook_queue';
        
        // Récupérer les webhooks en attente
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table 
                 WHERE status = 'pending' 
                 AND next_retry <= %s 
                 ORDER BY created_at ASC 
                 LIMIT 10",
                current_time('mysql')
            )
        );
        
        if (empty($items)) {
            $this->logger->debug('No webhooks in queue');
            return;
        }
        
        $this->logger->info('Processing webhook queue', [
            'count' => count($items)
        ]);
        
        foreach ($items as $item) {
            $this->process_queue_item($item);
        }
        
        // Replanifier si encore des éléments
        $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
        if ($pending > 0) {
            wp_schedule_single_event(time() + 300, 'rmcu_process_webhook_queue');
        }
    }
    
    /**
     * Traiter un élément de la queue
     */
    private function process_queue_item($item) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_webhook_queue';
        $data = json_decode($item->data, true);
        
        $response = $this->send_request($item->webhook_url, $data);
        
        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            
            if ($code >= 200 && $code < 300) {
                // Succès - supprimer de la queue
                $wpdb->delete($table, ['id' => $item->id], ['%d']);
                
                $this->logger->info('Queue item processed successfully', [
                    'queue_id' => $item->id,
                    'capture_id' => $item->capture_id
                ]);
                
                return;
            }
        }
        
        // Échec - mettre à jour les tentatives
        $attempts = $item->attempts + 1;
        $max_attempts = 10;
        
        if ($attempts >= $max_attempts) {
            // Trop de tentatives - marquer comme échoué
            $wpdb->update(
                $table,
                ['status' => 'failed', 'updated_at' => current_time('mysql')],
                ['id' => $item->id],
                ['%s', '%s'],
                ['%d']
            );
            
            $this->logger->error('Queue item failed permanently', [
                'queue_id' => $item->id,
                'capture_id' => $item->capture_id,
                'attempts' => $attempts
            ]);
        } else {
            // Planifier la prochaine tentative (exponential backoff)
            $next_retry = date('Y-m-d H:i:s', time() + pow(2, $attempts) * 60);
            
            $wpdb->update(
                $table,
                [
                    'attempts' => $attempts,
                    'next_retry' => $next_retry,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $item->id],
                ['%d', '%s', '%s'],
                ['%d']
            );
            
            $this->logger->debug('Queue item retry scheduled', [
                'queue_id' => $item->id,
                'attempts' => $attempts,
                'next_retry' => $next_retry
            ]);
        }
    }
    
    /**
     * Marquer comme envoyé
     */
    private function mark_as_sent($capture_id, $response) {
        $metadata = [
            'webhook_sent' => true,
            'webhook_sent_at' => current_time('mysql'),
            'webhook_response_code' => wp_remote_retrieve_response_code($response)
        ];
        
        // Mettre à jour les métadonnées de la capture
        if ($this->database) {
            $capture = $this->database->get_capture($capture_id);
            if ($capture) {
                $existing_meta = json_decode($capture->metadata, true) ?: [];
                $new_meta = array_merge($existing_meta, $metadata);
                
                $this->database->update_capture($capture_id, [
                    'metadata' => json_encode($new_meta)
                ]);
            }
        }
        
        // Déclencher un hook
        do_action('rmcu_webhook_sent', $capture_id, $response);
    }
    
    /**
     * Handler pour capture créée
     */
    public function on_capture_created($capture_id, $capture_data) {
        $settings = get_option('rmcu_settings', []);
        
        if (!empty($settings['auto_send_n8n'])) {
            $this->send_capture($capture_id, [
                'event' => 'capture_created'
            ]);
        }
    }
    
    /**
     * Handler pour capture mise à jour
     */
    public function on_capture_updated($capture_id, $capture_data) {
        $settings = get_option('rmcu_settings', []);
        
        if (!empty($settings['send_updates_n8n'])) {
            $this->send_capture($capture_id, [
                'event' => 'capture_updated'
            ]);
        }
    }
    
    /**
     * AJAX : Envoyer webhook manuellement
     */
    public function ajax_send_webhook() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $capture_id = isset($_POST['capture_id']) ? intval($_POST['capture_id']) : 0;
        
        if (!$capture_id) {
            wp_send_json_error(['message' => __('Invalid capture ID', 'rmcu')]);
        }
        
        $result = $this->send_capture($capture_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        }
        
        wp_send_json_success([
            'message' => __('Webhook sent successfully', 'rmcu')
        ]);
    }
    
    /**
     * AJAX : Tester la connexion
     */
    public function ajax_test_webhook() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $webhook_url = isset($_POST['webhook_url']) ? esc_url_raw($_POST['webhook_url']) : null;
        
        $result = $this->test_connection($webhook_url);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Créer la table de queue
     */
    public static function create_queue_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rmcu_webhook_queue';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            capture_id int(11) NOT NULL,
            webhook_url text NOT NULL,
            data longtext,
            attempts int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            next_retry datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY capture_id (capture_id),
            KEY status (status),
            KEY next_retry (next_retry)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Obtenir les statistiques des webhooks
     */
    public function get_statistics() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_webhook_queue';
        
        $stats = [
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'"),
            'total_sent' => get_option('rmcu_webhooks_sent_total', 0),
            'last_sent' => get_option('rmcu_webhook_last_sent', null)
        ];
        
        return $stats;
    }
    
    /**
     * Nettoyer la queue
     */
    public function clean_queue($days_old = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_webhook_queue';
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE created_at < %s AND status IN ('failed', 'completed')",
                $date_limit
            )
        );
        
        $this->logger->info('Cleaned webhook queue', [
            'deleted' => $deleted,
            'days_old' => $days_old
        ]);
        
        return $deleted;
    }
}