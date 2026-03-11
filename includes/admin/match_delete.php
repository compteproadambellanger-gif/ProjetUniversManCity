<?php
session_start();
require_once __DIR__ . '/../../db.php';

// Vérifier connexion + rôle staff
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'staff') {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

// Récupérer l'id du match
$id_match = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_match <= 0) {
    header('Location: matchs.php');
    exit;
}

// Vérifier que le match existe
$requete = $pdo->prepare('SELECT id FROM matchs WHERE id = ?');
$requete->execute([$id_match]);
$match = $requete->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    header('Location: matchs.php');
    exit;
}

// Supprimer
$requete = $pdo->prepare('DELETE FROM matchs WHERE id = ?');
$requete->execute([$id_match]);

header('Location: matchs.php');
exit;
