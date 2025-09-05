<?php
/**
 * Logs Viewer View
 * 
 * @package RMCU
 * @subpackage Admin/Views
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get log file path
$log_file = WP_CONTENT_DIR . '/rmcu-logs/rmcu.log';
$log_exists = file_exists($log_file);
$logs = '';
$total_lines = 0;

if ($log_exists) {
    $logs = file_get_contents($log_file);
    $total_lines = substr_count($logs, "\n");
}

// Filter parameters
$filter_level = isset($_GET['level']) ? sanitize_key($_GET['level']) : 'all';
$filter_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
$lines_to_show = isset($_GET['lines']) ? intval($_GET['lines']) : 100;
?>

<div class="rmcu-logs-viewer">
    <div class="logs-header">
        <h2><?php _e('System Logs', 'rmcu'); ?></h2>
        
        <div class="logs-info">
            <?php if ($log_exists): ?>
                <span><?php printf(__('Log file: %s', 'rmcu'), '<code>' . $log_file . '</code>'); ?></span>
                <span><?php printf(__('Size: %s', 'rmcu'), size_format(filesize($log_file))); ?></span>
                <span><?php printf(__('Lines: %d', 'rmcu'), $total_lines); ?></span>
            <?php else: ?>
                <span class="no-logs"><?php _e('No log file found', 'rmcu'); ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="logs-controls">
        <form method="get" class="logs-filter-form">
            <input type="hidden" name="page" value="rmcu-logs">
            
            <select name="level" id="log-level-filter">
                <option value="all" <?php selected($filter_level, 'all'); ?>><?php _e('All Levels', 'rmcu'); ?></option>
                <option value="debug" <?php selected($filter_level, 'debug'); ?>><?php _e('Debug', 'rmcu'); ?></option>
                <option value="info" <?php selected($filter_level, 'info'); ?>><?php _e('Info', 'rmcu'); ?></option>
                <option value="warning" <?php selected($filter_level, 'warning'); ?>><?php _e('Warning', 'rmcu'); ?></option>
                <option value="error" <?php selected($filter_level, 'error'); ?>><?php _e('Error', 'rmcu'); ?></option>
                <option value="critical" <?php selected($filter_level, 'critical'); ?>><?php _e('Critical', 'rmcu'); ?></option>
            </select>
            
            <input type="date" 
                   name="date" 
                   id="log-date-filter" 
                   value="<?php echo esc_attr($filter_date); ?>"
                   placeholder="<?php esc_attr_e('Filter by date', 'rmcu'); ?>">
            
            <select name="lines" id="lines-to-show">
                <option value="50" <?php selected($lines_to_show, 50); ?>><?php _e('Last 50 lines', 'rmcu'); ?></option>
                <option value="100" <?php selected($lines_to_show, 100); ?>><?php _e('Last 100 lines', 'rmcu'); ?></option>
                <option value="250" <?php selected($lines_to_show, 250); ?>><?php _e('Last 250 lines', 'rmcu'); ?></option>
                <option value="500" <?php selected($lines_to_show, 500); ?>><?php _e('Last 500 lines', 'rmcu'); ?></option>
                <option value="1000" <?php selected($lines_to_show, 1000); ?>><?php _e('Last 1000 lines', 'rmcu'); ?></option>
            </select>
            
            <button type="submit" class="button"><?php _e('Filter', 'rmcu'); ?></button>
            <button type="button" class="button" id="refresh-logs"><?php _e('Refresh', 'rmcu'); ?></button>
            <button type="button" class="button" id="download-logs"><?php _e('Download', 'rmcu'); ?></button>
            <button type="button" class="button button-link-delete" id="clear-logs"><?php _e('Clear Logs', 'rmcu'); ?></button>
        </form>
        
        <div class="logs-search">
            <input type="text" 
                   id="logs-search-input" 
                   placeholder="<?php esc_attr_e('Search in logs...', 'rmcu'); ?>">
            <span class="search-results"></span>
        </div>
    </div>
    
    <div class="logs-content-wrapper">
        <?php if ($log_exists && !empty($logs)): ?>
            <div class="logs-toolbar">
                <label>
                    <input type="checkbox" id="auto-scroll">
                    <?php _e('Auto-scroll', 'rmcu'); ?>
                </label>
                <label>
                    <input type="checkbox" id="word-wrap" checked>
                    <?php _e('Word wrap', 'rmcu'); ?>
                </label>
                <label>
                    <input type="checkbox" id="show-timestamps" checked>
                    <?php _e('Show timestamps', 'rmcu'); ?>
                </label>
            </div>
            
            <div class="logs-content" id="logs-content">
                <pre><?php 
                    // Process and display logs
                    $lines = explode("\n", $logs);
                    $lines = array_slice($lines, -$lines_to_show);
                    
                    foreach ($lines as $line) {
                        if (empty($line)) continue;
                        
                        // Parse log level
                        $class = '';
                        if (strpos($line, '[ERROR]') !== false) {
                            $class = 'log-error';
                        } elseif (strpos($line, '[WARNING]') !== false) {
                            $class = 'log-warning';
                        } elseif (strpos($line, '[INFO]') !== false) {
                            $class = 'log-info';
                        } elseif (strpos($line, '[DEBUG]') !== false) {
                            $class = 'log-debug';
                        } elseif (strpos($line, '[CRITICAL]') !== false) {
                            $class = 'log-critical';
                        }
                        
                        // Apply filters
                        if ($filter_level !== 'all') {
                            $level_upper = strtoupper($filter_level);
                            if (strpos($line, "[$level_upper]") === false) {
                                continue;
                            }
                        }
                        
                        if (!empty($filter_date)) {
                            if (strpos($line, $filter_date) === false) {
                                continue;
                            }
                        }
                        
                        echo '<span class="log-line ' . $class . '">' . esc_html($line) . '</span>' . "\n";
                    }
                ?></pre>
            </div>
        <?php else: ?>
            <div class="no-logs-message">
                <span class="dashicons dashicons-info"></span>
                <p><?php _e('No logs available. Logs will appear here once the plugin starts recording events.', 'rmcu'); ?></p>
                <p><?php _e('Make sure debug mode is enabled in Advanced Settings.', 'rmcu'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="logs-legend">
        <h4><?php _e('Legend:', 'rmcu'); ?></h4>
        <span class="log-level-badge debug">DEBUG</span>
        <span class="log-level-badge info">INFO</span>
        <span class="log-level-badge warning">WARNING</span>
        <span class="log-level-badge error">ERROR</span>
        <span class="log-level-badge critical">CRITICAL</span>
    </div>
</div>

<style>
.rmcu-logs-viewer {
    background: #fff;
    padding: 20px;
    margin-top: 20px;
}

.logs-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
}

.logs-info span {
    margin-right: 20px;
    color: #666;
}

.logs-controls {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.logs-filter-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.logs-content-wrapper {
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
    position: relative;
}

.logs-toolbar {
    padding: 10px;
    background: #fff;
    border-bottom: 1px solid #ddd;
}

.logs-toolbar label {
    margin-right: 20px;
}

.logs-content {
    max-height: 600px;
    overflow: auto;
    padding: 15px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.6;
}

.logs-content pre {
    margin: 0;
    white-space: pre-wrap;
}

.log-line {
    display: block;
    padding: 2px 0;
}

.log-line.log-error {
    color: #dc3545;
    background: rgba(220, 53, 69, 0.05);
}

.log-line.log-warning {
    color: #ffc107;
    background: rgba(255, 193, 7, 0.05);
}

.log-line.log-info {
    color: #17a2b8;
}

.log-line.log-debug {
    color: #6c757d;
    opacity: 0.8;
}

.log-line.log-critical {
    color: #fff;
    background: #dc3545;
    font-weight: bold;
}

.logs-legend {
    margin-top: 15px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.log-level-badge {
    display: inline-block;
    padding: 2px 8px;
    margin-right: 10px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
}

.log-level-badge.debug {
    background: #6c757d;
    color: #fff;
}

.log-level-badge.info {
    background: #17a2b8;
    color: #fff;
}

.log-level-badge.warning {
    background: #ffc107;
    color: #000;
}

.log-level-badge.error {
    background: #dc3545;
    color: #fff;
}

.log-level-badge.critical {
    background: #721c24;
    color: #fff;
}

.no-logs-message {
    padding: 40px;
    text-align: center;
    color: #666;
}

.no-logs-message .dashicons {
    font-size: 48px;
    color: #ccc;
}

.logs-search {
    display: flex;
    align-items: center;
    gap: 10px;
}

.logs-search input {
    width: 250px;
}

.search-results {
    color: #666;
    font-size: 12px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Refresh logs
    $('#refresh-logs').on('click', function() {
        location.reload();
    });
    
    // Download logs
    $('#download-logs').on('click', function() {
        $.post(ajaxurl, {
            action: 'rmcu_download_logs',
            nonce: '<?php echo wp_create_nonce('rmcu_logs'); ?>'
        }, function(response) {
            if (response.success) {
                const blob = new Blob([response.data], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'rmcu-logs-' + new Date().toISOString().split('T')[0] + '.log';
                a.click();
                URL.revokeObjectURL(url);
            }
        });
    });
    
    // Clear logs
    $('#clear-logs').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to clear all logs? This cannot be undone.', 'rmcu'); ?>')) {
            $.post(ajaxurl, {
                action: 'rmcu_clear_logs',
                nonce: '<?php echo wp_create_nonce('rmcu_logs'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
    });
    
    // Word wrap toggle
    $('#word-wrap').on('change', function() {
        if ($(this).is(':checked')) {
            $('.logs-content pre').css('white-space', 'pre-wrap');
        } else {
            $('.logs-content pre').css('white-space', 'pre');
        }
    });
    
    // Auto-scroll
    $('#auto-scroll').on('change', function() {
        if ($(this).is(':checked')) {
            const logsContent = document.getElementById('logs-content');
            logsContent.scrollTop = logsContent.scrollHeight;
        }
    });
    
    // Search in logs
    $('#logs-search-input').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        let matchCount = 0;
        
        $('.log-line').each(function() {
            const text = $(this).text().toLowerCase();
            if (searchTerm && text.includes(searchTerm)) {
                $(this).addClass('highlighted');
                matchCount++;
            } else {
                $(this).removeClass('highlighted');
            }
        });
        
        if (searchTerm) {
            $('.search-results').text(matchCount + ' matches found');
        } else {
            $('.search-results').text('');
        }
    });
});
</script>