<?php
/**
 * RMCU Settings Page
 * Page de paramètres dans l'administration WordPress
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour gérer la page de paramètres
 */
class RMCU_Settings_Page {
    
    /**
     * Slug de la page
     */
    const PAGE_SLUG = 'rmcu-settings';
    
    /**
     * Groupe d'options
     */
    const OPTION_GROUP = 'rmcu_settings_group';
    
    /**
     * Nom de l'option
     */
    const OPTION_NAME = 'rmcu_settings';
    
    /**
     * Instance singleton
     */
    private static $instance = null;
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Obtenir l'instance singleton
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur
     */
    private function __construct() {
        $this->logger = new RMCU_Logger('Settings_Page');
        $this->init_hooks();
    }
    
    /**
     * Initialiser les hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_rmcu_test_webhook', [$this, 'ajax_test_webhook']);
        add_action('wp_ajax_rmcu_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_rmcu_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_rmcu_import_settings', [$this, 'ajax_import_settings']);
    }
    
    /**
     * Ajouter les pages de menu
     */
    public function add_menu_pages() {
        // Menu principal
        add_menu_page(
            __('RM Capture', 'rmcu'),
            __('RM Capture', 'rmcu'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page'],
            'dashicons-video-alt2',
            100
        );
        
        // Sous-menu Paramètres
        add_submenu_page(
            self::PAGE_SLUG,
            __('Paramètres', 'rmcu'),
            __('Paramètres', 'rmcu'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
        
        // Sous-menu Captures
        add_submenu_page(
            self::PAGE_SLUG,
            __('Captures', 'rmcu'),
            __('Captures', 'rmcu'),
            'manage_options',
            'rmcu-captures',
            [$this, 'render_captures_page']
        );
        
        // Sous-menu Logs
        add_submenu_page(
            self::PAGE_SLUG,
            __('Logs', 'rmcu'),
            __('Logs', 'rmcu'),
            'manage_options',
            'rmcu-logs',
            [$this, 'render_logs_page']
        );
    }
    
    /**
     * Enregistrer les paramètres
     */
    public function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [$this, 'sanitize_settings']
        );
        
        // Section Général
        add_settings_section(
            'rmcu_general_section',
            __('Paramètres généraux', 'rmcu'),
            [$this, 'render_general_section'],
            self::PAGE_SLUG
        );
        
        // Section n8n
        add_settings_section(
            'rmcu_n8n_section',
            __('Configuration n8n', 'rmcu'),
            [$this, 'render_n8n_section'],
            self::PAGE_SLUG
        );
        
        // Section Capture
        add_settings_section(
            'rmcu_capture_section',
            __('Options de capture', 'rmcu'),
            [$this, 'render_capture_section'],
            self::PAGE_SLUG
        );
        
        // Section Avancé
        add_settings_section(
            'rmcu_advanced_section',
            __('Paramètres avancés', 'rmcu'),
            [$this, 'render_advanced_section'],
            self::PAGE_SLUG
        );
        
        // Champs de paramètres
        $this->add_settings_fields();
    }
    
    /**
     * Ajouter les champs de paramètres
     */
    private function add_settings_fields() {
        // Champs Général
        add_settings_field(
            'enable_plugin',
            __('Activer le plugin', 'rmcu'),
            [$this, 'render_checkbox_field'],
            self::PAGE_SLUG,
            'rmcu_general_section',
            [
                'label_for' => 'enable_plugin',
                'description' => __('Activer ou désactiver toutes les fonctionnalités du plugin', 'rmcu')
            ]
        );
        
        // Champs n8n
        add_settings_field(
            'n8n_webhook_url',
            __('URL Webhook n8n', 'rmcu'),
            [$this, 'render_text_field'],
            self::PAGE_SLUG,
            'rmcu_n8n_section',
            [
                'label_for' => 'n8n_webhook_url',
                'type' => 'url',
                'placeholder' => 'https://your-n8n.com/webhook/xxx',
                'description' => __('URL du webhook n8n pour recevoir les captures', 'rmcu')
            ]
        );
        
        add_settings_field(
            'n8n_api_key',
            __('Clé API n8n', 'rmcu'),
            [$this, 'render_password_field'],
            self::PAGE_SLUG,
            'rmcu_n8n_section',
            [
                'label_for' => 'n8n_api_key',
                'description' => __('Clé API pour sécuriser les communications (optionnel)', 'rmcu')
            ]
        );
        
        add_settings_field(
            'auto_send_n8n',
            __('Envoi automatique', 'rmcu'),
            [$this, 'render_checkbox_field'],
            self::PAGE_SLUG,
            'rmcu_n8n_section',
            [
                'label_for' => 'auto_send_n8n',
                'description' => __('Envoyer automatiquement les captures à n8n', 'rmcu')
            ]
        );
        
        // Champs Capture
        add_settings_field(
            'enable_video',
            __('Capture vidéo', 'rmcu'),
            [$this, 'render_checkbox_field'],
            self::PAGE_SLUG,
            'rmcu_capture_section',
            [
                'label_for' => 'enable_video',
                'description' => __('Activer la capture vidéo webcam', 'rmcu')
            ]
        );
        
        add_settings_field(
            'enable_audio',
            __('Capture audio', 'rmcu'),
            [$this, 'render_checkbox_field'],
            self::PAGE_SLUG,
            'rmcu_capture_section',
            [
                'label_for' => 'enable_audio',
                'description' => __('Activer la capture audio microphone', 'rmcu')
            ]
        );
        
        add_settings_field(
            'enable_screen',
            __('Capture écran', 'rmcu'),
            [$this, 'render_checkbox_field'],
            self::PAGE_SLUG,
            'rmcu_capture_section',
            [
                'label_for' => 'enable_screen',
                'description' => __('Activer la capture d\'écran', 'rmcu')
            ]
        );
        
        add_settings_field(
            'max_duration',
            __('Durée maximale', 'rmcu'),
            [$this, 'render_number_field'],
            self::PAGE_SLUG,
            'rmcu_capture_section',
            [
                'label_for' => 'max_duration',
                'min' => 10,
                'max' => 600,
                'step' => 10,
                'description' => __('Durée maximale en secondes (10-600)', 'rmcu')
            ]
        );
        
        add_settings_field(
            'video_quality',
            __('Qualité vidéo', 'rmcu'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'rmcu_capture_section',
            [
                'label_for' => 'video_quality',
                'options' => [
                    'low' => __('Basse (480p)', 'rmcu'),
                    'medium' => __('Moyenne (720p)', 'rmcu'),
                    'high' => __('Haute (1080p)', 'rmcu'),
                    'ultra' => __('Ultra (4K)', 'rmcu')
                ],
                'description' => __('Qualité de la capture vidéo', 'rmcu')
            ]
        );
        
        // Champs Avancé
        add_settings_field(
            'enable_debug',
            __('Mode debug', 'rmcu'),
            [$this, 'render_checkbox_field'],
            self::PAGE_SLUG,
            'rmcu_advanced_section',
            [
                'label_for' => 'enable_debug',
                'description' => __('Activer les logs détaillés', 'rmcu')
            ]
        );
        
        add_settings_field(
            'retention_days',
            __('Rétention des données', 'rmcu'),
            [$this, 'render_number_field'],
            self::PAGE_SLUG,
            'rmcu_advanced_section',
            [
                'label_for' => 'retention_days',
                'min' => 1,
                'max' => 365,
                'step' => 1,
                'description' => __('Nombre de jours de conservation des captures (1-365)', 'rmcu')
            ]
        );
        
        add_settings_field(
            'allowed_roles',
            __('Rôles autorisés', 'rmcu'),
            [$this, 'render_roles_field'],
            self::PAGE_SLUG,
            'rmcu_advanced_section',
            [
                'label_for' => 'allowed_roles',
                'description' => __('Rôles autorisés à utiliser les captures', 'rmcu')
            ]
        );
    }
    
    /**
     * Render field methods
     */
    public function render_checkbox_field($args) {
        $options = get_option(self::OPTION_NAME);
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : false;
        ?>
        <label for="<?php echo esc_attr($args['label_for']); ?>">
            <input type="checkbox" 
                   id="<?php echo esc_attr($args['label_for']); ?>"
                   name="<?php echo self::OPTION_NAME; ?>[<?php echo esc_attr($args['label_for']); ?>]"
                   value="1"
                   <?php checked($value, 1); ?>
            />
            <?php if (isset($args['description'])): ?>
                <span class="description"><?php echo esc_html($args['description']); ?></span>
            <?php endif; ?>
        </label>
        <?php
    }
    
    public function render_text_field($args) {
        $options = get_option(self::OPTION_NAME);
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        $type = isset($args['type']) ? $args['type'] : 'text';
        ?>
        <input type="<?php echo esc_attr($type); ?>"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo self::OPTION_NAME; ?>[<?php echo esc_attr($args['label_for']); ?>]"
               value="<?php echo esc_attr($value); ?>"
               placeholder="<?php echo esc_attr($args['placeholder'] ?? ''); ?>"
               class="regular-text"
        />
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }
    
    public function render_password_field($args) {
        $options = get_option(self::OPTION_NAME);
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        ?>
        <div class="password-field-wrapper">
            <input type="password"
                   id="<?php echo esc_attr($args['label_for']); ?>"
                   name="<?php echo self::OPTION_NAME; ?>[<?php echo esc_attr($args['label_for']); ?>]"
                   value="<?php echo esc_attr($value); ?>"
                   class="regular-text"
            />
            <button type="button" class="button button-secondary toggle-password" data-target="<?php echo esc_attr($args['label_for']); ?>">
                <span class="dashicons dashicons-visibility"></span>
            </button>
        </div>
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }
    
    public function render_number_field($args) {
        $options = get_option(self::OPTION_NAME);
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : $args['min'];
        ?>
        <input type="number"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo self::OPTION_NAME; ?>[<?php echo esc_attr($args['label_for']); ?>]"
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($args['min']); ?>"
               max="<?php echo esc_attr($args['max']); ?>"
               step="<?php echo esc_attr($args['step']); ?>"
               class="small-text"
        />
        <?php if (isset($args['description'])): ?>
            <span class="description"><?php echo esc_html($args['description']); ?></span>
        <?php endif; ?>
        <?php
    }
    
    public function render_select_field($args) {
        $options = get_option(self::OPTION_NAME);
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>"
                name="<?php echo self::OPTION_NAME; ?>[<?php echo esc_attr($args['label_for']); ?>]">
            <?php foreach ($args['options'] as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }
    
    public function render_roles_field($args) {
        $options = get_option(self::OPTION_NAME);
        $selected_roles = isset($options[$args['label_for']]) ? $options[$args['label_for']] : ['administrator'];
        $wp_roles = wp_roles();
        ?>
        <fieldset>
            <?php foreach ($wp_roles->roles as $role_slug => $role): ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox"
                           name="<?php echo self::OPTION_NAME; ?>[<?php echo esc_attr($args['label_for']); ?>][]"
                           value="<?php echo esc_attr($role_slug); ?>"
                           <?php checked(in_array($role_slug, $selected_roles)); ?>
                    />
                    <?php echo esc_html($role['name']); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render section descriptions
     */
    public function render_general_section() {
        echo '<p>' . __('Configurez les paramètres généraux du plugin.', 'rmcu') . '</p>';
    }
    
    public function render_n8n_section() {
        echo '<p>' . __('Configurez la connexion avec votre instance n8n.', 'rmcu') . '</p>';
        echo '<button type="button" class="button button-secondary" id="test-webhook">' . __('Tester la connexion', 'rmcu') . '</button>';
        echo '<span id="test-result" style="margin-left: 10px;"></span>';
    }
    
    public function render_capture_section() {
        echo '<p>' . __('Configurez les options de capture média.', 'rmcu') . '</p>';
    }
    
    public function render_advanced_section() {
        echo '<p>' . __('Paramètres avancés et options de développement.', 'rmcu') . '</p>';
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        // Checkboxes
        $checkboxes = ['enable_plugin', 'auto_send_n8n', 'enable_video', 'enable_audio', 'enable_screen', 'enable_debug'];
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = isset($input[$checkbox]) ? 1 : 0;
        }
        
        // URLs
        if (isset($input['n8n_webhook_url'])) {
            $sanitized['n8n_webhook_url'] = esc_url_raw($input['n8n_webhook_url']);
        }
        
        // Text fields
        if (isset($input['n8n_api_key'])) {
            $sanitized['n8n_api_key'] = sanitize_text_field($input['n8n_api_key']);
        }
        
        // Numbers
        if (isset($input['max_duration'])) {
            $sanitized['max_duration'] = absint($input['max_duration']);
            $sanitized['max_duration'] = max(10, min(600, $sanitized['max_duration']));
        }
        
        if (isset($input['retention_days'])) {
            $sanitized['retention_days'] = absint($input['retention_days']);
            $sanitized['retention_days'] = max(1, min(365, $sanitized['retention_days']));
        }
        
        // Select
        if (isset($input['video_quality'])) {
            $valid_qualities = ['low', 'medium', 'high', 'ultra'];
            $sanitized['video_quality'] = in_array($input['video_quality'], $valid_qualities) 
                ? $input['video_quality'] 
                : 'medium';
        }
        
        // Roles
        if (isset($input['allowed_roles']) && is_array($input['allowed_roles'])) {
            $sanitized['allowed_roles'] = array_map('sanitize_text_field', $input['allowed_roles']);
        } else {
            $sanitized['allowed_roles'] = ['administrator'];
        }
        
        return $sanitized;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions nécessaires.', 'rmcu'));
        }
        ?>
        <div class="wrap rmcu-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php settings_errors(); ?>
            
            <div class="rmcu-settings-container">
                <div class="rmcu-tabs">
                    <ul class="rmcu-tab-nav">
                        <li><a href="#general" class="active"><?php _e('Général', 'rmcu'); ?></a></li>
                        <li><a href="#n8n"><?php _e('n8n', 'rmcu'); ?></a></li>
                        <li><a href="#capture"><?php _e('Capture', 'rmcu'); ?></a></li>
                        <li><a href="#advanced"><?php _e('Avancé', 'rmcu'); ?></a></li>
                    </ul>
                    
                    <form method="post" action="options.php" class="rmcu-settings-form">
                        <?php settings_fields(self::OPTION_GROUP); ?>
                        
                        <div id="general" class="rmcu-tab-content active">
                            <?php do_settings_sections(self::PAGE_SLUG); ?>
                        </div>
                        
                        <div id="n8n" class="rmcu-tab-content">
                            <!-- n8n settings rendered by sections -->
                        </div>
                        
                        <div id="capture" class="rmcu-tab-content">
                            <!-- capture settings rendered by sections -->
                        </div>
                        
                        <div id="advanced" class="rmcu-tab-content">
                            <!-- advanced settings rendered by sections -->
                        </div>
                        
                        <?php submit_button(__('Enregistrer les modifications', 'rmcu')); ?>
                    </form>
                </div>
                
                <div class="rmcu-sidebar">
                    <div class="rmcu-card">
                        <h3><?php _e('Statut', 'rmcu'); ?></h3>
                        <?php $this->render_status_widget(); ?>
                    </div>
                    
                    <div class="rmcu-card">
                        <h3><?php _e('Actions rapides', 'rmcu'); ?></h3>
                        <button class="button button-secondary full-width" id="export-settings">
                            <?php _e('Exporter les paramètres', 'rmcu'); ?>
                        </button>
                        <button class="button button-secondary full-width" id="import-settings">
                            <?php _e('Importer les paramètres', 'rmcu'); ?>
                        </button>
                        <button class="button button-secondary full-width" id="clear-cache">
                            <?php _e('Vider le cache', 'rmcu'); ?>
                        </button>
                    </div>
                    
                    <div class="rmcu-card">
                        <h3><?php _e('Documentation', 'rmcu'); ?></h3>
                        <ul>
                            <li><a href="#" target="_blank"><?php _e('Guide d\'installation', 'rmcu'); ?></a></li>
                            <li><a href="#" target="_blank"><?php _e('Configuration n8n', 'rmcu'); ?></a></li>
                            <li><a href="#" target="_blank"><?php _e('FAQ', 'rmcu'); ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render status widget
     */
    private function render_status_widget() {
        $options = get_option(self::OPTION_NAME);
        $is_active = isset($options['enable_plugin']) && $options['enable_plugin'];
        $webhook_configured = !empty($options['n8n_webhook_url']);
        $rankmath_active = class_exists('RankMath');
        ?>
        <div class="rmcu-status-list">
            <div class="rmcu-status-item">
                <span class="dashicons dashicons-<?php echo $is_active ? 'yes' : 'no'; ?>"></span>
                <?php _e('Plugin actif', 'rmcu'); ?>
            </div>
            <div class="rmcu-status-item">
                <span class="dashicons dashicons-<?php echo $webhook_configured ? 'yes' : 'no'; ?>"></span>
                <?php _e('Webhook configuré', 'rmcu'); ?>
            </div>
            <div class="rmcu-status-item">
                <span class="dashicons dashicons-<?php echo $rankmath_active ? 'yes' : 'warning'; ?>"></span>
                <?php _e('RankMath', 'rmcu'); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render captures page
     */
    public function render_captures_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions nécessaires.', 'rmcu'));
        }
        
        // Récupérer les captures depuis la base de données
        global $wpdb;
        $table_name = $wpdb->prefix . 'rmcu_captures';
        $captures = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 50");
        ?>
        <div class="wrap">
            <h1><?php _e('Captures', 'rmcu'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'rmcu'); ?></th>
                        <th><?php _e('Type', 'rmcu'); ?></th>
                        <th><?php _e('Post', 'rmcu'); ?></th>
                        <th><?php _e('Utilisateur', 'rmcu'); ?></th>
                        <th><?php _e('Date', 'rmcu'); ?></th>
                        <th><?php _e('Actions', 'rmcu'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($captures): ?>
                        <?php foreach ($captures as $capture): ?>
                            <tr>
                                <td><?php echo esc_html($capture->id); ?></td>
                                <td>
                                    <span class="capture-type capture-type-<?php echo esc_attr($capture->capture_type); ?>">
                                        <?php echo esc_html($capture->capture_type); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($capture->post_id): ?>
                                        <a href="<?php echo get_edit_post_link($capture->post_id); ?>">
                                            <?php echo get_the_title($capture->post_id); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $user = get_userdata($capture->user_id);
                                    echo $user ? esc_html($user->display_name) : '-';
                                    ?>
                                </td>
                                <td><?php echo esc_html($capture->created_at); ?></td>
                                <td>
                                    <button class="button button-small view-capture" data-id="<?php echo esc_attr($capture->id); ?>">
                                        <?php _e('Voir', 'rmcu'); ?>
                                    </button>
                                    <button class="button button-small send-to-n8n" data-id="<?php echo esc_attr($capture->id); ?>">
                                        <?php _e('Envoyer à n8n', 'rmcu'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6"><?php _e('Aucune capture trouvée.', 'rmcu'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render logs page
     */
    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n\'avez pas les permissions nécessaires.', 'rmcu'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Logs', 'rmcu'); ?></h1>
            
            <div class="rmcu-logs-viewer">
                <div class="rmcu-logs-controls">
                    <select id="log-level">
                        <option value="all"><?php _e('Tous', 'rmcu'); ?></option>
                        <option value="error"><?php _e('Erreurs', 'rmcu'); ?></option>
                        <option value="warning"><?php _e('Avertissements', 'rmcu'); ?></option>
                        <option value="info"><?php _e('Info', 'rmcu'); ?></option>
                        <option value="debug"><?php _e('Debug', 'rmcu'); ?></option>
                    </select>
                    <button class="button" id="refresh-logs"><?php _e('Rafraîchir', 'rmcu'); ?></button>
                    <button class="button" id="clear-logs"><?php _e('Effacer', 'rmcu'); ?></button>
                </div>
                
                <div class="rmcu-logs-content">
                    <pre id="logs-output"><?php echo $this->get_recent_logs(); ?></pre>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get recent logs
     */
    private function get_recent_logs($lines = 100) {
        $log_file = WP_CONTENT_DIR . '/rmcu-logs/debug.log';
        
        if (!file_exists($log_file)) {
            return __('Aucun log disponible.', 'rmcu');
        }
        
        $logs = file_get_contents($log_file);
        $log_lines = explode("\n", $logs);
        $recent_lines = array_slice($log_lines, -$lines);
        
        return implode("\n", $recent_lines);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'rmcu') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'rmcu-admin-style',
            RMCU_PLUGIN_URL . 'assets/css/admin.css',
            [],
            RMCU_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'rmcu-admin-script',
            RMCU_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            RMCU_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('rmcu-admin-script', 'rmcuAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rmcu_admin_nonce'),
            'strings' => [
                'testingWebhook' => __('Test en cours...', 'rmcu'),
                'webhookSuccess' => __('Connexion réussie !', 'rmcu'),
                'webhookError' => __('Erreur de connexion', 'rmcu'),
                'confirmClearCache' => __('Êtes-vous sûr de vouloir vider le cache ?', 'rmcu'),
                'confirmClearLogs' => __('Êtes-vous sûr de vouloir effacer les logs ?', 'rmcu')
            ]
        ]);
    }
    
    /**
     * AJAX: Test webhook
     */
    public function ajax_test_webhook() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $options = get_option(self::OPTION_NAME);
        $webhook_url = isset($options['n8n_webhook_url']) ? $options['n8n_webhook_url'] : '';
        
        if (empty($webhook_url)) {
            wp_send_json_error(['message' => __('URL webhook non configurée', 'rmcu')]);
        }
        
        // Tester la connexion
        $response = wp_remote_post($webhook_url, [
            'body' => json_encode([
                'test' => true,
                'timestamp' => current_time('mysql')
            ]),
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            wp_send_json_success(['message' => __('Connexion réussie !', 'rmcu')]);
        } else {
            wp_send_json_error(['message' => sprintf(__('Code de réponse: %d', 'rmcu'), $code)]);
        }
    }
    
    /**
     * AJAX: Clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        // Vider le cache
        delete_transient('rmcu_cache');
        
        $this->logger->info('Cache cleared by user', ['user_id' => get_current_user_id()]);
        
        wp_send_json_success(['message' => __('Cache vidé avec succès', 'rmcu')]);
    }
    
    /**
     * AJAX: Export settings
     */
    public function ajax_export_settings() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $options = get_option(self::OPTION_NAME);
        $export_data = [
            'version' => RMCU_VERSION,
            'settings' => $options,
            'timestamp' => current_time('mysql')
        ];
        
        wp_send_json_success([
            'data' => base64_encode(json_encode($export_data)),
            'filename' => 'rmcu-settings-' . date('Y-m-d') . '.json'
        ]);
    }
    
    /**
     * AJAX: Import settings
     */
    public function ajax_import_settings() {
        check_ajax_referer('rmcu_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $import_data = isset($_POST['import_data']) ? $_POST['import_data'] : '';
        
        if (empty($import_data)) {
            wp_send_json_error(['message' => __('Aucune donnée à importer', 'rmcu')]);
        }
        
        try {
            $decoded = json_decode(base64_decode($import_data), true);
            
            if (!isset($decoded['settings'])) {
                throw new Exception(__('Format de données invalide', 'rmcu'));
            }
            
            // Sauvegarder les paramètres
            update_option(self::OPTION_NAME, $decoded['settings']);
            
            $this->logger->info('Settings imported', [
                'user_id' => get_current_user_id(),
                'version' => $decoded['version'] ?? 'unknown'
            ]);
            
            wp_send_json_success(['message' => __('Paramètres importés avec succès', 'rmcu')]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}

// Initialiser la classe
add_action('init', function() {
    RMCU_Settings_Page::get_instance();
});