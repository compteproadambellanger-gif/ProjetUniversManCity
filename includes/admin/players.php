<?php
session_start();
require_once __DIR__ . '/../../db.php';

$erreurs = [];

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'staff') {
    $erreurs['acces'] = 'Vous n\'avez pas les droits pour accéder à cette section réservée au staff.';
}

$requete = $pdo->query(
    'SELECT p.id, p.full_name, p.shirt_number, p.position, p.nationality, p.email,
            u.derniere_activite
     FROM players p
     LEFT JOIN users u ON p.user_id = u.id
     ORDER BY p.shirt_number'
);
$joueurs = $requete->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="titre-page">Administration des joueurs</h1>
<p class="sous-titre-page">Zone réservée au staff du club.</p>

<?php if (!empty($erreurs['acces'])): ?>
    <div class="erreur-globale" style="max-width:500px;">
        <strong>Accès refusé</strong>
        <p><?php echo htmlspecialchars($erreurs['acces'], ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="../../dashboard.php" class="bouton bouton-secondaire" style="margin-top:1rem;">
            Retour au tableau de bord
        </a>
    </div>
<?php else: ?>

    <div style="margin-bottom:1.5rem;">
        <a href="player_create.php" class="bouton bouton-principal">
            + Ajouter un joueur
        </a>
    </div>

    <div class="tableau-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nom</th>
                    <th>Poste</th>
                    <th>Nationalité</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($joueurs as $joueur): ?>
                    <tr>
                        <td><?php echo (int)$joueur['shirt_number']; ?></td>
                        <td><?php echo htmlspecialchars($joueur['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($joueur['position'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($joueur['nationality'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php
                            if (!empty($joueur['derniere_activite'])) {
                                $timestamp_activite = strtotime($joueur['derniere_activite']);
                                $minutes_ecoulees = (time() - $timestamp_activite) / 60;

                                if ($minutes_ecoulees <= 5) {
                                    echo '<span style="display:inline-flex;align-items:center;gap:6px;">
                                                <span style="display:inline-block;width:10px;height:10px;
                                                border-radius:50%;background:#2ecc71;
                                                box-shadow:0 0 6px #2ecc71;"></span>
                                                <span style="color:#2ecc71;font-size:0.85rem;">En ligne</span>
                                              </span>';
                                } else {
                                    echo '<span style="display:inline-flex;align-items:center;gap:6px;">
                                                <span style="display:inline-block;width:10px;height:10px;
                                                border-radius:50%;background:#e74c3c;
                                                box-shadow:0 0 6px #e74c3c;"></span>
                                                <span style="color:#e74c3c;font-size:0.85rem;">Hors ligne</span>
                                              </span>';
                                }
                            } else {
                                echo '<span style="display:inline-flex;align-items:center;gap:6px;">
                                            <span style="display:inline-block;width:10px;height:10px;
                                            border-radius:50%;background:#95a5a6;"></span>
                                            <span style="color:#95a5a6;font-size:0.85rem;">Jamais connecté</span>
                                          </span>';
                            }
                            ?>
                        </td>
                        <td style="display:flex; gap:0.5rem;">
                            <a href="player_edit.php?id=<?php echo (int)$joueur['id']; ?>"
                                class="bouton bouton-secondaire"
                                style="padding:0.3rem 0.8rem; font-size:0.8rem;">
                                Modifier
                            </a>
                            <a href="player_delete.php?id=<?php echo (int)$joueur['id']; ?>"
                                class="bouton bouton-danger"
                                style="padding:0.3rem 0.8rem; font-size:0.8rem;"
                                onclick="return confirm('Supprimer ce joueur ? Cette action est définitive.');">
                                Supprimer
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
