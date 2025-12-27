<?php
require_once '../config/database.php';
require_once '../config/functions.php';

redirigerSiNonAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = securiser($_POST['type']);
    $update = isset($_POST['update']) ? true : false;
    $skip_errors = isset($_POST['skip_errors']) ? true : false;
    
    if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erreur de téléchargement']);
        exit();
    }
    
    $fichier = $_FILES['fichier']['tmp_name'];
    $resultat = [
        'success' => true,
        'imported' => 0,
        'updated' => 0,
        'errors' => [],
        'message' => ''
    ];
    
    try {
        // Lire le fichier CSV
        $handle = fopen($fichier, 'r');
        if (!$handle) {
            throw new Exception('Impossible d\'ouvrir le fichier');
        }
        
        // Lire les en-têtes
        $headers = fgetcsv($handle, 0, ';');
        
        // Traiter selon le type
        switch ($type) {
            case 'produits':
                $resultat = importerProduits($handle, $headers, $update, $skip_errors, $pdo);
                break;
                
            case 'clients':
                $resultat = importerClients($handle, $headers, $update, $skip_errors, $pdo);
                break;
                
            case 'categories':
                $resultat = importerCategories($handle, $headers, $update, $skip_errors, $pdo);
                break;
                
            default:
                throw new Exception('Type d\'import non supporté');
        }
        
        fclose($handle);
        
    } catch (Exception $e) {
        $resultat['success'] = false;
        $resultat['message'] = $e->getMessage();
    }
    
    echo json_encode($resultat);
    exit();
}

function importerProduits($handle, $headers, $update, $skip_errors, $pdo) {
    $resultat = [
        'imported' => 0,
        'updated' => 0,
        'errors' => []
    ];
    
    $pdo->beginTransaction();
    
    try {
        $ligne = 1;
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $ligne++;
            
            // Associer les données aux en-têtes
            $row = array_combine($headers, $data);
            
            // Validation des données
            if (empty($row['nom']) || empty($row['prix_vente'])) {
                if (!$skip_errors) {
                    throw new Exception("Ligne $ligne: Nom et prix sont obligatoires");
                }
                $resultat['errors'][] = "Ligne $ligne: Données incomplètes";
                continue;
            }
            
            // Vérifier si le produit existe
            $stmt = $pdo->prepare("SELECT id_meuble FROM meubles WHERE nom = ?");
            $stmt->execute([$row['nom']]);
            $existant = $stmt->fetch();
            
            if ($existant && $update) {
                // Mettre à jour
                $sql = "UPDATE meubles SET 
                        id_categorie = :categorie,
                        description = :description,
                        bois_type = :bois_type,
                        longueur = :longueur,
                        largeur = :largeur,
                        hauteur = :hauteur,
                        cout_fabrication = :cout,
                        prix_vente = :prix,
                        quantite_stock = :stock
                        WHERE id_meuble = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':categorie' => $row['id_categorie'] ?? null,
                    ':description' => $row['description'] ?? '',
                    ':bois_type' => $row['bois_type'] ?? '',
                    ':longueur' => $row['longueur'] ?? 0,
                    ':largeur' => $row['largeur'] ?? 0,
                    ':hauteur' => $row['hauteur'] ?? 0,
                    ':cout' => $row['cout_fabrication'] ?? 0,
                    ':prix' => $row['prix_vente'],
                    ':stock' => $row['quantite_stock'] ?? 0,
                    ':id' => $existant['id_meuble']
                ]);
                
                $resultat['updated']++;
            } else {
                // Insérer
                $sql = "INSERT INTO meubles (id_categorie, nom, description, bois_type, 
                        longueur, largeur, hauteur, cout_fabrication, prix_vente, quantite_stock) 
                        VALUES (:categorie, :nom, :description, :bois_type, 
                        :longueur, :largeur, :hauteur, :cout, :prix, :stock)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':categorie' => $row['id_categorie'] ?? null,
                    ':nom' => $row['nom'],
                    ':description' => $row['description'] ?? '',
                    ':bois_type' => $row['bois_type'] ?? '',
                    ':longueur' => $row['longueur'] ?? 0,
                    ':largeur' => $row['largeur'] ?? 0,
                    ':hauteur' => $row['hauteur'] ?? 0,
                    ':cout' => $row['cout_fabrication'] ?? 0,
                    ':prix' => $row['prix_vente'],
                    ':stock' => $row['quantite_stock'] ?? 0
                ]);
                
                // Créer une entrée de fabrication
                $id_meuble = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO fabrication (id_meuble, statut) VALUES (?, 'en attente')");
                $stmt->execute([$id_meuble]);
                
                $resultat['imported']++;
            }
        }
        
        $pdo->commit();
        $resultat['success'] = true;
        $resultat['message'] = "Import réussi : {$resultat['imported']} ajoutés, {$resultat['updated']} mis à jour";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $resultat['success'] = false;
        $resultat['message'] = $e->getMessage();
    }
    
    return $resultat;
}

function importerClients($handle, $headers, $update, $skip_errors, $pdo) {
    $resultat = [
        'imported' => 0,
        'updated' => 0,
        'errors' => []
    ];
    
    $pdo->beginTransaction();
    
    try {
        $ligne = 1;
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $ligne++;
            
            $row = array_combine($headers, $data);
            
            // Validation
            if (empty($row['email']) || empty($row['nom']) || empty($row['prenom'])) {
                if (!$skip_errors) {
                    throw new Exception("Ligne $ligne: Email, nom et prénom sont obligatoires");
                }
                $resultat['errors'][] = "Ligne $ligne: Données incomplètes";
                continue;
            }
            
            // Vérifier l'email
            if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                if (!$skip_errors) {
                    throw new Exception("Ligne $ligne: Email invalide");
                }
                $resultat['errors'][] = "Ligne $ligne: Email invalide";
                continue;
            }
            
            // Vérifier si le client existe
            $stmt = $pdo->prepare("SELECT id_user FROM utilisateurs WHERE email = ?");
            $stmt->execute([$row['email']]);
            $existant = $stmt->fetch();
            
            if ($existant && $update) {
                // Mettre à jour
                $sql = "UPDATE utilisateurs SET 
                        nom = :nom,
                        prenom = :prenom,
                        password = :password,
                        date_inscription = :date
                        WHERE id_user = :id";
                
                $password = !empty($row['password']) ? password_hash($row['password'], PASSWORD_DEFAULT) : '';
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nom' => $row['nom'],
                    ':prenom' => $row['prenom'],
                    ':password' => $password,
                    ':date' => $row['date_inscription'] ?? date('Y-m-d H:i:s'),
                    ':id' => $existant['id_user']
                ]);
                
                $resultat['updated']++;
            } else {
                // Insérer
                $password = !empty($row['password']) ? 
                    password_hash($row['password'], PASSWORD_DEFAULT) : 
                    password_hash('password123', PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO utilisateurs (nom, prenom, email, password, role, date_inscription) 
                        VALUES (:nom, :prenom, :email, :password, 'client', :date)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nom' => $row['nom'],
                    ':prenom' => $row['prenom'],
                    ':email' => $row['email'],
                    ':password' => $password,
                    ':date' => $row['date_inscription'] ?? date('Y-m-d H:i:s')
                ]);
                
                $resultat['imported']++;
            }
        }
        
        $pdo->commit();
        $resultat['success'] = true;
        $resultat['message'] = "Import réussi : {$resultat['imported']} clients ajoutés, {$resultat['updated']} mis à jour";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $resultat['success'] = false;
        $resultat['message'] = $e->getMessage();
    }
    
    return $resultat;
}
?>