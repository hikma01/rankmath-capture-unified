<?php
/**
 * RMCU Logger - Système de journalisation
 *
 * @package    RMCU_Plugin
 * @subpackage RMCU_Plugin/includes
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RMCU_Logger {
    
    /**
     * Nom du fichier de log
     */
    private $log_file;
    
    /**
     * Niveau de log actuel
     */
    private $log_level;
    
    /**
     * Niveaux de log disponibles
     */
    const LOG_LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];
    
    /**
     * Constructeur
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/rmcu-logs';
        
        // Créer le répertoire de logs s'il n'existe pas
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Ajouter un fichier .htaccess pour protéger les logs
            file_put_contents($log_dir . '/.htaccess', 'Deny from all');
        }
        
        // Définir le fichier de log avec la date du jour
        $this->log_file = $log_dir . '/rmcu-' . date('Y-m-d') . '.log';
        
        // Récupérer le niveau de log depuis les options
        $this->log_level = get_option('rmcu_log_level', 'info');
    }
    
    /**
     * Écrire un message dans le log
     *
     * @param string $level Niveau du message
     * @param string $message Message à logger
     * @param array $context Contexte additionnel
     */
    private function write($level, $message, $context = []) {
        // Vérifier si le niveau actuel permet d'écrire ce log
        if (self::LOG_LEVELS[$level] < self::LOG_LEVELS[$this->log_level]) {
            return;
        }
        
        // Préparer le message
        $timestamp = date('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        
        // Formater le contexte si fourni
        $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        
        // Construire la ligne de log
        $log_entry = sprintf(
            "[%s] [%s] [User: %d] [IP: %s] %s%s\n",
            $timestamp,
            strtoupper($level),
            $user_id,
            $ip,
            $message,
            $context_str
        );
        
        // Écrire dans le fichier
        error_log($log_entry, 3, $this->log_file);
        
        // Si c'est une erreur critique, envoyer aussi un email à l'admin
        if ($level === 'critical') {
            $this->notify_admin($message, $context);
        }
    }
    
    /**
     * Log de debug
     */
    public function debug($message, $context = []) {
        $this->write('debug', $message, $context);
    }
    
    /**
     * Log d'information
     */
    public function info($message, $context = []) {
        $this->write('info', $message, $context);
    }
    
    /**
     * Log d'avertissement
     */
    public function warning($message, $context = []) {
        $this->write('warning', $message, $context);
    }
    
    /**
     * Log d'erreur
     */
    public function error($message, $context = []) {
        $this->write('error', $message, $context);
    }
    
    /**
     * Log critique
     */
    public function critical($message, $context = []) {
        $this->write('critical', $message, $context);
    }
    
    /**
     * Logger une action utilisateur
     *
     * @param string $action Action effectuée
     * @param array $data Données associées
     */
    public function log_user_action($action, $data = []) {
        $user = wp_get_current_user();
        $message = sprintf(
            'User action: %s by %s (ID: %d)',
            $action,
            $user->display_name,
            $user->ID
        );
        
        $this->info($message, $data);
    }
    
    /**
     * Logger une erreur d'API
     *
     * @param string $endpoint Endpoint appelé
     * @param mixed $error Erreur retournée
     * @param array $params Paramètres de la requête
     */
    public function log_api_error($endpoint, $error, $params = []) {
        $message = sprintf('API Error on endpoint: %s', $endpoint);
        $context = [
            'error' => $error,
            'params' => $params,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown'
        ];
        
        $this->error($message, $context);
    }
    
    /**
     * Logger une exception
     *
     * @param Exception $exception Exception à logger
     */
    public function log_exception($exception) {
        $message = sprintf(
            'Exception: %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        
        $context = [
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode()
        ];
        
        $this->error($message, $context);
    }
    
    /**
     * Notifier l'administrateur en cas d'erreur critique
     *
     * @param string $message Message d'erreur
     * @param array $context Contexte
     */
    private function notify_admin($message, $context) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Erreur critique RMCU', $site_name);
        
        $body = "Une erreur critique s'est produite sur votre site:\n\n";
        $body .= "Message: " . $message . "\n\n";
        
        if (!empty($context)) {
            $body .= "Contexte:\n" . print_r($context, true) . "\n\n";
        }
        
        $body .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $body .= "URL: " . home_url() . "\n";
        
        wp_mail($admin_email, $subject, $body);
    }
    
    /**
     * Nettoyer les anciens logs
     *
     * @param int $days Nombre de jours à conserver
     */
    public function cleanup_old_logs($days = 30) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/rmcu-logs';
        
        if (!is_dir($log_dir)) {
            return;
        }
        
        $files = glob($log_dir . '/rmcu-*.log');
        $now = time();
        $deleted = 0;
        
        foreach ($files as $file) {
            if ($now - filemtime($file) > ($days * 24 * 60 * 60)) {
                unlink($file);
                $deleted++;
            }
        }
        
        if ($deleted > 0) {
            $this->info(sprintf('Cleaned up %d old log files', $deleted));
        }
    }
    
    /**
     * Obtenir les logs récents
     *
     * @param int $lines Nombre de lignes à retourner
     * @param string $level Filtrer par niveau (optionnel)
     * @return array
     */
    public function get_recent_logs($lines = 100, $level = null) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $logs = [];
        $file = new SplFileObject($this->log_file);
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start = max(0, $total_lines - $lines);
        $file->seek($start);
        
        while (!$file->eof()) {
            $line = trim($file->fgets());
            if (empty($line)) {
                continue;
            }
            
            // Parser la ligne de log
            if (preg_match('/\[(.*?)\] \[(.*?)\] \[User: (.*?)\] \[IP: (.*?)\] (.*)/', $line, $matches)) {
                $log_entry = [
                    'timestamp' => $matches[1],
                    'level' => strtolower($matches[2]),
                    'user_id' => $matches[3],
                    'ip' => $matches[4],
                    'message' => $matches[5]
                ];
                
                // Filtrer par niveau si spécifié
                if ($level === null || $log_entry['level'] === $level) {
                    $logs[] = $log_entry;
                }
            }
        }
        
        return array_reverse($logs);
    }
    
    /**
     * Obtenir les statistiques des logs
     *
     * @return array
     */
    public function get_stats() {
        $stats = [
            'total' => 0,
            'by_level' => [
                'debug' => 0,
                'info' => 0,
                'warning' => 0,
                'error' => 0,
                'critical' => 0
            ],
            'file_size' => 0,
            'last_entry' => null
        ];
        
        if (!file_exists($this->log_file)) {
            return $stats;
        }
        
        $stats['file_size'] = filesize($this->log_file);
        
        $file = fopen($this->log_file, 'r');
        $last_line = '';
        
        while (($line = fgets($file)) !== false) {
            $stats['total']++;
            $last_line = $line;
            
            // Compter par niveau
            foreach (self::LOG_LEVELS as $level => $priority) {
                if (stripos($line, "[" . strtoupper($level) . "]") !== false) {
                    $stats['by_level'][$level]++;
                    break;
                }
            }
        }
        
        fclose($file);
        
        // Parser la dernière entrée
        if (!empty($last_line) && preg_match('/\[(.*?)\]/', $last_line, $matches)) {
            $stats['last_entry'] = $matches[1];
        }
        
        return $stats;
    }
}