/**
 * RMCU Configuration Module
 * Configuration centralisée pour le plugin RankMath Capture Unified
 * 
 * @module rmcu-config
 * @version 2.0.0
 */

(function(window) {
    'use strict';

    /**
     * Configuration principale du plugin
     */
    const RMCUConfig = {
        
        // Version du plugin
        version: '2.0.0',
        
        // Mode debug (récupéré depuis WordPress)
        debug: window.rmcuData?.debug || false,
        
        // Configuration API
        api: {
            ajaxUrl: window.rmcuData?.ajaxUrl || '/wp-admin/admin-ajax.php',
            restUrl: window.rmcuData?.restUrl || '/wp-json/rmcu/v1/',
            nonce: window.rmcuData?.nonce || '',
            timeout: 30000,
            retryCount: 3,
            retryDelay: 1000 // ms
        },
        
        // Sélecteurs DOM pour RankMath
        selectors: {
            rankmath: {
                // Versions multiples de RankMath supportées
                v1: {
                    container: '.rank-math-analyzer-wrap',
                    score: '.rank-math-total-score',
                    scoreValue: '.rank-math-total',
                    keyword: '.rank-math-focus-keyword input',
                    tests: '.rank-math-test',
                    testTitle: '.test-title',
                    testStatus: '.test-status',
                    errorIcon: '.dashicons-warning',
                    successIcon: '.dashicons-yes-alt',
                    warningIcon: '.dashicons-info',
                    accordion: '.rank-math-accordion',
                    panel: '.rank-math-accordion-content'
                },
                v2: {
                    // Pour RankMath 2.x
                    container: '[data-rm-analyzer]',
                    score: '[data-rm-score]',
                    scoreValue: '[data-rm-score-value]',
                    keyword: '[data-rm-keyword-input]',
                    tests: '[data-rm-test]',
                    testTitle: '[data-rm-test-title]',
                    testStatus: '[data-rm-test-status]',
                    errorIcon: '[data-rm-icon="error"]',
                    successIcon: '[data-rm-icon="success"]',
                    warningIcon: '[data-rm-icon="warning"]',
                    accordion: '[data-rm-accordion]',
                    panel: '[data-rm-panel]'
                },
                // Version par défaut
                current: null
            },
            
            // Sélecteurs pour l'éditeur WordPress
            wordpress: {
                // Gutenberg (Block Editor)
                gutenberg: {
                    container: '.edit-post-visual-editor',
                    title: '.editor-post-title__input',
                    content: '.block-editor-block-list__layout',
                    block: '.wp-block',
                    paragraph: '.wp-block-paragraph',
                    heading: '[data-type="core/heading"]',
                    image: '[data-type="core/image"]',
                    list: '[data-type="core/list"]',
                    quote: '[data-type="core/quote"]',
                    code: '[data-type="core/code"]',
                    table: '[data-type="core/table"]',
                    saveButton: '.editor-post-publish-button',
                    updateButton: '.editor-post-publish-button'
                },
                
                // Classic Editor
                classic: {
                    container: '#post-body-content',
                    title: '#title',
                    content: '#content_ifr', // iframe du TinyMCE
                    tinymce: '#content',
                    saveButton: '#publish',
                    updateButton: '#publish'
                },
                
                // Détection automatique de l'éditeur
                current: null
            }
        },
        
        // Configuration des extracteurs
        extractors: {
            // Délai avant extraction (ms)
            delay: 500,
            
            // Timeout pour attendre le chargement du DOM
            domTimeout: 10000,
            
            // Intervalle de vérification (ms)
            checkInterval: 250,
            
            // Nombre max de vérifications
            maxChecks: 40
        },
        
        // Configuration du cache
        cache: {
            enabled: true,
            duration: 3600, // secondes
            prefix: 'rmcu_cache_',
            storage: 'sessionStorage' // ou 'localStorage'
        },
        
        // Configuration des logs
        logging: {
            enabled: true,
            level: 'info', // 'debug', 'info', 'warn', 'error'
            prefix: '[RMCU]',
            timestamp: true,
            colors: {
                debug: '#888',
                info: '#2196F3',
                warn: '#FF9800',
                error: '#F44336',
                success: '#4CAF50'
            }
        },
        
        // Messages i18n
        i18n: window.rmcuData?.i18n || {
            extracting: 'Extracting data...',
            success: 'Data extracted successfully',
            error: 'Error during extraction',
            retry: 'Retrying...',
            noRankMath: 'RankMath not detected',
            noContent: 'No content detected',
            processing: 'Processing...',
            complete: 'Complete',
            failed: 'Failed'
        },
        
        // Paramètres utilisateur (depuis WordPress)
        settings: window.rmcuData?.settings || {
            targetScore: 90,
            maxIterations: 5,
            autoOptimize: false
        },
        
        // Détection des versions
        detection: {
            /**
             * Détecter la version de RankMath
             */
            detectRankMathVersion() {
                const selectors = this.parent.selectors.rankmath;
                
                // Tester V2 d'abord (plus récent)
                if (document.querySelector(selectors.v2.container)) {
                    console.log('RankMath v2.x detected');
                    return 'v2';
                }
                
                // Tester V1
                if (document.querySelector(selectors.v1.container)) {
                    console.log('RankMath v1.x detected');
                    return 'v1';
                }
                
                console.warn('RankMath version not detected');
                return null;
            },
            
            /**
             * Détecter le type d'éditeur WordPress
             */
            detectEditorType() {
                const selectors = this.parent.selectors.wordpress;
                
                // Vérifier Gutenberg
                if (document.querySelector(selectors.gutenberg.container)) {
                    console.log('Gutenberg editor detected');
                    return 'gutenberg';
                }
                
                // Vérifier Classic Editor
                if (document.querySelector(selectors.classic.container)) {
                    console.log('Classic editor detected');
                    return 'classic';
                }
                
                console.warn('WordPress editor not detected');
                return null;
            },
            
            parent: null
        },
        
        /**
         * Initialiser la configuration
         */
        init() {
            // Lier le contexte parent pour la détection
            this.detection.parent = this;
            
            // Détecter les versions
            this.selectors.rankmath.current = this.detection.detectRankMathVersion();
            this.selectors.wordpress.current = this.detection.detectEditorType();
            
            // Appliquer les sélecteurs courants
            this.applyCurrentSelectors();
            
            // Logger la configuration si debug
            if (this.debug) {
                console.log('%c[RMCU] Configuration initialized:', 'color: #4CAF50; font-weight: bold');
                console.log({
                    rankMathVersion: this.selectors.rankmath.current,
                    editorType: this.selectors.wordpress.current,
                    settings: this.settings
                });
            }
            
            return this;
        },
        
        /**
         * Appliquer les sélecteurs de la version courante
         */
        applyCurrentSelectors() {
            // RankMath
            if (this.selectors.rankmath.current) {
                const version = this.selectors.rankmath.current;
                this.selectors.rankmath.active = this.selectors.rankmath[version];
            }
            
            // WordPress
            if (this.selectors.wordpress.current) {
                const editor = this.selectors.wordpress.current;
                this.selectors.wordpress.active = this.selectors.wordpress[editor];
            }
        },
        
        /**
         * Obtenir un sélecteur RankMath actif
         */
        getRankMathSelector(key) {
            if (!this.selectors.rankmath.active) {
                console.warn('[RMCU] No active RankMath selectors');
                return null;
            }
            return this.selectors.rankmath.active[key];
        },
        
        /**
         * Obtenir un sélecteur WordPress actif
         */
        getWordPressSelector(key) {
            if (!this.selectors.wordpress.active) {
                console.warn('[RMCU] No active WordPress selectors');
                return null;
            }
            return this.selectors.wordpress.active[key];
        },
        
        /**
         * Mettre à jour un paramètre
         */
        updateSetting(key, value) {
            if (this.settings.hasOwnProperty(key)) {
                this.settings[key] = value;
                
                // Sauvegarder en session si cache activé
                if (this.cache.enabled) {
                    const storage = window[this.cache.storage];
                    storage.setItem(this.cache.prefix + 'settings', JSON.stringify(this.settings));
                }
                
                return true;
            }
            return false;
        },
        
        /**
         * Obtenir un paramètre
         */
        getSetting(key) {
            return this.settings[key];
        },
        
        /**
         * Vérifier si en mode debug
         */
        isDebug() {
            return this.debug === true;
        },
        
        /**
         * Obtenir la configuration API
         */
        getApiConfig() {
            return this.api;
        },
        
        /**
         * Obtenir la configuration de cache
         */
        getCacheConfig() {
            return this.cache;
        },
        
        /**
         * Réinitialiser la détection (utile après navigation AJAX)
         */
        refresh() {
            this.selectors.rankmath.current = this.detection.detectRankMathVersion();
            this.selectors.wordpress.current = this.detection.detectEditorType();
            this.applyCurrentSelectors();
            
            if (this.debug) {
                console.log('[RMCU] Configuration refreshed');
            }
        }
    };
    
    // Exposer globalement
    window.RMCUConfig = RMCUConfig;
    
    // Auto-initialisation si le DOM est prêt
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => RMCUConfig.init());
    } else {
        RMCUConfig.init();
    }
    
})(window);