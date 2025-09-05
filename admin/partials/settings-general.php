<?php
/**
 * RMCU Settings - General Tab
 * 
 * @package RMCU
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="rmcu-settings-section">
    <h2><?php _e('General Settings', 'rmcu'); ?></h2>
    <p class="description"><?php _e('Configure the basic settings for RankMath Content & Media Capture.', 'rmcu'); ?></p>

    <table class="form-table rmcu-settings-table">
        <!-- Enable/Disable Plugin -->
        <tr>
            <th scope="row">
                <label for="rmcu_enable_plugin"><?php _e('Enable Plugin', 'rmcu'); ?></label>
            </th>
            <td>
                <label class="rmcu-switch">
                    <input type="checkbox" 
                           id="rmcu_enable_plugin" 
                           name="rmcu_enable_plugin" 
                           value="1" 
                           <?php checked(get_option('rmcu_enable_plugin', 1), 1); ?>>
                    <span class="rmcu-slider"></span>
                </label>
                <p class="description">
                    <?php _e('Enable or disable the RMCU plugin functionality.', 'rmcu'); ?>
                </p>
            </td>
        </tr>

        <!-- Auto-save Captures -->
        <tr>
            <th scope="row">
                <label for="rmcu_auto_save"><?php _e('Auto-save Captures', 'rmcu'); ?></label>
            </th>
            <td>
                <label class="rmcu-switch">
                    <input type="checkbox" 
                           id="rmcu_auto_save" 
                           name="rmcu_auto_save" 
                           value="1" 
                           <?php checked(get_option('rmcu_auto_save', 0), 1); ?>>
                    <span class="rmcu-slider"></span>
                </label>
                <p class="description">
                    <?php _e('Automatically save captures without confirmation.', 'rmcu'); ?>
                </p>
            </td>
        </tr>

        <!-- Default Storage Location -->
        <tr>
            <th scope="row">
                <label for="rmcu_storage_location"><?php _e('Storage Location', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="rmcu_storage_location" name="rmcu_storage_location" class="regular-text">
                    <option value="uploads" <?php selected(get_option('rmcu_storage_location', 'uploads'), 'uploads'); ?>>
                        <?php _e('WordPress Uploads', 'rmcu'); ?>
                    </option>
                    <option value="custom" <?php selected(get_option('rmcu_storage_location'), 'custom'); ?>>
                        <?php _e('Custom Directory', 'rmcu'); ?>
                    </option>
                    <option value="cloud" <?php selected(get_option('rmcu_storage_location'), 'cloud'); ?>>
                        <?php _e('Cloud Storage', 'rmcu'); ?>
                    </option>
                </select>
                
                <div class="rmcu-conditional-field" data-condition="rmcu_storage_location=custom">
                    <input type="text" 
                           name="rmcu_custom_directory" 
                           class="regular-text" 
                           value="<?php echo esc_attr(get_option('rmcu_custom_directory')); ?>"
                           placeholder="/wp-content/uploads/rmcu/">
                    <p class="description">
                        <?php _e('Enter the custom directory path for storing captures.', 'rmcu'); ?>
                    </p>
                </div>
                
                <div class="rmcu-conditional-field" data-condition="rmcu_storage_location=cloud">
                    <select name="rmcu_cloud_provider" class="regular-text">
                        <option value="s3" <?php selected(get_option('rmcu_cloud_provider'), 's3'); ?>>Amazon S3</option>
                        <option value="gcs" <?php selected(get_option('rmcu_cloud_provider'), 'gcs'); ?>>Google Cloud Storage</option>
                        <option value="azure" <?php selected(get_option('rmcu_cloud_provider'), 'azure'); ?>>Azure Blob</option>
                    </select>
                </div>
            </td>
        </tr>

        <!-- Compression Level -->
        <tr>
            <th scope="row">
                <label for="rmcu_compression_level"><?php _e('Compression Level', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="range" 
                       id="rmcu_compression_level" 
                       name="rmcu_compression_level" 
                       min="0" 
                       max="100" 
                       step="5"
                       value="<?php echo esc_attr(get_option('rmcu_compression_level', 80)); ?>">
                <span class="range-value"><?php echo get_option('rmcu_compression_level', 80); ?></span>%
                <p class="description">
                    <?php _e('Set the compression level for images and videos. Lower values mean higher compression.', 'rmcu'); ?>
                </p>
            </td>
        </tr>

        <!-- File Naming Convention -->
        <tr>
            <th scope="row">
                <label for="rmcu_file_naming"><?php _e('File Naming', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="rmcu_file_naming" name="rmcu_file_naming" class="regular-text">
                    <option value="timestamp" <?php selected(get_option('rmcu_file_naming', 'timestamp'), 'timestamp'); ?>>
                        <?php _e('Timestamp (capture_20231225_143022)', 'rmcu'); ?>
                    </option>
                    <option value="sequential" <?php selected(get_option('rmcu_file_naming'), 'sequential'); ?>>
                        <?php _e('Sequential (capture_001, capture_002)', 'rmcu'); ?>
                    </option>
                    <option value="custom" <?php selected(get_option('rmcu_file_naming'), 'custom'); ?>>
                        <?php _e('Custom Pattern', 'rmcu'); ?>
                    </option>
                </select>
                
                <div class="rmcu-conditional-field" data-condition="rmcu_file_naming=custom">
                    <input type="text" 
                           name="rmcu_file_pattern" 
                           class="regular-text" 
                           value="<?php echo esc_attr(get_option('rmcu_file_pattern', '{type}_{date}_{time}')); ?>"
                           placeholder="{type}_{date}_{time}">
                    <p class="description">
                        <?php _e('Available placeholders: {type}, {date}, {time}, {user}, {post_id}', 'rmcu'); ?>
                    </p>
                </div>
            </td>
        </tr>

        <!-- User Permissions -->
        <tr>
            <th scope="row">
                <label><?php _e('User Permissions', 'rmcu'); ?></label>
            </th>
            <td>
                <fieldset>
                    <?php
                    $roles = wp_roles()->roles;
                    $allowed_roles = get_option('rmcu_allowed_roles', ['administrator', 'editor']);
                    
                    foreach ($roles as $role_key => $role) {
                        ?>
                        <label class="rmcu-checkbox-label">
                            <input type="checkbox" 
                                   name="rmcu_allowed_roles[]" 
                                   value="<?php echo esc_attr($role_key); ?>"
                                   <?php checked(in_array($role_key, $allowed_roles)); ?>>
                            <?php echo esc_html($role['name']); ?>
                        </label><br>
                        <?php
                    }
                    ?>
                </fieldset>
                <p class="description">
                    <?php _e('Select which user roles can use the capture features.', 'rmcu'); ?>
                </p>
            </td>
        </tr>

        <!-- Delete Files on Uninstall -->
        <tr>
            <th scope="row">
                <label for="rmcu_delete_on_uninstall"><?php _e('Delete Data on Uninstall', 'rmcu'); ?></label>
            </th>
            <td>
                <label class="rmcu-switch">
                    <input type="checkbox" 
                           id="rmcu_delete_on_uninstall" 
                           name="rmcu_delete_on_uninstall" 
                           value="1" 
                           <?php checked(get_option('rmcu_delete_on_uninstall', 0), 1); ?>>
                    <span class="rmcu-slider"></span>
                </label>
                <p class="description rmcu-warning">
                    <?php _e('Warning: This will permanently delete all captures and settings when the plugin is uninstalled.', 'rmcu'); ?>
                </p>
            </td>
        </tr>

        <!-- Email Notifications -->
        <tr>
            <th scope="row">
                <label for="rmcu_enable_notifications"><?php _e('Email Notifications', 'rmcu'); ?></label>
            </th>
            <td>
                <label class="rmcu-switch">
                    <input type="checkbox" 
                           id="rmcu_enable_notifications" 
                           name="rmcu_enable_notifications" 
                           value="1" 
                           <?php checked(get_option('rmcu_enable_notifications', 0), 1); ?>>
                    <span class="rmcu-slider"></span>
                </label>
                
                <div class="rmcu-conditional-field" data-condition="rmcu_enable_notifications=true">
                    <input type="email" 
                           name="rmcu_notification_email" 
                           class="regular-text" 
                           value="<?php echo esc_attr(get_option('rmcu_notification_email', get_option('admin_email'))); ?>"
                           placeholder="admin@example.com">
                    
                    <fieldset class="rmcu-notification-events">
                        <legend><?php _e('Send notifications for:', 'rmcu'); ?></legend>
                        <?php
                        $events = [
                            'new_capture' => __('New Capture Created', 'rmcu'),
                            'storage_limit' => __('Storage Limit Reached', 'rmcu'),
                            'error' => __('Capture Errors', 'rmcu'),
                            'weekly_report' => __('Weekly Report', 'rmcu')
                        ];
                        
                        $selected_events = get_option('rmcu_notification_events', ['error']);
                        
                        foreach ($events as $event_key => $event_label) {
                            ?>
                            <label class="rmcu-checkbox-label">
                                <input type="checkbox" 
                                       name="rmcu_notification_events[]" 
                                       value="<?php echo esc_attr($event_key); ?>"
                                       <?php checked(in_array($event_key, $selected_events)); ?>>
                                <?php echo esc_html($event_label); ?>
                            </label><br>
                            <?php
                        }
                        ?>
                    </fieldset>
                </div>
            </td>
        </tr>

        <!-- Privacy Settings -->
        <tr>
            <th scope="row">
                <label><?php _e('Privacy', 'rmcu'); ?></label>
            </th>
            <td>
                <label class="rmcu-checkbox-label">
                    <input type="checkbox" 
                           name="rmcu_anonymize_data" 
                           value="1" 
                           <?php checked(get_option('rmcu_anonymize_data', 0), 1); ?>>
                    <?php _e('Anonymize user data in captures', 'rmcu'); ?>
                </label><br>
                
                <label class="rmcu-checkbox-label">
                    <input type="checkbox" 
                           name="rmcu_gdpr_compliance" 
                           value="1" 
                           <?php checked(get_option('rmcu_gdpr_compliance', 1), 1); ?>>
                    <?php _e('Enable GDPR compliance features', 'rmcu'); ?>
                </label>
                
                <p class="description">
                    <?php _e('Configure privacy settings to comply with data protection regulations.', 'rmcu'); ?>
                </p>
            </td>
        </tr>

        <!-- Default Language -->
        <tr>
            <th scope="row">
                <label for="rmcu_default_language"><?php _e('Interface Language', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="rmcu_default_language" name="rmcu_default_language" class="regular-text">
                    <option value="auto" <?php selected(get_option('rmcu_default_language', 'auto'), 'auto'); ?>>
                        <?php _e('Auto-detect', 'rmcu'); ?>
                    </option>
                    <?php
                    $languages = [
                        'en_US' => 'English',
                        'es_ES' => 'Español',
                        'fr_FR' => 'Français',
                        'de_DE' => 'Deutsch',
                        'it_IT' => 'Italiano',
                        'pt_BR' => 'Português',
                        'nl_NL' => 'Nederlands',
                        'ru_RU' => 'Русский',
                        'ja' => '日本語',
                        'zh_CN' => '中文'
                    ];
                    
                    foreach ($languages as $lang_code => $lang_name) {
                        ?>
                        <option value="<?php echo $lang_code; ?>" <?php selected(get_option('rmcu_default_language'), $lang_code); ?>>
                            <?php echo $lang_name; ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
                <p class="description">
                    <?php _e('Select the language for the plugin interface.', 'rmcu'); ?>
                </p>
            </td>
        </tr>
    </table>
</div>

<!-- Additional Settings Section -->
<div class="rmcu-settings-section">
    <h2><?php _e('Performance Settings', 'rmcu'); ?></h2>
    
    <table class="form-table rmcu-settings-table">
        <!-- Lazy Loading -->
        <tr>
            <th scope="row">
                <label for="rmcu_lazy_loading"><?php _e('Lazy Loading', 'rmcu'); ?></label>
            </th>
            <td>
                <label class="rmcu-switch">
                    <input type="checkbox" 
                           id="rmcu_lazy_loading" 
                           name="rmcu_lazy_loading" 
                           value="1" 
                           <?php checked(get_option('rmcu_lazy_loading', 1), 1); ?>>
                    <span class="rmcu-slider"></span>
                </label>
                <p class="description">
                    <?php _e('Enable lazy loading for captured media to improve page load times.', 'rmcu'); ?>
                </p>
            </td>
        </tr>

        <!-- Cache Duration -->
        <tr>
            <th scope="row">
                <label for="rmcu_cache_duration"><?php _e('Cache Duration', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="rmcu_cache_duration" name="rmcu_cache_duration" class="regular-text">
                    <option value="0" <?php selected(get_option('rmcu_cache_duration', 3600), 0); ?>>
                        <?php _e('No Cache', 'rmcu'); ?>
                    </option>
                    <option value="3600" <?php selected(get_option('rmcu_cache_duration', 3600), 3600); ?>>
                        <?php _e('1 Hour', 'rmcu'); ?>
                    </option>
                    <option value="21600" <?php selected(get_option('rmcu_cache_duration'), 21600); ?>>
                        <?php _e('6 Hours', 'rmcu'); ?>
                    </option>
                    <option value="86400" <?php selected(get_option('rmcu_cache_duration'), 86400); ?>>
                        <?php _e('24 Hours', 'rmcu'); ?>
                    </option>
                    <option value="604800" <?php selected(get_option('rmcu_cache_duration'), 604800); ?>>
                        <?php _e('1 Week', 'rmcu'); ?>
                    </option>
                </select>
                <p class="description">
                    <?php _e('How long to cache capture data and analysis results.', 'rmcu'); ?>
                </p>
            </td>
        </tr>

        <!-- Batch Processing -->
        <tr>
            <th scope="row">
                <label for="rmcu_batch_size"><?php _e('Batch Processing Size', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="rmcu_batch_size" 
                       name="rmcu_batch_size" 
                       min="1" 
                       max="100" 
                       value="<?php echo esc_attr(get_option('rmcu_batch_size', 10)); ?>"
                       class="small-text">
                <p class="description">
                    <?php _e('Number of items to process in each batch operation.', 'rmcu'); ?>
                </p>
            </td>
        </tr>
    </table>
</div>