<?php
/**
 * RMCU Admin Dashboard View
 * 
 * @package RMCU
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap rmcu-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Welcome Panel -->
    <div class="rmcu-welcome-panel">
        <div class="rmcu-welcome-panel-content">
            <h2><?php _e('Welcome to RankMath Content & Media Capture!', 'rmcu'); ?></h2>
            <p class="about-description"><?php _e('Capture, analyze, and optimize your content with powerful media tools.', 'rmcu'); ?></p>
            
            <div class="rmcu-welcome-panel-column-container">
                <div class="rmcu-welcome-panel-column">
                    <h3><?php _e('Get Started', 'rmcu'); ?></h3>
                    <a class="button button-primary button-hero" href="<?php echo admin_url('admin.php?page=rmcu-captures'); ?>">
                        <?php _e('Start Capturing', 'rmcu'); ?>
                    </a>
                </div>
                
                <div class="rmcu-welcome-panel-column">
                    <h3><?php _e('Quick Links', 'rmcu'); ?></h3>
                    <ul>
                        <li><a href="<?php echo admin_url('admin.php?page=rmcu-settings'); ?>"><?php _e('Configure Settings', 'rmcu'); ?></a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=rmcu-analysis'); ?>"><?php _e('Content Analysis', 'rmcu'); ?></a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=rmcu-help'); ?>"><?php _e('Documentation', 'rmcu'); ?></a></li>
                    </ul>
                </div>
                
                <div class="rmcu-welcome-panel-column rmcu-welcome-panel-last">
                    <h3><?php _e('System Status', 'rmcu'); ?></h3>
                    <ul class="rmcu-system-status-list">
                        <li>
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('Plugin Active', 'rmcu'); ?>
                        </li>
                        <li>
                            <?php if ($system_status['rankmath_active']): ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('RankMath Connected', 'rmcu'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('RankMath Not Active', 'rmcu'); ?>
                            <?php endif; ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-database"></span>
                            <?php printf(__('Database: %s', 'rmcu'), $system_status['database_size']); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="rmcu-stats-cards">
        <div class="rmcu-stat-card">
            <div class="rmcu-stat-number"><?php echo number_format($stats['total_captures']); ?></div>
            <div class="rmcu-stat-label"><?php _e('Total Captures', 'rmcu'); ?></div>
            <div class="rmcu-stat-change <?php echo $stats['weekly_trend'] > 0 ? 'positive' : 'negative'; ?>">
                <?php echo $stats['weekly_trend'] > 0 ? '↑' : '↓'; ?>
                <?php echo abs($stats['weekly_trend']); ?>%
            </div>
        </div>
        
        <div class="rmcu-stat-card">
            <div class="rmcu-stat-number"><?php echo $stats['total_size']; ?></div>
            <div class="rmcu-stat-label"><?php _e('Storage Used', 'rmcu'); ?></div>
            <div class="rmcu-stat-sublabel"><?php echo $system_status['max_upload_size']; ?> <?php _e('max', 'rmcu'); ?></div>
        </div>
        
        <div class="rmcu-stat-card">
            <div class="rmcu-stat-number"><?php echo number_format($stats['today_captures']); ?></div>
            <div class="rmcu-stat-label"><?php _e('Today\'s Captures', 'rmcu'); ?></div>
            <div class="rmcu-stat-sublabel"><?php echo date_i18n(get_option('date_format')); ?></div>
        </div>
        
        <div class="rmcu-stat-card">
            <div class="rmcu-stat-number"><?php echo round($stats['seo_scores']['average']); ?>/100</div>
            <div class="rmcu-stat-label"><?php _e('Avg SEO Score', 'rmcu'); ?></div>
            <div class="rmcu-progress">
                <div class="rmcu-progress-bar" style="width: <?php echo $stats['seo_scores']['average']; ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="rmcu-dashboard-content">
        <div class="rmcu-dashboard-main">
            
            <!-- Activity Chart -->
            <div class="rmcu-panel">
                <div class="rmcu-panel-header">
                    <h2><?php _e('Capture Activity', 'rmcu'); ?></h2>
                    <div class="rmcu-panel-actions">
                        <select id="rmcu-chart-period" class="rmcu-select">
                            <option value="7"><?php _e('Last 7 Days', 'rmcu'); ?></option>
                            <option value="30"><?php _e('Last 30 Days', 'rmcu'); ?></option>
                            <option value="90"><?php _e('Last 3 Months', 'rmcu'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="rmcu-panel-body">
                    <canvas id="rmcu-activity-chart" height="100"></canvas>
                </div>
            </div>

            <!-- Recent Captures -->
            <div class="rmcu-panel">
                <div class="rmcu-panel-header">
                    <h2><?php _e('Recent Captures', 'rmcu'); ?></h2>
                    <a href="<?php echo admin_url('admin.php?page=rmcu-captures'); ?>" class="rmcu-view-all">
                        <?php _e('View All', 'rmcu'); ?> →
                    </a>
                </div>
                <div class="rmcu-panel-body">
                    <?php if (!empty($recent_captures)): ?>
                        <table class="rmcu-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Type', 'rmcu'); ?></th>
                                    <th><?php _e('Title', 'rmcu'); ?></th>
                                    <th><?php _e('Date', 'rmcu'); ?></th>
                                    <th><?php _e('Size', 'rmcu'); ?></th>
                                    <th><?php _e('Actions', 'rmcu'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_captures as $capture): ?>
                                    <tr>
                                        <td>
                                            <span class="rmcu-type-badge rmcu-type-<?php echo esc_attr($capture->type); ?>">
                                                <?php echo esc_html($capture->type); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($capture->title); ?></strong>
                                            <?php if ($capture->post_id): ?>
                                                <br>
                                                <small>
                                                    <?php _e('Post:', 'rmcu'); ?> 
                                                    <a href="<?php echo get_edit_post_link($capture->post_id); ?>">
                                                        <?php echo get_the_title($capture->post_id); ?>
                                                    </a>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo human_time_diff(strtotime($capture->created_at), current_time('timestamp')); ?> <?php _e('ago', 'rmcu'); ?></td>
                                        <td><?php echo size_format($capture->file_size); ?></td>
                                        <td>
                                            <div class="rmcu-actions">
                                                <a href="#" class="rmcu-action-view" data-id="<?php echo $capture->id; ?>" title="<?php _e('View', 'rmcu'); ?>">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </a>
                                                <a href="<?php echo $capture->file_url; ?>" class="rmcu-action-download" download title="<?php _e('Download', 'rmcu'); ?>">
                                                    <span class="dashicons dashicons-download"></span>
                                                </a>
                                                <a href="#" class="rmcu-action-delete" data-id="<?php echo $capture->id; ?>" title="<?php _e('Delete', 'rmcu'); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="rmcu-no-items"><?php _e('No captures yet. Start capturing content!', 'rmcu'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Sidebar -->
        <div class="rmcu-dashboard-sidebar">
            
            <!-- Content Types Distribution -->
            <div class="rmcu-panel">
                <div class="rmcu-panel-header">
                    <h3><?php _e('Content Types', 'rmcu'); ?></h3>
                </div>
                <div class="rmcu-panel-body">
                    <canvas id="rmcu-content-types-chart" height="200"></canvas>
                    <div class="rmcu-legend">
                        <?php foreach ($stats['top_content_types'] as $type => $count): ?>
                            <div class="rmcu-legend-item">
                                <span class="rmcu-legend-color" style="background: <?php echo $this->get_type_color($type); ?>"></span>
                                <span class="rmcu-legend-label"><?php echo ucfirst($type); ?></span>
                                <span class="rmcu-legend-value"><?php echo $count; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="rmcu-panel">
                <div class="rmcu-panel-header">
                    <h3><?php _e('Quick Actions', 'rmcu'); ?></h3>
                </div>
                <div class="rmcu-panel-body">
                    <div class="rmcu-quick-actions">
                        <button class="button button-primary rmcu-full-width" id="rmcu-quick-capture">
                            <span class="dashicons dashicons-camera"></span>
                            <?php _e('Quick Capture', 'rmcu'); ?>
                        </button>
                        
                        <button class="button rmcu-full-width" id="rmcu-analyze-current">
                            <span class="dashicons dashicons-analytics"></span>
                            <?php _e('Analyze Current Page', 'rmcu'); ?>
                        </button>
                        
                        <button class="button rmcu-full-width" id="rmcu-export-data">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Export Data', 'rmcu'); ?>
                        </button>
                        
                        <button class="button rmcu-full-width" id="rmcu-clear-cache">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Clear Cache', 'rmcu'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="rmcu-panel">
                <div class="rmcu-panel-header">
                    <h3><?php _e('System Information', 'rmcu'); ?></h3>
                </div>
                <div class="rmcu-panel-body">
                    <dl class="rmcu-system-info">
                        <dt><?php _e('PHP Version:', 'rmcu'); ?></dt>
                        <dd><?php echo $system_status['php_version']; ?></dd>
                        
                        <dt><?php _e('WordPress:', 'rmcu'); ?></dt>
                        <dd><?php echo $system_status['wordpress_version']; ?></dd>
                        
                        <dt><?php _e('Plugin Version:', 'rmcu'); ?></dt>
                        <dd><?php echo $system_status['plugin_version']; ?></dd>
                        
                        <dt><?php _e('Memory Limit:', 'rmcu'); ?></dt>
                        <dd><?php echo $system_status['memory_limit']; ?></dd>
                        
                        <dt><?php _e('Upload Limit:', 'rmcu'); ?></dt>
                        <dd><?php echo $system_status['max_upload_size']; ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Support -->
            <div class="rmcu-panel">
                <div class="rmcu-panel-header">
                    <h3><?php _e('Need Help?', 'rmcu'); ?></h3>
                </div>
                <div class="rmcu-panel-body">
                    <p><?php _e('Check our documentation or contact support.', 'rmcu'); ?></p>
                    <div class="rmcu-support-links">
                        <a href="<?php echo admin_url('admin.php?page=rmcu-help'); ?>" class="button">
                            <?php _e('Documentation', 'rmcu'); ?>
                        </a>
                        <a href="mailto:support@example.com" class="button">
                            <?php _e('Contact Support', 'rmcu'); ?>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Initialize dashboard charts and features
jQuery(document).ready(function($) {
    // Activity Chart
    if (document.getElementById('rmcu-activity-chart')) {
        const ctx = document.getElementById('rmcu-activity-chart').getContext('2d');
        const activityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($this->get_chart_labels()); ?>,
                datasets: [{
                    label: '<?php _e('Captures', 'rmcu'); ?>',
                    data: <?php echo json_encode($this->get_chart_data()); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Content Types Chart  
    if (document.getElementById('rmcu-content-types-chart')) {
        const ctx2 = document.getElementById('rmcu-content-types-chart').getContext('2d');
        const typesChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($stats['top_content_types'])); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($stats['top_content_types'])); ?>,
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
});
</script>

<?php
// Helper methods for the view
function get_type_color($type) {
    $colors = [
        'video' => '#FF6384',
        'audio' => '#36A2EB',
        'screenshot' => '#FFCE56',
        'screen' => '#4BC0C0',
        'document' => '#9966FF'
    ];
    return isset($colors[$type]) ? $colors[$type] : '#999999';
}

function get_chart_labels() {
    $labels = [];
    for ($i = 6; $i >= 0; $i--) {
        $labels[] = date_i18n('M j', strtotime("-$i days"));
    }
    return $labels;
}

function get_chart_data() {
    // This would typically fetch from database
    return [12, 19, 3, 5, 2, 8, 15];
}
?>