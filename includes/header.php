<?php
// Déterminer le chemin des assets selon l'emplacement
$isAdmin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$assetsPath = $isAdmin ? '../assets' : 'assets';
?>

<header class="site-header">
    <div class="container">
        <div class="header-content">
            <a href="<?php echo $isAdmin ? '../index.php' : 'index.php'; ?>" class="logo" aria-label="Accueil Menuiserie Bois Noble">
                Menuiserie <span>Bois Noble</span>
            </a>
            
            <nav class="main-nav" aria-label="Navigation principale">
                <ul>
                    <?php if (estConnecte()): ?>
                        <?php if (estAdmin()): ?>
                            <!-- Menu admin -->
                            <li><a href="../admin/index.php">Dashboard</a></li>
                            <li><a href="../admin/produits/liste.php">Produits</a></li>
                            <li><a href="../admin/commandes/liste.php">Commandes</a></li>
                            <li><a href="../admin/statistiques.php">Statistiques</a></li>
                        <?php else: ?>
                            <!-- Menu client -->
                            <li><a href="../client/index.php">Accueil</a></li>
                            <li><a href="../client/produits/catalogue.php">Catalogue</a></li>
                            <li><a href="../client/panier/panier.php">Panier</a></li>
                            <li><a href="../client/commandes/suivi.php">Mes commandes</a></li>
                        <?php endif; ?>
                        
                        <li class="user-menu">
                            <span>Bonjour <?php echo htmlspecialchars($_SESSION['prenom']); ?>!</span>
                            <a href="../includes/logout.php" class="btn btn-secondary">Déconnexion</a>
                        </li>
                    <?php else: ?>
                        <!-- Menu visiteur -->
                        <li><a href="index.php">Accueil</a></li>
                        <li><a href="client/produits/catalogue.php">Catalogue</a></li>
                        <li><a href="client/login.php">Connexion</a></li>
                        <li><a href="client/register.php">Inscription</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <?php if (estConnecte() && !estAdmin()): ?>
                <div class="panier-icon" aria-label="Panier">
                    <a href="../client/panier/panier.php" class="btn btn-secondary" style="font-size:1.3rem;">
                        🛒
                        <?php if (isset($_SESSION['panier']) && count($_SESSION['panier']) > 0): ?>
                            <span class="panier-count" style="background:#4fc3f7;color:#222e3a;border-radius:50%;padding:0 7px;font-size:0.95rem;position:relative;top:-8px;left:-5px;min-width:22px;display:inline-block;text-align:center;">
                                <?php echo array_sum(array_column($_SESSION['panier'], 'quantite')); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <button class="menu-toggle" aria-label="Ouvrir le menu" style="background:none;border:none;color:#fff;font-size:2rem;display:none;">
                &#9776;
            </button>
        </div>
    </div>
</header>
<script>
// Responsive menu JS (à placer dans main.js si besoin)
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.querySelector('.menu-toggle');
    const nav = document.querySelector('.main-nav ul');
    if(toggle && nav) {
        toggle.addEventListener('click', function() {
            nav.classList.toggle('open');
        });
    }
});
</script>