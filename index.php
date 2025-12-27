<?php
require_once 'config/database.php';
require_once 'config/functions.php';

// Récupérer les derniers meubles
$stmt = $pdo->query("SELECT m.*, c.nom_categorie FROM meubles m 
                     LEFT JOIN categories c ON m.id_categorie = c.id_categorie 
                     WHERE m.quantite_stock > 0 
                     ORDER BY m.date_ajout DESC LIMIT 6");
$derniers_meubles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories
$categories = $pdo->query("SELECT * FROM categories LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menuiserie Bois Noble - Fabrication et Vente de Meubles</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero {
            background: linear-gradient(120deg, #222e3a 60%, #4fc3f7 100%);
            color: #fff;
            padding: 4rem 0 3rem 0;
            text-align: center;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
        }
        .hero-content h1 {
            font-size: 2.7rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
        }
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }
        .categorie-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            padding: 2rem 1.2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .categorie-card h3 {
            color: #4fc3f7;
            margin-bottom: 0.7rem;
        }
        .produit-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }
        .produit-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.15s;
        }
        .produit-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 6px 24px rgba(79,195,247,0.13);
        }
        .produit-image img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #f4f6f8;
        }
        .produit-info {
            padding: 1.2rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .produit-categorie {
            color: #4fc3f7;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        .produit-prix {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        .produit-actions {
            margin-top: auto;
        }
        .about-content {
            display: flex;
            gap: 2.5rem;
            align-items: center;
            margin: 2rem 0;
        }
        .about-text {
            flex: 2;
        }
        .about-image {
            flex: 1;
            min-width: 220px;
        }
        .about-image img {
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.09);
        }
        .about-text ul {
            margin: 1.2rem 0 0 0;
            padding: 0;
            list-style: none;
        }
        .about-text li {
            margin-bottom: 0.5rem;
            font-size: 1.08rem;
        }
        .section {
            padding: 3rem 0 2rem 0;
        }
        .bg-light {
            background: #f4f6f8;
        }
        .text-center {
            text-align: center;
        }
        @media (max-width: 900px) {
            .about-content {
                flex-direction: column;
                gap: 1.5rem;
            }
            .about-image {
                width: 100%;
                min-width: 0;
            }
        }
        @media (max-width: 600px) {
            .hero-content h1 {
                font-size: 1.5rem;
            }
            .section {
                padding: 1.5rem 0 1rem 0;
            }
            .produit-image img {
                height: 120px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Menuiserie d'Art & Meubles Sur Mesure</h1>
                    <p>Fabriqués avec passion depuis 1995. Découvrez nos créations uniques en bois massif.</p>
                    <a href="client/produits/catalogue.php" class="btn btn-primary">Voir le catalogue</a>
                </div>
            </div>
        </section>
        <!-- Section Catégories -->
        <section class="section">
            <div class="container">
                <h2>Nos Catégories</h2>
                <div class="categories-grid">
                    <?php foreach ($categories as $categorie): ?>
                        <div class="categorie-card">
                            <h3><?php echo htmlspecialchars($categorie['nom_categorie']); ?></h3>
                            <p><?php echo htmlspecialchars($categorie['description']); ?></p>
                            <a href="client/produits/catalogue.php?categorie=<?php echo $categorie['id_categorie']; ?>" 
                               class="btn btn-secondary">Explorer</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <!-- Section Nouveautés -->
        <section class="section bg-light">
            <div class="container">
                <h2>Nos Nouveautés</h2>
                <div class="produit-grid">
                    <?php foreach ($derniers_meubles as $meuble): ?>
                        <div class="produit-card">
                            <div class="produit-image">
                                <?php if (!empty($meuble['images'])): ?>
                                    <img src="uploads/produits/<?php echo htmlspecialchars($meuble['images']); ?>" 
                                         alt="<?php echo htmlspecialchars($meuble['nom']); ?>">
                                <?php else: ?>
                                    <img src="assets/images/produits/default.jpg" alt="Image par défaut">
                                <?php endif; ?>
                            </div>
                            <div class="produit-info">
                                <h3><?php echo htmlspecialchars($meuble['nom']); ?></h3>
                                <p class="produit-categorie"><?php echo htmlspecialchars($meuble['nom_categorie']); ?></p>
                                <p class="produit-prix"><?php echo formatPrix($meuble['prix_vente']); ?></p>
                                <div class="produit-actions">
                                    <a href="client/produits/details.php?id=<?php echo $meuble['id_meuble']; ?>" 
                                       class="btn btn-secondary">Voir détails</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center">
                    <a href="client/produits/catalogue.php" class="btn btn-primary">Voir tout le catalogue</a>
                </div>
            </div>
        </section>
        <!-- Section À Propos -->
        <section class="section">
            <div class="container">
                <div class="about-content">
                    <div class="about-text">
                        <h2>Notre Savoir-Faire Artisanal</h2>
                        <p>Depuis plus de 25 ans, nous créons des meubles uniques en bois massif, 
                        alliant tradition et design contemporain. Chaque pièce est fabriquée 
                        avec passion dans notre atelier.</p>
                        <ul>
                            <li>✅ Bois massif de qualité</li>
                            <li>✅ Fabrication française</li>
                            <li>✅ Sur-mesure possible</li>
                            <li>✅ Livraison en France</li>
                        </ul>
                    </div>
                    <div class="about-image">
                        <img src="assets/images/atelier.jpg" alt="Notre atelier">
                    </div>
                </div>
            </div>
        </section>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>