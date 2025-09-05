<?php
/**
 * RMCU Sanitizer
 * Sanitisation et validation des données
 * 
 * @package RankMath_Capture_Unified
 * @version 2.0.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour sanitiser et valider les données
 */
class RMCU_Sanitizer {
    
    /**
     * Sanitiser un texte simple
     */
    public function text($input) {
        return sanitize_text_field($input);
    }
    
    /**
     * Sanitiser un textarea
     */
    public function textarea($input) {
        return sanitize_textarea_field($input);
    }
    
    /**
     * Sanitiser un email
     */
    public function email($input) {
        return sanitize_email($input);
    }
    
    /**
     * Sanitiser une URL
     */
    public function url($input, $protocols = null) {
        return esc_url_raw($input, $protocols);
    }
    
    /**
     * Sanitiser un nom de fichier
     */
    public function filename($input) {
        return sanitize_file_name($input);
    }
    
    /**
     * Sanitiser une clé
     */
    public function key($input) {
        return sanitize_key($input);
    }
    
    /**
     * Sanitiser un titre
     */
    public function title($input) {
        return sanitize_title($input);
    }
    
    /**
     * Sanitiser du HTML
     */
    public function html($input, $allowed_html = null) {
        if ($allowed_html === null) {
            $allowed_html = $this->get_allowed_html();
        }
        return wp_kses($input, $allowed_html);
    }
    
    /**
     * Sanitiser du contenu de post
     */
    public function post_content($input) {
        return wp_kses_post($input);
    }
    
    /**
     * Sanitiser un entier
     */
    public function integer($input, $min = null, $max = null) {
        $value = intval($input);
        
        if ($min !== null && $value < $min) {
            $value = $min;
        }
        
        if ($max !== null && $value > $max) {
            $value = $max;
        }
        
        return $value;
    }
    
    /**
     * Sanitiser un nombre décimal
     */
    public function float($input, $min = null, $max = null) {
        $value = floatval($input);
        
        if ($min !== null && $value < $min) {
            $value = $min;
        }
        
        if ($max !== null && $value > $max) {
            $value = $max;
        }
        
        return $value;
    }
    
    /**
     * Sanitiser un booléen
     */
    public function boolean($input) {
        return filter_var($input, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Sanitiser un tableau
     */
    public function array($input, $callback = 'text') {
        if (!is_array($input)) {
            return [];
        }
        
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            $clean_key = $this->key($key);
            
            if (is_array($value)) {
                $sanitized[$clean_key] = $this->array($value, $callback);
            } else {
                if (method_exists($this, $callback)) {
                    $sanitized[$clean_key] = $this->$callback($value);
                } else {
                    $sanitized[$clean_key] = $this->text($value);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitiser du JSON
     */
    public function json($input) {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($this->array($decoded));
            }
        }
        
        return '';
    }
    
    /**
     * Sanitiser une date
     */
    public function date($input, $format = 'Y-m-d') {
        $date = DateTime::createFromFormat($format, $input);
        
        if ($date && $date->format($format) === $input) {
            return $input;
        }
        
        return '';
    }
    
    /**
     * Sanitiser une heure
     */
    public function time($input, $format = 'H:i:s') {
        return $this->date($input, $format);
    }
    
    /**
     * Sanitiser un datetime
     */
    public function datetime($input, $format = 'Y-m-d H:i:s') {
        return $this->date($input, $format);
    }
    
    /**
     * Sanitiser une couleur hex
     */
    public function hex_color($input) {
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $input)) {
            return $input;
        }
        return '';
    }
    
    /**
     * Sanitiser un slug
     */
    public function slug($input) {
        return sanitize_title_with_dashes($input);
    }
    
    /**
     * Sanitiser des classes CSS
     */
    public function css_classes($input) {
        if (is_array($input)) {
            $input = implode(' ', $input);
        }
        
        $classes = explode(' ', $input);
        $sanitized = array_map('sanitize_html_class', $classes);
        
        return implode(' ', array_filter($sanitized));
    }
    
    /**
     * Sanitiser un ID HTML
     */
    public function html_id($input) {
        // Remplacer les caractères non valides
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', $input);
        
        // S'assurer qu'il ne commence pas par un chiffre
        if (preg_match('/^[0-9]/', $id)) {
            $id = 'id-' . $id;
        }
        
        return $id;
    }
    
    /**
     * Sanitiser une option select
     */
    public function select($input, $valid_options) {
        if (in_array($input, $valid_options, true)) {
            return $input;
        }
        
        // Retourner la première option valide par défaut
        return !empty($valid_options) ? $valid_options[0] : '';
    }
    
    /**
     * Sanitiser des checkboxes multiples
     */
    public function checkboxes($input, $valid_options) {
        if (!is_array($input)) {
            return [];
        }
        
        return array_intersect($input, $valid_options);
    }
    
    /**
     * Sanitiser un numéro de téléphone
     */
    public function phone($input) {
        // Garder seulement les chiffres, +, -, (, ), et espaces
        return preg_replace('/[^0-9+\-() ]/', '', $input);
    }
    
    /**
     * Sanitiser un code postal
     */
    public function postal_code($input, $country = 'US') {
        switch ($country) {
            case 'US':
                // Format: 12345 ou 12345-6789
                if (preg_match('/^\d{5}(-\d{4})?$/', $input)) {
                    return $input;
                }
                break;
                
            case 'FR':
                // Format: 75001
                if (preg_match('/^\d{5}$/', $input)) {
                    return $input;
                }
                break;
                
            case 'UK':
                // Format complexe du Royaume-Uni
                $input = strtoupper($input);
                if (preg_match('/^[A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}$/', $input)) {
                    return $input;
                }
                break;
        }
        
        return '';
    }
    
    /**
     * Sanitiser une adresse IP
     */
    public function ip_address($input) {
        if (filter_var($input, FILTER_VALIDATE_IP)) {
            return $input;
        }
        return '';
    }
    
    /**
     * Sanitiser un nom d'utilisateur
     */
    public function username($input) {
        return sanitize_user($input);
    }
    
    /**
     * Sanitiser des métadonnées
     */
    public function meta($input) {
        if (is_array($input) || is_object($input)) {
            return $this->array((array)$input);
        }
        return $this->text($input);
    }
    
    /**
     * Sanitiser pour SQL
     */
    public function sql($input) {
        global $wpdb;
        return esc_sql($input);
    }
    
    /**
     * Sanitiser pour JavaScript
     */
    public function js($input) {
        return esc_js($input);
    }
    
    /**
     * Sanitiser pour attribut HTML
     */
    public function attr($input) {
        return esc_attr($input);
    }
    
    /**
     * Sanitiser pour output HTML
     */
    public function html_output($input) {
        return esc_html($input);
    }
    
    /**
     * Valider et sanitiser selon un schéma
     */
    public function validate_schema($data, $schema) {
        $sanitized = [];
        $errors = [];
        
        foreach ($schema as $field => $rules) {
            $value = isset($data[$field]) ? $data[$field] : null;
            
            // Vérifier si requis
            if (!empty($rules['required']) && empty($value)) {
                $errors[$field] = sprintf(__('Field %s is required', 'rankmath-capture-unified'), $field);
                continue;
            }
            
            // Appliquer la sanitisation
            if (!empty($rules['sanitize'])) {
                $method = $rules['sanitize'];
                
                if (method_exists($this, $method)) {
                    $value = $this->$method($value);
                }
            }
            
            // Valider
            if (!empty($rules['validate'])) {
                $validation = $this->validate_field($value, $rules['validate']);
                
                if ($validation !== true) {
                    $errors[$field] = $validation;
                    continue;
                }
            }
            
            $sanitized[$field] = $value;
        }
        
        return [
            'data' => $sanitized,
            'errors' => $errors,
            'valid' => empty($errors)
        ];
    }
    
    /**
     * Valider un champ
     */
    private function validate_field($value, $rules) {
        // Email
        if (isset($rules['email']) && $rules['email']) {
            if (!is_email($value)) {
                return __('Invalid email address', 'rankmath-capture-unified');
            }
        }
        
        // URL
        if (isset($rules['url']) && $rules['url']) {
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                return __('Invalid URL', 'rankmath-capture-unified');
            }
        }
        
        // Longueur minimale
        if (isset($rules['min_length'])) {
            if (strlen($value) < $rules['min_length']) {
                return sprintf(__('Minimum length is %d characters', 'rankmath-capture-unified'), $rules['min_length']);
            }
        }
        
        // Longueur maximale
        if (isset($rules['max_length'])) {
            if (strlen($value) > $rules['max_length']) {
                return sprintf(__('Maximum length is %d characters', 'rankmath-capture-unified'), $rules['max_length']);
            }
        }
        
        // Valeur minimale
        if (isset($rules['min'])) {
            if ($value < $rules['min']) {
                return sprintf(__('Minimum value is %s', 'rankmath-capture-unified'), $rules['min']);
            }
        }
        
        // Valeur maximale
        if (isset($rules['max'])) {
            if ($value > $rules['max']) {
                return sprintf(__('Maximum value is %s', 'rankmath-capture-unified'), $rules['max']);
            }
        }
        
        // Pattern regex
        if (isset($rules['pattern'])) {
            if (!preg_match($rules['pattern'], $value)) {
                $message = isset($rules['pattern_message']) 
                    ? $rules['pattern_message'] 
                    : __('Invalid format', 'rankmath-capture-unified');
                return $message;
            }
        }
        
        // Options valides
        if (isset($rules['in'])) {
            if (!in_array($value, $rules['in'], true)) {
                return __('Invalid option selected', 'rankmath-capture-unified');
            }
        }
        
        // Callback personnalisé
        if (isset($rules['callback']) && is_callable($rules['callback'])) {
            $result = call_user_func($rules['callback'], $value);
            if ($result !== true) {
                return $result;
            }
        }
        
        return true;
    }
    
    /**
     * Obtenir le HTML autorisé
     */
    private function get_allowed_html() {
        return [
            'a' => [
                'href' => [],
                'title' => [],
                'target' => [],
                'rel' => [],
                'class' => [],
                'id' => []
            ],
            'br' => [],
            'em' => ['class' => [], 'id' => []],
            'strong' => ['class' => [], 'id' => []],
            'p' => ['class' => [], 'id' => []],
            'span' => ['class' => [], 'id' => []],
            'div' => ['class' => [], 'id' => []],
            'ul' => ['class' => [], 'id' => []],
            'ol' => ['class' => [], 'id' => []],
            'li' => ['class' => [], 'id' => []],
            'h1' => ['class' => [], 'id' => []],
            'h2' => ['class' => [], 'id' => []],
            'h3' => ['class' => [], 'id' => []],
            'h4' => ['class' => [], 'id' => []],
            'h5' => ['class' => [], 'id' => []],
            'h6' => ['class' => [], 'id' => []],
            'img' => [
                'src' => [],
                'alt' => [],
                'title' => [],
                'width' => [],
                'height' => [],
                'class' => [],
                'id' => []
            ],
            'blockquote' => ['cite' => [], 'class' => [], 'id' => []],
            'code' => ['class' => [], 'id' => []],
            'pre' => ['class' => [], 'id' => []],
            'table' => ['class' => [], 'id' => []],
            'thead' => [],
            'tbody' => [],
            'tfoot' => [],
            'tr' => ['class' => [], 'id' => []],
            'th' => ['class' => [], 'id' => [], 'scope' => []],
            'td' => ['class' => [], 'id' => [], 'colspan' => [], 'rowspan' => []]
        ];
    }
    
    /**
     * Nettoyer en lot
     */
    public function bulk_sanitize($data, $rules) {
        $sanitized = [];
        
        foreach ($rules as $key => $method) {
            if (isset($data[$key])) {
                if (is_array($method)) {
                    // Méthode avec paramètres
                    $method_name = $method[0];
                    $params = array_slice($method, 1);
                    array_unshift($params, $data[$key]);
                    
                    if (method_exists($this, $method_name)) {
                        $sanitized[$key] = call_user_func_array([$this, $method_name], $params);
                    }
                } elseif (method_exists($this, $method)) {
                    $sanitized[$key] = $this->$method($data[$key]);
                } else {
                    $sanitized[$key] = $this->text($data[$key]);
                }
            }
        }
        
        return $sanitized;
    }
}