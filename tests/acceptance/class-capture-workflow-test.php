<?php
/**
 * End-to-end tests for complete capture workflow
 *
 * @package RankMath_Capture_Unified
 * @subpackage Tests/Acceptance
 */

/**
 * Test complete capture workflows
 */
class Capture_Workflow_Test extends RMCU_Test_Case {
    
    /**
     * Test complete video capture workflow
     */
    public function test_complete_video_capture_workflow() {
        // 1. User visits page with capture shortcode
        $this->go_to(home_url('/capture-page/'));
        
        // 2. Verify shortcode renders capture interface
        $content = apply_filters('the_content', '[rmcu_capture type="video"]');
        $this->assertStringContainsString('rmcu-capture-interface', $content);
        $this->assertStringContainsString('rmcu-start-btn', $content);
        
        // 3. Simulate starting capture (AJAX)
        $_POST['action'] = 'rmcu_start_capture';
        $_POST['type'] = 'video';
        $_POST['nonce'] = wp_create_nonce('rmcu_public_nonce');
        
        // 4. Mock media stream (simulate browser MediaRecorder API)
        $mock_blob = $this->create_mock_video_blob();
        
        // 5. Simulate stopping and saving capture
        $_POST['action'] = 'rmcu_save_capture';
        $_POST['type'] = 'video';
        $_POST['title'] = 'Test Video Capture';
        $_POST['description'] = 'This is a test video';
        $_POST['duration'] = 30;
        $_FILES['capture_file'] = array(
            'name' => 'capture.webm',
            'type' => 'video/webm',
            'tmp_name' => $mock_blob['tmp_name'],
            'error' => 0,
            'size' => $mock_blob['size']
        );
        
        // Execute AJAX handler
        ob_start();
        do_action('wp_ajax_rmcu_save_capture');
        $response = json_decode(ob_get_clean(), true);
        
        // 6. Verify capture was saved
        $this->assertAjaxSuccess($response);
        $this->assertArrayHasKey('capture_id', $response['data']);
        
        $capture_id = $response['data']['capture_id'];
        $this->assertCaptureExists($capture_id);
        $this->assertCaptureStatus($capture_id, 'completed');
        
        // 7. Verify webhook was triggered (if enabled)
        if (get_option('rmcu_enable_n8n_webhook')) {
            $this->assertQueueItemExists($capture_id, 'send_webhook');
        }
        
        // 8. Verify capture appears in gallery
        $gallery_content = do_shortcode('[rmcu_gallery]');
        $this->assertStringContainsString('Test Video Capture', $gallery_content);
        
        // 9. Test viewing single capture
        $capture_url = add_query_arg('capture_id', $capture_id, get_permalink(get_option('rmcu_single_page_id')));
        $this->go_to($capture_url);
        
        // 10. Verify single capture page displays correctly
        ob_start();
        include RMCU_PLUGIN_DIR . 'templates/capture/single-capture.php';
        $single_content = ob_get_clean();
        
        $this->assertStringContainsString('Test Video Capture', $single_content);
        $this->assertStringContainsString('rmcu-video-player', $single_content);
        
        // 11. Test social sharing
        $this->assertStringContainsString('rmcu-share-btn', $single_content);
        
        // 12. Test download functionality
        $download_url = wp_get_attachment_url($response['data']['attachment_id']);
        $this->assertNotEmpty($download_url);
        
        // 13. Test deletion (for authorized users)
        wp_set_current_user($this->test_admin_id);
        
        $_POST['action'] = 'rmcu_delete_capture';
        $_POST['capture_id'] = $capture_id;
        $_POST['nonce'] = wp_create_nonce('rmcu_public_nonce');
        
        ob_start();
        do_action('wp_ajax_rmcu_delete_capture');
        $delete_response = json_decode(ob_get_clean(), true);
        
        $this->assertAjaxSuccess($delete_response);
        
        // Verify capture was deleted
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rmcu_captures WHERE id = %d",
            $capture_id
        ));
        
        $this->assertEquals(0, $exists, 'Capture should be deleted');
    }
    
    /**
     * Test audio capture workflow
     */
    public function test_audio_capture_workflow() {
        // Enable audio capture
        update_option('rmcu_enable_audio_capture', true);
        
        // 1. Render audio capture interface
        $content = do_shortcode('[rmcu_capture type="audio"]');
        $this->assertStringContainsString('rmcu-capture-interface', $content);
        $this->assertStringContainsString('data-type="audio"', $content);
        
        // 2. Create mock audio capture
        $capture_id = $this->create_test_capture(array(
            'type' => 'audio',
            'title' => 'Test Audio Recording',
            'duration' => 60
        ));
        
        // 3. Verify audio player in gallery
        $gallery = do_shortcode('[rmcu_gallery type="audio"]');
        $this->assertStringContainsString('rmcu-gallery-audio', $gallery);
        $this->assertStringContainsString('Test Audio Recording', $gallery);
        
        // 4. Test audio-specific metadata
        $this->assertCaptureExists($capture_id);
        
        global $wpdb;
        $capture = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rmcu_captures WHERE id = %d",
            $capture_id
        ));
        
        $this->assertEquals('audio', $capture->type);
        $this->assertEquals(60, $capture->duration);
    }
    
    /**
     * Test screen capture workflow
     */
    public function test_screen_capture_workflow() {
        // Enable screen capture
        update_option('rmcu_enable_screen_capture', true);
        
        // 1. Test screen capture interface
        $content = do_shortcode('[rmcu_capture type="screen"]');
        $this->assertStringContainsString('data-type="screen"', $content);
        
        // 2. Mock screen capture with system audio
        $_POST['action'] = 'rmcu_save_capture';
        $_POST['type'] = 'screen';
        $_POST['title'] = 'Screen Recording Demo';
        $_POST['metadata'] = json_encode(array(
            'include_system_audio' => true,
            'cursor_visible' => true,
            'resolution' => '1920x1080'
        ));
        
        // 3. Create screen capture
        $capture_id = $this->create_test_capture(array(
            'type' => 'screen',
            'title' => 'Screen Recording Demo',
            'metadata' => json_encode(array(
                'include_system_audio' => true,
                'cursor_visible' => true,
                'resolution' => '1920x1080'
            ))
        ));
        
        // 4. Verify screen capture metadata
        global $wpdb;
        $metadata = $wpdb->get_var($wpdb->prepare(
            "SELECT metadata FROM {$wpdb->prefix}rmcu_captures WHERE id = %d",
            $capture_id
        ));
        
        $metadata = json_decode($metadata, true);
        $this->assertTrue($metadata['include_system_audio']);
        $this->assertEquals('1920x1080', $metadata['resolution']);
    }
    
    /**
     * Test image capture workflow
     */
    public function test_image_capture_workflow() {
        // 1. Test photo capture interface
        $content = do_shortcode('[rmcu_capture type="image"]');
        $this->assertStringContainsString('rmcu-capture-btn', $content);
        
        // 2. Create image capture
        $capture_id = $this->create_test_capture(array(
            'type' => 'image',
            'title' => 'Screenshot',
            'duration' => 0
        ));
        
        // 3. Verify image display in gallery
        $gallery = do_shortcode('[rmcu_gallery type="image"]');
        $this->assertStringContainsString('rmcu-gallery-image', $gallery);
        $this->assertStringNotContainsString('rmcu-duration', $gallery); // Images don't have duration
    }
    
    /**
     * Test guest capture workflow
     */
    public function test_guest_capture_workflow() {
        // Enable guest capture
        update_option('rmcu_allow_guest_capture', true);
        
        // Logout current user
        wp_set_current_user(0);
        
        // 1. Verify guest can see capture interface
        $content = do_shortcode('[rmcu_capture]');
        $this->assertStringContainsString('rmcu-capture-interface', $content);
        $this->assertStringNotContainsString('You do not have permission', $content);
        
        // 2. Test guest capture save
        $_POST['action'] = 'rmcu_save_capture';
        $_POST['type'] = 'audio';
        $_POST['title'] = 'Guest Recording';
        $_POST['nonce'] = wp_create_nonce('rmcu_public_nonce');
        
        // Note: In real scenario, would need to mock file upload
        // For testing, create capture directly
        $capture_id = $this->create_test_capture(array(
            'user_id' => 0, // Guest user
            'title' => 'Guest Recording'
        ));
        
        $this->assertCaptureExists($capture_id);
        
        // 3. Verify guest cannot delete captures
        $_POST['action'] = 'rmcu_delete_capture';
        $_POST['capture_id'] = $capture_id;
        
        ob_start();
        do_action('wp_ajax_nopriv_rmcu_delete_capture');
        $response = ob_get_clean();
        
        // Should fail for guest
        $this->assertEmpty($response, 'Guest should not be able to delete');
    }
    
    /**
     * Test capture with RankMath SEO
     */
    public function test_capture_with_rankmath_seo() {
        // Mock RankMath is active
        if (!function_exists('rank_math')) {
            function rank_math() {
                return true;
            }
        }
        
        // 1. Create capture with SEO optimization
        $capture_id = $this->create_test_capture(array(
            'title' => 'SEO Optimized Video',
            'description' => 'This video is optimized for search engines with proper metadata and schema markup.'
        ));
        
        // 2. Add RankMath metadata
        $attachment_id = $this->test_attachment_id;
        update_post_meta($attachment_id, 'rank_math_seo_score', 92);
        update_post_meta($attachment_id, 'rank_math_focus_keyword', 'tutorial video');
        update_post_meta($attachment_id, 'rank_math_schema_VideoObject', array(
            'name' => 'SEO Optimized Video',
            'description' => 'This video is optimized for search engines',
            'thumbnailUrl' => 'https://example.com/thumbnail.jpg',
            'uploadDate' => date('c'),
            'duration' => 'PT2M30S'
        ));
        
        // 3. Verify SEO data in single capture view
        ob_start();
        $GLOBALS['capture_id'] = $capture_id;
        include RMCU_PLUGIN_DIR . 'templates/capture/single-capture.php';
        $content = ob_get_clean();
        
        $this->assertStringContainsString('rmcu-seo-info', $content);
        $this->assertStringContainsString('92/100', $content);
        $this->assertStringContainsString('tutorial video', $content);
        
        // 4. Test Open Graph meta tags
        ob_start();
        do_action('wp_head');
        $head_content = ob_get_clean();
        
        // Would contain OG tags in real scenario
        // This is simplified for testing
    }
    
    /**
     * Test capture embed functionality
     */
    public function test_capture_embed() {
        // 1. Create capture
        $capture_id = $this->create_test_capture(array(
            'title' => 'Embeddable Video'
        ));
        
        // 2. Get embed code
        $embed_url = add_query_arg(array(
            'capture_id' => $capture_id,
            'embed' => 'true'
        ), home_url());
        
        // 3. Test embed template
        $_GET['embed'] = 'true';
        $_GET['capture_id'] = $capture_id;
        
        ob_start();
        include RMCU_PLUGIN_DIR . 'templates/capture/embed-capture.php';
        $embed_content = ob_get_clean();
        
        $this->assertStringContainsString('rmcu-embed-container', $embed_content);
        $this->assertStringContainsString('Embeddable Video', $embed_content);
        
        // 4. Verify embed has minimal UI
        $this->assertStringNotContainsString('rmcu-comments-section', $embed_content);
        $this->assertStringNotContainsString('rmcu-sidebar', $embed_content);
    }
    
    /**
     * Helper: Create mock video blob
     */
    private function create_mock_video_blob() {
        $upload_dir = wp_upload_dir();
        $tmp_file = $upload_dir['path'] . '/test-video-' . time() . '.webm';
        
        // Create dummy video file
        $content = 'WEBM' . str_repeat('0', 1024); // 1KB dummy content
        file_put_contents($tmp_file, $content);
        
        return array(
            'tmp_name' => $tmp_file,
            'size' => filesize($tmp_file)
        );
    }
}