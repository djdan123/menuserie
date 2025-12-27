<?php
require_once '../config/database.php';
require_once '../config/functions.php';

redirigerSiNonAdmin();

// Types d'export disponibles
$types_export = [
    'produits' => '📦 Produits',
    'clients' => '👥 Clients',
    'commandes' => '📋 Commandes',
    'categories' => '📁 Catégories',
    'fabrication' => '🔨 Fabrication',
    'messages' => '✉️ Messages'
];

// Types d'import disponibles
$types_import = [
    'produits' => '📦 Produits (CSV)',
    'clients' => '👥 Clients (CSV)',
    'commandes' => '📋 Commandes (JSON)'
];

// Exporter des données
if (isset($_GET['exporter'])) {
    $type = securiser($_GET['exporter']);
    $format = isset($_GET['format']) ? securiser($_GET['format']) : 'csv';
    
    if (!array_key_exists($type, $types_export)) {
        die('Type d\'export invalide');
    }
    
    // Configuration selon le type
    $config = [
        'filename' => 'export_' . $type . '_' . date('Y-m-d_H-i') . '.' . $format,
        'headers' => []
    ];
    
    switch ($type) {
        case 'produits':
            $data = exporterProduits($pdo);
            $config['headers'] = ['ID', 'Nom', 'Catégorie', 'Type bois', 'Dimensions', 'Prix', 'Stock', 'Date ajout'];
            break;
            
        case 'clients':
            $data = exporterClients($pdo);
            $config['headers'] = ['ID', 'Nom', 'Prénom', 'Email', 'Date inscription', 'Commandes', 'Total achats'];
            break;
            
        case 'commandes':
            $data = exporterCommandes($pdo);
            $config['headers'] = ['ID', 'Client', 'Date', 'Total', 'Statut', 'Articles'];
            break;
            
        case 'categories':
            $data = exporterCategories($pdo);
            $config['headers'] = ['ID', 'Nom', 'Description', 'Nb produits'];
            break;
            
        case 'fabrication':
            $data = exporterFabrication($pdo);
            $config['headers'] = ['ID', 'Meuble', 'Artisan', 'Début', 'Fin', 'Statut', 'Priorité'];
            break;
            
        case 'messages':
            $data = exporterMessages($pdo);
            $config['headers'] = ['ID', 'Expéditeur', 'Destinataire', 'Sujet', 'Date', 'Lu', 'Répondu'];
            break;
    }
    
    // Générer le fichier selon le format
    if ($format === 'csv') {
        exporterCSV($data, $config['headers'], $config['filename']);
    } elseif ($format === 'json') {
        exporterJSON($data, $config['filename']);
    } elseif ($format === 'excel') {
        exporterExcel($data, $config['headers'], $config['filename']);
    }
    
    exit();
}

// Importer des données
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importer'])) {
    $type = securiser($_POST['type_import']);
    $format = securiser($_POST['format_import']);
    
    if (!isset($_FILES['fichier_import']) || $_FILES['fichier_import']['error'] !== UPLOAD_ERR_OK) {
        flashMessage("Erreur lors du téléchargement du fichier", "error");
        header('Location: export_import.php');
        exit();
    }
    
    $fichier = $_FILES['fichier_import']['tmp_name'];
    $resultat = [];
    
    try {
        switch ($format) {
            case 'csv':
                $resultat = importerCSV($fichier, $type, $pdo);
                break;
                
            case 'json':
                $resultat = importerJSON($fichier, $type, $pdo);
                break;
                
            case 'excel':
                $resultat = importerExcel($fichier, $type, $pdo);
                break;
        }
        
        if ($resultat['success']) {
            flashMessage("Import réussi : " . $resultat['message'], "success");
        } else {
            flashMessage("Erreur d'import : " . $resultat['message'], "error");
        }
        
    } catch (Exception $e) {
        flashMessage("Erreur : " . $e->getMessage(), "error");
    }
    
    header('Location: export_import.php');
    exit();
}

// Récupérer l'historique des exports
$historique_exports = $pdo->query("
    SELECT * FROM export_log 
    ORDER BY date_export DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Fonctions d'export
function exporterProduits($pdo) {
    $stmt = $pdo->query("
        SELECT 
            m.id_meuble,
            m.nom,
            c.nom_categorie,
            m.bois_type,
            CONCAT(m.longueur, 'x', m.largeur, 'x', m.hauteur) as dimensions,
            m.prix_vente,
            m.quantite_stock,
            m.date_ajout
        FROM meubles m
        LEFT JOIN categories c ON m.id_categorie = c.id_categorie
        ORDER BY m.date_ajout DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function exporterClients($pdo) {
    $stmt = $pdo->query("
        SELECT 
            u.id_user,
            u.nom,
            u.prenom,
            u.email,
            u.date_inscription,
            (SELECT COUNT(*) FROM commandes c WHERE c.id_user = u.id_user) as nb_commandes,
            (SELECT SUM(total) FROM commandes c WHERE c.id_user = u.id_user AND c.statut = 'livrée') as total_achats
        FROM utilisateurs u
        WHERE u.role = 'client'
        ORDER BY u.date_inscription DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function exporterCommandes($pdo) {
    $stmt = $pdo->query("
        SELECT 
            c.id_commande,
            CONCAT(u.prenom, ' ', u.nom) as client,
            c.date_commande,
            c.total,
            c.statut,
            (SELECT GROUP_CONCAT(CONCAT(dc.quantite, 'x ', m.nom) SEPARATOR '; ') 
             FROM details_commandes dc 
             JOIN meubles m ON dc.id_meuble = m.id_meuble 
             WHERE dc.id_commande = c.id_commande) as articles
        FROM commandes c
        JOIN utilisateurs u ON c.id_user = u.id_user
        ORDER BY c.date_commande DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function exporterCategories($pdo) {
    $stmt = $pdo->query("
        SELECT 
            c.id_categorie,
            c.nom_categorie,
            c.description,
            (SELECT COUNT(*) FROM meubles m WHERE m.id_categorie = c.id_categorie) as nb_produits
        FROM categories c
        ORDER BY c.nom_categorie
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonctions de génération de fichiers
function exporterCSV($data, $headers, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers, ';');
    
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
}

function exporterJSON($data, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function exporterExcel($data, $headers, $filename) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "<table border='1'>";
    echo "<tr>";
    foreach ($headers as $header) {
        echo "<th>" . htmlspecialchars($header) . "</th>";
    }
    echo "</tr>";
    
    foreach ($data as $row) {
        echo "<tr>";
        foreach ($row as $cell) {
            echo "<td>" . htmlspecialchars($cell) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export/Import de données - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>📊 Export/Import de données</h1>
            </div>
            
            <?php echo getFlashMessage(); ?>
            
            <div class="export-import-container">
                <!-- Section Export -->
                <div class="export-section">
                    <div class="section-header">
                        <h2>📤 Exporter des données</h2>
                        <p>Sélectionnez les données à exporter et le format</p>
                    </div>
                    
                    <div class="export-options">
                        <div class="export-grid">
                            <?php foreach ($types_export as $type => $label): ?>
                                <div class="export-card">
                                    <div class="export-icon">
                                        <?php echo substr($label, 0, 2); ?>
                                    </div>
                                    <div class="export-info">
                                        <h3><?php echo substr($label, 3); ?></h3>
                                        <div class="export-formats">
                                            <a href="export_import.php?exporter=<?php echo $type; ?>&format=csv" 
                                               class="btn-format csv" title="CSV">CSV</a>
                                            <a href="export_import.php?exporter=<?php echo $type; ?>&format=json" 
                                               class="btn-format json" title="JSON">JSON</a>
                                            <a href="export_import.php?exporter=<?php echo $type; ?>&format=excel" 
                                               class="btn-format excel" title="Excel">Excel</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Export personnalisé -->
                        <div class="export-custom">
                            <h3>⚙️ Export personnalisé</h3>
                            <form method="GET" class="custom-export-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="custom_type">Type de données</label>
                                        <select id="custom_type" name="exporter">
                                            <?php foreach ($types_export as $type => $label): ?>
                                                <option value="<?php echo $type; ?>"><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="custom_format">Format</label>
                                        <select id="custom_format" name="format">
                                            <option value="csv">CSV</option>
                                            <option value="json">JSON</option>
                                            <option value="excel">Excel</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="custom_date_debut">Date début</label>
                                        <input type="date" id="custom_date_debut" name="date_debut">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="custom_date_fin">Date fin</label>
                                        <input type="date" id="custom_date_fin" name="date_fin">
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-success">🚀 Exporter</button>
                                    <button type="button" class="btn btn-secondary" onclick="exporterTout()">📦 Tout exporter (ZIP)</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Section Import -->
                <div class="import-section">
                    <div class="section-header">
                        <h2>📥 Importer des données</h2>
                        <p>Importez des données depuis un fichier externe</p>
                    </div>
                    
                    <div class="import-form">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="type_import">Type de données à importer</label>
                                <select id="type_import" name="type_import" required>
                                    <option value="">Sélectionner un type...</option>
                                    <?php foreach ($types_import as $type => $label): ?>
                                        <option value="<?php echo $type; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="format_import">Format du fichier</label>
                                <select id="format_import" name="format_import" required>
                                    <option value="csv">CSV</option>
                                    <option value="json">JSON</option>
                                    <option value="excel">Excel</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="fichier_import">Fichier à importer *</label>
                                <input type="file" id="fichier_import" name="fichier_import" accept=".csv,.json,.xls,.xlsx" required>
                                <small>Formats acceptés: CSV, JSON, Excel</small>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" id="update_existing" name="update_existing">
                                <label for="update_existing">Mettre à jour les entrées existantes</label>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" id="skip_errors" name="skip_errors" checked>
                                <label for="skip_errors">Ignorer les erreurs et continuer</label>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="importer" value="1" class="btn btn-success">📤 Importer</button>
                                <button type="button" class="btn btn-secondary" onclick="previsualiserImport()">👁️ Prévisualiser</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Templates de fichiers -->
                    <div class="import-templates">
                        <h3>📄 Templates de fichiers</h3>
                        <p>Téléchargez les modèles pour créer vos fichiers d'import :</p>
                        <div class="templates-grid">
                            <a href="templates/template_produits.csv" class="template-card" download>
                                <span class="template-icon">📦</span>
                                <span class="template-name">Produits</span>
                                <small>CSV</small>
                            </a>
                            <a href="templates/template_clients.csv" class="template-card" download>
                                <span class="template-icon">👥</span>
                                <span class="template-name">Clients</span>
                                <small>CSV</small>
                            </a>
                            <a href="templates/template_commandes.json" class="template-card" download>
                                <span class="template-icon">📋</span>
                                <span class="template-name">Commandes</span>
                                <small>JSON</small>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Historique -->
                <div class="history-section">
                    <h2>📜 Historique des exports</h2>
                    
                    <div class="history-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Format</th>
                                    <th>Fichier</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($historique_exports)): ?>
                                    <tr>
                                        <td colspan="5" class="no-data">Aucun export récent</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($historique_exports as $export): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($export['date_export'])); ?></td>
                                            <td><?php echo htmlspecialchars($export['type_export']); ?></td>
                                            <td><?php echo strtoupper($export['format']); ?></td>
                                            <td>
                                                <code><?php echo htmlspecialchars($export['fichier']); ?></code>
                                            </td>
                                            <td>
                                                <button class="btn-action" 
                                                        onclick="telechargerExport('<?php echo $export['fichier']; ?>')"
                                                        title="Télécharger">📥</button>
                                                <button class="btn-action" 
                                                        onclick="supprimerExport(<?php echo $export['id_export']; ?>)"
                                                        title="Supprimer">🗑️</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="history-actions">
                        <button class="btn btn-secondary" onclick="viderHistorique()">🗑️ Vider l'historique</button>
                        <button class="btn btn-primary" onclick="sauvegarderConfiguration()">💾 Sauvegarder configuration</button>
                    </div>
                </div>
                
                <!-- Statistiques -->
                <div class="stats-section">
                    <h2>📊 Statistiques de données</h2>
                    <div class="stats-grid">
                        <?php
                        $stats_data = [
                            'produits' => $pdo->query("SELECT COUNT(*) FROM meubles")->fetchColumn(),
                            'clients' => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'client'")->fetchColumn(),
                            'commandes' => $pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn(),
                            'categories' => $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
                            'fabrication' => $pdo->query("SELECT COUNT(*) FROM fabrication")->fetchColumn(),
                            'messages' => $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn()
                        ];
                        
                        foreach ($stats_data as $type => $count):
                        ?>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <?php echo substr($types_export[$type] ?? '📊', 0, 2); ?>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo $types_export[$type] ?? ucfirst($type); ?></h3>
                                    <p class="stat-value"><?php echo number_format($count); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal Prévisualisation import -->
    <div id="modalPreview" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>👁️ Prévisualisation de l'import</h2>
                <span class="close-modal" onclick="fermerModal()">&times;</span>
            </div>
            
            <div class="modal-body">
                <div id="previewContent">
                    <p>Sélectionnez d'abord un fichier pour prévisualiser</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Configuration export -->
    <div id="modalConfig" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>⚙️ Configuration d'export</h2>
                <span class="close-modal" onclick="fermerModal()">&times;</span>
            </div>
            
            <div class="modal-body">
                <form id="formConfig">
                    <div class="form-group">
                        <label for="config_nom">Nom de la configuration</label>
                        <input type="text" id="config_nom" placeholder="Ex: Export mensuel produits">
                    </div>
                    
                    <div class="form-group">
                        <label for="config_types">Types à inclure</label>
                        <div class="checkbox-group">
                            <?php foreach ($types_export as $type => $label): ?>
                                <label>
                                    <input type="checkbox" name="types[]" value="<?php echo $type; ?>" checked>
                                    <?php echo $label; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="config_format">Format principal</label>
                        <select id="config_format">
                            <option value="csv">CSV</option>
                            <option value="json">JSON</option>
                            <option value="zip">ZIP (tous formats)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="config_programmation">Programmation</label>
                        <select id="config_programmation">
                            <option value="manuel">Manuel</option>
                            <option value="quotidien">Quotidien</option>
                            <option value="hebdomadaire">Hebdomadaire</option>
                            <option value="mensuel">Mensuel</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-success" onclick="sauvegarderConfig()">💾 Sauvegarder</button>
                        <button type="button" class="btn btn-secondary" onclick="fermerModal()">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Fonctions export
        function exporterTout() {
            if (confirm('Créer un export complet de toutes les données (ZIP) ?')) {
                window.location.href = 'actions/export_complet.php';
            }
        }
        
        function telechargerExport(fichier) {
            window.open('exports/' + fichier, '_blank');
        }
        
        function supprimerExport(id) {
            if (confirm('Supprimer cet export de l\'historique ?')) {
                fetch('actions/supprimer_export.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id_export=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        function viderHistorique() {
            if (confirm('Vider tout l\'historique des exports ?')) {
                fetch('actions/vider_historique.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        // Fonctions import
        function previsualiserImport() {
            const fileInput = document.getElementById('fichier_import');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Veuillez sélectionner un fichier d\'abord');
                return;
            }
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const content = e.target.result;
                const type = document.getElementById('type_import').value;
                const format = document.getElementById('format_import').value;
                
                let previewHTML = '';
                
                if (format === 'csv') {
                    previewHTML = previewCSV(content);
                } else if (format === 'json') {
                    previewHTML = previewJSON(content);
                }
                
                document.getElementById('previewContent').innerHTML = `
                    <h3>Prévisualisation (${type})</h3>
                    <div class="preview-data">
                        ${previewHTML}
                    </div>
                    <div class="preview-info">
                        <p><strong>Fichier :</strong> ${file.name}</p>
                        <p><strong>Taille :</strong> ${(file.size / 1024).toFixed(2)} KB</p>
                        <p><strong>Type :</strong> ${type}</p>
                        <p><strong>Format :</strong> ${format}</p>
                    </div>
                `;
                
                document.getElementById('modalPreview').style.display = 'block';
            };
            
            if (format === 'csv' || format === 'json') {
                reader.readAsText(file);
            } else {
                alert('Prévisualisation non disponible pour le format Excel');
            }
        }
        
        function previewCSV(content) {
            const lines = content.split('\n');
            let html = '<table class="preview-table">';
            
            // Afficher les 10 premières lignes
            for (let i = 0; i < Math.min(10, lines.length); i++) {
                const cells = lines[i].split(';');
                html += '<tr>';
                cells.forEach(cell => {
                    html += `<td>${cell}</td>`;
                });
                html += '</tr>';
            }
            
            html += '</table>';
            
            if (lines.length > 10) {
                html += `<p>... et ${lines.length - 10} lignes supplémentaires</p>`;
            }
            
            return html;
        }
        
        function previewJSON(content) {
            try {
                const data = JSON.parse(content);
                return `
                    <pre><code>${JSON.stringify(data.slice(0, 5), null, 2)}</code></pre>
                    <p>${Array.isArray(data) ? `${data.length} éléments trouvés` : 'Objet JSON'}</p>
                `;
            } catch (e) {
                return `<p class="error">Format JSON invalide</p>`;
            }
        }
        
        // Configuration
        function sauvegarderConfiguration() {
            document.getElementById('modalConfig').style.display = 'block';
        }
        
        function sauvegarderConfig() {
            const nom = document.getElementById('config_nom').value;
            const types = Array.from(document.querySelectorAll('input[name="types[]"]:checked')).map(cb => cb.value);
            const format = document.getElementById('config_format').value;
            const programmation = document.getElementById('config_programmation').value;
            
            if (!nom) {
                alert('Veuillez saisir un nom pour la configuration');
                return;
            }
            
            fetch('actions/sauvegarder_config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    nom: nom,
                    types: types,
                    format: format,
                    programmation: programmation
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Configuration sauvegardée');
                    fermerModal();
                } else {
                    alert('Erreur: ' + data.message);
                }
            });
        }
        
        // Utilitaires
        function fermerModal() {
            document.getElementById('modalPreview').style.display = 'none';
            document.getElementById('modalConfig').style.display = 'none';
        }
        
        // Validation
        document.querySelector('form[enctype="multipart/form-data"]').addEventListener('submit', function(e) {
            const type = document.getElementById('type_import').value;
            const file = document.getElementById('fichier_import').files[0];
            
            if (!type) {
                e.preventDefault();
                alert('Veuillez sélectionner un type de données');
                return;
            }
            
            if (!file) {
                e.preventDefault();
                alert('Veuillez sélectionner un fichier');
                return;
            }
            
            // Vérifier l'extension
            const allowedExtensions = ['.csv', '.json', '.xls', '.xlsx'];
            const fileExt = '.' + file.name.split('.').pop().toLowerCase();
            
            if (!allowedExtensions.includes(fileExt)) {
                e.preventDefault();
                alert('Format de fichier non supporté. Formats acceptés: .csv, .json, .xls, .xlsx');
            }
        });
        
        // Fermer modals en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                fermerModal();
            }
        };
    </script>
    
    <style>
        .export-import-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .section-header {
            margin-bottom: 1.5rem;
        }
        
        .section-header h2 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .section-header p {
            color: #666;
        }
        
        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .export-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.3s;
        }
        
        .export-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .export-icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .export-info h3 {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
        }
        
        .export-formats {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-format {
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-format.csv {
            background: #27ae60;
            color: white;
        }
        
        .btn-format.json {
            background: #f39c12;
            color: white;
        }
        
        .btn-format.excel {
            background: #2ecc71;
            color: white;
        }
        
        .btn-format:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .export-custom {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .custom-export-form .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .import-section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .import-form {
            margin-bottom: 2rem;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }
        
        .import-templates {
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .template-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .template-card:hover {
            border-color: #3498db;
            transform: translateY(-3px);
        }
        
        .template-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .template-name {
            font-weight: 500;
        }
        
        .template-card small {
            color: #666;
            font-size: 0.8rem;
        }
        
        .history-section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .history-table {
            margin: 1.5rem 0;
        }
        
        .history-table .table {
            font-size: 0.9rem;
        }
        
        .no-data {
            text-align: center;
            color: #999;
            padding: 2rem;
        }
        
        .btn-action {
            background: none;
            border: none;
            font-size: 1rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .btn-action:hover {
            background: #3498db;
            color: white;
        }
        
        .history-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .stats-section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            font-size: 1.5rem;
        }
        
        .stat-info h3 {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        /* Modals */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            width: 90%;
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-large {
            max-width: 1000px;
        }
        
        .modal-header {
            padding: 1.5rem;
            background: #2c3e50;
            color: white;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .close-modal {
            font-size: 2rem;
            cursor: pointer;
            color: white;
        }
        
        .close-modal:hover {
            color: #e67e22;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .preview-data {
            max-height: 400px;
            overflow-y: auto;
            margin: 1rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .preview-table {
            width: 100%;
            font-size: 0.9rem;
            border-collapse: collapse;
        }
        
        .preview-table td {
            padding: 0.5rem;
            border: 1px solid #ddd;
        }
        
        .preview-info {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.5rem;
            margin: 0.5rem 0;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .export-grid {
                grid-template-columns: 1fr;
            }
            
            .custom-export-form .form-row {
                grid-template-columns: 1fr;
            }
            
            .history-actions {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 2% auto;
            }
        }
    </style>
</body>
</html>