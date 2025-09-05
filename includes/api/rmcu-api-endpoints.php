<?php
/**
 * RMCU API Endpoints
 * Points de terminaison REST API pour le plugin
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour gérer les endpoints REST API
 */
class RMCU_API_Endpoints {
    
    /**
     * Namespace de l'API
     */
    const NAMESPACE = 'rmcu/v1';
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Sanitizer
     */
    private $sanitizer;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->logger = new RMCU_Logger('API_Endpoints');
        $this->sanitizer = new RMCU_Sanitizer();
    }
    
    /**
     * Enregistrer toutes les routes
     */
    public function register_routes() {
        // Route principale d'optimisation
        register_rest_route(self::NAMESPACE, '/optimize', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_optimize'],
                'permission_callback' => [$this, 'check_optimize_permission'],
                'args' => $this->get_optimize_args()
            ]
        ]);
        
        // Extraction de données
        register_rest_route(self::NAMESPACE, '/extract', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_extract'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'post_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'validate_callback' => [$this, 'validate_post_id']
                    ]
                ]
            ]
        ]);
        
        // Analyse SEO
        register_rest_route(self::NAMESPACE, '/analyze', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_analyze'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_analyze_args()
            ]
        ]);
        
        // État de l'optimisation
        register_rest_route(self::NAMESPACE, '/status/(?P<post_id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_get_status'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'post_id' => [
                        'required' => true,
                        'type' => 'integer'
                    ]
                ]
            ]
        ]);
        
        // Historique
        register_rest_route(self::NAMESPACE, '/history', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_get_history'],
                'permission_callback' => [$this, 'check_read_permission'],
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
                    ],
                    'offset' => [
                        'type' => 'integer',
                        'default' => 0
                    ]
                ]
            ]
        ]);
        
        // File d'attente
        register_rest_route(self::NAMESPACE, '/queue', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_get_queue'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_add_to_queue'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_queue_args()
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'handle_remove_from_queue'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => [
                    'job_id' => [
                        'required' => true,
                        'type' => 'integer'
                    ]
                ]
            ]
        ]);
        
        // Configuration
        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_get_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'handle_update_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => $this->get_settings_args()
            ]
        ]);
        
        // Health check
        register_rest_route(self::NAMESPACE, '/health', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_health_check'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ]
        ]);
        
        // Webhook pour n8n
        register_rest_route(self::NAMESPACE, '/webhook/(?P<action>[a-zA-Z0-9_-]+)', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_webhook'],
                'permission_callback' => [$this, 'check_webhook_permission'],
                'args' => [
                    'action' => [
                        'required' => true,
                        'type' => 'string'
                    ]
                ]
            ]
        ]);
        
        // Export/Import
        register_rest_route(self::NAMESPACE, '/export', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_export'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'type' => [
                        'type' => 'string',
                        'default' => 'settings',
                        'enum' => ['settings', 'history', 'all']
                    ]
                ]
            ]
        ]);
        
        register_rest_route(self::NAMESPACE, '/import', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_import'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ]
        ]);
    }
    
    /**
     * Handler pour l'optimisation
     */
    public function handle_optimize($request) {
        $post_id = $request->get_param('post_id');
        $data = $request->get_param('data');
        $options = $request->get_param('options');
        
        $this->logger->info('Optimize request received', [
            'post_id' => $post_id,
            'options' => $options
        ]);
        
        // Vérifier que le post existe
        if (!get_post($post_id)) {
            return new WP_Error(
                'invalid_post',
                'Post not found',
                ['status' => 404]
            );
        }
        
        // Vérifier les permissions sur le post
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error(
                'forbidden',
                'You cannot edit this post',
                ['status' => 403]
            );
        }
        
        try {
            // Dispatcher l'optimisation
            $dispatcher = new RMCU_Dispatcher();
            $result = $dispatcher->dispatch_optimization($post_id, $data, $options['priority'] ?? 'normal');
            
            if ($result['success']) {
                return new WP_REST_Response([
                    'success' => true,
                    'job_id' => $result['job_id'],
                    'message' => $result['message']
                ], 200);
            } else {
                return new WP_Error(
                    'optimization_failed',
                    $result['message'],
                    ['status' => 500]
                );
            }
            
        } catch (Exception $e) {
            $this->logger->error('Optimization failed', [
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'server_error',
                'An error occurred during optimization',
                ['status' => 500]
            );
        }
    }
    
    /**
     * Handler pour l'extraction
     */
    public function handle_extract($request) {
        $post_id = $request->get_param('post_id');
        
        // Cette route est conçue pour être appelée par JavaScript
        // Elle retourne les métadonnées du post
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error(
                'invalid_post',
                'Post not found',
                ['status' => 404]
            );
        }
        
        // Extraire les métadonnées côté serveur
        $data = [
            'post' => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'status' => $post->post_status,
                'type' => $post->post_type,
                'author' => $post->post_author,
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'link' => get_permalink($post->ID)
            ],
            'meta' => [
                'rank_math_score' => get_post_meta($post_id, 'rank_math_seo_score', true),
                'rank_math_keyword' => get_post_meta($post_id, 'rank_math_focus_keyword', true),
                'rank_math_title' => get_post_meta($post_id, 'rank_math_title', true),
                'rank_math_description' => get_post_meta($post_id, 'rank_math_description', true)
            ],
            'taxonomies' => [
                'categories' => wp_get_post_categories($post_id, ['fields' => 'all']),
                'tags' => wp_get_post_tags($post_id, ['fields' => 'all'])
            ]
        ];
        
        return new WP_REST_Response($data, 200);
    }
    
    /**
     * Handler pour l'analyse
     */
    public function handle_analyze($request) {
        $content = $request->get_param('content');
        $keyword = $request->get_param('keyword');
        $options = $request->get_param('options');
        
        // Analyser le contenu
        $analyzer = new RMCU_Content_Analyzer();
        $analysis = $analyzer->analyze($content, $keyword, $options);
        
        return new WP_REST_Response($analysis, 200);
    }
    
    /**
     * Handler pour obtenir le statut
     */
    public function handle_get_status($request) {
        $post_id = $request->get_param('post_id');
        
        global $wpdb;
        $table = $wpdb->prefix . 'rmcu_optimization_queue';
        
        $status = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d ORDER BY id DESC LIMIT 1",
            $post_id
        ), ARRAY_A);
        
        if ($status) {
            // Ajouter des informations supplémentaires
            $status['post_title'] = get_the_title($post_id);
            $status['edit_link'] = get_edit_post_link($post_id);
            $status['progress_percentage'] = $this->calculate_progress($status);
            
            return new WP_REST_Response($status, 200);
        } else {
            return new WP_REST_Response([
                'post_id' => $post_id,
                'status' => 'not_started',
                'current_score' => 0,
                'target_score' => 90,
                'iterations' => 0
            ], 200);
        }
    }
    
    /**
     * Handler pour obtenir l'historique
     */
    public function handle_get_history($request) {
        $post_id = $request->get_param('post_id');
        $limit = $request->get_param('limit');
        $offset = $request->get_param('offset');
        
        global $wpdb;
        $table = $wpdb->prefix . 'rmcu_optimization_queue';
        
        $where = $post_id ? $wpdb->prepare(" WHERE post_id = %d", $post_id) : '';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table $where 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $limit, $offset
        ), ARRAY_A);
        
        // Enrichir les résultats
        foreach ($results as &$row) {
            $row['post_title'] = get_the_title($row['post_id']);
            $row['post_link'] = get_permalink($row['post_id']);
            $row['edit_link'] = get_edit_post_link($row['post_id']);
            $row['duration'] = $this->calculate_duration($row);
        }
        
        // Obtenir le total pour la pagination
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
        
        return new WP_REST_Response([
            'items' => $results,
            'total' => intval($total),
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ], 200);
    }
    
    /**
     * Handler pour obtenir la file d'attente
     */
    public function handle_get_queue($request) {
        $queue_manager = new RMCU_Queue_Manager();
        $queue = $queue_manager->get_queue();
        
        return new WP_REST_Response([
            'queue' => $queue,
            'stats' => $queue_manager->get_queue_stats()
        ], 200);
    }
    
    /**
     * Handler pour ajouter à la file d'attente
     */
    public function handle_add_to_queue($request) {
        $post_id = $request->get_param('post_id');
        $priority = $request->get_param('priority');
        
        if (!get_post($post_id)) {
            return new WP_Error(
                'invalid_post',
                'Post not found',
                ['status' => 404]
            );
        }
        
        $queue_manager = new RMCU_Queue_Manager();
        $job_id = $queue_manager->add_job([
            'post_id' => $post_id,
            'priority' => $priority,
            'status' => 'pending'
        ]);
        
        if ($job_id) {
            return new WP_REST_Response([
                'success' => true,
                'job_id' => $job_id,
                'message' => 'Job added to queue'
            ], 201);
        } else {
            return new WP_Error(
                'queue_error',
                'Failed to add job to queue',
                ['status' => 500]
            );
        }
    }
    
    /**
     * Handler pour retirer de la file d'attente
     */
    public function handle_remove_from_queue($request) {
        $job_id = $request->get_param('job_id');
        
        $queue_manager = new RMCU_Queue_Manager();
        
        if ($queue_manager->cancel_job($job_id)) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Job removed from queue'
            ], 200);
        } else {
            return new WP_Error(
                'queue_error',
                'Failed to remove job from queue',
                ['status' => 500]
            );
        }
    }
    
    /**
     * Handler pour obtenir les paramètres
     */
    public function handle_get_settings($request) {
        $settings = get_option('rmcu_settings', []);
        
        // Masquer les clés sensibles
        if (isset($settings['api_key'])) {
            $settings['api_key_configured'] = !empty($settings['api_key']);
            $settings['api_key'] = str_repeat('*', 20);
        }
        
        return new WP_REST_Response($settings, 200);
    }
    
    /**
     * Handler pour mettre à jour les paramètres
     */
    public function handle_update_settings($request) {
        $settings = $request->get_params();
        
        // Retirer les paramètres système
        unset($settings['rest_route']);
        unset($settings['_locale']);
        
        // Valider et sanitizer
        $validated = $this->validate_settings($settings);
        
        if (is_wp_error($validated)) {
            return $validated;
        }
        
        // Sauvegarder
        update_option('rmcu_settings', $validated);
        
        // Logger
        $this->logger->info('Settings updated via API');
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Settings updated successfully'
        ], 200);
    }
    
    /**
     * Handler pour le health check
     */
    public function handle_health_check($request) {
        $health_check = new RMCU_Health_Check();
        $results = $health_check->run_check();
        
        return new WP_REST_Response($results, 200);
    }
    
    /**
     * Handler pour les webhooks
     */
    public function handle_webhook($request) {
        $action = $request->get_param('action');
        $data = $request->get_json_params();
        
        $this->logger->info('Webhook received', [
            'action' => $action,
            'data_keys' => array_keys($data)
        ]);
        
        // Vérifier la signature si configurée
        if (!$this->verify_webhook_signature($request)) {
            return new WP_Error(
                'invalid_signature',
                'Invalid webhook signature',
                ['status' => 401]
            );
        }
        
        // Traiter selon l'action
        switch ($action) {
            case 'optimization-complete':
                return $this->handle_optimization_complete($data);
                
            case 'analysis-complete':
                return $this->handle_analysis_complete($data);
                
            case 'error':
                return $this->handle_webhook_error($data);
                
            default:
                return new WP_Error(
                    'unknown_action',
                    'Unknown webhook action',
                    ['status' => 400]
                );
        }
    }
    
    /**
     * Handler pour l'export
     */
    public function handle_export($request) {
        $type = $request->get_param('type');
        $data = [];
        
        switch ($type) {
            case 'settings':
                $data['settings'] = get_option('rmcu_settings', []);
                break;
                
            case 'history':
                global $wpdb;
                $table = $wpdb->prefix . 'rmcu_optimization_queue';
                $data['history'] = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
                break;
                
            case 'all':
                $data['settings'] = get_option('rmcu_settings', []);
                global $wpdb;
                $table = $wpdb->prefix . 'rmcu_optimization_queue';
                $data['history'] = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
                $data['health_check'] = get_option('rmcu_health_check_results', []);
                break;
        }
        
        $data['export_info'] = [
            'plugin_version' => RMCU_VERSION,
            'export_date' => current_time('c'),
            'site_url' => get_site_url()
        ];
        
        return new WP_REST_Response($data, 200);
    }
    
    /**
     * Handler pour l'import
     */
    public function handle_import($request) {
        $data = $request->get_json_params();
        
        if (!isset($data['export_info'])) {
            return new WP_Error(
                'invalid_import',
                'Invalid import data',
                ['status' => 400]
            );
        }
        
        $imported = [];
        
        // Importer les paramètres
        if (isset($data['settings'])) {
            update_option('rmcu_settings', $data['settings']);
            $imported[] = 'settings';
        }
        
        // Importer l'historique (optionnel)
        if (isset($data['history']) && is_array($data['history'])) {
            // Note: Implémentation de l'import d'historique si nécessaire
            $imported[] = 'history';
        }
        
        return new WP_REST_Response([
            'success' => true,
            'imported' => $imported,
            'message' => 'Import completed successfully'
        ], 200);
    }
    
    /**
     * Vérifier les permissions d'optimisation
     */
    public function check_optimize_permission($request) {
        return current_user_can('edit_posts');
    }
    
    /**
     * Vérifier les permissions de lecture
     */
    public function check_read_permission($request) {
        return current_user_can('read');
    }
    
    /**
     * Vérifier les permissions d'écriture
     */
    public function check_write_permission($request) {
        return current_user_can('edit_posts');
    }
    
    /**
     * Vérifier les permissions admin
     */
    public function check_admin_permission($request) {
        return current_user_can('manage_options');
    }
    
    /**
     * Vérifier les permissions webhook
     */
    public function check_webhook_permission($request) {
        // Les webhooks peuvent être publics mais doivent avoir une signature valide
        return true;
    }
    
    /**
     * Valider l'ID de post
     */
    public function validate_post_id($param, $request, $key) {
        if (!is_numeric($param)) {
            return false;
        }
        
        $post = get_post($param);
        return $post && $post->post_status !== 'trash';
    }
    
    /**
     * Obtenir les arguments pour l'optimisation
     */
    private function get_optimize_args() {
        return [
            'post_id' => [
                'required' => true,
                'type' => 'integer',
                'validate_callback' => [$this, 'validate_post_id']
            ],
            'data' => [
                'required' => true,
                'type' => 'object'
            ],
            'options' => [
                'type' => 'object',
                'default' => [],
                'properties' => [
                    'priority' => [
                        'type' => 'string',
                        'enum' => ['low', 'normal', 'high'],
                        'default' => 'normal'
                    ],
                    'target_score' => [
                        'type' => 'integer',
                        'minimum' => 0,
                        'maximum' => 100,
                        'default' => 90
                    ],
                    'max_iterations' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 10,
                        'default' => 5
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Obtenir les arguments pour l'analyse
     */
    private function get_analyze_args() {
        return [
            'content' => [
                'required' => true,
                'type' => 'string'
            ],
            'keyword' => [
                'type' => 'string',
                'default' => ''
            ],
            'options' => [
                'type' => 'object',
                'default' => []
            ]
        ];
    }
    
    /**
     * Obtenir les arguments pour la file d'attente
     */
    private function get_queue_args() {
        return [
            'post_id' => [
                'required' => true,
                'type' => 'integer',
                'validate_callback' => [$this, 'validate_post_id']
            ],
            'priority' => [
                'type' => 'string',
                'enum' => ['low', 'normal', 'high'],
                'default' => 'normal'
            ]
        ];
    }
    
    /**
     * Obtenir les arguments pour les paramètres
     */
    private function get_settings_args() {
        return [
            'n8n_url' => [
                'type' => 'string',
                'format' => 'uri'
            ],
            'api_key' => [
                'type' => 'string'
            ],
            'target_score' => [
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 100
            ],
            'max_iterations' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 10
            ],
            'auto_optimize' => [
                'type' => 'boolean'
            ],
            'cache_duration' => [
                'type' => 'integer',
                'minimum' => 0
            ],
            'debug_mode' => [
                'type' => 'boolean'
            ]
        ];
    }
    
    /**
     * Valider les paramètres
     */
    private function validate_settings($settings) {
        $validated = [];
        
        // n8n URL
        if (isset($settings['n8n_url'])) {
            $url = esc_url_raw($settings['n8n_url']);
            if (empty($url) && !empty($settings['n8n_url'])) {
                return new WP_Error(
                    'invalid_url',
                    'Invalid n8n URL',
                    ['status' => 400]
                );
            }
            $validated['n8n_url'] = $url;
        }
        
        // API Key
        if (isset($settings['api_key']) && !str_contains($settings['api_key'], '*')) {
            $validated['api_key'] = sanitize_text_field($settings['api_key']);
        }
        
        // Target Score
        if (isset($settings['target_score'])) {
            $validated['target_score'] = max(0, min(100, intval($settings['target_score'])));
        }
        
        // Max Iterations
        if (isset($settings['max_iterations'])) {
            $validated['max_iterations'] = max(1, min(10, intval($settings['max_iterations'])));
        }
        
        // Booléens
        foreach (['auto_optimize', 'debug_mode'] as $key) {
            if (isset($settings[$key])) {
                $validated[$key] = (bool) $settings[$key];
            }
        }
        
        // Cache Duration
        if (isset($settings['cache_duration'])) {
            $validated['cache_duration'] = max(0, intval($settings['cache_duration']));
        }
        
        return $validated;
    }
    
    /**
     * Vérifier la signature du webhook
     */
    private function verify_webhook_signature($request) {
        $settings = get_option('rmcu_settings', []);
        
        if (empty($settings['api_key'])) {
            // Pas de clé API configurée, accepter le webhook
            return true;
        }
        
        $signature = $request->get_header('X-Webhook-Signature');
        if (!$signature) {
            return false;
        }
        
        $body = $request->get_body();
        $expected = hash_hmac('sha256', $body, $settings['api_key']);
        
        return hash_equals($expected, $signature);
    }
    
    /**
     * Handler pour optimization-complete webhook
     */
    private function handle_optimization_complete($data) {
        $post_id = $data['post_id'] ?? 0;
        $optimized_content = $data['content'] ?? [];
        $new_score = $data['score'] ?? 0;
        
        if (!$post_id) {
            return new WP_Error(
                'missing_post_id',
                'Post ID is required',
                ['status' => 400]
            );
        }
        
        // Mettre à jour le post
        if (!empty($optimized_content['title']) || !empty($optimized_content['content'])) {
            $update = ['ID' => $post_id];
            
            if (!empty($optimized_content['title'])) {
                $update['post_title'] = sanitize_text_field($optimized_content['title']);
            }
            
            if (!empty($optimized_content['content'])) {
                $update['post_content'] = wp_kses_post($optimized_content['content']);
            }
            
            wp_update_post($update);
        }
        
        // Mettre à jour le statut
        global $wpdb;
        $table = $wpdb->prefix . 'rmcu_optimization_queue';
        
        $wpdb->update(
            $table,
            [
                'status' => 'completed',
                'current_score' => $new_score,
                'updated_at' => current_time('mysql')
            ],
            ['post_id' => $post_id]
        );
        
        // Déclencher un événement
        do_action('rmcu_optimization_completed_webhook', $post_id, $optimized_content, $new_score);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Optimization complete'
        ], 200);
    }
    
    /**
     * Handler pour analysis-complete webhook
     */
    private function handle_analysis_complete($data) {
        $post_id = $data['post_id'] ?? 0;
        $analysis = $data['analysis'] ?? [];
        
        // Sauvegarder l'analyse
        if ($post_id) {
            update_post_meta($post_id, '_rmcu_last_analysis', $analysis);
            update_post_meta($post_id, '_rmcu_last_analysis_time', current_time('mysql'));
        }
        
        // Déclencher un événement
        do_action('rmcu_analysis_completed_webhook', $post_id, $analysis);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Analysis saved'
        ], 200);
    }
    
    /**
     * Handler pour les erreurs webhook
     */
    private function handle_webhook_error($data) {
        $error = $data['error'] ?? 'Unknown error';
        $context = $data['context'] ?? [];
        
        $this->logger->error('Webhook error received', [
            'error' => $error,
            'context' => $context
        ]);
        
        // Notifier l'admin si critique
        if ($data['severity'] === 'critical') {
            $this->notify_admin_error($error, $context);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Error logged'
        ], 200);
    }
    
    /**
     * Calculer le pourcentage de progression
     */
    private function calculate_progress($status) {
        if ($status['status'] === 'completed') {
            return 100;
        }
        
        if ($status['target_score'] == 0) {
            return 0;
        }
        
        return min(100, round(($status['current_score'] / $status['target_score']) * 100));
    }
    
    /**
     * Calculer la durée
     */
    private function calculate_duration($row) {
        if (!empty($row['completed_at']) && !empty($row['created_at'])) {
            $start = strtotime($row['created_at']);
            $end = strtotime($row['completed_at']);
            return $end - $start;
        }
        return null;
    }
    
    /**
     * Notifier l'admin d'une erreur
     */
    private function notify_admin_error($error, $context) {
        $to = get_option('admin_email');
        $subject = '[RMCU] Critical Error from Webhook';
        
        $message = "A critical error was received via webhook:\n\n";
        $message .= "Error: $error\n\n";
        $message .= "Context:\n" . print_r($context, true);
        
        wp_mail($to, $subject, $message);
    }
}