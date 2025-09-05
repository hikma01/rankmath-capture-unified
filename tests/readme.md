# RMCU Plugin Tests

Comprehensive test suite for the RankMath Capture Unified plugin.

## 🚀 Quick Start

### Prerequisites

- PHP 7.4 or higher
- Composer
- MySQL/MariaDB
- WordPress test suite
- PHPUnit 9.x

### Installation

1. **Install dependencies:**
```bash
composer install
```

2. **Install WordPress test suite:**
```bash
# From the tests directory
./bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

Parameters:
- `wordpress_test` - Test database name
- `root` - Database user
- `''` - Database password (empty for local)
- `localhost` - Database host
- `latest` - WordPress version

3. **Run tests:**
```bash
# Run all tests
composer test

# Or using PHPUnit directly
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite "RMCU Unit Tests"
```

## 📁 Structure

```
tests/
├── bin/                      # Test utilities
│   └── install-wp-tests.sh  # WordPress test installer
├── includes/                # Test helpers and base classes
│   ├── class-rmcu-test-case.php
│   ├── class-rmcu-ajax-test-case.php
│   └── helpers/
├── unit/                    # Unit tests
│   ├── class-rmcu-plugin-test.php
│   ├── class-rmcu-capture-handler-test.php
│   └── ...
├── integration/            # Integration tests
│   ├── class-n8n-integration-test.php
│   ├── class-rankmath-integration-test.php
│   └── ...
├── acceptance/            # Acceptance/E2E tests
├── fixtures/              # Test fixtures and data
├── bootstrap.php          # PHPUnit bootstrap
├── phpunit.xml           # PHPUnit configuration
└── README.md             # This file
```

## 🧪 Test Types

### Unit Tests
Test individual components in isolation:
```php
class RMCU_Plugin_Test extends RMCU_Test_Case {
    public function test_plugin_activation() {
        // Test code
    }
}
```

### Integration Tests
Test component interactions:
```php
class N8N_Integration_Test extends RMCU_Test_Case {
    public function test_webhook_sends_on_capture() {
        // Test code
    }
}
```

### Acceptance Tests
End-to-end user scenarios:
```php
class Capture_Workflow_Test extends RMCU_Test_Case {
    public function test_complete_capture_workflow() {
        // Test code
    }
}
```

## 🎯 Running Tests

### Run All Tests
```bash
composer test
```

### Run Specific Test File
```bash
vendor/bin/phpunit tests/unit/class-rmcu-plugin-test.php
```

### Run Specific Test Method
```bash
vendor/bin/phpunit --filter test_plugin_activation
```

### Run with Code Coverage
```bash
vendor/bin/phpunit --coverage-html coverage-report
```

View report: `open coverage-report/index.html`

### Run Test Suites
```bash
# Unit tests only
vendor/bin/phpunit --testsuite "RMCU Unit Tests"

# Integration tests only
vendor/bin/phpunit --testsuite "RMCU Integration Tests"
```

## 🛠️ Writing Tests

### Basic Test Structure
```php
class My_Component_Test extends RMCU_Test_Case {
    
    public function setUp(): void {
        parent::setUp();
        // Setup test environment
    }
    
    public function tearDown(): void {
        // Cleanup
        parent::tearDown();
    }
    
    public function test_something() {
        // Arrange
        $expected = 'value';
        
        // Act
        $result = my_function();
        
        // Assert
        $this->assertEquals($expected, $result);
    }
}
```

### Available Assertions

Custom assertions provided by `RMCU_Test_Case`:

```php
// Database assertions
$this->assertCaptureExists($capture_id);
$this->assertCaptureStatus($capture_id, 'completed');
$this->assertQueueItemExists($capture_id, 'send_webhook');

// AJAX assertions
$this->assertAjaxSuccess($response);
$this->assertAjaxError($response);

// Log assertions
$this->assertLogContains('Error message', 'error');
```

### Test Helpers

Create test data easily:
```php
// Create test capture
$capture_id = $this->create_test_capture(array(
    'type' => 'video',
    'title' => 'Test Video'
));

// Create test user
$user_id = $this->factory->user->create(array(
    'role' => 'editor'
));

// Create test attachment
$attachment_id = $this->create_test_attachment();
```

### Mocking External Services

Mock n8n webhook responses:
```php
add_filter('pre_http_request', function($preempt, $args, $url) {
    if ($url === 'https://webhook.test/webhook') {
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array('success' => true))
        );
    }
    return $preempt;
}, 10, 3);
```

## 📊 Code Coverage

Generate coverage reports:
```bash
# HTML report
vendor/bin/phpunit --coverage-html coverage-report

# Text report
vendor/bin/phpunit --coverage-text

# Clover XML (for CI)
vendor/bin/phpunit --coverage-clover coverage.xml
```

### Coverage Goals
- Unit Tests: >80% coverage
- Integration Tests: >60% coverage
- Overall: >70% coverage

## 🔧 Continuous Integration

### GitHub Actions
```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: composer test
```

## 🐛 Debugging Tests

### Enable Debug Output
```bash
vendor/bin/phpunit --debug
```

### Stop on First Failure
```bash
vendor/bin/phpunit --stop-on-failure
```

### Verbose Output
```bash
vendor/bin/phpunit -v
```

### Run Specific Group
```php
/**
 * @group webhook
 */
public function test_webhook_functionality() {
    // Test code
}
```

```bash
vendor/bin/phpunit --group webhook
```

## 📝 Test Database

### Reset Test Database
```bash
# Drop and recreate
mysql -u root -e "DROP DATABASE IF EXISTS wordpress_test"
mysql -u root -e "CREATE DATABASE wordpress_test"
```

### Custom Test Tables
Tables are automatically created during bootstrap:
- `wp_rmcu_captures`
- `wp_rmcu_queue`

## ⚠️ Common Issues

### Cannot Find WordPress Test Suite
```bash
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
```

### Permission Denied on install-wp-tests.sh
```bash
chmod +x tests/bin/install-wp-tests.sh
```

### MySQL Connection Failed
Check database credentials in `phpunit.xml`:
```xml
<env name="WP_TESTS_DB_USER" value="root" />
<env name="WP_TESTS_DB_PASS" value="password" />
```

## 📚 Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Testing Documentation](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [WP_UnitTestCase Reference](https://developer.wordpress.org/reference/classes/wp_unittestcase/)

## 🤝 Contributing

1. Write tests for new features
2. Ensure all tests pass before submitting PR
3. Maintain >70% code coverage
4. Follow naming conventions:
   - Test classes: `{Component}_Test`
   - Test methods: `test_{functionality}`
5. Document complex test scenarios

---

**Test Coverage Status:** ![Coverage](https://img.shields.io/badge/coverage-75%25-green)
**Last Updated:** January 2024
**Plugin Version:** 2.0.0