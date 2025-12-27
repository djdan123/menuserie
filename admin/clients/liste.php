<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

redirigerSiNonAdmin();

// Filtres
$recherche = isset($_GET['recherche']) ? securiser($_GET['recherche']) : '';
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$tri = isset($_GET['tri']) ? securiser($_GET['tri']) : 'date_inscription_desc';

// Construire la requête
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM commandes c WHERE c.id_user = u.id_user) as nb_commandes,
        (SELECT SUM(total) FROM commandes c WHERE c.id_user = u.id_user AND c.statut = 'livrée') as total_achats,
        (SELECT MAX(date_commande) FROM commandes c WHERE c.id_user = u.id_user) as derniere_commande
        FROM utilisateurs u 
        WHERE u.role = 'client'";
$params = [];

if (!empty($recherche)) {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$recherche%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
}

if (!empty($date_debut)) {
    $sql .= " AND DATE(u.date_inscription) >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $sql .= " AND DATE(u.date_inscription) <= ?";
    $params[] = $date_fin;
}

// Ordre de tri
switch ($tri) {
    case 'nom_asc':
        $sql .= " ORDER BY u.nom ASC, u.prenom ASC";
        break;
    case 'nom_desc':
        $sql .= " ORDER BY u.nom DESC, u.prenom DESC";
        break;
    case 'achats_desc':
        $sql .= " ORDER BY total_achats DESC";
        break;
    case 'commandes_desc':
        $sql .= " ORDER BY nb_commandes DESC";
        break;
    case 'date_inscription_asc':
        $sql .= " ORDER BY u.date_inscription ASC";
        break;
    case 'date_inscription_desc':
    default:
        $sql .= " ORDER BY u.date_inscription DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques clients
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_clients,
        AVG((SELECT COUNT(*) FROM commandes c WHERE c.id_user = u.id_user)) as moy_commandes,
        AVG((SELECT SUM(total) FROM commandes c WHERE c.id_user = u.id_user AND c.statut = 'livrée')) as moy_achats,
        SUM((SELECT SUM(total) FROM commandes c WHERE c.id_user = u.id_user AND c.statut = 'livrée')) as ca_total
    FROM utilisateurs u 
    WHERE u.role = 'client'
")->fetch(PDO::FETCH_ASSOC);

// Récupérer les 10 meilleurs clients
$top_clients = $pdo->query("
    SELECT u.nom, u.prenom, u.email,
           COUNT(c.id_commande) as nb_commandes,
           SUM(c.total) as total_achats
    FROM utilisateurs u
    LEFT JOIN commandes c ON u.id_user = c.id_user AND c.statut = 'livrée'
    WHERE u.role = 'client'
    GROUP BY u.id_user
    ORDER BY total_achats DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des clients - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include '../../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../../includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>👥 Gestion des clients</h1>
                <button class="btn btn-success" onclick="exporterClients()">📊 Exporter</button>
            </div>
            
            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <h3>Total clients</h3>
                        <p class="stat-value"><?php echo $stats['total_clients']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <h3>Commandes moy.</h3>
                        <p class="stat-value"><?php echo round($stats['moy_commandes'], 1); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3>Panier moyen</h3>
                        <p class="stat-value"><?php echo formatPrix($stats['moy_achats']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-info">
                        <h3>CA total</h3>
                        <p class="stat-value"><?php echo formatPrix($stats['ca_total']); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Top clients -->
            <div class="dashboard-card">
                <h3>🏆 Top 10 clients</h3>
                <div class="top-clients">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Email</th>
                                <th>Commandes</th>
                                <th>Total achats</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_clients as $client): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                                    <td><?php echo $client['nb_commandes']; ?></td>
                                    <td><?php echo formatPrix($client['total_achats']); ?></td>
                                    <td>
                                        <button class="btn-action view" 
                                                onclick="voirClient('<?php echo $client['email']; ?>')"
                                                title="Voir">👁️</button>
                                        <button class="btn-action message" 
                                                onclick="envoyerMessage('<?php echo $client['email']; ?>')"
                                                title="Message">✉️</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Filtres -->
            <div class="filtres-card">
                <h3>Filtres et recherche</h3>
                <form method="GET" class="filtre-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="recherche">Recherche :</label>
                            <input type="text" id="recherche" name="recherche" 
                                   placeholder="Nom, prénom, email..." 
                                   value="<?php echo htmlspecialchars($recherche); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_debut">Inscrit après :</label>
                            <input type="date" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_fin">Inscrit avant :</label>
                            <input type="date" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="tri">Trier par :</label>
                            <select id="tri" name="tri">
                                <option value="date_inscription_desc" <?php echo ($tri == 'date_inscription_desc') ? 'selected' : ''; ?>>Date inscription (récent)</option>
                                <option value="date_inscription_asc" <?php echo ($tri == 'date_inscription_asc') ? 'selected' : ''; ?>>Date inscription (ancien)</option>
                                <option value="nom_asc" <?php echo ($tri == 'nom_asc') ? 'selected' : ''; ?>>Nom (A-Z)</option>
                                <option value="nom_desc" <?php echo ($tri == 'nom_desc') ? 'selected' : ''; ?>>Nom (Z-A)</option>
                                <option value="achats_desc" <?php echo ($tri == 'achats_desc') ? 'selected' : ''; ?>>Total achats (desc)</option>
                                <option value="commandes_desc" <?php echo ($tri == 'commandes_desc') ? 'selected' : ''; ?>>Nombre commandes (desc)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-secondary">Filtrer</button>
                            <a href="liste.php" class="btn btn-link">Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Liste des clients -->
            <div class="table-card">
                <div class="table-header">
                    <h3>Liste des clients (<?php echo count($clients); ?>)</h3>
                    <div class="actions">
                        <button class="btn btn-primary" onclick="nouveauClient()">+ Nouveau client</button>
                        <button class="btn btn-secondary" onclick="envoyerNewsletter()">📧 Newsletter</button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table clients-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Contact</th>
                                <th>Inscription</th>
                                <th>Statistiques</th>
                                <th>Dernière commande</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <?php
                                // Calculer la valeur client
                                $valeur = $client['total_achats'] ?? 0;
                                $valeur_class = '';
                                if ($valeur > 5000) {
                                    $valeur_class = 'valeur-haute';
                                } elseif ($valeur > 1000) {
                                    $valeur_class = 'valeur-moyenne';
                                }
                                
                                // Dernière activité
                                $derniere_activite = 'Jamais';
                                if ($client['derniere_commande']) {
                                    $derniere_date = new DateTime($client['derniere_commande']);
                                    $aujourdhui = new DateTime();
                                    $difference = $aujourdhui->diff($derniere_date);
                                    
                                    if ($difference->y > 0) {
                                        $derniere_activite = $difference->y . ' an(s)';
                                    } elseif ($difference->m > 0) {
                                        $derniere_activite = $difference->m . ' mois';
                                    } elseif ($difference->d > 0) {
                                        $derniere_activite = $difference->d . ' jour(s)';
                                    } else {
                                        $derniere_activite = "Aujourd'hui";
                                    }
                                }
                                ?>
                                
                                <tr class="<?php echo $valeur_class; ?>">
                                    <td>#<?php echo str_pad($client['id_user'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                        <div class="client-info">
                                            <strong><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></strong>
                                            <div class="client-tags">
                                                <?php if ($client['nb_commandes'] > 10): ?>
                                                    <span class="tag fidèle">👑 Fidèle</span>
                                                <?php elseif ($client['nb_commandes'] > 5): ?>
                                                    <span class="tag régulier">⭐ Régulier</span>
                                                <?php endif; ?>
                                                
                                                <?php if (strtotime($client['date_inscription']) > strtotime('-30 days')): ?>
                                                    <span class="tag nouveau">🆕 Nouveau</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-info">
                                            <div>📧 <?php echo htmlspecialchars($client['email']); ?></div>
                                            <small>Inscrit le <?php echo date('d/m/Y', strtotime($client['date_inscription'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($client['date_inscription'])); ?><br>
                                        <small><?php echo date('H:i', strtotime($client['date_inscription'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="client-stats">
                                            <div class="stat-item">
                                                <span class="stat-label">Commandes:</span>
                                                <span class="stat-value"><?php echo $client['nb_commandes']; ?></span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-label">Total:</span>
                                                <span class="stat-value"><?php echo formatPrix($client['total_achats']); ?></span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-label">Panier moy:</span>
                                                <span class="stat-value">
                                                    <?php 
                                                    $panier_moy = ($client['nb_commandes'] > 0) ? 
                                                        $client['total_achats'] / $client['nb_commandes'] : 0;
                                                    echo formatPrix($panier_moy);
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($client['derniere_commande']): ?>
                                            <?php echo date('d/m/Y', strtotime($client['derniere_commande'])); ?><br>
                                            <small>Il y a <?php echo $derniere_activite; ?></small>
                                        <?php else: ?>
                                            <em>Aucune commande</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action view" 
                                                    onclick="voirDetails(<?php echo $client['id_user']; ?>)"
                                                    title="Détails">👁️</button>
                                            <button class="btn-action edit" 
                                                    onclick="modifierClient(<?php echo $client['id_user']; ?>)"
                                                    title="Modifier">✏️</button>
                                            <button class="btn-action message" 
                                                    onclick="envoyerMessageClient(<?php echo $client['id_user']; ?>)"
                                                    title="Message">✉️</button>
                                            <button class="btn-action history" 
                                                    onclick="voirHistorique(<?php echo $client['id_user']; ?>)"
                                                    title="Historique">📋</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($clients)): ?>
                        <div class="no-results">
                            <p>Aucun client trouvé</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <span>Page 1 sur 1</span>
                    <div class="pagination-controls">
                        <button class="btn btn-sm" disabled>← Précédent</button>
                        <button class="btn btn-sm active">1</button>
                        <button class="btn btn-sm" disabled>Suivant →</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal Détails client -->
    <div id="modalDetails" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Détails du client</h2>
                <span class="close-modal" onclick="fermerModal('modalDetails')">&times;</span>
            </div>
            
            <div class="modal-body">
                <div id="detailsContent">
                    <!-- Contenu chargé via AJAX -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Message -->
    <div id="modalMessage" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Envoyer un message</h2>
                <span class="close-modal" onclick="fermerModal('modalMessage')">&times;</span>
            </div>
            
            <div class="modal-body">
                <form id="formMessage" method="POST" action="actions/envoyer_message.php">
                    <input type="hidden" id="message_client_id" name="client_id">
                    
                    <div class="form-group">
                        <label for="message_sujet">Sujet *</label>
                        <input type="text" id="message_sujet" name="sujet" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message_contenu">Message *</label>
                        <textarea id="message_contenu" name="contenu" rows="6" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="message_type">Type de message</label>
                        <select id="message_type" name="type">
                            <option value="information">Information</option>
                            <option value="promotion">Promotion</option>
                            <option value="suivi">Suivi de commande</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" id="message_copie" name="copie" checked>
                        <label for="message_copie">M'envoyer une copie</label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Envoyer</button>
                        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalMessage')">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Fonctions générales
        function fermerModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function exporterClients() {
            const recherche = document.getElementById('recherche').value;
            const dateDebut = document.getElementById('date_debut').value;
            const dateFin = document.getElementById('date_fin').value;
            const tri = document.getElementById('tri').value;
            
            window.open(`export.php?recherche=${encodeURIComponent(recherche)}&date_debut=${dateDebut}&date_fin=${dateFin}&tri=${tri}`, '_blank');
        }
        
        function nouveauClient() {
            alert('Fonctionnalité à implémenter : Création de client manuelle');
        }
        
        function envoyerNewsletter() {
            window.open('newsletter.php', '_blank');
        }
        
        // Fonctions clients
        function voirDetails(clientId) {
            fetch(`actions/get_client.php?id=${clientId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const client = data.client;
                        const commandes = data.commandes || [];
                        
                        let html = `
                            <div class="client-details">
                                <div class="details-header">
                                    <div class="client-photo">
                                        <div class="avatar">${client.prenom.charAt(0)}${client.nom.charAt(0)}</div>
                                    </div>
                                    <div class="client-infos">
                                        <h3>${client.prenom} ${client.nom}</h3>
                                        <p>📧 ${client.email}</p>
                                        <p>Inscrit le ${new Date(client.date_inscription).toLocaleDateString('fr-FR')}</p>
                                    </div>
                                    <div class="client-stats-summary">
                                        <div class="stat-summary">
                                            <span class="stat-number">${client.nb_commandes || 0}</span>
                                            <span class="stat-label">Commandes</span>
                                        </div>
                                        <div class="stat-summary">
                                            <span class="stat-number">${formatPrice(client.total_achats || 0)}</span>
                                            <span class="stat-label">Total achats</span>
                                        </div>
                                        <div class="stat-summary">
                                            <span class="stat-number">${client.panier_moyen ? formatPrice(client.panier_moyen) : '0,00 €'}</span>
                                            <span class="stat-label">Panier moyen</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="details-tabs">
                                    <button class="tab-btn active" onclick="switchTab('commandes')">📋 Commandes</button>
                                    <button class="tab-btn" onclick="switchTab('messages')">✉️ Messages</button>
                                    <button class="tab-btn" onclick="switchTab('infos')">👤 Infos</button>
                                </div>
                                
                                <div id="tab-commandes" class="tab-content active">
                                    <h4>Historique des commandes</h4>
                                    ${commandes.length > 0 ? 
                                        `<table class="table">
                                            <thead>
                                                <tr>
                                                    <th>N°</th>
                                                    <th>Date</th>
                                                    <th>Total</th>
                                                    <th>Statut</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${commandes.map(cmd => `
                                                    <tr>
                                                        <td>#${cmd.id_commande}</td>
                                                        <td>${new Date(cmd.date_commande).toLocaleDateString('fr-FR')}</td>
                                                        <td>${formatPrice(cmd.total)}</td>
                                                        <td><span class="badge badge-${cmd.statut.replace(' ', '-')}">${cmd.statut}</span></td>
                                                        <td>
                                                            <button class="btn btn-sm" onclick="voirCommande(${cmd.id_commande})">Voir</button>
                                                        </td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>` 
                                        : '<p>Aucune commande</p>'}
                                </div>
                                
                                <div id="tab-messages" class="tab-content">
                                    <h4>Messages envoyés</h4>
                                    <p class="no-data">Aucun message envoyé</p>
                                </div>
                                
                                <div id="tab-infos" class="tab-content">
                                    <h4>Informations complémentaires</h4>
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <label>Client depuis :</label>
                                            <span>${Math.floor((new Date() - new Date(client.date_inscription)) / (1000 * 60 * 60 * 24 * 365))} an(s)</span>
                                        </div>
                                        <div class="info-item">
                                            <label>Dernière connexion :</label>
                                            <span>Non enregistrée</span>
                                        </div>
                                        <div class="info-item">
                                            <label>Type de client :</label>
                                            <span>
                                                ${client.nb_commandes > 10 ? '👑 Fidèle' : 
                                                  client.nb_commandes > 5 ? '⭐ Régulier' : 
                                                  '🆕 Nouveau'}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="actions">
                                        <button class="btn btn-primary" onclick="envoyerMessageClient(${clientId})">✉️ Envoyer message</button>
                                        <button class="btn btn-secondary" onclick="modifierClient(${clientId})">✏️ Modifier</button>
                                        <button class="btn btn-danger" onclick="supprimerClient(${clientId})">🗑️ Supprimer</button>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('detailsContent').innerHTML = html;
                        document.getElementById('modalDetails').style.display = 'block';
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors du chargement des données');
                });
        }
        
        function switchTab(tabName) {
            // Désactiver tous les tabs
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Activer le tab sélectionné
            event.target.classList.add('active');
            document.getElementById(`tab-${tabName}`).classList.add('active');
        }
        
        function formatPrice(price) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR'
            }).format(price);
        }
        
        function modifierClient(clientId) {
            // Rediriger vers la page de modification
            window.location.href = `modifier.php?id=${clientId}`;
        }
        
        function envoyerMessageClient(clientId) {
            document.getElementById('message_client_id').value = clientId;
            
            // Pré-remplir avec les infos client
            fetch(`actions/get_client.php?id=${clientId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const client = data.client;
                        document.getElementById('message_sujet').value = `Message pour ${client.prenom} ${client.nom}`;
                    }
                });
            
            document.getElementById('modalMessage').style.display = 'block';
        }
        
        function voirHistorique(clientId) {
            window.open(`historique.php?id=${clientId}`, '_blank');
        }
        
        function voirCommande(commandeId) {
            window.open(`../../commandes/details.php?id=${commandeId}`, '_blank');
        }
        
        function voirClient(email) {
            // Rechercher le client par email
            const recherche = encodeURIComponent(email);
            window.location.href = `liste.php?recherche=${recherche}`;
        }
        
        function envoyerMessage(email) {
            // Pré-remplir le formulaire de message
            document.getElementById('message_sujet').value = `Message pour ${email}`;
            // Ici, on devrait trouver l'ID client par email
            alert('À implémenter : trouver ID client par email');
            document.getElementById('modalMessage').style.display = 'block';
        }
        
        function supprimerClient(clientId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce client ? Cette action est irréversible.')) {
                fetch(`actions/supprimer.php?id=${clientId}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Client supprimé avec succès');
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Validation du formulaire de message
        document.getElementById('formMessage').addEventListener('submit', function(e) {
            const sujet = document.getElementById('message_sujet').value.trim();
            const contenu = document.getElementById('message_contenu').value.trim();
            
            if (!sujet || !contenu) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires');
            }
        });
    </script>
    
    <style>
        .clients-table {
            font-size: 0.9rem;
        }
        
        .clients-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .client-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .client-tags {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }
        
        .tag {
            padding: 0.1rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .tag.fidèle {
            background: #ffd700;
            color: #000;
        }
        
        .tag.régulier {
            background: #3498db;
            color: white;
        }
        
        .tag.nouveau {
            background: #2ecc71;
            color: white;
        }
        
        .contact-info {
            line-height: 1.4;
        }
        
        .client-stats {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.8rem;
        }
        
        .stat-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .valeur-haute {
            background-color: #e8f6f3 !important;
        }
        
        .valeur-haute:hover {
            background-color: #d1f2eb !important;
        }
        
        .valeur-moyenne {
            background-color: #fef9e7 !important;
        }
        
        .valeur-moyenne:hover {
            background-color: #fcf3cf !important;
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
        
        .btn-action.view:hover {
            background: #3498db;
            color: white;
        }
        
        .btn-action.edit:hover {
            background: #f39c12;
            color: white;
        }
        
        .btn-action.message:hover {
            background: #9b59b6;
            color: white;
        }
        
        .btn-action.history:hover {
            background: #2ecc71;
            color: white;
        }
        
        .top-clients {
            margin-top: 1rem;
        }
        
        .top-clients .table {
            font-size: 0.9rem;
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-top: 1px solid #eee;
            background: #f8f9fa;
            border-radius: 0 0 8px 8px;
        }
        
        .pagination-controls {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
        }
        
        /* Modal détails */
        .modal-large {
            max-width: 900px;
        }
        
        .client-details {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .details-header {
            display: flex;
            gap: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #eee;
            margin-bottom: 1.5rem;
        }
        
        .client-photo {
            flex-shrink: 0;
        }
        
        .avatar {
            width: 80px;
            height: 80px;
            background: #3498db;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .client-infos {
            flex: 1;
        }
        
        .client-infos h3 {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
        }
        
        .client-infos p {
            margin: 0.25rem 0;
            color: #666;
        }
        
        .client-stats-summary {
            display: flex;
            gap: 2rem;
        }
        
        .stat-summary {
            text-align: center;
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            min-width: 100px;
        }
        
        .stat-number {
            display: block;
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            display: block;
            font-size: 0.9rem;
            color: #666;
        }
        
        .details-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }
        
        .tab-btn {
            background: none;
            border: none;
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-radius: 4px;
            color: #666;
            transition: all 0.3s;
        }
        
        .tab-btn:hover {
            background: #f8f9fa;
        }
        
        .tab-btn.active {
            background: #3498db;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .tab-content h4 {
            margin: 0 0 1rem 0;
            color: #2c3e50;
        }
        
        .tab-content .table {
            font-size: 0.9rem;
        }
        
        .no-data {
            color: #999;
            font-style: italic;
            text-align: center;
            padding: 2rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .info-item {
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .info-item label {
            display: block;
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .info-item span {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        /* Modal message */
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }
        
        .form-check input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
        
        @media (max-width: 768px) {
            .details-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .client-stats-summary {
                justify-content: center;
            }
            
            .stat-summary {
                min-width: 80px;
                padding: 0.5rem;
            }
            
            .details-tabs {
                flex-wrap: wrap;
            }
            
            .tab-btn {
                flex: 1;
                min-width: 100px;
            }
            
            .modal-large {
                width: 95%;
                margin: 2% auto;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
            
            .client-tags {
                justify-content: center;
            }
        }
    </style>
</body>
</html>