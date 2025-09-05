<?php
/**
 * Capture Settings Partial
 * 
 * @package RMCU
 * @subpackage Admin/Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

$capture_settings = get_option('rmcu_capture_settings', []);
?>

<div class="rmcu-settings-section">
    <h2><?php _e('Capture Settings', 'rmcu'); ?></h2>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="enable_video_capture"><?php _e('Enable Video Capture', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="enable_video_capture" 
                       name="rmcu_capture_settings[enable_video]" 
                       value="1" 
                       <?php checked(isset($capture_settings['enable_video']) ? $capture_settings['enable_video'] : 0); ?>>
                <p class="description"><?php _e('Allow users to record video from their webcam', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="enable_audio_capture"><?php _e('Enable Audio Capture', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="enable_audio_capture" 
                       name="rmcu_capture_settings[enable_audio]" 
                       value="1" 
                       <?php checked(isset($capture_settings['enable_audio']) ? $capture_settings['enable_audio'] : 0); ?>>
                <p class="description"><?php _e('Allow users to record audio from their microphone', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="enable_screen_capture"><?php _e('Enable Screen Capture', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="enable_screen_capture" 
                       name="rmcu_capture_settings[enable_screen]" 
                       value="1" 
                       <?php checked(isset($capture_settings['enable_screen']) ? $capture_settings['enable_screen'] : 0); ?>>
                <p class="description"><?php _e('Allow users to capture their screen', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="max_recording_time"><?php _e('Max Recording Time', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="max_recording_time" 
                       name="rmcu_capture_settings[max_time]" 
                       value="<?php echo esc_attr($capture_settings['max_time'] ?? 120); ?>" 
                       min="10" 
                       max="600" 
                       step="10">
                <span><?php _e('seconds', 'rmcu'); ?></span>
                <p class="description"><?php _e('Maximum recording duration (10-600 seconds)', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="video_quality"><?php _e('Video Quality', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="video_quality" name="rmcu_capture_settings[video_quality]">
                    <option value="low" <?php selected($capture_settings['video_quality'] ?? '', 'low'); ?>>
                        <?php _e('Low (480p)', 'rmcu'); ?>
                    </option>
                    <option value="medium" <?php selected($capture_settings['video_quality'] ?? 'medium', 'medium'); ?>>
                        <?php _e('Medium (720p)', 'rmcu'); ?>
                    </option>
                    <option value="high" <?php selected($capture_settings['video_quality'] ?? '', 'high'); ?>>
                        <?php _e('High (1080p)', 'rmcu'); ?>
                    </option>
                    <option value="ultra" <?php selected($capture_settings['video_quality'] ?? '', 'ultra'); ?>>
                        <?php _e('Ultra (4K)', 'rmcu'); ?>
                    </option>
                </select>
                <p class="description"><?php _e('Higher quality will result in larger file sizes', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="audio_quality"><?php _e('Audio Quality', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="audio_quality" name="rmcu_capture_settings[audio_quality]">
                    <option value="low" <?php selected($capture_settings['audio_quality'] ?? '', 'low'); ?>>
                        <?php _e('Low (8kHz)', 'rmcu'); ?>
                    </option>
                    <option value="medium" <?php selected($capture_settings['audio_quality'] ?? 'medium', 'medium'); ?>>
                        <?php _e('Medium (22kHz)', 'rmcu'); ?>
                    </option>
                    <option value="high" <?php selected($capture_settings['audio_quality'] ?? '', 'high'); ?>>
                        <?php _e('High (44kHz)', 'rmcu'); ?>
                    </option>
                </select>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="capture_format"><?php _e('Output Format', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="capture_format" name="rmcu_capture_settings[format]">
                    <option value="webm" <?php selected($capture_settings['format'] ?? 'webm', 'webm'); ?>>
                        WebM
                    </option>
                    <option value="mp4" <?php selected($capture_settings['format'] ?? '', 'mp4'); ?>>
                        MP4
                    </option>
                </select>
                <p class="description"><?php _e('WebM is recommended for better browser compatibility', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="auto_save"><?php _e('Auto-save Captures', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="auto_save" 
                       name="rmcu_capture_settings[auto_save]" 
                       value="1" 
                       <?php checked(isset($capture_settings['auto_save']) ? $capture_settings['auto_save'] : 0); ?>>
                <p class="description"><?php _e('Automatically save captures to media library', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="show_countdown"><?php _e('Show Countdown', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="show_countdown" 
                       name="rmcu_capture_settings[show_countdown]" 
                       value="1" 
                       <?php checked(isset($capture_settings['show_countdown']) ? $capture_settings['show_countdown'] : 1); ?>>
                <p class="description"><?php _e('Display countdown before recording starts', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="countdown_duration"><?php _e('Countdown Duration', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="countdown_duration" 
                       name="rmcu_capture_settings[countdown_duration]" 
                       value="<?php echo esc_attr($capture_settings['countdown_duration'] ?? 3); ?>" 
                       min="1" 
                       max="10" 
                       step="1">
                <span><?php _e('seconds', 'rmcu'); ?></span>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="allowed_file_size"><?php _e('Max File Size', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="allowed_file_size" 
                       name="rmcu_capture_settings[max_file_size]" 
                       value="<?php echo esc_attr($capture_settings['max_file_size'] ?? 100); ?>" 
                       min="10" 
                       max="500" 
                       step="10">
                <span>MB</span>
                <p class="description"><?php _e('Maximum allowed file size for uploads', 'rmcu'); ?></p>
            </td>
        </tr>
    </table>
</div>