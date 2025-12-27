<?php
require_once '../config/database.php';
require_once '../config/functions.php';

if (estConnecte()) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    if ($password !== $password2) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO utilisateurs (prenom, nom, email, password) VALUES (?, ?, ?, ?)');
            $stmt->execute([$prenom, $nom, $email, $hash]);
            header('Location: login.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Menuiserie</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: linear-gradient(120deg, #e0eafc, #cfdef3); min-height: 100vh; }
        .register-container { max-width: 400px; margin: 60px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px #0002; padding: 2.5rem 2rem; }
        .register-container h2 { text-align: center; margin-bottom: 2rem; color: #2980b9; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #2c3e50; }
        .form-group input { width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; }
        .btn-primary { width: 100%; padding: 0.9rem; background: linear-gradient(90deg, #2980b9, #6dd5fa); color: #fff; border: none; border-radius: 6px; font-size: 1.1rem; font-weight: bold; box-shadow: 0 2px 8px #0001; transition: background 0.2s; }
        .btn-primary:hover { background: linear-gradient(90deg, #2574a9, #4ca1af); }
        .error { color: #e74c3c; text-align: center; margin-bottom: 1rem; }
        .login-link { text-align: center; margin-top: 1.5rem; }
        @media (max-width: 500px) {
          .register-container { padding: 1.2rem 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Inscription</h2>
        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="prenom">Prénom</label>
                <input type="text" name="prenom" id="prenom" required>
            </div>
            <div class="form-group">
                <label for="nom">Nom</label>
                <input type="text" name="nom" id="nom" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group">
                <label for="password2">Confirmer le mot de passe</label>
                <input type="password" name="password2" id="password2" required>
            </div>
            <button type="submit" class="btn-primary">S'inscrire</button>
        </form>
        <div class="login-link">
            <a href="login.php">Déjà un compte ? Se connecter</a>
        </div>
    </div>
</body>
</html>
