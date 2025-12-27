<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

redirigerSiNonAdmin();

$erreurs = [];
$succes = false;

// Récupérer les catégories pour le select
$categories = $pdo->query("SELECT * FROM categories ORDER BY nom_categorie")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données
    $nom = securiser($_POST['nom']);
    $id_categorie = intval($_POST['id_categorie']);
    $description = securiser($_POST['description']);
    $bois_type = securiser($_POST['bois_type']);
    $longueur = floatval($_POST['longueur']);
    $largeur = floatval($_POST['largeur']);
    $hauteur = floatval($_POST['hauteur']);
    $cout_fabrication = floatval($_POST['cout_fabrication']);
    $prix_vente = floatval($_POST['prix_vente']);
    $quantite_stock = intval($_POST['quantite_stock']);
    
    // Validation
    if (empty($nom)) $erreurs[] = "Le nom est obligatoire";
    if ($id_categorie <= 0) $erreurs[] = "La catégorie est obligatoire";
    if ($prix_vente <= 0) $erreurs[] = "Le prix de vente doit être positif";
    if ($quantite_stock < 0) $erreurs[] = "La quantité ne peut pas être négative";
    
    // Gestion de l'image
    $image_name = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_result = uploaderImage($_FILES['image']);
        if ($upload_result['success']) {
            $image_name = $upload_result['fileName'];
        } else {
            $erreurs[] = $upload_result['message'];
        }
    }
    
    // Insertion si aucune erreur
    if (empty($erreurs)) {
        $stmt = $pdo->prepare("INSERT INTO meubles (id_categorie, nom, description, bois_type, longueur, largeur, hauteur, cout_fabrication, prix_vente, quantite_stock, images) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        try {
            $stmt->execute([
                $id_categorie, $nom, $description, $bois_type, $longueur, $largeur, $hauteur,
                $cout_fabrication, $prix_vente, $quantite_stock, $image_name
            ]);
            
            $id_meuble = $pdo->lastInsertId();
            
            // Créer une entrée dans la table fabrication
            $stmt_fab = $pdo->prepare("INSERT INTO fabrication (id_meuble, statut) VALUES (?, 'en attente')");
            $stmt_fab->execute([$id_meuble]);
            
            flashMessage("Produit ajouté avec succès !", "success");
            header('Location: liste.php');
            exit();
        } catch (PDOException $e) {
            $erreurs[] = "Erreur : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include '../../includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include '../../includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="form-container">
                <h1>Ajouter un meuble</h1>
                
                <?php echo getFlashMessage(); ?>
                
                <?php if (!empty($erreurs)): ?>
                    <div class="error">
                        <?php foreach ($erreurs as $erreur): ?>
                            <p><?php echo $erreur; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" name="nom" id="nom" required value="<?php echo isset($nom) ? $nom : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" required><?php echo isset($description) ? $description : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="bois_type">Type de bois</label>
                        <input type="text" name="bois_type" id="bois_type" value="<?php echo isset($bois_type) ? $bois_type : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="longueur">Longueur (cm)</label>
                        <input type="number" name="longueur" id="longueur" step="0.01" value="<?php echo isset($longueur) ? $longueur : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="largeur">Largeur (cm)</label>
                        <input type="number" name="largeur" id="largeur" step="0.01" value="<?php echo isset($largeur) ? $largeur : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="hauteur">Hauteur (cm)</label>
                        <input type="number" name="hauteur" id="hauteur" step="0.01" value="<?php echo isset($hauteur) ? $hauteur : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="cout_fabrication">Coût de fabrication (€)</label>
                        <input type="number" name="cout_fabrication" id="cout_fabrication" step="0.01" value="<?php echo isset($cout_fabrication) ? $cout_fabrication : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="prix_vente">Prix de vente (€)</label>
                        <input type="number" name="prix_vente" id="prix_vente" step="0.01" required value="<?php echo isset($prix_vente) ? $prix_vente : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="quantite_stock">Quantité en stock</label>
                        <input type="number" name="quantite_stock" id="quantite_stock" required value="<?php echo isset($quantite_stock) ? $quantite_stock : 0; ?>">
                    </div>
                    <div class="form-group">
                        <label for="id_categorie">Catégorie</label>
                        <select name="id_categorie" id="id_categorie" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id_categorie']; ?>"
                                        <?php echo (isset($id_categorie) && $id_categorie == $cat['id_categorie']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nom_categorie']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="image">Image</label>
                        <input type="file" name="image" id="image" accept="image/*">
                    </div>
                    <button type="submit" class="btn-primary">Ajouter</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>