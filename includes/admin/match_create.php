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

$adversaire = '';
$competition = '';
$date_match = '';
$lieu = '';
$buts_city = '';
$buts_adverse = '';
$youtube_url = '';
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erreur_acces === '') {
    $adversaire = trim($_POST['adversaire'] ?? '');
    $competition = trim($_POST['competition'] ?? '');
    $date_match = trim($_POST['date_match'] ?? '');
    $lieu = $_POST['lieu'] ?? '';
    $buts_city = trim($_POST['buts_city'] ?? '0');
    $buts_adverse = trim($_POST['buts_adverse'] ?? '0');
    $youtube_url = trim($_POST['youtube_url'] ?? '');

    if ($adversaire === '') {
        $erreurs['adversaire'] = 'Le nom de l\'adversaire est obligatoire.';
    }
    if ($competition === '') {
        $erreurs['competition'] = 'La compétition est obligatoire.';
    }
    if ($date_match === '') {
        $erreurs['date_match'] = 'La date du match est obligatoire.';
    }
    if (!in_array($lieu, ['HOME', 'AWAY'], true)) {
        $erreurs['lieu'] = 'Le lieu du match est invalide.';
    }
    if ($buts_city === '' || !ctype_digit($buts_city)) {
        $erreurs['buts_city'] = 'Les buts de City doivent être un entier.';
    }
    if ($buts_adverse === '' || !ctype_digit($buts_adverse)) {
        $erreurs['buts_adverse'] = 'Les buts de l\'adversaire doivent être un entier.';
    }

    if (empty($erreurs)) {
        $requete = $pdo->prepare(
            'INSERT INTO matchs (opponent, competition, match_date, home_away, goals_city, goals_opponent, youtube_url)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $requete->execute([
            $adversaire,
            $competition,
            $date_match,
            $lieu,
            (int)$buts_city,
            (int)$buts_adverse,
            $youtube_url !== '' ? $youtube_url : null
        ]);

        header('Location: matchs.php');
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="titre-page">Ajouter un match</h1>
<p class="sous-titre-page">Zone réservée au staff du club.</p>

<?php if ($erreur_acces !== ''): ?>
    <div class="erreur-globale" style="max-width:500px; margin: 0 auto;">
        <strong>Accès refusé</strong>
        <p><?php echo htmlspecialchars($erreur_acces, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="../../dashboard.php" class="bouton bouton-secondaire" style="margin-top:1rem;">
            Retour au tableau de bord
        </a>
    </div>
<?php else: ?>

    <?php if (!empty($erreurs)): ?>
        <div class="erreur-globale" style="max-width:500px; margin: 0 auto 1rem auto;">
            Merci de corriger les erreurs ci-dessous.
        </div>
    <?php endif; ?>

    <div style="
            max-width:500px;
            margin: 0 auto;
            background:rgba(26,43,74,0.6);
            border:1px solid rgba(108,171,221,0.2);
            border-radius:16px;
            padding:2rem;
            backdrop-filter:blur(10px);">

        <form action="match_create.php" method="post" class="formulaire-groupe">

            <div class="formulaire-champ">
<label for="adversaire">Adversaire</label>
                <input type="text" id="adversaire" name="adversaire"
                    placeholder="Ex : Arsenal"
                    value="<?php echo htmlspecialchars($adversaire, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (!empty($erreurs['adversaire'])): ?>
                    <span class="erreur-champ">
                        <?php echo htmlspecialchars($erreurs['adversaire'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="formulaire-champ">
<label for="competition">Compétition</label>
                <input type="text" id="competition" name="competition"
                    placeholder="Ex : Premier League"
                    value="<?php echo htmlspecialchars($competition, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (!empty($erreurs['competition'])): ?>
                    <span class="erreur-champ">
                        <?php echo htmlspecialchars($erreurs['competition'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="formulaire-champ">
<label for="date_match">Date du match</label>
                <input type="date" id="date_match" name="date_match"
                    value="<?php echo htmlspecialchars($date_match, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (!empty($erreurs['date_match'])): ?>
                    <span class="erreur-champ">
                        <?php echo htmlspecialchars($erreurs['date_match'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="formulaire-champ">
<label for="lieu">Lieu</label>
                <select id="lieu" name="lieu">
                    <option value="">-- Choisir --</option>
                    <option value="HOME" <?php if ($lieu === 'HOME') echo 'selected'; ?>>Domicile</option>
                    <option value="AWAY" <?php if ($lieu === 'AWAY') echo 'selected'; ?>>Extérieur</option>
                </select>
                <?php if (!empty($erreurs['lieu'])): ?>
                    <span class="erreur-champ">
                        <?php echo htmlspecialchars($erreurs['lieu'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="formulaire-champ">
<label for="buts_city">Buts City</label>
                    <input type="number" id="buts_city" name="buts_city" min="0"
                        value="<?php echo htmlspecialchars($buts_city, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!empty($erreurs['buts_city'])): ?>
                        <span class="erreur-champ">
                            <?php echo htmlspecialchars($erreurs['buts_city'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="formulaire-champ">
<label for="buts_adverse">Buts adversaire</label>
                    <input type="number" id="buts_adverse" name="buts_adverse" min="0"
                        value="<?php echo htmlspecialchars($buts_adverse, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!empty($erreurs['buts_adverse'])): ?>
                        <span class="erreur-champ">
                            <?php echo htmlspecialchars($erreurs['buts_adverse'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="formulaire-champ">
                <label for="youtube_url">Lien YouTube (optionnel)</label>
                <input type="url" id="youtube_url" name="youtube_url"
                    placeholder="https://www.youtube.com/watch?v=..."
                    value="<?php echo htmlspecialchars($youtube_url, ENT_QUOTES, 'UTF-8'); ?>">
                <small style="color:var(--gris-fonce); font-size:0.78rem;">Résumé vidéo du match — affiché dans la zone supporter</small>
            </div>

            <div style="display:flex; gap:1rem; margin-top:0.5rem;">
                <button type="submit" class="bouton bouton-principal" style="flex:1;">
                    Ajouter le match
                </button>
                <a href="matchs.php" class="bouton bouton-secondaire" style="flex:1; text-align:center;">
                    Annuler
                </a>
            </div>

        </form>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
