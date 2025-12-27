<?php
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

redirigerSiNonAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_meuble = intval($_POST['id_meuble']);
    $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $id_user = !empty($_POST['id_user']) ? intval($_POST['id_user']) : null;
    $priorite = securiser($_POST['priorite']);
    $notes = securiser($_POST['notes']);
    
    // Vérifier si le meuble existe
    $stmt = $pdo->prepare("SELECT * FROM meubles WHERE id_meuble = ?");
    $stmt->execute([$id_meuble]);
    $meuble = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$meuble) {
        echo json_encode(['success' => false, 'message' => 'Meuble non trouvé']);
        exit();
    }
    
    // Vérifier les dates
    if ($date_debut && $date_fin && $date_fin < $date_debut) {
        echo json_encode(['success' => false, 'message' => 'La date de fin ne peut pas être antérieure à la date de début']);
        exit();
    }
    
    // Insérer la fabrication
    $stmt = $pdo->prepare("INSERT INTO fabrication (id_meuble, id_user, date_debut, date_fin, statut, priorite, notes) 
                           VALUES (?, ?, ?, ?, 'en attente', ?, ?)");
    
    try {
        $stmt->execute([$id_meuble, $id_user, $date_debut, $date_fin, $priorite, $notes]);
        $id_fabrication = $pdo->lastInsertId();
        
        echo json_encode(['success' => true, 'id' => $id_fabrication, 'message' => 'Projet créé avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
?>