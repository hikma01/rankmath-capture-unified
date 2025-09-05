<?php
/**
 * Advanced Settings Partial
 * 
 * @package RMCU
 * @subpackage Admin/Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

$advanced_settings = get_option('rmcu_advanced_settings', []);
?>

<div class="rmcu-settings-section">
    <h2><?php _e('Advanced Settings', 'rmcu'); ?></h2>
    
    <div class="notice notice-warning inline">
        <p><?php _e('⚠️ These settings are for advanced users. Incorrect configuration may affect plugin functionality.', 'rmcu'); ?></p>
    </div>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="debug_mode"><?php _e('Debug Mode', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="debug_mode" 
                       name="rmcu_advanced_settings[debug_mode]" 
                       value="1" 
                       <?php checked(isset($advanced_settings['debug_mode']) ? $advanced_settings['debug_mode'] : 0); ?>>
                <p class="description"><?php _e('Enable detailed logging for debugging', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="log_level"><?php _e('Log Level', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="log_level" name="rmcu_advanced_settings[log_level]">
                    <option value="error" <?php selected($advanced_settings['log_level'] ?? 'error', 'error'); ?>>
                        <?php _e('Errors Only', 'rmcu'); ?>
                    </option>
                    <option value="warning" <?php selected($advanced_settings['log_level'] ?? '', 'warning'); ?>>
                        <?php _e('Warnings & Errors', 'rmcu'); ?>
                    </option>
                    <option value="info" <?php selected($advanced_settings['log_level'] ?? '', 'info'); ?>>
                        <?php _e('Info, Warnings & Errors', 'rmcu'); ?>
                    </option>
                    <option value="debug" <?php selected($advanced_settings['log_level'] ?? '', 'debug'); ?>>
                        <?php _e('Everything (Debug)', 'rmcu'); ?>
                    </option>
                </select>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="api_timeout"><?php _e('API Timeout', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="api_timeout" 
                       name="rmcu_advanced_settings[api_timeout]" 
                       value="<?php echo esc_attr($advanced_settings['api_timeout'] ?? 30); ?>" 
                       min="5" 
                       max="120" 
                       step="5">
                <span><?php _e('seconds', 'rmcu'); ?></span>
                <p class="description"><?php _e('Maximum time to wait for API responses', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="n8n_webhook_url"><?php _e('n8n Webhook URL', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="url" 
                       id="n8n_webhook_url" 
                       name="rmcu_advanced_settings[n8n_webhook_url]" 
                       value="<?php echo esc_attr($advanced_settings['n8n_webhook_url'] ?? ''); ?>" 
                       class="regular-text"
                       placeholder="https://your-n8n.com/webhook/xxx">
                <button type="button" class="button" id="test-n8n-connection"><?php _e('Test Connection', 'rmcu'); ?></button>
                <p class="description"><?php _e('n8n webhook endpoint for automation', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="n8n_api_key"><?php _e('n8n API Key', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="password" 
                       id="n8n_api_key" 
                       name="rmcu_advanced_settings[n8n_api_key]" 
                       value="<?php echo esc_attr($advanced_settings['n8n_api_key'] ?? ''); ?>" 
                       class="regular-text">
                <p class="description"><?php _e('Optional API key for secure communication', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="cache_enable"><?php _e('Enable Caching', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="cache_enable" 
                       name="rmcu_advanced_settings[cache_enable]" 
                       value="1" 
                       <?php checked(isset($advanced_settings['cache_enable']) ? $advanced_settings['cache_enable'] : 1); ?>>
                <p class="description"><?php _e('Cache processed captures for better performance', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="cache_duration"><?php _e('Cache Duration', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="cache_duration" 
                       name="rmcu_advanced_settings[cache_duration]" 
                       value="<?php echo esc_attr($advanced_settings['cache_duration'] ?? 3600); ?>" 
                       min="60" 
                       max="86400" 
                       step="60">
                <span><?php _e('seconds', 'rmcu'); ?></span>
                <button type="button" class="button" id="clear-cache"><?php _e('Clear Cache Now', 'rmcu'); ?></button>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="async_processing"><?php _e('Async Processing', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="async_processing" 
                       name="rmcu_advanced_settings[async_processing]" 
                       value="1" 
                       <?php checked(isset($advanced_settings['async_processing']) ? $advanced_settings['async_processing'] : 0); ?>>
                <p class="description"><?php _e('Process captures in background (requires WP-Cron)', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="batch_size"><?php _e('Batch Processing Size', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="batch_size" 
                       name="rmcu_advanced_settings[batch_size]" 
                       value="<?php echo esc_attr($advanced_settings['batch_size'] ?? 5); ?>" 
                       min="1" 
                       max="20" 
                       step="1">
                <p class="description"><?php _e('Number of items to process in each batch', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="allowed_roles"><?php _e('Allowed User Roles', 'rmcu'); ?></label>
            </th>
            <td>
                <fieldset>
                    <?php 
                    $roles = wp_roles()->roles;
                    $allowed_roles = $advanced_settings['allowed_roles'] ?? ['administrator', 'editor'];
                    foreach ($roles as $role_key => $role): 
                    ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" 
                                   name="rmcu_advanced_settings[allowed_roles][]" 
                                   value="<?php echo esc_attr($role_key); ?>"
                                   <?php checked(in_array($role_key, $allowed_roles)); ?>>
                            <?php echo esc_html($role['name']); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <p class="description"><?php _e('User roles that can use capture features', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="custom_css"><?php _e('Custom CSS', 'rmcu'); ?></label>
            </th>
            <td>
                <textarea id="custom_css" 
                          name="rmcu_advanced_settings[custom_css]" 
                          rows="5" 
                          class="large-text code"><?php echo esc_textarea($advanced_settings['custom_css'] ?? ''); ?></textarea>
                <p class="description"><?php _e('Add custom CSS for capture widgets', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="custom_js"><?php _e('Custom JavaScript', 'rmcu'); ?></label>
            </th>
            <td>
                <textarea id="custom_js" 
                          name="rmcu_advanced_settings[custom_js]" 
                          rows="5" 
                          class="large-text code"><?php echo esc_textarea($advanced_settings['custom_js'] ?? ''); ?></textarea>
                <p class="description"><?php _e('Add custom JavaScript for capture widgets', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="uninstall_cleanup"><?php _e('Clean on Uninstall', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="uninstall_cleanup" 
                       name="rmcu_advanced_settings[uninstall_cleanup]" 
                       value="1" 
                       <?php checked(isset($advanced_settings['uninstall_cleanup']) ? $advanced_settings['uninstall_cleanup'] : 0); ?>>
                <p class="description"><?php _e('Remove all plugin data when uninstalling (⚠️ This cannot be undone)', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label><?php _e('Database Maintenance', 'rmcu'); ?></label>
            </th>
            <td>
                <button type="button" class="button" id="optimize-tables"><?php _e('Optimize Tables', 'rmcu'); ?></button>
                <button type="button" class="button" id="cleanup-orphans"><?php _e('Clean Orphaned Data', 'rmcu'); ?></button>
                <button type="button" class="button button-link-delete" id="reset-plugin"><?php _e('Reset Plugin', 'rmcu'); ?></button>
                <p class="description"><?php _e('Database maintenance operations', 'rmcu'); ?></p>
            </td>
        </tr>
    </table>
</div>

<script>
jQuery(document).ready(function($) {
    // Test n8n connection
    $('#test-n8n-connection').on('click', function() {
        const $btn = $(this);
        const url = $('#n8n_webhook_url').val();
        
        if (!url) {
            alert('<?php _e('Please enter a webhook URL', 'rmcu'); ?>');
            return;
        }
        
        $btn.prop('disabled', true).text('<?php _e('Testing...', 'rmcu'); ?>');
        
        $.post(ajaxurl, {
            action: 'rmcu_test_n8n',
            url: url,
            nonce: '<?php echo wp_create_nonce('rmcu_admin'); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Connection successful!', 'rmcu'); ?>');
            } else {
                alert('<?php _e('Connection failed: ', 'rmcu'); ?>' + response.data);
            }
            $btn.prop('disabled', false).text('<?php _e('Test Connection', 'rmcu'); ?>');
        });
    });
    
    // Clear cache
    $('#clear-cache').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to clear the cache?', 'rmcu'); ?>')) {
            $.post(ajaxurl, {
                action: 'rmcu_clear_cache',
                nonce: '<?php echo wp_create_nonce('rmcu_admin'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Cache cleared successfully!', 'rmcu'); ?>');
                }
            });
        }
    });
    
    // Reset plugin
    $('#reset-plugin').on('click', function() {
        if (confirm('<?php _e('⚠️ This will reset all plugin settings to defaults. Are you sure?', 'rmcu'); ?>')) {
            if (confirm('<?php _e('This action cannot be undone. Type "RESET" to confirm.', 'rmcu'); ?>')) {
                // Additional confirmation logic here
            }
        }
    });
});
</script>