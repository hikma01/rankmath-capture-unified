# Frequently Asked Questions (FAQ)

## 📋 RankMath Capture Unified - Common Questions & Answers

### General Questions

<details>
<summary><strong>What is RankMath Capture Unified?</strong></summary>

RMCU is a comprehensive WordPress plugin that enables media capture directly from your browser. It allows users to:
- Record video from webcam
- Record audio from microphone
- Capture screen activity
- Take screenshots
- Integrate with RankMath SEO
- Automate with n8n workflows

</details>

<details>
<summary><strong>Is RMCU free or paid?</strong></summary>

RMCU offers both free and premium versions:

**Free Version:**
- Basic video/audio capture
- WordPress media library integration
- Basic settings
- Community support

**Premium Version:**
- Advanced features (screen capture, effects)
- n8n integration
- Cloud storage
- Priority support
- Advanced processing options

</details>

<details>
<summary><strong>Do I need RankMath SEO to use this plugin?</strong></summary>

No, RankMath SEO is not required. However, having RankMath installed enables additional features:
- Automatic schema markup
- SEO optimization for captures
- Integration with RankMath Content AI
- Enhanced metadata generation

The plugin works perfectly fine without RankMath, but you'll get the best experience with it installed.

</details>

---

### Installation & Requirements

<details>
<summary><strong>What are the minimum requirements?</strong></summary>

**Server Requirements:**
- WordPress 5.8+
- PHP 7.4+
- MySQL 5.6+
- 128MB memory (256MB recommended)
- HTTPS enabled

**Browser Requirements:**
- Chrome 60+
- Firefox 55+
- Safari 11+
- Edge 79+

</details>

<details>
<summary><strong>Why does the plugin require HTTPS?</strong></summary>

Modern browsers require HTTPS for accessing camera and microphone due to security restrictions. The MediaStream API (used for capture) only works on:
- HTTPS connections
- localhost (for development)

Without HTTPS, capture features won't work.

</details>

<details>
<summary><strong>I'm getting a "Requirements not met" error. What should I do?</strong></summary>

1. Check PHP version: `php -v`
2. Verify WordPress version in admin
3. Check required PHP extensions:
   ```bash
   php -m | grep -E 'curl|gd|json|mbstring'
   ```
4. Go to `RM Capture > System Status` for detailed report
5. Contact your hosting provider if updates are needed

</details>

---

### Media Capture

<details>
<summary><strong>Why can't I see the capture button?</strong></summary>

Common causes and solutions:

1. **JavaScript conflict:** Check browser console for errors
2. **Permissions:** Ensure your user role has capture permissions
3. **Browser blocking:** Check if browser is blocking camera/microphone
4. **Cache issue:** Clear browser and WordPress cache
5. **Theme conflict:** Try with default WordPress theme

</details>

<details>
<summary><strong>The camera/microphone isn't working. How do I fix it?</strong></summary>

1. **Check browser permissions:**
   - Click padlock icon in address bar
   - Allow camera and microphone access
   - Refresh the page

2. **Check system permissions:**
   - Windows: Settings > Privacy > Camera/Microphone
   - Mac: System Preferences > Security & Privacy > Camera/Microphone

3. **Test hardware:**
   - Try another application (Zoom, Skype)
   - Check device manager
   - Update drivers if needed

4. **Browser specific:**
   - Chrome: chrome://settings/content/camera
   - Firefox: about:preferences#privacy
   - Safari: Preferences > Websites > Camera

</details>

<details>
<summary><strong>What video formats are supported?</strong></summary>

**Recording formats:**
- WebM (recommended, best compatibility)
- MP4 (requires additional processing)

**Playback formats:**
- WebM
- MP4
- MOV
- AVI (converted automatically)

**Audio formats:**
- WebM audio
- MP3
- WAV
- OGG

</details>

<details>
<summary><strong>What's the maximum recording duration?</strong></summary>

Default: 120 seconds (2 minutes)

You can change this in Settings:
- Minimum: 10 seconds
- Maximum: 600 seconds (10 minutes)
- Recommended: 120-180 seconds

Longer recordings may cause:
- Memory issues
- Upload timeouts
- Storage problems

</details>

<details>
<summary><strong>Can I record screen with audio?</strong></summary>

Yes! Screen recording supports:
- Screen only
- Screen + microphone
- Screen + system audio (Chrome only with extension)
- Screen + webcam (picture-in-picture)

Note: System audio recording requires browser extensions or special permissions.

</details>

---

### Storage & Files

<details>
<summary><strong>Where are captured files stored?</strong></summary>

By default: `/wp-content/uploads/rmcu-captures/`

You can configure storage location:
1. Go to `Settings > Media`
2. Choose from:
   - WordPress uploads (default)
   - Custom directory
   - External storage (S3, Google Cloud)

</details>

<details>
<summary><strong>What's the file size limit?</strong></summary>

Default: 100MB

Limits depend on:
- PHP `upload_max_filesize`
- PHP `post_max_size`
- Server timeout settings
- Available storage space

To increase:
```php
// In .htaccess or php.ini
php_value upload_max_filesize 200M
php_value post_max_size 200M
```

</details>

<details>
<summary><strong>How do I enable cloud storage?</strong></summary>

**Amazon S3:**
1. Install S3 plugin (e.g., WP Offload Media)
2. Configure S3 credentials
3. Select S3 in RMCU storage settings

**Google Cloud Storage:**
1. Set up GCS bucket
2. Configure service account
3. Add credentials to RMCU

**Dropbox:**
1. Create Dropbox app
2. Get access token
3. Configure in RMCU settings

</details>

---

### n8n Integration

<details>
<summary><strong>What is n8n and why would I use it?</strong></summary>

n8n is an automation tool that allows you to:
- Process captures automatically
- Send to external services
- Trigger workflows
- Transform data
- Integrate with 200+ services

Use cases:
- Auto-upload to YouTube
- Transcribe audio
- Generate thumbnails
- Send notifications
- Archive to cloud

</details>

<details>
<summary><strong>How do I set up n8n webhook?</strong></summary>

1. **In n8n:**
   - Create new workflow
   - Add Webhook node
   - Set to POST method
   - Copy webhook URL

2. **In RMCU:**
   - Go to `Settings > Advanced`
   - Paste webhook URL
   - Add API key (optional)
   - Test connection

3. **Handle data in n8n:**
   ```javascript
   // Webhook will receive:
   {
     "capture_id": 123,
     "type": "video",
     "url": "https://...",
     "metadata": {...}
   }
   ```

</details>

---

### Troubleshooting

<details>
<summary><strong>Capture fails with "Processing failed" error</strong></summary>

Common solutions:

1. **Increase PHP limits:**
   ```php
   ini_set('memory_limit', '256M');
   set_time_limit(300);
   ```

2. **Check error logs:**
   - WordPress debug.log
   - PHP error log
   - RMCU logs in `RM Capture > Logs`

3. **Verify permissions:**
   - Uploads folder writable
   - Database tables created
   - User has correct role

4. **Test with smaller capture:**
   - Lower quality setting
   - Shorter duration
   - Disable effects

</details>

<details>
<summary><strong>Videos won't play after upload</strong></summary>

Possible causes:

1. **MIME type issue:**
   ```php
   // Add to functions.php
   add_filter('upload_mimes', function($mimes) {
       $mimes['webm'] = 'video/webm';
       return $mimes;
   });
   ```

2. **Codec compatibility:**
   - Use WebM format
   - Check browser support
   - Try different player

3. **CDN/Cache issue:**
   - Clear CDN cache
   - Check CORS headers
   - Verify CDN URL

</details>

<details>
<summary><strong>Getting 413 "Request Entity Too Large" error</strong></summary>

Server upload limit exceeded. Fix:

**Apache:**
```apache
LimitRequestBody 104857600
```

**Nginx:**
```nginx
client_max_body_size 100M;
```

**PHP:**
```ini
upload_max_filesize = 100M
post_max_size = 100M
```

</details>

---

### Performance

<details>
<summary><strong>The plugin is slowing down my site. What can I do?</strong></summary>

Optimization steps:

1. **Enable caching:**
   - Use caching plugin
   - Enable RMCU cache
   - Use CDN for media

2. **Optimize settings:**
   - Lower default quality
   - Reduce thumbnail sizes
   - Enable lazy loading

3. **Use async processing:**
   - Enable in Advanced settings
   - Configure WP-Cron properly
   - Use real cron if possible

4. **Database optimization:**
   - Clean old captures
   - Optimize tables
   - Remove unused data

</details>

<details>
<summary><strong>How can I improve capture quality?</strong></summary>

For better quality:

1. **Video settings:**
   - Quality: High or Ultra
   - Resolution: 1080p or 4K
   - Bitrate: 3000-6000 kbps
   - Frame rate: 30-60 fps

2. **Hardware:**
   - Better webcam
   - Good lighting
   - Stable internet
   - Sufficient RAM

3. **Environment:**
   - Close other apps
   - Use wired connection
   - Good microphone
   - Quiet space

</details>

---

### Security & Privacy

<details>
<summary><strong>Is captured data secure?</strong></summary>

Security measures:

1. **Data protection:**
   - Files stored securely
   - Database encrypted
   - Secure transmission (HTTPS)

2. **Access control:**
   - Role-based permissions
   - Nonce verification
   - Capability checks

3. **Privacy:**
   - No third-party tracking
   - GDPR compliant
   - User consent required

</details>

<details>
<summary><strong>Can users capture content on frontend?</strong></summary>

Yes, with controls:

1. Enable frontend capture in settings
2. Configure allowed roles
3. Set moderation options
4. Add consent checkbox
5. Implement captcha (recommended)

Security considerations:
- Moderate submissions
- Limit file sizes
- Scan for malware
- Set rate limits

</details>

---

### Updates & Support

<details>
<summary><strong>How do I update the plugin?</strong></summary>

**Automatic updates:**
1. Enable auto-updates in WordPress
2. Or go to `Plugins` page
3. Click `Update Now` when available

**Manual update:**
1. Backup your site
2. Deactivate plugin
3. Upload new version
4. Reactivate plugin
5. Check for database updates

**Important:** Always backup before major updates!

</details>

<details>
<summary><strong>Where can I get support?</strong></summary>

**Free support:**
- WordPress.org forums
- GitHub issues
- Documentation
- FAQ (this document)

**Premium support:**
- Priority email support
- Live chat
- Phone support
- Custom development

**Community:**
- Discord server
- Facebook group
- Reddit community

</details>

---

### Common Errors

<details>
<summary><strong>Error: "Failed to initialize capture device"</strong></summary>

```javascript
// Browser console error
NotAllowedError: Permission denied
```

**Solution:**
1. Check camera/microphone permissions
2. Ensure HTTPS is enabled
3. Allow permissions when prompted
4. Check if device is in use

</details>

<details>
<summary><strong>Error: "Maximum execution time exceeded"</strong></summary>

```php
Fatal error: Maximum execution time of 30 seconds exceeded
```

**Solution:**
```php
// Increase in .htaccess
php_value max_execution_time 300

// Or in wp-config.php
set_time_limit(300);
```

</details>

<details>
<summary><strong>Error: "Allowed memory size exhausted"</strong></summary>

```php
Fatal error: Allowed memory size of 134217728 bytes exhausted
```

**Solution:**
```php
// Increase memory limit
define('WP_MEMORY_LIMIT', '256M');
ini_set('memory_limit', '256M');
```

</details>

---

### Advanced Topics

<details>
<summary><strong>Can I customize the capture interface?</strong></summary>

Yes, several ways:

1. **CSS customization:**
   ```css
   /* Add to theme or custom CSS */
   .rmcu-capture-container {
       background: custom-color;
   }
   ```

2. **Template override:**
   - Copy from `plugin/templates/`
   - Paste to `theme/rmcu/`
   - Modify as needed

3. **Hooks and filters:**
   ```php
   add_filter('rmcu_capture_button_text', function() {
       return 'Custom Text';
   });
   ```

</details>

<details>
<summary><strong>How do I add capture programmatically?</strong></summary>

```php
// PHP example
$capture_id = rmcu_create_capture([
    'title' => 'Programmatic Capture',
    'type' => 'video',
    'file' => '/path/to/file.webm',
    'post_id' => 123,
    'metadata' => [
        'duration' => 60,
        'custom_field' => 'value'
    ]
]);

// JavaScript example
RMCUCapture.create({
    type: 'video',
    quality: 'high',
    onComplete: function(capture) {
        console.log('Capture created:', capture);
    }
});
```

</details>

---

### Miscellaneous

<details>
<summary><strong>Can I use RMCU on multisite?</strong></summary>

Yes! RMCU supports WordPress Multisite:

**Network activation:**
- Activate for all sites
- Configure per-site settings
- Shared or separate storage

**Considerations:**
- Storage usage per site
- User permissions across network
- Database tables per site

</details>

<details>
<summary><strong>Is the plugin GDPR compliant?</strong></summary>

Yes, RMCU is GDPR-ready:

✅ User consent for capture
✅ Data portability (export)
✅ Right to deletion
✅ Privacy policy integration
✅ No external tracking
✅ Secure data storage

Add privacy policy text:
```
This site uses RMCU to capture media content. 
Captured data is stored securely and not shared 
with third parties without consent.
```

</details>

<details>
<summary><strong>Can I white-label the plugin?</strong></summary>

Premium version supports white-labeling:
- Custom branding
- Remove RMCU references
- Custom menu names
- Your logo/colors

Contact support for white-label license.

</details>

---

## Still Have Questions?

### Before Contacting Support

1. ✓ Check this FAQ
2. ✓ Read [Documentation](README.md)
3. ✓ Search forums
4. ✓ Check error logs
5. ✓ Test with default theme/plugins

### Contact Information

- **Email:** support@rmcu.com
- **Forum:** [WordPress.org](https://wordpress.org/support/plugin/rmcu)
- **GitHub:** [Issues](https://github.com/rmcu/issues)
- **Discord:** [Join Server](#)

---

*Last updated: January 2024*