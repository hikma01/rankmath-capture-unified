/**
 * RMCU Main Controller JavaScript
 * Contrôleur principal de l'application
 */
(function(window, document) {
    'use strict';

    class RMCUController {
        constructor() {
            this.modules = new Map();
            this.events = new Map();
            this.state = {
                initialized: false,
                capturing: false,
                processing: false,
                currentCapture: null
            };
            
            // Attendre que le DOM soit prêt
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.init());
            } else {
                this.init();
            }
        }

        /**
         * Initialisation
         */
        async init() {
            try {
                window.RMCULogger.info('Initializing RMCU Controller');
                
                // Charger les modules
                await this.loadModules();
                
                // Initialiser les événements
                this.setupEvents();
                
                // Initialiser l'interface
                this.setupUI();
                
                // Vérifier les permissions
                await this.checkPermissions();
                
                this.state.initialized = true;
                this.emit('initialized');
                
                window.RMCULogger.info('RMCU Controller initialized successfully');
            } catch (error) {
                window.RMCULogger.error('Failed to initialize RMCU Controller', error);
                this.handleError(error);
            }
        }

        /**
         * Charger les modules
         */
        async loadModules() {
            const modules = [
                { name: 'parser', class: window.RMCUParser },
                { name: 'scanner', class: window.RMCURankMathScanner },
                { name: 'video', class: window.CaptureVideo },
                { name: 'audio', class: window.CaptureAudio },
                { name: 'screen', class: window.CaptureScreen }
            ];

            for (const module of modules) {
                if (module.class) {
                    try {
                        const instance = new module.class(this);
                        this.modules.set(module.name, instance);
                        window.RMCULogger.debug(`Module loaded: ${module.name}`);
                    } catch (error) {
                        window.RMCULogger.warning(`Failed to load module: ${module.name}`, error);
                    }
                }
            }
        }

        /**
         * Configurer les événements
         */
        setupEvents() {
            // Événements globaux
            window.addEventListener('error', (e) => this.handleError(e.error));
            window.addEventListener('unhandledrejection', (e) => this.handleError(e.reason));
            
            // Événements de capture
            document.addEventListener('click', (e) => {
                if (e.target.matches('[data-rmcu-action]')) {
                    e.preventDefault();
                    this.handleAction(e.target.dataset.rmcuAction, e.target);
                }
            });
            
            // Raccourcis clavier
            document.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.shiftKey) {
                    switch(e.key) {
                        case 'S':
                            e.preventDefault();
                            this.captureScreenshot();
                            break;
                        case 'R':
                            e.preventDefault();
                            this.toggleRecording();
                            break;
                    }
                }
            });
        }

        /**
         * Configurer l'interface
         */
        setupUI() {
            // Créer la barre d'outils
            this.createToolbar();
            
            // Créer les notifications
            this.createNotificationContainer();
            
            // Mettre à jour l'état de l'interface
            this.updateUI();
        }

        /**
         * Créer la barre d'outils
         */
        createToolbar() {
            const existing = document.getElementById('rmcu-toolbar');
            if (existing) return;

            const toolbar = document.createElement('div');
            toolbar.id = 'rmcu-toolbar';
            toolbar.className = 'rmcu-toolbar';
            toolbar.innerHTML = `
                <div class="rmcu-toolbar-inner">
                    <button data-rmcu-action="capture-screenshot" class="rmcu-btn">
                        📷 Screenshot
                    </button>
                    <button data-rmcu-action="start-recording" class="rmcu-btn">
                        🎥 Record
                    </button>
                    <button data-rmcu-action="capture-audio" class="rmcu-btn">
                        🎤 Audio
                    </button>
                    <button data-rmcu-action="scan-content" class="rmcu-btn">
                        🔍 Scan
                    </button>
                    <button data-rmcu-action="open-settings" class="rmcu-btn">
                        ⚙️ Settings
                    </button>
                    <div class="rmcu-status">
                        <span class="rmcu-status-text">Ready</span>
                    </div>
                </div>
            `;
            
            document.body.appendChild(toolbar);
        }

        /**
         * Créer le conteneur de notifications
         */
        createNotificationContainer() {
            const existing = document.getElementById('rmcu-notifications');
            if (existing) return;

            const container = document.createElement('div');
            container.id = 'rmcu-notifications';
            container.className = 'rmcu-notifications';
            document.body.appendChild(container);
        }

        /**
         * Gérer une action
         */
        async handleAction(action, element) {
            window.RMCULogger.debug(`Handling action: ${action}`);
            
            switch(action) {
                case 'capture-screenshot':
                    await this.captureScreenshot();
                    break;
                case 'start-recording':
                    await this.startRecording();
                    break;
                case 'stop-recording':
                    await this.stopRecording();
                    break;
                case 'capture-audio':
                    await this.captureAudio();
                    break;
                case 'scan-content':
                    await this.scanContent();
                    break;
                case 'open-settings':
                    this.openSettings();
                    break;
                default:
                    window.RMCULogger.warning(`Unknown action: ${action}`);
            }
        }

        /**
         * Capturer une screenshot
         */
        async captureScreenshot() {
            try {
                this.state.capturing = true;
                this.updateUI();
                
                const screen = this.modules.get('screen');
                if (!screen) throw new Error('Screen module not available');
                
                const result = await screen.captureScreenshot();
                await this.saveCapture(result);
                
                this.notify('Screenshot captured successfully', 'success');
            } catch (error) {
                window.RMCULogger.error('Screenshot capture failed', error);
                this.notify('Failed to capture screenshot', 'error');
            } finally {
                this.state.capturing = false;
                this.updateUI();
            }
        }

        /**
         * Démarrer l'enregistrement
         */
        async startRecording() {
            try {
                this.state.capturing = true;
                this.updateUI();
                
                const video = this.modules.get('video');
                if (!video) throw new Error('Video module not available');
                
                await video.startRecording();
                this.state.currentCapture = { type: 'video', module: video };
                
                // Changer le bouton
                const btn = document.querySelector('[data-rmcu-action="start-recording"]');
                if (btn) {
                    btn.dataset.rmcuAction = 'stop-recording';
                    btn.textContent = '⏹️ Stop';
                    btn.classList.add('recording');
                }
                
                this.notify('Recording started', 'success');
            } catch (error) {
                window.RMCULogger.error('Failed to start recording', error);
                this.notify('Failed to start recording', 'error');
                this.state.capturing = false;
                this.updateUI();
            }
        }

        /**
         * Arrêter l'enregistrement
         */
        async stopRecording() {
            try {
                if (!this.state.currentCapture) return;
                
                const result = await this.state.currentCapture.module.stopRecording();
                await this.saveCapture(result);
                
                // Réinitialiser le bouton
                const btn = document.querySelector('[data-rmcu-action="stop-recording"]');
                if (btn) {
                    btn.dataset.rmcuAction = 'start-recording';
                    btn.textContent = '🎥 Record';
                    btn.classList.remove('recording');
                }
                
                this.notify('Recording saved', 'success');
            } catch (error) {
                window.RMCULogger.error('Failed to stop recording', error);
                this.notify('Failed to save recording', 'error');
            } finally {
                this.state.capturing = false;
                this.state.currentCapture = null;
                this.updateUI();
            }
        }

        /**
         * Capturer l'audio
         */
        async captureAudio() {
            try {
                this.state.capturing = true;
                this.updateUI();
                
                const audio = this.modules.get('audio');
                if (!audio) throw new Error('Audio module not available');
                
                const result = await audio.capture();
                await this.saveCapture(result);
                
                this.notify('Audio captured successfully', 'success');
            } catch (error) {
                window.RMCULogger.error('Audio capture failed', error);
                this.notify('Failed to capture audio', 'error');
            } finally {
                this.state.capturing = false;
                this.updateUI();
            }
        }

        /**
         * Scanner le contenu
         */
        async scanContent() {
            try {
                this.state.processing = true;
                this.updateUI();
                
                const scanner = this.modules.get('scanner');
                const parser = this.modules.get('parser');
                
                if (!scanner || !parser) {
                    throw new Error('Scanner or parser module not available');
                }
                
                // Parser le contenu WordPress
                const content = await parser.parseContent();
                
                // Scanner avec RankMath
                const results = await scanner.scan(content);
                
                // Afficher les résultats
                this.displayScanResults(results);
                
                this.notify('Content scanned successfully', 'success');
            } catch (error) {
                window.RMCULogger.error('Content scan failed', error);
                this.notify('Failed to scan content', 'error');
            } finally {
                this.state.processing = false;
                this.updateUI();
            }
        }

        /**
         * Sauvegarder une capture
         */
        async saveCapture(captureData) {
            const formData = new FormData();
            formData.append('action', 'rmcu_save_capture');
            formData.append('nonce', window.RMCUConfig.get('nonce'));
            formData.append('capture_data', JSON.stringify(captureData));
            
            const response = await fetch(window.RMCUConfig.get('ajax_url'), {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.data?.message || 'Save failed');
            }
            
            return result.data;
        }

        /**
         * Afficher les résultats du scan
         */
        displayScanResults(results) {
            const modal = document.createElement('div');
            modal.className = 'rmcu-modal';
            modal.innerHTML = `
                <div class="rmcu-modal-content">
                    <div class="rmcu-modal-header">
                        <h2>Scan Results</h2>
                        <button class="rmcu-modal-close">&times;</button>
                    </div>
                    <div class="rmcu-modal-body">
                        <div class="rmcu-scan-score">
                            Score: ${results.score}/100
                        </div>
                        <div class="rmcu-scan-details">
                            ${this.formatScanResults(results)}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            modal.querySelector('.rmcu-modal-close').addEventListener('click', () => {
                modal.remove();
            });
        }

        /**
         * Formater les résultats du scan
         */
        formatScanResults(results) {
            let html = '';
            
            if (results.issues && results.issues.length > 0) {
                html += '<h3>Issues Found:</h3><ul>';
                results.issues.forEach(issue => {
                    html += `<li class="rmcu-issue-${issue.severity}">
                        <strong>${issue.title}</strong>: ${issue.description}
                    </li>`;
                });
                html += '</ul>';
            }
            
            if (results.suggestions && results.suggestions.length > 0) {
                html += '<h3>Suggestions:</h3><ul>';
                results.suggestions.forEach(suggestion => {
                    html += `<li>${suggestion}</li>`;
                });
                html += '</ul>';
            }
            
            return html;
        }

        /**
         * Ouvrir les paramètres
         */
        openSettings() {
            const url = window.RMCUConfig.get('settings_url', '/wp-admin/admin.php?page=rmcu-settings');
            window.open(url, '_blank');
        }

        /**
         * Vérifier les permissions
         */
        async checkPermissions() {
            const permissions = ['camera', 'microphone'];
            
            for (const permission of permissions) {
                try {
                    const result = await navigator.permissions.query({ name: permission });
                    window.RMCULogger.debug(`Permission ${permission}: ${result.state}`);
                    
                    result.addEventListener('change', () => {
                        window.RMCULogger.debug(`Permission ${permission} changed to: ${result.state}`);
                        this.emit('permission-change', { permission, state: result.state });
                    });
                } catch (error) {
                    window.RMCULogger.warning(`Cannot query permission ${permission}`, error);
                }
            }
        }

        /**
         * Mettre à jour l'interface
         */
        updateUI() {
            const status = document.querySelector('.rmcu-status-text');
            if (status) {
                if (this.state.capturing) {
                    status.textContent = 'Capturing...';
                    status.className = 'rmcu-status-text capturing';
                } else if (this.state.processing) {
                    status.textContent = 'Processing...';
                    status.className = 'rmcu-status-text processing';
                } else {
                    status.textContent = 'Ready';
                    status.className = 'rmcu-status-text ready';
                }
            }
        }

        /**
         * Afficher une notification
         */
        notify(message, type = 'info') {
            const container = document.getElementById('rmcu-notifications');
            if (!container) return;
            
            const notification = document.createElement('div');
            notification.className = `rmcu-notification rmcu-notification-${type}`;
            notification.textContent = message;
            
            container.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('fade-out');
                setTimeout(() => notification.remove(), 300);
            }, window.RMCUConfig.get('ui.notifications.duration', 5000));
        }

        /**
         * Gérer les erreurs
         */
        handleError(error) {
            window.RMCULogger.error('Unhandled error', error);
            this.notify(error.message || 'An error occurred', 'error');
        }

        /**
         * Émettre un événement
         */
        emit(event, data = null) {
            if (this.events.has(event)) {
                this.events.get(event).forEach(callback => {
                    try {
                        callback(data);
                    } catch (error) {
                        window.RMCULogger.error(`Event handler error for ${event}`, error);
                    }
                });
            }
        }

        /**
         * Écouter un événement
         */
        on(event, callback) {
            if (!this.events.has(event)) {
                this.events.set(event, []);
            }
            this.events.get(event).push(callback);
        }

        /**
         * Retirer un écouteur d'événement
         */
        off(event, callback) {
            if (this.events.has(event)) {
                const callbacks = this.events.get(event);
                const index = callbacks.indexOf(callback);
                if (index > -1) {
                    callbacks.splice(index, 1);
                }
            }
        }

        /**
         * Obtenir un module
         */
        getModule(name) {
            return this.modules.get(name);
        }

        /**
         * Détruire le contrôleur
         */
        destroy() {
            // Nettoyer les modules
            this.modules.forEach(module => {
                if (typeof module.destroy === 'function') {
                    module.destroy();
                }
            });
            this.modules.clear();
            
            // Nettoyer les événements
            this.events.clear();
            
            // Retirer l'interface
            const toolbar = document.getElementById('rmcu-toolbar');
            if (toolbar) toolbar.remove();
            
            const notifications = document.getElementById('rmcu-notifications');
            if (notifications) notifications.remove();
            
            window.RMCULogger.info('RMCU Controller destroyed');
        }
    }

    // Exposer globalement et initialiser
    window.RMCUController = RMCUController;
    window.rmcuController = new RMCUController();

})(window, document);