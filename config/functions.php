<?php
// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifie si un utilisateur est connecté
function estConnecte(): bool
{
    return !empty($_SESSION['id_user']);
}

// Vérifie si l'utilisateur actuel est admin
function estAdmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Formatage simple pour les prix
function formatPrix($montant): string
{
    return number_format((float)$montant, 2, ',', ' ') . ' €';
}

// Petit utilitaire de debug (désactiver en production)
function debug_print($var)
{
    echo '<pre>' . htmlspecialchars(print_r($var, true)) . '</pre>';
}