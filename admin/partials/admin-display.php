<?php
/**
 * Main admin display template
 * 
 * @package RMCU
 * @subpackage Admin/Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
?>

<div class="wrap rmcu-admin-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-video-alt2"></span>
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>
    
    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Settings saved successfully!', 'rmcu'); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="rmcu-admin-container">
        <nav class="nav-tab-wrapper rmcu-tabs">
            <a href="?page=rmcu&tab=dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Dashboard', 'rmcu'); ?>
            </a>
            <a href="?page=rmcu&tab=captures" class="nav-tab <?php echo $active_tab === 'captures' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Captures', 'rmcu'); ?>
            </a>
            <a href="?page=rmcu&tab=queue" class="nav-tab <?php echo $active_tab === 'queue' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Queue', 'rmcu'); ?>
            </a>
            <a href="?page=rmcu&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Settings', 'rmcu'); ?>
            </a>
        </nav>
        
        <div class="rmcu-tab-content">
            <?php
            switch ($active_tab) {
                case 'dashboard':
                    include RMCU_PLUGIN_DIR . 'admin/views/dashboard.php';
                    break;
                case 'captures':
                    include RMCU_PLUGIN_DIR . 'admin/views/captures-list.php';
                    break;
                case 'queue':
                    include RMCU_PLUGIN_DIR . 'admin/partials/queue-manager-display.php';
                    break;
                case 'settings':
                    include RMCU_PLUGIN_DIR . 'admin/views/settings.php';
                    break;
                default:
                    do_action('rmcu_admin_tab_' . $active_tab);
                    break;
            }
            ?>
        </div>
    </div>
</div>