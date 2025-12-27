<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

redirigerSiNonConnecte();

// Récupérer les commandes du client
$stmt = $pdo->prepare("SELECT c.*, 
                       (SELECT COUNT(*) FROM details_commandes dc WHERE dc.id_commande = c.id_commande) as nb_articles
                       FROM commandes c 
                       WHERE c.id_user = ? 
                       ORDER BY c.date_commande DESC");
$stmt->execute([$_SESSION['user_id']]);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques du client
$stats = $pdo->prepare("SELECT 
                        COUNT(*) as total_commandes,
                        SUM(CASE WHEN statut = 'livrée' THEN 1 ELSE 0 END) as commandes_livrees,
                        SUM(CASE WHEN statut = 'en attente' OR statut = 'en fabrication' THEN 1 ELSE 0 END) as commandes_en_cours,
                        SUM(total) as total_depense
                        FROM commandes 
                        WHERE id_user = ?");
$stats->execute([$_SESSION['user_id']]);
$statistiques = $stats->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi de mes commandes - Menuiserie</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .suivi-container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #eee; text-align: center; }
        th { background: #f5f6fa; }
        .statut { font-weight: bold; }
        .statut.en-attente { color: #e67e22; }
        .statut.confirmee { color: #2980b9; }
        .statut.livree { color: #27ae60; }
        .statut.annulee { color: #e74c3c; }
        @media (max-width: 700px) {
          .suivi-container { padding: 0.5rem; }
          table, thead, tbody, th, td, tr { display: block; }
          th, td { padding: 0.5rem; }
          tr { margin-bottom: 1rem; }
        }
    </style>
</head>
<body>
<div class="suivi-container">
    <h1>Mes commandes</h1>
    <?php if (empty($commandes)): ?>
        <p>Vous n'avez pas encore passé de commande.</p>
        <a href="../produits/catalogue.php" class="btn btn-primary">Voir le catalogue</a>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Commande n°</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Total</th>
                    <th>Détails</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commandes as $commande): ?>
                    <tr>
                        <td><?php echo $commande['id_commande']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></td>
                        <td class="statut <?php echo str_replace(' ', '-', $commande['statut']); ?>">
                            <?php echo ucfirst($commande['statut']); ?>
                        </td>
                        <td><?php echo formatPrix($commande['total']); ?></td>
                        <td><a href="details.php?id=<?php echo $commande['id_commande']; ?>" class="btn btn-secondary">Voir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>