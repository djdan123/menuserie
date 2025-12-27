<?php
require_once '../../../config/database.php';
require_once '../../../config/functions.php';

redirigerSiNonAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id']);
    $sujet = securiser($_POST['sujet']);
    $contenu = securiser($_POST['contenu']);
    $type = securiser($_POST['type']);
    $copie = isset($_POST['copie']) ? 1 : 0;
    
    // Vérifier le client
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_user = ? AND role = 'client'");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Client non trouvé']);
        exit();
    }
    
    // Enregistrer le message
    $stmt = $pdo->prepare("INSERT INTO messages (id_client, sujet, contenu, type, date_envoi, lu, repondu, id_admin) 
                           VALUES (?, ?, ?, ?, NOW(), 0, 0, ?)");
    
    try {
        $stmt->execute([$client_id, $sujet, $contenu, $type, $_SESSION['user_id']]);
        $id_message = $pdo->lastInsertId();
        
        // Envoyer l'email (simulation)
        $email_sent = false;
        $admin_email = $_SESSION['email'] ?? 'admin@menuiserie.com';
        
        if ($copie) {
            // Envoyer une copie à l'admin
            $email_sent = true;
        }
        
        // Envoyer au client
        $email_sent = true;
        
        // Créer une notification
        $stmt = $pdo->prepare("INSERT INTO notifications (id_user, type, titre, contenu, date_creation, lu) 
                               VALUES (?, 'message', 'Nouveau message', ?, NOW(), 0)");
        $stmt->execute([$client_id, "Vous avez reçu un message de l'administration"]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Message envoyé avec succès',
            'email_sent' => $email_sent
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
?>