<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
include '../../includes/admin-header.php';
include '../../includes/admin-sidebar.php';

if (!estAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Récupérer les catégories
$categories = $pdo->query('SELECT * FROM categories ORDER BY nom_categorie')->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des catégories - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .categories-container { max-width: 700px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #eee; }
        th { background: #f5f6fa; }
        .actions a { margin-right: 0.5rem; }
        .btn-add { margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="categories-container">
    <h1>Gestion des catégories</h1>
    <a href="ajouter.php" class="btn btn-primary btn-add">Ajouter une catégorie</a>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?php echo htmlspecialchars($cat['nom_categorie']); ?></td>
                    <td><?php echo htmlspecialchars($cat['description']); ?></td>
                    <td class="actions">
                        <a href="modifier.php?id=<?php echo $cat['id_categorie']; ?>" class="btn btn-secondary">Modifier</a>
                        <a href="supprimer.php?id=<?php echo $cat['id_categorie']; ?>" class="btn btn-danger" onclick="return confirm('Supprimer cette catégorie ?');">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
