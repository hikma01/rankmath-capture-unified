/**
 * RMCU Audio Capture Module
 * Gère l'enregistrement audio
 */
(function(window) {
    'use strict';

    class CaptureAudio {
        constructor(controller) {
            this.controller = controller;
            this.mediaRecorder = null;
            this.stream = null;
            this.audioContext = null;
            this.analyser = null;
            this.chunks = [];
            this.recording = false;
            this.startTime = null;
            this.config = window.RMCUConfig.get('capture.audio');
            this.visualizer = null;
        }

        /**
         * Démarrer l'enregistrement audio
         */
        async startRecording(options = {}) {
            if (this.recording) {
                throw new Error('Recording already in progress');
            }

            try {
                window.RMCULogger.info('Starting audio recording');

                // Configuration
                const config = {
                    audio: {
                        sampleRate: options.sampleRate || this.config.sampleRate,
                        channelCount: options.channels || this.config.channels,
                        echoCancellation: options.echoCancellation !== false,
                        noiseSuppression: options.noiseSuppression !== false,
                        autoGainControl: options.autoGainControl !== false
                    }
                };

                // Obtenir le stream audio
                this.stream = await navigator.mediaDevices.getUserMedia(config);

                // Créer le contexte audio pour l'analyse
                this.setupAudioContext();

                // Créer le MediaRecorder
                const mimeType = this.getSupportedMimeType();
                const recorderOptions = {
                    mimeType: mimeType,
                    audioBitsPerSecond: options.bitrate || this.config.bitrate
                };

                this.mediaRecorder = new MediaRecorder(this.stream, recorderOptions);
                this.chunks = [];

                // Gérer les événements
                this.setupRecorderEvents();

                // Démarrer l'enregistrement
                this.mediaRecorder.start();
                this.recording = true;
                this.startTime = Date.now();

                // Démarrer la visualisation si activée
                if (options.visualize) {
                    this.startVisualization();
                }

                window.RMCULogger.info('Audio recording started');
                return {
                    success: true,
                    stream: this.stream,
                    mimeType: mimeType
                };

            } catch (error) {
                window.RMCULogger.error('Failed to start audio recording', error);
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

            window.RMCULogger.info('Stopping audio recording');

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
                    window.RMCULogger.info('Audio recording stopped', {
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
         * Capturer un échantillon audio court
         */
        async capture(duration = 5000) {
            await this.startRecording();
            
            return new Promise((resolve) => {
                setTimeout(async () => {
                    const result = await this.stopRecording();
                    resolve(result);
                }, duration);
            });
        }

        /**
         * Configurer le contexte audio
         */
        setupAudioContext() {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            const source = this.audioContext.createMediaStreamSource(this.stream);
            this.analyser = this.audioContext.createAnalyser();
            this.analyser.fftSize = 2048;
            
            source.connect(this.analyser);
            
            window.RMCULogger.debug('Audio context initialized');
        }

        /**
         * Obtenir le type MIME supporté
         */
        getSupportedMimeType() {
            const types = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/ogg;codecs=opus',
                'audio/mp4',
                'audio/wav'
            ];

            for (const type of types) {
                if (MediaRecorder.isTypeSupported(type)) {
                    window.RMCULogger.debug(`Using audio MIME type: ${type}`);
                    return type;
                }
            }

            throw new Error('No supported audio MIME type found');
        }

        /**
         * Configurer les événements du recorder
         */
        setupRecorderEvents() {
            this.mediaRecorder.addEventListener('dataavailable', (e) => {
                if (e.data.size > 0) {
                    this.chunks.push(e.data);
                    window.RMCULogger.debug('Audio data available', {
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
                    type: 'audio',
                    mimeType: this.mediaRecorder.mimeType
                });
            });

            this.mediaRecorder.addEventListener('stop', () => {
                this.controller.emit('recording-stopped', {
                    type: 'audio',
                    duration: Date.now() - this.startTime
                });
            });
        }

        /**
         * Démarrer la visualisation audio
         */
        startVisualization() {
            if (!this.analyser) return;

            // Créer le canvas de visualisation
            const canvas = document.createElement('canvas');
            canvas.id = 'rmcu-audio-visualizer';
            canvas.width = 300;
            canvas.height = 100;
            canvas.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: rgba(0,0,0,0.8);
                border: 2px solid #4CAF50;
                border-radius: 5px;
                z-index: 10000;
            `;
            document.body.appendChild(canvas);

            const ctx = canvas.getContext('2d');
            const bufferLength = this.analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);

            const draw = () => {
                if (!this.recording) {
                    canvas.remove();
                    return;
                }

                requestAnimationFrame(draw);

                this.analyser.getByteFrequencyData(dataArray);

                ctx.fillStyle = 'rgba(0, 0, 0, 0.2)';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                const barWidth = (canvas.width / bufferLength) * 2.5;
                let barHeight;
                let x = 0;

                for (let i = 0; i < bufferLength; i++) {
                    barHeight = (dataArray[i] / 255) * canvas.height;

                    const r = barHeight + (25 * (i / bufferLength));
                    const g = 250 * (i / bufferLength);
                    const b = 50;

                    ctx.fillStyle = `rgb(${r},${g},${b})`;
                    ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);

                    x += barWidth + 1;
                }
            };

            draw();
            this.visualizer = canvas;
            window.RMCULogger.debug('Audio visualization started');
        }

        /**
         * Obtenir le niveau audio actuel
         */
        getAudioLevel() {
            if (!this.analyser) return 0;

            const dataArray = new Uint8Array(this.analyser.frequencyBinCount);
            this.analyser.getByteFrequencyData(dataArray);

            let sum = 0;
            for (let i = 0; i < dataArray.length; i++) {
                sum += dataArray[i];
            }

            return sum / dataArray.length / 255; // Normaliser entre 0 et 1
        }

        /**
         * Obtenir les fréquences audio
         */
        getFrequencyData() {
            if (!this.analyser) return null;

            const dataArray = new Uint8Array(this.analyser.frequencyBinCount);
            this.analyser.getByteFrequencyData(dataArray);

            return {
                raw: dataArray,
                bass: this.getFrequencyRange(dataArray, 0, 250),
                midrange: this.getFrequencyRange(dataArray, 250, 4000),
                treble: this.getFrequencyRange(dataArray, 4000, 20000)
            };
        }

        /**
         * Obtenir une plage de fréquences
         */
        getFrequencyRange(dataArray, minFreq, maxFreq) {
            const sampleRate = this.audioContext.sampleRate;
            const binCount = dataArray.length;
            const freqPerBin = sampleRate / 2 / binCount;

            const minBin = Math.floor(minFreq / freqPerBin);
            const maxBin = Math.floor(maxFreq / freqPerBin);

            let sum = 0;
            let count = 0;

            for (let i = minBin; i <= maxBin && i < binCount; i++) {
                sum += dataArray[i];
                count++;
            }

            return count > 0 ? sum / count / 255 : 0;
        }

        /**
         * Détecter le silence
         */
        detectSilence(threshold = 0.01, duration = 1000) {
            let silenceStart = null;

            const checkSilence = setInterval(() => {
                const level = this.getAudioLevel();

                if (level < threshold) {
                    if (!silenceStart) {
                        silenceStart = Date.now();
                    } else if (Date.now() - silenceStart > duration) {
                        clearInterval(checkSilence);
                        this.controller.emit('silence-detected', {
                            duration: Date.now() - silenceStart
                        });
                    }
                } else {
                    silenceStart = null;
                }

                if (!this.recording) {
                    clearInterval(checkSilence);
                }
            }, 100);
        }

        /**
         * Appliquer un filtre audio
         */
        applyFilter(type, frequency = 1000, q = 1) {
            if (!this.audioContext || !this.stream) return;

            const source = this.audioContext.createMediaStreamSource(this.stream);
            const filter = this.audioContext.createBiquadFilter();

            filter.type = type; // lowpass, highpass, bandpass, etc.
            filter.frequency.value = frequency;
            filter.Q.value = q;

            source.connect(filter);
            filter.connect(this.audioContext.destination);

            window.RMCULogger.debug('Audio filter applied', { type, frequency, q });
        }

        /**
         * Obtenir les périphériques audio disponibles
         */
        async getDevices() {
            const devices = await navigator.mediaDevices.enumerateDevices();
            return devices.filter(d => d.kind === 'audioinput');
        }

        /**
         * Changer de microphone
         */
        async switchMicrophone(deviceId) {
            if (!this.recording) {
                throw new Error('No recording in progress');
            }

            // Arrêter le stream actuel
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
            }

            // Démarrer avec le nouveau microphone
            this.stream = await navigator.mediaDevices.getUserMedia({
                audio: { deviceId: deviceId }
            });

            // Reconfigurer le contexte audio
            this.setupAudioContext();

            window.RMCULogger.info('Microphone switched', { deviceId });
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
                state: this.mediaRecorder?.state,
                audioLevel: this.getAudioLevel()
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

            if (this.audioContext) {
                this.audioContext.close();
                this.audioContext = null;
            }

            if (this.visualizer) {
                this.visualizer.remove();
                this.visualizer = null;
            }

            this.mediaRecorder = null;
            this.analyser = null;
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
            window.RMCULogger.debug('Audio capture module destroyed');
        }
    }

    // Exposer globalement
    window.CaptureAudio = CaptureAudio;

})(window);