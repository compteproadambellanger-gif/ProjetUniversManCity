<?php
session_start();
require_once __DIR__ . '/../../db.php';

// 1) Vérifier connexion + rôle staff
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'staff') {
    $accessError = 'Vous n\'avez pas les droits pour accéder à cette section réservée au staff.';
} else {
    $accessError = '';
}

$full_name = '';
$shirt_number = '';
$position = '';
$nationality = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accessError === '') {
    $full_name = trim($_POST['full_name'] ?? '');
    $shirt_number = trim($_POST['shirt_number'] ?? '');
    $position = $_POST['position'] ?? '';
    $nationality = trim($_POST['nationality'] ?? '');

    // Validations
    if ($full_name === '') {
        $errors['full_name'] = 'Le nom du joueur est obligatoire.';
    }

    if ($shirt_number === '') {
        $errors['shirt_number'] = 'Le numéro de maillot est obligatoire.';
    } elseif (!ctype_digit($shirt_number)) {
        $errors['shirt_number'] = 'Le numéro de maillot doit être un nombre entier.';
    }

    $allowed_positions = ['GK', 'DEF', 'MID', 'FWD'];
    if (!in_array($position, $allowed_positions, true)) {
        $errors['position'] = 'Le poste est invalide.';
    }

    if ($nationality === '') {
        $errors['nationality'] = 'La nationalité est obligatoire.';
    }

    // Vérifier qu'il n'y a pas déjà un joueur avec ce numéro
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM players WHERE shirt_number = ?');
        $stmt->execute([$shirt_number]);
        if ($stmt->fetch()) {
            $errors['shirt_number'] = 'Un joueur utilise déjà ce numéro de maillot.';
        }
    }

    // Insertion
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO players (full_name, shirt_number, position, nationality)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $full_name,
            (int)$shirt_number,
            $position,
            $nationality
        ]);

        // Redirection vers la liste admin après création
        header('Location: players.php');
        exit;
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<h1 class="titre-page">Ajouter un joueur</h1>
<p class="sous-titre-page">Zone réservée au staff du club.</p>

<?php if ($accessError !== ''): ?>
    <div class="erreur-globale" style="max-width:500px; margin: 0 auto;">
        <strong>Accès refusé</strong>
        <p><?php echo htmlspecialchars($accessError, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="../../dashboard.php" class="bouton bouton-secondaire" style="margin-top:1rem;">
            Retour au tableau de bord
        </a>
    </div>
<?php else: ?>

    <?php if (!empty($errors)): ?>
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

        <form action="player_create.php" method="post" class="formulaire-groupe">

            <div class="formulaire-champ">
                <label for="full_name">Nom complet du joueur</label>
                <input type="text" id="full_name" name="full_name"
                    value="<?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (!empty($errors['full_name'])): ?>
                    <span class="erreur-champ">
                        <?php echo htmlspecialchars($errors['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="formulaire-champ">
                    <label for="shirt_number">Numéro de maillot</label>
                    <input type="number" id="shirt_number" name="shirt_number"
                        value="<?php echo htmlspecialchars($shirt_number, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!empty($errors['shirt_number'])): ?>
                        <span class="erreur-champ">
                            <?php echo htmlspecialchars($errors['shirt_number'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="formulaire-champ">
                    <label for="position">Poste</label>
                    <select id="position" name="position">
                        <option value="">-- Choisir un poste --</option>
                        <option value="GK" <?php if ($position === 'GK')  echo 'selected'; ?>>Gardien</option>
                        <option value="DEF" <?php if ($position === 'DEF') echo 'selected'; ?>>Défenseur</option>
                        <option value="MID" <?php if ($position === 'MID') echo 'selected'; ?>>Milieu</option>
                        <option value="FWD" <?php if ($position === 'FWD') echo 'selected'; ?>>Attaquant</option>
                    </select>
                    <?php if (!empty($errors['position'])): ?>
                        <span class="erreur-champ">
                            <?php echo htmlspecialchars($errors['position'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="formulaire-champ">
                <label for="nationality">Nationalité</label>
                <input type="text" id="nationality" name="nationality"
                    value="<?php echo htmlspecialchars($nationality, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (!empty($errors['nationality'])): ?>
                    <span class="erreur-champ">
                        <?php echo htmlspecialchars($errors['nationality'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div style="display:flex; gap:1rem; margin-top:0.5rem;">
                <button type="submit" class="bouton bouton-principal" style="flex:1;">
                    Ajouter le joueur
                </button>
                <a href="players.php" class="bouton bouton-secondaire" style="flex:1; text-align:center;">
                    Retour à la liste
                </a>
            </div>

        </form>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>