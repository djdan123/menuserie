<?php
require_once '../config/database.php';
require_once '../config/functions.php';

redirigerSiNonAdmin();

// Créer un ZIP avec tous les exports
$zip = new ZipArchive();
$filename = 'export_complet_' . date('Y-m-d_H-i') . '.zip';

if ($zip->open($filename, ZipArchive::CREATE) !== TRUE) {
    die("Impossible de créer le fichier ZIP");
}

// Fonction pour exporter en CSV
function exporterTableCSV($pdo, $table, $nomFichier) {
    $stmt = $pdo->query("SELECT * FROM $table");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        return false;
    }
    
    $output = fopen('php://temp', 'w');
    
    // En-têtes
    fputcsv($output, array_keys($data[0]), ';');
    
    // Données
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}

// Tables à exporter
$tables = [
    'utilisateurs',
    'categories',
    'meubles',
    'commandes',
    'details_commandes',
    'fabrication',
    'messages',
    'parametres'
];

// Exporter chaque table
foreach ($tables as $table) {
    try {
        $csv = exporterTableCSV($pdo, $table, $table . '.csv');
        if ($csv !== false) {
            $zip->addFromString('csv/' . $table . '.csv', $csv);
        }
    } catch (Exception $e) {
        // Ignorer les tables qui n'existent pas
    }
}

// Exporter en JSON aussi
$export_json = [];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT * FROM $table");
        $export_json[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Ignorer les tables qui n'existent pas
    }
}

$zip->addFromString('json/export_complet.json', json_encode($export_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Ajouter un fichier README
$readme = "# Export complet - " . date('d/m/Y H:i') . "\n\n";
$readme .= "Ce fichier contient un export complet de la base de données.\n";
$readme .= "Structure :\n";
$readme .= "- csv/ : Fichiers CSV par table\n";
$readme .= "- json/ : Export complet en JSON\n\n";
$readme .= "Généré automatiquement par le système de menuiserie.\n";

$zip->addFromString('README.txt', $readme);

$zip->close();

// Enregistrer dans l'historique
$stmt = $pdo->prepare("INSERT INTO export_log (type_export, format, fichier, date_export) 
                       VALUES ('complet', 'zip', ?, NOW())");
$stmt->execute([$filename]);

// Télécharger le fichier
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filename));

readfile($filename);

// Supprimer le fichier temporaire
unlink($filename);
?>