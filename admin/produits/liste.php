<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

redirigerSiNonAdmin();

// Récupérer les filtres
$categorie = isset($_GET['categorie']) ? intval($_GET['categorie']) : 0;
$stock_min = isset($_GET['stock_min']) ? intval($_GET['stock_min']) : 0;
$stock_max = isset($_GET['stock_max']) ? intval($_GET['stock_max']) : 1000;
$recherche = isset($_GET['recherche']) ? securiser($_GET['recherche']) : '';

// Construire la requête
$sql = "SELECT m.*, c.nom_categorie,
        (SELECT COUNT(*) FROM details_commandes dc WHERE dc.id_meuble = m.id_meuble) as nb_ventes
        FROM meubles m 
        LEFT JOIN categories c ON m.id_categorie = c.id_categorie 
        WHERE 1=1";
$params = [];

if ($categorie > 0) {
    $sql .= " AND m.id_categorie = ?";
    $params[] = $categorie;
}

if ($stock_min > 0) {
    $sql .= " AND m.quantite_stock >= ?";
    $params[] = $stock_min;
}

if ($stock_max < 1000) {
    $sql .= " AND m.quantite_stock <= ?";
    $params[] = $stock_max;
}

if (!empty($recherche)) {
    $sql .= " AND (m.nom LIKE ? OR m.description LIKE ? OR m.bois_type LIKE ?)";
    $searchTerm = "%$recherche%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY m.date_ajout DESC";

// Exécuter la requête
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$meubles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories pour le filtre
$categories = $pdo->query("SELECT * FROM categories ORDER BY nom_categorie")->fetchAll(PDO::FETCH_ASSOC);

// Statistiques des produits
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_produits,
        SUM(quantite_stock) as total_stock,
        SUM(prix_vente * quantite_stock) as valeur_stock,
        SUM(CASE WHEN quantite_stock = 0 THEN 1 ELSE 0 END) as ruptures,
        SUM(CASE WHEN quantite_stock < 5 THEN 1 ELSE 0 END) as stock_faible
    FROM meubles
")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des produits - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include '../../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../../includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>Gestion des produits</h1>
                <a href="ajouter.php" class="btn btn-success">+ Nouveau produit</a>
            </div>
            
            <!-- Statistiques rapides -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <h3>Total produits</h3>
                        <p class="stat-value"><?php echo $stats['total_produits']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-info">
                        <h3>Stock total</h3>
                        <p class="stat-value"><?php echo $stats['total_stock']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3>Valeur stock</h3>
                        <p class="stat-value"><?php echo formatPrix($stats['valeur_stock']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-info">
                        <h3>Stock faible</h3>
                        <p class="stat-value"><?php echo $stats['stock_faible']; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Filtres -->
            <div class="filtres-card">
                <h3>Filtres</h3>
                <form method="GET" class="filtre-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="categorie">Catégorie :</label>
                            <select id="categorie" name="categorie">
                                <option value="0">Toutes les catégories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id_categorie']; ?>" 
                                            <?php echo ($categorie == $cat['id_categorie']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nom_categorie']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="stock_min">Stock min :</label>
                            <input type="number" id="stock_min" name="stock_min" min="0" value="<?php echo $stock_min; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="stock_max">Stock max :</label>
                            <input type="number" id="stock_max" name="stock_max" min="0" value="<?php echo $stock_max; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="recherche">Recherche :</label>
                            <input type="text" id="recherche" name="recherche" placeholder="Nom, description, bois..." 
                                   value="<?php echo htmlspecialchars($recherche); ?>">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-secondary">Filtrer</button>
                            <a href="liste.php" class="btn btn-link">Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Tableau des produits -->
            <div class="meubles-container">
                <h1>Gestion des meubles</h1>
                <a href="ajouter.php" class="btn btn-primary btn-add">Ajouter un meuble</a>
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($meubles as $meuble): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($meuble['images'])): ?>
                                        <img src="../../uploads/produits/<?php echo htmlspecialchars($meuble['images']); ?>" class="produit-img" alt="<?php echo htmlspecialchars($meuble['nom']); ?>">
                                    <?php else: ?>
                                        <img src="../../assets/images/produits/default.jpg" class="produit-img" alt="Image par défaut">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($meuble['nom']); ?></td>
                                <td><?php echo htmlspecialchars($meuble['nom_categorie']); ?></td>
                                <td><?php echo formatPrix($meuble['prix_vente']); ?></td>
                                <td><?php echo $meuble['quantite_stock']; ?></td>
                                <td class="actions">
                                    <a href="modifier.php?id=<?php echo $meuble['id_meuble']; ?>" class="btn btn-secondary">Modifier</a>
                                    <a href="supprimer.php?id=<?php echo $meuble['id_meuble']; ?>" class="btn btn-danger" onclick="return confirm('Supprimer ce meuble ?');">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Produits en rupture de stock -->
            <?php
            $ruptures = $pdo->query("SELECT * FROM meubles WHERE quantite_stock = 0 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($ruptures)): ?>
                <div class="alert-card alert-warning">
                    <h3>⚠️ Produits en rupture de stock (<?php echo count($ruptures); ?>)</h3>
                    <ul class="rupture-list">
                        <?php foreach ($ruptures as $rupture): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($rupture['nom']); ?></strong>
                                - Dernier prix : <?php echo formatPrix($rupture['prix_vente']); ?>
                                <a href="modifier.php?id=<?php echo $rupture['id_meuble']; ?>" class="btn btn-sm">Réapprovisionner</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function exporterCSV() {
            // Récupérer les filtres actuels
            const categorie = document.getElementById('categorie').value;
            const stockMin = document.getElementById('stock_min').value;
            const stockMax = document.getElementById('stock_max').value;
            const recherche = document.getElementById('recherche').value;
            
            // Créer l'URL d'export
            const url = `export.php?categorie=${categorie}&stock_min=${stockMin}&stock_max=${stockMax}&recherche=${encodeURIComponent(recherche)}`;
            
            // Télécharger le fichier
            window.location.href = url;
        }
        
        // Auto-refresh du stock toutes les 30 secondes
        setInterval(() => {
            // Mettre à jour les indicateurs de stock
            document.querySelectorAll('.stock-value').forEach(element => {
                const current = parseInt(element.textContent);
                // Simulation de mise à jour (dans la réalité, appeler une API)
                if (current > 0 && Math.random() > 0.8) {
                    element.textContent = current - 1;
                    if (current - 1 < 3) {
                        element.classList.add('low');
                        element.parentElement.innerHTML += ' <span class="stock-alert">⚠️</span>';
                    }
                }
            });
        }, 30000);
    </script>
</body>
</html>