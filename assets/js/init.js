/**
 * RMCU Initialization JavaScript
 * Initialise tous les modules et composants
 */
(function(window, document) {
    'use strict';

    // Namespace RMCU
    window.RMCU = window.RMCU || {};

    /**
     * Classe d'initialisation principale
     */
    class RMCUInit {
        constructor() {
            this.modules = [];
            this.ready = false;
            this.startTime = performance.now();
            
            // Vérifier les dépendances et initialiser
            this.checkDependencies();
        }

        /**
         * Vérifier les dépendances
         */
        checkDependencies() {
            const required = {
                'RMCULogger': window.RMCULogger,
                'RMCUConfig': window.RMCUConfig,
                'RMCUApiClient': window.RMCUApiClient
            };

            const missing = [];
            for (const [name, obj] of Object.entries(required)) {
                if (!obj) {
                    missing.push(name);
                }
            }

            if (missing.length > 0) {
                console.error('[RMCU] Missing dependencies:', missing);
                // Réessayer après un délai
                setTimeout(() => this.checkDependencies(), 100);
                return;
            }

            // Toutes les dépendances sont présentes
            this.init();
        }

        /**
         * Initialisation principale
         */
        async init() {
            try {
                console.log('[RMCU] Starting initialization...');

                // 1. Configuration
                await this.initConfig();

                // 2. Détection de l'environnement
                this.detectEnvironment();

                // 3. Initialisation des modules selon le contexte
                await this.initModules();

                // 4. Événements globaux
                this.setupGlobalEvents();

                // 5. Interface utilisateur
                this.initUI();

                // 6. WebSocket si activé
                if (window.RMCUConfig.get('realtime.enabled')) {
                    this.initWebSocket();
                }

                // Marquer comme prêt
                this.ready = true;
                const loadTime = performance.now() - this.startTime;
                
                window.RMCULogger.info(`RMCU initialized in ${loadTime.toFixed(2)}ms`);
                
                // Déclencher l'événement ready
                this.triggerReady();

            } catch (error) {
                console.error('[RMCU] Initialization failed:', error);
                window.RMCULogger?.error('Initialization failed', error);
            }
        }

        /**
         * Initialiser la configuration
         */
        async initConfig() {
            // Charger la configuration depuis le serveur si nécessaire
            if (window.RMCUConfig.get('loadFromServer')) {
                try {
                    const settings = await window.RMCUApiClient.settings.get();
                    if (settings.success) {
                        Object.keys(settings.data).forEach(key => {
                            window.RMCUConfig.set(key, settings.data[key]);
                        });
                    }
                } catch (error) {
                    window.RMCULogger.warning('Failed to load server config', error);
                }
            }
        }

        /**
         * Détecter l'environnement
         */
        detectEnvironment() {
            const env = {
                isAdmin: document.body.classList.contains('wp-admin'),
                isCustomizer: document.body.classList.contains('wp-customizer'),
                isGutenberg: !!window.wp?.blocks,
                isFrontend: !document.body.classList.contains('wp-admin'),
                isMobile: /iPhone|iPad|iPod|Android/i.test(navigator.userAgent),
                screen: {
                    width: window.innerWidth,
                    height: window.innerHeight
                }
            };

            window.RMCU.environment = env;
            window.RMCULogger.debug('Environment detected', env);
        }

        /**
         * Initialiser les modules
         */
        async initModules() {
            const modulesToLoad = [];

            // Modules admin uniquement
            if (window.RMCU.environment.isAdmin) {
                if (window.RMCUController) {
                    modulesToLoad.push({
                        name: 'Controller',
                        init: () => {
                            window.rmcuController = window.rmcuController || new window.RMCUController();
                        }
                    });
                }

                if (window.RMCU.environment.isGutenberg) {
                    modulesToLoad.push({
                        name: 'Gutenberg Integration',
                        init: () => this.initGutenberg()
                    });
                }
            }

            // Modules frontend
            if (window.RMCU.environment.isFrontend) {
                modulesToLoad.push({
                    name: 'Public Features',
                    init: () => this.initPublicFeatures()
                });
            }

            // Charger les modules
            for (const module of modulesToLoad) {
                try {
                    await module.init();
                    this.modules.push(module.name);
                    window.RMCULogger.debug(`Module loaded: ${module.name}`);
                } catch (error) {
                    window.RMCULogger.error(`Failed to load module: ${module.name}`, error);
                }
            }
        }

        /**
         * Initialiser l'intégration Gutenberg
         */
        initGutenberg() {
            if (!window.wp?.plugins) return;

            // Enregistrer le plugin Gutenberg
            window.wp.plugins.registerPlugin('rmcu-gutenberg', {
                render: () => {
                    // Le composant React sera ajouté ici
                    window.RMCULogger.debug('Gutenberg plugin registered');
                }
            });

            // Ajouter des formats personnalisés
            if (window.wp.richText) {
                window.wp.richText.registerFormatType('rmcu/highlight', {
                    title: 'RMCU Highlight',
                    tagName: 'mark',
                    className: 'rmcu-highlight'
                });
            }

            // Ajouter des blocs personnalisés si nécessaire
            if (window.wp.blocks && window.RMCU.blocks) {
                Object.values(window.RMCU.blocks).forEach(block => {
                    window.wp.blocks.registerBlockType(block.name, block);
                });
            }
        }

        /**
         * Initialiser les fonctionnalités publiques
         */
        initPublicFeatures() {
            // Widget de capture public
            if (window.RMCUConfig.get('public.captureWidget')) {
                this.initCaptureWidget();
            }

            // Analytics
            if (window.RMCUConfig.get('analytics.enabled')) {
                this.initAnalytics();
            }
        }

        /**
         * Initialiser le widget de capture
         */
        initCaptureWidget() {
            const widget = document.createElement('div');
            widget.id = 'rmcu-capture-widget';
            widget.className = 'rmcu-widget';
            widget.innerHTML = `
                <button class="rmcu-widget-toggle">
                    <span class="rmcu-widget-icon">📸</span>
                </button>
                <div class="rmcu-widget-panel">
                    <h3>Quick Capture</h3>
                    <button data-rmcu-action="capture-screenshot">Screenshot</button>
                    <button data-rmcu-action="report-issue">Report Issue</button>
                    <button data-rmcu-action="send-feedback">Send Feedback</button>
                </div>
            `;

            document.body.appendChild(widget);

            // Événements du widget
            widget.querySelector('.rmcu-widget-toggle').addEventListener('click', () => {
                widget.classList.toggle('rmcu-widget-open');
            });
        }

        /**
         * Initialiser l'analytics
         */
        initAnalytics() {
            // Tracker les pages vues
            window.RMCUApiClient.analytics.track('pageview', {
                url: window.location.href,
                title: document.title,
                referrer: document.referrer
            });

            // Tracker les clics
            document.addEventListener('click', (e) => {
                if (e.target.matches('a, button, [data-track]')) {
                    window.RMCUApiClient.analytics.track('click', {
                        element: e.target.tagName,
                        text: e.target.textContent,
                        href: e.target.href
                    });
                }
            });
        }

        /**
         * Configurer les événements globaux
         */
        setupGlobalEvents() {
            // Gestion des erreurs
            window.addEventListener('error', (e) => {
                window.RMCULogger.error('Global error', {
                    message: e.message,
                    filename: e.filename,
                    lineno: e.lineno,
                    colno: e.colno
                });
            });

            window.addEventListener('unhandledrejection', (e) => {
                window.RMCULogger.error('Unhandled promise rejection', e.reason);
            });

            // Visibilité de la page
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    window.RMCULogger.debug('Page hidden');
                    this.onPageHidden();
                } else {
                    window.RMCULogger.debug('Page visible');
                    this.onPageVisible();
                }
            });

            // Avant de quitter
            window.addEventListener('beforeunload', (e) => {
                if (this.hasUnsavedChanges()) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                }
            });

            // Changements de réseau
            if ('connection' in navigator) {
                navigator.connection.addEventListener('change', () => {
                    window.RMCULogger.info('Network change', {
                        effectiveType: navigator.connection.effectiveType,
                        downlink: navigator.connection.downlink
                    });
                });
            }
        }

        /**
         * Initialiser l'interface utilisateur
         */
        initUI() {
            // Ajouter les styles CSS dynamiques
            this.injectStyles();

            // Tooltips
            this.initTooltips();

            // Notifications
            this.initNotifications();

            // Modals
            this.initModals();
        }

        /**
         * Injecter les styles CSS
         */
        injectStyles() {
            const style = document.createElement('style');
            style.id = 'rmcu-dynamic-styles';
            style.textContent = `
                .rmcu-loading {
                    opacity: 0.5;
                    pointer-events: none;
                }
                
                .rmcu-hidden {
                    display: none !important;
                }
                
                .rmcu-highlight {
                    background-color: yellow;
                    padding: 2px;
                }
                
                @keyframes rmcu-pulse {
                    0% { opacity: 1; }
                    50% { opacity: 0.5; }
                    100% { opacity: 1; }
                }
                
                .rmcu-recording .rmcu-recording-dot {
                    display: inline-block;
                    width: 10px;
                    height: 10px;
                    background: red;
                    border-radius: 50%;
                    animation: rmcu-pulse 1s infinite;
                }
            `;
            document.head.appendChild(style);
        }

        /**
         * Initialiser les tooltips
         */
        initTooltips() {
            document.addEventListener('mouseover', (e) => {
                if (e.target.matches('[data-rmcu-tooltip]')) {
                    this.showTooltip(e.target, e.target.dataset.rmcuTooltip);
                }
            });

            document.addEventListener('mouseout', (e) => {
                if (e.target.matches('[data-rmcu-tooltip]')) {
                    this.hideTooltip();
                }
            });
        }

        /**
         * Afficher un tooltip
         */
        showTooltip(element, text) {
            const tooltip = document.createElement('div');
            tooltip.className = 'rmcu-tooltip';
            tooltip.textContent = text;
            
            const rect = element.getBoundingClientRect();
            tooltip.style.cssText = `
                position: fixed;
                top: ${rect.bottom + 5}px;
                left: ${rect.left}px;
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 5px 10px;
                border-radius: 3px;
                font-size: 12px;
                z-index: 10000;
            `;
            
            document.body.appendChild(tooltip);
            this.currentTooltip = tooltip;
        }

        /**
         * Cacher le tooltip
         */
        hideTooltip() {
            if (this.currentTooltip) {
                this.currentTooltip.remove();
                this.currentTooltip = null;
            }
        }

        /**
         * Initialiser les notifications
         */
        initNotifications() {
            if (!document.getElementById('rmcu-notifications')) {
                const container = document.createElement('div');
                container.id = 'rmcu-notifications';
                container.className = 'rmcu-notifications';
                document.body.appendChild(container);
            }
        }

        /**
         * Initialiser les modals
         */
        initModals() {
            document.addEventListener('click', (e) => {
                if (e.target.matches('[data-rmcu-modal]')) {
                    e.preventDefault();
                    this.openModal(e.target.dataset.rmcuModal);
                }
                
                if (e.target.matches('.rmcu-modal-close, .rmcu-modal-overlay')) {
                    this.closeModal();
                }
            });

            // Fermer avec ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.currentModal) {
                    this.closeModal();
                }
            });
        }

        /**
         * Ouvrir une modal
         */
        openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('rmcu-modal-open');
                this.currentModal = modal;
            }
        }

        /**
         * Fermer la modal
         */
        closeModal() {
            if (this.currentModal) {
                this.currentModal.classList.remove('rmcu-modal-open');
                this.currentModal = null;
            }
        }

        /**
         * Initialiser WebSocket
         */
        initWebSocket() {
            try {
                window.RMCUApiClient.connectWebSocket();
                window.RMCULogger.debug('WebSocket initialized');
            } catch (error) {
                window.RMCULogger.warning('WebSocket initialization failed', error);
            }
        }

        /**
         * Page cachée
         */
        onPageHidden() {
            // Sauvegarder l'état si nécessaire
            if (this.hasUnsavedChanges()) {
                this.saveState();
            }
        }

        /**
         * Page visible
         */
        onPageVisible() {
            // Rafraîchir les données si nécessaire
            if (window.RMCUConfig.get('autoRefresh')) {
                this.refreshData();
            }
        }

        /**
         * Vérifier les changements non sauvegardés
         */
        hasUnsavedChanges() {
            return window.rmcuController?.state?.hasUnsavedChanges || false;
        }

        /**
         * Sauvegarder l'état
         */
        saveState() {
            const state = {
                config: window.RMCUConfig.config,
                timestamp: Date.now()
            };
            
            try {
                localStorage.setItem('rmcu_state', JSON.stringify(state));
            } catch (error) {
                window.RMCULogger.warning('Failed to save state', error);
            }
        }

        /**
         * Rafraîchir les données
         */
        async refreshData() {
            // Implémenter selon les besoins
            window.RMCULogger.debug('Refreshing data...');
        }

        /**
         * Déclencher l'événement ready
         */
        triggerReady() {
            // Événement personnalisé
            const event = new CustomEvent('rmcu:ready', {
                detail: {
                    modules: this.modules,
                    environment: window.RMCU.environment,
                    loadTime: performance.now() - this.startTime
                }
            });
            document.dispatchEvent(event);

            // Callback jQuery si disponible
            if (window.jQuery) {
                window.jQuery(document).trigger('rmcu:ready');
            }
        }

        /**
         * API publique
         */
        static whenReady(callback) {
            if (window.RMCU.init && window.RMCU.init.ready) {
                callback();
            } else {
                document.addEventListener('rmcu:ready', callback);
            }
        }

        /**
         * Obtenir l'instance
         */
        static getInstance() {
            return window.RMCU.init;
        }
    }

    // Initialiser automatiquement
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.RMCU.init = new RMCUInit();
        });
    } else {
        window.RMCU.init = new RMCUInit();
    }

    // Exposer l'API publique
    window.RMCU.whenReady = RMCUInit.whenReady;
    window.RMCU.getInstance = RMCUInit.getInstance;

})(window, document);