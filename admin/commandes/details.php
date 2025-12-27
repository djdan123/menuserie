<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
include '../../includes/admin-header.php';
include '../../includes/admin-sidebar.php';

if (!estAdmin()) {
    header('Location: ../login.php');
    exit();
}

$id_commande = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT c.*, u.nom, u.prenom, u.email FROM commandes c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_commande = ?');
$stmt->execute([$id_commande]);
$commande = $stmt->fetch();
if (!$commande) {
    echo '<p>Commande introuvable.</p>';
    exit();
}
$stmt = $pdo->prepare('SELECT d.*, m.nom, m.images FROM details_commandes d JOIN meubles m ON d.id_meuble = m.id_meuble WHERE d.id_commande = ?');
$stmt->execute([$id_commande]);
$details = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail commande - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .details-container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #eee; }
        th { background: #f5f6fa; }
        .produit-img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; }
        .statut { font-weight: bold; }
        .statut.en-attente { color: #e67e22; }
        .statut.confirmee { color: #2980b9; }
        .statut.livree { color: #27ae60; }
        .statut.annulee { color: #e74c3c; }
    </style>
</head>
<body>
<div class="details-container">
    <h1>Commande n°<?php echo $commande['id_commande']; ?></h1>
    <p>Date : <?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></p>
    <p>Client : <?php echo htmlspecialchars($commande['prenom'].' '.$commande['nom']); ?> (<?php echo htmlspecialchars($commande['email']); ?>)</p>
    <p>Statut : <span class="statut <?php echo str_replace(' ', '-', $commande['statut']); ?>"><?php echo ucfirst($commande['statut']); ?></span></p>
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
    <a href="liste.php" class="btn btn-secondary">Retour à la liste</a>
    <?php if ($commande['statut'] === 'en attente'): ?>
        <a href="valider.php?id=<?php echo $commande['id_commande']; ?>" class="btn btn-success">Valider</a>
        <a href="refuser.php?id=<?php echo $commande['id_commande']; ?>" class="btn btn-danger">Refuser</a>
    <?php endif; ?>
</div>
</body>
</html>
