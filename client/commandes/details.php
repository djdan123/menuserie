<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
include '../../includes/header.php';

if (!estConnecte()) {
    header('Location: ../login.php');
    exit();
}

$id_commande = (int)($_GET['id'] ?? 0);
$id_user = $_SESSION['id_user'];

// Vérifier que la commande appartient à l'utilisateur
$stmt = $pdo->prepare('SELECT * FROM commandes WHERE id_commande = ? AND id_user = ?');
$stmt->execute([$id_commande, $id_user]);
$commande = $stmt->fetch();
if (!$commande) {
    echo '<p>Commande introuvable.</p>';
    exit();
}

// Récupérer les détails de la commande
$stmt = $pdo->prepare('SELECT d.*, m.nom, m.images FROM details_commandes d JOIN meubles m ON d.id_meuble = m.id_meuble WHERE d.id_commande = ?');
$stmt->execute([$id_commande]);
$details = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail commande - Menuiserie</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .details-container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #eee; text-align: center; }
        th { background: #f5f6fa; }
        .produit-img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; }
        @media (max-width: 700px) {
          .details-container { padding: 0.5rem; }
          table, thead, tbody, th, td, tr { display: block; }
          th, td { padding: 0.5rem; }
          tr { margin-bottom: 1rem; }
        }
    </style>
</head>
<body>
<div class="details-container">
    <h1>Commande n°<?php echo $commande['id_commande']; ?></h1>
    <p>Date : <?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></p>
    <p>Statut : <strong><?php echo ucfirst($commande['statut']); ?></strong></p>
    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th>Image</th>
                <th>Prix</th>
                <th>Quantité</th>
                <th>Sous-total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['nom']); ?></td>
                    <td>
                        <?php if (!empty($item['images'])): ?>
                            <img src="../../uploads/produits/<?php echo htmlspecialchars($item['images']); ?>" class="produit-img" alt="<?php echo htmlspecialchars($item['nom']); ?>">
                        <?php else: ?>
                            <img src="../../assets/images/produits/default.jpg" class="produit-img" alt="Image par défaut">
                        <?php endif; ?>
                    </td>
                    <td><?php echo formatPrix($item['prix']); ?></td>
                    <td><?php echo $item['quantite']; ?></td>
                    <td><?php echo formatPrix($item['prix'] * $item['quantite']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h3>Total : <?php echo formatPrix($commande['total']); ?></h3>
    <a href="suivi.php" class="btn btn-secondary">Retour à mes commandes</a>
</div>
</body>
</html>
