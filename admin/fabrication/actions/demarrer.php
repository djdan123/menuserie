<?php
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

redirigerSiNonAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_fabrication = intval($_POST['id_fabrication']);
    $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $id_user = !empty($_POST['id_user']) ? intval($_POST['id_user']) : null;
    $statut = securiser($_POST['statut']);
    $priorite = securiser($_POST['priorite']);
    $notes = securiser($_POST['notes']);
    
    // Vérifier les dates
    if ($date_debut && $date_fin && $date_fin < $date_debut) {
        echo json_encode(['success' => false, 'message' => 'La date de fin ne peut pas être antérieure à la date de début']);
        exit();
    }
    
    // Mettre à jour
    $stmt = $pdo->prepare("UPDATE fabrication SET 
                          date_debut = ?, date_fin = ?, id_user = ?, 
                          statut = ?, priorite = ?, notes = ?
                          WHERE id_fabrication = ?");
    
    try {
        $stmt->execute([$date_debut, $date_fin, $id_user, $statut, $priorite, $notes, $id_fabrication]);
        
        // Si statut = terminé, vérifier si c'est pour une commande
        if ($statut == 'terminé') {
            // Vérifier si c'est pour une commande client
            $stmt = $pdo->prepare("SELECT c.id_commande FROM commandes c
                                   JOIN details_commandes dc ON c.id_commande = dc.id_commande
                                   JOIN meubles m ON dc.id_meuble = m.id_meuble
                                   JOIN fabrication f ON m.id_meuble = f.id_meuble
                                   WHERE f.id_fabrication = ? AND c.statut = 'en fabrication'");
            $stmt->execute([$id_fabrication]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($commande) {
                // Marquer toutes les fabrications de cette commande comme terminées
                $stmt = $pdo->prepare("UPDATE fabrication f
                                       JOIN meubles m ON f.id_meuble = m.id_meuble
                                       JOIN details_commandes dc ON m.id_meuble = dc.id_meuble
                                       SET f.statut = 'terminé'
                                       WHERE dc.id_commande = ? AND f.statut = 'en cours'");
                $stmt->execute([$commande['id_commande']]);
                
                // Vérifier si toutes les fabrications de la commande sont terminées
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
        }
        
        echo json_encode(['success' => true, 'message' => 'Projet mis à jour avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
?>