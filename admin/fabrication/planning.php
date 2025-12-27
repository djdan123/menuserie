<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

redirigerSiNonAdmin();

// Date par défaut : semaine courante
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-d', strtotime('monday this week'));
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d', strtotime('sunday this week'));

// Récupérer les fabrications dans la période
$stmt = $pdo->prepare("SELECT f.*, m.nom as nom_meuble, m.images, c.nom_categorie, 
                       u.nom as artisan_nom, u.prenom as artisan_prenom,
                       cl.nom as client_nom, cl.prenom as client_prenom
                       FROM fabrication f
                       JOIN meubles m ON f.id_meuble = m.id_meuble
                       JOIN categories c ON m.id_categorie = c.id_categorie
                       LEFT JOIN utilisateurs u ON f.id_user = u.id_user
                       LEFT JOIN details_commandes dc ON m.id_meuble = dc.id_meuble
                       LEFT JOIN commandes co ON dc.id_commande = co.id_commande
                       LEFT JOIN utilisateurs cl ON co.id_user = cl.id_user
                       WHERE f.statut = 'en cours' 
                       AND ((f.date_debut BETWEEN ? AND ?) OR (f.date_fin BETWEEN ? AND ?) 
                            OR (f.date_debut <= ? AND f.date_fin >= ?))
                       GROUP BY f.id_fabrication
                       ORDER BY f.priorite DESC, f.date_debut ASC");

$stmt->execute([$date_debut, $date_fin, $date_debut, $date_fin, $date_debut, $date_debut]);
$fabrications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les artisans
$artisans = $pdo->query("SELECT * FROM utilisateurs WHERE role IN ('admin') ORDER BY nom, prenom")
                ->fetchAll(PDO::FETCH_ASSOC);

// Créer le calendrier
$calendrier = [];
$current = new DateTime($date_debut);
$end = new DateTime($date_fin);

while ($current <= $end) {
    $jour = $current->format('Y-m-d');
    $calendrier[$jour] = [
        'date' => $current->format('d/m/Y'),
        'jour_semaine' => $current->format('l'),
        'fabrications' => []
    ];
    $current->modify('+1 day');
}

// Répartir les fabrications dans le calendrier
foreach ($fabrications as $fab) {
    if ($fab['date_debut'] && $fab['date_fin']) {
        $debut = new DateTime($fab['date_debut']);
        $fin = new DateTime($fab['date_fin']);
        
        $current = clone $debut;
        while ($current <= $fin) {
            $jour = $current->format('Y-m-d');
            if (isset($calendrier[$jour])) {
                $calendrier[$jour]['fabrications'][] = $fab;
            }
            $current->modify('+1 day');
        }
    }
}

// Calculer la charge par artisan
$charge_artisans = [];
foreach ($artisans as $artisan) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as nb_projets, 
                           SUM(DATEDIFF(COALESCE(date_fin, NOW()), date_debut)) as jours_totaux
                           FROM fabrication 
                           WHERE id_user = ? AND statut = 'en cours'");
    $stmt->execute([$artisan['id_user']]);
    $charge = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $charge_artisans[$artisan['id_user']] = [
        'nom' => $artisan['prenom'] . ' ' . $artisan['nom'],
        'nb_projets' => $charge['nb_projets'] ?? 0,
        'jours_totaux' => $charge['jours_totaux'] ?? 0
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning de production - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/locales/fr.js"></script>
</head>
<body>
    <?php include '../../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../../includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>📅 Planning de production</h1>
                
                <!-- Navigation période -->
                <div class="planning-nav">
                    <form method="GET" class="period-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_debut">Du :</label>
                                <input type="date" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="date_fin">Au :</label>
                                <input type="date" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-secondary">Voir</button>
                                <div class="quick-periods">
                                    <button type="button" onclick="setPeriod('week')" class="btn btn-link">Cette semaine</button>
                                    <button type="button" onclick="setPeriod('month')" class="btn btn-link">Ce mois</button>
                                    <button type="button" onclick="setPeriod('next_week')" class="btn btn-link">Semaine prochaine</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Vue calendrier -->
            <div class="planning-view">
                <!-- Calendrier semaine -->
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
                
                <!-- Liste des projets -->
                <div class="projects-sidebar">
                    <div class="sidebar-section">
                        <h3>Projets en cours (<?php echo count($fabrications); ?>)</h3>
                        <div class="projects-list">
                            <?php foreach ($fabrications as $fab): ?>
                                <div class="project-card" 
                                     data-id="<?php echo $fab['id_fabrication']; ?>"
                                     data-debut="<?php echo $fab['date_debut']; ?>"
                                     data-fin="<?php echo $fab['date_fin']; ?>"
                                     data-artisan="<?php echo $fab['id_user']; ?>">
                                    <div class="project-header">
                                        <h4><?php echo htmlspecialchars($fab['nom_meuble']); ?></h4>
                                        <span class="badge badge-<?php echo $fab['priorite'] == 'haute' ? 'danger' : ($fab['priorite'] == 'basse' ? 'secondary' : 'info'); ?>">
                                            <?php echo $fab['priorite'] ?? 'normale'; ?>
                                        </span>
                                    </div>
                                    <div class="project-info">
                                        <p><small><?php echo htmlspecialchars($fab['nom_categorie']); ?></small></p>
                                        <p>Artisan: <?php echo !empty($fab['artisan_nom']) ? htmlspecialchars($fab['artisan_prenom']) : 'Non assigné'; ?></p>
                                        <p>Période: <?php echo date('d/m', strtotime($fab['date_debut'])); ?> - <?php echo date('d/m', strtotime($fab['date_fin'])); ?></p>
                                    </div>
                                    <div class="project-actions">
                                        <button class="btn btn-sm" onclick="voirProjet(<?php echo $fab['id_fabrication']; ?>)">👁️</button>
                                        <button class="btn btn-sm" onclick="modifierDates(<?php echo $fab['id_fabrication']; ?>)">📅</button>
                                        <button class="btn btn-sm" onclick="dragProject(<?php echo $fab['id_fabrication']; ?>)">👆</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Charge des artisans -->
                    <div class="sidebar-section">
                        <h3>Charge des artisans</h3>
                        <div class="artisans-load">
                            <?php foreach ($charge_artisans as $id => $charge): ?>
                                <div class="artisan-load">
                                    <div class="artisan-info">
                                        <strong><?php echo htmlspecialchars($charge['nom']); ?></strong>
                                        <span><?php echo $charge['nb_projets']; ?> projets</span>
                                    </div>
                                    <div class="load-bar">
                                        <?php
                                        $charge_niveau = min(100, ($charge['jours_totaux'] / 20) * 100);
                                        $charge_class = $charge_niveau > 80 ? 'high' : ($charge_niveau > 50 ? 'medium' : 'low');
                                        ?>
                                        <div class="load-fill load-<?php echo $charge_class; ?>" 
                                             style="width: <?php echo $charge_niveau; ?>%"></div>
                                        <span class="load-text"><?php echo round($charge_niveau); ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Vue tableau -->
            <div class="table-planning">
                <h3>Planning détaillé par jour</h3>
                <table class="table planning-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Jour</th>
                            <th>Nombre de projets</th>
                            <th>Projets</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calendrier as $jour => $data): ?>
                            <tr>
                                <td><strong><?php echo $data['date']; ?></strong></td>
                                <td><?php echo ucfirst($data['jour_semaine']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo count($data['fabrications']) > 3 ? 'danger' : (count($data['fabrications']) > 1 ? 'warning' : 'success'); ?>">
                                        <?php echo count($data['fabrications']); ?> projets
                                    </span>
                                </td>
                                <td>
                                    <div class="day-projects">
                                        <?php foreach ($data['fabrications'] as $fab): ?>
                                            <div class="day-project">
                                                <span class="project-name"><?php echo htmlspecialchars($fab['nom_meuble']); ?></span>
                                                <span class="project-artisan">
                                                    <?php echo !empty($fab['artisan_nom']) ? htmlspecialchars($fab['artisan_prenom'][0] . '. ' . $fab['artisan_nom']) : 'Non assigné'; ?>
                                                </span>
                                                <span class="project-priority priority-<?php echo $fab['priorite'] ?? 'normale'; ?>">
                                                    <?php echo $fab['priorite'] ?? 'normale'; ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-sm" onclick="ajouterProjetJour('<?php echo $jour; ?>')">+ Ajouter</button>
                                    <button class="btn btn-sm" onclick="voirJour('<?php echo $jour; ?>')">📋 Liste</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Statistiques planning -->
            <div class="planning-stats">
                <h3>Statistiques de planification</h3>
                <div class="stats-grid">
                    <?php
                    // Calcul des statistiques
                    $jours_occupes = count(array_filter($calendrier, function($jour) {
                        return count($jour['fabrications']) > 0;
                    }));
                    $projets_par_jour = count($fabrications) > 0 ? count($fabrications) / $jours_occupes : 0;
                    $artisans_surcharge = count(array_filter($charge_artisans, function($charge) {
                        return ($charge['jours_totaux'] / 20) * 100 > 80;
                    }));
                    $jours_vides = count($calendrier) - $jours_occupes;
                    ?>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-info">
                            <h3>Taux d'occupation</h3>
                            <p class="stat-value"><?php echo round(($jours_occupes / count($calendrier)) * 100); ?>%</p>
                            <small><?php echo $jours_occupes; ?> jours sur <?php echo count($calendrier); ?></small>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⚖️</div>
                        <div class="stat-info">
                            <h3>Projets/jour</h3>
                            <p class="stat-value"><?php echo round($projets_par_jour, 1); ?></p>
                            <small>Moyenne</small>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-info">
                            <h3>Artisans surchargés</h3>
                            <p class="stat-value"><?php echo $artisans_surcharge; ?></p>
                            <small>Sur <?php echo count($artisans); ?></small>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📅</div>
                        <div class="stat-info">
                            <h3>Jours disponibles</h3>
                            <p class="stat-value"><?php echo $jours_vides; ?></p>
                            <small>Sur <?php echo count($calendrier); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal Ajouter projet -->
    <div id="modalAjouter" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajouter un projet au planning</h2>
                <span class="close-modal" onclick="fermerModal()">&times;</span>
            </div>
            
            <div class="modal-body">
                <form id="formAjouter" method="POST" action="actions/planifier.php">
                    <input type="hidden" id="add_date" name="date">
                    
                    <div class="form-group">
                        <label for="add_id_meuble">Meuble</label>
                        <select id="add_id_meuble" name="id_meuble" required>
                            <option value="">Sélectionner un meuble</option>
                            <?php
                            $meubles = $pdo->query("SELECT * FROM meubles WHERE id_meuble NOT IN (SELECT id_meuble FROM fabrication WHERE statut = 'en cours') ORDER BY nom")
                                          ->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($meubles as $meuble):
                            ?>
                                <option value="<?php echo $meuble['id_meuble']; ?>">
                                    <?php echo htmlspecialchars($meuble['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_duree">Durée (jours)</label>
                            <input type="number" id="add_duree" name="duree" min="1" max="30" value="5">
                        </div>
                        
                        <div class="form-group">
                            <label for="add_artisan">Artisan</label>
                            <select id="add_artisan" name="id_user">
                                <option value="">Non assigné</option>
                                <?php foreach ($artisans as $artisan): ?>
                                    <option value="<?php echo $artisan['id_user']; ?>">
                                        <?php echo htmlspecialchars($artisan['prenom'] . ' ' . $artisan['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="add_priorite">Priorité</label>
                        <select id="add_priorite" name="priorite">
                            <option value="normale">Normale</option>
                            <option value="haute">Haute</option>
                            <option value="basse">Basse</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Planifier</button>
                        <button type="button" class="btn btn-secondary" onclick="fermerModal()">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Initialiser FullCalendar
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                locale: 'fr',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridWeek,timeGridDay,listWeek'
                },
                events: [
                    <?php foreach ($fabrications as $fab): ?>
                        {
                            id: '<?php echo $fab['id_fabrication']; ?>',
                            title: '<?php echo addslashes($fab['nom_meuble']); ?>',
                            start: '<?php echo $fab['date_debut']; ?>',
                            end: '<?php echo $fab['date_fin']; ?>',
                            extendedProps: {
                                artisan: '<?php echo addslashes($fab['artisan_prenom'] . ' ' . $fab['artisan_nom']); ?>',
                                priorite: '<?php echo $fab['priorite']; ?>',
                                client: '<?php echo addslashes($fab['client_prenom'] . ' ' . $fab['client_nom']); ?>'
                            },
                            color: '<?php echo $fab['priorite'] == 'haute' ? '#e74c3c' : ($fab['priorite'] == 'basse' ? '#95a5a6' : '#3498db'); ?>'
                        },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    voirProjet(info.event.id);
                },
                dateClick: function(info) {
                    ajouterProjetJour(info.dateStr.split('T')[0]);
                },
                eventDrop: function(info) {
                    // Mettre à jour les dates via AJAX
                    updateProjectDates(info.event.id, info.event.start, info.event.end);
                },
                eventResize: function(info) {
                    // Mettre à jour la durée via AJAX
                    updateProjectDates(info.event.id, info.event.start, info.event.end);
                }
            });
            
            calendar.render();
            window.calendar = calendar;
        });
        
        // Fonctions
        function setPeriod(period) {
            const today = new Date();
            let start, end;
            
            switch(period) {
                case 'week':
                    start = new Date(today.setDate(today.getDate() - today.getDay() + 1));
                    end = new Date(today.setDate(today.getDate() - today.getDay() + 7));
                    break;
                case 'month':
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    break;
                case 'next_week':
                    start = new Date(today.setDate(today.getDate() - today.getDay() + 8));
                    end = new Date(today.setDate(today.getDate() - today.getDay() + 14));
                    break;
            }
            
            document.getElementById('date_debut').value = start.toISOString().split('T')[0];
            document.getElementById('date_fin').value = end.toISOString().split('T')[0];
            document.querySelector('.period-form').submit();
        }
        
        function ajouterProjetJour(date) {
            document.getElementById('add_date').value = date;
            document.getElementById('modalAjouter').style.display = 'block';
        }
        
        function fermerModal() {
            document.getElementById('modalAjouter').style.display = 'none';
        }
        
        function voirProjet(id) {
            window.open(`../suivi.php#projet-${id}`, '_blank');
        }
        
        function modifierDates(id) {
            const projet = document.querySelector(`[data-id="${id}"]`);
            const nouvelleDate = prompt('Nouvelle date de début (YYYY-MM-DD) :', projet.dataset.debut.split(' ')[0]);
            const nouvelleDuree = prompt('Nouvelle durée (jours) :', 
                Math.ceil((new Date(projet.dataset.fin) - new Date(projet.dataset.debut)) / (1000 * 60 * 60 * 24)));
            
            if (nouvelleDate && nouvelleDuree) {
                const dateFin = new Date(nouvelleDate);
                dateFin.setDate(dateFin.getDate() + parseInt(nouvelleDuree));
                
                fetch('actions/modifier_dates.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id_fabrication=${id}&date_debut=${nouvelleDate}&date_fin=${dateFin.toISOString().split('T')[0]}`
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
        
        function updateProjectDates(id, start, end) {
            fetch('actions/modifier_dates.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id_fabrication=${id}&date_debut=${start.toISOString().split('T')[0]}&date_fin=${end.toISOString().split('T')[0]}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Erreur: ' + data.message);
                    // Recharger pour annuler le drag/drop
                    location.reload();
                }
            });
        }
        
        function dragProject(id) {
            const projet = document.querySelector(`[data-id="${id}"]`);
            projet.classList.add('dragging');
            
            // Activer le drag and drop sur le calendrier
            document.addEventListener('dragover', function(e) {
                e.preventDefault();
            });
            
            document.addEventListener('drop', function(e) {
                e.preventDefault();
                projet.classList.remove('dragging');
                
                // Récupérer la date cible (simplifié)
                alert('Déposez le projet sur le calendrier pour changer sa date');
            });
        }
        
        function voirJour(date) {
            const projets = <?php echo json_encode($calendrier); ?>;
            const jourProjets = projets[date]?.fabrications || [];
            
            let message = `Projets pour le ${date}:\n\n`;
            jourProjets.forEach(projet => {
                message += `• ${projet.nom_meuble} (${projet.artisan_prenom || 'Non assigné'})\n`;
            });
            
            alert(message);
        }
        
        // Fermer modal en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                fermerModal();
            }
        }
    </script>
    
    <style>
        .planning-nav {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .period-form .form-row {
            display: flex;
            align-items: end;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .quick-periods {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .planning-view {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        
        @media (max-width: 1024px) {
            .planning-view {
                grid-template-columns: 1fr;
            }
        }
        
        .calendar-container {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        #calendar {
            height: 600px;
        }
        
        .projects-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .sidebar-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .projects-list {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 1rem;
        }
        
        .project-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border: 1px solid #eee;
            transition: all 0.3s;
        }
        
        .project-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .project-card.dragging {
            opacity: 0.5;
            border: 2px dashed #3498db;
        }
        
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .project-header h4 {
            margin: 0;
            font-size: 1rem;
            color: #2c3e50;
        }
        
        .project-info {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.75rem;
        }
        
        .project-info p {
            margin: 0.25rem 0;
        }
        
        .project-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
        }
        
        .artisans-load {
            margin-top: 1rem;
        }
        
        .artisan-load {
            margin-bottom: 1rem;
        }
        
        .artisan-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .load-bar {
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        
        .load-fill {
            height: 100%;
            border-radius: 4px;
        }
        
        .load-low {
            background: #2ecc71;
        }
        
        .load-medium {
            background: #f39c12;
        }
        
        .load-high {
            background: #e74c3c;
        }
        
        .load-text {
            position: absolute;
            right: 0;
            top: -20px;
            font-size: 0.8rem;
            color: #666;
        }
        
        .table-planning {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .planning-table {
            font-size: 0.9rem;
        }
        
        .day-projects {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .day-project {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .project-name {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .project-artisan {
            color: #666;
            font-size: 0.8rem;
        }
        
        .project-priority {
            padding: 0.1rem 0.5rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .priority-haute {
            background: #e74c3c;
            color: white;
        }
        
        .priority-normale {
            background: #3498db;
            color: white;
        }
        
        .priority-basse {
            background: #95a5a6;
            color: white;
        }
        
        .planning-stats {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
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
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
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
        
        .form-actions {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            display: flex;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .planning-view {
                grid-template-columns: 1fr;
            }
            
            #calendar {
                height: 400px;
            }
            
            .projects-list {
                max-height: 300px;
            }
            
            .modal-content {
                width: 95%;
                margin: 2% auto;
            }
        }
    </style>
</body>
</html>