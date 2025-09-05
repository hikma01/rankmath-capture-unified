/**
 * RMCU Logger Module
 * Système de logging centralisé pour le plugin RankMath Capture Unified
 * 
 * @module rmcu-logger
 * @version 2.0.0
 */

(function(window) {
    'use strict';

    /**
     * Classe Logger pour gérer les logs de manière centralisée
     */
    class RMCULogger {
        /**
         * Constructeur du logger
         * @param {string} module - Nom du module qui utilise le logger
         * @param {Object} config - Configuration optionnelle
         */
        constructor(module = 'RMCU', config = null) {
            this.module = module;
            this.config = config || window.RMCUConfig?.logging || this.getDefaultConfig();
            this.history = [];
            this.maxHistorySize = 100;
            this.sessionId = this.generateSessionId();
        }

        /**
         * Configuration par défaut
         */
        getDefaultConfig() {
            return {
                enabled: true,
                level: 'info',
                prefix: '[RMCU]',
                timestamp: true,
                colors: {
                    debug: '#888',
                    info: '#2196F3',
                    warn: '#FF9800',
                    error: '#F44336',
                    success: '#4CAF50'
                }
            };
        }

        /**
         * Générer un ID de session unique
         */
        generateSessionId() {
            return 'rmcu_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        /**
         * Niveaux de log avec leurs priorités
         */
        static LEVELS = {
            debug: 0,
            info: 1,
            warn: 2,
            error: 3,
            success: 1
        };

        /**
         * Log de debug
         */
        debug(...args) {
            this.log('debug', ...args);
        }

        /**
         * Log d'information
         */
        info(...args) {
            this.log('info', ...args);
        }

        /**
         * Log d'avertissement
         */
        warn(...args) {
            this.log('warn', ...args);
        }

        /**
         * Log d'erreur
         */
        error(...args) {
            this.log('error', ...args);
        }

        /**
         * Log de succès
         */
        success(...args) {
            this.log('success', ...args);
        }

        /**
         * Méthode principale de logging
         */
        log(level, ...args) {
            // Vérifier si le logging est activé
            if (!this.config.enabled) return;

            // Vérifier le niveau de log
            const currentLevelPriority = RMCULogger.LEVELS[this.config.level] || 0;
            const messageLevelPriority = RMCULogger.LEVELS[level] || 0;
            
            if (messageLevelPriority < currentLevelPriority) {
                return;
            }

            // Construire le message
            const entry = this.buildLogEntry(level, args);
            
            // Ajouter à l'historique
            this.addToHistory(entry);

            // Afficher dans la console
            this.outputToConsole(level, entry);

            // Envoyer au serveur si erreur critique
            if (level === 'error' && this.shouldSendToServer(args)) {
                this.sendToServer(entry);
            }
        }

        /**
         * Construire une entrée de log
         */
        buildLogEntry(level, args) {
            const entry = {
                timestamp: new Date().toISOString(),
                sessionId: this.sessionId,
                module: this.module,
                level: level,
                message: args,
                url: window.location.href,
                userAgent: navigator.userAgent
            };

            // Ajouter le contexte WordPress si disponible
            if (window.rmcuData) {
                entry.context = {
                    postId: window.rmcuData.postId,
                    userId: window.rmcuData.userId,
                    ajaxUrl: window.rmcuData.ajaxUrl
                };
            }

            return entry;
        }

        /**
         * Ajouter à l'historique
         */
        addToHistory(entry) {
            this.history.push(entry);
            
            // Limiter la taille de l'historique
            if (this.history.length > this.maxHistorySize) {
                this.history.shift();
            }

            // Sauvegarder en sessionStorage si disponible
            try {
                if (window.sessionStorage) {
                    sessionStorage.setItem(
                        'rmcu_log_history_' + this.module,
                        JSON.stringify(this.history.slice(-20)) // Garder les 20 derniers
                    );
                }
            } catch (e) {
                // Ignorer les erreurs de stockage
            }
        }

        /**
         * Afficher dans la console
         */
        outputToConsole(level, entry) {
            const prefix = this.buildPrefix(level);
            const color = this.config.colors[level] || '#000';
            const style = `color: ${color}; font-weight: bold;`;

            // Formatter le message pour la console
            const consoleArgs = [
                `%c${prefix}`,
                style,
                ...entry.message
            ];

            // Utiliser la méthode console appropriée
            switch(level) {
                case 'error':
                    console.error(...consoleArgs);
                    break;
                case 'warn':
                    console.warn(...consoleArgs);
                    break;
                case 'debug':
                    console.debug(...consoleArgs);
                    break;
                default:
                    console.log(...consoleArgs);
            }
        }

        /**
         * Construire le préfixe du message
         */
        buildPrefix(level) {
            let prefix = '';

            // Ajouter le timestamp si activé
            if (this.config.timestamp) {
                const now = new Date();
                const time = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}`;
                prefix += `[${time}] `;
            }

            // Ajouter le préfixe configuré
            prefix += this.config.prefix + ' ';

            // Ajouter le module
            prefix += `[${this.module}] `;

            // Ajouter le niveau
            prefix += `[${level.toUpperCase()}]`;

            return prefix;
        }

        /**
         * Déterminer si on doit envoyer au serveur
         */
        shouldSendToServer(args) {
            // Chercher des erreurs critiques
            const errorString = args.join(' ').toLowerCase();
            const criticalKeywords = [
                'fatal',
                'critical',
                'crash',
                'uncaught',
                'undefined is not',
                'cannot read property'
            ];

            return criticalKeywords.some(keyword => errorString.includes(keyword));
        }

        /**
         * Envoyer les logs au serveur
         */
        async sendToServer(entry) {
            if (!window.rmcuData?.ajaxUrl) return;

            try {
                const formData = new FormData();
                formData.append('action', 'rmcu_log_error');
                formData.append('nonce', window.rmcuData.nonce);
                formData.append('log_data', JSON.stringify(entry));

                await fetch(window.rmcuData.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
            } catch (error) {
                // Ignorer les erreurs d'envoi
            }
        }

        /**
         * Grouper les logs
         */
        group(label) {
            if (!this.config.enabled) return;
            console.group(`${this.config.prefix} [${this.module}] ${label}`);
        }

        /**
         * Fin du groupe de logs
         */
        groupEnd() {
            if (!this.config.enabled) return;
            console.groupEnd();
        }

        /**
         * Timer pour mesurer les performances
         */
        time(label) {
            if (!this.config.enabled) return;
            const key = `${this.module}_${label}`;
            console.time(key);
            return key;
        }

        /**
         * Fin du timer
         */
        timeEnd(label) {
            if (!this.config.enabled) return;
            const key = `${this.module}_${label}`;
            console.timeEnd(key);
        }

        /**
         * Table pour afficher des données structurées
         */
        table(data) {
            if (!this.config.enabled) return;
            console.table(data);
        }

        /**
         * Obtenir l'historique des logs
         */
        getHistory(level = null) {
            if (level) {
                return this.history.filter(entry => entry.level === level);
            }
            return this.history;
        }

        /**
         * Nettoyer l'historique
         */
        clearHistory() {
            this.history = [];
            try {
                if (window.sessionStorage) {
                    sessionStorage.removeItem('rmcu_log_history_' + this.module);
                }
            } catch (e) {
                // Ignorer les erreurs
            }
        }

        /**
         * Exporter les logs
         */
        exportLogs() {
            const data = {
                sessionId: this.sessionId,
                module: this.module,
                timestamp: new Date().toISOString(),
                logs: this.history
            };

            const json = JSON.stringify(data, null, 2);
            const blob = new Blob([json], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `rmcu_logs_${this.module}_${Date.now()}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            this.info('Logs exported successfully');
        }

        /**
         * Créer un rapport d'erreur détaillé
         */
        createErrorReport(error) {
            const report = {
                message: error.message || 'Unknown error',
                stack: error.stack || '',
                type: error.name || 'Error',
                timestamp: new Date().toISOString(),
                url: window.location.href,
                line: error.lineNumber || 0,
                column: error.columnNumber || 0,
                userAgent: navigator.userAgent,
                module: this.module,
                sessionId: this.sessionId
            };

            // Ajouter le contexte WordPress
            if (window.rmcuData) {
                report.wordpress = {
                    postId: window.rmcuData.postId,
                    restUrl: window.rmcuData.restUrl
                };
            }

            // Ajouter l'état du DOM si disponible
            if (window.RMCUConfig) {
                report.detection = {
                    rankMathVersion: window.RMCUConfig.selectors.rankmath.current,
                    editorType: window.RMCUConfig.selectors.wordpress.current
                };
            }

            return report;
        }

        /**
         * Logger une erreur avec rapport complet
         */
        logError(error, context = {}) {
            const report = this.createErrorReport(error);
            Object.assign(report, context);

            this.error('Error Report:', report);
            
            // Envoyer au serveur
            this.sendToServer({
                level: 'error',
                type: 'error_report',
                data: report
            });

            return report;
        }
    }

    /**
     * Factory pour créer des instances de logger
     */
    class RMCULoggerFactory {
        constructor() {
            this.loggers = new Map();
        }

        /**
         * Obtenir ou créer un logger pour un module
         */
        getLogger(module, config = null) {
            if (!this.loggers.has(module)) {
                this.loggers.set(module, new RMCULogger(module, config));
            }
            return this.loggers.get(module);
        }

        /**
         * Obtenir tous les loggers
         */
        getAllLoggers() {
            return Array.from(this.loggers.values());
        }

        /**
         * Exporter tous les logs
         */
        exportAllLogs() {
            const allLogs = {};
            this.loggers.forEach((logger, module) => {
                allLogs[module] = logger.getHistory();
            });

            const json = JSON.stringify(allLogs, null, 2);
            const blob = new Blob([json], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `rmcu_all_logs_${Date.now()}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    }

    // Créer la factory globale
    const loggerFactory = new RMCULoggerFactory();

    // Exposer globalement
    window.RMCULogger = RMCULogger;
    window.RMCULoggerFactory = loggerFactory;
    
    // Créer un logger par défaut
    window.rmcuLog = loggerFactory.getLogger('Main');

    // Capturer les erreurs globales si en debug
    if (window.rmcuData?.debug) {
        window.addEventListener('error', function(event) {
            const logger = loggerFactory.getLogger('GlobalError');
            logger.logError(event.error || {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno
            });
        });

        window.addEventListener('unhandledrejection', function(event) {
            const logger = loggerFactory.getLogger('UnhandledPromise');
            logger.error('Unhandled Promise Rejection:', event.reason);
        });
    }

})(window);