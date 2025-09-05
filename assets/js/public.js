/**
 * RMCU Public JavaScript
 * Version: 1.0.0
 */

(function($, window, document) {
    'use strict';

    // RMCU Public Namespace
    window.RMCU = window.RMCU || {};

    /**
     * RMCU Public Controller
     */
    class RMCUPublic {
        constructor() {
            this.widget = null;
            this.currentCapture = null;
            this.isRecording = false;
            this.mediaStream = null;
            this.mediaRecorder = null;
            this.chunks = [];
            
            this.init();
        }

        /**
         * Initialize
         */
        init() {
            this.setupWidget();
            this.setupShortcodes();
            this.setupEventListeners();
            this.loadRecentCaptures();
            
            // Initialize modules if available
            if (window.RMCUConfig) {
                window.RMCUConfig.set('public', true);
            }
        }

        /**
         * Setup Widget
         */
        setupWidget() {
            this.widget = $('#rmcu-capture-widget');
            if (!this.widget.length) return;

            // Toggle widget panel
            this.widget.find('.rmcu-widget-toggle').on('click', (e) => {
                e.preventDefault();
                this.toggleWidget();
            });

            // Close widget panel
            this.widget.find('.rmcu-widget-close').on('click', (e) => {
                e.preventDefault();
                this.closeWidget();
            });

            // Capture buttons
            this.widget.find('.rmcu-capture-btn').on('click', (e) => {
                e.preventDefault();
                const type = $(e.currentTarget).data('capture-type');
                this.startCapture(type);
            });

            // Report issue button
            $('#rmcu-report-issue').on('click', (e) => {
                e.preventDefault();
                this.showReportModal();
            });

            // Preview modal controls
            $('#rmcu-preview-save').on('click', () => this.saveCapture());
            $('#rmcu-preview-retry').on('click', () => this.retryCapture());
            $('#rmcu-preview-download').on('click', () => this.downloadCapture());
            $('.rmcu-preview-close').on('click', () => this.closePreview());
        }

        /**
         * Setup Shortcodes
         */
        setupShortcodes() {
            // Capture shortcode
            $('.rmcu-shortcode-capture').each((index, element) => {
                const $element = $(element);
                
                $element.find('.rmcu-capture-trigger').on('click', (e) => {
                    e.preventDefault();
                    const type = $(e.currentTarget).data('capture-type');
                    const postId = $element.data('post-id');
                    const autoSave = $element.data('auto-save') === 'true';
                    const showPreview = $element.data('show-preview') === 'true';
                    
                    this.startCapture(type, {
                        postId: postId,
                        autoSave: autoSave,
                        showPreview: showPreview,
                        container: $element
                    });
                });
            });

            // Gallery shortcode
            $('.rmcu-shortcode-gallery').each((index, element) => {
                this.initGallery($(element));
            });

            // Player shortcode
            $('.rmcu-shortcode-player').each((index, element) => {
                this.initPlayer($(element));
            });
        }

        /**
         * Setup Event Listeners
         */
        setupEventListeners() {
            // Modal close on overlay click
            $('.rmcu-modal').on('click', (e) => {
                if ($(e.target).hasClass('rmcu-modal')) {
                    $(e.target).hide();
                }
            });

            // Modal close buttons
            $('.rmcu-modal-close, .rmcu-modal-cancel').on('click', (e) => {
                e.preventDefault();
                $(e.currentTarget).closest('.rmcu-modal').hide();
            });

            // Issue form submission
            $('#rmcu-issue-form').on('submit', (e) => {
                e.preventDefault();
                this.submitIssue();
            });

            // Share buttons
            $(document).on('click', '.rmcu-share-button', (e) => {
                e.preventDefault();
                const $btn = $(e.currentTarget);
                const platform = $btn.data('platform');
                const url = $btn.data('url');
                const title = $btn.data('title') || '';
                
                if (platform === 'copy') {
                    this.copyToClipboard(url);
                } else {
                    this.share(platform, url, title);
                }
            });

            // Keyboard shortcuts
            $(document).on('keydown', (e) => {
                if (e.ctrlKey && e.shiftKey) {
                    switch(e.key) {
                        case 'S':
                            e.preventDefault();
                            this.startCapture('screenshot');
                            break;
                        case 'R':
                            e.preventDefault();
                            this.startCapture('video');
                            break;
                    }
                }
            });
        }

        /**
         * Toggle Widget
         */
        toggleWidget() {
            const panel = this.widget.find('.rmcu-widget-panel');
            if (panel.is(':visible')) {
                this.closeWidget();
            } else {
                panel.fadeIn(300);
                this.widget.addClass('rmcu-widget-open');
            }
        }

        /**
         * Close Widget
         */
        closeWidget() {
            this.widget.find('.rmcu-widget-panel').fadeOut(300);
            this.widget.removeClass('rmcu-widget-open');
        }

        /**
         * Start Capture
         */
        async startCapture(type, options = {}) {
            this.currentCapture = {
                type: type,
                options: options,
                startTime: Date.now()
            };

            try {
                switch(type) {
                    case 'screenshot':
                        await this.captureScreenshot();
                        break;
                    case 'fullpage':
                        await this.captureFullPage();
                        break;
                    case 'video':
                        await this.startVideoRecording();
                        break;
                    case 'audio':
                        await this.startAudioRecording();
                        break;
                    case 'annotation':
                        await this.startAnnotation();
                        break;
                    default:
                        throw new Error('Unknown capture type');
                }
            } catch (error) {
                console.error('Capture error:', error);
                this.showError(RMCU_Public.i18n.error);
            }
        }

        /**
         * Capture Screenshot
         */
        async captureScreenshot() {
            this.showStatus('Capturing screenshot...');
            
            // Using html2canvas if available
            if (window.html2canvas) {
                const canvas = await html2canvas(document.body);
                const dataUrl = canvas.toDataURL('image/png');
                this.showPreview(dataUrl, 'image');
            } else {
                // Fallback: Use browser API if available
                if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
                    try {
                        const stream = await navigator.mediaDevices.getDisplayMedia({
                            video: { mediaSource: 'screen' }
                        });
                        
                        const video = document.createElement('video');
                        video.srcObject = stream;
                        video.play();
                        
                        video.onloadedmetadata = () => {
                            const canvas = document.createElement('canvas');
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(video, 0, 0);
                            
                            const dataUrl = canvas.toDataURL('image/png');
                            this.showPreview(dataUrl, 'image');
                            
                            // Stop the stream
                            stream.getTracks().forEach(track => track.stop());
                        };
                    } catch (error) {
                        this.showError('Screenshot capture not supported');
                    }
                }
            }
        }

        /**
         * Capture Full Page
         */
        async captureFullPage() {
            this.showStatus('Capturing full page...');
            
            // Store original scroll position
            const originalScroll = window.pageYOffset;
            
            // Get page dimensions
            const body = document.body;
            const html = document.documentElement;
            const height = Math.max(
                body.scrollHeight, body.offsetHeight,
                html.clientHeight, html.scrollHeight, html.offsetHeight
            );
            
            // Scroll to top
            window.scrollTo(0, 0);
            
            // Capture with html2canvas if available
            if (window.html2canvas) {
                const canvas = await html2canvas(document.body, {
                    height: height,
                    windowHeight: height
                });
                
                const dataUrl = canvas.toDataURL('image/png');
                this.showPreview(dataUrl, 'image');
            }
            
            // Restore scroll position
            window.scrollTo(0, originalScroll);
        }

        /**
         * Start Video Recording
         */
        async startVideoRecording() {
            try {
                const stream = await navigator.mediaDevices.getDisplayMedia({
                    video: true,
                    audio: true
                });
                
                this.mediaStream = stream;
                this.mediaRecorder = new MediaRecorder(stream);
                this.chunks = [];
                
                this.mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) {
                        this.chunks.push(e.data);
                    }
                };
                
                this.mediaRecorder.onstop = () => {
                    const blob = new Blob(this.chunks, { type: 'video/webm' });
                    const url = URL.createObjectURL(blob);
                    this.showPreview(url, 'video');
                };
                
                this.mediaRecorder.start();
                this.isRecording = true;
                
                this.showRecordingControls();
                
                // Auto-stop after max duration
                const maxDuration = RMCU_Public.settings.max_recording_duration || 300000; // 5 minutes
                this.recordingTimeout = setTimeout(() => {
                    this.stopRecording();
                }, maxDuration);
                
            } catch (error) {
                this.showError('Video recording not supported or permission denied');
            }
        }

        /**
         * Start Audio Recording
         */
        async startAudioRecording() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                
                this.mediaStream = stream;
                this.mediaRecorder = new MediaRecorder(stream);
                this.chunks = [];
                
                this.mediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) {
                        this.chunks.push(e.data);
                    }
                };
                
                this.mediaRecorder.onstop = () => {
                    const blob = new Blob(this.chunks, { type: 'audio/webm' });
                    const url = URL.createObjectURL(blob);
                    this.showPreview(url, 'audio');
                };
                
                this.mediaRecorder.start();
                this.isRecording = true;
                
                this.showRecordingControls();
                
            } catch (error) {
                this.showError('Audio recording not supported or permission denied');
            }
        }

        /**
         * Stop Recording
         */
        stopRecording() {
            if (this.mediaRecorder && this.isRecording) {
                this.mediaRecorder.stop();
                this.isRecording = false;
                
                if (this.mediaStream) {
                    this.mediaStream.getTracks().forEach(track => track.stop());
                    this.mediaStream = null;
                }
                
                if (this.recordingTimeout) {
                    clearTimeout(this.recordingTimeout);
                }
                
                this.hideRecordingControls();
            }
        }

        /**
         * Show Recording Controls
         */
        showRecordingControls() {
            const controls = $('#rmcu-recording-controls');
            controls.show();
            
            // Update timer
            const startTime = Date.now();
            this.timerInterval = setInterval(() => {
                const elapsed = Math.floor((Date.now() - startTime) / 1000);
                const minutes = Math.floor(elapsed / 60);
                const seconds = elapsed % 60;
                controls.find('.rmcu-recording-time').text(
                    `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
                );
            }, 1000);
            
            // Bind controls
            controls.find('.rmcu-recording-stop').off('click').on('click', () => {
                this.stopRecording();
            });
            
            controls.find('.rmcu-recording-pause').off('click').on('click', () => {
                if (this.mediaRecorder.state === 'recording') {
                    this.mediaRecorder.pause();
                    $(this).find('svg').html('<rect x="8" y="5" width="8" height="14"></rect>');
                } else {
                    this.mediaRecorder.resume();
                    $(this).find('svg').html('<rect x="6" y="4" width="3" height="12"></rect><rect x="11" y="4" width="3" height="12"></rect>');
                }
            });
        }

        /**
         * Hide Recording Controls
         */
        hideRecordingControls() {
            $('#rmcu-recording-controls').hide();
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
            }
        }

        /**
         * Show Preview
         */
        showPreview(url, type) {
            const modal = $('#rmcu-preview-modal');
            const container = modal.find('.rmcu-preview-container');
            
            container.empty();
            
            if (type === 'image') {
                container.html(`<img src="${url}" alt="Preview">`);
            } else if (type === 'video') {
                container.html(`<video src="${url}" controls></video>`);
            } else if (type === 'audio') {
                container.html(`<audio src="${url}" controls></audio>`);
            }
            
            modal.show();
            
            // Store current capture data
            this.currentCapture.url = url;
            this.currentCapture.blob = this.dataURLtoBlob(url);
        }

        /**
         * Close Preview
         */
        closePreview() {
            $('#rmcu-preview-modal').hide();
            
            // Clean up
            if (this.currentCapture && this.currentCapture.url) {
                URL.revokeObjectURL(this.currentCapture.url);
            }
            this.currentCapture = null;
        }

        /**
         * Save Capture
         */
        async saveCapture() {
            const title = $('#rmcu-preview-modal .rmcu-preview-title').val() || '';
            const description = $('#rmcu-preview-modal .rmcu-preview-description').val() || '';
            
            this.showStatus('Saving...');
            
            const formData = new FormData();
            formData.append('action', 'rmcu_public_capture');
            formData.append('nonce', RMCU_Public.nonce);
            formData.append('type', this.currentCapture.type);
            formData.append('title', title);
            formData.append('description', description);
            
            if (this.currentCapture.options.postId) {
                formData.append('post_id', this.currentCapture.options.postId);
            }
            
            // Convert data URL to blob if needed
            if (this.currentCapture.url.startsWith('data:')) {
                formData.append('data', this.currentCapture.url);
            } else {
                // For blob URLs, fetch the blob
                const response = await fetch(this.currentCapture.url);
                const blob = await response.blob();
                formData.append('file', blob, `capture.${this.getFileExtension(this.currentCapture.type)}`);
            }
            
            try {
                const response = await $.ajax({
                    url: RMCU_Public.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false
                });
                
                if (response.success) {
                    this.showSuccess(RMCU_Public.i18n.success);
                    this.closePreview();
                    this.loadRecentCaptures();
                    
                    // Update shortcode preview if exists
                    if (this.currentCapture.options.container) {
                        this.updateShortcodePreview(this.currentCapture.options.container, response.data);
                    }
                } else {
                    this.showError(response.data.message || RMCU_Public.i18n.error);
                }
            } catch (error) {
                console.error('Save error:', error);
                this.showError(RMCU_Public.i18n.error);
            }
        }

        /**
         * Retry Capture
         */
        retryCapture() {
            this.closePreview();
            if (this.currentCapture) {
                this.startCapture(this.currentCapture.type, this.currentCapture.options);
            }
        }

        /**
         * Download Capture
         */
        downloadCapture() {
            if (!this.currentCapture || !this.currentCapture.url) return;
            
            const a = document.createElement('a');
            a.href = this.currentCapture.url;
            a.download = `capture_${Date.now()}.${this.getFileExtension(this.currentCapture.type)}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        /**
         * Load Recent Captures
         */
        async loadRecentCaptures() {
            const container = $('#rmcu-recent-captures');
            if (!container.length) return;
            
            try {
                const response = await $.get(RMCU_Public.api_url + 'public/captures', {
                    limit: 5
                });
                
                if (response && response.length > 0) {
                    let html = '';
                    response.forEach(capture => {
                        html += this.renderRecentCapture(capture);
                    });
                    container.html(html);
                } else {
                    container.html('<p class="rmcu-no-captures">No captures yet</p>');
                }
            } catch (error) {
                console.error('Failed to load recent captures:', error);
            }
        }

        /**
         * Render Recent Capture
         */
        renderRecentCapture(capture) {
            return `
                <div class="rmcu-recent-item" data-id="${capture.id}">
                    ${capture.thumbnail_url ? `<img src="${capture.thumbnail_url}" class="rmcu-recent-thumbnail" alt="">` : ''}
                    <div class="rmcu-recent-info">
                        <div class="rmcu-recent-title">${capture.title}</div>
                        <div class="rmcu-recent-meta">${capture.type} • ${capture.created_at_human}</div>
                    </div>
                </div>
            `;
        }

        /**
         * Show Report Modal
         */
        showReportModal() {
            $('#rmcu-issue-modal').show();
        }

        /**
         * Submit Issue
         */
        async submitIssue() {
            const form = $('#rmcu-issue-form');
            const formData = form.serialize() + '&action=rmcu_report_issue&nonce=' + RMCU_Public.nonce;
            
            // Include screenshot if checked
            if (form.find('[name="include_screenshot"]').is(':checked')) {
                // Capture screenshot first
                // TODO: Implement screenshot capture for issue
            }
            
            try {
                const response = await $.post(RMCU_Public.ajax_url, formData);
                
                if (response.success) {
                    this.showSuccess('Issue reported successfully');
                    $('#rmcu-issue-modal').hide();
                    form[0].reset();
                } else {
                    this.showError(response.data.message);
                }
            } catch (error) {
                console.error('Failed to submit issue:', error);
                this.showError('Failed to submit issue');
            }
        }

        /**
         * Initialize Gallery
         */
        initGallery($gallery) {
            // Play buttons for videos
            $gallery.find('.rmcu-gallery-play').on('click', function(e) {
                e.preventDefault();
                const $video = $(this).siblings('video');
                if ($video[0]) {
                    $video[0].play();
                    $(this).hide();
                }
            });
            
            // Lightbox
            if ($gallery.data('lightbox') === 'true') {
                $gallery.find('.rmcu-gallery-link').on('click', function(e) {
                    e.preventDefault();
                    // TODO: Implement lightbox
                });
            }
            
            // Delete action
            $gallery.find('.rmcu-action-delete').on('click', async function(e) {
                e.preventDefault();
                if (!confirm(RMCU_Public.i18n.confirm_delete)) return;
                
                const id = $(this).data('id');
                // TODO: Implement delete
            });
        }

        /**
         * Initialize Player
         */
        initPlayer($player) {
            // Custom controls if needed
            // TODO: Implement custom player controls
        }

        /**
         * Share
         */
        share(platform, url, title) {
            let shareUrl;
            
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`;
                    break;
                case 'linkedin':
                    shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }

        /**
         * Copy to Clipboard
         */
        async copyToClipboard(text) {
            try {
                await navigator.clipboard.writeText(text);
                this.showSuccess('Link copied to clipboard');
            } catch (error) {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                this.showSuccess('Link copied to clipboard');
            }
        }

        /**
         * Utilities
         */
        
        showStatus(message) {
            const $status = $('.rmcu-widget-status');
            $status.find('.rmcu-status-text').text(message);
            $status.show();
        }
        
        showSuccess(message) {
            this.showNotification(message, 'success');
        }
        
        showError(message) {
            this.showNotification(message, 'error');
        }
        
        showNotification(message, type = 'info') {
            const notification = $(`<div class="rmcu-notification rmcu-notification-${type}">${message}</div>`);
            $('body').append(notification);
            
            setTimeout(() => {
                notification.fadeOut(300, () => notification.remove());
            }, 3000);
        }
        
        getFileExtension(type) {
            const extensions = {
                'screenshot': 'png',
                'image': 'png',
                'video': 'webm',
                'audio': 'webm'
            };
            return extensions[type] || 'dat';
        }
        
        dataURLtoBlob(dataURL) {
            const arr = dataURL.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while(n--) {
                u8arr[n] = bstr.charCodeAt(n);
            }
            return new Blob([u8arr], {type: mime});
        }
    }

    // Initialize on DOM ready
    $(document).ready(() => {
        window.RMCU.public = new RMCUPublic();
    });

})(jQuery, window, document);