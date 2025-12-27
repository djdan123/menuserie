<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

redirigerSiNonConnecte();

// Initialiser le panier
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// Actions sur le panier
if (isset($_POST['action'])) {
    $id_meuble = isset($_POST['id_meuble']) ? intval($_POST['id_meuble']) : 0;
    
    switch ($_POST['action']) {
        case 'supprimer':
            if (isset($_SESSION['panier'][$id_meuble])) {
                unset($_SESSION['panier'][$id_meuble]);
                flashMessage("Produit retiré du panier", "success");
            }
            break;
            
        case 'modifier':
            $quantite = isset($_POST['quantite']) ? intval($_POST['quantite']) : 1;
            if (isset($_SESSION['panier'][$id_meuble]) && $quantite > 0) {
                $_SESSION['panier'][$id_meuble]['quantite'] = $quantite;
                flashMessage("Quantité mise à jour", "success");
            }
            break;
            
        case 'vider':
            $_SESSION['panier'] = [];
            flashMessage("Panier vidé", "success");
            break;
    }
    
    header('Location: panier.php');
    exit();
}

// Calculer le total
$total = 0;
$articles = [];

if (!empty($_SESSION['panier'])) {
    // Récupérer les infos complètes des produits
    $ids = array_keys($_SESSION['panier']);
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    $stmt = $pdo->prepare("SELECT * FROM meubles WHERE id_meuble IN ($placeholders)");
    $stmt->execute($ids);
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Associer les données
    foreach ($produits as $produit) {
        $id = $produit['id_meuble'];
        $quantite = $_SESSION['panier'][$id]['quantite'];
        $sous_total = $produit['prix_vente'] * $quantite;
        $total += $sous_total;
        
        $articles[] = [
            'id' => $id,
            'nom' => $produit['nom'],
            'prix' => $produit['prix_vente'],
            'quantite' => $quantite,
            'sous_total' => $sous_total,
            'stock' => $produit['quantite_stock'],
            'image' => $produit['images']
        ];
    }
}

// Calculer la TVA
$tva = calculerTVA($total);
$total_ttc = $total + $tva;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier - Menuiserie Bois Noble</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="container">
        <h1>Mon Panier</h1>
        
        <?php echo getFlashMessage(); ?>
        
        <?php if (empty($articles)): ?>
            <div class="panier-vide">
                <p>Votre panier est vide</p>
                <a href="../produits/catalogue.php" class="btn btn-primary">Continuer vos achats</a>
            </div>
        <?php else: ?>
            <div class="panier-content">
                <!-- Liste des articles -->
                <div class="panier-articles">
                    <table class="table-panier">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Prix unitaire</th>
                                <th>Quantité</th>
                                <th>Sous-total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $article): ?>
                                <tr>
                                    <td class="produit-info">
                                        <?php if (!empty($article['image'])): ?>
                                            <img src="../../uploads/produits/<?php echo htmlspecialchars($article['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($article['nom']); ?>" class="produit-thumb">
                                        <?php endif; ?>
                                        <div>
                                            <h4><?php echo htmlspecialchars($article['nom']); ?></h4>
                                            <small>Stock disponible : <?php echo $article['stock']; ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo formatPrix($article['prix']); ?></td>
                                    <td>
                                        <form method="POST" class="form-quantite">
                                            <input type="hidden" name="id_meuble" value="<?php echo $article['id']; ?>">
                                            <input type="hidden" name="action" value="modifier">
                                            <input type="number" name="quantite" value="<?php echo $article['quantite']; ?>" 
                                                   min="1" max="<?php echo $article['stock']; ?>" class="input-quantite">
                                            <button type="submit" class="btn-update">🔄</button>
                                        </form>
                                    </td>
                                    <td><?php echo formatPrix($article['sous_total']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id_meuble" value="<?php echo $article['id']; ?>">
                                            <input type="hidden" name="action" value="supprimer">
                                            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <form method="POST" class="text-right">
                        <input type="hidden" name="action" value="vider">
                        <button type="submit" class="btn btn-secondary">Vider le panier</button>
                    </form>
                </div>
                
                <!-- Récapitulatif -->
                <div class="panier-recap">
                    <div class="recap-card">
                        <h3>Récapitulatif</h3>
                        
                        <div class="recap-details">
                            <div class="recap-ligne">
                                <span>Sous-total</span>
                                <span><?php echo formatPrix($total); ?></span>
                            </div>
                            <div class="recap-ligne">
                                <span>TVA (20%)</span>
                                <span><?php echo formatPrix($tva); ?></span>
                            </div>
                            <div class="recap-ligne total">
                                <span>Total TTC</span>
                                <span class="total-price"><?php echo formatPrix($total_ttc); ?></span>
                            </div>
                        </div>
                        
                        <div class="recap-actions">
                            <a href="../produits/catalogue.php" class="btn btn-secondary">Continuer mes achats</a>
                            <a href="commander.php" class="btn btn-primary">Passer la commande</a>
                        </div>
                        
                        <div class="recap-info">
                            <p>✅ Paiement sécurisé</p>
                            <p>✅ Livraison sous 15 jours</p>
                            <p>✅ Retour sous 30 jours</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Validation des quantités
        document.querySelectorAll('.input-quantite').forEach(input => {
            input.addEventListener('change', function() {
                const max = parseInt(this.getAttribute('max'));
                const value = parseInt(this.value);
                
                if (value > max) {
                    alert('Quantité maximale disponible : ' + max);
                    this.value = max;
                } else if (value < 1) {
                    this.value = 1;
                }
            });
        });
    </script>

    <style>
        .panier-container { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #eee; text-align: center; }
        th { background: #f5f6fa; }
        .produit-img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; }
        .btn-danger { background: #e74c3c; color: #fff; border: none; border-radius: 4px; padding: 0.5rem 1rem; }
        .btn-primary { margin-top: 2rem; }
        @media (max-width: 700px) {
          .panier-container { padding: 0.5rem; }
          table, thead, tbody, th, td, tr { display: block; }
          th, td { padding: 0.5rem; }
          tr { margin-bottom: 1rem; }
        }
    </style>
</body>
</html>