<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

redirigerSiNonAdmin();

// Récupérer les clients abonnés à la newsletter
$clients = $pdo->query("SELECT * FROM utilisateurs WHERE role = 'client' ORDER BY date_inscription DESC")
               ->fetchAll(PDO::FETCH_ASSOC);

// Envoi de la newsletter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer_newsletter'])) {
    $sujet = securiser($_POST['sujet']);
    $contenu = securiser($_POST['contenu']);
    $clients_cibles = isset($_POST['clients']) ? array_map('intval', $_POST['clients']) : [];
    
    if (empty($clients_cibles)) {
        flashMessage("Aucun client sélectionné", "error");
    } elseif (empty($sujet) || empty($contenu)) {
        flashMessage("Veuillez remplir le sujet et le contenu", "error");
    } else {
        $placeholders = str_repeat('?,', count($clients_cibles) - 1) . '?';
        
        // Récupérer les emails des clients
        $stmt = $pdo->prepare("SELECT email FROM utilisateurs WHERE id_user IN ($placeholders)");
        $stmt->execute($clients_cibles);
        $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Simuler l'envoi
        $envoyes = 0;
        foreach ($emails as $email) {
            // Ici, vous intégreriez votre système d'envoi d'email
            // Ex: mail($email, $sujet, $contenu, "From: no-reply@menuiserie.com");
            $envoyes++;
            
            // Enregistrer dans l'historique
            $stmt = $pdo->prepare("INSERT INTO newsletter_log (email, sujet, date_envoi, statut) 
                                   VALUES (?, ?, NOW(), 'envoyé')");
            $stmt->execute([$email, $sujet]);
        }
        
        flashMessage("Newsletter envoyée à $envoyes clients", "success");
        header('Location: newsletter.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include '../../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../../includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>📧 Newsletter</h1>
            </div>
            
            <?php echo getFlashMessage(); ?>
            
            <div class="newsletter-container">
                <!-- Formulaire newsletter -->
                <div class="newsletter-form-section">
                    <h2>Créer une newsletter</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="sujet">Sujet *</label>
                            <input type="text" id="sujet" name="sujet" required placeholder="Sujet de la newsletter">
                        </div>
                        
                        <div class="form-group">
                            <label for="contenu">Contenu *</label>
                            <textarea id="contenu" name="contenu" rows="10" required placeholder="Contenu de la newsletter..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Destinataires *</label>
                            <div class="destinataires-list">
                                <?php foreach ($clients as $client): ?>
                                    <div class="destinataire-item">
                                        <input type="checkbox" id="client_<?php echo $client['id_user']; ?>" 
                                               name="clients[]" value="<?php echo $client['id_user']; ?>">
                                        <label for="client_<?php echo $client['id_user']; ?>">
                                            <?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?>
                                            <small><?php echo htmlspecialchars($client['email']); ?></small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="selection-actions">
                                <button type="button" onclick="selectionnerTous()">Tout sélectionner</button>
                                <button type="button" onclick="deselectionnerTous()">Tout désélectionner</button>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="envoyer_newsletter" class="btn btn-success">Envoyer la newsletter</button>
                            <button type="button" class="btn btn-secondary" onclick="previsualiser()">Prévisualiser</button>
                        </div>
                    </form>
                </div>
                
                <!-- Statistiques newsletter -->
                <div class="newsletter-stats">
                    <h2>📊 Statistiques</h2>
                    <?php
                    // Récupérer les stats
                    $stats = $pdo->query("
                        SELECT 
                            COUNT(*) as total_envoyes,
                            COUNT(DISTINCT email) as clients_uniques,
                            DATE_FORMAT(date_envoi, '%Y-%m') as mois,
                            COUNT(*) as par_mois
                        FROM newsletter_log 
                        WHERE date_envoi > DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        GROUP BY DATE_FORMAT(date_envoi, '%Y-%m')
                        ORDER BY mois DESC
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">📨</div>
                            <div class="stat-info">
                                <h3>Total envoyés</h3>
                                <p class="stat-value"><?php echo $stats[0]['total_envoyes'] ?? 0; ?></p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">👥</div>
                            <div class="stat-info">
                                <h3>Clients uniques</h3>
                                <p class="stat-value"><?php echo $stats[0]['clients_uniques'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <h3>Historique des envois</h3>
                    <div class="historique-envois">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Sujet</th>
                                    <th>Destinataires</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $historique = $pdo->query("
                                    SELECT sujet, COUNT(*) as nb, MAX(date_envoi) as derniere_date, statut
                                    FROM newsletter_log 
                                    GROUP BY sujet, DATE(date_envoi)
                                    ORDER BY derniere_date DESC
                                    LIMIT 10
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($historique as $envoi):
                                ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($envoi['derniere_date'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr($envoi['sujet'], 0, 50)); ?>...</td>
                                        <td><?php echo $envoi['nb']; ?></td>
                                        <td><span class="badge badge-success"><?php echo $envoi['statut']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function selectionnerTous() {
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = true;
            });
        }
        
        function deselectionnerTous() {
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        }
        
        function previsualiser() {
            const sujet = document.getElementById('sujet').value;
            const contenu = document.getElementById('contenu').value;
            
            if (!sujet || !contenu) {
                alert('Veuillez remplir le sujet et le contenu');
                return;
            }
            
            const preview = `
                <html>
                    <head>
                        <title>${sujet}</title>
                        <style>
                            body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                            .content { padding: 20px; line-height: 1.6; }
                            .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>Menuiserie Bois Noble</h1>
                            <p>${sujet}</p>
                        </div>
                        <div class="content">
                            ${contenu.replace(/\n/g, '<br>')}
                        </div>
                        <div class="footer">
                            <p>© <?php echo date('Y'); ?> Menuiserie Bois Noble</p>
                            <p><a href="#">Se désabonner</a></p>
                        </div>
                    </body>
                </html>
            `;
            
            const previewWindow = window.open('', '_blank');
            previewWindow.document.write(preview);
            previewWindow.document.close();
        }
        
        // Validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const sujets = document.querySelectorAll('input[type="checkbox"]:checked');
            if (sujets.length === 0) {
                e.preventDefault();
                alert('Veuillez sélectionner au moins un destinataire');
            }
        });
    </script>
    
    <style>
        .newsletter-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .newsletter-container {
                grid-template-columns: 1fr;
            }
        }
        
        .newsletter-form-section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .destinataires-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .destinataire-item {
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .destinataire-item:last-child {
            border-bottom: none;
        }
        
        .destinataire-item label {
            display: flex;
            flex-direction: column;
            cursor: pointer;
            margin-left: 0.5rem;
        }
        
        .destinataire-item small {
            color: #666;
            font-size: 0.9rem;
        }
        
        .selection-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .selection-actions button {
            background: none;
            border: none;
            color: #3498db;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .selection-actions button:hover {
            text-decoration: underline;
        }
        
        .newsletter-stats {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .historique-envois {
            margin-top: 1.5rem;
        }
        
        .historique-envois .table {
            font-size: 0.9rem;
        }
        
        textarea {
            width: 100%;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            resize: vertical;
            font-family: Arial, sans-serif;
        }
        
        .form-actions {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            display: flex;
            gap: 1rem;
        }
    </style>
</body>
</html>