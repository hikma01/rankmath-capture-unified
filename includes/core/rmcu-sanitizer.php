<?php
/**
 * RMCU Sanitizer - Classe de sanitisation des données
 *
 * @package    RMCU_Plugin
 * @subpackage RMCU_Plugin/includes
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RMCU_Sanitizer {
    
    /**
     * Règles de sanitisation par type
     */
    private $sanitization_rules = [
        'text' => 'sanitize_text',
        'email' => 'sanitize_email',
        'url' => 'sanitize_url',
        'textarea' => 'sanitize_textarea',
        'html' => 'sanitize_html',
        'key' => 'sanitize_key',
        'title' => 'sanitize_title',
        'filename' => 'sanitize_filename',
        'number' => 'sanitize_number',
        'integer' => 'sanitize_integer',
        'float' => 'sanitize_float',
        'boolean' => 'sanitize_boolean',
        'array' => 'sanitize_array',
        'json' => 'sanitize_json',
        'color' => 'sanitize_hex_color',
        'phone' => 'sanitize_phone',
        'date' => 'sanitize_date',
        'time' => 'sanitize_time',
        'datetime' => 'sanitize_datetime',
        'slug' => 'sanitize_slug',
        'username' => 'sanitize_username',
        'password' => 'sanitize_password',
        'sql' => 'sanitize_sql',
        'javascript' => 'sanitize_javascript',
        'css' => 'sanitize_css',
        'meta_key' => 'sanitize_meta_key',
        'option_name' => 'sanitize_option_name'
    ];
    
    /**
     * Tags HTML autorisés par défaut
     */
    private $allowed_html = [];
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->set_default_allowed_html();
    }
    
    /**
     * Définir les tags HTML autorisés par défaut
     */
    private function set_default_allowed_html() {
        $this->allowed_html = [
            'a' => [
                'href' => [],
                'title' => [],
                'target' => [],
                'rel' => []
            ],
            'br' => [],
            'em' => [],
            'strong' => [],
            'b' => [],
            'i' => [],
            'u' => [],
            'p' => [
                'class' => [],
                'id' => []
            ],
            'div' => [
                'class' => [],
                'id' => []
            ],
            'span' => [
                'class' => [],
                'id' => []
            ],
            'h1' => ['class' => [], 'id' => []],
            'h2' => ['class' => [], 'id' => []],
            'h3' => ['class' => [], 'id' => []],
            'h4' => ['class' => [], 'id' => []],
            'h5' => ['class' => [], 'id' => []],
            'h6' => ['class' => [], 'id' => []],
            'ul' => ['class' => []],
            'ol' => ['class' => []],
            'li' => ['class' => []],
            'blockquote' => ['cite' => []],
            'img' => [
                'src' => [],
                'alt' => [],
                'title' => [],
                'width' => [],
                'height' => [],
                'class' => []
            ],
            'table' => ['class' => []],
            'thead' => [],
            'tbody' => [],
            'tfoot' => [],
            'tr' => [],
            'th' => ['scope' => [], 'colspan' => [], 'rowspan' => []],
            'td' => ['colspan' => [], 'rowspan' => []],
            'code' => [],
            'pre' => ['class' => []],
            'del' => [],
            'ins' => [],
            'sub' => [],
            'sup' => [],
            'mark' => []
        ];
    }
    
    /**
     * Méthode principale de sanitisation
     *
     * @param mixed $data Donnée à sanitiser
     * @param string $type Type de sanitisation
     * @param array $options Options supplémentaires
     * @return mixed Donnée sanitisée
     */
    public function sanitize($data, $type = 'text', $options = []) {
        // Si la donnée est nulle, la retourner telle quelle
        if (is_null($data)) {
            return null;
        }
        
        // Appliquer un filtre avant sanitisation
        $data = apply_filters('rmcu_before_sanitize', $data, $type, $options);
        
        // Vérifier si le type de sanitisation existe
        if (!isset($this->sanitization_rules[$type])) {
            $type = 'text';
        }
        
        // Appeler la méthode de sanitisation appropriée
        $method = $this->sanitization_rules[$type];
        if (method_exists($this, $method)) {
            $sanitized = $this->$method($data, $options);
        } else {
            $sanitized = $this->sanitize_text($data);
        }
        
        // Appliquer un filtre après sanitisation
        return apply_filters('rmcu_after_sanitize', $sanitized, $type, $options);
    }
    
    /**
     * Sanitiser un texte simple
     *
     * @param string $text
     * @param array $options
     * @return string
     */
    public function sanitize_text($text, $options = []) {
        $text = (string) $text;
        
        // Options par défaut
        $defaults = [
            'strip_tags' => true,
            'remove_breaks' => false,
            'trim' => true,
            'max_length' => 0
        ];
        $options = wp_parse_args($options, $defaults);
        
        if ($options['strip_tags']) {
            $text = wp_strip_all_tags($text, $options['remove_breaks']);
        }
        
        $text = sanitize_text_field($text);
        
        if ($options['trim']) {
            $text = trim($text);
        }
        
        if ($options['max_length'] > 0) {
            $text = substr($text, 0, $options['max_length']);
        }
        
        return $text;
    }
    
    /**
     * Sanitiser un email
     *
     * @param string $email
     * @param array $options
     * @return string
     */
    public function sanitize_email($email, $options = []) {
        $email = sanitize_email($email);
        
        // Validation supplémentaire si demandée
        if (!empty($options['validate']) && !is_email($email)) {
            return '';
        }
        
        // Convertir en minuscules si demandé
        if (!empty($options['lowercase'])) {
            $email = strtolower($email);
        }
        
        return $email;
    }
    
    /**
     * Sanitiser une URL
     *
     * @param string $url
     * @param array $options
     * @return string
     */
    public function sanitize_url($url, $options = []) {
        $defaults = [
            'protocols' => ['http', 'https', 'ftp', 'mailto'],
            'encode' => false
        ];
        $options = wp_parse_args($options, $defaults);
        
        $url = esc_url_raw($url, $options['protocols']);
        
        if ($options['encode']) {
            $url = esc_url($url);
        }
        
        return $url;
    }
    
    /**
     * Sanitiser un textarea
     *
     * @param string $text
     * @param array $options
     * @return string
     */
    public function sanitize_textarea($text, $options = []) {
        $defaults = [
            'preserve_breaks' => true,
            'max_length' => 5000
        ];
        $options = wp_parse_args($options, $defaults);
        
        $text = sanitize_textarea_field($text);
        
        if (!$options['preserve_breaks']) {
            $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        }
        
        if ($options['max_length'] > 0) {
            $text = substr($text, 0, $options['max_length']);
        }
        
        return $text;
    }
    
    /**
     * Sanitiser du HTML
     *
     * @param string $html
     * @param array $options
     * @return string
     */
    public function sanitize_html($html, $options = []) {
        $defaults = [
            'allowed_html' => $this->allowed_html,
            'allowed_protocols' => ['http', 'https', 'mailto'],
            'strip_shortcodes' => false
        ];
        $options = wp_parse_args($options, $defaults);
        
        if ($options['strip_shortcodes']) {
            $html = strip_shortcodes($html);
        }
        
        // Utiliser wp_kses pour nettoyer le HTML
        $html = wp_kses($html, $options['allowed_html'], $options['allowed_protocols']);
        
        // Nettoyer les attributs de style dangereux
        $html = $this->remove_dangerous_styles($html);
        
        return $html;
    }
    
    /**
     * Sanitiser une clé
     *
     * @param string $key
     * @param array $options
     * @return string
     */
    public function sanitize_key($key, $options = []) {
        $defaults = [
            'lowercase' => true,
            'underscore' => true
        ];
        $options = wp_parse_args($options, $defaults);
        
        $key = sanitize_key($key);
        
        if (!$options['underscore']) {
            $key = str_replace('_', '-', $key);
        }
        
        if (!$options['lowercase']) {
            // Permettre les majuscules si demandé
            $key = preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
        }
        
        return $key;
    }
    
    /**
     * Sanitiser un titre
     *
     * @param string $title
     * @param array $options
     * @return string
     */
    public function sanitize_title($title, $options = []) {
        $defaults = [
            'fallback_title' => '',
            'context' => 'save'
        ];
        $options = wp_parse_args($options, $defaults);
        
        return sanitize_title($title, $options['fallback_title'], $options['context']);
    }
    
    /**
     * Sanitiser un nom de fichier
     *
     * @param string $filename
     * @param array $options
     * @return string
     */
    public function sanitize_filename($filename, $options = []) {
        $filename = sanitize_file_name($filename);
        
        // Vérifier l'extension si une liste blanche est fournie
        if (!empty($options['allowed_extensions'])) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if (!in_array(strtolower($extension), $options['allowed_extensions'])) {
                return '';
            }
        }
        
        return $filename;
    }
    
    /**
     * Sanitiser un nombre
     *
     * @param mixed $number
     * @param array $options
     * @return float|int
     */
    public function sanitize_number($number, $options = []) {
        $defaults = [
            'min' => null,
            'max' => null,
            'decimal' => true
        ];
        $options = wp_parse_args($options, $defaults);
        
        // Nettoyer le nombre
        $number = preg_replace('/[^0-9.\-]/', '', $number);
        
        if ($options['decimal']) {
            $number = floatval($number);
        } else {
            $number = intval($number);
        }
        
        // Appliquer les limites min/max
        if ($options['min'] !== null && $number < $options['min']) {
            $number = $options['min'];
        }
        if ($options['max'] !== null && $number > $options['max']) {
            $number = $options['max'];
        }
        
        return $number;
    }
    
    /**
     * Sanitiser un entier
     *
     * @param mixed $integer
     * @param array $options
     * @return int
     */
    public function sanitize_integer($integer, $options = []) {
        $options['decimal'] = false;
        return (int) $this->sanitize_number($integer, $options);
    }
    
    /**
     * Sanitiser un nombre flottant
     *
     * @param mixed $float
     * @param array $options
     * @return float
     */
    public function sanitize_float($float, $options = []) {
        $defaults = [
            'decimals' => 2,
            'min' => null,
            'max' => null
        ];
        $options = wp_parse_args($options, $defaults);
        
        $float = $this->sanitize_number($float, $options);
        
        if ($options['decimals'] >= 0) {
            $float = round($float, $options['decimals']);
        }
        
        return $float;
    }
    
    /**
     * Sanitiser un booléen
     *
     * @param mixed $boolean
     * @param array $options
     * @return bool
     */
    public function sanitize_boolean($boolean, $options = []) {
        if (is_bool($boolean)) {
            return $boolean;
        }
        
        if (is_string($boolean)) {
            $boolean = strtolower($boolean);
            return in_array($boolean, ['1', 'true', 'yes', 'on', 'checked']);
        }
        
        return (bool) $boolean;
    }
    
    /**
     * Sanitiser un tableau
     *
     * @param array $array
     * @param array $options
     * @return array
     */
    public function sanitize_array($array, $options = []) {
        if (!is_array($array)) {
            return [];
        }
        
        $defaults = [
            'recursive' => true,
            'type' => 'text',
            'remove_empty' => false
        ];
        $options = wp_parse_args($options, $defaults);
        
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            $clean_key = $this->sanitize_key($key);
            
            if (is_array($value) && $options['recursive']) {
                $sanitized[$clean_key] = $this->sanitize_array($value, $options);
            } else {
                $sanitized[$clean_key] = $this->sanitize($value, $options['type']);
            }
            
            if ($options['remove_empty'] && empty($sanitized[$clean_key])) {
                unset($sanitized[$clean_key]);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitiser du JSON
     *
     * @param string $json
     * @param array $options
     * @return string
     */
    public function sanitize_json($json, $options = []) {
        $defaults = [
            'decode' => false,
            'assoc' => true
        ];
        $options = wp_parse_args($options, $defaults);
        
        // Décoder le JSON
        $data = json_decode($json, $options['assoc']);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }
        
        // Sanitiser les données
        if (is_array($data)) {
            $data = $this->sanitize_array($data);
        } elseif (is_object($data)) {
            $data = $this->sanitize_object($data);
        } else {
            $data = $this->sanitize_text($data);
        }
        
        // Retourner les données décodées ou réencodées
        if ($options['decode']) {
            return $data;
        }
        
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Sanitiser une couleur hexadécimale
     *
     * @param string $color
     * @param array $options
     * @return string
     */
    public function sanitize_hex_color($color, $options = []) {
        $defaults = [
            'hash' => true
        ];
        $options = wp_parse_args($options, $defaults);
        
        $color = sanitize_hex_color($color);
        
        if (!$options['hash'] && $color) {
            $color = ltrim($color, '#');
        }
        
        return $color;
    }
    
    /**
     * Sanitiser un numéro de téléphone
     *
     * @param string $phone
     * @param array $options
     * @return string
     */
    public function sanitize_phone($phone, $options = []) {
        $defaults = [
            'format' => 'international',
            'country' => 'US'
        ];
        $options = wp_parse_args($options, $defaults);
        
        // Garder uniquement les chiffres et le +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Formater selon le pays si demandé
        if ($options['format'] === 'national' && $options['country'] === 'US') {
            // Format US: (XXX) XXX-XXXX
            if (strlen($phone) === 10) {
                $phone = sprintf('(%s) %s-%s',
                    substr($phone, 0, 3),
                    substr($phone, 3, 3),
                    substr($phone, 6)
                );
            }
        }
        
        return $phone;
    }
    
    /**
     * Sanitiser une date
     *
     * @param string $date
     * @param array $options
     * @return string
     */
    public function sanitize_date($date, $options = []) {
        $defaults = [
            'format' => 'Y-m-d',
            'min' => null,
            'max' => null
        ];
        $options = wp_parse_args($options, $defaults);
        
        // Parser la date
        $timestamp = strtotime($date);
        
        if ($timestamp === false) {
            return '';
        }
        
        // Vérifier les limites
        if ($options['min'] !== null) {
            $min_timestamp = strtotime($options['min']);
            if ($timestamp < $min_timestamp) {
                $timestamp = $min_timestamp;
            }
        }
        
        if ($options['max'] !== null) {
            $max_timestamp = strtotime($options['max']);
            if ($timestamp > $max_timestamp) {
                $timestamp = $max_timestamp;
            }
        }
        
        return date($options['format'], $timestamp);
    }
    
    /**
     * Sanitiser une heure
     *
     * @param string $time
     * @param array $options
     * @return string
     */
    public function sanitize_time($time, $options = []) {
        $defaults = [
            'format' => 'H:i:s'
        ];
        $options = wp_parse_args($options, $defaults);
        
        return $this->sanitize_date($time, $options);
    }
    
    /**
     * Sanitiser un datetime
     *
     * @param string $datetime
     * @param array $options
     * @return string
     */
    public function sanitize_datetime($datetime, $options = []) {
        $defaults = [
            'format' => 'Y-m-d H:i:s'
        ];
        $options = wp_parse_args($options, $defaults);
        
        return $this->sanitize_date($datetime, $options);
    }
    
    /**
     * Sanitiser un slug
     *
     * @param string $slug
     * @param array $options
     * @return string
     */
    public function sanitize_slug($slug, $options = []) {
        return sanitize_title_with_dashes($slug);
    }
    
    /**
     * Sanitiser un nom d'utilisateur
     *
     * @param string $username
     * @param array $options
     * @return string
     */
    public function sanitize_username($username, $options = []) {
        $defaults = [
            'strict' => false
        ];
        $options = wp_parse_args($options, $defaults);
        
        return sanitize_user($username, $options['strict']);
    }
    
    /**
     * Sanitiser un mot de passe
     *
     * @param string $password
     * @param array $options
     * @return string
     */
    public function sanitize_password($password, $options = []) {
        // Les mots de passe ne doivent pas être modifiés
        // mais on peut vérifier leur force
        if (!empty($options['check_strength'])) {
            $strength = $this->check_password_strength($password);
            if ($strength < ($options['min_strength'] ?? 2)) {
                return '';
            }
        }
        
        return $password;
    }
    
    /**
     * Sanitiser du SQL
     *
     * @param string $sql
     * @param array $options
     * @return string
     */
    public function sanitize_sql($sql, $options = []) {
        global $wpdb;
        
        // Échapper le SQL
        return esc_sql($sql);
    }
    
    /**
     * Sanitiser du JavaScript
     *
     * @param string $js
     * @param array $options
     * @return string
     */
    public function sanitize_javascript($js, $options = []) {
        // Supprimer les tags script
        $js = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $js);
        
        // Échapper pour utilisation dans les attributs
        if (!empty($options['attribute'])) {
            $js = esc_js($js);
        }
        
        return $js;
    }
    
    /**
     * Sanitiser du CSS
     *
     * @param string $css
     * @param array $options
     * @return string
     */
    public function sanitize_css($css, $options = []) {
        // Supprimer les balises style
        $css = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $css);
        
        // Supprimer les expressions JavaScript dangereuses
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/javascript:/i', '', $css);
        $css = preg_replace('/behavior\s*:/i', '', $css);
        
        // Nettoyer les imports
        if (empty($options['allow_import'])) {
            $css = preg_replace('/@import/i', '', $css);
        }
        
        return $css;
    }
    
    /**
     * Sanitiser une clé meta
     *
     * @param string $key
     * @param array $options
     * @return string
     */
    public function sanitize_meta_key($key, $options = []) {
        return sanitize_meta($key, '', 'post');
    }
    
    /**
     * Sanitiser un nom d'option
     *
     * @param string $option_name
     * @param array $options
     * @return string
     */
    public function sanitize_option_name($option_name, $options = []) {
        return sanitize_option($option_name, '');
    }
    
    /**
     * Sanitiser un objet
     *
     * @param object $object
     * @param array $options
     * @return object
     */
    private function sanitize_object($object, $options = []) {
        $array = (array) $object;
        $array = $this->sanitize_array($array, $options);
        return (object) $array;
    }
    
    /**
     * Supprimer les styles dangereux
     *
     * @param string $html
     * @return string
     */
    private function remove_dangerous_styles($html) {
        // Supprimer les attributs style contenant javascript:
        $html = preg_replace('/style\s*=\s*["\'].*?javascript:.*?["\']/i', '', $html);
        
        // Supprimer les attributs on* (onclick, onmouseover, etc.)
        $html = preg_replace('/on\w+\s*=\s*["\'].*?["\']/i', '', $html);
        
        return $html;
    }
    
    /**
     * Vérifier la force d'un mot de passe
     *
     * @param string $password
     * @return int Score de 0 à 5
     */
    private function check_password_strength($password) {
        $strength = 0;
        
        // Longueur
        if (strlen($password) >= 8) $strength++;
        if (strlen($password) >= 12) $strength++;
        
        // Complexité
        if (preg_match('/[a-z]/', $password)) $strength++;
        if (preg_match('/[A-Z]/', $password)) $strength++;
        if (preg_match('/[0-9]/', $password)) $strength++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $strength++;
        
        return min($strength, 5);
    }
    
    /**
     * Méthode de sanitisation en masse
     *
     * @param array $data Données à sanitiser
     * @param array $rules Règles de sanitisation
     * @return array
     */
    public function sanitize_fields($data, $rules) {
        $sanitized = [];
        
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                continue;
            }
            
            if (is_array($rule)) {
                $type = $rule['type'] ?? 'text';
                $options = $rule['options'] ?? [];
            } else {
                $type = $rule;
                $options = [];
            }
            
            $sanitized[$field] = $this->sanitize($data[$field], $type, $options);
        }
        
        return $sanitized;
    }
}