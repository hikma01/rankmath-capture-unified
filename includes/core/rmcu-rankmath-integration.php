<?php
/**
 * RMCU RankMath Integration - Intégration avec RankMath SEO
 *
 * @package    RMCU_Plugin
 * @subpackage RMCU_Plugin/includes
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RMCU_RankMath_Integration {
    
    /**
     * Instance du logger
     */
    private $logger;
    
    /**
     * État de RankMath
     */
    private $rankmath_active = false;
    
    /**
     * Données SEO personnalisées
     */
    private $custom_seo_data = [];
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->logger = new RMCU_Logger();
        $this->check_rankmath_status();
        
        if ($this->rankmath_active) {
            $this->init_hooks();
        }
    }
    
    /**
     * Vérifier si RankMath est actif
     */
    private function check_rankmath_status() {
        $this->rankmath_active = class_exists('RankMath');
        
        if (!$this->rankmath_active) {
            $this->logger->info('RankMath not detected, integration disabled');
        } else {
            $this->logger->info('RankMath detected, initializing integration');
        }
    }
    
    /**
     * Initialiser les hooks
     */
    private function init_hooks() {
        // Modifications des meta tags
        add_filter('rank_math/frontend/title', [$this, 'modify_title'], 10, 1);
        add_filter('rank_math/frontend/description', [$this, 'modify_description'], 10, 1);
        add_filter('rank_math/frontend/canonical', [$this, 'modify_canonical'], 10, 1);
        add_filter('rank_math/frontend/robots', [$this, 'modify_robots'], 10, 1);
        
        // Schema.org
        add_filter('rank_math/snippet/rich_snippet_article_entity', [$this, 'modify_article_schema'], 10, 1);
        add_filter('rank_math/snippet/rich_snippet_product_entity', [$this, 'modify_product_schema'], 10, 1);
        add_filter('rank_math/json_ld', [$this, 'add_custom_schema'], 10, 2);
        
        // Sitemap
        add_filter('rank_math/sitemap/entry', [$this, 'modify_sitemap_entry'], 10, 3);
        add_filter('rank_math/sitemap/urlset', [$this, 'add_custom_sitemap_urls'], 10, 1);
        add_action('rank_math/sitemap/index', [$this, 'add_custom_sitemaps']);
        
        // Breadcrumbs
        add_filter('rank_math/frontend/breadcrumb/items', [$this, 'modify_breadcrumbs'], 10, 2);
        add_filter('rank_math/frontend/breadcrumb/html', [$this, 'modify_breadcrumb_html'], 10, 3);
        
        // Open Graph
        add_filter('rank_math/opengraph/facebook/og_title', [$this, 'modify_og_title'], 10, 1);
        add_filter('rank_math/opengraph/facebook/og_description', [$this, 'modify_og_description'], 10, 1);
        add_filter('rank_math/opengraph/facebook/og_image', [$this, 'modify_og_image'], 10, 1);
        add_filter('rank_math/opengraph/facebook', [$this, 'add_custom_og_tags'], 10, 1);
        
        // Twitter Cards
        add_filter('rank_math/opengraph/twitter/twitter_title', [$this, 'modify_twitter_title'], 10, 1);
        add_filter('rank_math/opengraph/twitter/twitter_description', [$this, 'modify_twitter_description'], 10, 1);
        add_filter('rank_math/opengraph/twitter/twitter_image', [$this, 'modify_twitter_image'], 10, 1);
        
        // Admin
        add_action('rank_math/metabox/process_fields', [$this, 'save_custom_meta'], 10, 2);
        add_action('rank_math/admin/settings/general', [$this, 'add_settings_section'], 10, 1);
        
        // Analytics
        add_filter('rank_math/analytics/gtag_events', [$this, 'add_tracking_events'], 10, 1);
        
        // Redirections
        add_filter('rank_math/redirection/pre_redirect', [$this, 'handle_custom_redirects'], 10, 2);
        
        // SEO Score
        add_filter('rank_math/seo_score', [$this, 'modify_seo_score'], 10, 2);
        
        // Content Analysis
        add_filter('rank_math/researches/tests', [$this, 'add_custom_tests'], 10, 1);
        
        // Keywords
        add_filter('rank_math/frontend/keywords', [$this, 'add_dynamic_keywords'], 10, 1);
        
        // Actions personnalisées
        add_action('init', [$this, 'register_custom_variables']);
        add_action('wp_head', [$this, 'add_custom_head_tags'], 5);
        add_action('wp_footer', [$this, 'add_tracking_scripts']);
    }
    
    /**
     * Modifier le titre
     *
     * @param string $title
     * @return string
     */
    public function modify_title($title) {
        // Ajouter des données dynamiques au titre
        if (is_singular('rmcu_content')) {
            $custom_title = get_post_meta(get_the_ID(), 'rmcu_seo_title', true);
            if ($custom_title) {
                return $this->replace_variables($custom_title);
            }
        }
        
        // Modifier les titres des archives
        if (is_archive()) {
            $title = apply_filters('rmcu_rankmath_archive_title', $title);
        }
        
        return $title;
    }
    
    /**
     * Modifier la description
     *
     * @param string $description
     * @return string
     */
    public function modify_description($description) {
        if (is_singular('rmcu_content')) {
            $custom_desc = get_post_meta(get_the_ID(), 'rmcu_seo_description', true);
            if ($custom_desc) {
                return $this->replace_variables($custom_desc);
            }
        }
        
        // Générer une description dynamique si nécessaire
        if (empty($description) && get_option('rmcu_auto_generate_desc')) {
            $description = $this->generate_dynamic_description();
        }
        
        return $description;
    }
    
    /**
     * Modifier l'URL canonique
     *
     * @param string $canonical
     * @return string
     */
    public function modify_canonical($canonical) {
        // Gérer les URLs canoniques personnalisées
        if (is_singular('rmcu_content')) {
            $custom_canonical = get_post_meta(get_the_ID(), 'rmcu_canonical_url', true);
            if ($custom_canonical) {
                return $custom_canonical;
            }
        }
        
        // Gérer les paramètres de tracking
        if (get_option('rmcu_strip_tracking_params')) {
            $canonical = remove_query_arg(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'], $canonical);
        }
        
        return $canonical;
    }
    
    /**
     * Modifier les directives robots
     *
     * @param array $robots
     * @return array
     */
    public function modify_robots($robots) {
        // Ajouter des directives personnalisées
        if (is_singular('rmcu_content')) {
            $custom_robots = get_post_meta(get_the_ID(), 'rmcu_robots', true);
            if ($custom_robots) {
                $robots = array_merge($robots, explode(',', $custom_robots));
            }
        }
        
        // Gérer les pages de filtres
        if (isset($_GET['filter']) && get_option('rmcu_noindex_filtered')) {
            $robots['index'] = 'noindex';
        }
        
        return $robots;
    }
    
    /**
     * Modifier le schema Article
     *
     * @param array $entity
     * @return array
     */
    public function modify_article_schema($entity) {
        // Ajouter des données personnalisées au schema
        $entity['custom_property'] = get_post_meta(get_the_ID(), 'rmcu_schema_property', true);
        
        // Ajouter l'auteur étendu
        if (get_option('rmcu_extended_author_schema')) {
            $entity['author'] = $this->get_extended_author_schema($entity['author']);
        }
        
        // Ajouter les ratings personnalisés
        if ($rating = $this->get_custom_rating()) {
            $entity['aggregateRating'] = $rating;
        }
        
        return $entity;
    }
    
    /**
     * Modifier le schema Product
     *
     * @param array $entity
     * @return array
     */
    public function modify_product_schema($entity) {
        // Intégration avec WooCommerce ou système de produits personnalisé
        if (function_exists('wc_get_product')) {
            $product = wc_get_product(get_the_ID());
            if ($product) {
                $entity['offers'] = $this->get_product_offers($product);
                $entity['brand'] = $this->get_product_brand($product);
            }
        }
        
        return $entity;
    }
    
    /**
     * Ajouter un schema personnalisé
     *
     * @param array $data
     * @param object $jsonld
     * @return array
     */
    public function add_custom_schema($data, $jsonld) {
        // Ajouter un schema Organization étendu
        if (is_front_page() && get_option('rmcu_add_organization_schema')) {
            $data[] = $this->get_organization_schema();
        }
        
        // Ajouter un schema LocalBusiness si configuré
        if (get_option('rmcu_local_business_schema')) {
            $data[] = $this->get_local_business_schema();
        }
        
        // Schema FAQ pour les pages spécifiques
        if (is_singular() && has_shortcode(get_the_content(), 'rmcu_faq')) {
            $data[] = $this->get_faq_schema();
        }
        
        // Schema HowTo pour les tutoriels
        if (is_singular() && get_post_meta(get_the_ID(), 'rmcu_is_tutorial', true)) {
            $data[] = $this->get_howto_schema();
        }
        
        return $data;
    }
    
    /**
     * Modifier une entrée du sitemap
     *
     * @param array $url
     * @param string $type
     * @param object $object
     * @return array
     */
    public function modify_sitemap_entry($url, $type, $object) {
        // Ajouter la priorité personnalisée
        if ($priority = get_post_meta($object->ID, 'rmcu_sitemap_priority', true)) {
            $url['priority'] = $priority;
        }
        
        // Ajouter la fréquence de changement personnalisée
        if ($changefreq = get_post_meta($object->ID, 'rmcu_sitemap_changefreq', true)) {
            $url['changefreq'] = $changefreq;
        }
        
        // Ajouter des images supplémentaires
        if ($images = $this->get_additional_images($object->ID)) {
            $url['images'] = array_merge($url['images'] ?? [], $images);
        }
        
        return $url;
    }
    
    /**
     * Ajouter des URLs personnalisées au sitemap
     *
     * @param string $urlset
     * @return string
     */
    public function add_custom_sitemap_urls($urlset) {
        $custom_urls = get_option('rmcu_custom_sitemap_urls', []);
        
        foreach ($custom_urls as $custom_url) {
            $urlset .= $this->generate_sitemap_url_xml($custom_url);
        }
        
        return $urlset;
    }
    
    /**
     * Ajouter des sitemaps personnalisés
     */
    public function add_custom_sitemaps() {
        // Sitemap pour le contenu RMCU
        if (get_option('rmcu_enable_custom_sitemap')) {
            echo '<sitemap>';
            echo '<loc>' . home_url('/sitemap-rmcu.xml') . '</loc>';
            echo '<lastmod>' . date('c') . '</lastmod>';
            echo '</sitemap>';
        }
    }
    
    /**
     * Modifier les breadcrumbs
     *
     * @param array $crumbs
     * @param array $args
     * @return array
     */
    public function modify_breadcrumbs($crumbs, $args) {
        // Ajouter des éléments personnalisés aux breadcrumbs
        if (is_singular('rmcu_content')) {
            $category = get_post_meta(get_the_ID(), 'rmcu_category', true);
            if ($category) {
                array_splice($crumbs, -1, 0, [[
                    'text' => $category,
                    'url' => home_url('/rmcu-category/' . sanitize_title($category))
                ]]);
            }
        }
        
        return $crumbs;
    }
    
    /**
     * Modifier le HTML des breadcrumbs
     *
     * @param string $html
     * @param array $crumbs
     * @param object $class
     * @return string
     */
    public function modify_breadcrumb_html($html, $crumbs, $class) {
        // Ajouter le schema breadcrumb si nécessaire
        if (get_option('rmcu_add_breadcrumb_schema')) {
            $html = $this->add_breadcrumb_schema($html, $crumbs);
        }
        
        return $html;
    }
    
    /**
     * Enregistrer des variables personnalisées
     */
    public function register_custom_variables() {
        if (!class_exists('RankMath\Helper')) {
            return;
        }
        
        // Variable pour le prix
        RankMath\Helper::register_var_replacement(
            'rmcu_price',
            [$this, 'get_rmcu_price'],
            [
                'name' => 'RMCU Price',
                'description' => 'Prix du produit RMCU',
                'variable' => 'rmcu_price'
            ]
        );
        
        // Variable pour la disponibilité
        RankMath\Helper::register_var_replacement(
            'rmcu_availability',
            [$this, 'get_rmcu_availability'],
            [
                'name' => 'RMCU Availability',
                'description' => 'Disponibilité du produit RMCU',
                'variable' => 'rmcu_availability'
            ]
        );
        
        // Variable dynamique pour la date
        RankMath\Helper::register_var_replacement(
            'rmcu_current_year',
            function() { return date('Y'); },
            [
                'name' => 'RMCU Current Year',
                'description' => 'Année actuelle',
                'variable' => 'rmcu_current_year'
            ]
        );
    }
    
    /**
     * Ajouter des tags personnalisés dans le head
     */
    public function add_custom_head_tags() {
        // Meta tags personnalisés
        $custom_meta = get_option('rmcu_custom_meta_tags', []);
        foreach ($custom_meta as $meta) {
            printf('<meta name="%s" content="%s" />' . "\n", 
                esc_attr($meta['name']), 
                esc_attr($meta['content'])
            );
        }
        
        // Preconnect pour améliorer les performances
        $preconnect_urls = get_option('rmcu_preconnect_urls', []);
        foreach ($preconnect_urls as $url) {
            printf('<link rel="preconnect" href="%s" crossorigin />' . "\n", esc_url($url));
        }
        
        // DNS Prefetch
        $dns_prefetch = get_option('rmcu_dns_prefetch', []);
        foreach ($dns_prefetch as $domain) {
            printf('<link rel="dns-prefetch" href="%s" />' . "\n", esc_url($domain));
        }
    }
    
    /**
     * Ajouter des scripts de tracking
     */
    public function add_tracking_scripts() {
        if (!get_option('rmcu_enable_tracking')) {
            return;
        }
        ?>
        <script>
        (function() {
            // Script de tracking RMCU
            var rmcuTracking = {
                init: function() {
                    this.trackPageView();
                    this.trackEvents();
                },
                
                trackPageView: function() {
                    if (typeof gtag !== 'undefined') {
                        gtag('event', 'rmcu_page_view', {
                            'page_type': '<?php echo esc_js(get_post_type()); ?>',
                            'page_id': '<?php echo esc_js(get_the_ID()); ?>'
                        });
                    }
                },
                
                trackEvents: function() {
                    // Tracking des clics sur les liens
                    document.querySelectorAll('a[data-rmcu-track]').forEach(function(link) {
                        link.addEventListener('click', function() {
                            if (typeof gtag !== 'undefined') {
                                gtag('event', 'rmcu_link_click', {
                                    'link_url': this.href,
                                    'link_text': this.textContent
                                });
                            }
                        });
                    });
                }
            };
            
            rmcuTracking.init();
        })();
        </script>
        <?php
    }
    
    /**
     * Obtenir le schema Organization
     *
     * @return array
     */
    private function get_organization_schema() {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => get_option('rmcu_org_name', get_bloginfo('name')),
            'url' => home_url(),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => get_option('rmcu_org_logo', '')
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => get_option('rmcu_org_phone', ''),
                'contactType' => 'customer service',
                'availableLanguage' => get_option('rmcu_org_languages', ['French', 'English'])
            ],
            'sameAs' => get_option('rmcu_social_profiles', [])
        ];
    }
    
    /**
     * Obtenir le schema LocalBusiness
     *
     * @return array
     */
    private function get_local_business_schema() {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => get_option('rmcu_business_name'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => get_option('rmcu_street_address'),
                'addressLocality' => get_option('rmcu_city'),
                'addressRegion' => get_option('rmcu_region'),
                'postalCode' => get_option('rmcu_postal_code'),
                'addressCountry' => get_option('rmcu_country')
            ],
            'telephone' => get_option('rmcu_business_phone'),
            'openingHours' => get_option('rmcu_opening_hours', []),
            'priceRange' => get_option('rmcu_price_range', '$$')
        ];
    }
    
    /**
     * Obtenir le schema FAQ
     *
     * @return array
     */
    private function get_faq_schema() {
        $faqs = get_post_meta(get_the_ID(), 'rmcu_faqs', true);
        $questions = [];
        
        if (is_array($faqs)) {
            foreach ($faqs as $faq) {
                $questions[] = [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faq['answer']
                    ]
                ];
            }
        }
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $questions
        ];
    }
    
    /**
     * Obtenir le schema HowTo
     *
     * @return array
     */
    private function get_howto_schema() {
        $steps = get_post_meta(get_the_ID(), 'rmcu_tutorial_steps', true);
        $howto_steps = [];
        
        if (is_array($steps)) {
            foreach ($steps as $index => $step) {
                $howto_steps[] = [
                    '@type' => 'HowToStep',
                    'position' => $index + 1,
                    'name' => $step['title'],
                    'text' => $step['description'],
                    'image' => $step['image'] ?? ''
                ];
            }
        }
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => get_the_title(),
            'description' => get_the_excerpt(),
            'step' => $howto_steps,
            'totalTime' => get_post_meta(get_the_ID(), 'rmcu_tutorial_duration', true)
        ];
    }
    
    /**
     * Remplacer les variables
     *
     * @param string $text
     * @return string
     */
    private function replace_variables($text) {
        $replacements = [
            '%current_year%' => date('Y'),
            '%site_name%' => get_bloginfo('name'),
            '%post_title%' => get_the_title(),
            '%author%' => get_the_author(),
            '%category%' => $this->get_primary_category()
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    /**
     * Générer une description dynamique
     *
     * @return string
     */
    private function generate_dynamic_description() {
        $content = get_the_content();
        $content = wp_strip_all_tags($content);
        $content = str_replace(["\r", "\n"], ' ', $content);
        
        return wp_trim_words($content, 30);
    }
    
    /**
     * Obtenir la catégorie principale
     *
     * @return string
     */
    private function get_primary_category() {
        $categories = get_the_category();
        if (!empty($categories)) {
            return $categories[0]->name;
        }
        return '';
    }
    
    /**
     * Obtenir les images supplémentaires
     *
     * @param int $post_id
     * @return array
     */
    private function get_additional_images($post_id) {
        $images = [];
        $gallery = get_post_meta($post_id, 'rmcu_gallery', true);
        
        if (is_array($gallery)) {
            foreach ($gallery as $image_id) {
                $image_url = wp_get_attachment_url($image_id);
                if ($image_url) {
                    $images[] = [
                        'loc' => $image_url,
                        'title' => get_the_title($image_id)
                    ];
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Ajouter une section de paramètres
     *
     * @param object $cmb
     */
    public function add_settings_section($cmb) {
        $cmb->add_field([
            'id' => 'rmcu_rankmath_settings',
            'type' => 'title',
            'name' => __('RMCU Integration Settings', 'rmcu')
        ]);
        
        $cmb->add_field([
            'id' => 'rmcu_auto_generate_desc',
            'type' => 'toggle',
            'name' => __('Auto-generate descriptions', 'rmcu'),
            'desc' => __('Automatically generate meta descriptions when empty', 'rmcu')
        ]);
        
        $cmb->add_field([
            'id' => 'rmcu_strip_tracking_params',
            'type' => 'toggle',
            'name' => __('Strip tracking parameters', 'rmcu'),
            'desc' => __('Remove UTM parameters from canonical URLs', 'rmcu')
        ]);
    }
    
    /**
     * Sauvegarder les meta personnalisées
     *
     * @param object $object_id
     * @param object $cmb
     */
    public function save_custom_meta($object_id, $cmb) {
        // Sauvegarder les données SEO personnalisées
        $fields = ['rmcu_seo_title', 'rmcu_seo_description', 'rmcu_canonical_url'];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($object_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    /**
     * Obtenir le statut de l'intégration
     *
     * @return array
     */
    public function get_integration_status() {
        return [
            'rankmath_active' => $this->rankmath_active,
            'features_enabled' => [
                'schema' => get_option('rmcu_enable_schema', true),
                'sitemap' => get_option('rmcu_enable_sitemap', true),
                'tracking' => get_option('rmcu_enable_tracking', false),
                'breadcrumbs' => get_option('rmcu_modify_breadcrumbs', true)
            ],
            'custom_variables' => $this->get_registered_variables(),
            'last_sync' => get_option('rmcu_rankmath_last_sync', '')
        ];
    }
    
    /**
     * Obtenir les variables enregistrées
     *
     * @return array
     */
    private function get_registered_variables() {
        return [
            'rmcu_price',
            'rmcu_availability',
            'rmcu_current_year'
        ];
    }
}