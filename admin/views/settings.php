<?php
/**
 * RMCU Admin Settings View
 * 
 * @package RMCU
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap rmcu-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('rmcu_messages'); ?>

    <div class="rmcu-settings-container">
        <!-- Tabs Navigation -->
        <nav class="nav-tab-wrapper rmcu-nav-tabs">
            <?php foreach ($tabs as $tab_key => $tab_label): ?>
                <a href="?page=rmcu-settings&tab=<?php echo $tab_key; ?>" 
                   class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                    <?php echo $tab_label; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Settings Form -->
        <form method="post" action="options.php" class="rmcu-settings-form" id="rmcu-settings-form">
            <?php settings_fields('rmcu_' . $active_tab . '_settings'); ?>
            
            <div class="rmcu-settings-content">
                <?php
                switch ($active_tab) {
                    case 'general':
                        include RMCU_PLUGIN_DIR . 'admin/partials/settings-general.php';
                        break;
                    case 'capture':
                        include RMCU_PLUGIN_DIR . 'admin/partials/settings-capture.php';
                        break;
                    case 'seo':
                        include RMCU_PLUGIN_DIR . 'admin/partials/settings-seo.php';
                        break;
                    case 'media':
                        include RMCU_PLUGIN_DIR . 'admin/partials/settings-media.php';
                        break;
                    case 'advanced':
                        include RMCU_PLUGIN_DIR . 'admin/partials/settings-advanced.php';
                        break;
                    default:
                        do_action('rmcu_settings_tab_' . $active_tab, $settings);
                        break;
                }
                ?>
            </div>

            <div class="rmcu-settings-footer">
                <?php submit_button(__('Save Settings', 'rmcu'), 'primary', 'submit', false); ?>
                <button type="button" class="button" id="rmcu-reset-settings">
                    <?php _e('Reset to Defaults', 'rmcu'); ?>
                </button>
                <button type="button" class="button" id="rmcu-export-settings">
                    <?php _e('Export Settings', 'rmcu'); ?>
                </button>
                <button type="button" class="button" id="rmcu-import-settings">
                    <?php _e('Import Settings', 'rmcu'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Import Modal -->
    <div id="rmcu-import-modal" class="rmcu-modal" style="display: none;">
        <div class="rmcu-modal-content">
            <div class="rmcu-modal-header">
                <h2><?php _e('Import Settings', 'rmcu'); ?></h2>
                <button type="button" class="rmcu-modal-close">&times;</button>
            </div>
            <div class="rmcu-modal-body">
                <form id="rmcu-import-form">
                    <p><?php _e('Select a JSON file containing RMCU settings to import.', 'rmcu'); ?></p>
                    <input type="file" name="import_file" accept=".json" required>
                    <p class="description">
                        <?php _e('Note: This will overwrite your current settings.', 'rmcu'); ?>
                    </p>
                </form>
            </div>
            <div class="rmcu-modal-footer">
                <button type="button" class="button button-primary" id="rmcu-confirm-import">
                    <?php _e('Import', 'rmcu'); ?>
                </button>
                <button type="button" class="button rmcu-modal-cancel">
                    <?php _e('Cancel', 'rmcu'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Form validation
    $('#rmcu-settings-form').on('submit', function(e) {
        // Add validation logic here
        return true;
    });

    // Reset settings
    $('#rmcu-reset-settings').on('click', function(e) {
        e.preventDefault();
        if (confirm('<?php _e('Are you sure you want to reset all settings to their defaults?', 'rmcu'); ?>')) {
            $.post(RMCU_Admin.ajax_url, {
                action: 'rmcu_reset_settings',
                nonce: RMCU_Admin.nonce,
                tab: '<?php echo $active_tab; ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
    });

    // Export settings
    $('#rmcu-export-settings').on('click', function(e) {
        e.preventDefault();
        $.post(RMCU_Admin.ajax_url, {
            action: 'rmcu_export_settings',
            nonce: RMCU_Admin.nonce
        }, function(response) {
            if (response.success) {
                const blob = new Blob([JSON.stringify(response.data.settings, null, 2)], {
                    type: 'application/json'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'rmcu-settings-' + new Date().toISOString().split('T')[0] + '.json';
                a.click();
                URL.revokeObjectURL(url);
            }
        });
    });

    // Import settings
    $('#rmcu-import-settings').on('click', function(e) {
        e.preventDefault();
        $('#rmcu-import-modal').show();
    });

    // Modal controls
    $('.rmcu-modal-close, .rmcu-modal-cancel').on('click', function() {
        $('#rmcu-import-modal').hide();
    });

    $('#rmcu-confirm-import').on('click', function() {
        const fileInput = $('#rmcu-import-form input[type="file"]')[0];
        if (!fileInput.files[0]) {
            alert('<?php _e('Please select a file to import.', 'rmcu'); ?>');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const settings = JSON.parse(e.target.result);
                $.post(RMCU_Admin.ajax_url, {
                    action: 'rmcu_import_settings',
                    nonce: RMCU_Admin.nonce,
                    settings: settings
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                });
            } catch (error) {
                alert('<?php _e('Invalid JSON file.', 'rmcu'); ?>');
            }
        };
        reader.readAsText(fileInput.files[0]);
    });

    // Live preview for some settings
    $('input[type="range"]').on('input', function() {
        const $this = $(this);
        const $output = $this.siblings('.range-value');
        if ($output.length) {
            $output.text($this.val());
        }
    });

    // Color pickers
    if ($.fn.wpColorPicker) {
        $('.rmcu-color-picker').wpColorPicker();
    }

    // Conditional fields
    $('[data-condition]').each(function() {
        const $this = $(this);
        const condition = $this.data('condition');
        const parts = condition.split('=');
        const field = parts[0];
        const value = parts[1];
        
        const checkCondition = function() {
            const $field = $('[name="' + field + '"]');
            if ($field.is(':checkbox')) {
                $this.toggle($field.is(':checked') == (value === 'true'));
            } else {
                $this.toggle($field.val() == value);
            }
        };
        
        $('[name="' + field + '"]').on('change', checkCondition);
        checkCondition();
    });
});
</script>