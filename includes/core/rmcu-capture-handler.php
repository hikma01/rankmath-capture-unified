<?php
/**
 * RMCU Capture Handler - Gestionnaire de captures de données
 *
 * @package    RMCU_Plugin
 * @subpackage RMCU_Plugin/includes
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RMCU_Capture_Handler {
    
    /**
     * Instance du logger
     */
    private $logger;
    
    /**
     * Instance du sanitizer
     */
    private $sanitizer;
    
    /**
     * Types de capture supportés
     */
    const CAPTURE_TYPES = [
        'form_submission',
        'user_interaction',
        'page_view',
        'conversion',
        'email_capture',
        'phone_capture',
        'custom_event'
    ];
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->logger = new RMCU_Logger();
        $this->init_hooks();
    }
    
    /**
     * Initialiser les hooks
     */
    private function init_hooks() {
        // Capture des soumissions de formulaires
        add_action('wp_ajax_rmcu_capture_form', [$this, 'handle_form_capture']);
        add_action('wp_ajax_nopriv_rmcu_capture_form', [$this, 'handle_form_capture']);
        
        // Capture des interactions utilisateur
        add_action('wp_ajax_rmcu_capture_interaction', [$this, 'handle_interaction_capture']);
        add_action('wp_ajax_nopriv_rmcu_capture_interaction', [$this, 'handle_interaction_capture']);
        
        // Capture des vues de page
        add_action('wp_footer', [$this, 'inject_page_view_tracker']);
        
        // Capture des conversions
        add_action('woocommerce_thankyou', [$this, 'capture_woocommerce_conversion']);
        add_action('edd_complete_purchase', [$this, 'capture_edd_conversion']);
        
        // Intégration Contact Form 7
        add_action('wpcf7_mail_sent', [$this, 'capture_cf7_submission']);
        
        // Intégration Gravity Forms
        add_action('gform_after_submission', [$this, 'capture_gravity_submission'], 10, 2);
        
        // Intégration WPForms
        add_action('wpforms_process_complete', [$this, 'capture_wpforms_submission'], 10, 4);
        
        // Intégration Elementor Forms
        add_action('elementor_pro/forms/new_record', [$this, 'capture_elementor_submission'], 10, 2);
    }
    
    /**
     * Capturer une donnée générique
     *
     * @param string $type Type de capture
     * @param array $data Données à capturer
     * @param array $metadata Métadonnées additionnelles
     * @return int|false ID de la capture ou false en cas d'échec
     */
    public function capture($type, $data, $metadata = []) {
        // Validation du type
        if (!in_array($type, self::CAPTURE_TYPES)) {
            $this->logger->error('Invalid capture type', ['type' => $type]);
            return false;
        }
        
        // Préparation des données
        $capture_data = [
            'type' => $type,
            'data' => $data,
            'metadata' => array_merge($this->get_default_metadata(), $metadata),
            'timestamp' => current_time('mysql'),
            'status' => 'pending'
        ];
        
        // Filtrer les données avant capture
        $capture_data = apply_filters('rmcu_before_capture', $capture_data, $type);
        
        // Validation des données requises
        if (!$this->validate_capture_data($capture_data)) {
            return false;
        }
        
        // Enrichissement des données
        $capture_data = $this->enrich_capture_data($capture_data);
        
        // Sauvegarde en base de données
        $capture_id = $this->save_capture($capture_data);
        
        if ($capture_id) {
            // Déclencher les actions post-capture
            do_action('rmcu_after_capture', $capture_id, $capture_data);
            
            // Traitement asynchrone si nécessaire
            if ($this->should_process_async($type)) {
                wp_schedule_single_event(time(), 'rmcu_process_capture', [$capture_id]);
            }
            
            $this->logger->info('Data captured successfully', [
                'capture_id' => $capture_id,
                'type' => $type
            ]);
        }
        
        return $capture_id;
    }
    
    /**
     * Obtenir les métadonnées par défaut
     *
     * @return array
     */
    private function get_default_metadata() {
        return [
            'ip' => $this->get_user_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'url' => $this->get_current_url(),
            'user_id' => get_current_user_id(),
            'session_id' => $this->get_session_id(),
            'device_type' => $this->detect_device_type(),
            'browser' => $this->detect_browser(),
            'country' => $this->get_user_country(),
            'language' => get_locale()
        ];
    }
    
    /**
     * Enrichir les données de capture
     *
     * @param array $capture_data
     * @return array
     */
    private function enrich_capture_data($capture_data) {
        // Ajouter les informations UTM si disponibles
        $capture_data['metadata']['utm_source'] = $_GET['utm_source'] ?? '';
        $capture_data['metadata']['utm_medium'] = $_GET['utm_medium'] ?? '';
        $capture_data['metadata']['utm_campaign'] = $_GET['utm_campaign'] ?? '';
        $capture_data['metadata']['utm_term'] = $_GET['utm_term'] ?? '';
        $capture_data['metadata']['utm_content'] = $_GET['utm_content'] ?? '';
        
        // Ajouter les informations de géolocalisation
        if (get_option('rmcu_enable_geolocation')) {
            $geo_data = $this->get_geolocation_data($capture_data['metadata']['ip']);
            $capture_data['metadata'] = array_merge($capture_data['metadata'], $geo_data);
        }
        
        // Score de lead si applicable
        if (in_array($capture_data['type'], ['form_submission', 'email_capture'])) {
            $capture_data['lead_score'] = $this->calculate_lead_score($capture_data);
        }
        
        return apply_filters('rmcu_enrich_capture_data', $capture_data);
    }
    
    /**
     * Valider les données de capture
     *
     * @param array $capture_data
     * @return bool
     */
    private function validate_capture_data($capture_data) {
        // Vérification RGPD/Consentement
        if (get_option('rmcu_require_consent') && !$this->has_user_consent()) {
            $this->logger->warning('Capture blocked: no user consent');
            return false;
        }
        
        // Vérification anti-spam
        if ($this->is_spam($capture_data)) {
            $this->logger->warning('Capture blocked: detected as spam');
            return false;
        }
        
        // Vérification des limites de taux
        if ($this->is_rate_limited($capture_data['metadata']['ip'])) {
            $this->logger->warning('Capture blocked: rate limit exceeded');
            return false;
        }
        
        return true;
    }
    
    /**
     * Sauvegarder la capture en base de données
     *
     * @param array $capture_data
     * @return int|false
     */
    private function save_capture($capture_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rmcu_captures';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'type' => $capture_data['type'],
                'data' => json_encode($capture_data['data']),
                'metadata' => json_encode($capture_data['metadata']),
                'lead_score' => $capture_data['lead_score'] ?? 0,
                'status' => $capture_data['status'],
                'created_at' => $capture_data['timestamp']
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );
        
        if ($result === false) {
            $this->logger->error('Failed to save capture', ['error' => $wpdb->last_error]);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Gérer la capture de formulaire via AJAX
     */
    public function handle_form_capture() {
        // Vérification du nonce
        if (!check_ajax_referer('rmcu_capture_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        // Récupération et sanitisation des données
        $form_data = isset($_POST['form_data']) ? $_POST['form_data'] : [];
        $form_id = sanitize_text_field($_POST['form_id'] ?? '');
        
        if (!class_exists('RMCU_Sanitizer')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rmcu-sanitizer.php';
        }
        $this->sanitizer = new RMCU_Sanitizer();
        
        $sanitized_data = $this->sanitizer->sanitize_array($form_data);
        
        // Capture des données
        $capture_id = $this->capture('form_submission', $sanitized_data, [
            'form_id' => $form_id,
            'form_type' => 'custom'
        ]);
        
        if ($capture_id) {
            wp_send_json_success([
                'message' => 'Form captured successfully',
                'capture_id' => $capture_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to capture form']);
        }
    }
    
    /**
     * Gérer la capture d'interaction via AJAX
     */
    public function handle_interaction_capture() {
        // Vérification du nonce
        if (!check_ajax_referer('rmcu_capture_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        $interaction_type = sanitize_text_field($_POST['interaction_type'] ?? '');
        $element = sanitize_text_field($_POST['element'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');
        
        $capture_id = $this->capture('user_interaction', [
            'interaction_type' => $interaction_type,
            'element' => $element,
            'value' => $value
        ]);
        
        if ($capture_id) {
            wp_send_json_success(['capture_id' => $capture_id]);
        } else {
            wp_send_json_error(['message' => 'Failed to capture interaction']);
        }
    }
    
    /**
     * Injecter le tracker de vue de page
     */
    public function inject_page_view_tracker() {
        if (!get_option('rmcu_enable_page_tracking')) {
            return;
        }
        
        $page_data = [
            'post_id' => get_the_ID(),
            'post_type' => get_post_type(),
            'title' => get_the_title(),
            'categories' => get_the_category_list(', ')
        ];
        ?>
        <script type="text/javascript">
        (function() {
            if (typeof rmcu_capture !== 'undefined') {
                rmcu_capture.trackPageView(<?php echo json_encode($page_data); ?>);
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Capturer une conversion WooCommerce
     *
     * @param int $order_id
     */
    public function capture_woocommerce_conversion($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $this->capture('conversion', [
            'order_id' => $order_id,
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'items' => $this->get_order_items($order),
            'customer_email' => $order->get_billing_email()
        ], [
            'platform' => 'woocommerce'
        ]);
    }
    
    /**
     * Capturer une soumission Contact Form 7
     *
     * @param object $contact_form
     */
    public function capture_cf7_submission($contact_form) {
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return;
        
        $data = $submission->get_posted_data();
        
        $this->capture('form_submission', $data, [
            'form_id' => $contact_form->id(),
            'form_title' => $contact_form->title(),
            'form_type' => 'cf7'
        ]);
    }
    
    /**
     * Capturer une soumission Gravity Forms
     *
     * @param array $entry
     * @param array $form
     */
    public function capture_gravity_submission($entry, $form) {
        $this->capture('form_submission', $entry, [
            'form_id' => $form['id'],
            'form_title' => $form['title'],
            'form_type' => 'gravity_forms'
        ]);
    }
    
    /**
     * Capturer une soumission WPForms
     *
     * @param array $fields
     * @param array $entry
     * @param array $form_data
     * @param int $entry_id
     */
    public function capture_wpforms_submission($fields, $entry, $form_data, $entry_id) {
        $data = [];
        foreach ($fields as $field) {
            $data[$field['name']] = $field['value'];
        }
        
        $this->capture('form_submission', $data, [
            'form_id' => $form_data['id'],
            'form_title' => $form_data['settings']['form_title'],
            'form_type' => 'wpforms',
            'entry_id' => $entry_id
        ]);
    }
    
    /**
     * Capturer une soumission Elementor
     *
     * @param object $record
     * @param object $handler
     */
    public function capture_elementor_submission($record, $handler) {
        $form_name = $record->get_form_settings('form_name');
        $raw_fields = $record->get('fields');
        
        $data = [];
        foreach ($raw_fields as $id => $field) {
            $data[$id] = $field['value'];
        }
        
        $this->capture('form_submission', $data, [
            'form_name' => $form_name,
            'form_type' => 'elementor'
        ]);
    }
    
    /**
     * Obtenir l'IP de l'utilisateur
     *
     * @return string
     */
    private function get_user_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
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
     * Obtenir l'URL actuelle
     *
     * @return string
     */
    private function get_current_url() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    
    /**
     * Obtenir l'ID de session
     *
     * @return string
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }
    
    /**
     * Détecter le type d'appareil
     *
     * @return string
     */
    private function detect_device_type() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/mobile/i', $user_agent)) {
            return 'mobile';
        } elseif (preg_match('/tablet/i', $user_agent)) {
            return 'tablet';
        }
        
        return 'desktop';
    }
    
    /**
     * Détecter le navigateur
     *
     * @return string
     */
    private function detect_browser() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
        if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
        if (strpos($user_agent, 'Safari') !== false) return 'Safari';
        if (strpos($user_agent, 'Edge') !== false) return 'Edge';
        if (strpos($user_agent, 'Opera') !== false) return 'Opera';
        
        return 'Other';
    }
    
    /**
     * Obtenir le pays de l'utilisateur
     *
     * @return string
     */
    private function get_user_country() {
        // Utiliser l'en-tête CloudFlare si disponible
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return $_SERVER['HTTP_CF_IPCOUNTRY'];
        }
        
        // Sinon, utiliser une API de géolocalisation ou retourner 'Unknown'
        return 'Unknown';
    }
    
    /**
     * Obtenir les données de géolocalisation
     *
     * @param string $ip
     * @return array
     */
    private function get_geolocation_data($ip) {
        $cache_key = 'rmcu_geo_' . md5($ip);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Appel API de géolocalisation (exemple avec ipapi.co)
        $response = wp_remote_get("https://ipapi.co/{$ip}/json/");
        
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $geo_data = [
                'city' => $data['city'] ?? '',
                'region' => $data['region'] ?? '',
                'country_name' => $data['country_name'] ?? '',
                'latitude' => $data['latitude'] ?? '',
                'longitude' => $data['longitude'] ?? ''
            ];
            
            set_transient($cache_key, $geo_data, DAY_IN_SECONDS);
            return $geo_data;
        }
        
        return [];
    }
    
    /**
     * Calculer le score de lead
     *
     * @param array $capture_data
     * @return int
     */
    private function calculate_lead_score($capture_data) {
        $score = 0;
        
        // Points basés sur le type de capture
        $type_scores = [
            'form_submission' => 20,
            'email_capture' => 15,
            'phone_capture' => 25,
            'conversion' => 50
        ];
        
        $score += $type_scores[$capture_data['type']] ?? 0;
        
        // Points pour email d'entreprise
        if (isset($capture_data['data']['email'])) {
            if (!preg_match('/@(gmail|yahoo|hotmail|outlook)\./', $capture_data['data']['email'])) {
                $score += 10;
            }
        }
        
        // Points pour informations complètes
        if (isset($capture_data['data']['phone'])) $score += 10;
        if (isset($capture_data['data']['company'])) $score += 15;
        
        // Points pour engagement (pages vues)
        $pages_viewed = get_transient('rmcu_pages_' . $capture_data['metadata']['session_id']);
        $score += min($pages_viewed * 2, 20);
        
        return min($score, 100); // Score maximum de 100
    }
    
    /**
     * Vérifier si c'est du spam
     *
     * @param array $capture_data
     * @return bool
     */
    private function is_spam($capture_data) {
        // Honeypot check
        if (!empty($capture_data['data']['honeypot'])) {
            return true;
        }
        
        // Temps de soumission trop rapide
        if (isset($capture_data['data']['form_load_time'])) {
            $time_spent = time() - $capture_data['data']['form_load_time'];
            if ($time_spent < 3) {
                return true;
            }
        }
        
        // Vérification des mots-clés spam
        $spam_keywords = get_option('rmcu_spam_keywords', []);
        $data_string = json_encode($capture_data['data']);
        
        foreach ($spam_keywords as $keyword) {
            if (stripos($data_string, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Vérifier la limite de taux
     *
     * @param string $ip
     * @return bool
     */
    private function is_rate_limited($ip) {
        $limit = get_option('rmcu_rate_limit', 30);
        $window = get_option('rmcu_rate_window', 60); // en secondes
        
        $key = 'rmcu_rate_' . md5($ip);
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            set_transient($key, 1, $window);
            return false;
        }
        
        if ($attempts >= $limit) {
            return true;
        }
        
        set_transient($key, $attempts + 1, $window);
        return false;
    }
    
    /**
     * Vérifier le consentement utilisateur
     *
     * @return bool
     */
    private function has_user_consent() {
        // Vérifier le cookie de consentement
        return isset($_COOKIE['rmcu_consent']) && $_COOKIE['rmcu_consent'] === 'true';
    }
    
    /**
     * Déterminer si le traitement doit être asynchrone
     *
     * @param string $type
     * @return bool
     */
    private function should_process_async($type) {
        $async_types = get_option('rmcu_async_types', ['conversion', 'form_submission']);
        return in_array($type, $async_types);
    }
    
    /**
     * Obtenir les items d'une commande
     *
     * @param WC_Order $order
     * @return array
     */
    private function get_order_items($order) {
        $items = [];
        
        foreach ($order->get_items() as $item) {
            $items[] = [
                'product_id' => $item->get_product_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total()
            ];
        }
        
        return $items;
    }
}