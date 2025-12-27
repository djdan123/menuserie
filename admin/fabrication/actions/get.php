<?php
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

redirigerSiNonAdmin();

if (isset($_GET['id'])) {
    $id_fabrication = intval($_GET['id']);
    
    $stmt = $pdo->prepare("SELECT * FROM fabrication WHERE id_fabrication = ?");
    $stmt->execute([$id_fabrication]);
    $fabrication = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($fabrication) {
        echo json_encode(['success' => true, 'fabrication' => $fabrication]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Projet non trouvé']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID non fourni']);
}
?>