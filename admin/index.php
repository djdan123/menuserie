<?php
require_once '../config/database.php';
require_once '../config/functions.php';
include '../includes/admin-header.php';
include '../includes/admin-sidebar.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Menuiserie</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .dashboard {
            padding: 2rem;
            background: #f5f6fa;
        }
        .dashboard h1 { color: #2c3e50; }
        .stats {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
        }
        .stat-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px #0001;
            padding: 2rem;
            flex: 1;
            text-align: center;
        }
        .stat-card h2 { color: #2980b9; margin-bottom: 0.5rem; }
        .stat-card p { color: #666; }
    </style>
</head>
<body>
    <main class="dashboard">
        <h1>Bienvenue sur le tableau de bord</h1>
        <div class="stats">
            <div class="stat-card">
                <h2><?php echo $pdo->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn(); ?></h2>
                <p>Utilisateurs</p>
            </div>
            <div class="stat-card">
                <h2><?php echo $pdo->query('SELECT COUNT(*) FROM meubles')->fetchColumn(); ?></h2>
                <p>Meubles</p>
            </div>
            <div class="stat-card">
                <h2><?php echo $pdo->query('SELECT COUNT(*) FROM commandes')->fetchColumn(); ?></h2>
                <p>Commandes</p>
            </div>
        </div>
    </main>
</body>
</html>
