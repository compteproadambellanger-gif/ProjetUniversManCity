<?php
session_start();
require_once __DIR__ . '/../../db.php';

// 1) Vérifier connexion + rôle staff
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'staff') {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

// 2) Récupérer l'ID du joueur
$playerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($playerId <= 0) {
    header('Location: players.php');
    exit;
}

// 3) Optionnel : vérifier que le joueur existe
$stmt = $pdo->prepare('SELECT id, full_name FROM players WHERE id = ?');
$stmt->execute([$playerId]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    header('Location: players.php');
    exit;
}

// 4) Supprimer le joueur
$stmt = $pdo->prepare('DELETE FROM players WHERE id = ?');
$stmt->execute([$playerId]);

// 5) Redirection vers la liste admin
header('Location: players.php');
exit;
