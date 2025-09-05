# RankMath Capture Unified (RMCU)

## 📸 Advanced Media Capture Plugin for WordPress with RankMath Integration

[![WordPress Version](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-2.0.0-orange.svg)](CHANGELOG.md)

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Documentation](#api-documentation)
- [Development](#development)
- [Support](#support)
- [License](#license)

## 🎯 Overview

RankMath Capture Unified (RMCU) is a comprehensive WordPress plugin that enables advanced media capture capabilities directly from your WordPress site. It seamlessly integrates with RankMath SEO to optimize captured media for search engines while providing powerful automation through n8n integration.

### Key Benefits

- **🎥 Multi-format Capture**: Record video, audio, screen, and screenshots
- **🔍 SEO Optimized**: Deep integration with RankMath for automatic SEO optimization
- **⚡ n8n Automation**: Connect with n8n workflows for advanced automation
- **🎨 Customizable**: Extensive settings and customization options
- **📱 Responsive**: Works on desktop and mobile devices
- **🔒 Secure**: Built with security best practices

## ✨ Features

### Media Capture
- **Video Recording**: Capture webcam video with customizable quality settings
- **Audio Recording**: Record high-quality audio from microphone
- **Screen Capture**: Record screen activity for tutorials and demonstrations
- **Screenshot**: Take instant screenshots with annotation support

### SEO Integration
- **RankMath Integration**: Automatic schema markup generation
- **Meta Tags**: Auto-generate SEO-friendly meta descriptions
- **Social Cards**: Create Open Graph and Twitter Cards
- **Video Sitemap**: Automatic inclusion in XML sitemap

### Storage & Processing
- **Multiple Storage Options**: Local, CDN, or cloud storage support
- **Compression**: Automatic media optimization
- **Watermarking**: Add custom watermarks to media
- **Thumbnail Generation**: Auto-create thumbnails from videos

### Automation & API
- **n8n Webhooks**: Send captures to n8n workflows
- **REST API**: Full REST API for external integrations
- **Bulk Processing**: Queue system for batch operations
- **Background Processing**: Async processing for better performance

### User Experience
- **Gutenberg Blocks**: Native block editor support
- **Classic Editor**: TinyMCE integration
- **Shortcodes**: Easy embedding with shortcodes
- **Frontend Capture**: Allow users to submit captures

## 📋 Requirements

### Minimum Requirements
- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- HTTPS enabled (required for media capture APIs)
- 128MB PHP memory limit (256MB recommended)

### Required PHP Extensions
- curl
- gd or imagick
- json
- mbstring
- dom
- fileinfo

### Recommended
- RankMath SEO plugin (for SEO features)
- n8n instance (for automation features)
- SSL certificate

## 🚀 Installation

### Via WordPress Admin

1. Download the plugin ZIP file
2. Navigate to **Plugins > Add New** in WordPress admin
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**

### Manual Installation

1. Extract the plugin ZIP file
2. Upload the `rankmath-capture-unified` folder to `/wp-content/plugins/`
3. Navigate to **Plugins** in WordPress admin
4. Find "RankMath Capture Unified" and click **Activate**

### Post-Installation

1. Navigate to **RM Capture > Settings**
2. Configure your capture preferences
3. Set up n8n webhook if using automation
4. Configure storage settings
5. Test capture functionality

## ⚙️ Configuration

### General Settings

```php
// Enable/disable features
- Enable Plugin: Master on/off switch
- Frontend Capture: Allow frontend submissions
- Gutenberg Blocks: Enable block editor support
```

### Capture Settings

```php
// Configure capture options
- Video Quality: low/medium/high/ultra
- Audio Quality: 8kHz/22kHz/44kHz
- Max Duration: 10-600 seconds
- Output Format: webm/mp4
```

### n8n Integration

1. Get your n8n webhook URL
2. Enter it in **Advanced > n8n Webhook URL**
3. Optional: Add API key for security
4. Test connection with the test button

### Storage Configuration

- **Local Storage**: Files stored in WordPress uploads
- **Custom Path**: Define custom storage directory
- **CDN**: Configure CDN URL for serving media
- **Cloud Storage**: S3, Google Cloud, Dropbox support

## 📖 Usage

### Shortcodes

```php
// Basic capture button
[rmcu_capture type="video"]

// Capture with custom settings
[rmcu_capture type="screen" duration="60" quality="high"]

// Display gallery of captures
[rmcu_gallery limit="10" user="current"]

// Embed capture player
[rmcu_player id="123" autoplay="false"]
```

### Gutenberg Blocks

1. Add block: **RMCU Capture**
2. Configure capture type and settings
3. Customize appearance
4. Preview and publish

### PHP Functions

```php
// Check if capture is enabled
if (RMCU_Config::is_enabled('video')) {
    // Video capture is enabled
}

// Get capture by ID
$capture = rmcu_get_capture(123);

// Create new capture programmatically
$capture_id = rmcu_create_capture([
    'type' => 'video',
    'title' => 'My Video',
    'file_url' => 'path/to/video.webm'
]);
```

### JavaScript API

```javascript
// Initialize capture
RMCUCapture.init({
    type: 'video',
    quality: 'high',
    duration: 120
});

// Start recording
RMCUCapture.start();

// Stop and save
RMCUCapture.stop().then(blob => {
    // Handle captured blob
});
```

## 🔌 API Documentation

### REST API Endpoints

```
GET    /wp-json/rmcu/v1/captures
GET    /wp-json/rmcu/v1/captures/{id}
POST   /wp-json/rmcu/v1/captures
PUT    /wp-json/rmcu/v1/captures/{id}
DELETE /wp-json/rmcu/v1/captures/{id}
POST   /wp-json/rmcu/v1/process
```

### Webhooks

```json
{
  "event": "capture_created",
  "data": {
    "id": 123,
    "type": "video",
    "url": "https://example.com/capture.webm",
    "metadata": {}
  }
}
```

For complete API documentation, see [API.md](API.md)

## 🛠️ Development

### Directory Structure

```
rankmath-capture-unified/
├── admin/           # Admin functionality
├── assets/          # CSS, JS, images
├── config/          # Configuration files
├── includes/        # Core plugin files
├── languages/       # Translation files
├── public/          # Frontend functionality
├── templates/       # Template files
└── vendor/          # Third-party libraries
```

### Hooks & Filters

```php
// Actions
do_action('rmcu_capture_created', $capture_id, $capture_data);
do_action('rmcu_before_process', $capture_id);
do_action('rmcu_after_process', $capture_id, $result);

// Filters
apply_filters('rmcu_capture_data', $data);
apply_filters('rmcu_allowed_types', $types);
apply_filters('rmcu_storage_path', $path);
```

### Building Assets

```bash
# Install dependencies
npm install

# Development build
npm run dev

# Production build
npm run build

# Watch for changes
npm run watch
```

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Reporting Issues

1. Check existing issues first
2. Use issue templates
3. Provide detailed information
4. Include error messages and logs

### Pull Requests

1. Fork the repository
2. Create feature branch
3. Write tests if applicable
4. Submit pull request

## 📞 Support

### Documentation
- [Installation Guide](INSTALLATION.md)
- [FAQ](FAQ.md)
- [API Documentation](API.md)
- [Changelog](CHANGELOG.md)

### Community
- WordPress.org Forums
- GitHub Issues
- Discord Server

### Premium Support
- Priority email support
- Custom development
- Training sessions

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
```

## 👏 Credits

- Developed by [Your Team Name]
- Icons by [Dashicons](https://developer.wordpress.org/resource/dashicons/)
- Video processing powered by [MediaRecorder API](https://developer.mozilla.org/en-US/docs/Web/API/MediaRecorder)

## 🔗 Links

- [Plugin Homepage](#)
- [Documentation](#)
- [Support Forum](#)
- [GitHub Repository](#)

---

Made with ❤️ for the WordPress community