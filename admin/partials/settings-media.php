<?php
/**
 * Media Settings Partial
 * 
 * @package RMCU
 * @subpackage Admin/Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

$media_settings = get_option('rmcu_media_settings', []);
$upload_dir = wp_upload_dir();
?>

<div class="rmcu-settings-section">
    <h2><?php _e('Media & Storage Settings', 'rmcu'); ?></h2>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="storage_location"><?php _e('Storage Location', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="storage_location" name="rmcu_media_settings[storage_location]">
                    <option value="uploads" <?php selected($media_settings['storage_location'] ?? 'uploads', 'uploads'); ?>>
                        <?php _e('WordPress Uploads', 'rmcu'); ?>
                    </option>
                    <option value="custom" <?php selected($media_settings['storage_location'] ?? '', 'custom'); ?>>
                        <?php _e('Custom Directory', 'rmcu'); ?>
                    </option>
                    <option value="external" <?php selected($media_settings['storage_location'] ?? '', 'external'); ?>>
                        <?php _e('External Storage', 'rmcu'); ?>
                    </option>
                </select>
                <p class="description"><?php _e('Where to store captured media files', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr class="custom-storage-path" style="display: none;">
            <th scope="row">
                <label for="custom_path"><?php _e('Custom Path', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="text" 
                       id="custom_path" 
                       name="rmcu_media_settings[custom_path]" 
                       value="<?php echo esc_attr($media_settings['custom_path'] ?? '/rmcu-captures/'); ?>" 
                       class="regular-text">
                <p class="description"><?php printf(__('Relative to: %s', 'rmcu'), $upload_dir['basedir']); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="organize_by_date"><?php _e('Organize by Date', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="organize_by_date" 
                       name="rmcu_media_settings[organize_by_date]" 
                       value="1" 
                       <?php checked(isset($media_settings['organize_by_date']) ? $media_settings['organize_by_date'] : 1); ?>>
                <p class="description"><?php _e('Organize uploads into year/month folders', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="file_naming"><?php _e('File Naming Pattern', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="text" 
                       id="file_naming" 
                       name="rmcu_media_settings[file_naming]" 
                       value="<?php echo esc_attr($media_settings['file_naming'] ?? 'capture-{type}-{date}-{time}'); ?>" 
                       class="regular-text">
                <p class="description"><?php _e('Available tags: {type}, {date}, {time}, {user}, {post_id}, {random}', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="compression_level"><?php _e('Compression Level', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="range" 
                       id="compression_level" 
                       name="rmcu_media_settings[compression]" 
                       value="<?php echo esc_attr($media_settings['compression'] ?? 70); ?>" 
                       min="0" 
                       max="100" 
                       step="10">
                <span class="range-value"><?php echo esc_html($media_settings['compression'] ?? 70); ?>%</span>
                <p class="description"><?php _e('Higher values mean better quality but larger files', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="generate_sizes"><?php _e('Generate Image Sizes', 'rmcu'); ?></label>
            </th>
            <td>
                <fieldset>
                    <?php 
                    $sizes = get_intermediate_image_sizes();
                    $selected_sizes = $media_settings['generate_sizes'] ?? ['thumbnail', 'medium', 'large'];
                    foreach ($sizes as $size): 
                    ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" 
                                   name="rmcu_media_settings[generate_sizes][]" 
                                   value="<?php echo esc_attr($size); ?>"
                                   <?php checked(in_array($size, $selected_sizes)); ?>>
                            <?php echo esc_html($size); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <p class="description"><?php _e('Select which image sizes to generate for thumbnails', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="watermark_enable"><?php _e('Enable Watermark', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="watermark_enable" 
                       name="rmcu_media_settings[watermark]" 
                       value="1" 
                       <?php checked(isset($media_settings['watermark']) ? $media_settings['watermark'] : 0); ?>>
                <p class="description"><?php _e('Add watermark to captured media', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr class="watermark-settings" style="<?php echo ($media_settings['watermark'] ?? 0) ? '' : 'display: none;'; ?>">
            <th scope="row">
                <label for="watermark_text"><?php _e('Watermark Text', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="text" 
                       id="watermark_text" 
                       name="rmcu_media_settings[watermark_text]" 
                       value="<?php echo esc_attr($media_settings['watermark_text'] ?? get_bloginfo('name')); ?>" 
                       class="regular-text">
            </td>
        </tr>
        
        <tr class="watermark-settings" style="<?php echo ($media_settings['watermark'] ?? 0) ? '' : 'display: none;'; ?>">
            <th scope="row">
                <label for="watermark_position"><?php _e('Watermark Position', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="watermark_position" name="rmcu_media_settings[watermark_position]">
                    <option value="top-left" <?php selected($media_settings['watermark_position'] ?? '', 'top-left'); ?>>
                        <?php _e('Top Left', 'rmcu'); ?>
                    </option>
                    <option value="top-right" <?php selected($media_settings['watermark_position'] ?? '', 'top-right'); ?>>
                        <?php _e('Top Right', 'rmcu'); ?>
                    </option>
                    <option value="bottom-left" <?php selected($media_settings['watermark_position'] ?? '', 'bottom-left'); ?>>
                        <?php _e('Bottom Left', 'rmcu'); ?>
                    </option>
                    <option value="bottom-right" <?php selected($media_settings['watermark_position'] ?? 'bottom-right', 'bottom-right'); ?>>
                        <?php _e('Bottom Right', 'rmcu'); ?>
                    </option>
                    <option value="center" <?php selected($media_settings['watermark_position'] ?? '', 'center'); ?>>
                        <?php _e('Center', 'rmcu'); ?>
                    </option>
                </select>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="cdn_enable"><?php _e('Enable CDN', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="cdn_enable" 
                       name="rmcu_media_settings[cdn_enable]" 
                       value="1" 
                       <?php checked(isset($media_settings['cdn_enable']) ? $media_settings['cdn_enable'] : 0); ?>>
                <p class="description"><?php _e('Serve media files from CDN', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr class="cdn-settings" style="<?php echo ($media_settings['cdn_enable'] ?? 0) ? '' : 'display: none;'; ?>">
            <th scope="row">
                <label for="cdn_url"><?php _e('CDN URL', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="url" 
                       id="cdn_url" 
                       name="rmcu_media_settings[cdn_url]" 
                       value="<?php echo esc_attr($media_settings['cdn_url'] ?? ''); ?>" 
                       class="regular-text"
                       placeholder="https://cdn.example.com">
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="retention_days"><?php _e('Media Retention', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="retention_days" 
                       name="rmcu_media_settings[retention_days]" 
                       value="<?php echo esc_attr($media_settings['retention_days'] ?? 0); ?>" 
                       min="0" 
                       max="365" 
                       step="1">
                <span><?php _e('days', 'rmcu'); ?></span>
                <p class="description"><?php _e('Automatically delete old captures after X days (0 = never delete)', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="backup_enable"><?php _e('Backup to Cloud', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="backup_enable" name="rmcu_media_settings[backup_service]">
                    <option value="" <?php selected($media_settings['backup_service'] ?? '', ''); ?>>
                        <?php _e('Disabled', 'rmcu'); ?>
                    </option>
                    <option value="s3" <?php selected($media_settings['backup_service'] ?? '', 's3'); ?>>
                        Amazon S3
                    </option>
                    <option value="gcs" <?php selected($media_settings['backup_service'] ?? '', 'gcs'); ?>>
                        Google Cloud Storage
                    </option>
                    <option value="dropbox" <?php selected($media_settings['backup_service'] ?? '', 'dropbox'); ?>>
                        Dropbox
                    </option>
                </select>
                <p class="description"><?php _e('Automatically backup captures to cloud storage', 'rmcu'); ?></p>
            </td>
        </tr>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle custom storage path
    $('#storage_location').on('change', function() {
        if ($(this).val() === 'custom') {
            $('.custom-storage-path').show();
        } else {
            $('.custom-storage-path').hide();
        }
    }).trigger('change');
    
    // Toggle watermark settings
    $('#watermark_enable').on('change', function() {
        if ($(this).is(':checked')) {
            $('.watermark-settings').show();
        } else {
            $('.watermark-settings').hide();
        }
    });
    
    // Toggle CDN settings
    $('#cdn_enable').on('change', function() {
        if ($(this).is(':checked')) {
            $('.cdn-settings').show();
        } else {
            $('.cdn-settings').hide();
        }
    });
    
    // Update range value display
    $('#compression_level').on('input', function() {
        $(this).next('.range-value').text($(this).val() + '%');
    });
});
</script>