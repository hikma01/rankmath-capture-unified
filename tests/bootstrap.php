<?php
/**
 * PHPUnit bootstrap file for RMCU plugin tests
 *
 * @package RankMath_Capture_Unified
 */

// Define test constants
define('RMCU_TESTS_RUNNING', true);
define('RMCU_PLUGIN_DIR', dirname(dirname(__FILE__)) . '/');
define('RMCU_TESTS_DIR', dirname(__FILE__) . '/');

// Load composer autoloader if available
$composer_autoloader = dirname(dirname(__FILE__)) . '/vendor/autoload.php';
if (file_exists($composer_autoloader)) {
    require_once $composer_autoloader;
}

// Determine WordPress test directory
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
    
    // Try common locations
    if (!file_exists($_tests_dir . '/includes/functions.php')) {
        $_tests_dir = '/tmp/wordpress-tests-lib';
    }
    
    if (!file_exists($_tests_dir . '/includes/functions.php')) {
        $_tests_dir = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/tests/phpunit';
    }
}

// Verify WordPress test suite exists
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find WordPress test suite at: $_tests_dir\n";
    echo "Please install WordPress test suite:\n";
    echo "bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-creation]\n";
    exit(1);
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    // Load RankMath if available (for integration tests)
    $rankmath_plugin = dirname(dirname(dirname(__FILE__))) . '/seo-by-rank-math/rank-math.php';
    if (file_exists($rankmath_plugin)) {
        require $rankmath_plugin;
    }
    
    // Load our plugin
    require RMCU_PLUGIN_DIR . 'rankmath-capture-unified.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

/**
 * Setup test database tables
 */
function _setup_test_tables() {
    global $wpdb;
    
    // Create plugin tables
    $charset_collate = $wpdb->get_charset_collate();
    
    // Captures table
    $table_name = $wpdb->prefix . 'rmcu_captures';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        type varchar(20) NOT NULL,
        title varchar(255) DEFAULT NULL,
        description text,
        file_url varchar(500) DEFAULT NULL,
        attachment_id bigint(20) UNSIGNED DEFAULT NULL,
        duration int(11) DEFAULT NULL,
        metadata longtext,
        status varchar(20) DEFAULT 'processing',
        views bigint(20) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY type (type),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Queue table
    $queue_table = $wpdb->prefix . 'rmcu_queue';
    $sql = "CREATE TABLE IF NOT EXISTS $queue_table (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        capture_id bigint(20) UNSIGNED NOT NULL,
        action varchar(50) NOT NULL,
        priority int(11) DEFAULT 10,
        attempts int(11) DEFAULT 0,
        max_attempts int(11) DEFAULT 3,
        payload longtext,
        error_message text,
        status varchar(20) DEFAULT 'pending',
        scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
        completed_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY capture_id (capture_id),
        KEY status (status),
        KEY scheduled_at (scheduled_at)
    ) $charset_collate;";
    
    dbDelta($sql);
}
tests_add_filter('init', '_setup_test_tables');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test case classes
require_once RMCU_TESTS_DIR . 'includes/class-rmcu-test-case.php';
require_once RMCU_TESTS_DIR . 'includes/class-rmcu-ajax-test-case.php';
require_once RMCU_TESTS_DIR . 'includes/class-rmcu-api-test-case.php';

// Load test helpers
require_once RMCU_TESTS_DIR . 'includes/helpers/class-test-helper.php';
require_once RMCU_TESTS_DIR . 'includes/helpers/class-capture-factory.php';
require_once RMCU_TESTS_DIR . 'includes/helpers/class-mock-webhook.php';

echo "RMCU Test Suite Loaded\n";
echo "WordPress Version: " . $GLOBALS['wp_version'] . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Test Directory: " . RMCU_TESTS_DIR . "\n\n";