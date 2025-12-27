<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

redirigerSiNonAdmin();

// Filtres
$statut = isset($_GET['statut']) ? securiser($_GET['statut']) : 'non_lu';
$type = isset($_GET['type']) ? securiser($_GET['type']) : '';
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

// Construire la requête pour les messages
$sql = "SELECT m.*, 
        u.nom as client_nom, u.prenom as client_prenom, u.email as client_email,
        a.nom as admin_nom, a.prenom as admin_prenom
        FROM messages m
        LEFT JOIN utilisateurs u ON m.id_client = u.id_user
        LEFT JOIN utilisateurs a ON m.id_admin = a.id_user
        WHERE 1=1";
$params = [];

if (!empty($statut) && $statut != 'tous') {
    $sql .= " AND m.lu = ?";
    $params[] = ($statut == 'lu') ? 1 : 0;
}

if (!empty($type)) {
    $sql .= " AND m.type = ?";
    $params[] = $type;
}

if (!empty($date_debut)) {
    $sql .= " AND DATE(m.date_envoi) >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $sql .= " AND DATE(m.date_envoi) <= ?";
    $params[] = $date_fin;
}

$sql .= " ORDER BY m.lu ASC, m.date_envoi DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN lu = 0 THEN 1 ELSE 0 END) as non_lus,
        SUM(CASE WHEN type = 'question' THEN 1 ELSE 0 END) as questions,
        SUM(CASE WHEN type = 'reclamation' THEN 1 ELSE 0 END) as reclamations,
        SUM(CASE WHEN repondu = 1 THEN 1 ELSE 0 END) as repondues
    FROM messages
")->fetch(PDO::FETCH_ASSOC);

// Types de messages
$types_messages = $pdo->query("SELECT DISTINCT type FROM messages WHERE type IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie interne - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include '../../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../../includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>✉️ Messagerie interne</h1>
                <button class="btn btn-success" onclick="nouveauMessage()">+ Nouveau message</button>
            </div>
            
            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📨</div>
                    <div class="stat-info">
                        <h3>Total messages</h3>
                        <p class="stat-value"><?php echo $stats['total']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-info">
                        <h3>Non lus</h3>
                        <p class="stat-value"><?php echo $stats['non_lus']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">❓</div>
                    <div class="stat-info">
                        <h3>Questions</h3>
                        <p class="stat-value"><?php echo $stats['questions']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <h3>Répondues</h3>
                        <p class="stat-value"><?php echo $stats['repondues']; ?></p>
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
                                <option value="tous" <?php echo ($statut == 'tous') ? 'selected' : ''; ?>>Tous</option>
                                <option value="non_lu" <?php echo ($statut == 'non_lu') ? 'selected' : ''; ?>>Non lus</option>
                                <option value="lu" <?php echo ($statut == 'lu') ? 'selected' : ''; ?>>Lus</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="type">Type :</label>
                            <select id="type" name="type">
                                <option value="">Tous les types</option>
                                <?php foreach ($types_messages as $type_msg): ?>
                                    <option value="<?php echo htmlspecialchars($type_msg); ?>" 
                                            <?php echo ($type == $type_msg) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type_msg); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_debut">Date début :</label>
                            <input type="date" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_fin">Date fin :</label>
                            <input type="date" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-secondary">Filtrer</button>
                            <a href="messages.php" class="btn btn-link">Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Liste des messages -->
            <div class="messages-container">
                <div class="messages-list">
                    <?php if (empty($messages)): ?>
                        <div class="no-messages">
                            <p>Aucun message trouvé</p>
                            <button class="btn btn-primary" onclick="nouveauMessage()">Écrire un message</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message-card <?php echo ($message['lu'] == 0) ? 'non-lu' : ''; ?>" 
                                 onclick="voirMessage(<?php echo $message['id_message']; ?>)">
                                <div class="message-header">
                                    <div class="message-infos">
                                        <div class="message-expediteur">
                                            <strong>
                                                <?php if ($message['id_client']): ?>
                                                    <?php echo htmlspecialchars($message['client_prenom'] . ' ' . $message['client_nom']); ?>
                                                <?php else: ?>
                                                    <em>Client anonyme</em>
                                                <?php endif; ?>
                                            </strong>
                                            <span class="message-email"><?php echo htmlspecialchars($message['client_email']); ?></span>
                                        </div>
                                        <div class="message-date">
                                            <?php echo date('d/m/Y H:i', strtotime($message['date_envoi'])); ?>
                                        </div>
                                    </div>
                                    <div class="message-actions">
                                        <?php if ($message['lu'] == 0): ?>
                                            <span class="badge badge-warning">Nouveau</span>
                                        <?php endif; ?>
                                        <?php if ($message['type']): ?>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($message['type']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($message['repondu'] == 1): ?>
                                            <span class="badge badge-success">Répondu</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="message-preview">
                                    <h4><?php echo htmlspecialchars($message['sujet']); ?></h4>
                                    <p><?php echo nl2br(htmlspecialchars(substr($message['contenu'], 0, 150) . '...')); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Panneau de lecture -->
                <div class="message-viewer" id="messageViewer">
                    <div class="viewer-placeholder">
                        <p>👈 Sélectionnez un message pour le lire</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal Nouveau message -->
    <div id="modalNouveauMessage" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nouveau message</h2>
                <span class="close-modal" onclick="fermerModal('modalNouveauMessage')">&times;</span>
            </div>
            
            <div class="modal-body">
                <form id="formNouveauMessage" method="POST" action="actions/envoyer.php">
                    <div class="form-group">
                        <label for="destinataire">Destinataire *</label>
                        <select id="destinataire" name="id_client" required>
                            <option value="">Sélectionner un client</option>
                            <?php
                            $clients = $pdo->query("SELECT id_user, nom, prenom, email FROM utilisateurs WHERE role = 'client' ORDER BY nom, prenom")
                                          ->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($clients as $client):
                            ?>
                                <option value="<?php echo $client['id_user']; ?>">
                                    <?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?> 
                                    (<?php echo htmlspecialchars($client['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="sujet">Sujet *</label>
                        <input type="text" id="sujet" name="sujet" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="type_message">Type de message</label>
                        <select id="type_message" name="type">
                            <option value="information">Information</option>
                            <option value="promotion">Promotion</option>
                            <option value="question">Question</option>
                            <option value="reclamation">Réclamation</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="contenu_message">Message *</label>
                        <textarea id="contenu_message" name="contenu" rows="8" required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Envoyer</button>
                        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalNouveauMessage')">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Fonctions
        function fermerModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function nouveauMessage() {
            document.getElementById('modalNouveauMessage').style.display = 'block';
        }
        
        function voirMessage(messageId) {
            // Marquer comme lu
            fetch(`actions/marquer_lu.php?id=${messageId}`)
                .then(response => response.json());
            
            // Charger le message
            fetch(`actions/get_message.php?id=${messageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const message = data.message;
                        const reponses = data.reponses || [];
                        
                        let html = `
                            <div class="message-detail">
                                <div class="message-detail-header">
                                    <button class="btn btn-back" onclick="retourListe()">← Retour</button>
                                    <div class="message-actions-top">
                                        <button class="btn btn-sm" onclick="repondreMessage(${message.id_message})">↩️ Répondre</button>
                                        <button class="btn btn-sm" onclick="transfererMessage(${message.id_message})">↪️ Transférer</button>
                                        <button class="btn btn-sm btn-danger" onclick="supprimerMessage(${message.id_message})">🗑️ Supprimer</button>
                                    </div>
                                </div>
                                
                                <div class="message-content">
                                    <div class="message-metadata">
                                        <div class="metadata-item">
                                            <strong>De :</strong> 
                                            ${message.client_prenom ? message.client_prenom + ' ' + message.client_nom : 'Client anonyme'}
                                        </div>
                                        <div class="metadata-item">
                                            <strong>Email :</strong> ${message.client_email || 'Non spécifié'}
                                        </div>
                                        <div class="metadata-item">
                                            <strong>Date :</strong> ${new Date(message.date_envoi).toLocaleString('fr-FR')}
                                        </div>
                                        ${message.type ? `<div class="metadata-item"><strong>Type :</strong> ${message.type}</div>` : ''}
                                    </div>
                                    
                                    <h2>${message.sujet}</h2>
                                    
                                    <div class="message-body">
                                        ${nl2br(message.contenu)}
                                    </div>
                                </div>
                                
                                <!-- Réponses -->
                                ${reponses.length > 0 ? `
                                    <div class="reponses">
                                        <h3>📨 Réponses</h3>
                                        ${reponses.map(reponse => `
                                            <div class="reponse-card ${reponse.id_admin ? 'admin' : 'client'}">
                                                <div class="reponse-header">
                                                    <strong>${reponse.admin_prenom ? reponse.admin_prenom + ' ' + reponse.admin_nom : (reponse.client_prenom ? reponse.client_prenom + ' ' + reponse.client_nom : 'Client')}</strong>
                                                    <span class="reponse-date">${new Date(reponse.date_envoi).toLocaleString('fr-FR')}</span>
                                                </div>
                                                <div class="reponse-body">
                                                    ${nl2br(reponse.contenu)}
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : ''}
                                
                                <!-- Formulaire de réponse -->
                                <div class="form-reponse">
                                    <h3>✍️ Répondre</h3>
                                    <form id="formReponse" onsubmit="envoyerReponse(event, ${message.id_message})">
                                        <div class="form-group">
                                            <textarea id="reponse_contenu" rows="4" placeholder="Votre réponse..." required></textarea>
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" class="btn btn-success">Envoyer la réponse</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('messageViewer').innerHTML = html;
                        
                        // Marquer visuellement comme lu
                        const card = document.querySelector(`[onclick="voirMessage(${messageId})"]`);
                        if (card) {
                            card.classList.remove('non-lu');
                        }
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors du chargement du message');
                });
        }
        
        function retourListe() {
            document.getElementById('messageViewer').innerHTML = `
                <div class="viewer-placeholder">
                    <p>👈 Sélectionnez un message pour le lire</p>
                </div>
            `;
        }
        
        function repondreMessage(messageId) {
            // Pré-remplir le formulaire de réponse
            const textarea = document.getElementById('reponse_contenu');
            if (textarea) {
                textarea.focus();
            } else {
                // Recharger le message pour afficher le formulaire
                voirMessage(messageId);
            }
        }
        
        function envoyerReponse(e, messageId) {
            e.preventDefault();
            
            const contenu = document.getElementById('reponse_contenu').value.trim();
            
            if (!contenu) {
                alert('Veuillez saisir une réponse');
                return;
            }
            
            fetch('actions/repondre.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id_message=${messageId}&contenu=${encodeURIComponent(contenu)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Réponse envoyée avec succès');
                    // Recharger le message pour voir la réponse
                    voirMessage(messageId);
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de l\'envoi de la réponse');
            });
        }
        
        function transfererMessage(messageId) {
            const destinataire = prompt('Email du destinataire pour le transfert :');
            if (destinataire) {
                fetch('actions/transferer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id_message=${messageId}&destinataire=${encodeURIComponent(destinataire)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Message transféré avec succès');
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        function supprimerMessage(messageId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce message ?')) {
                fetch(`actions/supprimer_message.php?id=${messageId}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Message supprimé avec succès');
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        function nl2br(text) {
            return text.replace(/\n/g, '<br>');
        }
        
        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                fermerModal(event.target.id);
            }
        };
        
        // Validation du formulaire
        document.getElementById('formNouveauMessage').addEventListener('submit', function(e) {
            const sujet = document.getElementById('sujet').value.trim();
            const contenu = document.getElementById('contenu_message').value.trim();
            
            if (!sujet || !contenu) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires');
            }
        });
    </script>
    
    <style>
        .messages-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
            height: 70vh;
        }
        
        @media (max-width: 1024px) {
            .messages-container {
                grid-template-columns: 1fr;
            }
        }
        
        .messages-list {
            background: white;
            border-radius: 8px;
            overflow-y: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .message-card {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .message-card:hover {
            background-color: #f8f9fa;
        }
        
        .message-card.non-lu {
            background-color: #e8f4fc;
            border-left: 4px solid #3498db;
        }
        
        .message-card.non-lu:hover {
            background-color: #d1ecf1;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .message-infos {
            flex: 1;
        }
        
        .message-expediteur {
            margin-bottom: 0.25rem;
        }
        
        .message-email {
            display: block;
            font-size: 0.8rem;
            color: #666;
        }
        
        .message-date {
            font-size: 0.8rem;
            color: #999;
        }
        
        .message-actions {
            display: flex;
            gap: 0.25rem;
        }
        
        .badge {
            padding: 0.1rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .badge-warning {
            background: #f39c12;
            color: white;
        }
        
        .badge-info {
            background: #3498db;
            color: white;
        }
        
        .badge-success {
            background: #27ae60;
            color: white;
        }
        
        .message-preview h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            color: #2c3e50;
        }
        
        .message-preview p {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }
        
        .message-viewer {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .viewer-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
            font-size: 1.1rem;
        }
        
        .no-messages {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .message-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #eee;
        }
        
        .btn-back {
            background: none;
            border: none;
            color: #3498db;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .message-actions-top {
            display: flex;
            gap: 0.5rem;
        }
        
        .message-metadata {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .metadata-item {
            font-size: 0.9rem;
        }
        
        .metadata-item strong {
            color: #666;
        }
        
        .message-detail h2 {
            margin: 0 0 1rem 0;
            color: #2c3e50;
        }
        
        .message-body {
            line-height: 1.6;
            font-size: 1.1rem;
            padding: 1.5rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        
        .reponses {
            margin-top: 2rem;
        }
        
        .reponses h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .reponse-card {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .reponse-card.admin {
            background: #e8f4fc;
            border-left-color: #3498db;
        }
        
        .reponse-card.client {
            background: #f8f9fa;
            border-left-color: #95a5a6;
        }
        
        .reponse-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .reponse-date {
            font-size: 0.8rem;
            color: #666;
        }
        
        .reponse-body {
            line-height: 1.4;
        }
        
        .form-reponse {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid #eee;
        }
        
        .form-reponse h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .form-reponse textarea {
            width: 100%;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            resize: vertical;
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
            .messages-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .message-viewer {
                min-height: 400px;
            }
            
            .message-detail-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .message-actions-top {
                width: 100%;
                justify-content: flex-end;
            }
            
            .modal-content {
                width: 95%;
                margin: 2% auto;
            }
        }
    </style>
</body>
</html>