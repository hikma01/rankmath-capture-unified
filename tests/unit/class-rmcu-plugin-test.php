<?php
/**
 * Tests for main plugin class
 *
 * @package RankMath_Capture_Unified
 * @subpackage Tests/Unit
 */

/**
 * Test main plugin functionality
 */
class RMCU_Plugin_Test extends RMCU_Test_Case {
    
    /**
     * Test plugin instance
     */
    public function test_plugin_instance() {
        $this->assertInstanceOf('RankMath_Capture_Unified', $this->plugin);
        
        // Test singleton pattern
        $instance2 = RankMath_Capture_Unified::get_instance();
        $this->assertSame($this->plugin, $instance2, 'Plugin should use singleton pattern');
    }
    
    /**
     * Test plugin activation
     */
    public function test_plugin_activation() {
        // Deactivate first
        $this->plugin->deactivate();
        
        // Activate
        $this->plugin->activate();
        
        // Check database tables exist
        global $wpdb;
        $table_name = $wpdb->prefix . 'rmcu_captures';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        $this->assertTrue($table_exists, 'Captures table should be created on activation');
        
        // Check default options
        $this->assertEquals('1', get_option('rmcu_enable_plugin'), 'Plugin should be enabled by default');
        $this->assertNotEmpty(get_option('rmcu_db_version'), 'Database version should be set');
    }
    
    /**
     * Test plugin deactivation
     */
    public function test_plugin_deactivation() {
        // Activate first
        $this->plugin->activate();
        
        // Create a test capture
        $capture_id = $this->create_test_capture();
        
        // Deactivate
        $this->plugin->deactivate();
        
        // Check scheduled hooks are cleared
        $this->assertFalse(wp_next_scheduled('rmcu_process_queue'), 'Queue processing should be unscheduled');
        $this->assertFalse(wp_next_scheduled('rmcu_cleanup_logs'), 'Log cleanup should be unscheduled');
    }
    
    /**
     * Test plugin constants
     */
    public function test_plugin_constants() {
        $this->assertTrue(defined('RMCU_VERSION'), 'Version constant should be defined');
        $this->assertTrue(defined('RMCU_PLUGIN_DIR'), 'Plugin directory constant should be defined');
        $this->assertTrue(defined('RMCU_PLUGIN_URL'), 'Plugin URL constant should be defined');
        $this->assertTrue(defined('RMCU_PLUGIN_BASENAME'), 'Plugin basename constant should be defined');
        
        // Test constant values
        $this->assertEquals('2.0.0', RMCU_VERSION);
        $this->assertStringContainsString('rankmath-capture-unified', RMCU_PLUGIN_DIR);
    }
    
    /**
     * Test hooks registration
     */
    public function test_hooks_registration() {
        // Admin hooks
        $this->assertNotFalse(has_action('admin_menu', array($this->plugin, 'add_admin_menu')));
        $this->assertNotFalse(has_action('admin_enqueue_scripts', array($this->plugin, 'enqueue_admin_scripts')));
        
        // Public hooks
        $this->assertNotFalse(has_action('wp_enqueue_scripts', array($this->plugin, 'enqueue_public_scripts')));
        $this->assertNotFalse(has_action('init', array($this->plugin, 'register_shortcodes')));
        
        // AJAX hooks
        $this->assertNotFalse(has_action('wp_ajax_rmcu_save_capture'));
        $this->assertNotFalse(has_action('wp_ajax_rmcu_delete_capture'));
        $this->assertNotFalse(has_action('wp_ajax_rmcu_get_capture_status'));
    }
    
    /**
     * Test RankMath integration check
     */
    public function test_rankmath_integration() {
        // Mock RankMath not installed
        $this->assertFalse($this->plugin->is_rankmath_active(), 'Should detect RankMath is not active');
        
        // Mock RankMath installed and active
        if (!function_exists('rank_math')) {
            function rank_math() {
                return new stdClass();
            }
        }
        
        // Note: In real tests, you'd use a proper mock
        // This is simplified for demonstration
    }
    
    /**
     * Test shortcode registration
     */
    public function test_shortcodes_registered() {
        global $shortcode_tags;
        
        $this->assertArrayHasKey('rmcu_capture', $shortcode_tags, 'Capture shortcode should be registered');
        $this->assertArrayHasKey('rmcu_gallery', $shortcode_tags, 'Gallery shortcode should be registered');
        $this->assertArrayHasKey('rmcu_upload', $shortcode_tags, 'Upload shortcode should be registered');
    }
    
    /**
     * Test admin menu creation
     */
    public function test_admin_menu() {
        global $menu, $submenu;
        
        // Trigger admin menu creation
        do_action('admin_menu');
        
        // Check main menu exists
        $menu_exists = false;
        foreach ($menu as $item) {
            if ($item[2] === 'rmcu-dashboard') {
                $menu_exists = true;
                break;
            }
        }
        
        $this->assertTrue($menu_exists, 'Admin menu should be created');
        
        // Check submenus
        $this->assertArrayHasKey('rmcu-dashboard', $submenu, 'Submenus should be created');
    }
    
    /**
     * Test capability checks
     */
    public function test_capabilities() {
        // Test admin capabilities
        wp_set_current_user($this->test_admin_id);
        $this->assertTrue(current_user_can('manage_rmcu_captures'), 'Admin should have manage capabilities');
        
        // Test regular user capabilities
        wp_set_current_user($this->test_user_id);
        $this->assertFalse(current_user_can('manage_rmcu_captures'), 'Regular user should not have manage capabilities');
        $this->assertTrue(current_user_can('upload_files'), 'Regular user should be able to upload files');
    }
    
    /**
     * Test plugin options
     */
    public function test_plugin_options() {
        // Test getting default options
        $this->assertTrue(get_option('rmcu_enable_video_capture', true));
        $this->assertTrue(get_option('rmcu_enable_audio_capture', true));
        $this->assertTrue(get_option('rmcu_enable_screen_capture', true));
        
        // Test updating options
        update_option('rmcu_video_quality', '1080p');
        $this->assertEquals('1080p', get_option('rmcu_video_quality'));
        
        // Test option validation
        update_option('rmcu_max_recording_time', -1);
        $validated_time = $this->plugin->validate_recording_time(-1);
        $this->assertGreaterThan(0, $validated_time, 'Recording time should be validated to positive value');
    }
    
    /**
     * Test API endpoints registration
     */
    public function test_api_endpoints() {
        // Test REST API routes
        $routes = rest_get_server()->get_routes();
        
        $this->assertArrayHasKey('/rmcu/v1/captures', $routes, 'Captures endpoint should be registered');
        $this->assertArrayHasKey('/rmcu/v1/capture/(?P<id>\d+)', $routes, 'Single capture endpoint should be registered');
        $this->assertArrayHasKey('/rmcu/v1/webhook', $routes, 'Webhook endpoint should be registered');
    }
    
    /**
     * Test database operations
     */
    public function test_database_operations() {
        global $wpdb;
        
        // Test table creation
        $tables = array(
            $wpdb->prefix . 'rmcu_captures',
            $wpdb->prefix . 'rmcu_queue'
        );
        
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            $this->assertTrue($exists, "Table $table should exist");
        }
        
        // Test database version
        $db_version = get_option('rmcu_db_version');
        $this->assertEquals(RMCU_DB_VERSION, $db_version, 'Database version should match');
    }
    
    /**
     * Test logging functionality
     */
    public function test_logging() {
        // Enable logging
        update_option('rmcu_enable_logging', true);
        update_option('rmcu_log_level', 'debug');
        
        // Log a test message
        $this->plugin->log('Test message', 'debug');
        
        // Check log file exists
        $log_file = RMCU_PLUGIN_DIR . 'logs/rmcu-debug.log';
        $this->assertFileExists($log_file, 'Log file should be created');
        
        // Check log contains message
        $this->assertLogContains('Test message', 'debug');
    }
    
    /**
     * Test queue processing
     */
    public function test_queue_processing() {
        // Create a test capture
        $capture_id = $this->create_test_capture();
        
        // Add item to queue
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'rmcu_queue',
            array(
                'capture_id' => $capture_id,
                'action' => 'process_video',
                'status' => 'pending'
            )
        );
        
        // Process queue
        do_action('rmcu_process_queue');
        
        // Check queue item was processed
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}rmcu_queue WHERE capture_id = %d",
            $capture_id
        ));
        
        $this->assertNotEquals('pending', $status, 'Queue item should be processed');
    }
    
    /**
     * Test upgrade routine
     */
    public function test_upgrade_routine() {
        // Set old version
        update_option('rmcu_version', '1.0.0');
        
        // Run upgrade
        $this->plugin->check_version();
        
        // Check version updated
        $this->assertEquals(RMCU_VERSION, get_option('rmcu_version'), 'Version should be updated');
        
        // Check migration completed
        $this->assertTrue(get_option('rmcu_migration_completed', false), 'Migration should be completed');
    }
}