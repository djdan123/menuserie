<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
include '../../includes/header.php';

// Récupérer les catégories
$categories = $pdo->query('SELECT * FROM categories')->fetchAll();

// Filtres
$cat = isset($_GET['categorie']) ? (int)$_GET['categorie'] : 0;
$search = trim($_GET['search'] ?? '');

$sql = 'SELECT m.*, c.nom_categorie FROM meubles m LEFT JOIN categories c ON m.id_categorie = c.id_categorie WHERE m.quantite_stock > 0';
$params = [];
if ($cat) {
    $sql .= ' AND m.id_categorie = ?';
    $params[] = $cat;
}
if ($search) {
    $sql .= ' AND m.nom LIKE ?';
    $params[] = "%$search%";
}
$sql .= ' ORDER BY m.date_ajout DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$meubles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue - Menuiserie</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .catalogue-container { max-width: 1200px; margin: 40px auto; }
        .catalogue-container h1 { text-align: center; color: #2c3e50; margin-bottom: 2rem; }
        .filters { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; justify-content: center; }
        .filters select, .filters input { padding: 0.5rem; border-radius: 4px; border: 1px solid #ddd; }
        .produit-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 2rem; }
        .produit-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 1rem; display: flex; flex-direction: column; transition: box-shadow 0.2s; }
        .produit-card:hover { box-shadow: 0 4px 16px #0002; }
        .produit-image img { width: 100%; height: 200px; object-fit: cover; border-radius: 6px; }
        .produit-info { flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .produit-info h3 { color: #2980b9; margin: 0.5rem 0; }
        .produit-prix { color: #27ae60; font-weight: bold; font-size: 1.1rem; }
        .btn-secondary { margin-top: 1rem; align-self: flex-end; }
        @media (max-width: 700px) {
          .catalogue-container { padding: 0 0.5rem; }
          .produit-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="catalogue-container">
    <h1>Catalogue des meubles</h1>
    <form class="filters" method="get">
        <select name="categorie">
            <option value="0">Toutes catégories</option>
            <?php foreach ($categories as $categorie): ?>
                <option value="<?php echo $categorie['id_categorie']; ?>" <?php if ($cat == $categorie['id_categorie']) echo 'selected'; ?>><?php echo htmlspecialchars($categorie['nom_categorie']); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="search" placeholder="Recherche..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn-primary">Filtrer</button>
    </form>
    <div class="produit-grid">
        <?php foreach ($meubles as $meuble): ?>
            <div class="produit-card">
                <div class="produit-image">
                    <?php if (!empty($meuble['images'])): ?>
                        <img src="../../uploads/produits/<?php echo htmlspecialchars($meuble['images']); ?>" alt="<?php echo htmlspecialchars($meuble['nom']); ?>">
                    <?php else: ?>
                        <img src="../../assets/images/produits/default.jpg" alt="Image par défaut">
                    <?php endif; ?>
                </div>
                <div class="produit-info">
                    <h3><?php echo htmlspecialchars($meuble['nom']); ?></h3>
                    <p><?php echo htmlspecialchars($meuble['nom_categorie']); ?></p>
                    <p class="produit-prix"><?php echo formatPrix($meuble['prix_vente']); ?></p>
                    <a href="details.php?id=<?php echo $meuble['id_meuble']; ?>" class="btn btn-secondary">Voir détails</a>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($meubles)): ?>
            <p>Aucun meuble trouvé.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
