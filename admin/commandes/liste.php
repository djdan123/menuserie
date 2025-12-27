<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

redirigerSiNonAdmin();

// Récupérer les commandes avec filtres
$statut = isset($_GET['statut']) ? securiser($_GET['statut']) : '';
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

// Construire la requête
$sql = "SELECT c.*, u.nom, u.prenom, u.email 
        FROM commandes c 
        JOIN utilisateurs u ON c.id_user = u.id_user 
        WHERE 1=1";
$params = [];

if (!empty($statut)) {
    $sql .= " AND c.statut = ?";
    $params[] = $statut;
}

if (!empty($date_debut)) {
    $sql .= " AND DATE(c.date_commande) >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $sql .= " AND DATE(c.date_commande) <= ?";
    $params[] = $date_fin;
}

$sql .= " ORDER BY c.date_commande DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques des commandes
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'en attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'confirmée' THEN 1 ELSE 0 END) as confirmees,
        SUM(CASE WHEN statut = 'en fabrication' THEN 1 ELSE 0 END) as en_fabrication,
        SUM(CASE WHEN statut = 'livrée' THEN 1 ELSE 0 END) as livrees,
        SUM(CASE WHEN statut = 'annulée' THEN 1 ELSE 0 END) as annulees
    FROM commandes
")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des commandes - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include '../../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../../includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="commandes-container">
                <h1>Gestion des commandes</h1>
                <table>
                    <thead>
                        <tr>
                            <th>Commande n°</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Statut</th>
                            <th>Total</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $commande): ?>
                            <tr>
                                <td><?php echo $commande['id_commande']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></td>
                                <td><?php echo htmlspecialchars($commande['prenom'].' '.$commande['nom']); ?></td>
                                <td class="statut <?php echo str_replace(' ', '-', $commande['statut']); ?>">
                                    <?php echo ucfirst($commande['statut']); ?>
                                </td>
                                <td><?php echo formatPrix($commande['total']); ?></td>
                                <td class="actions">
                                    <a href="details.php?id=<?php echo $commande['id_commande']; ?>" class="btn btn-secondary">Voir</a>
                                    <?php if ($commande['statut'] === 'en attente'): ?>
                                        <a href="valider.php?id=<?php echo $commande['id_commande']; ?>" class="btn btn-success">Valider</a>
                                        <a href="refuser.php?id=<?php echo $commande['id_commande']; ?>" class="btn btn-danger">Refuser</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($commandes)): ?>
                    <p class="alert alert-info">Aucune commande trouvée.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>