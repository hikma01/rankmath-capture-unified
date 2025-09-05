/**
 * RMCU RankMath Scanner Module
 * Scanner spécialisé pour extraire les données SEO de RankMath
 * 
 * @module rmcu-rankmath-scanner
 * @version 2.0.0
 */

(function(window) {
    'use strict';

    /**
     * Classe pour scanner l'interface RankMath
     */
    class RMCURankMathScanner {
        constructor() {
            this.logger = window.RMCULoggerFactory?.getLogger('RankMathScanner') || console;
            this.config = window.RMCUConfig || {};
            this.interfaces = window.RMCUInterfaces?.RankMathData || {};
            this.version = null;
            this.retryCount = 3;
            this.retryDelay = 1000;
            this.observerTimeout = 5000; // 5 secondes pour observer les changements
        }

        /**
         * Méthode principale de scan
         */
        async scan() {
            this.logger.info('Starting RankMath SEO scan');
            
            try {
                // Détecter la version de RankMath
                this.version = await this.detectRankMathVersion();
                
                if (!this.version) {
                    throw new Error('RankMath not detected on this page');
                }
                
                this.logger.info(`RankMath version detected: ${this.version}`);
                
                // Attendre que RankMath soit complètement chargé
                await this.waitForRankMath();
                
                // Ouvrir le panneau RankMath si nécessaire
                await this.ensureRankMathPanelOpen();
                
                // Scanner les données
                const data = await this.scanRankMathData();
                
                // Valider les données
                const validation = this.validateData(data);
                if (!validation.isValid) {
                    this.logger.warn('Data validation warnings:', validation.errors);
                }
                
                this.logger.success('RankMath data scanned successfully');
                return data;
                
            } catch (error) {
                this.logger.error('Scan failed:', error);
                throw error;
            }
        }

        /**
         * Détecter la version de RankMath
         */
        async detectRankMathVersion() {
            // Vérifier via l'objet global RankMath
            if (window.rankMath && window.rankMath.version) {
                return `api-${window.rankMath.version}`;
            }
            
            // Vérifier via les sélecteurs DOM
            const selectors = this.config.selectors?.rankmath;
            if (!selectors) {
                this.logger.warn('No RankMath selectors in config');
                return this.detectByDOM();
            }
            
            // Tester les différentes versions
            for (const [version, versionSelectors] of Object.entries(selectors)) {
                if (version === 'current' || version === 'active') continue;
                
                const container = document.querySelector(versionSelectors.container);
                if (container) {
                    return version;
                }
            }
            
            return this.detectByDOM();
        }

        /**
         * Détection par analyse du DOM
         */
        detectByDOM() {
            // Patterns de détection pour différentes versions
            const patterns = [
                {
                    selector: '.rank-math-analyzer-wrap',
                    version: 'v1'
                },
                {
                    selector: '[data-rm-analyzer]',
                    version: 'v2'
                },
                {
                    selector: '#rank-math-metabox',
                    version: 'legacy'
                },
                {
                    selector: '.rank-math-content-ai-tab',
                    version: 'v2-ai'
                }
            ];
            
            for (const pattern of patterns) {
                if (document.querySelector(pattern.selector)) {
                    return pattern.version;
                }
            }
            
            return null;
        }

        /**
         * Attendre que RankMath soit chargé
         */
        async waitForRankMath() {
            const maxWait = 10000; // 10 secondes
            const checkInterval = 250;
            const startTime = Date.now();
            
            return new Promise((resolve, reject) => {
                const check = () => {
                    if (this.isRankMathReady()) {
                        resolve();
                        return;
                    }
                    
                    if (Date.now() - startTime > maxWait) {
                        reject(new Error('RankMath loading timeout'));
                        return;
                    }
                    
                    setTimeout(check, checkInterval);
                };
                
                check();
            });
        }

        /**
         * Vérifier si RankMath est prêt
         */
        isRankMathReady() {
            // Vérifier l'API JavaScript
            if (window.rankMath?.analyzer?.isReady) {
                return window.rankMath.analyzer.isReady();
            }
            
            // Vérifier les éléments DOM essentiels
            const selectors = this.getActiveSelectors();
            if (!selectors) return false;
            
            const scoreElement = document.querySelector(selectors.score || selectors.scoreValue);
            const testsContainer = document.querySelector(selectors.container);
            
            return !!(scoreElement && testsContainer);
        }

        /**
         * Obtenir les sélecteurs actifs
         */
        getActiveSelectors() {
            if (this.config.selectors?.rankmath?.active) {
                return this.config.selectors.rankmath.active;
            }
            
            // Fallback sur les sélecteurs par défaut
            const version = this.version || 'v1';
            return this.config.selectors?.rankmath?.[version] || this.getDefaultSelectors();
        }

        /**
         * Sélecteurs par défaut
         */
        getDefaultSelectors() {
            return {
                container: '.rank-math-analyzer-wrap',
                score: '.rank-math-total-score',
                scoreValue: '.rank-math-total',
                keyword: '.rank-math-focus-keyword input',
                tests: '.rank-math-test',
                testTitle: '.test-title',
                testStatus: '.test-status',
                errorIcon: '.dashicons-warning',
                successIcon: '.dashicons-yes-alt',
                warningIcon: '.dashicons-info'
            };
        }

        /**
         * S'assurer que le panneau RankMath est ouvert
         */
        async ensureRankMathPanelOpen() {
            const selectors = this.getActiveSelectors();
            
            // Vérifier si le panneau est déjà visible
            const container = document.querySelector(selectors.container);
            if (container && container.offsetHeight > 0) {
                return;
            }
            
            // Chercher le bouton/onglet RankMath
            const possibleTriggers = [
                '.rank-math-tab',
                '[data-tab="rank-math"]',
                '.components-button[aria-label*="Rank Math"]',
                '.edit-post-sidebar__panel-tab[data-label*="SEO"]',
                '#rank-math-metabox-wrapper .hndle',
                '.rank-math-toolbar-score'
            ];
            
            for (const triggerSelector of possibleTriggers) {
                const trigger = document.querySelector(triggerSelector);
                if (trigger) {
                    this.logger.info('Opening RankMath panel');
                    trigger.click();
                    
                    // Attendre que le panneau s'ouvre
                    await new Promise(resolve => setTimeout(resolve, 500));
                    
                    // Vérifier si c'est maintenant visible
                    const containerAfter = document.querySelector(selectors.container);
                    if (containerAfter && containerAfter.offsetHeight > 0) {
                        return;
                    }
                }
            }
            
            this.logger.warn('Could not open RankMath panel automatically');
        }

        /**
         * Scanner les données RankMath
         */
        async scanRankMathData() {
            const data = this.interfaces.create ? this.interfaces.create() : {
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
            
            const selectors = this.getActiveSelectors();
            
            // Score SEO
            data.score = this.extractScore(selectors);
            
            // Mot-clé focus
            data.keyword = this.extractKeyword(selectors);
            
            // Analyser les tests
            const tests = await this.extractTests(selectors);
            data.analysis = this.categorizeTests(tests);
            data.recommendations = tests;
            
            // Statistiques
            data.stats = {
                totalTests: tests.length,
                passedTests: data.analysis.passed.length,
                warnings: data.analysis.warnings.length,
                errors: data.analysis.errors.length
            };
            
            // Données supplémentaires
            data.additional = await this.extractAdditionalData();
            
            return data;
        }

        /**
         * Extraire le score SEO
         */
        extractScore(selectors) {
            let score = 0;
            
            // Essayer différentes sources
            const scoreElements = [
                selectors.scoreValue,
                selectors.score,
                '.rank-math-score-value',
                '[data-score]'
            ];
            
            for (const selector of scoreElements) {
                if (!selector) continue;
                
                const element = document.querySelector(selector);
                if (element) {
                    // Extraire le nombre du texte
                    const text = element.textContent || element.getAttribute('data-score') || '';
                    const match = text.match(/\d+/);
                    if (match) {
                        score = parseInt(match[0]);
                        break;
                    }
                }
            }
            
            // API JavaScript si disponible
            if (window.rankMath?.analyzer?.getScore) {
                score = window.rankMath.analyzer.getScore() || score;
            }
            
            return Math.min(100, Math.max(0, score));
        }

        /**
         * Extraire le mot-clé focus
         */
        extractKeyword(selectors) {
            let keyword = '';
            
            // Input field
            const keywordInput = document.querySelector(selectors.keyword);
            if (keywordInput) {
                keyword = keywordInput.value || '';
            }
            
            // Alternatives
            if (!keyword) {
                const alternatives = [
                    'input[name="rank_math_focus_keyword"]',
                    '.rank-math-focus-keyword',
                    '[data-rm-keyword]'
                ];
                
                for (const alt of alternatives) {
                    const element = document.querySelector(alt);
                    if (element) {
                        keyword = element.value || element.textContent || element.getAttribute('data-rm-keyword') || '';
                        if (keyword) break;
                    }
                }
            }
            
            // API JavaScript
            if (!keyword && window.rankMath?.analyzer?.getFocusKeyword) {
                keyword = window.rankMath.analyzer.getFocusKeyword() || '';
            }
            
            return keyword.trim();
        }

        /**
         * Extraire les tests SEO
         */
        async extractTests(selectors) {
            const tests = [];
            
            // Observer les changements pendant un court moment
            // car RankMath peut charger les tests de manière asynchrone
            const allTests = await this.collectTestsWithObserver(selectors);
            
            allTests.forEach(testElement => {
                const test = this.parseTestElement(testElement, selectors);
                if (test) {
                    tests.push(test);
                }
            });
            
            // Si aucun test trouvé, essayer l'API
            if (tests.length === 0 && window.rankMath?.analyzer?.getTests) {
                const apiTests = window.rankMath.analyzer.getTests();
                if (apiTests && Array.isArray(apiTests)) {
                    apiTests.forEach(apiTest => {
                        tests.push(this.parseApiTest(apiTest));
                    });
                }
            }
            
            return tests;
        }

        /**
         * Collecter les tests avec un observer
         */
        async collectTestsWithObserver(selectors) {
            return new Promise((resolve) => {
                const tests = new Set();
                let observer = null;
                let timeout = null;
                
                // Fonction pour collecter les tests
                const collectTests = () => {
                    const testElements = document.querySelectorAll(selectors.tests);
                    testElements.forEach(el => tests.add(el));
                };
                
                // Collecter immédiatement
                collectTests();
                
                // Si on a déjà des tests, les retourner
                if (tests.size > 0) {
                    resolve(Array.from(tests));
                    return;
                }
                
                // Sinon, observer les changements
                const container = document.querySelector(selectors.container);
                if (!container) {
                    resolve(Array.from(tests));
                    return;
                }
                
                observer = new MutationObserver(() => {
                    collectTests();
                    
                    // Si on trouve des tests, arrêter l'observation
                    if (tests.size > 0) {
                        if (timeout) clearTimeout(timeout);
                        if (observer) observer.disconnect();
                        resolve(Array.from(tests));
                    }
                });
                
                observer.observe(container, {
                    childList: true,
                    subtree: true
                });
                
                // Timeout de sécurité
                timeout = setTimeout(() => {
                    if (observer) observer.disconnect();
                    resolve(Array.from(tests));
                }, this.observerTimeout);
            });
        }

        /**
         * Parser un élément de test
         */
        parseTestElement(element, selectors) {
            const test = {
                id: '',
                title: '',
                description: '',
                status: 'unknown',
                impact: 'medium',
                category: 'general',
                tooltip: '',
                details: {}
            };
            
            // ID du test
            test.id = element.getAttribute('data-test') || 
                     element.getAttribute('id') || 
                     this.generateTestId(element);
            
            // Titre
            const titleElement = element.querySelector(selectors.testTitle);
            test.title = titleElement ? titleElement.textContent.trim() : '';
            
            // Description
            const descElement = element.querySelector('.test-description, .test-content');
            test.description = descElement ? descElement.textContent.trim() : '';
            
            // Statut
            test.status = this.determineTestStatus(element, selectors);
            
            // Impact
            test.impact = this.determineImpact(test.status, element);
            
            // Catégorie
            test.category = this.determineCategory(test.title, element);
            
            // Tooltip
            const tooltipElement = element.querySelector('.test-tooltip, [data-tooltip]');
            if (tooltipElement) {
                test.tooltip = tooltipElement.getAttribute('title') || 
                              tooltipElement.getAttribute('data-tooltip') || 
                              tooltipElement.textContent.trim();
            }
            
            // Détails supplémentaires
            test.details = this.extractTestDetails(element);
            
            return test;
        }

        /**
         * Parser un test depuis l'API
         */
        parseApiTest(apiTest) {
            return {
                id: apiTest.id || '',
                title: apiTest.title || apiTest.name || '',
                description: apiTest.description || apiTest.message || '',
                status: this.mapApiStatus(apiTest.status || apiTest.result),
                impact: apiTest.impact || 'medium',
                category: apiTest.category || 'general',
                tooltip: apiTest.tooltip || apiTest.help || '',
                details: apiTest.details || apiTest.data || {}
            };
        }

        /**
         * Déterminer le statut d'un test
         */
        determineTestStatus(element, selectors) {
            // Vérifier les icônes
            if (element.querySelector(selectors.successIcon)) return 'passed';
            if (element.querySelector(selectors.errorIcon)) return 'failed';
            if (element.querySelector(selectors.warningIcon)) return 'warning';
            
            // Vérifier les classes
            const classList = element.classList;
            if (classList.contains('test-success') || classList.contains('test-passed')) return 'passed';
            if (classList.contains('test-error') || classList.contains('test-failed')) return 'failed';
            if (classList.contains('test-warning')) return 'warning';
            
            // Vérifier les attributs data
            const dataStatus = element.getAttribute('data-status');
            if (dataStatus) return dataStatus;
            
            // Vérifier le texte
            const text = element.textContent.toLowerCase();
            if (text.includes('pass') || text.includes('success')) return 'passed';
            if (text.includes('fail') || text.includes('error')) return 'failed';
            if (text.includes('warning')) return 'warning';
            
            return 'unknown';
        }

        /**
         * Mapper le statut de l'API
         */
        mapApiStatus(status) {
            const statusMap = {
                'ok': 'passed',
                'good': 'passed',
                'success': 'passed',
                'error': 'failed',
                'fail': 'failed',
                'bad': 'failed',
                'warning': 'warning',
                'info': 'warning'
            };
            
            return statusMap[status?.toLowerCase()] || 'unknown';
        }

        /**
         * Déterminer l'impact
         */
        determineImpact(status, element) {
            // Basé sur le statut
            if (status === 'failed') return 'high';
            if (status === 'warning') return 'medium';
            if (status === 'passed') return 'low';
            
            // Vérifier les classes ou attributs
            if (element.classList.contains('high-impact')) return 'high';
            if (element.classList.contains('medium-impact')) return 'medium';
            if (element.classList.contains('low-impact')) return 'low';
            
            const dataImpact = element.getAttribute('data-impact');
            if (dataImpact) return dataImpact;
            
            return 'medium';
        }

        /**
         * Déterminer la catégorie
         */
        determineCategory(title, element) {
            const titleLower = title.toLowerCase();
            
            // Catégories basées sur les mots-clés
            const categories = {
                'title': ['title', 'titre', 'h1'],
                'content': ['content', 'contenu', 'word', 'length', 'longueur'],
                'meta': ['meta', 'description', 'snippet'],
                'images': ['image', 'img', 'alt', 'media'],
                'links': ['link', 'lien', 'url', 'anchor'],
                'keywords': ['keyword', 'mot-clé', 'focus'],
                'readability': ['readability', 'lisibilité', 'sentence', 'paragraph'],
                'technical': ['schema', 'sitemap', 'robots', 'canonical'],
                'social': ['social', 'og:', 'twitter', 'facebook']
            };
            
            for (const [category, keywords] of Object.entries(categories)) {
                if (keywords.some(keyword => titleLower.includes(keyword))) {
                    return category;
                }
            }
            
            // Vérifier l'attribut data
            const dataCategory = element.getAttribute('data-category');
            if (dataCategory) return dataCategory;
            
            return 'general';
        }

        /**
         * Extraire les détails du test
         */
        extractTestDetails(element) {
            const details = {};
            
            // Extraire les valeurs numériques
            const numbers = element.textContent.match(/\d+/g);
            if (numbers) {
                details.values = numbers.map(n => parseInt(n));
            }
            
            // Extraire les pourcentages
            const percentages = element.textContent.match(/\d+%/g);
            if (percentages) {
                details.percentages = percentages;
            }
            
            // Extraire les recommandations
            const recommendation = element.querySelector('.test-recommendation, .test-fix');
            if (recommendation) {
                details.recommendation = recommendation.textContent.trim();
            }
            
            // Extraire les données structurées
            element.querySelectorAll('[data-*]').forEach(el => {
                Array.from(el.attributes).forEach(attr => {
                    if (attr.name.startsWith('data-')) {
                        const key = attr.name.replace('data-', '');
                        details[key] = attr.value;
                    }
                });
            });
            
            return details;
        }

        /**
         * Catégoriser les tests
         */
        categorizeTests(tests) {
            return {
                passed: tests.filter(t => t.status === 'passed'),
                warnings: tests.filter(t => t.status === 'warning'),
                errors: tests.filter(t => t.status === 'failed')
            };
        }

        /**
         * Extraire les données additionnelles
         */
        async extractAdditionalData() {
            const additional = {
                schemaMarkup: {},
                readabilityScore: 0,
                contentAnalysis: {},
                competitors: [],
                suggestions: []
            };
            
            try {
                // Schema Markup
                additional.schemaMarkup = this.extractSchemaData();
                
                // Score de lisibilité
                additional.readabilityScore = this.extractReadabilityScore();
                
                // Analyse du contenu
                additional.contentAnalysis = this.extractContentAnalysis();
                
                // Suggestions AI (si disponible)
                if (window.rankMath?.contentAI) {
                    additional.suggestions = await this.extractAISuggestions();
                }
                
            } catch (error) {
                this.logger.debug('Error extracting additional data:', error);
            }
            
            return additional;
        }

        /**
         * Extraire les données Schema
         */
        extractSchemaData() {
            const schema = {};
            
            // Depuis l'interface RankMath
            const schemaTab = document.querySelector('.rank-math-schema-tab');
            if (schemaTab) {
                const schemaTypes = schemaTab.querySelectorAll('.schema-type');
                schemaTypes.forEach(type => {
                    const name = type.querySelector('.schema-name')?.textContent || '';
                    const enabled = type.querySelector('input[type="checkbox"]')?.checked || false;
                    if (name) {
                        schema[name] = enabled;
                    }
                });
            }
            
            // Depuis l'API
            if (window.rankMath?.schema?.getSchemas) {
                const apiSchemas = window.rankMath.schema.getSchemas();
                Object.assign(schema, apiSchemas);
            }
            
            return schema;
        }

        /**
         * Extraire le score de lisibilité
         */
        extractReadabilityScore() {
            let score = 0;
            
            // Sélecteurs possibles
            const selectors = [
                '.readability-score',
                '[data-readability-score]',
                '.rank-math-readability-score'
            ];
            
            for (const selector of selectors) {
                const element = document.querySelector(selector);
                if (element) {
                    const text = element.textContent || element.getAttribute('data-readability-score') || '';
                    const match = text.match(/\d+/);
                    if (match) {
                        score = parseInt(match[0]);
                        break;
                    }
                }
            }
            
            return score;
        }

        /**
         * Extraire l'analyse du contenu
         */
        extractContentAnalysis() {
            const analysis = {
                keywordDensity: 0,
                keywordCount: 0,
                lsiKeywords: [],
                competitors: []
            };
            
            // Densité du mot-clé
            const densityElement = document.querySelector('.keyword-density, [data-keyword-density]');
            if (densityElement) {
                const text = densityElement.textContent || densityElement.getAttribute('data-keyword-density') || '';
                const match = text.match(/[\d.]+/);
                if (match) {
                    analysis.keywordDensity = parseFloat(match[0]);
                }
            }
            
            // Nombre d'occurrences
            const countElement = document.querySelector('.keyword-count, [data-keyword-count]');
            if (countElement) {
                const text = countElement.textContent || countElement.getAttribute('data-keyword-count') || '';
                const match = text.match(/\d+/);
                if (match) {
                    analysis.keywordCount = parseInt(match[0]);
                }
            }
            
            // Mots-clés LSI
            document.querySelectorAll('.lsi-keyword, [data-lsi]').forEach(el => {
                const keyword = el.textContent.trim() || el.getAttribute('data-lsi');
                if (keyword) {
                    analysis.lsiKeywords.push(keyword);
                }
            });
            
            return analysis;
        }

        /**
         * Extraire les suggestions AI
         */
        async extractAISuggestions() {
            const suggestions = [];
            
            try {
                // Si l'API Content AI est disponible
                if (window.rankMath?.contentAI?.getSuggestions) {
                    const aiSuggestions = await window.rankMath.contentAI.getSuggestions();
                    if (Array.isArray(aiSuggestions)) {
                        return aiSuggestions;
                    }
                }
                
                // Depuis le DOM
                document.querySelectorAll('.content-ai-suggestion').forEach(el => {
                    suggestions.push({
                        type: el.getAttribute('data-type') || 'general',
                        text: el.textContent.trim(),
                        priority: el.getAttribute('data-priority') || 'medium'
                    });
                });
                
            } catch (error) {
                this.logger.debug('AI suggestions not available:', error);
            }
            
            return suggestions;
        }

        /**
         * Générer un ID pour un test
         */
        generateTestId(element) {
            const title = element.querySelector('.test-title')?.textContent || '';
            return 'test_' + title.toLowerCase().replace(/[^a-z0-9]/g, '_');
        }

        /**
         * Valider les données scannées
         */
        validateData(data) {
            const errors = [];
            
            // Validation du score
            if (data.score < 0 || data.score > 100) {
                errors.push('Invalid score value');
            }
            
            // Validation du mot-clé
            if (!data.keyword || data.keyword.trim() === '') {
                errors.push('Focus keyword is missing');
            }
            
            // Validation des tests
            if (data.stats.totalTests === 0) {
                errors.push('No SEO tests found');
            }
            
            // Cohérence des statistiques
            const totalFromCategories = data.analysis.passed.length + 
                                      data.analysis.warnings.length + 
                                      data.analysis.errors.length;
            
            if (totalFromCategories !== data.stats.totalTests) {
                errors.push('Inconsistent test statistics');
            }
            
            return {
                isValid: errors.length === 0,
                errors: errors
            };
        }

        /**
         * Méthode de retry en cas d'échec
         */
        async retryScan(attemptNumber = 1) {
            if (attemptNumber > this.retryCount) {
                throw new Error('Maximum retry attempts reached');
            }
            
            this.logger.info(`Retry attempt ${attemptNumber}/${this.retryCount}`);
            
            await new Promise(resolve => setTimeout(resolve, this.retryDelay * attemptNumber));
            
            try {
                return await this.scan();
            } catch (error) {
                return await this.retryScan(attemptNumber + 1);
            }
        }
    }

    // Exposer globalement
    window.RMCURankMathScanner = RMCURankMathScanner;

})(window);