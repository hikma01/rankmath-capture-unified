<?php
/**
 * Capture gallery template
 *
 * @package    RankMath_Capture_Unified
 * @subpackage RankMath_Capture_Unified/public/partials
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

$columns = intval($atts['columns']);
$show_title = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);
$show_date = filter_var($atts['show_date'], FILTER_VALIDATE_BOOLEAN);
$show_user = filter_var($atts['show_user'], FILTER_VALIDATE_BOOLEAN);
?>

<div class="rmcu-gallery <?php echo esc_attr($atts['class']); ?>" data-columns="<?php echo $columns; ?>">
    <div class="rmcu-gallery-grid" style="grid-template-columns: repeat(<?php echo $columns; ?>, 1fr);">
        <?php foreach ($captures as $capture) : 
            $attachment_url = wp_get_attachment_url($capture->attachment_id);
            $attachment_thumb = wp_get_attachment_image_url($capture->attachment_id, 'medium');
            $user = get_userdata($capture->user_id);
            $metadata = json_decode($capture->metadata, true);
        ?>
        <div class="rmcu-gallery-item" data-capture-id="<?php echo esc_attr($capture->id); ?>" data-type="<?php echo esc_attr($capture->type); ?>">
            <div class="rmcu-gallery-thumbnail">
                <?php if ($capture->type === 'video') : ?>
                    <video class="rmcu-gallery-video" poster="<?php echo esc_url($attachment_thumb); ?>">
                        <source src="<?php echo esc_url($attachment_url); ?>" type="video/webm">
                        <source src="<?php echo esc_url($attachment_url); ?>" type="video/mp4">
                    </video>
                    <div class="rmcu-gallery-play-overlay">
                        <span class="rmcu-play-icon">▶️</span>
                    </div>
                    <?php if ($capture->duration) : ?>
                    <span class="rmcu-duration"><?php echo gmdate('i:s', $capture->duration); ?></span>
                    <?php endif; ?>
                <?php elseif ($capture->type === 'audio') : ?>
                    <div class="rmcu-gallery-audio-placeholder">
                        <span class="rmcu-audio-icon">🎵</span>
                        <audio class="rmcu-gallery-audio" controls>
                            <source src="<?php echo esc_url($attachment_url); ?>" type="audio/webm">
                            <source src="<?php echo esc_url($attachment_url); ?>" type="audio/ogg">
                        </audio>
                    </div>
                    <?php if ($capture->duration) : ?>
                    <span class="rmcu-duration"><?php echo gmdate('i:s', $capture->duration); ?></span>
                    <?php endif; ?>
                <?php else : ?>
                    <img src="<?php echo esc_url($attachment_thumb); ?>" alt="<?php echo esc_attr($capture->title); ?>" class="rmcu-gallery-image">
                <?php endif; ?>
                
                <div class="rmcu-gallery-type-badge">
                    <?php
                    $type_labels = array(
                        'video' => __('Video', 'rmcu'),
                        'audio' => __('Audio', 'rmcu'),
                        'screen' => __('Screen', 'rmcu'),
                        'image' => __('Photo', 'rmcu')
                    );
                    echo esc_html($type_labels[$capture->type] ?? $capture->type);
                    ?>
                </div>
            </div>
            
            <div class="rmcu-gallery-info">
                <?php if ($show_title && $capture->title) : ?>
                <h4 class="rmcu-gallery-title"><?php echo esc_html($capture->title); ?></h4>
                <?php endif; ?>
                
                <?php if ($capture->description) : ?>
                <p class="rmcu-gallery-description"><?php echo esc_html(wp_trim_words($capture->description, 20)); ?></p>
                <?php endif; ?>
                
                <div class="rmcu-gallery-meta">
                    <?php if ($show_date) : ?>
                    <span class="rmcu-gallery-date">
                        <span class="rmcu-icon">📅</span>
                        <?php echo human_time_diff(strtotime($capture->created_at), current_time('timestamp')) . ' ' . __('ago', 'rmcu'); ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($show_user && $user) : ?>
                    <span class="rmcu-gallery-user">
                        <span class="rmcu-icon">👤</span>
                        <?php echo esc_html($user->display_name); ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <div class="rmcu-gallery-actions">
                    <a href="<?php echo esc_url($attachment_url); ?>" class="rmcu-gallery-view" target="_blank" title="<?php esc_attr_e('View', 'rmcu'); ?>">
                        <span class="rmcu-icon">👁️</span>
                    </a>
                    
                    <a href="<?php echo esc_url($attachment_url); ?>" class="rmcu-gallery-download" download title="<?php esc_attr_e('Download', 'rmcu'); ?>">
                        <span class="rmcu-icon">⬇️</span>
                    </a>
                    
                    <?php if ($capture->user_id == get_current_user_id() || current_user_can('manage_options')) : ?>
                    <button class="rmcu-gallery-delete" data-capture-id="<?php echo esc_attr($capture->id); ?>" title="<?php esc_attr_e('Delete', 'rmcu'); ?>">
                        <span class="rmcu-icon">🗑️</span>
                    </button>
                    <?php endif; ?>
                    
                    <button class="rmcu-gallery-share" data-url="<?php echo esc_url(get_permalink($capture->attachment_id)); ?>" title="<?php esc_attr_e('Share', 'rmcu'); ?>">
                        <span class="rmcu-icon">🔗</span>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Lightbox Modal -->
    <div class="rmcu-lightbox" style="display:none;">
        <div class="rmcu-lightbox-content">
            <button class="rmcu-lightbox-close">✕</button>
            <div class="rmcu-lightbox-media"></div>
            <div class="rmcu-lightbox-info">
                <h3 class="rmcu-lightbox-title"></h3>
                <p class="rmcu-lightbox-description"></p>
            </div>
        </div>
    </div>
    
    <!-- Share Modal -->
    <div class="rmcu-share-modal" style="display:none;">
        <div class="rmcu-share-content">
            <h3><?php _e('Share this capture', 'rmcu'); ?></h3>
            <button class="rmcu-share-close">✕</button>
            
            <div class="rmcu-share-options">
                <button class="rmcu-share-facebook" data-network="facebook">
                    <span class="rmcu-icon">📘</span> Facebook
                </button>
                <button class="rmcu-share-twitter" data-network="twitter">
                    <span class="rmcu-icon">🐦</span> Twitter
                </button>
                <button class="rmcu-share-linkedin" data-network="linkedin">
                    <span class="rmcu-icon">💼</span> LinkedIn
                </button>
                <button class="rmcu-share-whatsapp" data-network="whatsapp">
                    <span class="rmcu-icon">📱</span> WhatsApp
                </button>
                <button class="rmcu-share-email" data-network="email">
                    <span class="rmcu-icon">✉️</span> Email
                </button>
            </div>
            
            <div class="rmcu-share-link">
                <input type="text" class="rmcu-share-url" readonly>
                <button class="rmcu-copy-link"><?php _e('Copy Link', 'rmcu'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Play video on hover
    $('.rmcu-gallery-video').on('mouseenter', function() {
        this.play();
    }).on('mouseleave', function() {
        this.pause();
        this.currentTime = 0;
    });
    
    // Handle play overlay click
    $('.rmcu-gallery-play-overlay').on('click', function(e) {
        e.preventDefault();
        const video = $(this).siblings('video')[0];
        const $item = $(this).closest('.rmcu-gallery-item');
        
        // Open in lightbox
        const $lightbox = $('.rmcu-lightbox');
        const $media = $('.rmcu-lightbox-media');
        
        $media.html('<video controls autoplay><source src="' + video.querySelector('source').src + '"></video>');
        $lightbox.fadeIn();
    });
    
    // Lightbox close
    $('.rmcu-lightbox-close, .rmcu-lightbox').on('click', function(e) {
        if (e.target === this) {
            $('.rmcu-lightbox').fadeOut();
            $('.rmcu-lightbox-media').empty();
        }
    });
    
    // Delete capture
    $('.rmcu-gallery-delete').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to delete this capture?', 'rmcu'); ?>')) {
            return;
        }
        
        const $btn = $(this);
        const captureId = $btn.data('capture-id');
        const $item = $btn.closest('.rmcu-gallery-item');
        
        $.post(rmcu_ajax.ajax_url, {
            action: 'rmcu_delete_capture',
            capture_id: captureId,
            nonce: rmcu_ajax.nonce
        }, function(response) {
            if (response.success) {
                $item.fadeOut(function() {
                    $(this).remove();
                });
            } else {
                alert(response.data.message);
            }
        });
    });
    
    // Share functionality
    $('.rmcu-gallery-share').on('click', function() {
        const url = $(this).data('url');
        const $modal = $('.rmcu-share-modal');
        
        $modal.find('.rmcu-share-url').val(url);
        $modal.fadeIn();
    });
    
    $('.rmcu-share-close, .rmcu-share-modal').on('click', function(e) {
        if (e.target === this) {
            $('.rmcu-share-modal').fadeOut();
        }
    });
    
    $('.rmcu-copy-link').on('click', function() {
        const $input = $('.rmcu-share-url');
        $input.select();
        document.execCommand('copy');
        $(this).text('<?php _e('Copied!', 'rmcu'); ?>');
        setTimeout(() => {
            $(this).text('<?php _e('Copy Link', 'rmcu'); ?>');
        }, 2000);
    });
    
    // Social share buttons
    $('.rmcu-share-options button').on('click', function() {
        const network = $(this).data('network');
        const url = $('.rmcu-share-url').val();
        const title = document.title;
        let shareUrl;
        
        switch(network) {
            case 'facebook':
                shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
                break;
            case 'twitter':
                shareUrl = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(title);
                break;
            case 'linkedin':
                shareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(url);
                break;
            case 'whatsapp':
                shareUrl = 'https://wa.me/?text=' + encodeURIComponent(title + ' ' + url);
                break;
            case 'email':
                shareUrl = 'mailto:?subject=' + encodeURIComponent(title) + '&body=' + encodeURIComponent(url);
                break;
        }
        
        if (shareUrl) {
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }
    });
});
</script>
