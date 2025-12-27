<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
include '../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM commandes WHERE id_commande = ? AND id_user = ?');
$stmt->execute([$id, $_SESSION['id_user']]);
$commande = $stmt->fetch();
if (!$commande) {
    echo '<p>Commande introuvable.</p>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation commande - Menuiserie</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .confirmation-container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 2rem; text-align: center; }
        .btn-primary { margin-top: 2rem; }
    </style>
</head>
<body>
<div class="confirmation-container">
    <h1>Merci pour votre commande !</h1>
    <p>Votre commande n°<?php echo $commande['id_commande']; ?> a bien été enregistrée.</p>
    <a href="../produits/catalogue.php" class="btn-primary">Retour au catalogue</a>
</div>
</body>
</html>
