<?php
session_start();
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'staff') {
    $erreur_acces = 'Vous n\'avez pas les droits pour accéder à cette section réservée au staff.';
} else {
    $erreur_acces = '';
}

$matchs = [];
if ($erreur_acces === '') {
    $requete = $pdo->query(
        'SELECT id, opponent, competition, match_date, home_away, goals_city, goals_opponent
         FROM matchs
         ORDER BY match_date DESC'
    );
    $matchs = $requete->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="titre-page">Administration des matchs</h1>
<p class="sous-titre-page">Zone réservée au staff du club.</p>

<?php if ($erreur_acces !== ''): ?>
    <div class="erreur-globale" style="max-width:500px;">
        <strong>Accès refusé</strong>
        <p><?php echo htmlspecialchars($erreur_acces, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="../../dashboard.php" class="bouton bouton-secondaire" style="margin-top:1rem;">
            Retour au tableau de bord
        </a>
    </div>
<?php else: ?>

    <div style="margin-bottom:1.5rem;">
        <a href="match_create.php" class="bouton bouton-principal">
            + Ajouter un match
        </a>
    </div>

    <div class="tableau-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Compétition</th>
                    <th>Lieu</th>
                    <th>Adversaire</th>
                    <th>Score</th>
                    <th>Résultat</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matchs as $match): ?>
                    <?php
                    if ((int)$match['goals_city'] > (int)$match['goals_opponent']) {
                        $couleur_resultat = '#2ecc71';
                        $resultat = 'Victoire';
                    } elseif ((int)$match['goals_city'] < (int)$match['goals_opponent']) {
                        $couleur_resultat = '#e74c3c';
                        $resultat = 'Défaite';
                    } else {
                        $couleur_resultat = 'var(--or)';
                        $resultat = 'Nul';
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($match['match_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($match['competition'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $match['home_away'] === 'HOME' ? 'Domicile' : 'Extérieur'; ?></td>
                        <td><?php echo htmlspecialchars($match['opponent'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="font-weight:700; color:var(--bleu-city); text-align:center;">
                            <?php echo (int)$match['goals_city'] . ' - ' . (int)$match['goals_opponent']; ?>
                        </td>
                        <td>
                            <span style="
                                    display:inline-block;
                                    padding:0.25rem 0.8rem;
                                    border-radius:20px;
                                    font-size:0.8rem;
                                    font-weight:600;
                                    background:<?php echo $couleur_resultat; ?>22;
                                    color:<?php echo $couleur_resultat; ?>;
                                    border:1px solid <?php echo $couleur_resultat; ?>44;">
                                <?php echo $resultat; ?>
                            </span>
                        </td>
                        <td style="display:flex; gap:0.5rem;">
                            <a href="match_edit.php?id=<?php echo (int)$match['id']; ?>"
                                class="bouton bouton-secondaire"
                                style="padding:0.3rem 0.8rem; font-size:0.8rem;">
                                Modifier
                            </a>
                            <a href="match_delete.php?id=<?php echo (int)$match['id']; ?>"
                                class="bouton bouton-danger"
                                style="padding:0.3rem 0.8rem; font-size:0.8rem;"
                                onclick="return confirm('Supprimer ce match ? Cette action est définitive.');">
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
