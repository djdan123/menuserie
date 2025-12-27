<?php
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

redirigerSiNonAdmin();

if (isset($_GET['id'])) {
    $message_id = intval($_GET['id']);
    
    $stmt = $pdo->prepare("UPDATE messages SET lu = 1 WHERE id_message = ?");
    $stmt->execute([$message_id]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'ID non fourni']);
}
?>