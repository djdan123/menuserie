<?php
require_once '../config/database.php';
require_once '../config/functions.php';

redirigerSiNonAdmin();

// Période par défaut : 30 derniers jours
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-d', strtotime('-30 days'));
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');

// Statistiques générales
$stats = [];

// Chiffre d'affaires total
$stmt = $pdo->prepare("SELECT SUM(total) as ca_total FROM commandes WHERE statut = 'livrée'");
$stmt->execute();
$stats['ca_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['ca_total'] ?? 0;

// CA de la période
$stmt = $pdo->prepare("SELECT SUM(total) as ca_periode FROM commandes 
                       WHERE statut = 'livrée' AND DATE(date_commande) BETWEEN ? AND ?");
$stmt->execute([$date_debut, $date_fin]);
$stats['ca_periode'] = $stmt->fetch(PDO::FETCH_ASSOC)['ca_periode'] ?? 0;

// Nombre de commandes
$stmt = $pdo->prepare("SELECT COUNT(*) as nb_commandes FROM commandes WHERE DATE(date_commande) BETWEEN ? AND ?");
$stmt->execute([$date_debut, $date_fin]);
$stats['nb_commandes'] = $stmt->fetch(PDO::FETCH_ASSOC)['nb_commandes'] ?? 0;

// Nombre de clients
$stmt = $pdo->query("SELECT COUNT(*) as nb_clients FROM utilisateurs WHERE role = 'client'");
$stats['nb_clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['nb_clients'] ?? 0;

// Panier moyen
$stmt = $pdo->prepare("SELECT AVG(total) as panier_moyen FROM commandes 
                       WHERE statut = 'livrée' AND DATE(date_commande) BETWEEN ? AND ?");
$stmt->execute([$date_debut, $date_fin]);
$stats['panier_moyen'] = $stmt->fetch(PDO::FETCH_ASSOC)['panier_moyen'] ?? 0;

// Meubles les plus vendus
$stmt = $pdo->prepare("SELECT m.nom, m.bois_type, SUM(dc.quantite) as total_vendu, 
                       SUM(dc.quantite * dc.prix) as ca_generé
                       FROM details_commandes dc
                       JOIN meubles m ON dc.id_meuble = m.id_meuble
                       JOIN commandes c ON dc.id_commande = c.id_commande
                       WHERE c.statut = 'livrée' AND DATE(c.date_commande) BETWEEN ? AND ?
                       GROUP BY m.id_meuble
                       ORDER BY total_vendu DESC
                       LIMIT 10");
$stmt->execute([$date_debut, $date_fin]);
$meubles_populaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Évolution du CA par jour
$stmt = $pdo->prepare("SELECT DATE(date_commande) as date, SUM(total) as ca_jour
                       FROM commandes 
                       WHERE statut = 'livrée' AND DATE(date_commande) BETWEEN ? AND ?
                       GROUP BY DATE(date_commande)
                       ORDER BY date");
$stmt->execute([$date_debut, $date_fin]);
$evolution_ca = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Répartition par catégorie
$stmt = $pdo->prepare("SELECT c.nom_categorie, COUNT(m.id_meuble) as nb_produits, 
                       SUM(dc.quantite) as total_vendu
                       FROM categories c
                       LEFT JOIN meubles m ON c.id_categorie = m.id_categorie
                       LEFT JOIN details_commandes dc ON m.id_meuble = dc.id_meuble
                       LEFT JOIN commandes co ON dc.id_commande = co.id_commande
                       WHERE co.statut = 'livrée' AND DATE(co.date_commande) BETWEEN ? AND ?
                       GROUP BY c.id_categorie
                       ORDER BY total_vendu DESC");
$stmt->execute([$date_debut, $date_fin]);
$repartition_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Commandes par statut
$stmt = $pdo->query("SELECT statut, COUNT(*) as nb FROM commandes GROUP BY statut ORDER BY statut");
$commandes_par_statut = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>Tableau de bord statistiques</h1>
                
                <!-- Filtre période -->
                <form method="GET" class="filtre-periode">
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
                            <button type="submit" class="btn btn-secondary">Actualiser</button>
                            <button type="button" class="btn btn-link" onclick="resetDates()">30 derniers jours</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Statistiques principales -->
            <div class="stats-grid">
                <div class="stat-card large">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3>Chiffre d'affaires total</h3>
                        <p class="stat-value"><?php echo formatPrix($stats['ca_total']); ?></p>
                        <small>Période : <?php echo formatPrix($stats['ca_periode']); ?></small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <h3>Commandes</h3>
                        <p class="stat-value"><?php echo $stats['nb_commandes']; ?></p>
                        <small>Période sélectionnée</small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <h3>Clients</h3>
                        <p class="stat-value"><?php echo $stats['nb_clients']; ?></p>
                        <small>Total inscrits</small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-info">
                        <h3>Panier moyen</h3>
                        <p class="stat-value"><?php echo formatPrix($stats['panier_moyen']); ?></p>
                        <small>Période sélectionnée</small>
                    </div>
                </div>
            </div>
            
            <!-- Graphiques -->
            <div class="dashboard-grid">
                <!-- Évolution du CA -->
                <div class="dashboard-card">
                    <h2>Évolution du chiffre d'affaires</h2>
                    <div class="chart-container">
                        <canvas id="chartCA"></canvas>
                    </div>
                </div>
                
                <!-- Répartition par catégorie -->
                <div class="dashboard-card">
                    <h2>Répartition par catégorie</h2>
                    <div class="chart-container">
                        <canvas id="chartCategories"></canvas>
                    </div>
                </div>
                
                <!-- Meubles les plus vendus -->
                <div class="dashboard-card">
                    <h2>Top 10 des meubles les plus vendus</h2>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Meuble</th>
                                    <th>Bois</th>
                                    <th>Quantité</th>
                                    <th>CA généré</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($meubles_populaires as $meuble): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($meuble['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($meuble['bois_type']); ?></td>
                                        <td><?php echo $meuble['total_vendu']; ?></td>
                                        <td><?php echo formatPrix($meuble['ca_generé']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Commandes par statut -->
                <div class="dashboard-card">
                    <h2>Commandes par statut</h2>
                    <div class="chart-container">
                        <canvas id="chartStatuts"></canvas>
                    </div>
                    
                    <div class="stats-details">
                        <?php foreach ($commandes_par_statut as $statut): ?>
                            <div class="statut-item">
                                <span class="statut-label"><?php echo $statut['statut']; ?> :</span>
                                <span class="statut-value"><?php echo $statut['nb']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Rapports -->
            <div class="rapports-section">
                <h2>Rapports détaillés</h2>
                
                <div class="rapports-grid">
                    <!-- Performance produits -->
                    <div class="rapport-card">
                        <h3>Performance des produits</h3>
                        <?php
                        $stmt = $pdo->query("SELECT m.nom, 
                                            (SELECT SUM(dc.quantite) FROM details_commandes dc 
                                             JOIN commandes c ON dc.id_commande = c.id_commande 
                                             WHERE dc.id_meuble = m.id_meuble AND c.statut = 'livrée') as ventes,
                                            m.prix_vente,
                                            m.quantite_stock
                                            FROM meubles m
                                            ORDER BY ventes DESC
                                            LIMIT 5");
                        $performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <table class="table-small">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Ventes</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performance as $prod): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prod['nom']); ?></td>
                                        <td><?php echo $prod['ventes'] ?? 0; ?></td>
                                        <td><?php echo $prod['quantite_stock']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Clients fidèles -->
                    <div class="rapport-card">
                        <h3>Top clients</h3>
                        <?php
                        $stmt = $pdo->query("SELECT u.nom, u.prenom, u.email, 
                                            COUNT(c.id_commande) as nb_commandes,
                                            SUM(c.total) as total_depense
                                            FROM utilisateurs u
                                            LEFT JOIN commandes c ON u.id_user = c.id_user
                                            WHERE u.role = 'client'
                                            GROUP BY u.id_user
                                            ORDER BY total_depense DESC
                                            LIMIT 5");
                        $top_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <table class="table-small">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Commandes</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_clients as $client): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></td>
                                        <td><?php echo $client['nb_commandes']; ?></td>
                                        <td><?php echo formatPrix($client['total_depense']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Alertes stock -->
                    <div class="rapport-card alert">
                        <h3>Alertes stock</h3>
                        <?php
                        $stmt = $pdo->query("SELECT nom, quantite_stock, prix_vente 
                                            FROM meubles 
                                            WHERE quantite_stock < 5 
                                            ORDER BY quantite_stock ASC
                                            LIMIT 5");
                        $alertes_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <?php if (!empty($alertes_stock)): ?>
                            <ul class="alertes-list">
                                <?php foreach ($alertes_stock as $alerte): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($alerte['nom']); ?></strong>
                                        <span class="stock-alerte">Stock : <?php echo $alerte['quantite_stock']; ?></span>
                                        <a href="../produits/modifier.php?id=<?php echo $alerte['id_meuble'] ?? ''; ?>" 
                                           class="btn-alerte">Réappro</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-alert">✅ Tous les stocks sont corrects</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Export -->
            <div class="export-section">
                <h2>Export des données</h2>
                <div class="export-options">
                    <a href="export.php?type=commandes&debut=<?php echo $date_debut; ?>&fin=<?php echo $date_fin; ?>" 
                       class="btn btn-secondary">📊 Exporter commandes (CSV)</a>
                    <a href="export.php?type=produits" class="btn btn-secondary">📦 Exporter produits (CSV)</a>
                    <a href="export.php?type=clients" class="btn btn-secondary">👥 Exporter clients (CSV)</a>
                    <button onclick="imprimerRapport()" class="btn btn-primary">🖨️ Imprimer le rapport</button>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Réinitialiser les dates
        function resetDates() {
            const aujourdhui = new Date().toISOString().split('T')[0];
            const ilY30Jours = new Date();
            ilY30Jours.setDate(ilY30Jours.getDate() - 30);
            const dateIlY30Jours = ilY30Jours.toISOString().split('T')[0];
            
            document.getElementById('date_debut').value = dateIlY30Jours;
            document.getElementById('date_fin').value = aujourdhui;
            document.querySelector('.filtre-periode').submit();
        }
        
        // Graphique : Évolution du CA
        const ctxCA = document.getElementById('chartCA').getContext('2d');
        const dates = <?php echo json_encode(array_column($evolution_ca, 'date')); ?>;
        const caData = <?php echo json_encode(array_column($evolution_ca, 'ca_jour')); ?>;
        
        new Chart(ctxCA, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Chiffre d\'affaires (€)',
                    data: caData,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Graphique : Répartition par catégorie
        const ctxCategories = document.getElementById('chartCategories').getContext('2d');
        const categories = <?php echo json_encode(array_column($repartition_categories, 'nom_categorie')); ?>;
        const ventesCategories = <?php echo json_encode(array_column($repartition_categories, 'total_vendu')); ?>;
        
        new Chart(ctxCategories, {
            type: 'doughnut',
            data: {
                labels: categories,
                datasets: [{
                    data: ventesCategories,
                    backgroundColor: [
                        '#3498db', '#2ecc71', '#e74c3c', '#f39c12', 
                        '#9b59b6', '#1abc9c', '#d35400', '#c0392b'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });
        
        // Graphique : Commandes par statut
        const ctxStatuts = document.getElementById('chartStatuts').getContext('2d');
        const statuts = <?php echo json_encode(array_column($commandes_par_statut, 'statut')); ?>;
        const nbStatuts = <?php echo json_encode(array_column($commandes_par_statut, 'nb')); ?>;
        
        new Chart(ctxStatuts, {
            type: 'bar',
            data: {
                labels: statuts,
                datasets: [{
                    label: 'Nombre de commandes',
                    data: nbStatuts,
                    backgroundColor: [
                        '#f39c12', // en attente
                        '#3498db', // confirmée
                        '#9b59b6', // en fabrication
                        '#2ecc71', // livrée
                        '#e74c3c'  // annulée
                    ]
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Impression du rapport
        function imprimerRapport() {
            const printContent = `
                <html>
                    <head>
                        <title>Rapport statistiques - <?php echo date('d/m/Y'); ?></title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            h1 { color: #2c3e50; }
                            .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 20px 0; }
                            .stat-card { border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
                            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                            th { background-color: #f8f9fa; }
                        </style>
                    </head>
                    <body>
                        <h1>Rapport statistiques</h1>
                        <p>Période : <?php echo date('d/m/Y', strtotime($date_debut)); ?> au <?php echo date('d/m/Y', strtotime($date_fin)); ?></p>
                        
                        <div class="stats">
                            <div class="stat-card">
                                <h3>CA total période</h3>
                                <p><?php echo formatPrix($stats['ca_periode']); ?></p>
                            </div>
                            <div class="stat-card">
                                <h3>Nombre de commandes</h3>
                                <p><?php echo $stats['nb_commandes']; ?></p>
                            </div>
                            <div class="stat-card">
                                <h3>Panier moyen</h3>
                                <p><?php echo formatPrix($stats['panier_moyen']); ?></p>
                            </div>
                            <div class="stat-card">
                                <h3>Nombre de clients</h3>
                                <p><?php echo $stats['nb_clients']; ?></p>
                            </div>
                        </div>
                        
                        <h2>Top 10 des meubles les plus vendus</h2>
                        <table>
                            <tr>
                                <th>Meuble</th>
                                <th>Bois</th>
                                <th>Quantité vendue</th>
                                <th>CA généré</th>
                            </tr>
                            <?php foreach ($meubles_populaires as $meuble): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($meuble['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($meuble['bois_type']); ?></td>
                                    <td><?php echo $meuble['total_vendu']; ?></td>
                                    <td><?php echo formatPrix($meuble['ca_generé']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                        
                        <p style="margin-top: 40px; text-align: center; font-size: 0.9em; color: #666;">
                            Généré le <?php echo date('d/m/Y à H:i'); ?> - Menuiserie Bois Noble
                        </p>
                    </body>
                </html>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        }
    </script>
    
    <style>
        /* Styles supplémentaires pour les statistiques */
        .filtre-periode {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .filtre-periode .form-row {
            display: flex;
            align-items: end;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .stat-card.large .stat-value {
            font-size: 2.2rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        
        .stats-details {
            margin-top: 1rem;
        }
        
        .statut-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .statut-item:last-child {
            border-bottom: none;
        }
        
        .rapports-section {
            margin: 2rem 0;
        }
        
        .rapports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .rapport-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .rapport-card.alert {
            border-left: 4px solid #e74c3c;
        }
        
        .table-small {
            width: 100%;
            font-size: 0.9rem;
        }
        
        .table-small th,
        .table-small td {
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .alertes-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .alertes-list li {
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .alertes-list li:last-child {
            border-bottom: none;
        }
        
        .stock-alerte {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .btn-alerte {
            background: #e74c3c;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .no-alert {
            color: #27ae60;
            text-align: center;
            padding: 1rem;
        }
        
        .export-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .export-options {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .filtre-periode .form-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .rapports-grid {
                grid-template-columns: 1fr;
            }
            
            .export-options {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>