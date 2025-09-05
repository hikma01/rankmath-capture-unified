/**
 * RMCU Screen Capture Module  
 * Gère la capture d'écran et l'enregistrement d'écran
 */
(function(window) {
    'use strict';

    class CaptureScreen {
        constructor(controller) {
            this.controller = controller;
            this.mediaRecorder = null;
            this.stream = null;
            this.chunks = [];
            this.recording = false;
            this.startTime = null;
            this.config = window.RMCUConfig.get('capture.screen');
            this.cursorCanvas = null;
        }

        /**
         * Capturer une screenshot
         */
        async captureScreenshot(options = {}) {
            try {
                window.RMCULogger.info('Capturing screenshot');

                // Options de capture
                const captureOptions = {
                    video: {
                        displaySurface: options.displaySurface || this.config.displaySurface,
                        logicalSurface: true,
                        cursor: options.cursor !== false
                    },
                    audio: false
                };

                // Obtenir le stream d'écran temporaire
                const stream = await navigator.mediaDevices.getDisplayMedia(captureOptions);
                
                // Capturer l'image
                const screenshot = await this.captureFrameFromStream(stream);
                
                // Arrêter le stream
                stream.getTracks().forEach(track => track.stop());

                // Annoter si demandé
                if (options.annotate) {
                    screenshot.blob = await this.annotateScreenshot(screenshot.blob, options.annotations);
                }

                window.RMCULogger.info('Screenshot captured', {
                    width: screenshot.width,
                    height: screenshot.height,
                    size: screenshot.blob.size
                });

                return screenshot;

            } catch (error) {
                window.RMCULogger.error('Failed to capture screenshot', error);
                throw error;
            }
        }

        /**
         * Démarrer l'enregistrement d'écran
         */
        async startRecording(options = {}) {
            if (this.recording) {
                throw new Error('Recording already in progress');
            }

            try {
                window.RMCULogger.info('Starting screen recording');

                // Options de capture
                const captureOptions = {
                    video: {
                        displaySurface: options.displaySurface || this.config.displaySurface,
                        logicalSurface: true,
                        cursor: options.cursor !== false,
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                        frameRate: options.frameRate || this.config.frameRate
                    },
                    audio: options.audio === true
                };

                // Obtenir le stream d'écran
                this.stream = await navigator.mediaDevices.getDisplayMedia(captureOptions);

                // Ajouter l'audio du microphone si demandé
                if (options.microphoneAudio) {
                    const audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    const audioTrack = audioStream.getAudioTracks()[0];
                    this.stream.addTrack(audioTrack);
                }

                // Détecter la fin du partage
                this.stream.getVideoTracks()[0].addEventListener('ended', () => {
                    if (this.recording) {
                        this.stopRecording();
                    }
                });

                // Créer le MediaRecorder
                const mimeType = this.getSupportedMimeType();
                const recorderOptions = {
                    mimeType: mimeType,
                    videoBitsPerSecond: options.bitrate || 5000000
                };

                this.mediaRecorder = new MediaRecorder(this.stream, recorderOptions);
                this.chunks = [];

                // Gérer les événements
                this.setupRecorderEvents();

                // Démarrer l'enregistrement
                this.mediaRecorder.start();
                this.recording = true;
                this.startTime = Date.now();

                // Afficher les contrôles si demandé
                if (options.showControls) {
                    this.showRecordingControls();
                }

                window.RMCULogger.info('Screen recording started');
                return {
                    success: true,
                    stream: this.stream,
                    mimeType: mimeType
                };

            } catch (error) {
                window.RMCULogger.error('Failed to start screen recording', error);
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

            window.RMCULogger.info('Stopping screen recording');

            return new Promise((resolve, reject) => {
                const timeout = setTimeout(() => {
                    reject(new Error('Stop recording timeout'));
                }, 10000);

                this.mediaRecorder.addEventListener('stop', () => {
                    clearTimeout(timeout);

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
                    window.RMCULogger.info('Screen recording stopped', {
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
         * Capturer une frame depuis un stream
         */
        async captureFrameFromStream(stream) {
            const video = document.createElement('video');
            video.srcObject = stream;
            video.autoplay = true;

            return new Promise((resolve, reject) => {
                video.addEventListener('loadedmetadata', () => {
                    // Attendre que la vidéo soit prête
                    setTimeout(() => {
                        const canvas = document.createElement('canvas');
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;

                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(video, 0, 0);

                        canvas.toBlob((blob) => {
                            if (blob) {
                                resolve({
                                    blob: blob,
                                    url: URL.createObjectURL(blob),
                                    width: canvas.width,
                                    height: canvas.height,
                                    timestamp: Date.now()
                                });
                            } else {
                                reject(new Error('Failed to create blob'));
                            }
                        }, 'image/png', this.config.screenshot?.quality || 0.9);
                    }, 100);
                });

                video.addEventListener('error', reject);
            });
        }

        /**
         * Capturer une zone spécifique
         */
        async captureArea(x, y, width, height) {
            const screenshot = await this.captureScreenshot();
            
            // Créer un canvas pour recadrer
            const img = new Image();
            img.src = screenshot.url;

            return new Promise((resolve) => {
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, x, y, width, height, 0, 0, width, height);

                    canvas.toBlob((blob) => {
                        resolve({
                            blob: blob,
                            url: URL.createObjectURL(blob),
                            width: width,
                            height: height,
                            area: { x, y, width, height },
                            timestamp: Date.now()
                        });
                    }, 'image/png');
                };
            });
        }

        /**
         * Capturer un élément DOM spécifique
         */
        async captureElement(selector) {
            const element = document.querySelector(selector);
            if (!element) {
                throw new Error(`Element not found: ${selector}`);
            }

            const rect = element.getBoundingClientRect();
            const screenshot = await this.captureArea(
                rect.left + window.scrollX,
                rect.top + window.scrollY,
                rect.width,
                rect.height
            );

            screenshot.element = selector;
            return screenshot;
        }

        /**
         * Annoter une screenshot
         */
        async annotateScreenshot(blob, annotations = {}) {
            const img = new Image();
            img.src = URL.createObjectURL(blob);

            return new Promise((resolve) => {
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.width;
                    canvas.height = img.height;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);

                    // Appliquer les annotations
                    if (annotations.highlights) {
                        ctx.strokeStyle = 'red';
                        ctx.lineWidth = 3;
                        annotations.highlights.forEach(rect => {
                            ctx.strokeRect(rect.x, rect.y, rect.width, rect.height);
                        });
                    }

                    if (annotations.arrows) {
                        ctx.strokeStyle = 'blue';
                        ctx.lineWidth = 2;
                        ctx.fillStyle = 'blue';
                        annotations.arrows.forEach(arrow => {
                            this.drawArrow(ctx, arrow.from, arrow.to);
                        });
                    }

                    if (annotations.text) {
                        ctx.font = '16px Arial';
                        ctx.fillStyle = 'red';
                        annotations.text.forEach(text => {
                            ctx.fillText(text.content, text.x, text.y);
                        });
                    }

                    canvas.toBlob((newBlob) => {
                        resolve(newBlob);
                    }, 'image/png');
                };
            });
        }

        /**
         * Dessiner une flèche
         */
        drawArrow(ctx, from, to) {
            const headLength = 10;
            const angle = Math.atan2(to.y - from.y, to.x - from.x);

            ctx.beginPath();
            ctx.moveTo(from.x, from.y);
            ctx.lineTo(to.x, to.y);
            ctx.stroke();

            // Pointe de la flèche
            ctx.beginPath();
            ctx.moveTo(to.x, to.y);
            ctx.lineTo(
                to.x - headLength * Math.cos(angle - Math.PI / 6),
                to.y - headLength * Math.sin(angle - Math.PI / 6)
            );
            ctx.moveTo(to.x, to.y);
            ctx.lineTo(
                to.x - headLength * Math.cos(angle + Math.PI / 6),
                to.y - headLength * Math.sin(angle + Math.PI / 6)
            );
            ctx.stroke();
        }

        /**
         * Afficher les contrôles d'enregistrement
         */
        showRecordingControls() {
            const controls = document.createElement('div');
            controls.id = 'rmcu-screen-recording-controls';
            controls.className = 'rmcu-recording-controls';
            controls.innerHTML = `
                <div class="rmcu-recording-indicator">
                    <span class="rmcu-recording-dot"></span>
                    <span class="rmcu-recording-time">00:00</span>
                </div>
                <button class="rmcu-pause-btn">⏸️ Pause</button>
                <button class="rmcu-stop-btn">⏹️ Stop</button>
                <button class="rmcu-cancel-btn">❌ Cancel</button>
            `;

            Object.assign(controls.style, {
                position: 'fixed',
                top: '20px',
                right: '20px',
                background: 'rgba(0,0,0,0.8)',
                padding: '10px',
                borderRadius: '5px',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                zIndex: '10001',
                color: 'white'
            });

            document.body.appendChild(controls);

            // Timer
            const updateTimer = setInterval(() => {
                if (!this.recording) {
                    clearInterval(updateTimer);
                    controls.remove();
                    return;
                }

                const elapsed = Date.now() - this.startTime;
                const minutes = Math.floor(elapsed / 60000);
                const seconds = Math.floor((elapsed % 60000) / 1000);
                const timeDisplay = controls.querySelector('.rmcu-recording-time');
                if (timeDisplay) {
                    timeDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                }
            }, 1000);

            // Événements des boutons
            controls.querySelector('.rmcu-pause-btn').addEventListener('click', () => {
                if (this.mediaRecorder.state === 'recording') {
                    this.mediaRecorder.pause();
                    controls.querySelector('.rmcu-pause-btn').textContent = '▶️ Resume';
                } else {
                    this.mediaRecorder.resume();
                    controls.querySelector('.rmcu-pause-btn').textContent = '⏸️ Pause';
                }
            });

            controls.querySelector('.rmcu-stop-btn').addEventListener('click', () => {
                this.stopRecording();
            });

            controls.querySelector('.rmcu-cancel-btn').addEventListener('click', () => {
                this.cancelRecording();
            });
        }

        /**
         * Annuler l'enregistrement
         */
        cancelRecording() {
            this.chunks = [];
            this.cleanup();
            this.recording = false;
            window.RMCULogger.info('Recording cancelled');
            this.controller.emit('recording-cancelled');
        }

        /**
         * Obtenir le type MIME supporté
         */
        getSupportedMimeType() {
            const types = [
                'video/webm;codecs=vp9',
                'video/webm;codecs=vp8',
                'video/webm',
                'video/mp4'
            ];

            for (const type of types) {
                if (MediaRecorder.isTypeSupported(type)) {
                    window.RMCULogger.debug(`Using screen MIME type: ${type}`);
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
                    window.RMCULogger.debug('Screen data available', {
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
                    type: 'screen',
                    mimeType: this.mediaRecorder.mimeType
                });
            });

            this.mediaRecorder.addEventListener('stop', () => {
                this.controller.emit('recording-stopped', {
                    type: 'screen',
                    duration: Date.now() - this.startTime
                });
            });
        }

        /**
         * Capturer la page entière (avec défilement)
         */
        async captureFullPage() {
            const screenshots = [];
            const viewportHeight = window.innerHeight;
            const pageHeight = document.documentElement.scrollHeight;
            const originalScrollY = window.scrollY;

            // Capturer par sections
            for (let y = 0; y < pageHeight; y += viewportHeight) {
                window.scrollTo(0, y);
                await new Promise(resolve => setTimeout(resolve, 100)); // Attendre le rendu
                
                const screenshot = await this.captureScreenshot();
                screenshots.push({
                    screenshot: screenshot,
                    offset: y
                });
            }

            // Restaurer la position de défilement
            window.scrollTo(0, originalScrollY);

            // Assembler les screenshots
            const fullPageImage = await this.stitchScreenshots(screenshots, pageHeight);
            
            return fullPageImage;
        }

        /**
         * Assembler plusieurs screenshots
         */
        async stitchScreenshots(screenshots, totalHeight) {
            const canvas = document.createElement('canvas');
            canvas.width = screenshots[0].screenshot.width;
            canvas.height = totalHeight;

            const ctx = canvas.getContext('2d');

            for (const { screenshot, offset } of screenshots) {
                const img = new Image();
                img.src = screenshot.url;
                
                await new Promise((resolve) => {
                    img.onload = () => {
                        ctx.drawImage(img, 0, offset);
                        resolve();
                    };
                });
            }

            return new Promise((resolve) => {
                canvas.toBlob((blob) => {
                    resolve({
                        blob: blob,
                        url: URL.createObjectURL(blob),
                        width: canvas.width,
                        height: canvas.height,
                        timestamp: Date.now(),
                        type: 'full-page'
                    });
                }, 'image/png');
            });
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

            // Retirer les contrôles s'ils existent
            const controls = document.getElementById('rmcu-screen-recording-controls');
            if (controls) {
                controls.remove();
            }

            this.chunks = [];
            this.recording = false;
            this.startTime = null;
        }

        /**
         * Détruire le module
         */
        destroy() {
            this.cleanup();
            this.controller = null;
            window.RMCULogger.debug('Screen capture module destroyed');
        }
    }

    // Exposer globalement
    window.CaptureScreen = CaptureScreen;

})(window);