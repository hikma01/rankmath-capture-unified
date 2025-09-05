/**
 * RMCU WordPress Parser Module
 * Extracteur spécialisé pour le contenu WordPress (Gutenberg & Classic Editor)
 * 
 * @module rmcu-wordpress-parser
 * @version 2.0.0
 */

(function(window) {
    'use strict';

    /**
     * Classe pour extraire le contenu de l'éditeur WordPress
     */
    class RMCUWordPressParser {
        constructor() {
            this.logger = window.RMCULoggerFactory?.getLogger('WordPressParser') || console;
            this.config = window.RMCUConfig || {};
            this.interfaces = window.RMCUInterfaces?.WordPressData || {};
            this.editorType = null;
            this.retryCount = 3;
            this.retryDelay = 1000;
        }

        /**
         * Méthode principale d'extraction
         */
        async extract() {
            this.logger.info('Starting WordPress content extraction');
            
            try {
                // Détecter le type d'éditeur
                this.editorType = this.detectEditor();
                
                if (!this.editorType) {
                    throw new Error('No WordPress editor detected');
                }
                
                this.logger.info(`Editor detected: ${this.editorType}`);
                
                // Attendre que l'éditeur soit prêt
                await this.waitForEditor();
                
                // Extraire selon le type d'éditeur
                let data;
                if (this.editorType === 'gutenberg') {
                    data = await this.extractGutenberg();
                } else if (this.editorType === 'classic') {
                    data = await this.extractClassic();
                } else {
                    throw new Error(`Unsupported editor type: ${this.editorType}`);
                }
                
                // Enrichir avec les métadonnées
                data = this.enrichWithMetadata(data);
                
                // Valider les données
                const validation = this.validateData(data);
                if (!validation.isValid) {
                    this.logger.warn('Data validation warnings:', validation.errors);
                }
                
                this.logger.success('WordPress content extracted successfully');
                return data;
                
            } catch (error) {
                this.logger.error('Extraction failed:', error);
                throw error;
            }
        }

        /**
         * Détecter le type d'éditeur
         */
        detectEditor() {
            // Utiliser la config si disponible
            if (this.config.selectors?.wordpress?.current) {
                return this.config.selectors.wordpress.current;
            }
            
            // Détection manuelle
            if (document.querySelector('.edit-post-visual-editor') || 
                document.querySelector('.block-editor-writing-flow')) {
                return 'gutenberg';
            }
            
            if (document.querySelector('#content_ifr') || 
                document.querySelector('#content')) {
                return 'classic';
            }
            
            return null;
        }

        /**
         * Attendre que l'éditeur soit prêt
         */
        async waitForEditor() {
            const maxWait = 10000; // 10 secondes
            const checkInterval = 250;
            const startTime = Date.now();
            
            return new Promise((resolve, reject) => {
                const check = () => {
                    if (this.isEditorReady()) {
                        resolve();
                        return;
                    }
                    
                    if (Date.now() - startTime > maxWait) {
                        reject(new Error('Editor loading timeout'));
                        return;
                    }
                    
                    setTimeout(check, checkInterval);
                };
                
                check();
            });
        }

        /**
         * Vérifier si l'éditeur est prêt
         */
        isEditorReady() {
            if (this.editorType === 'gutenberg') {
                // Vérifier l'API Gutenberg
                return !!(window.wp?.data?.select('core/editor')?.getCurrentPost());
            } else if (this.editorType === 'classic') {
                // Vérifier TinyMCE
                return !!(window.tinymce?.activeEditor?.getContent);
            }
            
            return false;
        }

        /**
         * Extraire le contenu de Gutenberg
         */
        async extractGutenberg() {
            this.logger.info('Extracting from Gutenberg editor');
            
            const wp = window.wp;
            if (!wp || !wp.data) {
                throw new Error('WordPress block editor API not available');
            }
            
            const editor = wp.data.select('core/editor');
            const blocks = wp.data.select('core/block-editor');
            
            const data = this.interfaces.create ? this.interfaces.create() : {
                title: '',
                content: [],
                meta: {},
                headings: [],
                links: [],
                images: [],
                seo: {}
            };
            
            // Titre
            data.title = editor.getEditedPostAttribute('title') || '';
            
            // Contenu par blocs
            const contentBlocks = blocks.getBlocks();
            data.content = this.parseGutenbergBlocks(contentBlocks);
            
            // Métadonnées
            const post = editor.getCurrentPost();
            data.meta = {
                postId: post.id || 0,
                postType: post.type || 'post',
                postStatus: post.status || 'draft',
                author: post.author || '',
                lastModified: post.modified || new Date().toISOString(),
                permalink: post.link || '',
                wordCount: this.countWords(data.content),
                charCount: this.countChars(data.content)
            };
            
            // Extraire les structures spécifiques
            data.headings = this.extractHeadings(data.content);
            data.links = this.extractLinks(contentBlocks);
            data.images = this.extractImages(contentBlocks);
            
            // SEO (si RankMath est actif)
            data.seo = this.extractSEOData();
            
            return data;
        }

        /**
         * Parser les blocs Gutenberg
         */
        parseGutenbergBlocks(blocks, depth = 0) {
            const content = [];
            
            blocks.forEach(block => {
                const parsed = this.parseBlock(block);
                
                if (parsed) {
                    content.push(parsed);
                }
                
                // Parser les blocs imbriqués
                if (block.innerBlocks && block.innerBlocks.length > 0) {
                    const innerContent = this.parseGutenbergBlocks(block.innerBlocks, depth + 1);
                    content.push(...innerContent);
                }
            });
            
            return content;
        }

        /**
         * Parser un bloc individuel
         */
        parseBlock(block) {
            const blockType = block.name;
            const attributes = block.attributes || {};
            
            switch(blockType) {
                case 'core/paragraph':
                    return {
                        type: 'paragraph',
                        text: this.cleanHTML(attributes.content || ''),
                        html: attributes.content || '',
                        attributes: {
                            align: attributes.align,
                            className: attributes.className
                        }
                    };
                    
                case 'core/heading':
                    return {
                        type: 'heading',
                        level: attributes.level || 2,
                        text: this.cleanHTML(attributes.content || ''),
                        html: attributes.content || '',
                        attributes: {
                            anchor: attributes.anchor,
                            className: attributes.className
                        }
                    };
                    
                case 'core/list':
                    return {
                        type: 'list',
                        ordered: attributes.ordered || false,
                        items: this.parseListItems(attributes.values || ''),
                        html: attributes.values || ''
                    };
                    
                case 'core/image':
                    return {
                        type: 'image',
                        url: attributes.url || '',
                        alt: attributes.alt || '',
                        caption: this.cleanHTML(attributes.caption || ''),
                        attributes: {
                            id: attributes.id,
                            width: attributes.width,
                            height: attributes.height,
                            sizeSlug: attributes.sizeSlug
                        }
                    };
                    
                case 'core/quote':
                    return {
                        type: 'quote',
                        text: this.cleanHTML(attributes.value || ''),
                        citation: this.cleanHTML(attributes.citation || ''),
                        html: attributes.value || ''
                    };
                    
                case 'core/code':
                    return {
                        type: 'code',
                        content: attributes.content || '',
                        language: attributes.language || ''
                    };
                    
                case 'core/table':
                    return {
                        type: 'table',
                        content: this.parseTable(attributes),
                        hasFixedLayout: attributes.hasFixedLayout || false
                    };
                    
                case 'core/html':
                case 'core/shortcode':
                    return {
                        type: 'html',
                        content: attributes.content || ''
                    };
                    
                default:
                    // Pour les blocs personnalisés ou non reconnus
                    if (attributes.content) {
                        return {
                            type: 'custom',
                            blockType: blockType,
                            content: attributes.content,
                            attributes: attributes
                        };
                    }
                    return null;
            }
        }

        /**
         * Extraire le contenu du Classic Editor
         */
        async extractClassic() {
            this.logger.info('Extracting from Classic editor');
            
            const data = this.interfaces.create ? this.interfaces.create() : {
                title: '',
                content: [],
                meta: {},
                headings: [],
                links: [],
                images: [],
                seo: {}
            };
            
            // Titre
            const titleField = document.querySelector('#title');
            data.title = titleField ? titleField.value : '';
            
            // Contenu
            let htmlContent = '';
            
            if (window.tinymce && window.tinymce.activeEditor) {
                // TinyMCE actif
                htmlContent = window.tinymce.activeEditor.getContent();
            } else {
                // Mode texte
                const textarea = document.querySelector('#content');
                htmlContent = textarea ? textarea.value : '';
            }
            
            // Parser le HTML en structure
            data.content = this.parseHTMLContent(htmlContent);
            
            // Métadonnées
            data.meta = {
                postId: this.getPostId(),
                postType: this.getPostType(),
                postStatus: this.getPostStatus(),
                author: this.getAuthor(),
                lastModified: new Date().toISOString(),
                permalink: this.getPermalink(),
                wordCount: this.countWords(data.content),
                charCount: this.countChars(data.content)
            };
            
            // Extraire les structures
            data.headings = this.extractHeadingsFromHTML(htmlContent);
            data.links = this.extractLinksFromHTML(htmlContent);
            data.images = this.extractImagesFromHTML(htmlContent);
            
            // SEO
            data.seo = this.extractSEOData();
            
            return data;
        }

        /**
         * Parser le contenu HTML
         */
        parseHTMLContent(html) {
            const content = [];
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const body = doc.body;
            
            for (const node of body.childNodes) {
                const parsed = this.parseHTMLNode(node);
                if (parsed) {
                    content.push(parsed);
                }
            }
            
            return content;
        }

        /**
         * Parser un nœud HTML
         */
        parseHTMLNode(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                const text = node.textContent.trim();
                if (text) {
                    return {
                        type: 'text',
                        text: text
                    };
                }
                return null;
            }
            
            if (node.nodeType !== Node.ELEMENT_NODE) {
                return null;
            }
            
            const tagName = node.tagName.toLowerCase();
            
            switch(tagName) {
                case 'p':
                    return {
                        type: 'paragraph',
                        text: node.textContent.trim(),
                        html: node.innerHTML
                    };
                    
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    return {
                        type: 'heading',
                        level: parseInt(tagName.charAt(1)),
                        text: node.textContent.trim(),
                        html: node.innerHTML,
                        id: node.id || ''
                    };
                    
                case 'ul':
                case 'ol':
                    return {
                        type: 'list',
                        ordered: tagName === 'ol',
                        items: Array.from(node.querySelectorAll('li')).map(li => li.textContent.trim()),
                        html: node.innerHTML
                    };
                    
                case 'img':
                    return {
                        type: 'image',
                        url: node.src || '',
                        alt: node.alt || '',
                        title: node.title || '',
                        width: node.width || 0,
                        height: node.height || 0
                    };
                    
                case 'blockquote':
                    return {
                        type: 'quote',
                        text: node.textContent.trim(),
                        html: node.innerHTML
                    };
                    
                case 'pre':
                case 'code':
                    return {
                        type: 'code',
                        content: node.textContent,
                        language: node.className || ''
                    };
                    
                case 'table':
                    return {
                        type: 'table',
                        content: this.parseHTMLTable(node),
                        html: node.outerHTML
                    };
                    
                default:
                    // Conteneur générique
                    if (node.children.length > 0) {
                        const children = [];
                        for (const child of node.children) {
                            const parsed = this.parseHTMLNode(child);
                            if (parsed) {
                                children.push(parsed);
                            }
                        }
                        if (children.length > 0) {
                            return {
                                type: 'container',
                                tag: tagName,
                                children: children
                            };
                        }
                    } else if (node.textContent.trim()) {
                        return {
                            type: 'element',
                            tag: tagName,
                            text: node.textContent.trim(),
                            html: node.innerHTML
                        };
                    }
                    return null;
            }
        }

        /**
         * Nettoyer le HTML
         */
        cleanHTML(html) {
            const div = document.createElement('div');
            div.innerHTML = html;
            return div.textContent || div.innerText || '';
        }

        /**
         * Parser les items de liste
         */
        parseListItems(html) {
            const div = document.createElement('div');
            div.innerHTML = html;
            const items = [];
            
            div.querySelectorAll('li').forEach(li => {
                items.push(li.textContent.trim());
            });
            
            return items;
        }

        /**
         * Parser une table
         */
        parseTable(attributes) {
            // Pour Gutenberg
            if (attributes.head || attributes.body) {
                return {
                    head: attributes.head || [],
                    body: attributes.body || [],
                    foot: attributes.foot || []
                };
            }
            return null;
        }

        /**
         * Parser une table HTML
         */
        parseHTMLTable(table) {
            const data = {
                head: [],
                body: [],
                foot: []
            };
            
            // En-tête
            const thead = table.querySelector('thead');
            if (thead) {
                thead.querySelectorAll('tr').forEach(tr => {
                    const row = [];
                    tr.querySelectorAll('th, td').forEach(cell => {
                        row.push(cell.textContent.trim());
                    });
                    data.head.push(row);
                });
            }
            
            // Corps
            const tbody = table.querySelector('tbody') || table;
            tbody.querySelectorAll('tr').forEach(tr => {
                if (!tr.closest('thead') && !tr.closest('tfoot')) {
                    const row = [];
                    tr.querySelectorAll('td, th').forEach(cell => {
                        row.push(cell.textContent.trim());
                    });
                    if (row.length > 0) {
                        data.body.push(row);
                    }
                }
            });
            
            // Pied
            const tfoot = table.querySelector('tfoot');
            if (tfoot) {
                tfoot.querySelectorAll('tr').forEach(tr => {
                    const row = [];
                    tr.querySelectorAll('td, th').forEach(cell => {
                        row.push(cell.textContent.trim());
                    });
                    data.foot.push(row);
                });
            }
            
            return data;
        }

        /**
         * Extraire les titres
         */
        extractHeadings(content) {
            const headings = [];
            let order = 0;
            
            content.forEach(item => {
                if (item.type === 'heading') {
                    headings.push({
                        level: item.level,
                        text: item.text,
                        id: item.attributes?.anchor || `heading-${order}`,
                        order: order++
                    });
                } else if (item.children) {
                    // Recherche récursive
                    const childHeadings = this.extractHeadings(item.children);
                    headings.push(...childHeadings);
                }
            });
            
            return headings;
        }

        /**
         * Extraire les titres depuis HTML
         */
        extractHeadingsFromHTML(html) {
            const headings = [];
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            let order = 0;
            
            doc.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(heading => {
                headings.push({
                    level: parseInt(heading.tagName.charAt(1)),
                    text: heading.textContent.trim(),
                    id: heading.id || `heading-${order}`,
                    order: order++
                });
            });
            
            return headings;
        }

        /**
         * Extraire les liens (Gutenberg)
         */
        extractLinks(blocks) {
            const links = [];
            
            const processBlock = (block) => {
                if (block.attributes?.content) {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(block.attributes.content, 'text/html');
                    
                    doc.querySelectorAll('a').forEach(link => {
                        links.push({
                            url: link.href || '',
                            text: link.textContent.trim(),
                            type: this.getLinkType(link.href),
                            nofollow: link.rel?.includes('nofollow') || false,
                            target: link.target || '_self'
                        });
                    });
                }
                
                // Traiter les blocs imbriqués
                if (block.innerBlocks) {
                    block.innerBlocks.forEach(processBlock);
                }
            };
            
            blocks.forEach(processBlock);
            return links;
        }

        /**
         * Extraire les liens depuis HTML
         */
        extractLinksFromHTML(html) {
            const links = [];
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            doc.querySelectorAll('a').forEach(link => {
                links.push({
                    url: link.href || '',
                    text: link.textContent.trim(),
                    type: this.getLinkType(link.href),
                    nofollow: link.rel?.includes('nofollow') || false,
                    target: link.target || '_self'
                });
            });
            
            return links;
        }

        /**
         * Déterminer le type de lien
         */
        getLinkType(url) {
            if (!url) return 'anchor';
            if (url.startsWith('#')) return 'anchor';
            if (url.includes(window.location.hostname)) return 'internal';
            return 'external';
        }

        /**
         * Extraire les images (Gutenberg)
         */
        extractImages(blocks) {
            const images = [];
            
            const processBlock = (block) => {
                if (block.name === 'core/image') {
                    images.push({
                        src: block.attributes.url || '',
                        alt: block.attributes.alt || '',
                        title: block.attributes.title || '',
                        width: block.attributes.width || 0,
                        height: block.attributes.height || 0,
                        caption: this.cleanHTML(block.attributes.caption || '')
                    });
                }
                
                // Images dans le contenu HTML
                if (block.attributes?.content) {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(block.attributes.content, 'text/html');
                    
                    doc.querySelectorAll('img').forEach(img => {
                        images.push({
                            src: img.src || '',
                            alt: img.alt || '',
                            title: img.title || '',
                            width: img.width || 0,
                            height: img.height || 0,
                            caption: ''
                        });
                    });
                }
                
                // Traiter les blocs imbriqués
                if (block.innerBlocks) {
                    block.innerBlocks.forEach(processBlock);
                }
            };
            
            blocks.forEach(processBlock);
            return images;
        }

        /**
         * Extraire les images depuis HTML
         */
        extractImagesFromHTML(html) {
            const images = [];
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            doc.querySelectorAll('img').forEach(img => {
                // Chercher une légende
                let caption = '';
                const figure = img.closest('figure');
                if (figure) {
                    const figcaption = figure.querySelector('figcaption');
                    if (figcaption) {
                        caption = figcaption.textContent.trim();
                    }
                }
                
                images.push({
                    src: img.src || '',
                    alt: img.alt || '',
                    title: img.title || '',
                    width: img.width || 0,
                    height: img.height || 0,
                    caption: caption
                });
            });
            
            return images;
        }

        /**
         * Extraire les données SEO
         */
        extractSEOData() {
            const seo = {
                metaTitle: '',
                metaDescription: '',
                focusKeyword: '',
                breadcrumbs: '',
                canonical: '',
                robots: {}
            };
            
            // RankMath
            if (window.rankMath) {
                seo.metaTitle = document.querySelector('.rank-math-title')?.value || '';
                seo.metaDescription = document.querySelector('.rank-math-description')?.value || '';
                seo.focusKeyword = document.querySelector('.rank-math-focus-keyword input')?.value || '';
            }
            
            // Yoast (si présent)
            if (window.YoastSEO) {
                seo.metaTitle = document.querySelector('#yoast-google-preview-title')?.textContent || seo.metaTitle;
                seo.metaDescription = document.querySelector('#yoast-google-preview-description')?.textContent || seo.metaDescription;
                seo.focusKeyword = document.querySelector('#focus-keyword-input-metabox')?.value || seo.focusKeyword;
            }
            
            return seo;
        }

        /**
         * Compter les mots
         */
        countWords(content) {
            let text = '';
            
            const extractText = (items) => {
                items.forEach(item => {
                    if (item.text) {
                        text += ' ' + item.text;
                    }
                    if (item.children) {
                        extractText(item.children);
                    }
                    if (item.items) {
                        text += ' ' + item.items.join(' ');
                    }
                });
            };
            
            if (Array.isArray(content)) {
                extractText(content);
            }
            
            return text.trim().split(/\s+/).filter(word => word.length > 0).length;
        }

        /**
         * Compter les caractères
         */
        countChars(content) {
            let text = '';
            
            const extractText = (items) => {
                items.forEach(item => {
                    if (item.text) {
                        text += item.text;
                    }
                    if (item.children) {
                        extractText(item.children);
                    }
                    if (item.items) {
                        text += item.items.join('');
                    }
                });
            };
            
            if (Array.isArray(content)) {
                extractText(content);
            }
            
            return text.length;
        }

        /**
         * Obtenir l'ID du post
         */
        getPostId() {
            // Depuis Gutenberg
            if (window.wp?.data?.select('core/editor')) {
                return window.wp.data.select('core/editor').getCurrentPostId();
            }
            
            // Depuis le DOM
            const postIdField = document.querySelector('#post_ID');
            if (postIdField) {
                return parseInt(postIdField.value);
            }
            
            // Depuis l'URL
            const urlParams = new URLSearchParams(window.location.search);
            const postParam = urlParams.get('post');
            if (postParam) {
                return parseInt(postParam);
            }
            
            return 0;
        }

        /**
         * Obtenir le type de post
         */
        getPostType() {
            // Depuis Gutenberg
            if (window.wp?.data?.select('core/editor')) {
                return window.wp.data.select('core/editor').getCurrentPostType();
            }
            
            // Depuis le DOM
            const postTypeField = document.querySelector('#post_type');
            if (postTypeField) {
                return postTypeField.value;
            }
            
            return 'post';
        }

        /**
         * Obtenir le statut du post
         */
        getPostStatus() {
            // Depuis Gutenberg
            if (window.wp?.data?.select('core/editor')) {
                return window.wp.data.select('core/editor').getEditedPostAttribute('status');
            }
            
            // Depuis le DOM
            const statusField = document.querySelector('#post_status');
            if (statusField) {
                return statusField.value;
            }
            
            return 'draft';
        }

        /**
         * Obtenir l'auteur
         */
        getAuthor() {
            // Depuis Gutenberg
            if (window.wp?.data?.select('core')) {
                const authorId = window.wp.data.select('core/editor').getCurrentPostAttribute('author');
                const author = window.wp.data.select('core').getUser(authorId);
                return author?.name || '';
            }
            
            // Depuis le DOM
            const authorField = document.querySelector('#post_author_override');
            if (authorField) {
                const selectedOption = authorField.options[authorField.selectedIndex];
                return selectedOption?.text || '';
            }
            
            return '';
        }

        /**
         * Obtenir le permalien
         */
        getPermalink() {
            // Depuis Gutenberg
            if (window.wp?.data?.select('core/editor')) {
                return window.wp.data.select('core/editor').getPermalink();
            }
            
            // Depuis le DOM
            const permalinkField = document.querySelector('#sample-permalink a');
            if (permalinkField) {
                return permalinkField.href;
            }
            
            return '';
        }

        /**
         * Enrichir avec les métadonnées
         */
        enrichWithMetadata(data) {
            // Ajouter les taxonomies
            data.taxonomies = this.extractTaxonomies();
            
            // Ajouter les champs personnalisés
            data.customFields = this.extractCustomFields();
            
            // Ajouter les statistiques
            data.statistics = {
                paragraphCount: data.content.filter(item => item.type === 'paragraph').length,
                headingCount: data.headings.length,
                imageCount: data.images.length,
                linkCount: data.links.length,
                internalLinkCount: data.links.filter(link => link.type === 'internal').length,
                externalLinkCount: data.links.filter(link => link.type === 'external').length,
                averageWordPerParagraph: this.calculateAverageWords(data.content)
            };
            
            return data;
        }

        /**
         * Extraire les taxonomies
         */
        extractTaxonomies() {
            const taxonomies = {
                categories: [],
                tags: [],
                custom: {}
            };
            
            // Catégories
            document.querySelectorAll('#categorychecklist input:checked').forEach(input => {
                const label = input.closest('label');
                if (label) {
                    taxonomies.categories.push({
                        id: input.value,
                        name: label.textContent.trim()
                    });
                }
            });
            
            // Tags
            document.querySelectorAll('.tagchecklist span.screen-reader-text').forEach(span => {
                const text = span.textContent.replace('Remove term:', '').trim();
                if (text) {
                    taxonomies.tags.push(text);
                }
            });
            
            // Ou depuis le champ de tags
            const tagInput = document.querySelector('#new-tag-post_tag');
            if (tagInput && tagInput.value) {
                taxonomies.tags.push(...tagInput.value.split(',').map(tag => tag.trim()));
            }
            
            return taxonomies;
        }

        /**
         * Extraire les champs personnalisés
         */
        extractCustomFields() {
            const customFields = {};
            
            // ACF (Advanced Custom Fields)
            if (window.acf) {
                try {
                    const fields = window.acf.getFields();
                    fields.forEach(field => {
                        customFields[field.data.name] = field.val();
                    });
                } catch (e) {
                    this.logger.debug('ACF fields extraction failed:', e);
                }
            }
            
            // Metaboxes standards
            document.querySelectorAll('#postcustom tr').forEach(row => {
                const key = row.querySelector('.left input[type="text"]')?.value;
                const value = row.querySelector('.right textarea')?.value;
                if (key && value) {
                    customFields[key] = value;
                }
            });
            
            return customFields;
        }

        /**
         * Calculer la moyenne de mots
         */
        calculateAverageWords(content) {
            const paragraphs = content.filter(item => item.type === 'paragraph');
            
            if (paragraphs.length === 0) return 0;
            
            const totalWords = paragraphs.reduce((sum, p) => {
                const words = p.text.trim().split(/\s+/).filter(w => w.length > 0);
                return sum + words.length;
            }, 0);
            
            return Math.round(totalWords / paragraphs.length);
        }

        /**
         * Valider les données extraites
         */
        validateData(data) {
            const errors = [];
            
            // Validation basique
            if (!data.title || data.title.trim() === '') {
                errors.push('Title is empty');
            }
            
            if (!data.content || data.content.length === 0) {
                errors.push('Content is empty');
            }
            
            if (data.meta.wordCount < 100) {
                errors.push('Content is too short (less than 100 words)');
            }
            
            // Validation SEO
            if (data.seo.focusKeyword && !data.title.toLowerCase().includes(data.seo.focusKeyword.toLowerCase())) {
                errors.push('Focus keyword not found in title');
            }
            
            // Validation des liens
            const brokenLinks = data.links.filter(link => {
                return link.url && (link.url.includes('undefined') || link.url.includes('null'));
            });
            
            if (brokenLinks.length > 0) {
                errors.push(`Found ${brokenLinks.length} potentially broken links`);
            }
            
            // Validation des images
            const missingAltText = data.images.filter(img => !img.alt);
            if (missingAltText.length > 0) {
                errors.push(`${missingAltText.length} images missing alt text`);
            }
            
            return {
                isValid: errors.length === 0,
                errors: errors
            };
        }

        /**
         * Méthode de retry en cas d'échec
         */
        async retryExtraction(attemptNumber = 1) {
            if (attemptNumber > this.retryCount) {
                throw new Error('Maximum retry attempts reached');
            }
            
            this.logger.info(`Retry attempt ${attemptNumber}/${this.retryCount}`);
            
            await new Promise(resolve => setTimeout(resolve, this.retryDelay * attemptNumber));
            
            try {
                return await this.extract();
            } catch (error) {
                return await this.retryExtraction(attemptNumber + 1);
            }
        }
    }

    // Exposer globalement
    window.RMCUWordPressParser = RMCUWordPressParser;

})(window);