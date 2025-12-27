<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: catalogue.php');
    exit();
}

$id_meuble = intval($_GET['id']);

// Récupérer les informations du meuble
$stmt = $pdo->prepare("SELECT m.*, c.nom_categorie, c.description as desc_categorie 
                       FROM meubles m 
                       LEFT JOIN categories c ON m.id_categorie = c.id_categorie 
                       WHERE m.id_meuble = ?");
$stmt->execute([$id_meuble]);
$meuble = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier si le meuble existe
if (!$meuble) {
    header('Location: catalogue.php');
    exit();
}

// Récupérer les meubles similaires (même catégorie)
$stmt_similaires = $pdo->prepare("SELECT * FROM meubles 
                                  WHERE id_categorie = ? AND id_meuble != ? 
                                  AND quantite_stock > 0 
                                  ORDER BY RAND() LIMIT 4");
$stmt_similaires->execute([$meuble['id_categorie'], $id_meuble]);
$meubles_similaires = $stmt_similaires->fetchAll(PDO::FETCH_ASSOC);

// Gestion de l'ajout au panier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_panier'])) {
    if (!estConnecte()) {
        flashMessage("Vous devez être connecté pour ajouter au panier", "error");
        header('Location: ../../client/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    $quantite = intval($_POST['quantite']);
    
    if ($quantite > 0 && $quantite <= $meuble['quantite_stock']) {
        // Initialiser le panier
        if (!isset($_SESSION['panier'])) {
            $_SESSION['panier'] = [];
        }
        
        // Ajouter au panier
        if (isset($_SESSION['panier'][$id_meuble])) {
            $_SESSION['panier'][$id_meuble]['quantite'] += $quantite;
        } else {
            $_SESSION['panier'][$id_meuble] = [
                'id_meuble' => $id_meuble,
                'quantite' => $quantite,
                'nom' => $meuble['nom'],
                'prix' => $meuble['prix_vente']
            ];
        }
        
        flashMessage("Produit ajouté au panier avec succès !", "success");
        header('Location: details.php?id=' . $id_meuble);
        exit();
    } else {
        flashMessage("Quantité invalide ou stock insuffisant", "error");
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail - <?php echo htmlspecialchars($meuble['nom']); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .details-container { max-width: 900px; margin: 40px auto; display: flex; gap: 2rem; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 2rem; flex-wrap: wrap; }
        .details-image img { width: 350px; height: 350px; object-fit: cover; border-radius: 8px; }
        .details-info { flex: 1; min-width: 250px; }
        .details-info h1 { color: #2c3e50; }
        .details-info .prix { color: #2980b9; font-size: 1.5rem; font-weight: bold; }
        .btn-primary { margin-top: 2rem; }
        @media (max-width: 900px) {
          .details-container { flex-direction: column; align-items: center; padding: 1rem; }
          .details-image img { width: 100%; height: 220px; }
        }
    </style>
</head>
<body>
<div class="details-container">
    <div class="details-image">
        <?php if (!empty($meuble['images'])): ?>
            <img src="../../uploads/produits/<?php echo htmlspecialchars($meuble['images']); ?>" alt="<?php echo htmlspecialchars($meuble['nom']); ?>">
        <?php else: ?>
            <img src="../../assets/images/produits/default.jpg" alt="Image par défaut">
        <?php endif; ?>
    </div>
    <div class="details-info">
        <h1><?php echo htmlspecialchars($meuble['nom']); ?></h1>
        <p><strong>Catégorie :</strong> <?php echo htmlspecialchars($meuble['nom_categorie']); ?></p>
        <p><strong>Description :</strong> <?php echo nl2br(htmlspecialchars($meuble['description'])); ?></p>
        <p><strong>Type de bois :</strong> <?php echo htmlspecialchars($meuble['bois_type']); ?></p>
        <p><strong>Dimensions :</strong> <?php echo $meuble['longueur'].' x '.$meuble['largeur'].' x '.$meuble['hauteur'].' cm'; ?></p>
        <p class="prix"><?php echo formatPrix($meuble['prix_vente']); ?></p>
        <form method="post">
            <input type="hidden" name="ajouter_panier" value="1">
            <label for="quantite">Quantité :</label>
            <input type="number" name="quantite" id="quantite" value="1" min="1" max="<?php echo $meuble['quantite_stock']; ?>">
            <button type="submit" class="btn-primary">Ajouter au panier</button>
        </form>
    </div>
</div>
</body>
</html>