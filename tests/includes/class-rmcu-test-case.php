<?php
/**
 * Base test case class for RMCU plugin
 *
 * @package RankMath_Capture_Unified
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;
use WP_UnitTestCase;

/**
 * Base test case class
 * Extends WP_UnitTestCase for WordPress-specific testing
 */
class RMCU_Test_Case extends WP_UnitTestCase {
    
    /**
     * Plugin instance
     * @var RankMath_Capture_Unified
     */
    protected $plugin;
    
    /**
     * Test user ID
     * @var int
     */
    protected $test_user_id;
    
    /**
     * Test admin user ID
     * @var int
     */
    protected $test_admin_id;
    
    /**
     * Test attachment ID
     * @var int
     */
    protected $test_attachment_id;
    
    /**
     * Setup test case
     */
    public function setUp(): void {
        parent::setUp();
        
        // Get plugin instance
        $this->plugin = RankMath_Capture_Unified::get_instance();
        
        // Create test users
        $this->test_user_id = $this->factory->user->create(array(
            'role' => 'subscriber',
            'user_login' => 'test_user',
            'user_email' => 'test@example.com'
        ));
        
        $this->test_admin_id = $this->factory->user->create(array(
            'role' => 'administrator',
            'user_login' => 'test_admin',
            'user_email' => 'admin@example.com'
        ));
        
        // Set current user as admin for tests
        wp_set_current_user($this->test_admin_id);
        
        // Create test attachment
        $this->test_attachment_id = $this->create_test_attachment();
        
        // Clear any existing captures
        $this->clear_captures_table();
        
        // Reset options
        $this->reset_plugin_options();
    }
    
    /**
     * Teardown test case
     */
    public function tearDown(): void {
        parent::tearDown();
        
        // Clear test data
        $this->clear_captures_table();
        
        // Reset current user
        wp_set_current_user(0);
        
        // Delete test attachment
        if ($this->test_attachment_id) {
            wp_delete_attachment($this->test_attachment_id, true);
        }
    }
    
    /**
     * Create a test attachment
     * 
     * @return int Attachment ID
     */
    protected function create_test_attachment() {
        $filename = 'test-video.webm';
        $upload_dir = wp_upload_dir();
        $test_file = $upload_dir['path'] . '/' . $filename;
        
        // Create a dummy file
        file_put_contents($test_file, 'dummy content');
        
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => 'video/webm',
            'post_title' => 'Test Video',
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $test_file);
        
        // Generate metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attachment_id, $test_file);
        wp_update_attachment_metadata($attachment_id, $attach_data);
        
        return $attachment_id;
    }
    
    /**
     * Create a test capture
     * 
     * @param array $args Capture arguments
     * @return int|false Capture ID or false on failure
     */
    protected function create_test_capture($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => $this->test_user_id,
            'type' => 'video',
            'title' => 'Test Capture',
            'description' => 'Test capture description',
            'file_url' => 'https://example.com/test.webm',
            'attachment_id' => $this->test_attachment_id,
            'duration' => 120,
            'metadata' => json_encode(array('test' => true)),
            'status' => 'completed',
            'views' => 0
        );
        
        $data = wp_parse_args($args, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rmcu_captures',
            $data
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Clear captures table
     */
    protected function clear_captures_table() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}rmcu_captures");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}rmcu_queue");
    }
    
    /**
     * Reset plugin options to defaults
     */
    protected function reset_plugin_options() {
        // General settings
        update_option('rmcu_enable_plugin', true);
        update_option('rmcu_show_admin_bar', true);
        update_option('rmcu_enable_frontend_capture', true);
        
        // Capture settings
        update_option('rmcu_enable_video_capture', true);
        update_option('rmcu_enable_audio_capture', true);
        update_option('rmcu_enable_screen_capture', true);
        update_option('rmcu_max_recording_time', 300);
        update_option('rmcu_video_quality', '720p');
        
        // n8n settings
        update_option('rmcu_enable_n8n_webhook', false);
        update_option('rmcu_n8n_webhook_url', '');
        
        // Advanced settings
        update_option('rmcu_enable_logging', true);
        update_option('rmcu_log_level', 'error');
    }
    
    /**
     * Assert capture exists in database
     * 
     * @param int $capture_id
     * @param string $message
     */
    protected function assertCaptureExists($capture_id, $message = '') {
        global $wpdb;
        
        $capture = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rmcu_captures WHERE id = %d",
            $capture_id
        ));
        
        $this->assertNotNull($capture, $message ?: "Capture with ID $capture_id should exist");
    }
    
    /**
     * Assert capture has status
     * 
     * @param int $capture_id
     * @param string $status
     * @param string $message
     */
    protected function assertCaptureStatus($capture_id, $status, $message = '') {
        global $wpdb;
        
        $actual_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}rmcu_captures WHERE id = %d",
            $capture_id
        ));
        
        $this->assertEquals($status, $actual_status, $message ?: "Capture should have status: $status");
    }
    
    /**
     * Assert queue item exists
     * 
     * @param int $capture_id
     * @param string $action
     * @param string $message
     */
    protected function assertQueueItemExists($capture_id, $action, $message = '') {
        global $wpdb;
        
        $queue_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rmcu_queue WHERE capture_id = %d AND action = %s",
            $capture_id,
            $action
        ));
        
        $this->assertNotNull($queue_item, $message ?: "Queue item should exist for capture $capture_id with action: $action");
    }
    
    /**
     * Mock webhook response
     * 
     * @param int $status_code
     * @param array $body
     * @return array
     */
    protected function mock_webhook_response($status_code = 200, $body = array()) {
        return array(
            'response' => array(
                'code' => $status_code,
                'message' => $status_code === 200 ? 'OK' : 'Error'
            ),
            'body' => json_encode($body)
        );
    }
    
    /**
     * Assert AJAX success response
     * 
     * @param mixed $response
     * @param string $message
     */
    protected function assertAjaxSuccess($response, $message = '') {
        $this->assertIsArray($response, 'Response should be an array');
        $this->assertTrue($response['success'], $message ?: 'AJAX response should be successful');
    }
    
    /**
     * Assert AJAX error response
     * 
     * @param mixed $response
     * @param string $message
     */
    protected function assertAjaxError($response, $message = '') {
        $this->assertIsArray($response, 'Response should be an array');
        $this->assertFalse($response['success'], $message ?: 'AJAX response should be an error');
    }
    
    /**
     * Get last log entry
     * 
     * @param string $log_type
     * @return string|false
     */
    protected function get_last_log_entry($log_type = 'debug') {
        $log_file = RMCU_PLUGIN_DIR . "logs/rmcu-$log_type.log";
        
        if (!file_exists($log_file)) {
            return false;
        }
        
        $lines = file($log_file);
        return $lines ? trim(end($lines)) : false;
    }
    
    /**
     * Assert log contains message
     * 
     * @param string $message
     * @param string $log_type
     */
    protected function assertLogContains($message, $log_type = 'debug') {
        $log_content = file_get_contents(RMCU_PLUGIN_DIR . "logs/rmcu-$log_type.log");
        $this->assertStringContainsString($message, $log_content, "Log should contain: $message");
    }
}