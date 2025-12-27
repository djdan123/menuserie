<aside class="admin-sidebar">
    <div class="sidebar-header">
        <div class="admin-logo">
            <h2>Menuiserie Admin</h2>
            <p>Tableau de bord</p>
        </div>
    </div>
    
    <nav class="admin-menu">
        <!-- Tableau de bord -->
        <div class="menu-section">
            <h3>Tableau de bord</h3>
            <ul>
                <li>
                    <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        📊 Tableau de bord
                    </a>
                </li>
                <li>
                    <a href="statistiques.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'statistiques.php' ? 'active' : ''; ?>">
                        📈 Statistiques
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Gestion des produits -->
        <div class="menu-section">
            <h3>Produits</h3>
            <ul>
                <li>
                    <a href="produits/liste.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'produits/liste.php') !== false ? 'active' : ''; ?>">
                        📦 Tous les produits
                    </a>
                </li>
                <li>
                    <a href="produits/ajouter.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'ajouter.php' ? 'active' : ''; ?>">
                        ➕ Ajouter un produit
                    </a>
                </li>
                <li>
                    <a href="categories/gestion.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'gestion.php' ? 'active' : ''; ?>">
                        📁 Catégories
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Commandes -->
        <div class="menu-section">
            <h3>Commandes</h3>
            <ul>
                <li>
                    <a href="commandes/liste.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'commandes/liste.php') !== false ? 'active' : ''; ?>">
                        📋 Toutes les commandes
                    </a>
                </li>
                <li>
                    <a href="commandes/liste.php?statut=en+attente" 
                       class="<?php echo (isset($_GET['statut']) && $_GET['statut'] == 'en attente') ? 'active' : ''; ?>">
                        ⏳ En attente
                        <?php
                        $pdo = require '../config/database.php';
                        $stmt = $pdo->query("SELECT COUNT(*) as nb FROM commandes WHERE statut = 'en attente'");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $nb_attente = $result['nb'] ?? 0;
                        
                        if ($nb_attente > 0): ?>
                            <span class="badge"><?php echo $nb_attente; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="commandes/liste.php?statut=en+fabrication" 
                       class="<?php echo (isset($_GET['statut']) && $_GET['statut'] == 'en fabrication') ? 'active' : ''; ?>">
                        🔨 En fabrication
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Fabrication -->
        <div class="menu-section">
            <h3>Atelier</h3>
            <ul>
                <li>
                    <a href="fabrication/suivi.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'fabrication/suivi.php') !== false ? 'active' : ''; ?>">
                        🛠️ Suivi fabrication
                    </a>
                </li>
                <li>
                    <a href="fabrication/planning.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'fabrication/planning.php') !== false ? 'active' : ''; ?>">
                        📅 Planning
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Clients -->
        <div class="menu-section">
            <h3>Clients</h3>
            <ul>
                <li>
                    <a href="clients/liste.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'clients/liste.php') !== false ? 'active' : ''; ?>">
                        👥 Liste des clients
                    </a>
                </li>
                <li>
                    <a href="clients/messages.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'clients/messages.php') !== false ? 'active' : ''; ?>">
                        💬 Messages
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Administration -->
        <div class="menu-section">
            <h3>Administration</h3>
            <ul>
                <li>
                    <a href="parametres.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'parametres.php' ? 'active' : ''; ?>">
                        ⚙️ Paramètres
                    </a>
                </li>
                <li>
                    <a href="../index.php" target="_blank">
                        🌐 Voir le site
                    </a>
                </li>
                <li>
                    <a href="../includes/logout.php">
                        🚪 Déconnexion
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    
    <!-- Statistiques rapides -->
    <div class="sidebar-footer">
        <div class="quick-stats">
            <?php
            // Commandes du jour
            $stmt = $pdo->query("SELECT COUNT(*) as nb FROM commandes WHERE DATE(date_commande) = CURDATE()");
            $commandes_jour = $stmt->fetch(PDO::FETCH_ASSOC)['nb'] ?? 0;
            
            // CA du jour
            $stmt = $pdo->query("SELECT SUM(total) as ca FROM commandes WHERE DATE(date_commande) = CURDATE() AND statut = 'livrée'");
            $ca_jour = $stmt->fetch(PDO::FETCH_ASSOC)['ca'] ?? 0;
            ?>
            
            <div class="quick-stat">
                <span class="stat-label">Commandes aujourd'hui</span>
                <span class="stat-value"><?php echo $commandes_jour; ?></span>
            </div>
            
            <div class="quick-stat">
                <span class="stat-label">CA aujourd'hui</span>
                <span class="stat-value"><?php echo formatPrix($ca_jour); ?></span>
            </div>
        </div>
        
        <div class="sidebar-actions">
            <a href="produits/ajouter.php" class="btn btn-primary btn-block">+ Nouveau produit</a>
            <a href="commandes/liste.php?statut=en+attente" class="btn btn-warning btn-block">
                ⏳ Commandes en attente
            </a>
        </div>
    </div>
</aside>

<style>
    .admin-sidebar {
        width: 250px;
        background: #2c3e50;
        color: white;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        overflow-y: auto;
        transition: transform 0.3s;
        z-index: 99;
    }
    
    @media (max-width: 768px) {
        .admin-sidebar {
            transform: translateX(-100%);
        }
        
        .admin-sidebar.collapsed {
            transform: translateX(0);
        }
    }
    
    .sidebar-header {
        padding: 1.5rem 1rem;
        border-bottom: 1px solid #34495e;
    }
    
    .admin-logo h2 {
        color: #e67e22;
        margin: 0;
        font-size: 1.3rem;
    }
    
    .admin-logo p {
        color: #bdc3c7;
        margin: 0.25rem 0 0 0;
        font-size: 0.9rem;
    }
    
    .admin-menu {
        padding: 1rem 0;
    }
    
    .menu-section {
        margin-bottom: 1.5rem;
    }
    
    .menu-section h3 {
        font-size: 0.8rem;
        text-transform: uppercase;
        color: #95a5a6;
        padding: 0 1rem 0.5rem 1rem;
        margin: 0;
        letter-spacing: 1px;
    }
    
    .admin-menu ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .admin-menu a {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        color: #ecf0f1;
        text-decoration: none;
        transition: all 0.3s;
        font-size: 0.95rem;
        border-left: 3px solid transparent;
    }
    
    .admin-menu a:hover {
        background-color: #34495e;
        color: #e67e22;
    }
    
    .admin-menu a.active {
        background-color: #34495e;
        color: #e67e22;
        border-left: 3px solid #e67e22;
    }
    
    .badge {
        background: #e74c3c;
        color: white;
        padding: 0.1rem 0.5rem;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: bold;
    }
    
    .sidebar-footer {
        padding: 1rem;
        border-top: 1px solid #34495e;
        position: sticky;
        bottom: 0;
        background: #2c3e50;
    }
    
    .quick-stats {
        margin-bottom: 1rem;
    }
    
    .quick-stat {
        background: rgba(255, 255, 255, 0.1);
        padding: 0.5rem;
        border-radius: 4px;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        display: block;
        font-size: 0.8rem;
        color: #bdc3c7;
    }
    
    .stat-value {
        display: block;
        font-size: 1.2rem;
        font-weight: bold;
        color: white;
    }
    
    .btn-block {
        display: block;
        width: 100%;
        margin-bottom: 0.5rem;
        text-align: center;
    }
    
    .btn-warning {
        background: #f39c12;
        color: white;
    }
    
    .btn-warning:hover {
        background: #e67e22;
    }
</style>