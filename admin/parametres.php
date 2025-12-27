<?php
require_once '../config/database.php';
require_once '../config/functions.php';

redirigerSiNonAdmin();

// Tableau des paramètres par défaut
$parametres = [
    'site_nom' => 'Menuiserie Bois Noble',
    'site_description' => 'Fabrication artisanale de meubles en bois massif',
    'site_email' => 'contact@menuiserie.com',
    'site_telephone' => '01 23 45 67 89',
    'site_adresse' => '123 Rue de l\'Artisanat, 75000 Paris',
    'site_horaires' => 'Lundi - Vendredi : 9h - 18h',
    'tva_taux' => '20',
    'frais_livraison' => '49',
    'seuil_livraison_gratuite' => '500',
    'delai_livraison' => '15',
    'maintenance_mode' => '0',
    'currency' => 'EUR',
    'timezone' => 'Europe/Paris'
];

// Charger les paramètres depuis la base de données
try {
    // Créer la table des paramètres si elle n'existe pas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS parametres (
            id_parametre INT PRIMARY KEY AUTO_INCREMENT,
            cle VARCHAR(100) UNIQUE NOT NULL,
            valeur TEXT,
            type VARCHAR(50),
            categorie VARCHAR(50),
            description TEXT,
            date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Charger les paramètres existants
    $stmt = $pdo->query("SELECT cle, valeur FROM parametres");
    $parametres_db = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Fusionner avec les paramètres par défaut
    $parametres = array_merge($parametres, $parametres_db);
    
} catch (PDOException $e) {
    // Si erreur, continuer avec les paramètres par défaut
    error_log("Erreur chargement paramètres: " . $e->getMessage());
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sauvegarde des paramètres
    if (isset($_POST['action']) && $_POST['action'] === 'sauvegarder') {
        try {
            $pdo->beginTransaction();
            
            foreach ($_POST['parametres'] as $cle => $valeur) {
                // Nettoyer la valeur
                $valeur = securiser($valeur);
                
                // Vérifier si le paramètre existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM parametres WHERE cle = ?");
                $stmt->execute([$cle]);
                $exists = $stmt->fetchColumn();
                
                if ($exists) {
                    // Mettre à jour
                    $stmt = $pdo->prepare("UPDATE parametres SET valeur = ?, date_modification = NOW() WHERE cle = ?");
                    $stmt->execute([$valeur, $cle]);
                } else {
                    // Insérer
                    $type = 'texte';
                    $categorie = 'general';
                    $description = '';
                    
                    // Déterminer le type et la catégorie
                    if (is_numeric($valeur)) {
                        $type = 'nombre';
                    } elseif (in_array($cle, ['maintenance_mode'])) {
                        $type = 'boolean';
                    } elseif (strpos($cle, 'tva') !== false || strpos($cle, 'frais') !== false) {
                        $categorie = 'financier';
                    } elseif (strpos($cle, 'site_') === 0) {
                        $categorie = 'site';
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO parametres (cle, valeur, type, categorie, description) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$cle, $valeur, $type, $categorie, $description]);
                }
            }
            
            $pdo->commit();
            
            // Mettre à jour le tableau des paramètres
            $stmt = $pdo->query("SELECT cle, valeur FROM parametres");
            $parametres = array_merge($parametres, $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
            
            flashMessage("Paramètres sauvegardés avec succès", "success");
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            flashMessage("Erreur lors de la sauvegarde: " . $e->getMessage(), "error");
        }
    }
    
    // Action : basculer en maintenance
    if (isset($_POST['action']) && $_POST['action'] === 'maintenance') {
        $mode = $_POST['maintenance_mode'] === '1' ? '1' : '0';
        $message = $_POST['maintenance_message'] ?? 'Site en maintenance';
        
        // Sauvegarder
        $stmt = $pdo->prepare("INSERT INTO parametres (cle, valeur) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE valeur = ?, date_modification = NOW()");
        $stmt->execute(['maintenance_mode', $mode, $mode]);
        $stmt->execute(['maintenance_message', $message, $message]);
        
        $parametres['maintenance_mode'] = $mode;
        $parametres['maintenance_message'] = $message;
        
        flashMessage("Mode maintenance " . ($mode === '1' ? 'activé' : 'désactivé'), "success");
    }
    
    // Action : sauvegarder les infos de contact
    if (isset($_POST['action']) && $_POST['action'] === 'contact') {
        $infos = [
            'site_nom' => $_POST['site_nom'],
            'site_email' => $_POST['site_email'],
            'site_telephone' => $_POST['site_telephone'],
            'site_adresse' => $_POST['site_adresse'],
            'site_horaires' => $_POST['site_horaires']
        ];
        
        foreach ($infos as $cle => $valeur) {
            $stmt = $pdo->prepare("INSERT INTO parametres (cle, valeur) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE valeur = ?, date_modification = NOW()");
            $stmt->execute([$cle, $valeur, $valeur]);
        }
        
        $parametres = array_merge($parametres, $infos);
        flashMessage("Informations de contact mises à jour", "success");
    }
    
    // Rediriger pour éviter la resoumission du formulaire
    header('Location: parametres.php');
    exit();
}

// Récupérer l'historique des modifications
$historique = $pdo->query("
    SELECT cle, valeur, date_modification 
    FROM parametres 
    WHERE date_modification > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY date_modification DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Informations système
$infos_systeme = [
    'PHP Version' => PHP_VERSION,
    'MySQL Version' => $pdo->query("SELECT VERSION()")->fetchColumn(),
    'Serveur' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
    'Dernière sauvegarde' => date('d/m/Y H:i', filemtime('../config/database.php')),
    'Espace disque' => round(disk_free_space(__DIR__) / (1024 * 1024 * 1024), 2) . ' GB libre',
    'Mémoire max' => ini_get('memory_limit'),
    'Temps d\'exécution max' => ini_get('max_execution_time') . 's'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres du site - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>⚙️ Paramètres du site</h1>
                <button class="btn btn-success" onclick="sauvegarderTous()">💾 Sauvegarder tout</button>
            </div>
            
            <?php echo getFlashMessage(); ?>
            
            <!-- Onglets -->
            <div class="settings-tabs">
                <button class="tab-btn active" onclick="ouvrirOnglet('general')">🌐 Général</button>
                <button class="tab-btn" onclick="ouvrirOnglet('financier')">💰 Financier</button>
                <button class="tab-btn" onclick="ouvrirOnglet('livraison')">🚚 Livraison</button>
                <button class="tab-btn" onclick="ouvrirOnglet('maintenance')">🔧 Maintenance</button>
                <button class="tab-btn" onclick="ouvrirOnglet('systeme')">🖥️ Système</button>
            </div>
            
            <!-- Formulaire principal -->
            <form method="POST" id="formParametres">
                <input type="hidden" name="action" value="sauvegarder">
                
                <!-- Onglet Général -->
                <div id="onglet-general" class="onglet-content active">
                    <div class="settings-card">
                        <h2>🌐 Informations générales</h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="site_nom">Nom du site *</label>
                                <input type="text" id="site_nom" name="parametres[site_nom]" 
                                       value="<?php echo htmlspecialchars($parametres['site_nom']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_description">Description</label>
                                <textarea id="site_description" name="parametres[site_description]" rows="3"><?php echo htmlspecialchars($parametres['site_description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_email">Email de contact *</label>
                                <input type="email" id="site_email" name="parametres[site_email]" 
                                       value="<?php echo htmlspecialchars($parametres['site_email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_telephone">Téléphone</label>
                                <input type="text" id="site_telephone" name="parametres[site_telephone]" 
                                       value="<?php echo htmlspecialchars($parametres['site_telephone']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="site_adresse">Adresse</label>
                                <textarea id="site_adresse" name="parametres[site_adresse]" rows="2"><?php echo htmlspecialchars($parametres['site_adresse']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_horaires">Horaires d'ouverture</label>
                                <input type="text" id="site_horaires" name="parametres[site_horaires]" 
                                       value="<?php echo htmlspecialchars($parametres['site_horaires']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="currency">Devise</label>
                                <select id="currency" name="parametres[currency]">
                                    <option value="EUR" <?php echo ($parametres['currency'] == 'EUR') ? 'selected' : ''; ?>>Euro (€)</option>
                                    <option value="USD" <?php echo ($parametres['currency'] == 'USD') ? 'selected' : ''; ?>>Dollar ($)</option>
                                    <option value="GBP" <?php echo ($parametres['currency'] == 'GBP') ? 'selected' : ''; ?>>Livre (£)</option>
                                    <option value="CHF" <?php echo ($parametres['currency'] == 'CHF') ? 'selected' : ''; ?>>Franc suisse (CHF)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="timezone">Fuseau horaire</label>
                                <select id="timezone" name="parametres[timezone]">
                                    <option value="Europe/Paris" <?php echo ($parametres['timezone'] == 'Europe/Paris') ? 'selected' : ''; ?>>Europe/Paris</option>
                                    <option value="Europe/London" <?php echo ($parametres['timezone'] == 'Europe/London') ? 'selected' : ''; ?>>Europe/London</option>
                                    <option value="America/New_York" <?php echo ($parametres['timezone'] == 'America/New_York') ? 'selected' : ''; ?>>America/New_York</option>
                                    <option value="Asia/Tokyo" <?php echo ($parametres['timezone'] == 'Asia/Tokyo') ? 'selected' : ''; ?>>Asia/Tokyo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Financier -->
                <div id="onglet-financier" class="onglet-content">
                    <div class="settings-card">
                        <h2>💰 Paramètres financiers</h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="tva_taux">Taux de TVA (%) *</label>
                                <input type="number" id="tva_taux" name="parametres[tva_taux]" 
                                       value="<?php echo htmlspecialchars($parametres['tva_taux']); ?>" 
                                       min="0" max="100" step="0.1" required>
                                <small>TVA applicable à tous les produits</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="frais_livraison">Frais de livraison standard (€)</label>
                                <input type="number" id="frais_livraison" name="parametres[frais_livraison]" 
                                       value="<?php echo htmlspecialchars($parametres['frais_livraison']); ?>" 
                                       min="0" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="seuil_livraison_gratuite">Seuil livraison gratuite (€)</label>
                                <input type="number" id="seuil_livraison_gratuite" name="parametres[seuil_livraison_gratuite]" 
                                       value="<?php echo htmlspecialchars($parametres['seuil_livraison_gratuite']); ?>" 
                                       min="0" step="0.01">
                                <small>0 = pas de livraison gratuite</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="marge_minimale">Marge minimale (%)</label>
                                <input type="number" id="marge_minimale" name="parametres[marge_minimale]" 
                                       value="<?php echo $parametres['marge_minimale'] ?? '30'; ?>" 
                                       min="0" max="100" step="1">
                                <small>Pour alerte sur les nouveaux produits</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="monnaie_decimals">Décimales monnaie</label>
                                <select id="monnaie_decimals" name="parametres[monnaie_decimals]">
                                    <option value="2" <?php echo ($parametres['monnaie_decimals'] ?? '2') == '2' ? 'selected' : ''; ?>>2 (ex: 10,99 €)</option>
                                    <option value="0" <?php echo ($parametres['monnaie_decimals'] ?? '2') == '0' ? 'selected' : ''; ?>>0 (ex: 11 €)</option>
                                </select>
                            </div>
                        </div>
                        
                        <h3>🔄 Conversion devise</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="taux_usd">Taux USD/EUR</label>
                                <input type="number" id="taux_usd" name="parametres[taux_usd]" 
                                       value="<?php echo $parametres['taux_usd'] ?? '0.92'; ?>" 
                                       min="0" step="0.0001">
                            </div>
                            
                            <div class="form-group">
                                <label for="taux_gbp">Taux GBP/EUR</label>
                                <input type="number" id="taux_gbp" name="parametres[taux_gbp]" 
                                       value="<?php echo $parametres['taux_gbp'] ?? '1.17'; ?>" 
                                       min="0" step="0.0001">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Livraison -->
                <div id="onglet-livraison" class="onglet-content">
                    <div class="settings-card">
                        <h2>🚚 Paramètres de livraison</h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="delai_livraison">Délai de livraison standard (jours) *</label>
                                <input type="number" id="delai_livraison" name="parametres[delai_livraison]" 
                                       value="<?php echo htmlspecialchars($parametres['delai_livraison']); ?>" 
                                       min="1" max="365" required>
                                <small>Pour produits en stock</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="delai_fabrication">Délai fabrication sur mesure (jours)</label>
                                <input type="number" id="delai_fabrication" name="parametres[delai_fabrication]" 
                                       value="<?php echo $parametres['delai_fabrication'] ?? '30'; ?>" 
                                       min="1" max="365">
                            </div>
                            
                            <div class="form-group">
                                <label for="zones_livraison">Zones de livraison</label>
                                <textarea id="zones_livraison" name="parametres[zones_livraison]" rows="4"><?php echo htmlspecialchars($parametres['zones_livraison'] ?? 'France métropolitaine\nBelgique\nSuisse\nLuxembourg'); ?></textarea>
                                <small>Une zone par ligne</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="transporteurs">Transporteurs partenaires</label>
                                <textarea id="transporteurs" name="parametres[transporteurs]" rows="3"><?php echo htmlspecialchars($parametres['transporteurs'] ?? 'Chronopost\nColissimo\nUPS'); ?></textarea>
                            </div>
                        </div>
                        
                        <h3>📦 Dimensions colis standards</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="colis_petit">Petit colis (cm)</label>
                                <input type="text" id="colis_petit" name="parametres[colis_petit]" 
                                       value="<?php echo $parametres['colis_petit'] ?? '30x20x10'; ?>"
                                       placeholder="LxHxP">
                            </div>
                            
                            <div class="form-group">
                                <label for="colis_moyen">Moyen colis (cm)</label>
                                <input type="text" id="colis_moyen" name="parametres[colis_moyen]" 
                                       value="<?php echo $parametres['colis_moyen'] ?? '60x40x30'; ?>"
                                       placeholder="LxHxP">
                            </div>
                            
                            <div class="form-group">
                                <label for="colis_grand">Grand colis (cm)</label>
                                <input type="text" id="colis_grand" name="parametres[colis_grand]" 
                                       value="<?php echo $parametres['colis_grand'] ?? '120x80x60'; ?>"
                                       placeholder="LxHxP">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Maintenance -->
                <div id="onglet-maintenance" class="onglet-content">
                    <div class="settings-card">
                        <h2>🔧 Mode maintenance</h2>
                        
                        <div class="maintenance-status">
                            <div class="status-indicator <?php echo ($parametres['maintenance_mode'] == '1') ? 'active' : 'inactive'; ?>">
                                <span class="status-dot"></span>
                                <span class="status-text">
                                    <?php echo ($parametres['maintenance_mode'] == '1') ? 'ACTIF' : 'INACTIF'; ?>
                                </span>
                            </div>
                            
                            <p>
                                Le mode maintenance permet de fermer temporairement le site aux visiteurs 
                                tout en laissant l'accès aux administrateurs.
                            </p>
                        </div>
                        
                        <form method="POST" class="maintenance-form">
                            <input type="hidden" name="action" value="maintenance">
                            
                            <div class="form-group switch-group">
                                <label>
                                    <span>Activer le mode maintenance</span>
                                    <label class="switch">
                                        <input type="checkbox" name="maintenance_mode" value="1" 
                                               <?php echo ($parametres['maintenance_mode'] == '1') ? 'checked' : ''; ?> 
                                               onchange="this.form.submit()">
                                        <span class="slider"></span>
                                    </label>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label for="maintenance_message">Message de maintenance</label>
                                <textarea id="maintenance_message" name="maintenance_message" rows="4"><?php echo htmlspecialchars($parametres['maintenance_message'] ?? 'Le site est actuellement en maintenance. Veuillez nous excuser pour la gêne occasionnée.'); ?></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-<?php echo ($parametres['maintenance_mode'] == '1') ? 'danger' : 'success'; ?>">
                                    <?php echo ($parametres['maintenance_mode'] == '1') ? 'Désactiver' : 'Activer'; ?> le mode maintenance
                                </button>
                            </div>
                        </form>
                        
                        <div class="maintenance-info">
                            <h3>ℹ️ Information</h3>
                            <ul>
                                <li>Les administrateurs peuvent toujours accéder au site</li>
                                <li>Les clients connectés verront le message de maintenance</li>
                                <li>Les commandes seront suspendues</li>
                                <li>Pensez à prévenir vos clients par email</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Système -->
                <div id="onglet-systeme" class="onglet-content">
                    <div class="settings-card">
                        <h2>🖥️ Informations système</h2>
                        
                        <div class="system-info-grid">
                            <?php foreach ($infos_systeme as $label => $valeur): ?>
                                <div class="system-info-item">
                                    <span class="system-label"><?php echo $label; ?>:</span>
                                    <span class="system-value"><?php echo htmlspecialchars($valeur); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <h3>📊 Base de données</h3>
                        <?php
                        $db_stats = $pdo->query("
                            SELECT 
                                TABLE_NAME as table_name,
                                ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size_mb,
                                TABLE_ROWS as rows_count
                            FROM information_schema.TABLES 
                            WHERE TABLE_SCHEMA = DATABASE()
                            ORDER BY size_mb DESC
                        ")->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Table</th>
                                    <th>Lignes</th>
                                    <th>Taille</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($db_stats as $table): ?>
                                    <tr>
                                        <td><?php echo $table['table_name']; ?></td>
                                        <td><?php echo number_format($table['rows_count']); ?></td>
                                        <td><?php echo $table['size_mb']; ?> MB</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <h3>📝 Historique des modifications</h3>
                        <div class="historique-modifications">
                            <?php if (empty($historique)): ?>
                                <p class="no-data">Aucune modification récente</p>
                            <?php else: ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Paramètre</th>
                                            <th>Valeur</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historique as $modif): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($modif['cle']); ?></td>
                                                <td>
                                                    <code><?php echo htmlspecialchars(substr($modif['valeur'], 0, 50)); ?></code>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($modif['date_modification'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        
                        <h3>⚡ Actions système</h3>
                        <div class="system-actions">
                            <button type="button" class="btn btn-secondary" onclick="viderCache()">🗑️ Vider le cache</button>
                            <button type="button" class="btn btn-secondary" onclick="optimiserBDD()">🔧 Optimiser BDD</button>
                            <button type="button" class="btn btn-warning" onclick="creerSauvegarde()">💾 Créer sauvegarde</button>
                            <button type="button" class="btn btn-danger" onclick="reinitialiserParametres()">🔄 Réinitialiser</button>
                        </div>
                    </div>
                </div>
                
                <!-- Boutons de sauvegarde -->
                <div class="settings-actions">
                    <button type="submit" class="btn btn-success">💾 Sauvegarder les modifications</button>
                    <button type="button" class="btn btn-secondary" onclick="restaurerDefauts()">🔄 Restaurer valeurs par défaut</button>
                </div>
            </form>
        </main>
    </div>
    
    <!-- Modal Sauvegarde/Restauration -->
    <div id="modalSauvegarde" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>💾 Gestion des sauvegardes</h2>
                <span class="close-modal" onclick="fermerModal()">&times;</span>
            </div>
            
            <div class="modal-body">
                <div class="backup-options">
                    <h3>Créer une sauvegarde</h3>
                    <div class="form-group">
                        <label for="backup_type">Type de sauvegarde:</label>
                        <select id="backup_type">
                            <option value="complete">Complète (BDD + fichiers)</option>
                            <option value="database">Base de données uniquement</option>
                            <option value="parametres">Paramètres uniquement</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="backup_name">Nom de la sauvegarde:</label>
                        <input type="text" id="backup_name" value="sauvegarde_<?php echo date('Y-m-d_H-i'); ?>">
                    </div>
                    
                    <button class="btn btn-success" onclick="executerSauvegarde()">Créer la sauvegarde</button>
                    
                    <h3 style="margin-top: 2rem;">Sauvegardes disponibles</h3>
                    <div id="backupList" class="backup-list">
                        <!-- Liste chargée via AJAX -->
                        <p>Chargement...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Gestion des onglets
        function ouvrirOnglet(ongletId) {
            // Désactiver tous les onglets
            document.querySelectorAll('.onglet-content').forEach(onglet => {
                onglet.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Activer l'onglet sélectionné
            document.getElementById('onglet-' + ongletId).classList.add('active');
            event.target.classList.add('active');
        }
        
        // Sauvegarde
        function sauvegarderTous() {
            document.getElementById('formParametres').submit();
        }
        
        function restaurerDefauts() {
            if (confirm('Restaurer les valeurs par défaut ? Les modifications non sauvegardées seront perdues.')) {
                fetch('actions/restaurer_defauts.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Paramètres restaurés avec succès');
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        // Actions système
        function viderCache() {
            if (confirm('Vider le cache du site ?')) {
                fetch('actions/vider_cache.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cache vidé avec succès');
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        function optimiserBDD() {
            if (confirm('Optimiser la base de données ? Cette opération peut prendre quelques minutes.')) {
                fetch('actions/optimiser_bdd.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Base de données optimisée');
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        function creerSauvegarde() {
            document.getElementById('modalSauvegarde').style.display = 'block';
            chargerSauvegardes();
        }
        
        function fermerModal() {
            document.getElementById('modalSauvegarde').style.display = 'none';
        }
        
        function chargerSauvegardes() {
            fetch('actions/liste_sauvegardes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '';
                        if (data.sauvegardes.length > 0) {
                            data.sauvegardes.forEach(sauvegarde => {
                                html += `
                                    <div class="backup-item">
                                        <div class="backup-info">
                                            <strong>${sauvegarde.nom}</strong>
                                            <small>${sauvegarde.date} - ${sauvegarde.taille}</small>
                                        </div>
                                        <div class="backup-actions">
                                            <button class="btn btn-sm" onclick="restaurerSauvegarde('${sauvegarde.nom}')">🔄</button>
                                            <button class="btn btn-sm" onclick="telechargerSauvegarde('${sauvegarde.nom}')">📥</button>
                                            <button class="btn btn-sm btn-danger" onclick="supprimerSauvegarde('${sauvegarde.nom}')">🗑️</button>
                                        </div>
                                    </div>
                                `;
                            });
                        } else {
                            html = '<p>Aucune sauvegarde disponible</p>';
                        }
                        document.getElementById('backupList').innerHTML = html;
                    }
                });
        }
        
        function executerSauvegarde() {
            const type = document.getElementById('backup_type').value;
            const nom = document.getElementById('backup_name').value.trim();
            
            if (!nom) {
                alert('Veuillez saisir un nom pour la sauvegarde');
                return;
            }
            
            fetch('actions/creer_sauvegarde.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `type=${type}&nom=${encodeURIComponent(nom)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Sauvegarde créée avec succès');
                    chargerSauvegardes();
                } else {
                    alert('Erreur: ' + data.message);
                }
            });
        }
        
        function restaurerSauvegarde(nom) {
            if (confirm(`Restaurer la sauvegarde "${nom}" ? Le site sera temporairement indisponible.`)) {
                fetch('actions/restaurer_sauvegarde.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `nom=${encodeURIComponent(nom)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Sauvegarde restaurée. Redémarrage...');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        function telechargerSauvegarde(nom) {
            window.open(`actions/telecharger_sauvegarde.php?nom=${encodeURIComponent(nom)}`, '_blank');
        }
        
        function supprimerSauvegarde(nom) {
            if (confirm(`Supprimer la sauvegarde "${nom}" ?`)) {
                fetch('actions/supprimer_sauvegarde.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `nom=${encodeURIComponent(nom)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Sauvegarde supprimée');
                        chargerSauvegardes();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        function reinitialiserParametres() {
            if (confirm('ATTENTION: Réinitialiser tous les paramètres aux valeurs par défaut ? Cette action est irréversible.')) {
                fetch('actions/reinitialiser.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Paramètres réinitialisés');
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        // Validation du formulaire
        document.getElementById('formParametres').addEventListener('submit', function(e) {
            const tva = parseFloat(document.getElementById('tva_taux').value);
            if (tva < 0 || tva > 100) {
                e.preventDefault();
                alert('Le taux de TVA doit être compris entre 0 et 100%');
                document.getElementById('tva_taux').focus();
            }
        });
        
        // Fermer modal en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                fermerModal();
            }
        };
    </script>
    
    <style>
        .settings-tabs {
            display: flex;
            gap: 0.5rem;
            margin: 1.5rem 0;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }
        
        .tab-btn {
            background: none;
            border: none;
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            color: #666;
            font-weight: 500;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .tab-btn:hover {
            background: #f8f9fa;
        }
        
        .tab-btn.active {
            background: #3498db;
            color: white;
        }
        
        .onglet-content {
            display: none;
        }
        
        .onglet-content.active {
            display: block;
        }
        
        .settings-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #666;
            font-size: 0.85rem;
        }
        
        .maintenance-status {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .status-indicator.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-indicator.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-indicator.active .status-dot {
            background: #28a745;
        }
        
        .status-indicator.inactive .status-dot {
            background: #dc3545;
        }
        
        .switch-group {
            margin: 1.5rem 0;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
            margin-left: 1rem;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #28a745;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .maintenance-info {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #e8f4fc;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .maintenance-info ul {
            margin: 0.5rem 0 0 1.5rem;
            color: #2c3e50;
        }
        
        .maintenance-info li {
            margin-bottom: 0.5rem;
        }
        
        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .system-info-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .system-label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .system-value {
            font-family: monospace;
            color: #666;
        }
        
        .historique-modifications .table {
            font-size: 0.9rem;
        }
        
        .no-data {
            color: #999;
            font-style: italic;
            text-align: center;
            padding: 2rem;
        }
        
        .system-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        .settings-actions {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid #eee;
            display: flex;
            gap: 1rem;
        }
        
        /* Modal sauvegarde */
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
        
        .backup-options .form-group {
            margin-bottom: 1.5rem;
        }
        
        .backup-list {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 1rem;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 1rem;
        }
        
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        
        .backup-item:last-child {
            border-bottom: none;
        }
        
        .backup-info {
            flex: 1;
        }
        
        .backup-info strong {
            display: block;
            color: #2c3e50;
        }
        
        .backup-info small {
            color: #666;
            font-size: 0.85rem;
        }
        
        .backup-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .settings-tabs {
                flex-wrap: wrap;
            }
            
            .tab-btn {
                flex: 1;
                min-width: 120px;
                text-align: center;
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .system-actions,
            .settings-actions {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                margin: 2% auto;
            }
            
            .backup-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .backup-actions {
                align-self: flex-end;
            }
        }
    </style>
</body>
</html>