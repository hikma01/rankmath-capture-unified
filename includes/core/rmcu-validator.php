<?php
/**
 * RMCU Validator - Classe de validation des données
 *
 * @package    RMCU_Plugin
 * @subpackage RMCU_Plugin/includes
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RMCU_Validator {
    
    /**
     * Messages d'erreur
     */
    private $errors = [];
    
    /**
     * Règles de validation disponibles
     */
    private $validation_rules = [
        'required',
        'email',
        'url',
        'numeric',
        'integer',
        'float',
        'boolean',
        'alpha',
        'alphanumeric',
        'alphaDash',
        'slug',
        'min',
        'max',
        'between',
        'minLength',
        'maxLength',
        'exactLength',
        'in',
        'notIn',
        'regex',
        'date',
        'dateFormat',
        'before',
        'after',
        'ip',
        'ipv4',
        'ipv6',
        'mac',
        'json',
        'array',
        'file',
        'image',
        'mimes',
        'maxFileSize',
        'dimensions',
        'unique',
        'exists',
        'confirmed',
        'different',
        'same',
        'creditCard',
        'phone',
        'postalCode',
        'username',
        'password',
        'strongPassword'
    ];
    
    /**
     * Messages d'erreur par défaut
     */
    private $default_messages = [
        'required' => 'Le champ :attribute est requis.',
        'email' => 'Le champ :attribute doit être une adresse email valide.',
        'url' => 'Le champ :attribute doit être une URL valide.',
        'numeric' => 'Le champ :attribute doit être numérique.',
        'integer' => 'Le champ :attribute doit être un nombre entier.',
        'float' => 'Le champ :attribute doit être un nombre décimal.',
        'boolean' => 'Le champ :attribute doit être vrai ou faux.',
        'alpha' => 'Le champ :attribute ne doit contenir que des lettres.',
        'alphanumeric' => 'Le champ :attribute ne doit contenir que des lettres et des chiffres.',
        'alphaDash' => 'Le champ :attribute ne peut contenir que des lettres, chiffres, tirets et underscores.',
        'slug' => 'Le champ :attribute doit être un slug valide.',
        'min' => 'Le champ :attribute doit être au moins :min.',
        'max' => 'Le champ :attribute ne doit pas dépasser :max.',
        'between' => 'Le champ :attribute doit être entre :min et :max.',
        'minLength' => 'Le champ :attribute doit contenir au moins :min caractères.',
        'maxLength' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
        'exactLength' => 'Le champ :attribute doit contenir exactement :length caractères.',
        'in' => 'Le champ :attribute doit être l\'une des valeurs suivantes: :values.',
        'notIn' => 'Le champ :attribute ne doit pas être l\'une des valeurs suivantes: :values.',
        'regex' => 'Le format du champ :attribute est invalide.',
        'date' => 'Le champ :attribute doit être une date valide.',
        'dateFormat' => 'Le champ :attribute ne correspond pas au format :format.',
        'before' => 'Le champ :attribute doit être une date antérieure à :date.',
        'after' => 'Le champ :attribute doit être une date postérieure à :date.',
        'ip' => 'Le champ :attribute doit être une adresse IP valide.',
        'ipv4' => 'Le champ :attribute doit être une adresse IPv4 valide.',
        'ipv6' => 'Le champ :attribute doit être une adresse IPv6 valide.',
        'mac' => 'Le champ :attribute doit être une adresse MAC valide.',
        'json' => 'Le champ :attribute doit être une chaîne JSON valide.',
        'array' => 'Le champ :attribute doit être un tableau.',
        'file' => 'Le champ :attribute doit être un fichier.',
        'image' => 'Le champ :attribute doit être une image.',
        'mimes' => 'Le champ :attribute doit être un fichier de type: :values.',
        'maxFileSize' => 'Le champ :attribute ne doit pas dépasser :max KB.',
        'dimensions' => 'Les dimensions de l\'image :attribute sont invalides.',
        'unique' => 'La valeur du champ :attribute est déjà utilisée.',
        'exists' => 'La valeur du champ :attribute n\'existe pas.',
        'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
        'different' => 'Les champs :attribute et :other doivent être différents.',
        'same' => 'Les champs :attribute et :other doivent être identiques.',
        'creditCard' => 'Le champ :attribute doit être un numéro de carte de crédit valide.',
        'phone' => 'Le champ :attribute doit être un numéro de téléphone valide.',
        'postalCode' => 'Le champ :attribute doit être un code postal valide.',
        'username' => 'Le champ :attribute doit être un nom d\'utilisateur valide.',
        'password' => 'Le champ :attribute doit être un mot de passe valide.',
        'strongPassword' => 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.'
    ];
    
    /**
     * Messages personnalisés
     */
    private $custom_messages = [];
    
    /**
     * Attributs personnalisés
     */
    private $custom_attributes = [];
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->custom_messages = apply_filters('rmcu_validator_messages', []);
        $this->custom_attributes = apply_filters('rmcu_validator_attributes', []);
    }
    
    /**
     * Valider des données selon des règles
     *
     * @param array $data Données à valider
     * @param array $rules Règles de validation
     * @param array $messages Messages personnalisés (optionnel)
     * @param array $attributes Noms d'attributs personnalisés (optionnel)
     * @return bool
     */
    public function validate($data, $rules, $messages = [], $attributes = []) {
        $this->errors = [];
        $this->custom_messages = array_merge($this->custom_messages, $messages);
        $this->custom_attributes = array_merge($this->custom_attributes, $attributes);
        
        foreach ($rules as $field => $field_rules) {
            $value = $data[$field] ?? null;
            $rules_array = $this->parse_rules($field_rules);
            
            foreach ($rules_array as $rule) {
                $this->apply_rule($field, $value, $rule, $data);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Parser les règles de validation
     *
     * @param string|array $rules
     * @return array
     */
    private function parse_rules($rules) {
        if (is_array($rules)) {
            return $rules;
        }
        
        return explode('|', $rules);
    }
    
    /**
     * Appliquer une règle de validation
     *
     * @param string $field Nom du champ
     * @param mixed $value Valeur du champ
     * @param string $rule Règle à appliquer
     * @param array $data Toutes les données (pour les règles de comparaison)
     */
    private function apply_rule($field, $value, $rule, $data) {
        // Parser la règle et ses paramètres
        $rule_parts = explode(':', $rule);
        $rule_name = $rule_parts[0];
        $parameters = isset($rule_parts[1]) ? explode(',', $rule_parts[1]) : [];
        
        // Méthode de validation
        $method = 'validate_' . lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $rule_name))));
        
        if (method_exists($this, $method)) {
            $valid = $this->$method($field, $value, $parameters, $data);
            
            if (!$valid) {
                $this->add_error($field, $rule_name, $parameters);
            }
        } else {
            // Règle personnalisée via callback
            $valid = apply_filters('rmcu_validate_' . $rule_name, true, $field, $value, $parameters, $data);
            
            if (!$valid) {
                $this->add_error($field, $rule_name, $parameters);
            }
        }
    }
    
    /**
     * Ajouter une erreur
     *
     * @param string $field
     * @param string $rule
     * @param array $parameters
     */
    private function add_error($field, $rule, $parameters = []) {
        $message = $this->get_error_message($field, $rule, $parameters);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }
    
    /**
     * Obtenir le message d'erreur
     *
     * @param string $field
     * @param string $rule
     * @param array $parameters
     * @return string
     */
    private function get_error_message($field, $rule, $parameters) {
        // Vérifier les messages personnalisés
        $custom_key = $field . '.' . $rule;
        if (isset($this->custom_messages[$custom_key])) {
            $message = $this->custom_messages[$custom_key];
        } elseif (isset($this->custom_messages[$rule])) {
            $message = $this->custom_messages[$rule];
        } else {
            $message = $this->default_messages[$rule] ?? 'Le champ :attribute est invalide.';
        }
        
        // Remplacer les placeholders
        $attribute = $this->custom_attributes[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $message = str_replace(':attribute', $attribute, $message);
        
        // Remplacer les paramètres
        if (!empty($parameters)) {
            $replacements = [
                ':min' => $parameters[0] ?? '',
                ':max' => $parameters[1] ?? $parameters[0] ?? '',
                ':length' => $parameters[0] ?? '',
                ':format' => $parameters[0] ?? '',
                ':date' => $parameters[0] ?? '',
                ':other' => $this->custom_attributes[$parameters[0] ?? ''] ?? $parameters[0] ?? '',
                ':values' => implode(', ', $parameters)
            ];
            
            $message = str_replace(array_keys($replacements), array_values($replacements), $message);
        }
        
        return $message;
    }
    
    /**
     * Validation: required
     */
    private function validate_required($field, $value, $parameters, $data) {
        if (is_null($value)) {
            return false;
        }
        
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        
        if (is_array($value) && count($value) < 1) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validation: email
     */
    private function validate_email($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return is_email($value);
    }
    
    /**
     * Validation: url
     */
    private function validate_url($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validation: numeric
     */
    private function validate_numeric($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return is_numeric($value);
    }
    
    /**
     * Validation: integer
     */
    private function validate_integer($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    /**
     * Validation: float
     */
    private function validate_float($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }
    
    /**
     * Validation: boolean
     */
    private function validate_boolean($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
    }
    
    /**
     * Validation: alpha
     */
    private function validate_alpha($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return preg_match('/^[a-zA-Z]+$/', $value);
    }
    
    /**
     * Validation: alphanumeric
     */
    private function validate_alphanumeric($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return preg_match('/^[a-zA-Z0-9]+$/', $value);
    }
    
    /**
     * Validation: alphaDash
     */
    private function validate_alphaDash($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return preg_match('/^[a-zA-Z0-9_-]+$/', $value);
    }
    
    /**
     * Validation: slug
     */
    private function validate_slug($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return preg_match('/^[a-z0-9-]+$/', $value);
    }
    
    /**
     * Validation: min
     */
    private function validate_min($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $min = $parameters[0] ?? 0;
        
        if (is_numeric($value)) {
            return $value >= $min;
        }
        
        if (is_string($value)) {
            return strlen($value) >= $min;
        }
        
        if (is_array($value)) {
            return count($value) >= $min;
        }
        
        return false;
    }
    
    /**
     * Validation: max
     */
    private function validate_max($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $max = $parameters[0] ?? PHP_INT_MAX;
        
        if (is_numeric($value)) {
            return $value <= $max;
        }
        
        if (is_string($value)) {
            return strlen($value) <= $max;
        }
        
        if (is_array($value)) {
            return count($value) <= $max;
        }
        
        return false;
    }
    
    /**
     * Validation: between
     */
    private function validate_between($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $min = $parameters[0] ?? 0;
        $max = $parameters[1] ?? PHP_INT_MAX;
        
        if (is_numeric($value)) {
            return $value >= $min && $value <= $max;
        }
        
        if (is_string($value)) {
            $length = strlen($value);
            return $length >= $min && $length <= $max;
        }
        
        if (is_array($value)) {
            $count = count($value);
            return $count >= $min && $count <= $max;
        }
        
        return false;
    }
    
    /**
     * Validation: minLength
     */
    private function validate_minLength($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return strlen($value) >= ($parameters[0] ?? 0);
    }
    
    /**
     * Validation: maxLength
     */
    private function validate_maxLength($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return strlen($value) <= ($parameters[0] ?? PHP_INT_MAX);
    }
    
    /**
     * Validation: exactLength
     */
    private function validate_exactLength($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return strlen($value) === (int) ($parameters[0] ?? 0);
    }
    
    /**
     * Validation: in
     */
    private function validate_in($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return in_array($value, $parameters);
    }
    
    /**
     * Validation: notIn
     */
    private function validate_notIn($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return !in_array($value, $parameters);
    }
    
    /**
     * Validation: regex
     */
    private function validate_regex($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $pattern = $parameters[0] ?? '';
        if (empty($pattern)) return false;
        
        return preg_match($pattern, $value);
    }
    
    /**
     * Validation: date
     */
    private function validate_date($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        if ($value instanceof DateTime) {
            return true;
        }
        
        return strtotime($value) !== false;
    }
    
    /**
     * Validation: dateFormat
     */
    private function validate_dateFormat($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $format = $parameters[0] ?? 'Y-m-d';
        $date = DateTime::createFromFormat($format, $value);
        
        return $date && $date->format($format) === $value;
    }
    
    /**
     * Validation: before
     */
    private function validate_before($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $before_date = $parameters[0] ?? 'now';
        
        // Si c'est un nom de champ
        if (isset($data[$before_date])) {
            $before_date = $data[$before_date];
        }
        
        return strtotime($value) < strtotime($before_date);
    }
    
    /**
     * Validation: after
     */
    private function validate_after($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $after_date = $parameters[0] ?? 'now';
        
        // Si c'est un nom de champ
        if (isset($data[$after_date])) {
            $after_date = $data[$after_date];
        }
        
        return strtotime($value) > strtotime($after_date);
    }
    
    /**
     * Validation: ip
     */
    private function validate_ip($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Validation: ipv4
     */
    private function validate_ipv4($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
    
    /**
     * Validation: ipv6
     */
    private function validate_ipv6($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
    
    /**
     * Validation: mac
     */
    private function validate_mac($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $value);
    }
    
    /**
     * Validation: json
     */
    private function validate_json($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Validation: array
     */
    private function validate_array($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        return is_array($value);
    }
    
    /**
     * Validation: file
     */
    private function validate_file($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        if (isset($_FILES[$field])) {
            return $_FILES[$field]['error'] === UPLOAD_ERR_OK;
        }
        
        return is_file($value);
    }
    
    /**
     * Validation: image
     */
    private function validate_image($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        if (!$this->validate_file($field, $value, $parameters, $data)) {
            return false;
        }
        
        $mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        
        if (isset($_FILES[$field])) {
            return in_array($_FILES[$field]['type'], $mime_types);
        }
        
        $mime = mime_content_type($value);
        return in_array($mime, $mime_types);
    }
    
    /**
     * Validation: mimes
     */
    private function validate_mimes($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        if (!$this->validate_file($field, $value, $parameters, $data)) {
            return false;
        }
        
        $extension = pathinfo($value, PATHINFO_EXTENSION);
        return in_array(strtolower($extension), $parameters);
    }
    
    /**
     * Validation: maxFileSize
     */
    private function validate_maxFileSize($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $max_size = ($parameters[0] ?? 2048) * 1024; // Convertir KB en bytes
        
        if (isset($_FILES[$field])) {
            return $_FILES[$field]['size'] <= $max_size;
        }
        
        if (is_file($value)) {
            return filesize($value) <= $max_size;
        }
        
        return false;
    }
    
    /**
     * Validation: dimensions
     */
    private function validate_dimensions($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        if (!$this->validate_image($field, $value, $parameters, $data)) {
            return false;
        }
        
        $path = $_FILES[$field]['tmp_name'] ?? $value;
        list($width, $height) = getimagesize($path);
        
        $min_width = $parameters[0] ?? 0;
        $min_height = $parameters[1] ?? 0;
        $max_width = $parameters[2] ?? PHP_INT_MAX;
        $max_height = $parameters[3] ?? PHP_INT_MAX;
        
        return $width >= $min_width && $width <= $max_width &&
               $height >= $min_height && $height <= $max_height;
    }
    
    /**
     * Validation: unique
     */
    private function validate_unique($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        global $wpdb;
        
        $table = $wpdb->prefix . ($parameters[0] ?? 'users');
        $column = $parameters[1] ?? $field;
        $except_id = $parameters[2] ?? null;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$column} = %s",
            $value
        );
        
        if ($except_id) {
            $query .= $wpdb->prepare(" AND id != %d", $except_id);
        }
        
        $count = $wpdb->get_var($query);
        
        return $count == 0;
    }
    
    /**
     * Validation: exists
     */
    private function validate_exists($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        global $wpdb;
        
        $table = $wpdb->prefix . ($parameters[0] ?? 'users');
        $column = $parameters[1] ?? 'id';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$column} = %s",
            $value
        ));
        
        return $count > 0;
    }
    
    /**
     * Validation: confirmed
     */
    private function validate_confirmed($field, $value, $parameters, $data) {
        $confirmation_field = $field . '_confirmation';
        
        if (isset($parameters[0])) {
            $confirmation_field = $parameters[0];
        }
        
        return isset($data[$confirmation_field]) && $value === $data[$confirmation_field];
    }
    
    /**
     * Validation: different
     */
    private function validate_different($field, $value, $parameters, $data) {
        $other_field = $parameters[0] ?? '';
        
        if (!isset($data[$other_field])) {
            return true;
        }
        
        return $value !== $data[$other_field];
    }
    
    /**
     * Validation: same
     */
    private function validate_same($field, $value, $parameters, $data) {
        $other_field = $parameters[0] ?? '';
        
        if (!isset($data[$other_field])) {
            return false;
        }
        
        return $value === $data[$other_field];
    }
    
    /**
     * Validation: creditCard
     */
    private function validate_creditCard($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        // Algorithme de Luhn
        $value = preg_replace('/\D/', '', $value);
        $sum = 0;
        $length = strlen($value);
        
        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int) $value[$i];
            
            if (($length - $i) % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
        }
        
        return $sum % 10 === 0;
    }
    
    /**
     * Validation: phone
     */
    private function validate_phone($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $country = $parameters[0] ?? 'US';
        
        // Patterns pour différents pays
        $patterns = [
            'US' => '/^(\+1)?[2-9]\d{2}[2-9]\d{2}\d{4}$/',
            'FR' => '/^(\+33|0)[1-9](\d{2}){4}$/',
            'UK' => '/^(\+44|0)7\d{9}$/',
            'DE' => '/^(\+49|0)[1-9]\d{10,11}$/'
        ];
        
        $value = preg_replace('/[\s\-\(\)]/', '', $value);
        
        if (isset($patterns[$country])) {
            return preg_match($patterns[$country], $value);
        }
        
        // Pattern générique pour numéros internationaux
        return preg_match('/^\+?\d{7,15}$/', $value);
    }
    
    /**
     * Validation: postalCode
     */
    private function validate_postalCode($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $country = $parameters[0] ?? 'US';
        
        $patterns = [
            'US' => '/^\d{5}(-\d{4})?$/',
            'CA' => '/^[A-Z]\d[A-Z]\s?\d[A-Z]\d$/i',
            'UK' => '/^[A-Z]{1,2}\d{1,2}\s?\d[A-Z]{2}$/i',
            'FR' => '/^\d{5}$/',
            'DE' => '/^\d{5}$/',
            'AU' => '/^\d{4}$/'
        ];
        
        if (isset($patterns[$country])) {
            return preg_match($patterns[$country], $value);
        }
        
        return true;
    }
    
    /**
     * Validation: username
     */
    private function validate_username($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        // WordPress username rules
        return validate_username($value);
    }
    
    /**
     * Validation: password
     */
    private function validate_password($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $min_length = $parameters[0] ?? 8;
        
        return strlen($value) >= $min_length;
    }
    
    /**
     * Validation: strongPassword
     */
    private function validate_strongPassword($field, $value, $parameters, $data) {
        if (empty($value)) return true;
        
        $min_length = $parameters[0] ?? 8;
        
        if (strlen($value) < $min_length) {
            return false;
        }
        
        // Doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial
        return preg_match('/[A-Z]/', $value) &&
               preg_match('/[a-z]/', $value) &&
               preg_match('/[0-9]/', $value) &&
               preg_match('/[^A-Za-z0-9]/', $value);
    }
    
    /**
     * Obtenir les erreurs
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }
    
    /**
     * Obtenir les erreurs formatées
     *
     * @param string $format Format de sortie ('array', 'string', 'html')
     * @return mixed
     */
    public function get_errors_formatted($format = 'array') {
        if (empty($this->errors)) {
            return $format === 'array' ? [] : '';
        }
        
        switch ($format) {
            case 'string':
                $messages = [];
                foreach ($this->errors as $field_errors) {
                    foreach ($field_errors as $error) {
                        $messages[] = $error;
                    }
                }
                return implode("\n", $messages);
                
            case 'html':
                $html = '<ul class="rmcu-validation-errors">';
                foreach ($this->errors as $field => $field_errors) {
                    foreach ($field_errors as $error) {
                        $html .= '<li>' . esc_html($error) . '</li>';
                    }
                }
                $html .= '</ul>';
                return $html;
                
            default:
                return $this->errors;
        }
    }
    
    /**
     * Obtenir la première erreur
     *
     * @param string $field Champ spécifique (optionnel)
     * @return string|null
     */
    public function get_first_error($field = null) {
        if ($field) {
            return isset($this->errors[$field]) ? $this->errors[$field][0] : null;
        }
        
        foreach ($this->errors as $field_errors) {
            if (!empty($field_errors)) {
                return $field_errors[0];
            }
        }
        
        return null;
    }
    
    /**
     * Vérifier s'il y a des erreurs
     *
     * @param string $field Champ spécifique (optionnel)
     * @return bool
     */
    public function has_errors($field = null) {
        if ($field) {
            return isset($this->errors[$field]) && !empty($this->errors[$field]);
        }
        
        return !empty($this->errors);
    }
    
    /**
     * Réinitialiser les erreurs
     */
    public function reset() {
        $this->errors = [];
        $this->custom_messages = [];
        $this->custom_attributes = [];
    }
}