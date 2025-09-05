<?php
/**
 * Upload interface template
 *
 * @package    RankMath_Capture_Unified
 * @subpackage RankMath_Capture_Unified/public/partials
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

$allowed_types = explode(',', $atts['allowed_types']);
$max_size = $atts['max_size'];
$auto_process = filter_var($atts['auto_process'], FILTER_VALIDATE_BOOLEAN);
$show_progress = filter_var($atts['show_progress'], FILTER_VALIDATE_BOOLEAN);
$multiple = filter_var($atts['multiple'], FILTER_VALIDATE_BOOLEAN);

// Build accept attribute for file input
$accept_types = array();
foreach ($allowed_types as $type) {
    switch (trim($type)) {
        case 'video':
            $accept_types[] = 'video/*';
            break;
        case 'audio':
            $accept_types[] = 'audio/*';
            break;
        case 'image':
            $accept_types[] = 'image/*';
            break;
    }
}
$accept = implode(',', $accept_types);
?>

<div class="rmcu-upload-interface <?php echo esc_attr($atts['class']); ?>" data-max-size="<?php echo esc_attr($max_size); ?>" data-auto-process="<?php echo $auto_process ? 'true' : 'false'; ?>">
    
    <!-- Drop Zone -->
    <div class="rmcu-dropzone">
        <div class="rmcu-dropzone-inner">
            <div class="rmcu-upload-icon">📁</div>
            <h3 class="rmcu-upload-title"><?php _e('Drop files here or click to upload', 'rmcu'); ?></h3>
            <p class="rmcu-upload-subtitle">
                <?php 
                $types_text = array(
                    'video' => __('Videos', 'rmcu'),
                    'audio' => __('Audio', 'rmcu'),
                    'image' => __('Images', 'rmcu')
                );
                $allowed_text = array();
                foreach ($allowed_types as $type) {
                    if (isset($types_text[trim($type)])) {
                        $allowed_text[] = $types_text[trim($type)];
                    }
                }
                printf(
                    __('Accepted formats: %s | Max size: %s', 'rmcu'),
                    implode(', ', $allowed_text),
                    size_format($max_size)
                );
                ?>
            </p>
            
            <input type="file" 
                   class="rmcu-file-input" 
                   accept="<?php echo esc_attr($accept); ?>"
                   <?php echo $multiple ? 'multiple' : ''; ?>
                   style="display:none;">
            
            <button class="rmcu-browse-btn">
                <span class="rmcu-icon">📂</span>
                <?php _e('Browse Files', 'rmcu'); ?>
            </button>
        </div>
        
        <!-- Drag Overlay -->
        <div class="rmcu-drag-overlay" style="display:none;">
            <div class="rmcu-drag-message">
                <span class="rmcu-icon">⬇️</span>
                <p><?php _e('Drop files here', 'rmcu'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- File Queue -->
    <div class="rmcu-file-queue" style="display:none;">
        <h4><?php _e('Files to Upload', 'rmcu'); ?></h4>
        <div class="rmcu-queue-list"></div>
        
        <div class="rmcu-queue-actions">
            <button class="rmcu-clear-queue">
                <span class="rmcu-icon">🗑️</span>
                <?php _e('Clear All', 'rmcu'); ?>
            </button>
            <button class="rmcu-upload-all">
                <span class="rmcu-icon">⬆️</span>
                <?php _e('Upload All', 'rmcu'); ?>
            </button>
        </div>
    </div>
    
    <!-- Upload Progress -->
    <?php if ($show_progress) : ?>
    <div class="rmcu-upload-progress" style="display:none;">
        <div class="rmcu-overall-progress">
            <h4><?php _e('Uploading...', 'rmcu'); ?></h4>
            <div class="rmcu-progress-bar">
                <div class="rmcu-progress-fill"></div>
            </div>
            <div class="rmcu-progress-stats">
                <span class="rmcu-progress-percent">0%</span>
                <span class="rmcu-progress-speed"></span>
                <span class="rmcu-progress-eta"></span>
            </div>
        </div>
        
        <div class="rmcu-file-progress-list"></div>
    </div>
    <?php endif; ?>
    
    <!-- Results -->
    <div class="rmcu-upload-results" style="display:none;">
        <h4><?php _e('Upload Complete', 'rmcu'); ?></h4>
        <div class="rmcu-results-list"></div>
        
        <div class="rmcu-results-actions">
            <button class="rmcu-upload-more">
                <span class="rmcu-icon">➕</span>
                <?php _e('Upload More', 'rmcu'); ?>
            </button>
            <button class="rmcu-view-gallery">
                <span class="rmcu-icon">🖼️</span>
                <?php _e('View Gallery', 'rmcu'); ?>
            </button>
        </div>
    </div>
    
    <!-- Error Messages -->
    <div class="rmcu-upload-errors" style="display:none;">
        <h4><?php _e('Upload Errors', 'rmcu'); ?></h4>
        <ul class="rmcu-error-list"></ul>
    </div>
</div>

<!-- File Item Template -->
<script type="text/template" id="rmcu-file-item-template">
    <div class="rmcu-file-item" data-file-id="{id}">
        <div class="rmcu-file-preview">
            <img src="{preview}" alt="{name}" style="display:none;">
            <video src="{preview}" style="display:none;"></video>
            <audio src="{preview}" style="display:none;"></audio>
            <div class="rmcu-file-icon">{icon}</div>
        </div>
        
        <div class="rmcu-file-info">
            <h5 class="rmcu-file-name">{name}</h5>
            <p class="rmcu-file-size">{size}</p>
            
            <div class="rmcu-file-metadata" style="display:none;">
                <input type="text" class="rmcu-file-title" placeholder="<?php esc_attr_e('Title', 'rmcu'); ?>" value="{name}">
                <textarea class="rmcu-file-description" placeholder="<?php esc_attr_e('Description', 'rmcu'); ?>" rows="2"></textarea>
                <input type="text" class="rmcu-file-tags" placeholder="<?php esc_attr_e('Tags (comma separated)', 'rmcu'); ?>">
            </div>
            
            <div class="rmcu-file-progress" style="display:none;">
                <div class="rmcu-progress-bar">
                    <div class="rmcu-progress-fill"></div>
                </div>
                <span class="rmcu-progress-text">0%</span>
            </div>
            
            <div class="rmcu-file-status">
                <span class="rmcu-status-text"><?php _e('Ready', 'rmcu'); ?></span>
            </div>
        </div>
        
        <div class="rmcu-file-actions">
            <button class="rmcu-edit-file" title="<?php esc_attr_e('Edit details', 'rmcu'); ?>">
                <span class="rmcu-icon">✏️</span>
            </button>
            <button class="rmcu-remove-file" title="<?php esc_attr_e('Remove', 'rmcu'); ?>">
                <span class="rmcu-icon">❌</span>
            </button>
        </div>
    </div>
</script>

<!-- Result Item Template -->
<script type="text/template" id="rmcu-result-item-template">
    <div class="rmcu-result-item" data-capture-id="{id}">
        <div class="rmcu-result-preview">
            <img src="{thumbnail}" alt="{title}">
        </div>
        
        <div class="rmcu-result-info">
            <h5 class="rmcu-result-title">{title}</h5>
            <p class="rmcu-result-type">{type}</p>
        </div>
        
        <div class="rmcu-result-actions">
            <a href="{url}" class="rmcu-view-result" target="_blank">
                <span class="rmcu-icon">👁️</span>
                <?php _e('View', 'rmcu'); ?>
            </a>
            <button class="rmcu-copy-result-link" data-url="{url}">
                <span class="rmcu-icon">🔗</span>
                <?php _e('Copy Link', 'rmcu'); ?>
            </button>
        </div>
    </div>
</script>

<script>
jQuery(document).ready(function($) {
    const $interface = $('.rmcu-upload-interface');
    const $dropzone = $('.rmcu-dropzone');
    const $fileInput = $('.rmcu-file-input');
    const $queue = $('.rmcu-file-queue');
    const $queueList = $('.rmcu-queue-list');
    const maxSize = parseInt($interface.data('max-size'));
    const autoProcess = $interface.data('auto-process') === 'true';
    
    let fileQueue = [];
    let fileIdCounter = 0;
    
    // Browse button click
    $('.rmcu-browse-btn').on('click', function(e) {
        e.preventDefault();
        $fileInput.click();
    });
    
    // File input change
    $fileInput.on('change', function() {
        handleFiles(this.files);
    });
    
    // Drag and drop
    $dropzone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('rmcu-dragover');
        $('.rmcu-drag-overlay').show();
    });
    
    $dropzone.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('rmcu-dragover');
        $('.rmcu-drag-overlay').hide();
    });
    
    $dropzone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('rmcu-dragover');
        $('.rmcu-drag-overlay').hide();
        
        const files = e.originalEvent.dataTransfer.files;
        handleFiles(files);
    });
    
    // Handle files
    function handleFiles(files) {
        for (let file of files) {
            if (file.size > maxSize) {
                showError('<?php _e('File too large:', 'rmcu'); ?> ' + file.name);
                continue;
            }
            
            const fileId = 'file-' + (++fileIdCounter);
            const fileItem = {
                id: fileId,
                file: file,
                name: file.name,
                size: formatFileSize(file.size),
                type: file.type,
                icon: getFileIcon(file.type),
                preview: URL.createObjectURL(file)
            };
            
            fileQueue.push(fileItem);
            addFileToQueue(fileItem);
        }
        
        if (fileQueue.length > 0) {
            $queue.show();
            
            if (autoProcess) {
                uploadAll();
            }
        }
    }
    
    // Add file to queue display
    function addFileToQueue(fileItem) {
        const template = $('#rmcu-file-item-template').html();
        const html = template
            .replace(/{id}/g, fileItem.id)
            .replace(/{name}/g, fileItem.name)
            .replace(/{size}/g, fileItem.size)
            .replace(/{icon}/g, fileItem.icon)
            .replace(/{preview}/g, fileItem.preview);
        
        $queueList.append(html);
        
        // Show appropriate preview
        const $item = $(`[data-file-id="${fileItem.id}"]`);
        if (fileItem.type.startsWith('image/')) {
            $item.find('img').show();
            $item.find('.rmcu-file-icon').hide();
        } else if (fileItem.type.startsWith('video/')) {
            $item.find('video').show();
            $item.find('.rmcu-file-icon').hide();
        } else if (fileItem.type.startsWith('audio/')) {
            $item.find('audio').show();
            $item.find('.rmcu-file-icon').hide();
        }
    }
    
    // Upload all files
    $('.rmcu-upload-all').on('click', uploadAll);
    
    function uploadAll() {
        $('.rmcu-upload-progress').show();
        let completed = 0;
        
        fileQueue.forEach(function(fileItem) {
            uploadFile(fileItem, function() {
                completed++;
                updateOverallProgress(completed, fileQueue.length);
                
                if (completed === fileQueue.length) {
                    $('.rmcu-upload-progress').hide();
                    $('.rmcu-upload-results').show();
                }
            });
        });
    }
    
    // Upload single file
    function uploadFile(fileItem, callback) {
        const $item = $(`[data-file-id="${fileItem.id}"]`);
        const formData = new FormData();
        
        formData.append('action', 'rmcu_upload_file');
        formData.append('nonce', rmcu_ajax.nonce);
        formData.append('capture_file', fileItem.file);
        formData.append('title', $item.find('.rmcu-file-title').val() || fileItem.name);
        formData.append('description', $item.find('.rmcu-file-description').val());
        formData.append('tags', $item.find('.rmcu-file-tags').val());
        formData.append('type', getMediaType(fileItem.type));
        
        $item.find('.rmcu-file-progress').show();
        $item.find('.rmcu-status-text').text('<?php _e('Uploading...', 'rmcu'); ?>');
        
        $.ajax({
            url: rmcu_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        $item.find('.rmcu-progress-fill').css('width', percentComplete + '%');
                        $item.find('.rmcu-progress-text').text(Math.round(percentComplete) + '%');
                    }
                });
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $item.find('.rmcu-status-text').text('<?php _e('Complete', 'rmcu'); ?>');
                    $item.addClass('rmcu-upload-success');
                    
                    // Add to results
                    addToResults(response.data);
                } else {
                    $item.find('.rmcu-status-text').text('<?php _e('Error', 'rmcu'); ?>');
                    $item.addClass('rmcu-upload-error');
                    showError(response.data.message);
                }
                
                if (callback) callback();
            },
            error: function() {
                $item.find('.rmcu-status-text').text('<?php _e('Failed', 'rmcu'); ?>');
                $item.addClass('rmcu-upload-error');
                showError('<?php _e('Upload failed for', 'rmcu'); ?> ' + fileItem.name);
                
                if (callback) callback();
            }
        });
    }
    
    // Add to results
    function addToResults(data) {
        const template = $('#rmcu-result-item-template').html();
        const html = template
            .replace(/{id}/g, data.capture_id)
            .replace(/{title}/g, data.title || 'Untitled')
            .replace(/{type}/g, data.type)
            .replace(/{url}/g, data.redirect_url)
            .replace(/{thumbnail}/g, data.thumbnail || '');
        
        $('.rmcu-results-list').append(html);
    }
    
    // Clear queue
    $('.rmcu-clear-queue').on('click', function() {
        fileQueue = [];
        $queueList.empty();
        $queue.hide();
    });
    
    // Remove file from queue
    $(document).on('click', '.rmcu-remove-file', function() {
        const $item = $(this).closest('.rmcu-file-item');
        const fileId = $item.data('file-id');
        
        fileQueue = fileQueue.filter(f => f.id !== fileId);
        $item.remove();
        
        if (fileQueue.length === 0) {
            $queue.hide();
        }
    });
    
    // Edit file details
    $(document).on('click', '.rmcu-edit-file', function() {
        const $item = $(this).closest('.rmcu-file-item');
        $item.find('.rmcu-file-metadata').toggle();
    });
    
    // Copy result link
    $(document).on('click', '.rmcu-copy-result-link', function() {
        const url = $(this).data('url');
        copyToClipboard(url);
        $(this).text('<?php _e('Copied!', 'rmcu'); ?>');
        setTimeout(() => {
            $(this).html('<span class="rmcu-icon">🔗</span> <?php _e('Copy Link', 'rmcu'); ?>');
        }, 2000);
    });
    
    // Upload more
    $('.rmcu-upload-more').on('click', function() {
        $('.rmcu-upload-results').hide();
        $('.rmcu-results-list').empty();
        fileQueue = [];
        $queueList.empty();
        $fileInput.val('');
    });
    
    // View gallery
    $('.rmcu-view-gallery').on('click', function() {
        window.location.href = '<?php echo get_permalink(get_option('rmcu_gallery_page_id')); ?>';
    });
    
    // Helper functions
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    function getFileIcon(mimeType) {
        if (mimeType.startsWith('video/')) return '🎬';
        if (mimeType.startsWith('audio/')) return '🎵';
        if (mimeType.startsWith('image/')) return '🖼️';
        return '📄';
    }
    
    function getMediaType(mimeType) {
        if (mimeType.startsWith('video/')) return 'video';
        if (mimeType.startsWith('audio/')) return 'audio';
        if (mimeType.startsWith('image/')) return 'image';
        return 'file';
    }
    
    function updateOverallProgress(completed, total) {
        const percent = (completed / total) * 100;
        $('.rmcu-overall-progress .rmcu-progress-fill').css('width', percent + '%');
        $('.rmcu-progress-percent').text(Math.round(percent) + '%');
    }
    
    function showError(message) {
        $('.rmcu-upload-errors').show();
        $('.rmcu-error-list').append('<li>' + message + '</li>');
    }
    
    function copyToClipboard(text) {
        const $temp = $('<input>');
        $('body').append($temp);
        $temp.val(text).select();
        document.execCommand('copy');
        $temp.remove();
    }
});
</script>
