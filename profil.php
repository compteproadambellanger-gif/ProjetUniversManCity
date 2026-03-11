<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$succes = '';
$erreurs = [];

$requete = $pdo->prepare('SELECT nom, email, role, photo_profil, google_id, created_at, derniere_activite FROM users WHERE id = ?');
$requete->execute([$userId]);
$utilisateur = $requete->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$pdo->prepare('UPDATE users SET derniere_activite = NOW() WHERE id = ?')->execute([$userId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'photo') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $fichier = $_FILES['photo'];
            $extensionsAutorisees = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
            $tailleMax = 2 * 1024 * 1024; // 2 Mo

            if (!in_array($extension, $extensionsAutorisees)) {
                $erreurs['photo'] = 'Format non autorisé. Utilisez JPG, PNG, GIF ou WebP.';
            } elseif ($fichier['size'] > $tailleMax) {
                $erreurs['photo'] = 'L\'image ne doit pas dépasser 2 Mo.';
            } else {
                if (!empty($utilisateur['photo_profil']) && file_exists($utilisateur['photo_profil'])) {
                    unlink($utilisateur['photo_profil']);
                }

                $nomFichier = 'avatar_' . $userId . '_' . time() . '.' . $extension;
                $cheminDestination = 'uploads/avatars/' . $nomFichier;

                if (move_uploaded_file($fichier['tmp_name'], $cheminDestination)) {
                    $pdo->prepare('UPDATE users SET photo_profil = ? WHERE id = ?')->execute([$cheminDestination, $userId]);
                    $utilisateur['photo_profil'] = $cheminDestination;
                    $_SESSION['toast'] = ['message' => 'Photo de profil mise à jour !', 'type' => 'success'];
                    header('Location: profil.php');
                    exit;
                } else {
                    $erreurs['photo'] = 'Erreur lors de l\'upload. Réessayez.';
                }
            }
        } else {
            $erreurs['photo'] = 'Veuillez sélectionner une image.';
        }
    }

    if ($_POST['action'] === 'nom') {
        $nouveauNom = trim($_POST['nom'] ?? '');
        if ($nouveauNom === '') {
            $erreurs['nom'] = 'Le nom ne peut pas être vide.';
        } elseif (mb_strlen($nouveauNom) < 3) {
            $erreurs['nom'] = 'Le nom doit contenir au moins 3 caractères.';
        } else {
            $pdo->prepare('UPDATE users SET nom = ? WHERE id = ?')->execute([$nouveauNom, $userId]);
            $utilisateur['nom'] = $nouveauNom;
            $_SESSION['toast'] = ['message' => 'Nom mis à jour avec succès !', 'type' => 'success'];
            header('Location: profil.php');
            exit;
        }
    }

    if ($_POST['action'] === 'mot_de_passe') {
        $ancienMdp = $_POST['ancien_mdp'] ?? '';
        $nouveauMdp = $_POST['nouveau_mdp'] ?? '';
        $confirmMdp = $_POST['confirm_mdp'] ?? '';

        $req = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $req->execute([$userId]);
        $hashActuel = $req->fetchColumn();

        $estGoogleSansMotDePasse = (!empty($utilisateur['google_id']) && empty($hashActuel));

        if (!$estGoogleSansMotDePasse && $ancienMdp === '') {
            $erreurs['ancien_mdp'] = 'L\'ancien mot de passe est obligatoire.';
        } elseif (!$estGoogleSansMotDePasse && !password_verify($ancienMdp, $hashActuel)) {
            $erreurs['ancien_mdp'] = 'L\'ancien mot de passe est incorrect.';
        }

        if ($nouveauMdp === '') {
            $erreurs['nouveau_mdp'] = 'Le nouveau mot de passe est obligatoire.';
        } elseif (strlen($nouveauMdp) < 8) {
            $erreurs['nouveau_mdp'] = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif (!preg_match('/[A-Z]/', $nouveauMdp)) {
            $erreurs['nouveau_mdp'] = 'Le mot de passe doit contenir au moins une majuscule.';
        } elseif (!preg_match('/[a-z]/', $nouveauMdp)) {
            $erreurs['nouveau_mdp'] = 'Le mot de passe doit contenir au moins une minuscule.';
        } elseif (!preg_match('/[0-9]/', $nouveauMdp)) {
            $erreurs['nouveau_mdp'] = 'Le mot de passe doit contenir au moins un chiffre.';
        } elseif (!preg_match('/[\W_]/', $nouveauMdp)) {
            $erreurs['nouveau_mdp'] = 'Le mot de passe doit contenir au moins un caractère spécial.';
        }

        if ($confirmMdp !== $nouveauMdp) {
            $erreurs['confirm_mdp'] = 'Les mots de passe ne correspondent pas.';
        }

        if (empty($erreurs)) {
            $nouveauHash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$nouveauHash, $userId]);
            $_SESSION['toast'] = ['message' => 'Mot de passe modifié avec succès !', 'type' => 'success'];
            header('Location: profil.php');
            exit;
        }
    }

    if ($_POST['action'] === 'supprimer_photo') {
        if (!empty($utilisateur['photo_profil']) && file_exists($utilisateur['photo_profil'])) {
            unlink($utilisateur['photo_profil']);
        }
        $pdo->prepare('UPDATE users SET photo_profil = NULL WHERE id = ?')->execute([$userId]);
        $utilisateur['photo_profil'] = null;
        $_SESSION['toast'] = ['message' => 'Photo de profil supprimée.', 'type' => 'info'];
        header('Location: profil.php');
        exit;
    }
}

$req = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$req->execute([$userId]);
$hashActuel = $req->fetchColumn();
$estGoogleSansMotDePasse = (!empty($utilisateur['google_id']) && empty($hashActuel));

require_once 'includes/header.php';
?>

<div class="profil-page-wrapper">
    <div class="profil-grande-bulle">
        <h1 class="profil-titre-principal">Mon Profil</h1>
        
        <div class="profil-bulles-container">
            <div class="profil-bulle bulle-infos">
                <div class="profil-avatar-container">
                    <?php if (!empty($utilisateur['photo_profil']) && file_exists($utilisateur['photo_profil'])): ?>
                        <img src="/ProjetUnivers/<?php echo htmlspecialchars($utilisateur['photo_profil'], ENT_QUOTES, 'UTF-8'); ?>"
                             alt="Avatar" class="avatar-view">
                    <?php else: ?>
                        <div class="avatar-default-view">
                            <?php echo strtoupper(mb_substr($utilisateur['nom'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" name="action" value="photo">
                        <label for="photo-input" class="btn-edit-photo">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </label>
                        <input type="file" id="photo-input" name="photo" accept="image/*" onchange="this.form.submit()" style="display:none;">
                    </form>
                </div>

                <div class="user-details" style="width:100%;min-width:0;overflow:hidden;">
                    <h2 class="user-name"><?php echo htmlspecialchars($utilisateur['nom'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <div style="display:flex;justify-content:center;margin-top:0.5rem;">
                        <span style="
                            display:inline-block;
                            background:rgba(108,171,221,0.15);
                            color:var(--bleu-city);
                            border:1px solid rgba(108,171,221,0.4);
                            border-radius:999px;
                            padding:0.4rem 1rem;
                            font-size:0.85rem;
                            font-weight:500;
                            word-break:break-all;
                            text-align:center;
                        "><?php echo htmlspecialchars($utilisateur['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <?php if (!empty($utilisateur['photo_profil'])): ?>
                    <form method="post" class="delete-photo-form" style="width: 100%; margin-top: 1rem;">
                        <input type="hidden" name="action" value="supprimer_photo">
                        <button type="submit" class="btn-bubble-danger">Supprimer la photo</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="profil-bulle bulle-form">
                <h3>Changer le nom</h3>
                <form method="post" class="form-bubble">
                    <input type="hidden" name="action" value="nom">
                    <div class="bubble-field">
                        <input type="text" name="nom" value="<?php echo htmlspecialchars($utilisateur['nom'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nouveau nom">
                        <?php if (!empty($erreurs['nom'])): ?>
                            <span class="err-msg"><?php echo htmlspecialchars($erreurs['nom'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn-bubble">Mettre à jour</button>
                </form>
            </div>

            <div class="profil-bulle bulle-form">
                <h3>Mot de passe</h3>
                <form method="post" class="form-bubble">
                    <input type="hidden" name="action" value="mot_de_passe">
                    
                    <?php if (!$estGoogleSansMotDePasse): ?>
                        <div class="bubble-field">
                            <input type="password" name="ancien_mdp" placeholder="Ancien mot de passe">
                            <?php if (!empty($erreurs['ancien_mdp'])): ?>
                                <span class="err-msg"><?php echo htmlspecialchars($erreurs['ancien_mdp'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="bubble-field">
                        <input type="password" name="nouveau_mdp" placeholder="Nouveau mot de passe">
                        <?php if (!empty($erreurs['nouveau_mdp'])): ?>
                            <span class="err-msg"><?php echo htmlspecialchars($erreurs['nouveau_mdp'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="bubble-field">
                        <input type="password" name="confirm_mdp" placeholder="Confirmer mot de passe">
                        <?php if (!empty($erreurs['confirm_mdp'])): ?>
                            <span class="err-msg"><?php echo htmlspecialchars($erreurs['confirm_mdp'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn-bubble">Modifier le mot de passe</button>
                </form>
            </div>

            <div class="profil-bulle bulle-form">
                <h3>Activité</h3>
                <?php
                $dateInscription = new DateTime($utilisateur['created_at']);
                $maintenant      = new DateTime();
                $joursMemre      = (int)$dateInscription->diff($maintenant)->days;

                $derniereActivite = $utilisateur['derniere_activite'];
                $typeConnexion    = !empty($utilisateur['google_id']) ? 'Google' : 'Email / Mot de passe';
                $rolesLabels      = ['staff' => 'Staff / Coach', 'player' => 'Joueur', 'fan' => 'Supporter'];
                $roleLabel        = $rolesLabels[$utilisateur['role']] ?? $utilisateur['role'];
                ?>
                <ul style="list-style:none;padding:0;margin:0;width:100%;display:flex;flex-direction:column;gap:1rem;">
                    <li style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);padding-bottom:0.8rem;">
                        <span style="color:var(--gris-fonce);font-size:0.88rem;">Membre depuis</span>
                        <strong style="color:var(--blanc);"><?php echo $dateInscription->format('d/m/Y'); ?></strong>
                    </li>
                    <li style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);padding-bottom:0.8rem;">
                        <span style="color:var(--gris-fonce);font-size:0.88rem;">Jours sur le site</span>
                        <strong style="color:var(--bleu-city);"><?php echo $joursMemre; ?> jour<?php echo $joursMemre > 1 ? 's' : ''; ?></strong>
                    </li>
                    <li style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);padding-bottom:0.8rem;">
                        <span style="color:var(--gris-fonce);font-size:0.88rem;">Dernière connexion</span>
                        <strong style="color:var(--blanc);">
                            <?php echo $derniereActivite ? (new DateTime($derniereActivite))->format('d/m/Y H:i') : 'N/A'; ?>
                        </strong>
                    </li>
                    <li style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);padding-bottom:0.8rem;">
                        <span style="color:var(--gris-fonce);font-size:0.88rem;">Type de connexion</span>
                        <strong style="color:var(--blanc);"><?php echo $typeConnexion; ?></strong>
                    </li>
                    <li style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="color:var(--gris-fonce);font-size:0.88rem;">Rôle</span>
                        <strong style="color:var(--or);"><?php echo $roleLabel; ?></strong>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
