/**
 * RMCU Video Capture Module
 * Gère l'enregistrement vidéo
 */
(function(window) {
    'use strict';

    class CaptureVideo {
        constructor(controller) {
            this.controller = controller;
            this.mediaRecorder = null;
            this.stream = null;
            this.chunks = [];
            this.recording = false;
            this.startTime = null;
            this.config = window.RMCUConfig.get('capture.video');
        }

        /**
         * Démarrer l'enregistrement vidéo
         */
        async startRecording(options = {}) {
            if (this.recording) {
                throw new Error('Recording already in progress');
            }

            try {
                window.RMCULogger.info('Starting video recording');

                // Configuration
                const config = {
                    video: {
                        width: options.width || this.config.resolution.width,
                        height: options.height || this.config.resolution.height,
                        frameRate: options.frameRate || 30,
                        facingMode: options.facingMode || 'user'
                    },
                    audio: options.audio !== false
                };

                // Obtenir le stream
                this.stream = await navigator.mediaDevices.getUserMedia(config);

                // Créer le MediaRecorder
                const mimeType = this.getSupportedMimeType();
                const recorderOptions = {
                    mimeType: mimeType,
                    videoBitsPerSecond: options.bitrate || 2500000
                };

                this.mediaRecorder = new MediaRecorder(this.stream, recorderOptions);
                this.chunks = [];

                // Gérer les événements
                this.setupRecorderEvents();

                // Démarrer l'enregistrement
                this.mediaRecorder.start();
                this.recording = true;
                this.startTime = Date.now();

                // Limite de durée
                if (this.config.maxDuration) {
                    this.maxDurationTimeout = setTimeout(() => {
                        this.stopRecording();
                    }, this.config.maxDuration * 1000);
                }

                window.RMCULogger.info('Video recording started');
                return {
                    success: true,
                    stream: this.stream,
                    mimeType: mimeType
                };

            } catch (error) {
                window.RMCULogger.error('Failed to start video recording', error);
                this.cleanup();
                throw error;
            }
        }

        /**
         * Arrêter l'enregistrement
         */
        async stopRecording() {
            if (!this.recording || !this.mediaRecorder) {
                throw new Error('No recording in progress');
            }

            window.RMCULogger.info('Stopping video recording');

            return new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    reject(new Error('Stop recording timeout'));
                }, 10000);

                this.mediaRecorder.addEventListener('stop', () => {
                    clearTimeout(timeout);
                    clearTimeout(this.maxDurationTimeout);

                    const duration = Date.now() - this.startTime;
                    const blob = new Blob(this.chunks, {
                        type: this.mediaRecorder.mimeType
                    });

                    const result = {
                        blob: blob,
                        duration: duration,
                        mimeType: this.mediaRecorder.mimeType,
                        size: blob.size,
                        timestamp: this.startTime,
                        url: URL.createObjectURL(blob)
                    };

                    this.cleanup();
                    window.RMCULogger.info('Video recording stopped', {
                        duration: duration,
                        size: blob.size
                    });

                    resolve(result);
                });

                this.mediaRecorder.stop();
                this.recording = false;
            });
        }

        /**
         * Mettre en pause l'enregistrement
         */
        pauseRecording() {
            if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
                this.mediaRecorder.pause();
                window.RMCULogger.debug('Recording paused');
                return true;
            }
            return false;
        }

        /**
         * Reprendre l'enregistrement
         */
        resumeRecording() {
            if (this.mediaRecorder && this.mediaRecorder.state === 'paused') {
                this.mediaRecorder.resume();
                window.RMCULogger.debug('Recording resumed');
                return true;
            }
            return false;
        }

        /**
         * Prendre une capture d'écran de la vidéo
         */
        captureFrame() {
            if (!this.stream) {
                throw new Error('No video stream available');
            }

            const video = document.createElement('video');
            video.srcObject = this.stream;
            video.play();

            return new Promise((resolve) => {
                video.addEventListener('loadedmetadata', () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0);

                    canvas.toBlob((blob) => {
                        resolve({
                            blob: blob,
                            url: URL.createObjectURL(blob),
                            width: canvas.width,
                            height: canvas.height,
                            timestamp: Date.now()
                        });
                    }, 'image/png', this.config.quality);
                });
            });
        }

        /**
         * Obtenir le type MIME supporté
         */
        getSupportedMimeType() {
            const types = [
                'video/webm;codecs=vp9,opus',
                'video/webm;codecs=vp8,opus',
                'video/webm',
                'video/mp4',
            ];

            for (const type of types) {
                if (MediaRecorder.isTypeSupported(type)) {
                    window.RMCULogger.debug(`Using MIME type: ${type}`);
                    return type;
                }
            }

            throw new Error('No supported video MIME type found');
        }

        /**
         * Configurer les événements du recorder
         */
        setupRecorderEvents() {
            this.mediaRecorder.addEventListener('dataavailable', (e) => {
                if (e.data.size > 0) {
                    this.chunks.push(e.data);
                    window.RMCULogger.debug('Video data available', {
                        size: e.data.size,
                        totalChunks: this.chunks.length
                    });
                }
            });

            this.mediaRecorder.addEventListener('error', (e) => {
                window.RMCULogger.error('MediaRecorder error', e.error);
                this.controller.emit('recording-error', e.error);
            });

            this.mediaRecorder.addEventListener('start', () => {
                this.controller.emit('recording-started', {
                    type: 'video',
                    mimeType: this.mediaRecorder.mimeType
                });
            });

            this.mediaRecorder.addEventListener('stop', () => {
                this.controller.emit('recording-stopped', {
                    type: 'video',
                    duration: Date.now() - this.startTime
                });
            });
        }

        /**
         * Nettoyer les ressources
         */
        cleanup() {
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }

            if (this.mediaRecorder) {
                this.mediaRecorder = null;
            }

            this.chunks = [];
            this.recording = false;
            this.startTime = null;

            if (this.maxDurationTimeout) {
                clearTimeout(this.maxDurationTimeout);
                this.maxDurationTimeout = null;
            }
        }

        /**
         * Obtenir les périphériques disponibles
         */
        async getDevices() {
            const devices = await navigator.mediaDevices.enumerateDevices();
            return {
                video: devices.filter(d => d.kind === 'videoinput'),
                audio: devices.filter(d => d.kind === 'audioinput')
            };
        }

        /**
         * Changer de caméra
         */
        async switchCamera(deviceId) {
            if (!this.recording) {
                throw new Error('No recording in progress');
            }

            // Arrêter le stream actuel
            if (this.stream) {
                this.stream.getVideoTracks().forEach(track => track.stop());
            }

            // Démarrer avec la nouvelle caméra
            const newStream = await navigator.mediaDevices.getUserMedia({
                video: { deviceId: deviceId },
                audio: true
            });

            // Remplacer le track vidéo
            const videoTrack = newStream.getVideoTracks()[0];
            const sender = this.stream.getVideoTracks()[0];
            
            if (sender) {
                this.stream.removeTrack(sender);
            }
            this.stream.addTrack(videoTrack);

            window.RMCULogger.info('Camera switched', { deviceId });
        }

        /**
         * Appliquer des effets vidéo
         */
        applyVideoEffect(effect) {
            if (!this.stream) {
                throw new Error('No video stream available');
            }

            const video = document.createElement('video');
            video.srcObject = this.stream;

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            const processFrame = () => {
                if (!this.recording) return;

                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                // Appliquer l'effet
                switch (effect) {
                    case 'grayscale':
                        ctx.filter = 'grayscale(100%)';
                        break;
                    case 'sepia':
                        ctx.filter = 'sepia(100%)';
                        break;
                    case 'blur':
                        ctx.filter = 'blur(5px)';
                        break;
                    case 'brightness':
                        ctx.filter = 'brightness(1.5)';
                        break;
                    case 'contrast':
                        ctx.filter = 'contrast(2)';
                        break;
                }

                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                requestAnimationFrame(processFrame);
            };

            video.play();
            processFrame();

            window.RMCULogger.debug('Video effect applied', { effect });
        }

        /**
         * Obtenir les statistiques d'enregistrement
         */
        getStats() {
            if (!this.recording) {
                return null;
            }

            return {
                recording: this.recording,
                duration: Date.now() - this.startTime,
                chunks: this.chunks.length,
                estimatedSize: this.chunks.reduce((sum, chunk) => sum + chunk.size, 0),
                mimeType: this.mediaRecorder?.mimeType,
                state: this.mediaRecorder?.state
            };
        }

        /**
         * Détruire le module
         */
        destroy() {
            this.cleanup();
            this.controller = null;
            window.RMCULogger.debug('Video capture module destroyed');
        }
    }

    // Exposer globalement
    window.CaptureVideo = CaptureVideo;

})(window);