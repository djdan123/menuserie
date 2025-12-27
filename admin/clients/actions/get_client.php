<?php
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

redirigerSiNonAdmin();

if (isset($_GET['id'])) {
    $client_id = intval($_GET['id']);
    
    // Récupérer les infos client
    $stmt = $pdo->prepare("SELECT u.*, 
                          (SELECT COUNT(*) FROM commandes c WHERE c.id_user = u.id_user) as nb_commandes,
                          (SELECT SUM(total) FROM commandes c WHERE c.id_user = u.id_user AND c.statut = 'livrée') as total_achats
                          FROM utilisateurs u 
                          WHERE u.id_user = ? AND u.role = 'client'");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Client non trouvé']);
        exit();
    }
    
    // Calculer le panier moyen
    $client['panier_moyen'] = ($client['nb_commandes'] > 0) ? 
        $client['total_achats'] / $client['nb_commandes'] : 0;
    
    // Récupérer les commandes
    $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id_user = ? ORDER BY date_commande DESC LIMIT 10");
    $stmt->execute([$client_id]);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'client' => $client,
        'commandes' => $commandes
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'ID non fourni']);
}
?>