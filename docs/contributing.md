# Contributing to RankMath Capture Unified

Thank you for your interest in contributing to RMCU! We welcome contributions from the community and are grateful for any help you can provide.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How to Contribute](#how-to-contribute)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Reporting Issues](#reporting-issues)
- [Documentation](#documentation)
- [Community](#community)

## 📜 Code of Conduct

### Our Pledge

We pledge to make participation in our project and community a harassment-free experience for everyone, regardless of age, body size, disability, ethnicity, gender identity and expression, level of experience, nationality, personal appearance, race, religion, or sexual identity and orientation.

### Expected Behavior

- Be respectful and inclusive
- Accept constructive criticism
- Focus on what is best for the community
- Show empathy towards others

### Unacceptable Behavior

- Harassment or discriminatory language
- Personal attacks
- Trolling or insulting comments
- Public or private harassment
- Publishing others' private information

## 🤝 How to Contribute

### Ways to Contribute

1. **Report Bugs**: Found a bug? Let us know!
2. **Suggest Features**: Have an idea? Share it!
3. **Submit Code**: Fix bugs or add features
4. **Improve Documentation**: Help make our docs better
5. **Write Tests**: Increase test coverage
6. **Answer Questions**: Help others in discussions
7. **Translate**: Help localize the plugin

### First Time Contributing?

1. Fork the repository
2. Clone your fork locally
3. Create a new branch for your feature/fix
4. Make your changes
5. Push to your fork
6. Submit a pull request

## 🛠️ Development Setup

### Prerequisites

- PHP 7.4+
- WordPress 5.8+
- Node.js 14+
- Composer
- Git

### Local Environment Setup

```bash
# Clone the repository
git clone https://github.com/yourusername/rankmath-capture-unified.git
cd rankmath-capture-unified

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Set up WordPress development environment
wp-env start

# Create a feature branch
git checkout -b feature/your-feature-name
```

### WordPress Development Environment

#### Using wp-env (Recommended)

```bash
# Install wp-env globally
npm install -g @wordpress/env

# Start the environment
wp-env start

# Access WordPress
# Site: http://localhost:8888
# Admin: http://localhost:8888/wp-admin
# Username: admin
# Password: password
```

#### Using Local by Flywheel

1. Create new site in Local
2. Clone plugin to `/wp-content/plugins/`
3. Activate plugin in WordPress admin

#### Using Docker

```bash
# Use our Docker setup
docker-compose up -d

# Access at http://localhost:8080
```

## 📝 Coding Standards

### PHP Standards

We follow WordPress Coding Standards:

```php
// Good
function rmcu_get_capture( $capture_id ) {
    if ( ! $capture_id ) {
        return false;
    }
    
    $capture = get_post( $capture_id );
    
    if ( 'rmcu_capture' !== $capture->post_type ) {
        return false;
    }
    
    return $capture;
}

// Bad
function rmcu_get_capture($capture_id){
    if(!$capture_id) return false;
    $capture=get_post($capture_id);
    if($capture->post_type!='rmcu_capture') return false;
    return $capture;
}
```

### JavaScript Standards

Follow WordPress JavaScript standards:

```javascript
// Good
( function( $ ) {
    'use strict';
    
    const RMCUCapture = {
        init() {
            this.bindEvents();
        },
        
        bindEvents() {
            $( document ).on( 'click', '.rmcu-button', this.handleClick );
        },
        
        handleClick( event ) {
            event.preventDefault();
            // Handle click
        }
    };
    
    $( document ).ready( () => {
        RMCUCapture.init();
    } );
} )( jQuery );
```

### CSS Standards

```css
/* Good */
.rmcu-capture-container {
    display: flex;
    align-items: center;
    padding: 20px;
    background-color: #f5f5f5;
}

.rmcu-capture-container__title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

/* Bad */
.container{display:flex;align-items:center;padding:20px;background:#f5f5f5}
.title{font-size:18px;font-weight:600;color:#333}
```

### File Organization

```
/includes/
    class-rmcu-*.php       # Classes
    rmcu-functions.php     # Global functions
    rmcu-hooks.php         # Hooks and filters

/admin/
    class-rmcu-admin.php   # Admin main class
    /partials/             # Admin templates
    /views/                # Admin views

/public/
    class-rmcu-public.php  # Public main class
    /partials/             # Public templates
```

## 🧪 Testing

### PHP Testing

```bash
# Run PHP tests
composer test

# Run specific test
composer test -- --filter test_capture_creation

# Generate code coverage
composer test-coverage
```

### JavaScript Testing

```bash
# Run JS tests
npm test

# Run tests in watch mode
npm run test:watch

# Generate coverage report
npm run test:coverage
```

### E2E Testing

```bash
# Run end-to-end tests
npm run test:e2e

# Run in headed mode
npm run test:e2e:headed
```

### Writing Tests

#### PHP Test Example

```php
class Test_RMCU_Capture extends WP_UnitTestCase {
    
    public function test_capture_creation() {
        $capture_id = rmcu_create_capture( [
            'title' => 'Test Capture',
            'type' => 'video'
        ] );
        
        $this->assertIsInt( $capture_id );
        $this->assertGreaterThan( 0, $capture_id );
        
        $capture = get_post( $capture_id );
        $this->assertEquals( 'Test Capture', $capture->post_title );
    }
}
```

#### JavaScript Test Example

```javascript
describe( 'RMCUCapture', () => {
    it( 'should initialize capture', () => {
        const capture = new RMCUCapture( {
            type: 'video',
            quality: 'high'
        } );
        
        expect( capture.type ).toBe( 'video' );
        expect( capture.quality ).toBe( 'high' );
    } );
} );
```

## 🚀 Pull Request Process

### Before Submitting

1. **Update from main**
   ```bash
   git fetch origin
   git rebase origin/main
   ```

2. **Run tests**
   ```bash
   composer test
   npm test
   ```

3. **Check coding standards**
   ```bash
   composer phpcs
   npm run lint
   ```

4. **Update documentation**
   - Add/update PHPDoc blocks
   - Update README if needed
   - Add changelog entry

### PR Guidelines

#### Title Format
```
[Type] Short description

Types: Feature, Fix, Docs, Style, Refactor, Test, Chore
```

Examples:
- `[Feature] Add video compression settings`
- `[Fix] Resolve upload timeout issue`
- `[Docs] Update API documentation`

#### Description Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tests pass locally
- [ ] Added new tests
- [ ] Updated existing tests

## Screenshots (if applicable)
[Add screenshots]

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Comments added where needed
- [ ] Documentation updated
- [ ] No warnings generated
- [ ] Tests added/updated
- [ ] All tests passing
```

### Review Process

1. Automated checks run
2. Code review by maintainers
3. Address feedback
4. Approval and merge

## 🐛 Reporting Issues

### Before Reporting

1. Search existing issues
2. Check documentation
3. Verify it's not a configuration issue
4. Test with latest version

### Issue Template

```markdown
## Description
Clear description of the issue

## Steps to Reproduce
1. Go to...
2. Click on...
3. See error

## Expected Behavior
What should happen

## Actual Behavior
What actually happens

## Environment
- WordPress version:
- PHP version:
- Plugin version:
- Browser:
- Theme:
- Other plugins:

## Screenshots
[If applicable]

## Error Messages
```
[Paste any error messages]
```

## Additional Context
Any other relevant information
```

## 📚 Documentation

### Inline Documentation

```php
/**
 * Creates a new capture.
 *
 * @since 2.0.0
 * @param array $args {
 *     Optional. Arguments for creating capture.
 *
 *     @type string $title   Capture title. Default empty.
 *     @type string $type    Capture type. Default 'video'.
 *     @type int    $post_id Associated post ID. Default 0.
 * }
 * @return int|WP_Error Capture ID on success, WP_Error on failure.
 */
function rmcu_create_capture( $args = [] ) {
    // Function implementation
}
```

### README Updates

- Keep README.md up to date
- Document new features
- Update examples
- Add to FAQ if needed

## 🌐 Translation

### Adding Translatable Strings

```php
// Good - translatable
$message = __( 'Capture created successfully', 'rmcu' );
$formatted = sprintf( 
    __( 'Found %d captures', 'rmcu' ), 
    $count 
);

// Bad - not translatable
$message = 'Capture created successfully';
```

### Creating Translations

1. Generate POT file:
   ```bash
   npm run make-pot
   ```

2. Create translation:
   - Use Poedit or similar tool
   - Save as `rmcu-{locale}.po`
   - Generate `.mo` file

3. Submit translation:
   - Add files to `/languages/`
   - Submit PR

## 👥 Community

### Communication Channels

- **GitHub Discussions**: General discussion
- **GitHub Issues**: Bug reports and features
- **WordPress Forums**: User support
- **Discord**: Real-time chat
- **Email**: dev@rmcu.com

### Getting Help

- Read documentation first
- Search existing issues
- Ask in discussions
- Be specific and provide context
- Be patient and respectful

## 🏆 Recognition

Contributors will be:
- Listed in CREDITS.md
- Mentioned in release notes
- Given contributor badge
- Invited to contributor meetings

## 📄 License

By contributing, you agree that your contributions will be licensed under the same license as the project (GPL v2 or later).

## 🙏 Thank You!

Every contribution, no matter how small, helps make RMCU better. We appreciate your time and effort!

---

**Questions?** Feel free to reach out or open a discussion.