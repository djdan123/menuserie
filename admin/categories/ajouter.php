<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
include '../../includes/admin-header.php';
include '../../includes/admin-sidebar.php';

if (!estAdmin()) {
    header('Location: ../login.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (empty($nom)) {
        $error = 'Le nom de la catégorie est obligatoire.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO categories (nom_categorie, description) VALUES (?, ?)');
        $stmt->execute([$nom, $description]);
        header('Location: index.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une catégorie - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .form-container { max-width: 500px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; }
        .btn-primary { margin-top: 1rem; }
        .error { color: #e74c3c; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="form-container">
    <h1>Ajouter une catégorie</h1>
    <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="nom">Nom</label>
            <input type="text" name="nom" id="nom" required>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description"></textarea>
        </div>
        <button type="submit" class="btn-primary">Ajouter</button>
    </form>
</div>
</body>
</html>
