# Installation Guide

## 📦 RankMath Capture Unified - Complete Installation Guide

This guide will walk you through the complete installation and setup process for the RMCU plugin.

## Prerequisites

### System Requirements

#### Minimum Requirements
- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher / MariaDB 10.0 or higher
- **Memory Limit**: 128MB (256MB recommended)
- **Max Upload Size**: 100MB minimum
- **HTTPS**: Required for media capture APIs

#### Required PHP Extensions
```bash
# Check if extensions are installed
php -m | grep -E 'curl|gd|json|mbstring|dom|fileinfo'
```

- `curl` - API communications
- `gd` or `imagick` - Image processing
- `json` - Data handling
- `mbstring` - String operations
- `dom` - XML processing
- `fileinfo` - MIME type detection

#### Browser Requirements
- Chrome 60+
- Firefox 55+
- Safari 11+
- Edge 79+

### Pre-Installation Checklist

- [ ] Backup your WordPress site
- [ ] Verify PHP version: `php -v`
- [ ] Check available memory: `php -i | grep memory_limit`
- [ ] Ensure HTTPS is enabled
- [ ] Verify write permissions on `wp-content/uploads`
- [ ] Install RankMath SEO (optional but recommended)

---

## Installation Methods

### Method 1: WordPress Admin Dashboard (Recommended)

1. **Download the Plugin**
   - Download `rankmath-capture-unified.zip` from the official source

2. **Upload via WordPress Admin**
   - Navigate to `Plugins > Add New`
   - Click `Upload Plugin` button
   - Click `Choose File` and select the ZIP file
   - Click `Install Now`

3. **Activate the Plugin**
   - Click `Activate Plugin` after installation
   - Or navigate to `Plugins > Installed Plugins`
   - Find "RankMath Capture Unified" and click `Activate`

### Method 2: FTP/SFTP Upload

1. **Extract the Plugin**
   ```bash
   unzip rankmath-capture-unified.zip
   ```

2. **Upload via FTP**
   - Connect to your server via FTP/SFTP
   - Navigate to `/wp-content/plugins/`
   - Upload the entire `rankmath-capture-unified` folder

3. **Activate in WordPress**
   - Log in to WordPress admin
   - Go to `Plugins > Installed Plugins`
   - Activate "RankMath Capture Unified"

### Method 3: Command Line (WP-CLI)

```bash
# Download and install
wp plugin install /path/to/rankmath-capture-unified.zip --activate

# Or from URL
wp plugin install https://example.com/rankmath-capture-unified.zip --activate

# Verify installation
wp plugin list | grep rmcu
```

### Method 4: Composer

```json
{
    "require": {
        "rmcu/rankmath-capture-unified": "^2.0"
    }
}
```

```bash
composer update
wp plugin activate rankmath-capture-unified
```

### Method 5: Git Clone (Development)

```bash
cd wp-content/plugins/
git clone https://github.com/your-repo/rankmath-capture-unified.git
cd rankmath-capture-unified
composer install
npm install
npm run build
```

---

## Initial Configuration

### Step 1: Verify Installation

1. Check plugin status:
   - Go to `Plugins > Installed Plugins`
   - Verify "RankMath Capture Unified" is active

2. Check for errors:
   - Enable debug mode temporarily
   ```php
   // In wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. Run requirements check:
   - Navigate to `RM Capture > System Status`
   - Review any warnings or errors

### Step 2: Basic Settings

1. **Navigate to Settings**
   - Go to `RM Capture > Settings`

2. **General Configuration**
   ```
   ✓ Enable Plugin: ON
   ✓ Show in Admin Bar: ON (optional)
   ✓ Enable Frontend Capture: Configure as needed
   ```

3. **Save Settings**
   - Click `Save Changes`
   - Verify "Settings saved" message appears

### Step 3: Configure Capture Options

1. **Video Settings**
   ```
   Quality: Medium (recommended)
   Max Duration: 120 seconds
   Format: WebM (best compatibility)
   ```

2. **Audio Settings**
   ```
   Quality: 44.1kHz (CD quality)
   Channels: Stereo
   ```

3. **Screen Capture**
   ```
   Enable: Yes
   Include Audio: Optional
   ```

### Step 4: Storage Configuration

1. **Local Storage (Default)**
   ```
   Location: WordPress Uploads
   Organization: By Year/Month
   ```

2. **Custom Directory**
   ```bash
   # Create custom directory
   mkdir -p wp-content/uploads/rmcu-captures
   chmod 755 wp-content/uploads/rmcu-captures
   ```

3. **CDN Setup (Optional)**
   ```
   CDN URL: https://cdn.yourdomain.com
   Replace URLs: Yes
   ```

### Step 5: n8n Integration (Optional)

1. **Get n8n Webhook URL**
   - Log in to your n8n instance
   - Create new workflow
   - Add Webhook node
   - Copy webhook URL

2. **Configure in RMCU**
   ```
   Advanced > n8n Webhook URL: [paste URL]
   API Key: [optional security key]
   Auto Send: Configure as needed
   ```

3. **Test Connection**
   - Click `Test Connection` button
   - Verify success message

---

## Database Setup

The plugin automatically creates required tables on activation. If manual setup is needed:

```sql
-- Check if tables exist
SHOW TABLES LIKE '%rmcu%';

-- Manual table creation (if needed)
CREATE TABLE IF NOT EXISTS `wp_rmcu_captures` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `capture_type` varchar(50) NOT NULL,
  `status` varchar(50) DEFAULT 'active',
  `file_url` text,
  `thumbnail_url` text,
  `metadata` longtext,
  `post_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`capture_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## File Permissions

Ensure correct permissions for proper operation:

```bash
# WordPress root
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Uploads directory
chmod -R 755 wp-content/uploads/
chmod -R 755 wp-content/uploads/rmcu-captures/

# Logs directory
mkdir -p wp-content/rmcu-logs
chmod 755 wp-content/rmcu-logs
echo "Deny from all" > wp-content/rmcu-logs/.htaccess

# Cache directory
mkdir -p wp-content/cache/rmcu
chmod 755 wp-content/cache/rmcu
```

---

## Web Server Configuration

### Apache (.htaccess)

```apache
# Add to .htaccess in WordPress root
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^rmcu-webhook/?$ /index.php?rmcu_webhook=1 [QSA,L]
</IfModule>

# Increase limits for media upload
php_value upload_max_filesize 100M
php_value post_max_size 100M
php_value max_execution_time 300
php_value max_input_time 300
```

### Nginx

```nginx
# Add to server block
location /rmcu-webhook {
    try_files $uri $uri/ /index.php?rmcu_webhook=1&$args;
}

# Increase limits
client_max_body_size 100M;
fastcgi_read_timeout 300;
```

---

## SSL Configuration

HTTPS is required for media capture APIs:

### Verify SSL

1. **Check SSL Status**
   ```bash
   curl -I https://yourdomain.com
   ```

2. **Force HTTPS in WordPress**
   ```php
   // In wp-config.php
   define('FORCE_SSL_ADMIN', true);
   
   // Force HTTPS for all pages
   if ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
       $_SERVER['HTTPS'] = 'on';
   ```

### Self-Signed Certificate (Development)

```bash
# Generate self-signed certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/localhost.key \
    -out /etc/ssl/certs/localhost.crt
```

---

## Performance Optimization

### PHP Configuration

```ini
; Recommended php.ini settings
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
post_max_size = 100M
upload_max_filesize = 100M
max_file_uploads = 20
```

### WordPress Optimization

```php
// In wp-config.php
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
define('COMPRESS_CSS', true);
define('COMPRESS_SCRIPTS', true);
define('CONCATENATE_SCRIPTS', false);
define('AUTOSAVE_INTERVAL', 120);
```

### Caching Configuration

If using caching plugins, exclude RMCU paths:

```
/rmcu-webhook/*
/wp-json/rmcu/*
/wp-admin/admin-ajax.php?action=rmcu_*
```

---

## Troubleshooting Installation

### Common Issues

#### Plugin Won't Activate

```bash
# Check PHP version
php -v

# Check error logs
tail -f wp-content/debug.log

# Verify file permissions
ls -la wp-content/plugins/rankmath-capture-unified/
```

#### Database Tables Not Created

```php
// Force database creation
global $wpdb;
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
require_once(RMCU_PLUGIN_DIR . 'includes/class-rmcu-activator.php');
RMCU_Activator::create_tables();
```

#### Media Capture Not Working

1. Verify HTTPS is enabled
2. Check browser console for errors
3. Test with different browser
4. Verify permissions on uploads folder

#### 500 Internal Server Error

```bash
# Increase PHP memory
echo "php_value memory_limit 256M" >> .htaccess

# Check for PHP errors
tail -f /var/log/php/error.log
```

---

## Verification Steps

### Basic Functionality Test

1. **Create Test Capture**
   - Go to any post/page editor
   - Click "Add Capture" button
   - Allow camera/microphone permissions
   - Record short test video
   - Save and verify upload

2. **Check File Storage**
   ```bash
   ls -la wp-content/uploads/rmcu-captures/
   ```

3. **Verify Database Entry**
   ```sql
   SELECT * FROM wp_rmcu_captures ORDER BY id DESC LIMIT 1;
   ```

### Advanced Testing

```php
// Test via code
$capture_id = rmcu_create_capture([
    'title' => 'Test Capture',
    'type' => 'video',
    'file_url' => 'test.webm'
]);

if (is_wp_error($capture_id)) {
    error_log('RMCU Error: ' . $capture_id->get_error_message());
} else {
    error_log('RMCU Success: Capture ID ' . $capture_id);
}
```

---

## Post-Installation

### Recommended Steps

1. **Configure User Permissions**
   - Set which user roles can create captures
   - Configure frontend capture permissions

2. **Set Up Backup**
   - Include `/rmcu-captures/` in backups
   - Export settings for backup

3. **Monitor Performance**
   - Check site speed after installation
   - Monitor server resources

4. **Test All Features**
   - Video capture
   - Audio capture
   - Screen capture
   - n8n webhook (if configured)

### Security Hardening

```bash
# Protect sensitive directories
echo "Deny from all" > wp-content/rmcu-logs/.htaccess
echo "Options -Indexes" > wp-content/uploads/rmcu-captures/.htaccess

# Set secure permissions
find wp-content/plugins/rankmath-capture-unified -type f -exec chmod 644 {} \;
find wp-content/plugins/rankmath-capture-unified -type d -exec chmod 755 {} \;
```

---

## Uninstallation

### Safe Removal

1. **Backup Your Data**
   - Export captures from database
   - Download media files
   - Export settings

2. **Deactivate Plugin**
   - Go to Plugins page
   - Deactivate RMCU

3. **Delete Plugin**
   - Click "Delete" after deactivation
   - Or remove via FTP

### Clean Uninstall

```php
// Data will be removed if this option is enabled
Settings > Advanced > Clean on Uninstall
```

### Manual Cleanup

```sql
-- Remove database tables
DROP TABLE IF EXISTS wp_rmcu_captures;
DROP TABLE IF EXISTS wp_rmcu_queue;
DROP TABLE IF EXISTS wp_rmcu_logs;

-- Remove options
DELETE FROM wp_options WHERE option_name LIKE 'rmcu_%';
```

---

## Getting Help

### Resources
- [Documentation](README.md)
- [FAQ](FAQ.md)
- [API Reference](API.md)
- [Support Forum](#)

### Contact Support
- Email: support@rmcu.com
- Discord: [Join Server](#)
- GitHub: [Issues](#)

---

**Installation Complete!** You should now have RMCU fully installed and configured.