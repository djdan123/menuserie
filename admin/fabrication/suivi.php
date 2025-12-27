<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

redirigerSiNonAdmin();

// Filtres
$statut = isset($_GET['statut']) ? securiser($_GET['statut']) : '';
$artisan = isset($_GET['artisan']) ? intval($_GET['artisan']) : 0;
$priorite = isset($_GET['priorite']) ? securiser($_GET['priorite']) : '';

// Construire la requête
$sql = "SELECT f.*, m.nom as nom_meuble, m.images, c.nom_categorie, 
        u.nom as artisan_nom, u.prenom as artisan_prenom,
        co.date_commande, cl.nom as client_nom, cl.prenom as client_prenom
        FROM fabrication f
        JOIN meubles m ON f.id_meuble = m.id_meuble
        JOIN categories c ON m.id_categorie = c.id_categorie
        LEFT JOIN utilisateurs u ON f.id_user = u.id_user
        LEFT JOIN details_commandes dc ON m.id_meuble = dc.id_meuble
        LEFT JOIN commandes co ON dc.id_commande = co.id_commande
        LEFT JOIN utilisateurs cl ON co.id_user = cl.id_user
        WHERE 1=1";
$params = [];

if (!empty($statut)) {
    $sql .= " AND f.statut = ?";
    $params[] = $statut;
}

if ($artisan > 0) {
    $sql .= " AND f.id_user = ?";
    $params[] = $artisan;
}

if (!empty($priorite)) {
    $sql .= " AND f.priorite = ?";
    $params[] = $priorite;
}

$sql .= " GROUP BY f.id_fabrication
          ORDER BY 
            CASE f.statut 
                WHEN 'en attente' THEN 1
                WHEN 'en cours' THEN 2
                WHEN 'terminé' THEN 3
                ELSE 4
            END,
            f.date_debut ASC,
            f.date_fin ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fabrications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les artisans (utilisateurs avec rôle menuisier ou admin)
$artisans = $pdo->query("SELECT * FROM utilisateurs WHERE role IN ('admin') OR id_user IN (SELECT DISTINCT id_user FROM fabrication WHERE id_user IS NOT NULL)")
                ->fetchAll(PDO::FETCH_ASSOC);

// Statistiques fabrication
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'en attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'en cours' THEN 1 ELSE 0 END) as en_cours,
        SUM(CASE WHEN statut = 'terminé' THEN 1 ELSE 0 END) as termines,
        AVG(DATEDIFF(COALESCE(date_fin, NOW()), date_debut)) as duree_moyenne
    FROM fabrication
    WHERE date_debut IS NOT NULL
")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi de fabrication - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include '../../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../../includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>Atelier - Suivi de fabrication</h1>
                <button class="btn btn-success" onclick="ouvrirModalNouveau()">+ Nouveau projet</button>
            </div>
            
            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-info">
                        <h3>Total projets</h3>
                        <p class="stat-value"><?php echo $stats['total']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-info">
                        <h3>En attente</h3>
                        <p class="stat-value"><?php echo $stats['en_attente']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🔨</div>
                    <div class="stat-info">
                        <h3>En cours</h3>
                        <p class="stat-value"><?php echo $stats['en_cours']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <h3>Terminés</h3>
                        <p class="stat-value"><?php echo $stats['termines']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⏱️</div>
                    <div class="stat-info">
                        <h3>Durée moyenne</h3>
                        <p class="stat-value"><?php echo round($stats['duree_moyenne'] ?? 0); ?> jours</p>
                    </div>
                </div>
            </div>
            
            <!-- Filtres -->
            <div class="filtres-card">
                <h3>Filtres</h3>
                <form method="GET" class="filtre-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="statut">Statut :</label>
                            <select id="statut" name="statut">
                                <option value="">Tous les statuts</option>
                                <option value="en attente" <?php echo ($statut == 'en attente') ? 'selected' : ''; ?>>En attente</option>
                                <option value="en cours" <?php echo ($statut == 'en cours') ? 'selected' : ''; ?>>En cours</option>
                                <option value="terminé" <?php echo ($statut == 'terminé') ? 'selected' : ''; ?>>Terminé</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="artisan">Artisan :</label>
                            <select id="artisan" name="artisan">
                                <option value="0">Tous les artisans</option>
                                <?php foreach ($artisans as $artisan_item): ?>
                                    <option value="<?php echo $artisan_item['id_user']; ?>" 
                                            <?php echo ($artisan == $artisan_item['id_user']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($artisan_item['prenom'] . ' ' . $artisan_item['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="priorite">Priorité :</label>
                            <select id="priorite" name="priorite">
                                <option value="">Toutes priorités</option>
                                <option value="haute" <?php echo ($priorite == 'haute') ? 'selected' : ''; ?>>Haute</option>
                                <option value="normale" <?php echo ($priorite == 'normale') ? 'selected' : ''; ?>>Normale</option>
                                <option value="basse" <?php echo ($priorite == 'basse') ? 'selected' : ''; ?>>Basse</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-secondary">Filtrer</button>
                            <a href="suivi.php" class="btn btn-link">Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Tableau des fabrications -->
            <div class="table-card">
                <div class="table-header">
                    <h3>Projets de fabrication (<?php echo count($fabrications); ?>)</h3>
                    <div class="actions">
                        <button class="btn btn-secondary" onclick="exporterPlanning()">📅 Exporter planning</button>
                        <button class="btn btn-primary" onclick="imprimerFiches()">🖨️ Fiches de travail</button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table fabrication-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Meuble</th>
                                <th>Client</th>
                                <th>Dates</th>
                                <th>Durée</th>
                                <th>Artisan</th>
                                <th>Progression</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fabrications as $fab): ?>
                                <?php
                                // Calculer la progression
                                $progression = 0;
                                $duree = null;
                                
                                if ($fab['date_debut'] && $fab['date_fin']) {
                                    $debut = new DateTime($fab['date_debut']);
                                    $fin = new DateTime($fab['date_fin']);
                                    $now = new DateTime();
                                    
                                    $total = $debut->diff($fin)->days;
                                    $ecoule = $debut->diff($now)->days;
                                    
                                    if ($total > 0) {
                                        $progression = min(100, ($ecoule / $total) * 100);
                                    }
                                    $duree = $total . ' jours';
                                }
                                
                                // Déterminer la classe de statut
                                $statut_class = '';
                                switch ($fab['statut']) {
                                    case 'en attente': $statut_class = 'warning'; break;
                                    case 'en cours': $statut_class = 'info'; break;
                                    case 'terminé': $statut_class = 'success'; break;
                                }
                                
                                // Déterminer la priorité
                                $priorite_class = '';
                                $priorite_icon = '';
                                switch ($fab['priorite'] ?? 'normale') {
                                    case 'haute': 
                                        $priorite_class = 'priorite-haute';
                                        $priorite_icon = '🔥';
                                        break;
                                    case 'normale': 
                                        $priorite_class = 'priorite-normale';
                                        $priorite_icon = '⚡';
                                        break;
                                    case 'basse': 
                                        $priorite_class = 'priorite-basse';
                                        $priorite_icon = '🐌';
                                        break;
                                }
                                ?>
                                
                                <tr class="<?php echo $priorite_class; ?>">
                                    <td>#<?php echo $fab['id_fabrication']; ?></td>
                                    <td>
                                        <div class="meuble-info">
                                            <?php if (!empty($fab['images'])): ?>
                                                <img src="../../uploads/produits/<?php echo htmlspecialchars($fab['images']); ?>" 
                                                     alt="<?php echo htmlspecialchars($fab['nom_meuble']); ?>"
                                                     class="meuble-thumb">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($fab['nom_meuble']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($fab['nom_categorie']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($fab['client_nom'])): ?>
                                            <?php echo htmlspecialchars($fab['client_prenom'] . ' ' . $fab['client_nom']); ?><br>
                                            <small>Cmd: <?php echo date('d/m/Y', strtotime($fab['date_commande'])); ?></small>
                                        <?php else: ?>
                                            <em>Stock</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($fab['date_debut']): ?>
                                            Début: <?php echo date('d/m/Y', strtotime($fab['date_debut'])); ?><br>
                                        <?php endif; ?>
                                        <?php if ($fab['date_fin']): ?>
                                            Fin: <?php echo date('d/m/Y', strtotime($fab['date_fin'])); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $duree ?? 'Non définie'; ?>
                                        <?php if ($fab['priorite']): ?>
                                            <br><small><?php echo $priorite_icon; ?> <?php echo $fab['priorite']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($fab['artisan_nom'])): ?>
                                            <?php echo htmlspecialchars($fab['artisan_prenom'] . ' ' . $fab['artisan_nom']); ?>
                                        <?php else: ?>
                                            <em>Non assigné</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="progress-container">
                                            <div class="progress-bar">
                                                <div class="progress" style="width: <?php echo $progression; ?>%;"></div>
                                            </div>
                                            <span class="progress-text"><?php echo round($progression); ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $statut_class; ?>">
                                            <?php echo $fab['statut']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action edit" 
                                                    onclick="modifierFabrication(<?php echo $fab['id_fabrication']; ?>)"
                                                    title="Modifier">✏️</button>
                                            <button class="btn-action view" 
                                                    onclick="voirDetails(<?php echo $fab['id_fabrication']; ?>)"
                                                    title="Détails">👁️</button>
                                            <?php if ($fab['statut'] != 'terminé'): ?>
                                                <button class="btn-action complete" 
                                                        onclick="terminerFabrication(<?php echo $fab['id_fabrication']; ?>)"
                                                        title="Terminer">✅</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($fabrications)): ?>
                        <div class="no-results">
                            <p>Aucun projet de fabrication trouvé</p>
                            <button class="btn btn-primary" onclick="ouvrirModalNouveau()">Créer un projet</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sections par statut -->
            <div class="fabrication-sections">
                <!-- En attente -->
                <?php
                $en_attente = array_filter($fabrications, function($fab) {
                    return $fab['statut'] == 'en attente';
                });
                ?>
                <?php if (!empty($en_attente)): ?>
                    <div class="section-card section-warning">
                        <h3>⏳ En attente (<?php echo count($en_attente); ?>)</h3>
                        <div class="kanban-cards">
                            <?php foreach ($en_attente as $fab): ?>
                                <div class="kanban-card">
                                    <div class="kanban-header">
                                        <h4><?php echo htmlspecialchars($fab['nom_meuble']); ?></h4>
                                        <span class="badge badge-warning">En attente</span>
                                    </div>
                                    <div class="kanban-body">
                                        <p><small><?php echo htmlspecialchars($fab['nom_categorie']); ?></small></p>
                                        <p>Client: <?php echo !empty($fab['client_nom']) ? htmlspecialchars($fab['client_prenom']) : 'Stock'; ?></p>
                                        <div class="kanban-actions">
                                            <button class="btn btn-sm" 
                                                    onclick="demarrerFabrication(<?php echo $fab['id_fabrication']; ?>)">
                                                🚀 Démarrer
                                            </button>
                                            <button class="btn btn-sm" 
                                                    onclick="assignerArtisan(<?php echo $fab['id_fabrication']; ?>)">
                                                👤 Assigner
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- En cours -->
                <?php
                $en_cours = array_filter($fabrications, function($fab) {
                    return $fab['statut'] == 'en cours';
                });
                ?>
                <?php if (!empty($en_cours)): ?>
                    <div class="section-card section-info">
                        <h3>🔨 En cours (<?php echo count($en_cours); ?>)</h3>
                        <div class="kanban-cards">
                            <?php foreach ($en_cours as $fab): ?>
                                <?php
                                // Calculer les jours restants
                                $jours_restants = 'N/A';
                                if ($fab['date_debut'] && $fab['date_fin']) {
                                    $debut = new DateTime($fab['date_debut']);
                                    $fin = new DateTime($fab['date_fin']);
                                    $now = new DateTime();
                                    
                                    $total = $debut->diff($fin)->days;
                                    $ecoule = $debut->diff($now)->days;
                                    $restants = $total - $ecoule;
                                    
                                    if ($restants > 0) {
                                        $jours_restants = $restants . ' jours';
                                    } elseif ($restants < 0) {
                                        $jours_restants = '<span style="color: #e74c3c;">En retard: ' . abs($restants) . 'j</span>';
                                    } else {
                                        $jours_restants = 'Termine aujourd\'hui';
                                    }
                                }
                                ?>
                                
                                <div class="kanban-card">
                                    <div class="kanban-header">
                                        <h4><?php echo htmlspecialchars($fab['nom_meuble']); ?></h4>
                                        <span class="badge badge-info">En cours</span>
                                    </div>
                                    <div class="kanban-body">
                                        <p>Artisan: <?php echo !empty($fab['artisan_nom']) ? htmlspecialchars($fab['artisan_prenom']) : 'Non assigné'; ?></p>
                                        <p>Début: <?php echo date('d/m/Y', strtotime($fab['date_debut'])); ?></p>
                                        <p>Fin: <?php echo date('d/m/Y', strtotime($fab['date_fin'])); ?></p>
                                        <p><strong>Jours restants:</strong> <?php echo $jours_restants; ?></p>
                                        <div class="progress-bar-small">
                                            <div class="progress" style="width: 60%;"></div>
                                        </div>
                                        <div class="kanban-actions">
                                            <button class="btn btn-sm btn-success" 
                                                    onclick="ajouterNote(<?php echo $fab['id_fabrication']; ?>)">
                                                📝 Note
                                            </button>
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="terminerFabrication(<?php echo $fab['id_fabrication']; ?>)">
                                                ✅ Terminer
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Nouveau design tableau -->
            <div class="fabrication-container">
                <h1>Suivi de fabrication</h1>
                <table>
                    <thead>
                        <tr>
                            <th>Meuble</th>
                            <th>Image</th>
                            <th>Responsable</th>
                            <th>Date début</th>
                            <th>Date fin</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fabrications as $fab): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fab['nom_meuble']); ?></td>
                                <td>
                                    <?php if (!empty($fab['images'])): ?>
                                        <img src="../../uploads/produits/<?php echo htmlspecialchars($fab['images']); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:4px;" alt="<?php echo htmlspecialchars($fab['nom_meuble']); ?>">
                                    <?php else: ?>
                                        <img src="../../assets/images/produits/default.jpg" style="width:60px;height:60px;object-fit:cover;border-radius:4px;" alt="Image par défaut">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($fab['artisan_prenom'].' '.$fab['artisan_nom']); ?></td>
                                <td><?php echo $fab['date_debut'] ? date('d/m/Y H:i', strtotime($fab['date_debut'])) : '-'; ?></td>
                                <td><?php echo $fab['date_fin'] ? date('d/m/Y H:i', strtotime($fab['date_fin'])) : '-'; ?></td>
                                <td class="statut <?php echo str_replace(' ', '-', $fab['statut']); ?>">
                                    <?php echo ucfirst($fab['statut']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Modal Nouveau projet -->
    <div id="modalNouveau" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nouveau projet de fabrication</h2>
                <span class="close-modal" onclick="fermerModal('modalNouveau')">&times;</span>
            </div>
            
            <div class="modal-body">
                <form id="formNouveau" method="POST" action="actions/ajouter.php">
                    <div class="form-group">
                        <label for="id_meuble">Meuble à fabriquer *</label>
                        <select id="id_meuble" name="id_meuble" required>
                            <option value="">Sélectionner un meuble</option>
                            <?php
                            $meubles = $pdo->query("SELECT * FROM meubles ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($meubles as $meuble):
                            ?>
                                <option value="<?php echo $meuble['id_meuble']; ?>">
                                    <?php echo htmlspecialchars($meuble['nom']); ?> (<?php echo htmlspecialchars($meuble['bois_type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_debut">Date de début prévue</label>
                            <input type="date" id="date_debut" name="date_debut">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_fin">Date de fin prévue</label>
                            <input type="date" id="date_fin" name="date_fin">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_user">Artisan responsable</label>
                            <select id="id_user" name="id_user">
                                <option value="">Non assigné</option>
                                <?php foreach ($artisans as $artisan): ?>
                                    <option value="<?php echo $artisan['id_user']; ?>">
                                        <?php echo htmlspecialchars($artisan['prenom'] . ' ' . $artisan['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="priorite">Priorité</label>
                            <select id="priorite" name="priorite">
                                <option value="normale">Normale</option>
                                <option value="haute">Haute</option>
                                <option value="basse">Basse</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes initiales</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Instructions spéciales, détails, etc."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Créer le projet</button>
                        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalNouveau')">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Modification -->
    <div id="modalModification" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Modifier le projet</h2>
                <span class="close-modal" onclick="fermerModal('modalModification')">&times;</span>
            </div>
            
            <div class="modal-body">
                <form id="formModification" method="POST" action="actions/modifier.php">
                    <input type="hidden" id="mod_id_fabrication" name="id_fabrication">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="mod_date_debut">Date de début</label>
                            <input type="date" id="mod_date_debut" name="date_debut">
                        </div>
                        
                        <div class="form-group">
                            <label for="mod_date_fin">Date de fin</label>
                            <input type="date" id="mod_date_fin" name="date_fin">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="mod_id_user">Artisan</label>
                            <select id="mod_id_user" name="id_user">
                                <option value="">Non assigné</option>
                                <?php foreach ($artisans as $artisan): ?>
                                    <option value="<?php echo $artisan['id_user']; ?>">
                                        <?php echo htmlspecialchars($artisan['prenom'] . ' ' . $artisan['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="mod_statut">Statut</label>
                            <select id="mod_statut" name="statut">
                                <option value="en attente">En attente</option>
                                <option value="en cours">En cours</option>
                                <option value="terminé">Terminé</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="mod_priorite">Priorité</label>
                            <select id="mod_priorite" name="priorite">
                                <option value="normale">Normale</option>
                                <option value="haute">Haute</option>
                                <option value="basse">Basse</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="mod_notes">Notes</label>
                        <textarea id="mod_notes" name="notes" rows="4"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalModification')">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Gestion des modals
        function ouvrirModalNouveau() {
            document.getElementById('modalNouveau').style.display = 'block';
        }
        
        function modifierFabrication(id) {
            // Récupérer les données via AJAX
            fetch(`actions/get.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const fab = data.fabrication;
                        document.getElementById('mod_id_fabrication').value = fab.id_fabrication;
                        document.getElementById('mod_date_debut').value = fab.date_debut ? fab.date_debut.split(' ')[0] : '';
                        document.getElementById('mod_date_fin').value = fab.date_fin ? fab.date_fin.split(' ')[0] : '';
                        document.getElementById('mod_id_user').value = fab.id_user || '';
                        document.getElementById('mod_statut').value = fab.statut;
                        document.getElementById('mod_priorite').value = fab.priorite || 'normale';
                        document.getElementById('mod_notes').value = fab.notes || '';
                        
                        document.getElementById('modalModification').style.display = 'block';
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors du chargement des données');
                });
        }
        
        function fermerModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Actions rapides
        function demarrerFabrication(id) {
            if (confirm('Démarrer la fabrication ?')) {
                fetch('actions/demarrer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id_fabrication=${id}`
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
        
        function terminerFabrication(id) {
            if (confirm('Marquer comme terminé ?')) {
                fetch('actions/terminer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id_fabrication=${id}`
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
        
        function assignerArtisan(id) {
            const artisan = prompt('ID de l\'artisan à assigner :');
            if (artisan !== null) {
                fetch('actions/assigner.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id_fabrication=${id}&id_user=${artisan}`
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
        
        function ajouterNote(id) {
            const note = prompt('Ajouter une note :');
            if (note !== null) {
                fetch('actions/note.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id_fabrication=${id}&note=${encodeURIComponent(note)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Note ajoutée');
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        function voirDetails(id) {
            window.open(`details.php?id=${id}`, '_blank');
        }
        
        function exporterPlanning() {
            const dateDebut = prompt('Date de début (YYYY-MM-DD) :', new Date().toISOString().split('T')[0]);
            const dateFin = prompt('Date de fin (YYYY-MM-DD) :', new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
            
            if (dateDebut && dateFin) {
                window.open(`export_planning.php?debut=${dateDebut}&fin=${dateFin}`, '_blank');
            }
        }
        
        function imprimerFiches() {
            const ids = prompt('IDs des projets à imprimer (séparés par des virgules) ou "all" pour tous :');
            if (ids !== null) {
                window.open(`fiches_travail.php?ids=${ids}`, '_blank');
            }
        }
        
        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Validation des dates
        document.getElementById('formNouveau').addEventListener('submit', function(e) {
            const dateDebut = document.getElementById('date_debut').value;
            const dateFin = document.getElementById('date_fin').value;
            
            if (dateDebut && dateFin && new Date(dateFin) < new Date(dateDebut)) {
                e.preventDefault();
                alert('La date de fin ne peut pas être antérieure à la date de début !');
            }
        });
        
        document.getElementById('formModification').addEventListener('submit', function(e) {
            const dateDebut = document.getElementById('mod_date_debut').value;
            const dateFin = document.getElementById('mod_date_fin').value;
            
            if (dateDebut && dateFin && new Date(dateFin) < new Date(dateDebut)) {
                e.preventDefault();
                alert('La date de fin ne peut pas être antérieure à la date de début !');
            }
        });
    </script>
    
    <style>
        .fabrication-table {
            font-size: 0.9rem;
        }
        
        .fabrication-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .meuble-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .meuble-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #eee;
        }
        
        .progress-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .progress-bar {
            flex: 1;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-bar .progress {
            height: 100%;
            background: #3498db;
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .progress-text {
            font-size: 0.8rem;
            font-weight: 600;
            color: #2c3e50;
            min-width: 40px;
            text-align: right;
        }
        
        .priorite-haute {
            background-color: #ffeaea !important;
        }
        
        .priorite-haute:hover {
            background-color: #ffd6d6 !important;
        }
        
        .priorite-normale {
            background-color: #ffffff !important;
        }
        
        .priorite-basse {
            background-color: #f8f9fa !important;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.25rem;
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
        
        .btn-action.edit:hover {
            background: #3498db;
            color: white;
        }
        
        .btn-action.view:hover {
            background: #2ecc71;
            color: white;
        }
        
        .btn-action.complete:hover {
            background: #27ae60;
            color: white;
        }
        
        /* Sections Kanban */
        .fabrication-sections {
            margin-top: 2rem;
        }
        
        .section-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-warning {
            border-left: 4px solid #f39c12;
        }
        
        .section-info {
            border-left: 4px solid #3498db;
        }
        
        .section-success {
            border-left: 4px solid #27ae60;
        }
        
        .kanban-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .kanban-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid #eee;
            transition: all 0.3s;
        }
        
        .kanban-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .kanban-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .kanban-header h4 {
            margin: 0;
            color: #2c3e50;
            font-size: 1rem;
        }
        
        .kanban-body p {
            margin: 0.25rem 0;
            font-size: 0.9rem;
            color: #666;
        }
        
        .kanban-body strong {
            color: #2c3e50;
        }
        
        .progress-bar-small {
            height: 4px;
            background: #eee;
            border-radius: 2px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .kanban-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
        }
        
        /* Modal styles */
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
            max-width: 600px;
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
            position: sticky;
            top: 0;
            z-index: 1;
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
        
        .form-actions {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            display: flex;
            gap: 1rem;
        }
        
        .fabrication-container {
            margin-top: 2rem;
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .fabrication-container h1 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .fabrication-container table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .fabrication-container th, .fabrication-container td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .fabrication-container th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .fabrication-container tr:hover {
            background: #f1f1f1;
        }
        
        .statut {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .statut.en-attente {
            background: #ffeeba;
            color: #856404;
        }
        
        .statut.en-cours {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .statut.termine {
            background: #cfe2ff;
            color: #084298;
        }
        
        @media (max-width: 768px) {
            .kanban-cards {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 2% auto;
            }
            
            .meuble-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .meuble-thumb {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</body>
</html>