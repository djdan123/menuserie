<?php
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

redirigerSiNonAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_fabrication = intval($_POST['id_fabrication']);
    
    // Mettre à jour le statut et la date de fin
    $stmt = $pdo->prepare("UPDATE fabrication SET statut = 'terminé', date_fin = NOW() WHERE id_fabrication = ?");
    
    try {
        $stmt->execute([$id_fabrication]);
        
        // Si c'est pour une commande, vérifier si toutes les fabrications sont terminées
        $stmt = $pdo->prepare("SELECT c.id_commande FROM commandes c
                               JOIN details_commandes dc ON c.id_commande = dc.id_commande
                               JOIN meubles m ON dc.id_meuble = m.id_meuble
                               JOIN fabrication f ON m.id_meuble = f.id_meuble
                               WHERE f.id_fabrication = ?");
        $stmt->execute([$id_fabrication]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($commande) {
            // Vérifier s'il reste des fabrications en cours pour cette commande
            $stmt = $pdo->prepare("SELECT COUNT(*) as nb_en_cours FROM fabrication f
                                   JOIN meubles m ON f.id_meuble = m.id_meuble
                                   JOIN details_commandes dc ON m.id_meuble = dc.id_meuble
                                   WHERE dc.id_commande = ? AND f.statut IN ('en attente', 'en cours')");
            $stmt->execute([$commande['id_commande']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['nb_en_cours'] == 0) {
                // Toutes les fabrications sont terminées, passer la commande en "livrée"
                $stmt = $pdo->prepare("UPDATE commandes SET statut = 'livrée' WHERE id_commande = ?");
                $stmt->execute([$commande['id_commande']]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Fabrication terminée avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
?>