<?php
// Configuration de la base de données — modifiez si nécessaire
$host = '127.0.0.1';
$db   = 'menuiserie_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Retourner l'objet PDO pour que `require 'config/database.php'` renvoie l'instance
    return $pdo;
} catch (PDOException $e) {
    // En développement, afficher l'erreur. En production, logguez et affichez un message générique.
    echo "Erreur de connexion à la base de données: " . htmlspecialchars($e->getMessage());
    exit;
}
