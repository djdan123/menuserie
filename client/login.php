<?php
require_once '../config/database.php';
require_once '../config/functions.php';
 ini_set('display_errors', 1); error_reporting(E_ALL);

// Rediriger si déjà connecté
if (estConnecte()) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['id_user'] = $user['id_user'];
        $_SESSION['prenom'] = $user['prenom'];
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['role'] = $user['role'];
        if ($user['role'] === 'admin') {
            header('Location: ../admin/index.php');
        } else {
            header('Location: index.php');
        }
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
    <title>Connexion - Menuiserie</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: linear-gradient(120deg, #e0eafc, #cfdef3); min-height: 100vh; }
        .login-container { max-width: 400px; margin: 60px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px #0002; padding: 2.5rem 2rem; }
        .login-container h2 { text-align: center; margin-bottom: 2rem; color: #2980b9; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #2c3e50; }
        .form-group input { width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; }
        .btn-primary { width: 100%; padding: 0.9rem; background: linear-gradient(90deg, #2980b9, #6dd5fa); color: #fff; border: none; border-radius: 6px; font-size: 1.1rem; font-weight: bold; box-shadow: 0 2px 8px #0001; transition: background 0.2s; }
        .btn-primary:hover { background: linear-gradient(90deg, #2574a9, #4ca1af); }
        .error { color: #e74c3c; text-align: center; margin-bottom: 1rem; }
        .register-link { text-align: center; margin-top: 1.5rem; }
        @media (max-width: 500px) {
          .login-container { padding: 1.2rem 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Connexion</h2>
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
        <div class="register-link">
            <a href="register.php">Créer un compte</a>
        </div>
    </div>
</body>
</html>
