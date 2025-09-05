<?php
/**
 * Queue Manager Display
 * 
 * @package RMCU
 * @subpackage Admin/Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get queue items
global $wpdb;
$table_name = $wpdb->prefix . 'rmcu_queue';
$queue_items = $wpdb->get_results("
    SELECT * FROM $table_name 
    ORDER BY priority DESC, created_at ASC 
    LIMIT 50
");

$total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$pending_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
$processing_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'processing'");
$failed_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed'");
?>

<div class="rmcu-queue-manager">
    <div class="rmcu-queue-stats">
        <div class="stat-card">
            <span class="stat-number"><?php echo esc_html($total_items); ?></span>
            <span class="stat-label"><?php _e('Total Items', 'rmcu'); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-number pending"><?php echo esc_html($pending_items); ?></span>
            <span class="stat-label"><?php _e('Pending', 'rmcu'); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-number processing"><?php echo esc_html($processing_items); ?></span>
            <span class="stat-label"><?php _e('Processing', 'rmcu'); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-number failed"><?php echo esc_html($failed_items); ?></span>
            <span class="stat-label"><?php _e('Failed', 'rmcu'); ?></span>
        </div>
    </div>
    
    <div class="rmcu-queue-actions">
        <button class="button button-primary" id="process-queue">
            <span class="dashicons dashicons-controls-play"></span>
            <?php _e('Process Queue', 'rmcu'); ?>
        </button>
        <button class="button" id="pause-queue">
            <span class="dashicons dashicons-controls-pause"></span>
            <?php _e('Pause', 'rmcu'); ?>
        </button>
        <button class="button" id="clear-completed">
            <span class="dashicons dashicons-yes"></span>
            <?php _e('Clear Completed', 'rmcu'); ?>
        </button>
        <button class="button button-link-delete" id="clear-failed">
            <span class="dashicons dashicons-trash"></span>
            <?php _e('Clear Failed', 'rmcu'); ?>
        </button>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="check-column">
                    <input type="checkbox" id="cb-select-all">
                </th>
                <th scope="col" class="column-id"><?php _e('ID', 'rmcu'); ?></th>
                <th scope="col" class="column-url"><?php _e('URL', 'rmcu'); ?></th>
                <th scope="col" class="column-type"><?php _e('Type', 'rmcu'); ?></th>
                <th scope="col" class="column-status"><?php _e('Status', 'rmcu'); ?></th>
                <th scope="col" class="column-priority"><?php _e('Priority', 'rmcu'); ?></th>
                <th scope="col" class="column-attempts"><?php _e('Attempts', 'rmcu'); ?></th>
                <th scope="col" class="column-created"><?php _e('Created', 'rmcu'); ?></th>
                <th scope="col" class="column-actions"><?php _e('Actions', 'rmcu'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($queue_items): ?>
                <?php foreach ($queue_items as $item): ?>
                    <tr data-id="<?php echo esc_attr($item->id); ?>">
                        <td>
                            <input type="checkbox" name="queue_items[]" value="<?php echo esc_attr($item->id); ?>">
                        </td>
                        <td><?php echo esc_html($item->id); ?></td>
                        <td>
                            <a href="<?php echo esc_url($item->url); ?>" target="_blank">
                                <?php echo esc_html(substr($item->url, 0, 50)); ?>
                                <?php if (strlen($item->url) > 50) echo '...'; ?>
                            </a>
                        </td>
                        <td>
                            <span class="capture-type-badge type-<?php echo esc_attr($item->capture_type); ?>">
                                <?php echo esc_html($item->capture_type); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($item->status); ?>">
                                <?php echo esc_html($item->status); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($item->priority); ?></td>
                        <td><?php echo esc_html($item->attempts); ?>/3</td>
                        <td><?php echo esc_html(human_time_diff(strtotime($item->created_at))); ?> ago</td>
                        <td>
                            <button class="button button-small retry-item" data-id="<?php echo esc_attr($item->id); ?>">
                                <?php _e('Retry', 'rmcu'); ?>
                            </button>
                            <button class="button button-small button-link-delete remove-item" data-id="<?php echo esc_attr($item->id); ?>">
                                <?php _e('Remove', 'rmcu'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9">
                        <div class="no-items">
                            <span class="dashicons dashicons-inbox"></span>
                            <p><?php _e('No items in queue', 'rmcu'); ?></p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="rmcu-queue-bulk-actions">
        <select id="bulk-action">
            <option value=""><?php _e('Bulk Actions', 'rmcu'); ?></option>
            <option value="retry"><?php _e('Retry Selected', 'rmcu'); ?></option>
            <option value="priority-high"><?php _e('Set High Priority', 'rmcu'); ?></option>
            <option value="priority-normal"><?php _e('Set Normal Priority', 'rmcu'); ?></option>
            <option value="priority-low"><?php _e('Set Low Priority', 'rmcu'); ?></option>
            <option value="delete"><?php _e('Delete Selected', 'rmcu'); ?></option>
        </select>
        <button class="button" id="apply-bulk-action"><?php _e('Apply', 'rmcu'); ?></button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Process queue
    $('#process-queue').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'rmcu_process_queue',
            nonce: '<?php echo wp_create_nonce('rmcu_queue_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            }
            $btn.prop('disabled', false);
        });
    });
    
    // Retry item
    $('.retry-item').on('click', function() {
        const itemId = $(this).data('id');
        $.post(ajaxurl, {
            action: 'rmcu_retry_queue_item',
            item_id: itemId,
            nonce: '<?php echo wp_create_nonce('rmcu_queue_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            }
        });
    });
});
</script>