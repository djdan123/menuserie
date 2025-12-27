<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Vérifier si l'utilisateur est connecté
redirigerSiNonConnecte('../../client/login.php');

// Vérifier si le panier n'est pas vide
if (empty($_SESSION['panier'])) {
    header('Location: panier.php');
    exit();
}

// Traitement de la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Calculer le total
        $total = 0;
        foreach ($_SESSION['panier'] as $id_meuble => $item) {
            $stmt = $pdo->prepare("SELECT prix_vente, quantite_stock FROM meubles WHERE id_meuble = ?");
            $stmt->execute([$id_meuble]);
            $produit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$produit || $produit['quantite_stock'] < $item['quantite']) {
                throw new Exception("Produit $id_meuble non disponible en quantité suffisante");
            }
            
            $total += $produit['prix_vente'] * $item['quantite'];
        }
        
        // Créer la commande
        $stmt = $pdo->prepare("INSERT INTO commandes (id_user, total) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $total]);
        $id_commande = $pdo->lastInsertId();
        
        // Ajouter les détails de commande et mettre à jour le stock
        foreach ($_SESSION['panier'] as $id_meuble => $item) {
            // Récupérer le prix actuel
            $stmt = $pdo->prepare("SELECT prix_vente FROM meubles WHERE id_meuble = ?");
            $stmt->execute([$id_meuble]);
            $prix = $stmt->fetchColumn();
            
            // Ajouter le détail de commande
            $stmt = $pdo->prepare("INSERT INTO details_commandes (id_commande, id_meuble, quantite, prix) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_commande, $id_meuble, $item['quantite'], $prix]);
            
            // Mettre à jour le stock
            $stmt = $pdo->prepare("UPDATE meubles SET quantite_stock = quantite_stock - ? WHERE id_meuble = ?");
            $stmt->execute([$item['quantite'], $id_meuble]);
        }
        
        $pdo->commit();
        
        // Vider le panier
        unset($_SESSION['panier']);
        
        flashMessage("Commande passée avec succès ! Numéro de commande : #$id_commande", "success");
        header('Location: ../commandes/suivi.php?id=' . $id_commande);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        flashMessage("Erreur lors de la commande : " . $e->getMessage(), "error");
    }
}

// Récupérer les informations du client
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_user = ?");
$stmt->execute([$_SESSION['user_id']]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les détails du panier
$panier_details = [];
$total = 0;

foreach ($_SESSION['panier'] as $id_meuble => $item) {
    $stmt = $pdo->prepare("SELECT * FROM meubles WHERE id_meuble = ?");
    $stmt->execute([$id_meuble]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($produit) {
        $sous_total = $item['quantite'] * $produit['prix_vente'];
        $panier_details[] = [
            'nom' => $produit['nom'],
            'prix' => $produit['prix_vente'],
            'quantite' => $item['quantite'],
            'sous_total' => $sous_total
        ];
        $total += $sous_total;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser la commande - Menuiserie Bois Noble</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="container">
        <h1>Finaliser la commande</h1>
        
        <?php echo getFlashMessage(); ?>
        
        <div class="commande-container">
            <div class="commande-etapes">
                <div class="etape active">
                    <span class="etape-num">1</span>
                    <span class="etape-text">Panier</span>
                </div>
                <div class="etape active">
                    <span class="etape-num">2</span>
                    <span class="etape-text">Commande</span>
                </div>
                <div class="etape">
                    <span class="etape-num">3</span>
                    <span class="etape-text">Paiement</span>
                </div>
                <div class="etape">
                    <span class="etape-num">4</span>
                    <span class="etape-text">Confirmation</span>
                </div>
            </div>
            
            <form method="POST" class="commande-form">
                <div class="commande-grid">
                    <!-- Informations client -->
                    <div class="commande-section">
                        <h2>Informations personnelles</h2>
                        <div class="info-client">
                            <p><strong>Nom :</strong> <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?></p>
                            <p><strong>Email :</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                            <p><strong>Date d'inscription :</strong> <?php echo date('d/m/Y', strtotime($client['date_inscription'])); ?></p>
                        </div>
                        
                        <h2>Adresse de livraison</h2>
                        <div class="form-group">
                            <label for="adresse">Adresse complète *</label>
                            <textarea id="adresse" name="adresse" required rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="telephone">Téléphone *</label>
                            <input type="tel" id="telephone" name="telephone" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes de livraison (optionnel)</label>
                            <textarea id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <!-- Récapitulatif -->
                    <div class="commande-section">
                        <h2>Récapitulatif de la commande</h2>
                        
                        <div class="recap-produits">
                            <?php foreach ($panier_details as $item): ?>
                                <div class="recap-produit">
                                    <div class="recap-produit-info">
                                        <h4><?php echo htmlspecialchars($item['nom']); ?></h4>
                                        <p>Quantité : <?php echo $item['quantite']; ?></p>
                                    </div>
                                    <div class="recap-produit-prix">
                                        <?php echo formatPrix($item['sous_total']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="recap-total">
                            <div class="recap-ligne">
                                <span>Sous-total :</span>
                                <span><?php echo formatPrix($total); ?></span>
                            </div>
                            <div class="recap-ligne">
                                <span>Frais de livraison :</span>
                                <span><?php echo formatPrix(0); ?> (offerts)</span>
                            </div>
                            <div class="recap-ligne total">
                                <span><strong>Total TTC :</strong></span>
                                <span><strong><?php echo formatPrix($total); ?></strong></span>
                            </div>
                        </div>
                        
                        <div class="conditions">
                            <label>
                                <input type="checkbox" name="conditions" required>
                                J'accepte les <a href="#">conditions générales de vente</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            Confirmer la commande
                        </button>
                        
                        <p class="text-center">
                            <small>Vous serez redirigé vers notre partenaire de paiement sécurisé</small>
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    
    <script>
        // Validation du formulaire
        document.querySelector('.commande-form').addEventListener('submit', function(e) {
            const telephone = document.getElementById('telephone').value;
            const adresse = document.getElementById('adresse').value;
            
            if (!telephone.match(/^[0-9]{10}$/)) {
                e.preventDefault();
                alert('Veuillez entrer un numéro de téléphone valide (10 chiffres)');
                return false;
            }
            
            if (adresse.trim().length < 10) {
                e.preventDefault();
                alert('Veuillez entrer une adresse complète');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>