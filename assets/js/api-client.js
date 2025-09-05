/**
 * RMCU API Client JavaScript
 * Client pour communiquer avec l'API REST WordPress
 */
(function(window) {
    'use strict';

    class RMCUApiClient {
        constructor() {
            this.baseUrl = window.RMCUConfig.getApiUrl();
            this.headers = window.RMCUConfig.getApiHeaders();
            this.cache = new Map();
            this.pendingRequests = new Map();
        }

        /**
         * Requête GET
         */
        async get(endpoint, params = {}) {
            const url = this.buildUrl(endpoint, params);
            return this.request('GET', url);
        }

        /**
         * Requête POST
         */
        async post(endpoint, data = {}) {
            const url = this.buildUrl(endpoint);
            return this.request('POST', url, data);
        }

        /**
         * Requête PUT
         */
        async put(endpoint, data = {}) {
            const url = this.buildUrl(endpoint);
            return this.request('PUT', url, data);
        }

        /**
         * Requête DELETE
         */
        async delete(endpoint) {
            const url = this.buildUrl(endpoint);
            return this.request('DELETE', url);
        }

        /**
         * Requête PATCH
         */
        async patch(endpoint, data = {}) {
            const url = this.buildUrl(endpoint);
            return this.request('PATCH', url, data);
        }

        /**
         * Upload de fichier
         */
        async upload(endpoint, file, additionalData = {}) {
            const formData = new FormData();
            formData.append('file', file);
            
            Object.keys(additionalData).forEach(key => {
                formData.append(key, additionalData[key]);
            });

            const url = this.buildUrl(endpoint);
            return this.request('POST', url, formData, {
                'Content-Type': 'multipart/form-data'
            });
        }

        /**
         * Requête générique
         */
        async request(method, url, data = null, customHeaders = {}) {
            const cacheKey = `${method}:${url}`;
            
            // Vérifier le cache pour les GET
            if (method === 'GET' && this.cache.has(cacheKey)) {
                const cached = this.cache.get(cacheKey);
                if (cached.expires > Date.now()) {
                    window.RMCULogger.debug('Using cached response', { url });
                    return cached.data;
                }
                this.cache.delete(cacheKey);
            }

            // Éviter les requêtes dupliquées
            if (this.pendingRequests.has(cacheKey)) {
                window.RMCULogger.debug('Reusing pending request', { url });
                return this.pendingRequests.get(cacheKey);
            }

            // Créer la requête
            const requestPromise = this.performRequest(method, url, data, customHeaders);
            this.pendingRequests.set(cacheKey, requestPromise);

            try {
                const response = await requestPromise;
                
                // Mettre en cache les GET réussis
                if (method === 'GET' && response.success) {
                    this.cache.set(cacheKey, {
                        data: response,
                        expires: Date.now() + 300000 // 5 minutes
                    });
                }

                return response;
            } finally {
                this.pendingRequests.delete(cacheKey);
            }
        }

        /**
         * Effectuer la requête
         */
        async performRequest(method, url, data, customHeaders) {
            const options = {
                method: method,
                headers: {
                    ...this.headers,
                    ...customHeaders
                },
                credentials: 'same-origin'
            };

            // Ajouter le body si nécessaire
            if (data) {
                if (data instanceof FormData) {
                    options.body = data;
                    delete options.headers['Content-Type']; // Let browser set it
                } else {
                    options.body = JSON.stringify(data);
                }
            }

            window.RMCULogger.debug(`API ${method} request`, { url, data });

            try {
                const response = await fetch(url, options);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                
                window.RMCULogger.debug('API response', { url, result });
                
                return result;

            } catch (error) {
                window.RMCULogger.error('API request failed', { url, error });
                throw error;
            }
        }

        /**
         * Construire l'URL
         */
        buildUrl(endpoint, params = {}) {
            let url = `${this.baseUrl}/${endpoint}`;
            
            // Ajouter les paramètres de requête
            const queryParams = new URLSearchParams(params);
            const queryString = queryParams.toString();
            
            if (queryString) {
                url += `?${queryString}`;
            }
            
            return url;
        }

        /**
         * API Captures
         */
        captures = {
            list: (params) => this.get('captures', params),
            get: (id) => this.get(`captures/${id}`),
            create: (data) => this.post('captures', data),
            update: (id, data) => this.put(`captures/${id}`, data),
            delete: (id) => this.delete(`captures/${id}`),
            upload: (file, data) => this.upload('captures/upload', file, data)
        };

        /**
         * API Content
         */
        content = {
            analyze: (data) => this.post('content/analyze', data),
            parse: (data) => this.post('content/parse', data),
            scan: (data) => this.post('content/scan', data),
            seo: (data) => this.post('content/seo', data)
        };

        /**
         * API Media
         */
        media = {
            upload: (file) => this.upload('media', file),
            process: (id, options) => this.post(`media/${id}/process`, options),
            compress: (id, quality) => this.post(`media/${id}/compress`, { quality }),
            convert: (id, format) => this.post(`media/${id}/convert`, { format })
        };

        /**
         * API Settings
         */
        settings = {
            get: () => this.get('settings'),
            update: (data) => this.put('settings', data),
            reset: () => this.post('settings/reset')
        };

        /**
         * API Analytics
         */
        analytics = {
            stats: (params) => this.get('analytics/stats', params),
            events: (params) => this.get('analytics/events', params),
            track: (event, data) => this.post('analytics/track', { event, data })
        };

        /**
         * API Export
         */
        export = {
            data: (params) => this.get('export/data', params),
            backup: () => this.get('export/backup'),
            download: (id) => this.get(`export/download/${id}`)
        };

        /**
         * API Import
         */
        import = {
            data: (file) => this.upload('import/data', file),
            restore: (file) => this.upload('import/restore', file),
            status: (id) => this.get(`import/status/${id}`)
        };

        /**
         * Requête batch
         */
        async batch(requests) {
            return this.post('batch', { requests });
        }

        /**
         * WebSocket pour les mises à jour en temps réel
         */
        connectWebSocket(endpoint = 'ws') {
            const wsUrl = this.baseUrl.replace('http', 'ws') + `/${endpoint}`;
            
            this.ws = new WebSocket(wsUrl);
            
            this.ws.onopen = () => {
                window.RMCULogger.info('WebSocket connected');
                this.ws.send(JSON.stringify({
                    type: 'auth',
                    nonce: this.headers['X-WP-Nonce']
                }));
            };
            
            this.ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                window.RMCULogger.debug('WebSocket message', data);
                window.rmcuController.emit('ws-message', data);
            };
            
            this.ws.onerror = (error) => {
                window.RMCULogger.error('WebSocket error', error);
            };
            
            this.ws.onclose = () => {
                window.RMCULogger.info('WebSocket disconnected');
                // Reconnecter après 5 secondes
                setTimeout(() => this.connectWebSocket(endpoint), 5000);
            };
            
            return this.ws;
        }

        /**
         * Envoyer un message WebSocket
         */
        sendWebSocket(data) {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify(data));
            } else {
                window.RMCULogger.warning('WebSocket not connected');
            }
        }

        /**
         * Intercepteur de requêtes
         */
        addInterceptor(type, callback) {
            if (!this.interceptors) {
                this.interceptors = { request: [], response: [] };
            }
            
            if (type === 'request' || type === 'response') {
                this.interceptors[type].push(callback);
            }
        }

        /**
         * Vider le cache
         */
        clearCache() {
            this.cache.clear();
            window.RMCULogger.debug('API cache cleared');
        }

        /**
         * Obtenir les statistiques du cache
         */
        getCacheStats() {
            return {
                size: this.cache.size,
                entries: Array.from(this.cache.keys())
            };
        }
    }

    // Exposer globalement
    window.RMCUApiClient = new RMCUApiClient();

})(window);