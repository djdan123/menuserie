<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
include '../../includes/admin-header.php';
include '../../includes/admin-sidebar.php';

if (!estAdmin()) {
    header('Location: ../login.php');
    exit();
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM meubles WHERE id_meuble = ?');
$stmt->execute([$id]);
$meuble = $stmt->fetch();
if (!$meuble) {
    echo '<p>Meuble introuvable.</p>';
    exit();
}
$categories = $pdo->query('SELECT * FROM categories ORDER BY nom_categorie')->fetchAll();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $bois_type = trim($_POST['bois_type'] ?? '');
    $longueur = floatval($_POST['longueur'] ?? 0);
    $largeur = floatval($_POST['largeur'] ?? 0);
    $hauteur = floatval($_POST['hauteur'] ?? 0);
    $cout_fabrication = floatval($_POST['cout_fabrication'] ?? 0);
    $prix_vente = floatval($_POST['prix_vente'] ?? 0);
    $quantite_stock = intval($_POST['quantite_stock'] ?? 0);
    $id_categorie = intval($_POST['id_categorie'] ?? 0);
    $image = $meuble['images'];
    if (!empty($_FILES['image']['name'])) {
        $targetDir = '../../uploads/produits/';
        $fileName = uniqid().'-'.basename($_FILES['image']['name']);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $image = $fileName;
        } else {
            $error = "Erreur lors de l'upload de l'image.";
        }
    }
    if (!$error) {
        $stmt = $pdo->prepare('UPDATE meubles SET id_categorie=?, nom=?, description=?, bois_type=?, longueur=?, largeur=?, hauteur=?, cout_fabrication=?, prix_vente=?, quantite_stock=?, images=? WHERE id_meuble=?');
        $stmt->execute([$id_categorie, $nom, $description, $bois_type, $longueur, $largeur, $hauteur, $cout_fabrication, $prix_vente, $quantite_stock, $image, $id]);
        header('Location: liste.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un meuble - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .form-container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; }
        .btn-primary { margin-top: 1rem; }
        .error { color: #e74c3c; margin-bottom: 1rem; }
        .img-preview { width: 120px; height: 120px; object-fit: cover; border-radius: 6px; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="form-container">
    <h1>Modifier un meuble</h1>
    <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="nom">Nom</label>
            <input type="text" name="nom" id="nom" value="<?php echo htmlspecialchars($meuble['nom']); ?>" required>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" required><?php echo htmlspecialchars($meuble['description']); ?></textarea>
        </div>
        <div class="form-group">
            <label for="bois_type">Type de bois</label>
            <input type="text" name="bois_type" id="bois_type" value="<?php echo htmlspecialchars($meuble['bois_type']); ?>">
        </div>
        <div class="form-group">
            <label for="longueur">Longueur (cm)</label>
            <input type="number" name="longueur" id="longueur" step="0.01" value="<?php echo $meuble['longueur']; ?>">
        </div>
        <div class="form-group">
            <label for="largeur">Largeur (cm)</label>
            <input type="number" name="largeur" id="largeur" step="0.01" value="<?php echo $meuble['largeur']; ?>">
        </div>
        <div class="form-group">
            <label for="hauteur">Hauteur (cm)</label>
            <input type="number" name="hauteur" id="hauteur" step="0.01" value="<?php echo $meuble['hauteur']; ?>">
        </div>
        <div class="form-group">
            <label for="cout_fabrication">Coût de fabrication (€)</label>
            <input type="number" name="cout_fabrication" id="cout_fabrication" step="0.01" value="<?php echo $meuble['cout_fabrication']; ?>">
        </div>
        <div class="form-group">
            <label for="prix_vente">Prix de vente (€)</label>
            <input type="number" name="prix_vente" id="prix_vente" step="0.01" value="<?php echo $meuble['prix_vente']; ?>" required>
        </div>
        <div class="form-group">
            <label for="quantite_stock">Quantité en stock</label>
            <input type="number" name="quantite_stock" id="quantite_stock" value="<?php echo $meuble['quantite_stock']; ?>" required>
        </div>
        <div class="form-group">
            <label for="id_categorie">Catégorie</label>
            <select name="id_categorie" id="id_categorie" required>
                <option value="">-- Choisir --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id_categorie']; ?>" <?php if ($meuble['id_categorie'] == $cat['id_categorie']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['nom_categorie']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="image">Image</label><br>
            <?php if (!empty($meuble['images'])): ?>
                <img src="../../uploads/produits/<?php echo htmlspecialchars($meuble['images']); ?>" class="img-preview" alt="Image actuelle">
            <?php endif; ?>
            <input type="file" name="image" id="image" accept="image/*">
        </div>
        <button type="submit" class="btn-primary">Enregistrer</button>
    </form>
</div>
</body>
</html>
