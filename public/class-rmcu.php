<?php
/**
 * The public-facing functionality of the plugin
 *
 * @package    RankMath_Capture_Unified
 * @subpackage RankMath_Capture_Unified/public
 * @author     RMCU Team
 */

class RMCU_Public {
    /**
     * Plugin ID
     * @var string
     */
    private $plugin_name;

    /**
     * Plugin version
     * @var string
     */
    private $version;

    /**
     * Initialize the class
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        $this->init_shortcodes();
        $this->init_ajax_handlers();
    }

    /**
     * Register public stylesheets
     */
    public function enqueue_styles() {
        // Main public styles
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/rmcu-public.css',
            array(),
            $this->version,
            'all'
        );

        // Responsive styles
        wp_enqueue_style(
            $this->plugin_name . '-responsive',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/rmcu-responsive.css',
            array($this->plugin_name),
            $this->version,
            'all'
        );
    }

    /**
     * Register public JavaScript
     */
    public function enqueue_scripts() {
        // Media Capture API polyfill for older browsers
        wp_enqueue_script(
            $this->plugin_name . '-polyfill',
            'https://webrtc.github.io/adapter/adapter-latest.js',
            array(),
            null,
            true
        );

        // Main public script
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/rmcu-public.js',
            array('jquery'),
            $this->version,
            true
        );

        // WebRTC handler
        wp_enqueue_script(
            $this->plugin_name . '-capture',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/rmcu-capture-handler.js',
            array($this->plugin_name),
            $this->version,
            true
        );

        // Localize script with Ajax URL and nonce
        wp_localize_script($this->plugin_name, 'rmcu_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rmcu_public_nonce'),
            'max_file_size' => wp_max_upload_size(),
            'allowed_types' => array('video/webm', 'video/mp4', 'audio/webm', 'audio/ogg', 'image/png', 'image/jpeg'),
            'strings' => array(
                'start_recording' => __('Start Recording', 'rmcu'),
                'stop_recording' => __('Stop Recording', 'rmcu'),
                'pause_recording' => __('Pause', 'rmcu'),
                'resume_recording' => __('Resume', 'rmcu'),
                'downloading' => __('Downloading...', 'rmcu'),
                'uploading' => __('Uploading...', 'rmcu'),
                'processing' => __('Processing...', 'rmcu'),
                'error' => __('An error occurred', 'rmcu'),
                'success' => __('Successfully captured!', 'rmcu'),
                'no_media_access' => __('Cannot access media devices', 'rmcu'),
                'browser_not_supported' => __('Your browser does not support media capture', 'rmcu'),
            ),
            'settings' => array(
                'video_enabled' => get_option('rmcu_enable_video_capture', true),
                'audio_enabled' => get_option('rmcu_enable_audio_capture', true),
                'screen_enabled' => get_option('rmcu_enable_screen_capture', true),
                'max_duration' => get_option('rmcu_max_recording_time', 300),
                'video_quality' => get_option('rmcu_video_quality', '720p'),
                'auto_upload' => get_option('rmcu_auto_upload', false),
            )
        ));
    }

    /**
     * Initialize shortcodes
     */
    private function init_shortcodes() {
        add_shortcode('rmcu_capture', array($this, 'render_capture_shortcode'));
        add_shortcode('rmcu_gallery', array($this, 'render_gallery_shortcode'));
        add_shortcode('rmcu_upload', array($this, 'render_upload_shortcode'));
    }

    /**
     * Render capture interface shortcode
     */
    public function render_capture_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'all', // video, audio, screen, image, all
            'show_preview' => 'true',
            'auto_start' => 'false',
            'max_duration' => get_option('rmcu_max_recording_time', 300),
            'class' => '',
            'id' => 'rmcu-capture-' . uniqid(),
        ), $atts, 'rmcu_capture');

        // Check user capabilities
        if (!$this->can_user_capture()) {
            return '<div class="rmcu-error">' . __('You do not have permission to use capture features.', 'rmcu') . '</div>';
        }

        // Load template
        ob_start();
        include plugin_dir_path(dirname(__FILE__)) . 'public/partials/capture-interface.php';
        return ob_get_clean();
    }

    /**
     * Render gallery shortcode
     */
    public function render_gallery_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user' => 'current', // current, all, or specific user ID
            'type' => 'all', // video, audio, image, all
            'limit' => 10,
            'order' => 'DESC',
            'orderby' => 'date',
            'columns' => 3,
            'show_title' => 'true',
            'show_date' => 'true',
            'show_user' => 'false',
            'class' => '',
        ), $atts, 'rmcu_gallery');

        // Get captures
        $captures = $this->get_user_captures($atts);

        if (empty($captures)) {
            return '<div class="rmcu-gallery-empty">' . __('No captures found.', 'rmcu') . '</div>';
        }

        // Load template
        ob_start();
        include plugin_dir_path(dirname(__FILE__)) . 'public/partials/capture-gallery.php';
        return ob_get_clean();
    }

    /**
     * Render upload interface shortcode
     */
    public function render_upload_shortcode($atts) {
        $atts = shortcode_atts(array(
            'allowed_types' => 'video,audio,image',
            'max_size' => wp_max_upload_size(),
            'auto_process' => 'true',
            'show_progress' => 'true',
            'multiple' => 'false',
            'class' => '',
        ), $atts, 'rmcu_upload');

        // Check user capabilities
        if (!$this->can_user_upload()) {
            return '<div class="rmcu-error">' . __('You do not have permission to upload files.', 'rmcu') . '</div>';
        }

        // Load template
        ob_start();
        include plugin_dir_path(dirname(__FILE__)) . 'public/partials/upload-interface.php';
        return ob_get_clean();
    }

    /**
     * Initialize AJAX handlers
     */
    private function init_ajax_handlers() {
        // Public AJAX actions
        add_action('wp_ajax_rmcu_save_capture', array($this, 'ajax_save_capture'));
        add_action('wp_ajax_rmcu_upload_file', array($this, 'ajax_upload_file'));
        add_action('wp_ajax_rmcu_get_capture_status', array($this, 'ajax_get_capture_status'));
        add_action('wp_ajax_rmcu_delete_capture', array($this, 'ajax_delete_capture'));
        
        // Allow non-logged in users if enabled
        if (get_option('rmcu_allow_guest_capture', false)) {
            add_action('wp_ajax_nopriv_rmcu_save_capture', array($this, 'ajax_save_capture'));
            add_action('wp_ajax_nopriv_rmcu_upload_file', array($this, 'ajax_upload_file'));
        }
    }

    /**
     * AJAX handler for saving captures
     */
    public function ajax_save_capture() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rmcu_public_nonce')) {
            wp_die(__('Security check failed', 'rmcu'));
        }

        // Check capabilities
        if (!$this->can_user_capture()) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'rmcu')));
        }

        // Process the capture
        $capture_data = array(
            'type' => sanitize_text_field($_POST['type']),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'duration' => intval($_POST['duration'] ?? 0),
            'user_id' => get_current_user_id(),
            'metadata' => json_decode(stripslashes($_POST['metadata'] ?? '{}'), true),
        );

        // Handle file upload
        if (!empty($_FILES['capture_file'])) {
            $upload = $this->handle_file_upload($_FILES['capture_file'], $capture_data);
            
            if (is_wp_error($upload)) {
                wp_send_json_error(array('message' => $upload->get_error_message()));
            }
            
            $capture_data['file_url'] = $upload['url'];
            $capture_data['file_path'] = $upload['file'];
            $capture_data['attachment_id'] = $upload['attachment_id'];
        }

        // Save to database
        global $wpdb;
        $table_name = $wpdb->prefix . 'rmcu_captures';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $capture_data['user_id'],
                'type' => $capture_data['type'],
                'title' => $capture_data['title'],
                'description' => $capture_data['description'],
                'file_url' => $capture_data['file_url'],
                'attachment_id' => $capture_data['attachment_id'],
                'duration' => $capture_data['duration'],
                'metadata' => json_encode($capture_data['metadata']),
                'status' => 'completed',
                'created_at' => current_time('mysql'),
            )
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to save capture', 'rmcu')));
        }

        $capture_id = $wpdb->insert_id;

        // Trigger webhook if configured
        do_action('rmcu_capture_saved', $capture_id, $capture_data);

        // Send to n8n if enabled
        if (get_option('rmcu_enable_n8n_webhook', false)) {
            $this->send_to_n8n($capture_id, $capture_data);
        }

        wp_send_json_success(array(
            'message' => __('Capture saved successfully', 'rmcu'),
            'capture_id' => $capture_id,
            'redirect_url' => get_permalink($capture_data['attachment_id']),
        ));
    }

    /**
     * Handle file upload
     */
    private function handle_file_upload($file, $capture_data) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Check file type
        $allowed_types = array(
            'video/webm', 'video/mp4', 'video/ogg',
            'audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/wav',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp'
        );

        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_type', __('Invalid file type', 'rmcu'));
        }

        // Handle upload
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            return new WP_Error('upload_error', $upload['error']);
        }

        // Create attachment
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title' => $capture_data['title'] ?: 'RMCU Capture ' . date('Y-m-d H:i:s'),
            'post_content' => $capture_data['description'],
            'post_status' => 'inherit'
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate metadata
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        $upload['attachment_id'] = $attachment_id;
        
        return $upload;
    }

    /**
     * Send capture to n8n webhook
     */
    private function send_to_n8n($capture_id, $capture_data) {
        $webhook_url = get_option('rmcu_n8n_webhook_url');
        
        if (empty($webhook_url)) {
            return false;
        }

        $payload = array(
            'capture_id' => $capture_id,
            'type' => $capture_data['type'],
            'title' => $capture_data['title'],
            'description' => $capture_data['description'],
            'file_url' => $capture_data['file_url'],
            'user_id' => $capture_data['user_id'],
            'user_email' => wp_get_current_user()->user_email,
            'duration' => $capture_data['duration'],
            'metadata' => $capture_data['metadata'],
            'timestamp' => current_time('c'),
            'site_url' => get_site_url(),
        );

        $response = wp_remote_post($webhook_url, array(
            'body' => json_encode($payload),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));

        return !is_wp_error($response);
    }

    /**
     * Check if user can capture
     */
    private function can_user_capture() {
        if (is_user_logged_in()) {
            return current_user_can('upload_files') || current_user_can('rmcu_capture');
        }
        
        return get_option('rmcu_allow_guest_capture', false);
    }

    /**
     * Check if user can upload
     */
    private function can_user_upload() {
        if (is_user_logged_in()) {
            return current_user_can('upload_files');
        }
        
        return get_option('rmcu_allow_guest_upload', false);
    }

    /**
     * Get user captures
     */
    private function get_user_captures($args) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rmcu_captures';
        
        $where = array('1=1');
        $params = array();
        
        // Filter by user
        if ($args['user'] === 'current') {
            $where[] = 'user_id = %d';
            $params[] = get_current_user_id();
        } elseif ($args['user'] !== 'all') {
            $where[] = 'user_id = %d';
            $params[] = intval($args['user']);
        }
        
        // Filter by type
        if ($args['type'] !== 'all') {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }
        
        // Build query
        $query = "SELECT * FROM $table_name WHERE " . implode(' AND ', $where);
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        $query .= " LIMIT %d";
        $params[] = intval($args['limit']);
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query);
    }

    /**
     * AJAX handler for getting capture status
     */
    public function ajax_get_capture_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'rmcu_public_nonce')) {
            wp_die(__('Security check failed', 'rmcu'));
        }

        $capture_id = intval($_POST['capture_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'rmcu_captures';
        
        $capture = $wpdb->get_row($wpdb->prepare(
            "SELECT status, file_url FROM $table_name WHERE id = %d",
            $capture_id
        ));
        
        if (!$capture) {
            wp_send_json_error(array('message' => __('Capture not found', 'rmcu')));
        }
        
        wp_send_json_success(array(
            'status' => $capture->status,
            'file_url' => $capture->file_url,
        ));
    }

    /**
     * AJAX handler for deleting captures
     */
    public function ajax_delete_capture() {
        if (!wp_verify_nonce($_POST['nonce'], 'rmcu_public_nonce')) {
            wp_die(__('Security check failed', 'rmcu'));
        }

        $capture_id = intval($_POST['capture_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'rmcu_captures';
        
        // Check ownership
        $capture = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, attachment_id FROM $table_name WHERE id = %d",
            $capture_id
        ));
        
        if (!$capture) {
            wp_send_json_error(array('message' => __('Capture not found', 'rmcu')));
        }
        
        if ($capture->user_id != get_current_user_id() && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rmcu')));
        }
        
        // Delete attachment
        if ($capture->attachment_id) {
            wp_delete_attachment($capture->attachment_id, true);
        }
        
        // Delete from database
        $result = $wpdb->delete($table_name, array('id' => $capture_id));
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete capture', 'rmcu')));
        }
        
        wp_send_json_success(array('message' => __('Capture deleted successfully', 'rmcu')));
    }

    /**
     * Add Open Graph meta tags for captures
     */
    public function add_capture_meta_tags() {
        if (!is_singular('attachment')) {
            return;
        }

        global $post;
        
        // Check if this is an RMCU capture
        $is_rmcu = get_post_meta($post->ID, '_rmcu_capture', true);
        if (!$is_rmcu) {
            return;
        }

        $capture_type = get_post_meta($post->ID, '_rmcu_capture_type', true);
        $duration = get_post_meta($post->ID, '_rmcu_capture_duration', true);
        
        echo '<meta property="og:type" content="video.other" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($post->post_title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($post->post_content) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink($post->ID)) . '" />' . "\n";
        
        if ($capture_type === 'video') {
            echo '<meta property="og:video" content="' . esc_url(wp_get_attachment_url($post->ID)) . '" />' . "\n";
            if ($duration) {
                echo '<meta property="og:video:duration" content="' . esc_attr($duration) . '" />' . "\n";
            }
        } elseif ($capture_type === 'image' || $capture_type === 'screen') {
            echo '<meta property="og:image" content="' . esc_url(wp_get_attachment_url($post->ID)) . '" />' . "\n";
        }
    }
}
