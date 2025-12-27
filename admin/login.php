<?php
require_once '../config/database.php';
require_once '../config/functions.php';

if (estConnecte() && estAdmin()) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE email = ? AND role = "admin"');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['id_user'] = $user['id_user'];
        $_SESSION['prenom'] = $user['prenom'];
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['role'] = $user['role'];
        header('Location: index.php');
        exit();
    } else {
        $error = 'Identifiants invalides.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Menuiserie</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        body { background: #f5f6fa; }
        .login-container { max-width: 400px; margin: 60px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
        .login-container h2 { text-align: center; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; }
        .btn-primary { width: 100%; padding: 0.75rem; background: #2c3e50; color: #fff; border: none; border-radius: 4px; font-size: 1rem; }
        .error { color: #e74c3c; text-align: center; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Connexion Admin</h2>
        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit" class="btn-primary">Se connecter</button>
        </form>
    </div>
</body>
</html>
