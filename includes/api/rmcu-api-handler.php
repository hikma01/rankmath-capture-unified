<?php
/**
 * RMCU API Handler
 * Gestionnaire principal des requêtes API
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour gérer les requêtes API
 */
class RMCU_API_Handler {
    
    /**
     * Instance du logger
     */
    private $logger;
    
    /**
     * Instance du sanitizer
     */
    private $sanitizer;
    
    /**
     * Configuration n8n
     */
    private $n8n_config;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->logger = new RMCU_Logger('API_Handler');
        $this->sanitizer = new RMCU_Sanitizer();
        
        // Charger la configuration n8n
        $this->load_n8n_config();
        
        // Enregistrer les hooks AJAX
        $this->register_ajax_hooks();
        
        // Enregistrer les hooks de l'API REST
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    /**
     * Charger la configuration n8n
     */
    private function load_n8n_config() {
        $settings = get_option('rmcu_settings', []);
        
        $this->n8n_config = [
            'url' => $settings['n8n_url'] ?? '',
            'api_key' => $settings['api_key'] ?? '',
            'timeout' => $settings['api_timeout'] ?? 30,
            'webhook_path' => $settings['webhook_path'] ?? '/webhook/rmcu-optimize'
        ];
    }
    
    /**
     * Enregistrer les hooks AJAX
     */
    private function register_ajax_hooks() {
        // Actions pour les utilisateurs connectés
        add_action('wp_ajax_rmcu_dispatch', [$this, 'handle_ajax_dispatch']);
        add_action('wp_ajax_rmcu_get_status', [$this, 'handle_ajax_get_status']);
        add_action('wp_ajax_rmcu_log_error', [$this, 'handle_ajax_log_error']);
        add_action('wp_ajax_rmcu_save_settings', [$this, 'handle_ajax_save_settings']);
        
        // Actions publiques (si nécessaire)
        add_action('wp_ajax_nopriv_rmcu_webhook', [$this, 'handle_webhook']);
    }
    
    /**
     * Enregistrer les routes REST
     */
    public function register_rest_routes() {
        register_rest_route('rmcu/v1', '/dispatch', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_rest_dispatch'],
            'permission_callback' => [$this, 'check_rest_permission'],
            'args' => [
                'action' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['optimize', 'analyze', 'extract']
                ],
                'data' => [
                    'required' => true,
                    'type' => 'object'
                ]
            ]
        ]);
        
        register_rest_route('rmcu/v1', '/status/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_rest_status'],
            'permission_callback' => [$this, 'check_rest_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer'
                ]
            ]
        ]);
        
        register_rest_route('rmcu/v1', '/history', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_rest_history'],
            'permission_callback' => [$this, 'check_rest_permission'],
            'args' => [
                'post_id' => [
                    'type' => 'integer',
                    'default' => 0
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100
                ]
            ]
        ]);
        
        register_rest_route('rmcu/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_rest_webhook'],
            'permission_callback' => '__return_true', // Webhook public
        ]);
    }
    
    /**
     * Vérifier les permissions REST
     */
    public function check_rest_permission($request) {
        // Vérifier le nonce si présent
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && !wp_verify_nonce($nonce, 'wp_rest')) {
            return false;
        }
        
        // Vérifier les capabilities
        return current_user_can('edit_posts');
    }
    
    /**
     * Gérer la requête de dispatch AJAX
     */
    public function handle_ajax_dispatch() {
        // Vérifier le nonce
        if (!check_ajax_referer('rmcu_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }
        
        // Récupérer et valider les données
        $action = sanitize_text_field($_POST['action_type'] ?? 'optimize');
        $data = json_decode(stripslashes($_POST['data'] ?? '{}'), true);
        
        if (!$data) {
            wp_send_json_error(['message' => 'Invalid data']);
            return;
        }
        
        // Dispatcher vers n8n
        $result = $this->dispatch_to_n8n($action, $data);
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }
    
    /**
     * Gérer la requête de dispatch REST
     */
    public function handle_rest_dispatch($request) {
        $action = $request->get_param('action');
        $data = $request->get_param('data');
        
        // Logger la requête
        $this->logger->info('REST dispatch request', [
            'action' => $action,
            'post_id' => $data['wordpress']['meta']['postId'] ?? 0
        ]);
        
        // Dispatcher vers n8n
        $result = $this->dispatch_to_n8n($action, $data);
        
        if ($result['success']) {
            return new WP_REST_Response($result['data'], 200);
        } else {
            return new WP_Error('dispatch_failed', $result['error'], ['status' => 500]);
        }
    }
    
    /**
     * Dispatcher vers n8n
     */
    private function dispatch_to_n8n($action, $data) {
        // Vérifier la configuration
        if (empty($this->n8n_config['url'])) {
            $this->logger->error('n8n URL not configured');
            return [
                'success' => false,
                'error' => 'n8n URL not configured'
            ];
        }
        
        // Préparer l'URL du webhook
        $webhook_url = trailingslashit($this->n8n_config['url']) . ltrim($this->n8n_config['webhook_path'], '/');
        
        // Ajouter des métadonnées
        $data['metadata'] = array_merge($data['metadata'] ?? [], [
            'action' => $action,
            'site_url' => get_site_url(),
            'plugin_version' => RMCU_VERSION,
            'timestamp' => current_time('c'),
            'user_id' => get_current_user_id()
        ]);
        
        // Préparer la requête
        $args = [
            'method' => 'POST',
            'timeout' => $this->n8n_config['timeout'],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data)
        ];
        
        // Ajouter l'API key si configurée
        if (!empty($this->n8n_config['api_key'])) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->n8n_config['api_key'];
        }
        
        // Logger la requête
        $this->logger->debug('Sending to n8n', [
            'url' => $webhook_url,
            'action' => $action
        ]);
        
        // Envoyer la requête
        $response = wp_remote_post($webhook_url, $args);
        
        // Gérer les erreurs de connexion
        if (is_wp_error($response)) {
            $this->logger->error('n8n connection failed', [
                'error' => $response->get_error_message()
            ]);
            return [
                'success' => false,
                'error' => 'Connection to n8n failed: ' . $response->get_error_message()
            ];
        }
        
        // Vérifier le code de réponse
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200 && $response_code !== 201) {
            $this->logger->error('n8n returned error', [
                'code' => $response_code,
                'body' => $response_body
            ]);
            return [
                'success' => false,
                'error' => 'n8n error: ' . $response_code
            ];
        }
        
        // Parser la réponse
        $result = json_decode($response_body, true);
        
        if (!$result) {
            $this->logger->error('Invalid n8n response', [
                'body' => $response_body
            ]);
            return [
                'success' => false,
                'error' => 'Invalid response from n8n'
            ];
        }
        
        // Sauvegarder dans la base de données
        $this->save_optimization_record($action, $data, $result);
        
        $this->logger->info('n8n dispatch successful', [
            'action' => $action,
            'response' => $result
        ]);
        
        return [
            'success' => true,
            'data' => $result
        ];
    }
    
    /**
     * Sauvegarder un enregistrement d'optimisation
     */
    private function save_optimization_record($action, $request_data, $response_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_optimization_queue';
        
        $post_id = $request_data['wordpress']['meta']['postId'] ?? 0;
        $current_score = $request_data['rankmath']['score'] ?? 0;
        
        // Vérifier si un enregistrement existe
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d",
            $post_id
        ));
        
        if ($existing) {
            // Mettre à jour
            $wpdb->update(
                $table,
                [
                    'current_score' => $current_score,
                    'iterations' => $existing->iterations + 1,
                    'status' => 'processing',
                    'updated_at' => current_time('mysql')
                ],
                ['post_id' => $post_id]
            );
        } else {
            // Créer
            $wpdb->insert(
                $table,
                [
                    'post_id' => $post_id,
                    'status' => 'processing',
                    'target_score' => $request_data['metadata']['targetScore'] ?? 90,
                    'current_score' => $current_score,
                    'iterations' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ]
            );
        }
    }
    
    /**
     * Gérer le webhook de retour
     */
    public function handle_rest_webhook($request) {
        // Logger la requête
        $this->logger->info('Webhook received', [
            'body' => $request->get_body()
        ]);
        
        // Parser les données
        $data = $request->get_json_params();
        
        if (!$data) {
            return new WP_Error('invalid_data', 'Invalid webhook data', ['status' => 400]);
        }
        
        // Vérifier la signature si configurée
        if (!empty($this->n8n_config['api_key'])) {
            $signature = $request->get_header('X-Webhook-Signature');
            $expected = hash_hmac('sha256', $request->get_body(), $this->n8n_config['api_key']);
            
            if (!hash_equals($expected, $signature)) {
                $this->logger->error('Invalid webhook signature');
                return new WP_Error('invalid_signature', 'Invalid signature', ['status' => 401]);
            }
        }
        
        // Traiter les données
        $result = $this->process_webhook_data($data);
        
        if ($result['success']) {
            return new WP_REST_Response(['success' => true], 200);
        } else {
            return new WP_Error('processing_failed', $result['error'], ['status' => 500]);
        }
    }
    
    /**
     * Traiter les données du webhook
     */
    private function process_webhook_data($data) {
        // Extraire les informations
        $post_id = $data['post_id'] ?? 0;
        $optimized_content = $data['optimized_content'] ?? [];
        $score = $data['new_score'] ?? 0;
        
        if (!$post_id || !$optimized_content) {
            return [
                'success' => false,
                'error' => 'Missing required data'
            ];
        }
        
        // Mettre à jour le post
        $update_data = [
            'ID' => $post_id
        ];
        
        if (!empty($optimized_content['title'])) {
            $update_data['post_title'] = sanitize_text_field($optimized_content['title']);
        }
        
        if (!empty($optimized_content['content'])) {
            $update_data['post_content'] = wp_kses_post($optimized_content['content']);
        }
        
        $result = wp_update_post($update_data, true);
        
        if (is_wp_error($result)) {
            $this->logger->error('Failed to update post', [
                'post_id' => $post_id,
                'error' => $result->get_error_message()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to update post: ' . $result->get_error_message()
            ];
        }
        
        // Mettre à jour les métadonnées SEO si nécessaire
        if (!empty($optimized_content['seo'])) {
            $this->update_seo_meta($post_id, $optimized_content['seo']);
        }
        
        // Mettre à jour le statut dans la queue
        $this->update_queue_status($post_id, 'completed', $score);
        
        // Déclencher un hook pour d'autres plugins
        do_action('rmcu_optimization_completed', $post_id, $optimized_content, $score);
        
        $this->logger->info('Post updated successfully', [
            'post_id' => $post_id,
            'new_score' => $score
        ]);
        
        return ['success' => true];
    }
    
    /**
     * Mettre à jour les métadonnées SEO
     */
    private function update_seo_meta($post_id, $seo_data) {
        // RankMath
        if (class_exists('RankMath')) {
            if (!empty($seo_data['title'])) {
                update_post_meta($post_id, 'rank_math_title', sanitize_text_field($seo_data['title']));
            }
            
            if (!empty($seo_data['description'])) {
                update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($seo_data['description']));
            }
            
            if (!empty($seo_data['focus_keyword'])) {
                update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($seo_data['focus_keyword']));
            }
        }
        
        // Yoast (si présent)
        if (defined('WPSEO_VERSION')) {
            if (!empty($seo_data['title'])) {
                update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($seo_data['title']));
            }
            
            if (!empty($seo_data['description'])) {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($seo_data['description']));
            }
        }
    }
    
    /**
     * Mettre à jour le statut dans la queue
     */
    private function update_queue_status($post_id, $status, $score = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rmcu_optimization_queue';
        
        $update_data = [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ];
        
        if ($score !== null) {
            $update_data['current_score'] = $score;
        }
        
        $wpdb->update($table, $update_data, ['post_id' => $post_id]);
    }
    
    /**
     * Gérer la requête de statut
     */
    public function handle_ajax_get_status() {
        // Vérifier le nonce
        if (!check_ajax_referer('rmcu_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'Invalid post ID']);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'rmcu_optimization_queue';
        
        $status = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d",
            $post_id
        ), ARRAY_A);
        
        if ($status) {
            wp_send_json_success($status);
        } else {
            wp_send_json_success([
                'post_id' => $post_id,
                'status' => 'not_started',
                'current_score' => 0,
                'iterations' => 0
            ]);
        }
    }
    
    /**
     * Gérer les logs d'erreur
     */
    public function handle_ajax_log_error() {
        // Vérifier le nonce
        if (!check_ajax_referer('rmcu_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }
        
        $log_data = json_decode(stripslashes($_POST['log_data'] ?? '{}'), true);
        
        if ($log_data) {
            $this->logger->error('Client error', $log_data);
        }
        
        wp_send_json_success(['logged' => true]);
    }
    
    /**
     * Gérer la sauvegarde des paramètres
     */
    public function handle_ajax_save_settings() {
        // Vérifier le nonce
        if (!check_ajax_referer('rmcu_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }
        
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }
        
        $settings = [
            'n8n_url' => esc_url_raw($_POST['n8n_url'] ?? ''),
            'api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
            'target_score' => intval($_POST['target_score'] ?? 90),
            'max_iterations' => intval($_POST['max_iterations'] ?? 5),
            'auto_optimize' => isset($_POST['auto_optimize']),
            'cache_duration' => intval($_POST['cache_duration'] ?? 3600),
            'debug_mode' => isset($_POST['debug_mode'])
        ];
        
        update_option('rmcu_settings', $settings);
        
        // Recharger la configuration
        $this->load_n8n_config();
        
        wp_send_json_success(['message' => 'Settings saved successfully']);
    }
    
    /**
     * Obtenir l'historique des optimisations
     */
    public function handle_rest_history($request) {
        $post_id = $request->get_param('post_id');
        $limit = $request->get_param('limit');
        
        global $wpdb;
        $table = $wpdb->prefix . 'rmcu_optimization_queue';
        
        $where = $post_id ? $wpdb->prepare("WHERE post_id = %d", $post_id) : '';
        
        $results = $wpdb->get_results(
            "SELECT * FROM $table $where ORDER BY updated_at DESC LIMIT $limit",
            ARRAY_A
        );
        
        // Enrichir avec les titres des posts
        foreach ($results as &$row) {
            $row['post_title'] = get_the_title($row['post_id']);
            $row['edit_link'] = get_edit_post_link($row['post_id']);
        }
        
        return new WP_REST_Response($results, 200);
    }
    
    /**
     * Tester la connexion n8n
     */
    public function test_n8n_connection() {
        if (empty($this->n8n_config['url'])) {
            return [
                'success' => false,
                'message' => 'n8n URL not configured'
            ];
        }
        
        $test_url = trailingslashit($this->n8n_config['url']) . 'health';
        
        $response = wp_remote_get($test_url, [
            'timeout' => 5,
            'headers' => !empty($this->n8n_config['api_key']) ? [
                'Authorization' => 'Bearer ' . $this->n8n_config['api_key']
            ] : []
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200 || $code === 204) {
            return [
                'success' => true,
                'message' => 'Connection successful'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Connection failed with code: ' . $code
            ];
        }
    }
}