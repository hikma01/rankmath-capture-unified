<?php
/**
 * Captures List View
 * 
 * @package RMCU
 * @subpackage Admin/Views
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get captures from database
global $wpdb;
$table_name = $wpdb->prefix . 'rmcu_captures';

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Filters
$filter_type = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
$filter_status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : 0;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Build query
$where_clauses = [];
$where_values = [];

if ($filter_type) {
    $where_clauses[] = 'capture_type = %s';
    $where_values[] = $filter_type;
}

if ($filter_status) {
    $where_clauses[] = 'status = %s';
    $where_values[] = $filter_status;
}

if ($filter_user) {
    $where_clauses[] = 'user_id = %d';
    $where_values[] = $filter_user;
}

if ($search) {
    $where_clauses[] = '(title LIKE %s OR url LIKE %s OR metadata LIKE %s)';
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    $where_values[] = $search_like;
    $where_values[] = $search_like;
    $where_values[] = $search_like;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$total_query = "SELECT COUNT(*) FROM $table_name $where_sql";
$total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_values));

// Get captures
$query = "SELECT * FROM $table_name $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
$query_values = array_merge($where_values, [$per_page, $offset]);
$captures = $wpdb->get_results($wpdb->prepare($query, $query_values));

// Calculate pagination
$total_pages = ceil($total_items / $per_page);
?>

<div class="wrap rmcu-captures-list">
    <h1 class="wp-heading-inline">
        <?php _e('Captures', 'rmcu'); ?>
        <span class="count">(<?php echo number_format_i18n($total_items); ?>)</span>
    </h1>
    
    <a href="#" class="page-title-action" id="add-new-capture">
        <?php _e('Add New', 'rmcu'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" class="captures-filter-form">
                <input type="hidden" name="page" value="rmcu-captures">
                
                <select name="type" id="filter-type">
                    <option value=""><?php _e('All Types', 'rmcu'); ?></option>
                    <option value="video" <?php selected($filter_type, 'video'); ?>><?php _e('Video', 'rmcu'); ?></option>
                    <option value="audio" <?php selected($filter_type, 'audio'); ?>><?php _e('Audio', 'rmcu'); ?></option>
                    <option value="screen" <?php selected($filter_type, 'screen'); ?>><?php _e('Screen', 'rmcu'); ?></option>
                    <option value="screenshot" <?php selected($filter_type, 'screenshot'); ?>><?php _e('Screenshot', 'rmcu'); ?></option>
                </select>
                
                <select name="status" id="filter-status">
                    <option value=""><?php _e('All Status', 'rmcu'); ?></option>
                    <option value="active" <?php selected($filter_status, 'active'); ?>><?php _e('Active', 'rmcu'); ?></option>
                    <option value="processing" <?php selected($filter_status, 'processing'); ?>><?php _e('Processing', 'rmcu'); ?></option>
                    <option value="failed" <?php selected($filter_status, 'failed'); ?>><?php _e('Failed', 'rmcu'); ?></option>
                    <option value="archived" <?php selected($filter_status, 'archived'); ?>><?php _e('Archived', 'rmcu'); ?></option>
                </select>
                
                <?php
                // User dropdown
                wp_dropdown_users([
                    'name' => 'user',
                    'selected' => $filter_user,
                    'show_option_all' => __('All Users', 'rmcu'),
                    'show' => 'display_name',
                    'echo' => true
                ]);
                ?>
                
                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'rmcu'); ?>">
            </form>
        </div>
        
        <div class="alignleft actions">
            <select id="bulk-action">
                <option value=""><?php _e('Bulk Actions', 'rmcu'); ?></option>
                <option value="archive"><?php _e('Archive', 'rmcu'); ?></option>
                <option value="unarchive"><?php _e('Unarchive', 'rmcu'); ?></option>
                <option value="send-n8n"><?php _e('Send to n8n', 'rmcu'); ?></option>
                <option value="delete"><?php _e('Delete', 'rmcu'); ?></option>
            </select>
            <button class="button" id="apply-bulk-action"><?php _e('Apply', 'rmcu'); ?></button>
        </div>
        
        <div class="tablenav-pages">
            <?php
            $pagination_args = [
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $current_page
            ];
            
            echo paginate_links($pagination_args);
            ?>
        </div>
        
        <br class="clear">
    </div>
    
    <!-- Search Box -->
    <p class="search-box">
        <form method="get">
            <input type="hidden" name="page" value="rmcu-captures">
            <label class="screen-reader-text" for="capture-search"><?php _e('Search Captures', 'rmcu'); ?></label>
            <input type="search" id="capture-search" name="s" value="<?php echo esc_attr($search); ?>">
            <input type="submit" class="button" value="<?php esc_attr_e('Search Captures', 'rmcu'); ?>">
        </form>
    </p>
    
    <!-- Captures Table -->
    <table class="wp-list-table widefat fixed striped captures">
        <thead>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" id="cb-select-all">
                </td>
                <th class="manage-column column-thumbnail"><?php _e('Thumbnail', 'rmcu'); ?></th>
                <th class="manage-column column-title"><?php _e('Title', 'rmcu'); ?></th>
                <th class="manage-column column-type"><?php _e('Type', 'rmcu'); ?></th>
                <th class="manage-column column-post"><?php _e('Post', 'rmcu'); ?></th>
                <th class="manage-column column-user"><?php _e('User', 'rmcu'); ?></th>
                <th class="manage-column column-status"><?php _e('Status', 'rmcu'); ?></th>
                <th class="manage-column column-duration"><?php _e('Duration', 'rmcu'); ?></th>
                <th class="manage-column column-size"><?php _e('Size', 'rmcu'); ?></th>
                <th class="manage-column column-date"><?php _e('Date', 'rmcu'); ?></th>
                <th class="manage-column column-actions"><?php _e('Actions', 'rmcu'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($captures): ?>
                <?php foreach ($captures as $capture): ?>
                    <?php
                    $metadata = json_decode($capture->metadata, true);
                    $user = get_userdata($capture->user_id);
                    $post = $capture->post_id ? get_post($capture->post_id) : null;
                    ?>
                    <tr data-capture-id="<?php echo esc_attr($capture->id); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="captures[]" value="<?php echo esc_attr($capture->id); ?>">
                        </th>
                        <td class="column-thumbnail">
                            <?php if (!empty($capture->thumbnail_url)): ?>
                                <img src="<?php echo esc_url($capture->thumbnail_url); ?>" 
                                     alt="<?php echo esc_attr($capture->title); ?>"
                                     style="width: 60px; height: auto;">
                            <?php else: ?>
                                <div class="no-thumb">
                                    <span class="dashicons dashicons-format-<?php echo esc_attr($capture->capture_type); ?>"></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="column-title">
                            <strong>
                                <a href="#" class="view-capture" data-id="<?php echo esc_attr($capture->id); ?>">
                                    <?php echo esc_html($capture->title ?: __('Untitled Capture', 'rmcu')); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="#" class="view-capture" data-id="<?php echo esc_attr($capture->id); ?>">
                                        <?php _e('View', 'rmcu'); ?>
                                    </a> |
                                </span>
                                <span class="edit">
                                    <a href="#" class="edit-capture" data-id="<?php echo esc_attr($capture->id); ?>">
                                        <?php _e('Edit', 'rmcu'); ?>
                                    </a> |
                                </span>
                                <span class="download">
                                    <a href="<?php echo esc_url($capture->file_url); ?>" download>
                                        <?php _e('Download', 'rmcu'); ?>
                                    </a> |
                                </span>
                                <span class="trash">
                                    <a href="#" class="delete-capture" data-id="<?php echo esc_attr($capture->id); ?>">
                                        <?php _e('Delete', 'rmcu'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="column-type">
                            <span class="capture-type-badge type-<?php echo esc_attr($capture->capture_type); ?>">
                                <?php echo esc_html(ucfirst($capture->capture_type)); ?>
                            </span>
                        </td>
                        <td class="column-post">
                            <?php if ($post): ?>
                                <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                            <?php else: ?>
                                <span class="no-post">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="column-user">
                            <?php if ($user): ?>
                                <a href="<?php echo get_edit_user_link($user->ID); ?>">
                                    <?php echo get_avatar($user->ID, 24); ?>
                                    <?php echo esc_html($user->display_name); ?>
                                </a>
                            <?php else: ?>
                                <span><?php _e('Unknown', 'rmcu'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-status">
                            <span class="status-badge status-<?php echo esc_attr($capture->status); ?>">
                                <?php echo esc_html(ucfirst($capture->status)); ?>
                            </span>
                        </td>
                        <td class="column-duration">
                            <?php
                            if (!empty($metadata['duration'])) {
                                echo esc_html(gmdate('i:s', $metadata['duration']));
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td class="column-size">
                            <?php
                            if (!empty($metadata['file_size'])) {
                                echo size_format($metadata['file_size']);
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td class="column-date">
                            <?php echo human_time_diff(strtotime($capture->created_at)); ?> ago
                            <br>
                            <small><?php echo esc_html($capture->created_at); ?></small>
                        </td>
                        <td class="column-actions">
                            <button class="button button-small send-n8n" 
                                    data-id="<?php echo esc_attr($capture->id); ?>"
                                    title="<?php esc_attr_e('Send to n8n', 'rmcu'); ?>">
                                <span class="dashicons dashicons-external"></span>
                            </button>
                            <button class="button button-small copy-url" 
                                    data-url="<?php echo esc_url($capture->file_url); ?>"
                                    title="<?php esc_attr_e('Copy URL', 'rmcu'); ?>">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11">
                        <div class="no-items">
                            <span class="dashicons dashicons-video-alt2"></span>
                            <p><?php _e('No captures found', 'rmcu'); ?></p>
                            <a href="#" class="button button-primary" id="create-first-capture">
                                <?php _e('Create Your First Capture', 'rmcu'); ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox">
                </td>
                <th class="manage-column column-thumbnail"><?php _e('Thumbnail', 'rmcu'); ?></th>
                <th class="manage-column column-title"><?php _e('Title', 'rmcu'); ?></th>
                <th class="manage-column column-type"><?php _e('Type', 'rmcu'); ?></th>
                <th class="manage-column column-post"><?php _e('Post', 'rmcu'); ?></th>
                <th class="manage-column column-user"><?php _e('User', 'rmcu'); ?></th>
                <th class="manage-column column-status"><?php _e('Status', 'rmcu'); ?></th>
                <th class="manage-column column-duration"><?php _e('Duration', 'rmcu'); ?></th>
                <th class="manage-column column-size"><?php _e('Size', 'rmcu'); ?></th>
                <th class="manage-column column-date"><?php _e('Date', 'rmcu'); ?></th>
                <th class="manage-column column-actions"><?php _e('Actions', 'rmcu'); ?></th>
            </tr>
        </tfoot>
    </table>
    
    <!-- Bottom Pagination -->
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php echo paginate_links($pagination_args); ?>
        </div>
        <br class="clear">
    </div>
</div>

<!-- View Capture Modal -->
<div id="capture-modal" class="rmcu-modal" style="display: none;">
    <div class="rmcu-modal-content">
        <span class="close">&times;</span>
        <div class="modal-body">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<style>
.rmcu-captures-list .no-thumb {
    width: 60px;
    height: 60px;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.capture-type-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.capture-type-badge.type-video { background: #e3f2fd; color: #1976d2; }
.capture-type-badge.type-audio { background: #f3e5f5; color: #7b1fa2; }
.capture-type-badge.type-screen { background: #e8f5e9; color: #388e3c; }
.capture-type-badge.type-screenshot { background: #fff3e0; color: #f57c00; }

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
}

.status-badge.status-active { background: #d4edda; color: #155724; }
.status-badge.status-processing { background: #fff3cd; color: #856404; }
.status-badge.status-failed { background: #f8d7da; color: #721c24; }
.status-badge.status-archived { background: #e2e3e5; color: #383d41; }

.no-items {
    text-align: center;
    padding: 40px;
}

.no-items .dashicons {
    font-size: 48px;
    color: #ccc;
}

.column-thumbnail img {
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.column-user a {
    display: flex;
    align-items: center;
    gap: 5px;
}

.column-user img {
    border-radius: 50%;
}

.rmcu-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
}

.rmcu-modal-content {
    background: #fff;
    margin: 5% auto;
    padding: 20px;
    width: 80%;
    max-width: 900px;
    border-radius: 8px;
    position: relative;
}

.rmcu-modal .close {
    position: absolute;
    right: 20px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Bulk actions
    $('#apply-bulk-action').on('click', function() {
        const action = $('#bulk-action').val();
        const selected = $('input[name="captures[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (!action) {
            alert('<?php _e('Please select an action', 'rmcu'); ?>');
            return;
        }
        
        if (selected.length === 0) {
            alert('<?php _e('Please select at least one capture', 'rmcu'); ?>');
            return;
        }
        
        if (action === 'delete' && !confirm('<?php _e('Are you sure you want to delete selected captures?', 'rmcu'); ?>')) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'rmcu_bulk_captures',
            bulk_action: action,
            captures: selected,
            nonce: '<?php echo wp_create_nonce('rmcu_captures'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            }
        });
    });
    
    // View capture
    $('.view-capture').on('click', function(e) {
        e.preventDefault();
        const captureId = $(this).data('id');
        
        $.get(ajaxurl, {
            action: 'rmcu_get_capture',
            id: captureId,
            nonce: '<?php echo wp_create_nonce('rmcu_captures'); ?>'
        }, function(response) {
            if (response.success) {
                $('#capture-modal .modal-body').html(response.data);
                $('#capture-modal').show();
            }
        });
    });
    
    // Close modal
    $('.close, #capture-modal').on('click', function(e) {
        if (e.target === this) {
            $('#capture-modal').hide();
        }
    });
    
    // Send to n8n
    $('.send-n8n').on('click', function() {
        const $btn = $(this);
        const captureId = $btn.data('id');
        
        $btn.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'rmcu_send_to_n8n',
            id: captureId,
            nonce: '<?php echo wp_create_nonce('rmcu_captures'); ?>'
        }, function(response) {
            if (response.success) {
                alert('<?php _e('Sent to n8n successfully!', 'rmcu'); ?>');
            } else {
                alert('<?php _e('Failed to send to n8n', 'rmcu'); ?>');
            }
            $btn.prop('disabled', false);
        });
    });
    
    // Copy URL
    $('.copy-url').on('click', function() {
        const url = $(this).data('url');
        const temp = $('<input>');
        $('body').append(temp);
        temp.val(url).select();
        document.execCommand('copy');
        temp.remove();
        
        $(this).text('<?php _e('Copied!', 'rmcu'); ?>');
        setTimeout(() => {
            $(this).html('<span class="dashicons dashicons-clipboard"></span>');
        }, 2000);
    });
    
    // Select all checkboxes
    $('#cb-select-all').on('change', function() {
        $('input[name="captures[]"]').prop('checked', $(this).is(':checked'));
    });
});
</script>