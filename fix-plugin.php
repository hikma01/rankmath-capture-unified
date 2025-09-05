<?php
/**
 * Script de réparation pour le plugin RMCU
 * Exécutez ce script une seule fois pour corriger les problèmes de structure
 */

// Définir le chemin du plugin
$plugin_dir = dirname(__FILE__) . '/';

// Liste des renommages nécessaires
$renames = [
    'includes/core/rmcu-database.php' => 'includes/core/class-rmcu-database.php',
    'includes/core/rmcu-core-class.php' => 'includes/core/class-rmcu-core.php',
    'includes/core/rmcu-logger-class.php' => 'includes/core/class-rmcu-logger.php',
    'includes/core/rmcu-capture-handler.php' => 'includes/core/class-rmcu-capture-handler.php',
    'includes/core/rmcu-dispatcher.php' => 'includes/core/class-rmcu-dispatcher.php',
    'includes/core/rmcu-health-check.php' => 'includes/core/class-rmcu-health-check.php',
    'includes/core/rmcu-queue-manager.php' => 'includes/core/class-rmcu-queue-manager.php',
    'includes/core/rmcu-rankmath-integration.php' => 'includes/core/class-rmcu-rankmath-integration.php',
    'includes/core/rmcu-sanitizer.php' => 'includes/core/class-rmcu-sanitizer.php',
    'includes/core/rmcu-validator.php' => 'includes/core/class-rmcu-validator.php',
    'includes/core/rmcu-webhook-handler.php' => 'includes/core/class-rmcu-webhook-handler.php',
    'includes/core/rmcu-cache-manager.php' => 'includes/core/class-rmcu-cache-manager.php'
];

echo "<h2>Réparation du plugin RMCU</h2>\n";
echo "<pre>\n";

$success_count = 0;
$error_count = 0;

foreach ($renames as $old_name => $new_name) {
    $old_path = $plugin_dir . $old_name;
    $new_path = $plugin_dir . $new_name;
    
    echo "Traitement de : $old_name\n";
    
    // Vérifier si le nouveau fichier existe déjà
    if (file_exists($new_path)) {
        echo "  ✓ Le fichier $new_name existe déjà\n";
        $success_count++;
        continue;
    }
    
    // Vérifier si l'ancien fichier existe
    if (!file_exists($old_path)) {
        echo "  ⚠ Le fichier source $old_name n'existe pas\n";
        $error_count++;
        continue;
    }
    
    // Créer une copie plutôt qu'un renommage pour éviter de perdre les fichiers
    if (copy($old_path, $new_path)) {
        echo "  ✓ Copié $old_name vers $new_name\n";
        $success_count++;
    } else {
        echo "  ✗ Échec de la copie de $old_name vers $new_name\n";
        $error_count++;
    }
}

// Créer aussi des fichiers wrapper pour la compatibilité avec les utils
$utils_wrappers = [
    'includes/class-rmcu-logger.php' => 'includes/utils/rmcu-logger.php',
    'includes/class-rmcu-sanitizer.php' => 'includes/utils/rmcu-sanitizer.php'
];

echo "\nCréation des wrappers pour les utils :\n";

foreach ($utils_wrappers as $wrapper_path => $target_path) {
    $wrapper_full = $plugin_dir . $wrapper_path;
    $target_full = $plugin_dir . $target_path;
    
    if (file_exists($wrapper_full)) {
        echo "  ✓ Le wrapper $wrapper_path existe déjà\n";
        continue;
    }
    
    if (!file_exists($target_full)) {
        echo "  ⚠ Le fichier cible $target_path n'existe pas\n";
        continue;
    }
    
    // Créer un fichier wrapper
    $wrapper_content = "<?php\n// Wrapper de compatibilité\nrequire_once __DIR__ . '/" . 
                      str_replace('includes/', '', $target_path) . "';\n";
    
    // Créer le répertoire si nécessaire
    $wrapper_dir = dirname($wrapper_full);
    if (!is_dir($wrapper_dir)) {
        mkdir($wrapper_dir, 0755, true);
    }
    
    if (file_put_contents($wrapper_full, $wrapper_content)) {
        echo "  ✓ Wrapper créé : $wrapper_path\n";
        $success_count++;
    } else {
        echo "  ✗ Échec de création du wrapper : $wrapper_path\n";
        $error_count++;
    }
}

echo "\n";
echo "========================================\n";
echo "Résultats :\n";
echo "  ✓ Succès : $success_count\n";
echo "  ✗ Erreurs : $error_count\n";
echo "========================================\n";

if ($error_count === 0) {
    echo "\n✅ <strong>Réparation terminée avec succès!</strong>\n";
    echo "Vous pouvez maintenant activer le plugin.\n";
} else {
    echo "\n⚠ <strong>Réparation partielle.</strong>\n";
    echo "Certains fichiers n'ont pas pu être traités.\n";
    echo "Vérifiez les permissions des fichiers et réessayez.\n";
}

echo "</pre>\n";

// Optionnel : supprimer ce script après exécution
echo '<br><form method="post">';
echo '<input type="hidden" name="delete_script" value="1">';
echo '<button type="submit">Supprimer ce script de réparation</button>';
echo '</form>';

if (isset($_POST['delete_script'])) {
    if (unlink(__FILE__)) {
        echo '<p style="color: green;">Script supprimé avec succès.</p>';
    }
}
?>
