/**
 * RMCU Interfaces Module
 * Définition des structures de données et contrats d'interface
 * 
 * @module rmcu-interfaces
 * @version 2.0.0
 */

(function(window) {
    'use strict';

    /**
     * Interfaces et structures de données pour RMCU
     */
    const RMCUInterfaces = {
        
        /**
         * Structure des données WordPress extraites
         */
        WordPressData: {
            // Schéma de validation
            schema: {
                title: {
                    type: 'string',
                    required: true,
                    maxLength: 255
                },
                content: {
                    type: 'array',
                    required: true,
                    items: {
                        type: 'object',
                        properties: {
                            type: {
                                type: 'string',
                                enum: ['heading', 'paragraph', 'list', 'image', 'quote', 'code', 'table', 'html']
                            },
                            level: {
                                type: 'number',
                                min: 1,
                                max: 6
                            },
                            text: {
                                type: 'string'
                            },
                            html: {
                                type: 'string'
                            },
                            items: {
                                type: 'array'
                            },
                            attributes: {
                                type: 'object'
                            }
                        }
                    }
                },
                meta: {
                    type: 'object',
                    properties: {
                        postId: 'number',
                        postType: 'string',
                        postStatus: 'string',
                        author: 'string',
                        lastModified: 'string',
                        permalink: 'string',
                        wordCount: 'number',
                        charCount: 'number'
                    }
                },
                headings: {
                    type: 'array',
                    items: {
                        type: 'object',
                        properties: {
                            level: 'number',
                            text: 'string',
                            id: 'string',
                            order: 'number'
                        }
                    }
                },
                links: {
                    type: 'array',
                    items: {
                        type: 'object',
                        properties: {
                            url: 'string',
                            text: 'string',
                            type: {
                                type: 'string',
                                enum: ['internal', 'external', 'anchor']
                            },
                            nofollow: 'boolean',
                            target: 'string'
                        }
                    }
                },
                images: {
                    type: 'array',
                    items: {
                        type: 'object',
                        properties: {
                            src: 'string',
                            alt: 'string',
                            title: 'string',
                            width: 'number',
                            height: 'number',
                            caption: 'string'
                        }
                    }
                },
                seo: {
                    type: 'object',
                    properties: {
                        metaTitle: 'string',
                        metaDescription: 'string',
                        focusKeyword: 'string',
                        breadcrumbs: 'string',
                        canonical: 'string',
                        robots: 'object'
                    }
                }
            },
            
            /**
             * Créer un objet WordPress Data vide
             */
            create() {
                return {
                    title: '',
                    content: [],
                    meta: {
                        postId: 0,
                        postType: 'post',
                        postStatus: 'draft',
                        author: '',
                        lastModified: new Date().toISOString(),
                        permalink: '',
                        wordCount: 0,
                        charCount: 0
                    },
                    headings: [],
                    links: [],
                    images: [],
                    seo: {
                        metaTitle: '',
                        metaDescription: '',
                        focusKeyword: '',
                        breadcrumbs: '',
                        canonical: '',
                        robots: {}
                    }
                };
            },
            
            /**
             * Valider les données WordPress
             */
            validate(data) {
                return this.validateSchema(data, this.schema);
            },
            
            /**
             * Valider un schéma
             */
            validateSchema(data, schema) {
                const errors = [];
                
                for (const [key, rules] of Object.entries(schema)) {
                    // Vérifier si requis
                    if (rules.required && !data.hasOwnProperty(key)) {
                        errors.push(`Missing required field: ${key}`);
                        continue;
                    }
                    
                    if (!data.hasOwnProperty(key)) continue;
                    
                    const value = data[key];
                    
                    // Vérifier le type
                    if (rules.type) {
                        const actualType = Array.isArray(value) ? 'array' : typeof value;
                        if (actualType !== rules.type) {
                            errors.push(`Invalid type for ${key}: expected ${rules.type}, got ${actualType}`);
                        }
                    }
                    
                    // Vérifier les énumérations
                    if (rules.enum && !rules.enum.includes(value)) {
                        errors.push(`Invalid value for ${key}: ${value} not in ${rules.enum.join(', ')}`);
                    }
                    
                    // Vérifier la longueur max
                    if (rules.maxLength && value.length > rules.maxLength) {
                        errors.push(`${key} exceeds maximum length of ${rules.maxLength}`);
                    }
                    
                    // Valider les tableaux
                    if (rules.type === 'array' && rules.items && Array.isArray(value)) {
                        value.forEach((item, index) => {
                            if (rules.items.properties) {
                                const itemErrors = this.validateSchema(item, rules.items.properties);
                                errors.push(...itemErrors.map(e => `${key}[${index}].${e}`));
                            }
                        });
                    }
                    
                    // Valider les objets imbriqués
                    if (rules.properties && typeof value === 'object') {
                        const nestedErrors = this.validateSchema(value, rules.properties);
                        errors.push(...nestedErrors.map(e => `${key}.${e}`));
                    }
                }
                
                return errors;
            }
        },
        
        /**
         * Structure des données RankMath extraites
         */
        RankMathData: {
            schema: {
                score: {
                    type: 'number',
                    required: true,
                    min: 0,
                    max: 100
                },
                keyword: {
                    type: 'string',
                    required: true
                },
                analysis: {
                    type: 'object',
                    required: true,
                    properties: {
                        passed: {
                            type: 'array',
                            items: 'object'
                        },
                        warnings: {
                            type: 'array',
                            items: 'object'
                        },
                        errors: {
                            type: 'array',
                            items: 'object'
                        }
                    }
                },
                recommendations: {
                    type: 'array',
                    items: {
                        type: 'object',
                        properties: {
                            id: 'string',
                            title: 'string',
                            description: 'string',
                            impact: {
                                type: 'string',
                                enum: ['high', 'medium', 'low']
                            },
                            status: {
                                type: 'string',
                                enum: ['passed', 'warning', 'failed']
                            },
                            category: 'string',
                            tooltip: 'string'
                        }
                    }
                },
                stats: {
                    type: 'object',
                    properties: {
                        totalTests: 'number',
                        passedTests: 'number',
                        warnings: 'number',
                        errors: 'number'
                    }
                }
            },
            
            /**
             * Créer un objet RankMath Data vide
             */
            create() {
                return {
                    score: 0,
                    keyword: '',
                    analysis: {
                        passed: [],
                        warnings: [],
                        errors: []
                    },
                    recommendations: [],
                    stats: {
                        totalTests: 0,
                        passedTests: 0,
                        warnings: 0,
                        errors: 0
                    },
                    timestamp: new Date().toISOString()
                };
            },
            
            /**
             * Parser un test RankMath
             */
            parseTest(element, status) {
                return {
                    id: element.getAttribute('data-test-id') || '',
                    title: element.querySelector('.test-title')?.textContent || '',
                    description: element.querySelector('.test-description')?.textContent || '',
                    status: status,
                    impact: this.determineImpact(status),
                    category: element.getAttribute('data-category') || 'general',
                    tooltip: element.querySelector('.test-tooltip')?.textContent || ''
                };
            },
            
            /**
             * Déterminer l'impact d'un test
             */
            determineImpact(status) {
                switch(status) {
                    case 'failed':
                    case 'error':
                        return 'high';
                    case 'warning':
                        return 'medium';
                    case 'passed':
                        return 'low';
                    default:
                        return 'medium';
                }
            }
        },
        
        /**
         * Structure combinée pour l'envoi à n8n
         */
        CombinedData: {
            schema: {
                timestamp: {
                    type: 'string',
                    required: true
                },
                wordpress: {
                    type: 'object',
                    required: true
                },
                rankmath: {
                    type: 'object',
                    required: true
                },
                metadata: {
                    type: 'object',
                    properties: {
                        version: 'string',
                        sessionId: 'string',
                        iterationCount: 'number',
                        targetScore: 'number',
                        environment: 'object'
                    }
                }
            },
            
            /**
             * Combiner les données WordPress et RankMath
             */
            combine(wordpressData, rankmathData, metadata = {}) {
                return {
                    timestamp: new Date().toISOString(),
                    wordpress: wordpressData,
                    rankmath: rankmathData,
                    metadata: {
                        version: window.RMCUConfig?.version || '2.0.0',
                        sessionId: this.generateSessionId(),
                        iterationCount: metadata.iterationCount || 0,
                        targetScore: metadata.targetScore || 90,
                        environment: {
                            url: window.location.href,
                            userAgent: navigator.userAgent,
                            screenResolution: `${window.screen.width}x${window.screen.height}`,
                            language: navigator.language
                        },
                        ...metadata
                    }
                };
            },
            
            /**
             * Générer un ID de session
             */
            generateSessionId() {
                return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            }
        },
        
        /**
         * Structure de réponse de l'API
         */
        ApiResponse: {
            schema: {
                success: {
                    type: 'boolean',
                    required: true
                },
                data: {
                    type: 'object'
                },
                message: {
                    type: 'string'
                },
                error: {
                    type: 'object',
                    properties: {
                        code: 'string',
                        message: 'string',
                        details: 'object'
                    }
                }
            },
            
            /**
             * Créer une réponse de succès
             */
            success(data, message = '') {
                return {
                    success: true,
                    data: data,
                    message: message,
                    timestamp: new Date().toISOString()
                };
            },
            
            /**
             * Créer une réponse d'erreur
             */
            error(code, message, details = {}) {
                return {
                    success: false,
                    error: {
                        code: code,
                        message: message,
                        details: details
                    },
                    timestamp: new Date().toISOString()
                };
            }
        },
        
        /**
         * Structure pour les événements
         */
        Event: {
            /**
             * Créer un événement
             */
            create(type, data, source = 'unknown') {
                return {
                    type: type,
                    data: data,
                    source: source,
                    timestamp: new Date().toISOString(),
                    id: this.generateEventId()
                };
            },
            
            /**
             * Générer un ID d'événement
             */
            generateEventId() {
                return 'evt_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            },
            
            /**
             * Types d'événements
             */
            TYPES: {
                EXTRACTION_START: 'extraction_start',
                EXTRACTION_COMPLETE: 'extraction_complete',
                EXTRACTION_ERROR: 'extraction_error',
                SCAN_START: 'scan_start',
                SCAN_COMPLETE: 'scan_complete',
                SCAN_ERROR: 'scan_error',
                API_REQUEST: 'api_request',
                API_RESPONSE: 'api_response',
                API_ERROR: 'api_error',
                OPTIMIZATION_START: 'optimization_start',
                OPTIMIZATION_COMPLETE: 'optimization_complete',
                OPTIMIZATION_ERROR: 'optimization_error',
                CONTENT_UPDATE: 'content_update',
                SCORE_UPDATE: 'score_update'
            }
        },
        
        /**
         * Validateur générique
         */
        Validator: {
            /**
             * Valider une adresse email
             */
            isEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            },
            
            /**
             * Valider une URL
             */
            isUrl(url) {
                try {
                    new URL(url);
                    return true;
                } catch {
                    return false;
                }
            },
            
            /**
             * Valider un JSON
             */
            isJson(str) {
                try {
                    JSON.parse(str);
                    return true;
                } catch {
                    return false;
                }
            },
            
            /**
             * Valider une plage de nombres
             */
            inRange(value, min, max) {
                return typeof value === 'number' && value >= min && value <= max;
            },
            
            /**
             * Valider un objet contre un schéma
             */
            validateObject(obj, schema) {
                const errors = [];
                
                for (const [key, value] of Object.entries(schema)) {
                    if (!obj.hasOwnProperty(key) && value.required) {
                        errors.push(`Missing required field: ${key}`);
                    }
                }
                
                return {
                    isValid: errors.length === 0,
                    errors: errors
                };
            }
        },
        
        /**
         * Formatteur de données
         */
        Formatter: {
            /**
             * Formater pour l'export CSV
             */
            toCSV(data, headers = null) {
                if (!Array.isArray(data) || data.length === 0) {
                    return '';
                }
                
                const keys = headers || Object.keys(data[0]);
                const csvHeaders = keys.join(',');
                
                const csvRows = data.map(row => {
                    return keys.map(key => {
                        const value = row[key];
                        // Échapper les valeurs contenant des virgules ou des guillemets
                        if (typeof value === 'string' && (value.includes(',') || value.includes('"'))) {
                            return `"${value.replace(/"/g, '""')}"`;
                        }
                        return value;
                    }).join(',');
                });
                
                return [csvHeaders, ...csvRows].join('\n');
            },
            
            /**
             * Formater pour l'affichage
             */
            toDisplay(data) {
                return JSON.stringify(data, null, 2)
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            },
            
            /**
             * Formater la taille de fichier
             */
            formatFileSize(bytes) {
                const units = ['B', 'KB', 'MB', 'GB'];
                let size = bytes;
                let unitIndex = 0;
                
                while (size >= 1024 && unitIndex < units.length - 1) {
                    size /= 1024;
                    unitIndex++;
                }
                
                return `${size.toFixed(2)} ${units[unitIndex]}`;
            },
            
            /**
             * Formater une durée
             */
            formatDuration(ms) {
                if (ms < 1000) return `${ms}ms`;
                if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
                if (ms < 3600000) return `${Math.floor(ms / 60000)}m ${Math.floor((ms % 60000) / 1000)}s`;
                return `${Math.floor(ms / 3600000)}h ${Math.floor((ms % 3600000) / 60000)}m`;
            }
        }
    };
    
    // Exposer globalement
    window.RMCUInterfaces = RMCUInterfaces;
    
})(window);