<?php
session_start();
require_once __DIR__ . '/../../db.php';

// 1) Sécurité : connecté + staff
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'staff') {
    $accessError = 'Vous n\'avez pas les droits pour accéder à cette section réservée au staff.';
} else {
    $accessError = '';
}

$errors = [];
$full_name = '';
$shirt_number = '';
$position = '';
$nationality = '';
$photo_url = '';

// 2) Récupérer l'ID du joueur dans l'URL
$playerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($accessError === '') {
    if ($playerId <= 0) {
        $errors['global'] = 'ID de joueur invalide.';
    } else {
        // Charger les infos du joueur existant
        $stmt = $pdo->prepare('SELECT id, full_name, shirt_number, position, nationality, photo_url FROM players WHERE id = ?');
        $stmt->execute([$playerId]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$player) {
            $errors['global'] = 'Joueur introuvable.';
        } else {
            // Valeurs par défaut (avant soumission)
            $full_name = $player['full_name'];
            $shirt_number = $player['shirt_number'];
            $position = $player['position'];
            $nationality = $player['nationality'];
            $photo_url = $player['photo_url'] ?? '';
        }
    }
}

// 3) Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accessError === '' && empty($errors['global'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $shirt_number = trim($_POST['shirt_number'] ?? '');
    $position = $_POST['position'] ?? '';
    $nationality = trim($_POST['nationality'] ?? '');

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

    // Vérifier que le numéro n'est pas déjà utilisé par un AUTRE joueur
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM players WHERE shirt_number = ? AND id != ?');
        $stmt->execute([$shirt_number, $playerId]);
        if ($stmt->fetch()) {
            $errors['shirt_number'] = 'Un autre joueur utilise déjà ce numéro de maillot.';
        }
    }

    // Gestion upload photo
    $new_photo_url = $photo_url;
    if (!empty($_FILES['photo']['name'])) {
        $file = $_FILES['photo'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            $errors['photo'] = 'Format non supporté. Utilisez JPG, PNG ou WEBP.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors['photo'] = 'Image trop grande (max 2MB).';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'player_' . $playerId . '.' . $ext;
            $dest = __DIR__ . '/../../uploads/players/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // Supprimer l'ancienne photo si extension différente
                if ($photo_url && $photo_url !== 'uploads/players/' . $filename) {
                    $old = __DIR__ . '/../../' . $photo_url;
                    if (file_exists($old)) @unlink($old);
                }
                $new_photo_url = 'uploads/players/' . $filename;
            } else {
                $errors['photo'] = 'Erreur lors de l\'upload.';
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE players
             SET full_name = ?, shirt_number = ?, position = ?, nationality = ?, photo_url = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $full_name,
            (int)$shirt_number,
            $position,
            $nationality,
            $new_photo_url !== '' ? $new_photo_url : null,
            $playerId
        ]);

        header('Location: players.php');
        exit;
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<h1 class="titre-page">Modifier un joueur</h1>
<p class="sous-titre-page">Zone réservée au staff du club.</p>

<?php if ($accessError !== ''): ?>
    <div class="erreur-globale" style="max-width:500px; margin: 0 auto;">
        <strong>Accès refusé</strong>
        <p><?php echo htmlspecialchars($accessError, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="../../dashboard.php" class="bouton bouton-secondaire" style="margin-top:1rem;">
            Retour au tableau de bord
        </a>
    </div>
<?php elseif (!empty($errors['global'])): ?>
    <div class="erreur-globale" style="max-width:500px; margin: 0 auto;">
        <p><?php echo htmlspecialchars($errors['global'], ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="players.php" class="bouton bouton-secondaire" style="margin-top:1rem;">
            Retour à la liste des joueurs
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

        <form action="player_edit.php?id=<?php echo (int)$playerId; ?>" method="post" enctype="multipart/form-data" class="formulaire-groupe">

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

            <div class="formulaire-champ">
                <label for="photo">Photo du joueur (optionnel)</label>
                <?php if (!empty($photo_url) && file_exists(__DIR__ . '/../../' . $photo_url)): ?>
                    <img src="../../<?php echo htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8'); ?>"
                         style="width:60px; height:60px; border-radius:50%; object-fit:cover; display:block; margin-bottom:0.5rem; border:2px solid rgba(108,171,221,0.3);">
                <?php endif; ?>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                <small style="color:var(--gris-fonce); font-size:0.78rem;">JPG, PNG ou WEBP — max 2MB</small>
                <?php if (!empty($errors['photo'])): ?>
                    <span class="erreur-champ"><?php echo htmlspecialchars($errors['photo'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <div style="display:flex; gap:1rem; margin-top:0.5rem;">
                <button type="submit" class="bouton bouton-principal" style="flex:1;">
                    Enregistrer les modifications
                </button>
                <a href="players.php" class="bouton bouton-secondaire" style="flex:1; text-align:center;">
                    Retour à la liste
                </a>
            </div>

        </form>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
