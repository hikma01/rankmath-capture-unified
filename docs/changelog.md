# Changelog

All notable changes to RankMath Capture Unified plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### 🚧 In Development
- Advanced AI-powered video analysis
- Multi-language subtitle generation
- Real-time collaboration features
- Mobile app companion

---

## [2.0.0] - 2024-01-15

### 🎉 Major Release - Complete Rewrite

#### ✨ Added
- **n8n Integration**: Full webhook support for automation workflows
- **Screen Capture**: New screen recording capability
- **Queue System**: Background processing with retry mechanism
- **REST API**: Complete RESTful API for external integrations
- **Gutenberg Blocks**: Native block editor support
- **Cloud Storage**: Support for S3, Google Cloud, and Dropbox
- **Watermarking**: Add custom watermarks to captured media
- **Bulk Operations**: Process multiple captures at once
- **Advanced Logging**: Detailed logging system with viewer
- **Health Check**: System requirements checker
- **Import/Export**: Settings backup and restore

#### 🔄 Changed
- **Architecture**: Complete plugin architecture overhaul
- **UI/UX**: Redesigned admin interface with modern design
- **Performance**: 50% faster processing with optimized code
- **Database**: New efficient schema with proper indexing
- **File Structure**: Organized modular structure
- **Naming Convention**: Consistent naming throughout plugin
- **Error Handling**: Improved error messages and recovery

#### 🐛 Fixed
- Memory leaks during long recordings
- Browser compatibility issues with Safari
- Thumbnail generation for vertical videos
- Queue processing deadlocks
- File permission issues on shared hosting
- RankMath schema conflicts
- CDN URL replacement in captures

#### 🗑️ Removed
- Legacy code from v1.x
- Deprecated PHP functions
- jQuery dependency in frontend
- Flash-based fallback
- Internet Explorer support

#### 🔒 Security
- Added nonce verification to all AJAX calls
- Implemented capability checks
- Sanitized all user inputs
- Escaped all outputs
- Added rate limiting to API endpoints

---

## [1.5.0] - 2023-10-01

### Added
- Audio-only recording mode
- Custom thumbnail selection
- Capture scheduling
- Email notifications
- Basic n8n webhook support (beta)

### Changed
- Updated MediaRecorder API implementation
- Improved mobile responsiveness
- Better error messages

### Fixed
- Chrome 117 compatibility issues
- WordPress 6.3 compatibility
- Multisite activation errors

---

## [1.4.2] - 2023-08-15

### Fixed
- Critical security vulnerability in file upload
- XSS vulnerability in admin panel
- SQL injection in search function

### Security
- Emergency security patch - UPDATE IMMEDIATELY

---

## [1.4.1] - 2023-07-20

### Changed
- Performance improvements for large files
- Reduced memory usage by 30%

### Fixed
- Upload timeout on slow connections
- Incorrect file permissions
- Missing translations

---

## [1.4.0] - 2023-06-01

### Added
- RankMath SEO integration
- Video compression options
- Custom capture buttons
- Shortcode generator
- Debug mode

### Changed
- Minimum PHP version to 7.4
- Updated dependencies
- Improved documentation

### Fixed
- Memory exhaustion with 4K videos
- Broken pagination in media library
- JavaScript conflicts with other plugins

---

## [1.3.0] - 2023-04-15

### Added
- Screenshot capture feature
- Annotation tools
- Custom webhooks
- User role permissions
- Capture templates

### Changed
- New modern UI design
- Better mobile experience
- Faster thumbnail generation

### Fixed
- iOS Safari issues
- Firefox WebRTC bugs
- WordPress 6.2 compatibility

---

## [1.2.0] - 2023-02-01

### Added
- Live preview during recording
- Multiple quality presets
- Capture widget
- Trash/restore functionality
- Capture search and filters

### Changed
- Improved video player
- Better compression algorithms
- Enhanced metadata handling

### Fixed
- Audio sync issues
- Corrupted files on Android
- Admin menu conflicts

---

## [1.1.0] - 2022-12-01

### Added
- Audio capture support
- Basic editing tools
- Watermark feature
- Export to various formats
- Capture history

### Changed
- Redesigned settings page
- Improved error handling
- Better browser support

### Fixed
- Memory leaks
- Upload failures
- Database errors

---

## [1.0.0] - 2022-10-01

### 🎊 Initial Release

#### Features
- Video capture from webcam
- Basic WordPress integration
- Media library support
- Simple settings panel
- Shortcode support

---

## Version Guidelines

### Version Numbers
- **Major (X.0.0)**: Breaking changes, major features, architecture changes
- **Minor (0.X.0)**: New features, improvements, non-breaking changes
- **Patch (0.0.X)**: Bug fixes, security patches, minor improvements

### Release Cycle
- **Major releases**: Annually
- **Minor releases**: Quarterly
- **Patches**: As needed

### Support Policy
- **Current version**: Full support
- **Previous major**: Security updates only
- **Older versions**: No support

---

## Upgrade Notes

### From 1.x to 2.0
1. **Backup your database** before upgrading
2. Deactivate the plugin
3. Upload new version
4. Reactivate plugin
5. Run database migration from Tools menu
6. Review and update settings

### Breaking Changes in 2.0
- API endpoints have changed
- Shortcode attributes modified
- Database schema updated
- Some hooks renamed

---

## Deprecation Notices

### Deprecated in 2.0
- `rmcu_video_capture` shortcode → use `rmcu_capture`
- `getRMCUCapture()` JS function → use `RMCUCapture.get()`
- `rmcu_before_save` hook → use `rmcu_before_process`

### To be removed in 3.0
- Legacy API endpoints
- jQuery-based capture methods
- Flash fallback code

---

## Links
- [GitHub Repository](#)
- [Support Forum](#)
- [Documentation](README.md)
- [Report Issues](#)