<?php
// Script d'installation
echo "Installation de l'application de menuiserie...\n";
echo "============================================\n\n";

// Configuration
$host = 'localhost';
$dbname = 'menuiserie_db';
$username = 'root';
$password = '';

try {
    // Connexion sans base de données
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la base de données
    echo "Création de la base de données...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $dbname");
    
    // Tables
    echo "Création des tables...\n";
    
    $sql = "
    -- Table des utilisateurs
    CREATE TABLE IF NOT EXISTS utilisateurs (
        id_user INT PRIMARY KEY AUTO_INCREMENT,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'client') DEFAULT 'client',
        date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    -- Table des catégories
    CREATE TABLE IF NOT EXISTS categories (
        id_categorie INT PRIMARY KEY AUTO_INCREMENT,
        nom_categorie VARCHAR(100) NOT NULL,
        description TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    -- Table des meubles
    CREATE TABLE IF NOT EXISTS meubles (
        id_meuble INT PRIMARY KEY AUTO_INCREMENT,
        id_categorie INT,
        nom VARCHAR(200) NOT NULL,
        description TEXT,
        bois_type VARCHAR(100),
        longueur DECIMAL(10,2),
        largeur DECIMAL(10,2),
        hauteur DECIMAL(10,2),
        cout_fabrication DECIMAL(10,2),
        prix_vente DECIMAL(10,2) NOT NULL,
        quantite_stock INT DEFAULT 0,
        images VARCHAR(500),
        date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_categorie) REFERENCES categories(id_categorie) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    -- Table des commandes
    CREATE TABLE IF NOT EXISTS commandes (
        id_commande INT PRIMARY KEY AUTO_INCREMENT,
        id_user INT,
        date_commande DATETIME DEFAULT CURRENT_TIMESTAMP,
        statut ENUM('en attente', 'confirmée', 'en fabrication', 'livrée', 'annulée') DEFAULT 'en attente',
        total DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (id_user) REFERENCES utilisateurs(id_user) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    -- Table des détails de commande
    CREATE TABLE IF NOT EXISTS details_commandes (
        id_detail INT PRIMARY KEY AUTO_INCREMENT,
        id_commande INT,
        id_meuble INT,
        quantite INT NOT NULL,
        prix DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (id_commande) REFERENCES commandes(id_commande) ON DELETE CASCADE,
        FOREIGN KEY (id_meuble) REFERENCES meubles(id_meuble) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    -- Table de suivi de fabrication
    CREATE TABLE IF NOT EXISTS fabrication (
        id_fabrication INT PRIMARY KEY AUTO_INCREMENT,
        id_meuble INT,
        id_user INT,
        date_debut DATETIME,
        date_fin DATETIME,
        statut ENUM('en cours', 'terminé', 'en attente') DEFAULT 'en attente',
        FOREIGN KEY (id_meuble) REFERENCES meubles(id_meuble) ON DELETE CASCADE,
        FOREIGN KEY (id_user) REFERENCES utilisateurs(id_user) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    // Exécuter les requêtes
    $pdo->exec($sql);
    
    // Données de test
    echo "Insertion des données de test...\n";
    
    // Administrateur (mot de passe: admin123)
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO utilisateurs (nom, prenom, email, password, role) VALUES 
               ('Admin', 'System', 'admin@menuiserie.com', '$admin_password', 'admin')");
    
    // Catégories
    $pdo->exec("INSERT IGNORE INTO categories (nom_categorie, description) VALUES 
               ('Armoires', 'Armoires de rangement en bois massif'),
               ('Tables', 'Tables diverses pour salle à manger ou bureau'),
               ('Chaises', 'Chaises et fauteuils'),
               ('Étagères', 'Étagères et bibliothèques'),
               ('Lits', 'Lits et têtes de lit')");
    
    // Meubles
    $pdo->exec("INSERT IGNORE INTO meubles (id_categorie, nom, description, bois_type, longueur, largeur, hauteur, cout_fabrication, prix_vente, quantite_stock) VALUES 
               (1, 'Armoire normande', 'Grande armoire normande en chêne massif', 'Chêne', 200, 120, 220, 850, 1500, 5),
               (2, 'Table à manger extensible', 'Table extensible pour 6 à 10 personnes', 'Noyer', 180, 90, 75, 600, 1200, 3),
               (3, 'Chaise design', 'Chaise contemporaine en hêtre', 'Hêtre', 45, 45, 85, 80, 180, 20),
               (4, 'Étagère murale', 'Étagère murale design en bouleau', 'Bouleau', 120, 30, 180, 150, 350, 8),
               (5, 'Lit double en chêne', 'Lit double avec tête de lit en chêne massif', 'Chêne', 200, 160, 110, 1200, 2200, 2)");
    
    // Créer les dossiers nécessaires
    echo "Création des dossiers...\n";
    $dossiers = ['../uploads/produits', '../assets/images/produits', '../config'];
    
    foreach ($dossiers as $dossier) {
        if (!file_exists($dossier)) {
            mkdir($dossier, 0777, true);
            echo "  - $dossier créé\n";
        }
    }
    
    echo "\n✅ Installation terminée avec succès !\n";
    echo "🔑 Compte admin : admin@menuiserie.com / admin123\n";
    echo "🌐 Accès admin : http://localhost/menuiserie-app/admin/login.php\n";
    echo "🛒 Accès client : http://localhost/menuiserie-app/client/index.php\n";
    
} catch (PDOException $e) {
    die("❌ Erreur d'installation : " . $e->getMessage());
}
?>