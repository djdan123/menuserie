<?php
// Vérifier si l'utilisateur est admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../client/login.php');
    exit();
}
?>

<header class="admin-header">
    <div class="header-left">
        <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
        <h2>Administration</h2>
    </div>
    
    <div class="header-right">
        <div class="admin-info">
            <span>Connecté en tant que <strong><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></strong></span>
            <a href="../includes/logout.php" class="btn btn-sm btn-secondary">Déconnexion</a>
        </div>
        
        <div class="notifications">
            <button class="btn-notification" onclick="toggleNotifications()">
                🔔
                <?php
                // Compter les commandes en attente
                $pdo = require '../config/database.php';
                $stmt = $pdo->query("SELECT COUNT(*) as nb FROM commandes WHERE statut = 'en attente'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $nb_notifications = $result['nb'] ?? 0;
                
                if ($nb_notifications > 0): ?>
                    <span class="notification-count"><?php echo $nb_notifications; ?></span>
                <?php endif; ?>
            </button>
            
            <div id="notificationPanel" class="notification-panel">
                <div class="notification-header">
                    <h4>Notifications</h4>
                    <button onclick="markAllAsRead()">Tout marquer comme lu</button>
                </div>
                <div class="notification-list">
                    <?php
                    $stmt = $pdo->query("SELECT c.*, u.prenom, u.nom 
                                        FROM commandes c 
                                        JOIN utilisateurs u ON c.id_user = u.id_user
                                        WHERE c.statut = 'en attente' 
                                        ORDER BY c.date_commande DESC
                                        LIMIT 5");
                    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($notifications)):
                        foreach ($notifications as $notif): ?>
                            <div class="notification-item">
                                <div class="notification-content">
                                    <strong>Nouvelle commande #<?php echo $notif['id_commande']; ?></strong>
                                    <p><?php echo htmlspecialchars($notif['prenom'] . ' ' . $notif['nom']); ?> - <?php echo formatPrix($notif['total']); ?></p>
                                    <small><?php echo date('d/m/Y H:i', strtotime($notif['date_commande'])); ?></small>
                                </div>
                                <a href="../admin/commandes/details.php?id=<?php echo $notif['id_commande']; ?>" class="btn-notification-action">Voir</a>
                            </div>
                        <?php endforeach;
                    else: ?>
                        <div class="notification-empty">
                            <p>Aucune notification</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="notification-footer">
                    <a href="../admin/commandes/liste.php?statut=en+attente">Voir toutes les commandes en attente</a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.admin-sidebar');
        sidebar.classList.toggle('collapsed');
    }
    
    function toggleNotifications() {
        const panel = document.getElementById('notificationPanel');
        panel.classList.toggle('show');
    }
    
    function markAllAsRead() {
        // Ici, on pourrait marquer les notifications comme lues dans la base
        document.querySelectorAll('.notification-item').forEach(item => {
            item.style.opacity = '0.6';
        });
        document.querySelector('.notification-count').style.display = 'none';
    }
    
    // Fermer le panel en cliquant à l'extérieur
    document.addEventListener('click', function(event) {
        const panel = document.getElementById('notificationPanel');
        const btn = document.querySelector('.btn-notification');
        
        if (!panel.contains(event.target) && !btn.contains(event.target)) {
            panel.classList.remove('show');
        }
    });
</script>

<style>
    .admin-header {
        background: white;
        padding: 0.75rem 1.5rem;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .header-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .menu-toggle {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #2c3e50;
        padding: 0.5rem;
        border-radius: 4px;
        transition: background-color 0.3s;
    }
    
    .menu-toggle:hover {
        background-color: #f8f9fa;
    }
    
    .header-right {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    
    .admin-info {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.25rem;
    }
    
    .admin-info span {
        font-size: 0.9rem;
        color: #666;
    }
    
    .btn-sm {
        padding: 0.25rem 0.75rem;
        font-size: 0.85rem;
    }
    
    .notifications {
        position: relative;
    }
    
    .btn-notification {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        position: relative;
        padding: 0.5rem;
        border-radius: 50%;
        transition: background-color 0.3s;
    }
    
    .btn-notification:hover {
        background-color: #f8f9fa;
    }
    
    .notification-count {
        position: absolute;
        top: 0;
        right: 0;
        background: #e74c3c;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .notification-panel {
        position: absolute;
        top: 100%;
        right: 0;
        width: 350px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        display: none;
        z-index: 1000;
        margin-top: 0.5rem;
    }
    
    .notification-panel.show {
        display: block;
    }
    
    .notification-header {
        padding: 1rem;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .notification-header h4 {
        margin: 0;
        color: #2c3e50;
    }
    
    .notification-header button {
        background: none;
        border: none;
        color: #3498db;
        cursor: pointer;
        font-size: 0.9rem;
    }
    
    .notification-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .notification-item {
        padding: 1rem;
        border-bottom: 1px solid #f8f9fa;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background-color 0.3s;
    }
    
    .notification-item:hover {
        background-color: #f8f9fa;
    }
    
    .notification-content {
        flex: 1;
    }
    
    .notification-content strong {
        display: block;
        color: #2c3e50;
        margin-bottom: 0.25rem;
    }
    
    .notification-content p {
        margin: 0.25rem 0;
        color: #666;
        font-size: 0.9rem;
    }
    
    .notification-content small {
        color: #999;
        font-size: 0.8rem;
    }
    
    .btn-notification-action {
        background: #3498db;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        text-decoration: none;
        font-size: 0.85rem;
        margin-left: 0.5rem;
    }
    
    .notification-empty {
        padding: 2rem;
        text-align: center;
        color: #999;
    }
    
    .notification-footer {
        padding: 1rem;
        border-top: 1px solid #eee;
        text-align: center;
    }
    
    .notification-footer a {
        color: #3498db;
        text-decoration: none;
        font-size: 0.9rem;
    }
    
    @media (max-width: 768px) {
        .admin-header {
            padding: 0.5rem 1rem;
            flex-direction: column;
            gap: 1rem;
        }
        
        .header-left, .header-right {
            width: 100%;
            justify-content: space-between;
        }
        
        .notification-panel {
            width: 300px;
            right: -50px;
        }
    }
</style>