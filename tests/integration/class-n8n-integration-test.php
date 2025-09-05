<?php
/**
 * Tests for n8n webhook integration
 *
 * @package RankMath_Capture_Unified
 * @subpackage Tests/Integration
 */

/**
 * Test n8n integration functionality
 */
class N8N_Integration_Test extends RMCU_Test_Case {
    
    /**
     * Mock webhook URL
     * @var string
     */
    private $webhook_url = 'https://webhook.test/webhook/test-webhook-id';
    
    /**
     * Setup test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Enable n8n webhook
        update_option('rmcu_enable_n8n_webhook', true);
        update_option('rmcu_n8n_webhook_url', $this->webhook_url);
        
        // Mock wp_remote_post for testing
        add_filter('pre_http_request', array($this, 'mock_webhook_request'), 10, 3);
    }
    
    /**
     * Teardown test
     */
    public function tearDown(): void {
        parent::tearDown();
        
        // Remove mock filter
        remove_filter('pre_http_request', array($this, 'mock_webhook_request'), 10);
    }
    
    /**
     * Mock webhook request
     */
    public function mock_webhook_request($preempt, $args, $url) {
        if ($url === $this->webhook_url) {
            return $this->mock_webhook_response(200, array('success' => true));
        }
        return $preempt;
    }
    
    /**
     * Test webhook sends on capture creation
     */
    public function test_webhook_sends_on_capture() {
        // Track webhook calls
        $webhook_called = false;
        
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$webhook_called) {
            if ($url === $this->webhook_url) {
                $webhook_called = true;
                
                // Verify payload structure
                $body = json_decode($args['body'], true);
                $this->assertArrayHasKey('capture_id', $body);
                $this->assertArrayHasKey('type', $body);
                $this->assertArrayHasKey('title', $body);
                $this->assertArrayHasKey('user_id', $body);
                $this->assertArrayHasKey('timestamp', $body);
                $this->assertArrayHasKey('site_url', $body);
                
                return $this->mock_webhook_response(200);
            }
            return $preempt;
        }, 10, 3);
        
        // Create capture
        $capture_id = $this->create_test_capture(array(
            'type' => 'video',
            'title' => 'Test Video for Webhook'
        ));
        
        // Trigger webhook
        do_action('rmcu_capture_saved', $capture_id, array());
        
        $this->assertTrue($webhook_called, 'Webhook should be called on capture save');
    }
    
    /**
     * Test webhook retry on failure
     */
    public function test_webhook_retry_mechanism() {
        global $wpdb;
        
        // Mock failed webhook
        $attempt_count = 0;
        
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$attempt_count) {
            if ($url === $this->webhook_url) {
                $attempt_count++;
                
                // Fail first 2 attempts
                if ($attempt_count < 3) {
                    return $this->mock_webhook_response(500, array('error' => 'Server error'));
                }
                
                return $this->mock_webhook_response(200);
            }
            return $preempt;
        }, 10, 3);
        
        // Create capture
        $capture_id = $this->create_test_capture();
        
        // Add to webhook queue
        $wpdb->insert(
            $wpdb->prefix . 'rmcu_queue',
            array(
                'capture_id' => $capture_id,
                'action' => 'send_webhook',
                'status' => 'pending',
                'max_attempts' => 3
            )
        );
        
        // Process queue multiple times
        for ($i = 0; $i < 3; $i++) {
            do_action('rmcu_process_queue');
        }
        
        $this->assertEquals(3, $attempt_count, 'Webhook should retry on failure');
        
        // Check final status
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}rmcu_queue WHERE capture_id = %d",
            $capture_id
        ));
        
        $this->assertEquals('completed', $status, 'Webhook should succeed after retries');
    }
    
    /**
     * Test webhook payload structure
     */
    public function test_webhook_payload() {
        $payload_received = null;
        
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$payload_received) {
            if ($url === $this->webhook_url) {
                $payload_received = json_decode($args['body'], true);
                return $this->mock_webhook_response(200);
            }
            return $preempt;
        }, 10, 3);
        
        // Create capture with metadata
        $capture_id = $this->create_test_capture(array(
            'type' => 'video',
            'title' => 'Test Capture',
            'description' => 'Test description',
            'duration' => 120,
            'metadata' => json_encode(array(
                'resolution' => '1920x1080',
                'fps' => 30,
                'codec' => 'h264'
            ))
        ));
        
        // Send webhook
        $n8n_handler = new RMCU_N8N_Handler();
        $n8n_handler->send_capture_webhook($capture_id);
        
        // Verify payload
        $this->assertNotNull($payload_received, 'Payload should be received');
        $this->assertEquals($capture_id, $payload_received['capture_id']);
        $this->assertEquals('video', $payload_received['type']);
        $this->assertEquals('Test Capture', $payload_received['title']);
        $this->assertEquals('Test description', $payload_received['description']);
        $this->assertEquals(120, $payload_received['duration']);
        $this->assertArrayHasKey('metadata', $payload_received);
        $this->assertEquals('1920x1080', $payload_received['metadata']['resolution']);
    }
    
    /**
     * Test webhook authentication
     */
    public function test_webhook_authentication() {
        // Set authentication token
        update_option('rmcu_n8n_auth_token', 'test-token-123');
        
        $headers_received = null;
        
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$headers_received) {
            if ($url === $this->webhook_url) {
                $headers_received = $args['headers'];
                return $this->mock_webhook_response(200);
            }
            return $preempt;
        }, 10, 3);
        
        // Create and send capture
        $capture_id = $this->create_test_capture();
        do_action('rmcu_capture_saved', $capture_id, array());
        
        // Verify auth header
        $this->assertArrayHasKey('Authorization', $headers_received);
        $this->assertEquals('Bearer test-token-123', $headers_received['Authorization']);
    }
    
    /**
     * Test webhook disabled
     */
    public function test_webhook_disabled() {
        // Disable webhook
        update_option('rmcu_enable_n8n_webhook', false);
        
        $webhook_called = false;
        
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$webhook_called) {
            if ($url === $this->webhook_url) {
                $webhook_called = true;
            }
            return $preempt;
        }, 10, 3);
        
        // Create capture
        $capture_id = $this->create_test_capture();
        do_action('rmcu_capture_saved', $capture_id, array());
        
        $this->assertFalse($webhook_called, 'Webhook should not be called when disabled');
    }
    
    /**
     * Test webhook filtering by capture type
     */
    public function test_webhook_type_filtering() {
        // Configure to only send video captures
        update_option('rmcu_n8n_capture_types', array('video'));
        
        $webhooks_sent = array();
        
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$webhooks_sent) {
            if ($url === $this->webhook_url) {
                $body = json_decode($args['body'], true);
                $webhooks_sent[] = $body['type'];
                return $this->mock_webhook_response(200);
            }
            return $preempt;
        }, 10, 3);
        
        // Create different capture types
        $video_id = $this->create_test_capture(array('type' => 'video'));
        $audio_id = $this->create_test_capture(array('type' => 'audio'));
        $screen_id = $this->create_test_capture(array('type' => 'screen'));
        
        // Trigger webhooks
        do_action('rmcu_capture_saved', $video_id, array('type' => 'video'));
        do_action('rmcu_capture_saved', $audio_id, array('type' => 'audio'));
        do_action('rmcu_capture_saved', $screen_id, array('type' => 'screen'));
        
        // Only video should be sent
        $this->assertCount(1, $webhooks_sent);
        $this->assertContains('video', $webhooks_sent);
    }
    
    /**
     * Test webhook queue processing
     */
    public function test_webhook_queue() {
        global $wpdb;
        
        // Create multiple captures
        $capture_ids = array();
        for ($i = 0; $i < 5; $i++) {
            $capture_ids[] = $this->create_test_capture(array(
                'title' => "Test Capture $i"
            ));
        }
        
        // Add all to queue
        foreach ($capture_ids as $capture_id) {
            $wpdb->insert(
                $wpdb->prefix . 'rmcu_queue',
                array(
                    'capture_id' => $capture_id,
                    'action' => 'send_webhook',
                    'status' => 'pending',
                    'priority' => rand(1, 10)
                )
            );
        }
        
        // Process queue
        do_action('rmcu_process_queue');
        
        // Check all were processed
        $pending = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}rmcu_queue 
            WHERE action = 'send_webhook' AND status = 'pending'
        ");
        
        $this->assertEquals(0, $pending, 'All webhook queue items should be processed');
    }
    
    /**
     * Test webhook error logging
     */
    public function test_webhook_error_logging() {
        // Enable debug logging
        update_option('rmcu_enable_logging', true);
        update_option('rmcu_log_level', 'debug');
        
        // Mock failed webhook
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if ($url === $this->webhook_url) {
                return $this->mock_webhook_response(404, array('error' => 'Webhook not found'));
            }
            return $preempt;
        }, 10, 3);
        
        // Create capture and send webhook
        $capture_id = $this->create_test_capture();
        do_action('rmcu_capture_saved', $capture_id, array());
        
        // Check error was logged
        $this->assertLogContains('Webhook failed', 'error');
        $this->assertLogContains('404', 'error');
    }
    
    /**
     * Test webhook with RankMath data
     */
    public function test_webhook_with_rankmath_data() {
        $payload_received = null;
        
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$payload_received) {
            if ($url === $this->webhook_url) {
                $payload_received = json_decode($args['body'], true);
                return $this->mock_webhook_response(200);
            }
            return $preempt;
        }, 10, 3);
        
        // Create capture with SEO data
        $capture_id = $this->create_test_capture();
        
        // Add RankMath meta (mock)
        update_post_meta($this->test_attachment_id, 'rank_math_seo_score', 85);
        update_post_meta($this->test_attachment_id, 'rank_math_focus_keyword', 'test keyword');
        
        // Send webhook with SEO data
        do_action('rmcu_capture_saved', $capture_id, array(
            'seo_score' => 85,
            'focus_keyword' => 'test keyword'
        ));
        
        // Verify SEO data in payload
        $this->assertArrayHasKey('seo_data', $payload_received);
        $this->assertEquals(85, $payload_received['seo_data']['score']);
        $this->assertEquals('test keyword', $payload_received['seo_data']['focus_keyword']);
    }
}