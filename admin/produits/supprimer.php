<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!estAdmin()) {
    header('Location: ../login.php');
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM meubles WHERE id_meuble = ?');
    $stmt->execute([$id]);
}
header('Location: liste.php');
exit();
