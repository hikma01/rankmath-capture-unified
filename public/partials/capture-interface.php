<?php
/**
 * Capture interface template
 *
 * @package    RankMath_Capture_Unified
 * @subpackage RankMath_Capture_Unified/public/partials
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

$type = $atts['type'];
$show_preview = filter_var($atts['show_preview'], FILTER_VALIDATE_BOOLEAN);
$auto_start = filter_var($atts['auto_start'], FILTER_VALIDATE_BOOLEAN);
?>

<div id="<?php echo esc_attr($atts['id']); ?>" class="rmcu-capture-interface <?php echo esc_attr($atts['class']); ?>" data-type="<?php echo esc_attr($type); ?>" data-max-duration="<?php echo esc_attr($atts['max_duration']); ?>">
    
    <!-- Capture Type Selector -->
    <?php if ($type === 'all') : ?>
    <div class="rmcu-capture-types">
        <h3><?php _e('Select Capture Type', 'rmcu'); ?></h3>
        <div class="rmcu-type-buttons">
            <button class="rmcu-type-btn" data-capture-type="video">
                <span class="rmcu-icon">📹</span>
                <span class="rmcu-label"><?php _e('Video', 'rmcu'); ?></span>
            </button>
            <button class="rmcu-type-btn" data-capture-type="audio">
                <span class="rmcu-icon">🎤</span>
                <span class="rmcu-label"><?php _e('Audio', 'rmcu'); ?></span>
            </button>
            <button class="rmcu-type-btn" data-capture-type="screen">
                <span class="rmcu-icon">🖥️</span>
                <span class="rmcu-label"><?php _e('Screen', 'rmcu'); ?></span>
            </button>
            <button class="rmcu-type-btn" data-capture-type="image">
                <span class="rmcu-icon">📸</span>
                <span class="rmcu-label"><?php _e('Photo', 'rmcu'); ?></span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Preview Area -->
    <?php if ($show_preview) : ?>
    <div class="rmcu-preview-container">
        <video id="rmcu-preview-<?php echo esc_attr(uniqid()); ?>" class="rmcu-preview" autoplay muted playsinline></video>
        <canvas id="rmcu-canvas-<?php echo esc_attr(uniqid()); ?>" class="rmcu-canvas" style="display:none;"></canvas>
        <audio id="rmcu-audio-<?php echo esc_attr(uniqid()); ?>" class="rmcu-audio-preview" controls style="display:none;"></audio>
        
        <!-- Recording Indicator -->
        <div class="rmcu-recording-indicator" style="display:none;">
            <span class="rmcu-rec-dot"></span>
            <span class="rmcu-rec-time">00:00</span>
        </div>
        
        <!-- Volume Meter -->
        <div class="rmcu-volume-meter" style="display:none;">
            <div class="rmcu-volume-bar"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Control Buttons -->
    <div class="rmcu-controls">
        <button class="rmcu-btn rmcu-start-btn" <?php echo $auto_start ? 'data-auto-start="true"' : ''; ?>>
            <span class="rmcu-icon">⏺️</span>
            <span class="rmcu-label"><?php _e('Start Recording', 'rmcu'); ?></span>
        </button>
        
        <button class="rmcu-btn rmcu-pause-btn" style="display:none;">
            <span class="rmcu-icon">⏸️</span>
            <span class="rmcu-label"><?php _e('Pause', 'rmcu'); ?></span>
        </button>
        
        <button class="rmcu-btn rmcu-resume-btn" style="display:none;">
            <span class="rmcu-icon">▶️</span>
            <span class="rmcu-label"><?php _e('Resume', 'rmcu'); ?></span>
        </button>
        
        <button class="rmcu-btn rmcu-stop-btn" style="display:none;">
            <span class="rmcu-icon">⏹️</span>
            <span class="rmcu-label"><?php _e('Stop Recording', 'rmcu'); ?></span>
        </button>
        
        <button class="rmcu-btn rmcu-capture-btn" style="display:none;">
            <span class="rmcu-icon">📸</span>
            <span class="rmcu-label"><?php _e('Capture Photo', 'rmcu'); ?></span>
        </button>
        
        <button class="rmcu-btn rmcu-retry-btn" style="display:none;">
            <span class="rmcu-icon">🔄</span>
            <span class="rmcu-label"><?php _e('Retry', 'rmcu'); ?></span>
        </button>
        
        <button class="rmcu-btn rmcu-save-btn" style="display:none;">
            <span class="rmcu-icon">💾</span>
            <span class="rmcu-label"><?php _e('Save', 'rmcu'); ?></span>
        </button>
        
        <button class="rmcu-btn rmcu-download-btn" style="display:none;">
            <span class="rmcu-icon">⬇️</span>
            <span class="rmcu-label"><?php _e('Download', 'rmcu'); ?></span>
        </button>
    </div>

    <!-- Settings Panel -->
    <div class="rmcu-settings-panel" style="display:none;">
        <h4><?php _e('Recording Settings', 'rmcu'); ?></h4>
        
        <!-- Video Settings -->
        <div class="rmcu-video-settings" style="display:none;">
            <label for="rmcu-camera-select"><?php _e('Camera:', 'rmcu'); ?></label>
            <select id="rmcu-camera-select" class="rmcu-device-select"></select>
            
            <label for="rmcu-resolution-select"><?php _e('Resolution:', 'rmcu'); ?></label>
            <select id="rmcu-resolution-select" class="rmcu-resolution-select">
                <option value="480p">480p</option>
                <option value="720p" selected>720p HD</option>
                <option value="1080p">1080p Full HD</option>
                <option value="4k">4K Ultra HD</option>
            </select>
        </div>
        
        <!-- Audio Settings -->
        <div class="rmcu-audio-settings" style="display:none;">
            <label for="rmcu-mic-select"><?php _e('Microphone:', 'rmcu'); ?></label>
            <select id="rmcu-mic-select" class="rmcu-device-select"></select>
            
            <label for="rmcu-audio-quality"><?php _e('Audio Quality:', 'rmcu'); ?></label>
            <select id="rmcu-audio-quality" class="rmcu-quality-select">
                <option value="low">Low (64 kbps)</option>
                <option value="medium" selected>Medium (128 kbps)</option>
                <option value="high">High (256 kbps)</option>
            </select>
        </div>
        
        <!-- Screen Settings -->
        <div class="rmcu-screen-settings" style="display:none;">
            <label>
                <input type="checkbox" id="rmcu-cursor-checkbox" checked>
                <?php _e('Show cursor', 'rmcu'); ?>
            </label>
            
            <label>
                <input type="checkbox" id="rmcu-system-audio-checkbox">
                <?php _e('Include system audio', 'rmcu'); ?>
            </label>
        </div>
    </div>

    <!-- Metadata Form -->
    <div class="rmcu-metadata-form" style="display:none;">
        <h4><?php _e('Capture Details', 'rmcu'); ?></h4>
        
        <div class="rmcu-form-field">
            <label for="rmcu-title"><?php _e('Title:', 'rmcu'); ?></label>
            <input type="text" id="rmcu-title" class="rmcu-input" placeholder="<?php esc_attr_e('Enter a title...', 'rmcu'); ?>">
        </div>
        
        <div class="rmcu-form-field">
            <label for="rmcu-description"><?php _e('Description:', 'rmcu'); ?></label>
            <textarea id="rmcu-description" class="rmcu-textarea" rows="3" placeholder="<?php esc_attr_e('Enter a description...', 'rmcu'); ?>"></textarea>
        </div>
        
        <div class="rmcu-form-field">
            <label for="rmcu-tags"><?php _e('Tags:', 'rmcu'); ?></label>
            <input type="text" id="rmcu-tags" class="rmcu-input" placeholder="<?php esc_attr_e('Enter tags separated by commas...', 'rmcu'); ?>">
        </div>
        
        <?php if (function_exists('rank_math')) : ?>
        <div class="rmcu-form-field">
            <label>
                <input type="checkbox" id="rmcu-seo-optimize" checked>
                <?php _e('Optimize for SEO with RankMath', 'rmcu'); ?>
            </label>
        </div>
        <?php endif; ?>
    </div>

    <!-- Progress Indicator -->
    <div class="rmcu-progress" style="display:none;">
        <div class="rmcu-progress-bar"></div>
        <div class="rmcu-progress-text"></div>
    </div>

    <!-- Status Messages -->
    <div class="rmcu-status-message" style="display:none;"></div>

    <!-- Error Messages -->
    <div class="rmcu-error-message" style="display:none;"></div>

    <!-- Success Message -->
    <div class="rmcu-success-message" style="display:none;">
        <p><?php _e('Capture saved successfully!', 'rmcu'); ?></p>
        <div class="rmcu-success-actions">
            <a href="#" class="rmcu-view-capture" target="_blank"><?php _e('View Capture', 'rmcu'); ?></a>
            <button class="rmcu-new-capture"><?php _e('New Capture', 'rmcu'); ?></button>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div class="rmcu-loading" style="display:none;">
        <div class="rmcu-spinner"></div>
        <p class="rmcu-loading-text"><?php _e('Processing...', 'rmcu'); ?></p>
    </div>
</div>
