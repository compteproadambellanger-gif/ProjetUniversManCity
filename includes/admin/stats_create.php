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

$erreurs = [];

$requete = $pdo->query(
    'SELECT id, full_name, shirt_number FROM players ORDER BY shirt_number'
);
$joueurs = $requete->fetchAll(PDO::FETCH_ASSOC);

$requete = $pdo->query(
    'SELECT id, match_date, opponent, competition FROM matchs ORDER BY match_date DESC'
);
$matchs = $requete->fetchAll(PDO::FETCH_ASSOC);

$id_joueur      = '';
$id_match       = '';
$titulaire      = 1;
$minutes_jouees = '';
$buts           = '';
$passes_decisives = '';
$note           = '';
$cartons_jaunes = '';
$cartons_rouges = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erreur_acces === '') {
    $id_joueur        = (int)($_POST['id_joueur'] ?? 0);
    $id_match         = (int)($_POST['id_match'] ?? 0);
    $titulaire        = isset($_POST['titulaire']) ? 1 : 0;
    $minutes_jouees   = trim($_POST['minutes_jouees'] ?? '0');
    $buts             = trim($_POST['buts'] ?? '0');
    $passes_decisives = trim($_POST['passes_decisives'] ?? '0');
    $note             = trim($_POST['note'] ?? '');
    $cartons_jaunes   = trim($_POST['cartons_jaunes'] ?? '0');
    $cartons_rouges   = trim($_POST['cartons_rouges'] ?? '0');

    if ($id_joueur <= 0) {
        $erreurs['id_joueur'] = 'Vous devez choisir un joueur.';
    }
    if ($id_match <= 0) {
        $erreurs['id_match'] = 'Vous devez choisir un match.';
    }
    if ($minutes_jouees === '' || !ctype_digit($minutes_jouees)) {
        $erreurs['minutes_jouees'] = 'Les minutes jouées doivent être un entier.';
    }
    if ($buts === '' || !ctype_digit($buts)) {
        $erreurs['buts'] = 'Les buts doivent être un entier.';
    }
    if ($passes_decisives === '' || !ctype_digit($passes_decisives)) {
        $erreurs['passes_decisives'] = 'Les passes décisives doivent être un entier.';
    }
    if ($cartons_jaunes === '' || !ctype_digit($cartons_jaunes)) {
        $erreurs['cartons_jaunes'] = 'Les cartons jaunes doivent être un entier.';
    }
    if ($cartons_rouges === '' || !ctype_digit($cartons_rouges)) {
        $erreurs['cartons_rouges'] = 'Les cartons rouges doivent être un entier.';
    }
    if ($note !== '' && !preg_match('/^\d{1,2}(\.\d)?$/', $note)) {
        $erreurs['note'] = 'La note doit être un nombre (ex : 7 ou 7.5).';
    }

    if (empty($erreurs)) {
        $requete = $pdo->prepare(
            'SELECT id FROM player_match_stats WHERE player_id = ? AND match_id = ?'
        );
        $requete->execute([$id_joueur, $id_match]);
        if ($requete->fetch()) {
            $erreurs['global'] = 'Des statistiques existent déjà pour ce joueur sur ce match.';
        }
    }

    if (empty($erreurs)) {
        $requete = $pdo->prepare(
            'INSERT INTO player_match_stats (
                player_id, match_id, titulaire, minutes_jouees, buts,
                passes_decisives, note, cartons_jaunes, cartons_rouges
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $requete->execute([
            $id_joueur,
            $id_match,
            $titulaire,
            (int)$minutes_jouees,
            (int)$buts,
            (int)$passes_decisives,
            $note === '' ? null : (float)$note,
            (int)$cartons_jaunes,
            (int)$cartons_rouges
        ]);

        header('Location: ../../player_stats.php?id=' . $id_joueur);
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<h1 class="titre-page">Ajouter des statistiques</h1>
<p class="sous-titre-page">Zone réservée au staff du club.</p>

<?php if ($erreur_acces !== ''): ?>
    <div class="erreur-globale" style="max-width:600px;">
        <strong>Accès refusé</strong>
        <p><?php echo htmlspecialchars($erreur_acces, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="../../dashboard.php" class="bouton bouton-secondaire" style="margin-top:1rem;">
            Retour au tableau de bord
        </a>
    </div>
<?php else: ?>

    <?php if (!empty($erreurs['global'])): ?>
        <div class="erreur-globale" style="max-width:600px;">
            <?php echo htmlspecialchars($erreurs['global'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($erreurs) && empty($erreurs['global'])): ?>
        <div class="erreur-globale" style="max-width:600px;">
            Merci de corriger les erreurs ci-dessous.
        </div>
    <?php endif; ?>

    <div style="
            max-width:600px;
            background:rgba(26,43,74,0.6);
            border:1px solid rgba(108,171,221,0.2);
            border-radius:16px;
            padding:2rem;
            backdrop-filter:blur(10px);">

        <form action="stats_create.php" method="post" class="formulaire-groupe">

            <div class="formulaire-champ">
<label for="id_joueur">Joueur</label>
                <select id="id_joueur" name="id_joueur">
                    <option value="0">-- Choisir un joueur --</option>
                    <?php foreach ($joueurs as $j): ?>
                        <option value="<?php echo (int)$j['id']; ?>"
                            <?php if ($id_joueur == $j['id']) echo 'selected'; ?>>
                            n°<?php echo (int)$j['shirt_number']; ?> —
                            <?php echo htmlspecialchars($j['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($erreurs['id_joueur'])): ?>
                    <span class="erreur-champ">
                        <?php echo htmlspecialchars($erreurs['id_joueur'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="formulaire-champ">
<label for="id_match">Match</label>
                <select id="id_match" name="id_match">
                    <option value="0">-- Choisir un match --</option>
                    <?php foreach ($matchs as $m): ?>
                        <option value="<?php echo (int)$m['id']; ?>"
                            <?php if ($id_match == $m['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($m['match_date'], ENT_QUOTES, 'UTF-8'); ?>
                            —
                            <?php echo htmlspecialchars($m['competition'], ENT_QUOTES, 'UTF-8'); ?>
                            vs
                            <?php echo htmlspecialchars($m['opponent'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($erreurs['id_match'])): ?>
                    <span class="erreur-champ">
                        <?php echo htmlspecialchars($erreurs['id_match'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="formulaire-champ">
<label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer;">
                <input type="checkbox" name="titulaire" value="1"
                    <?php if ($titulaire) echo 'checked'; ?>
                    style="width:18px; height:18px; accent-color:var(--bleu-city);">
                <span>Titulaire</span>
                </label>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="formulaire-champ">
<label for="minutes_jouees">Minutes jouées</label>
                    <input type="number" id="minutes_jouees" name="minutes_jouees"
                        min="0" max="120"
                        value="<?php echo htmlspecialchars($minutes_jouees, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!empty($erreurs['minutes_jouees'])): ?>
                        <span class="erreur-champ">
                            <?php echo htmlspecialchars($erreurs['minutes_jouees'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="formulaire-champ">
<label for="note">Note (ex : 7.5)</label>
                    <input type="text" id="note" name="note"
                        placeholder="Ex : 8.5"
                        value="<?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!empty($erreurs['note'])): ?>
                        <span class="erreur-champ">
                            <?php echo htmlspecialchars($erreurs['note'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                <div class="formulaire-champ">
<label for="buts">Buts</label>
                    <input type="number" id="buts" name="buts" min="0"
                        value="<?php echo htmlspecialchars($buts, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!empty($erreurs['buts'])): ?>
                        <span class="erreur-champ">
                            <?php echo htmlspecialchars($erreurs['buts'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="formulaire-champ">
<label for="passes_decisives">Passes déc.</label>
                    <input type="number" id="passes_decisives" name="passes_decisives" min="0"
                        value="<?php echo htmlspecialchars($passes_decisives, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!empty($erreurs['passes_decisives'])): ?>
                        <span class="erreur-champ">
                            <?php echo htmlspecialchars($erreurs['passes_decisives'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="formulaire-champ">
<label for="cartons_jaunes">Cartons J.</label>
                    <input type="number" id="cartons_jaunes" name="cartons_jaunes"
                        min="0" max="2"
                        value="<?php echo htmlspecialchars($cartons_jaunes, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!empty($erreurs['cartons_jaunes'])): ?>
                        <span class="erreur-champ">
                            <?php echo htmlspecialchars($erreurs['cartons_jaunes'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="formulaire-champ">
<label for="cartons_rouges">Cartons rouges</label>
                <input type="number" id="cartons_rouges" name="cartons_rouges"
                    min="0" max="1"
                    value="<?php echo htmlspecialchars($cartons_rouges, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (!empty($erreurs['cartons_rouges'])): ?>
                    <span class="erreur-champ">
                        <?php echo htmlspecialchars($erreurs['cartons_rouges'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div style="display:flex; gap:1rem; margin-top:0.5rem;">
                <button type="submit" class="bouton bouton-principal" style="flex:1;">
                    Enregistrer les statistiques
                </button>
                <a href="../../dashboard.php" class="bouton bouton-secondaire"
                    style="flex:1; text-align:center;">
                    Annuler
                </a>
            </div>

        </form>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
