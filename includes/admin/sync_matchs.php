<?php
session_start();
require_once '../../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../../login.php');
    exit;
}

// ─── CONFIGURATION ──────────────────────────────────────────────────────────
// Clé API gratuite : https://www.football-data.org/client/register
// Copiez votre clé ici :
define('FOOTBALL_API_KEY', '');
define('MANCITY_ID', 65);
define('LAST_SYNC_FILE', __DIR__ . '/../../.last_sync');
// ────────────────────────────────────────────────────────────────────────────

// Ajouter les colonnes si elles n'existent pas encore
try {
    $pdo->exec("ALTER TABLE matchs ADD COLUMN status ENUM('played','upcoming') NOT NULL DEFAULT 'played'");
} catch (PDOException $e) { /* colonne déjà présente */ }
try {
    $pdo->exec("ALTER TABLE matchs ADD COLUMN api_id INT NULL");
} catch (PDOException $e) { /* colonne déjà présente */ }
try {
    $pdo->exec("ALTER TABLE matchs ADD UNIQUE KEY uk_api_id (api_id)");
} catch (PDOException $e) { /* index déjà présent */ }

// Corriger les matchs déjà en base selon la date
$pdo->exec("UPDATE matchs SET status='upcoming' WHERE match_date > CURDATE() AND api_id IS NULL");
$pdo->exec("UPDATE matchs SET status='played'   WHERE match_date <= CURDATE() AND api_id IS NULL");

$messages = [];

function call_api(string $url): ?array {
    if (!defined('FOOTBALL_API_KEY') || FOOTBALL_API_KEY === '') return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['X-Auth-Token: ' . FOOTBALL_API_KEY],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$body) return null;
    return json_decode($body, true);
}

$comp_map = [
    'PL'  => 'Premier League',
    'CL'  => 'Ligue des Champions',
    'FAC' => 'FA Cup',
    'ELC' => 'Carabao Cup',
    'SC'  => 'Super Coupe',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync'])) {

    if (FOOTBALL_API_KEY === '') {
        $messages[] = ['error', 'Clé API manquante — renseignez FOOTBALL_API_KEY dans ce fichier.'];
    } else {
        $season = (int)date('Y') >= 7 ? (int)date('Y') : (int)date('Y') - 1;
        // Saison commence en août, donc si on est avant juillet on est encore sur saison précédente
        $season = (int)date('m') >= 8 ? (int)date('Y') : (int)date('Y') - 1;

        $data = call_api('https://api.football-data.org/v4/teams/' . MANCITY_ID . '/matches?season=' . $season . '&limit=60');

        if (!$data || empty($data['matches'])) {
            $messages[] = ['error', 'Réponse API vide ou invalide. Vérifiez votre clé API.'];
        } else {
            $added = 0;
            $updated = 0;
            $skipped = 0;

            $stmt_check  = $pdo->prepare('SELECT id FROM matchs WHERE api_id = ?');
            $stmt_insert = $pdo->prepare(
                'INSERT INTO matchs (opponent, competition, match_date, home_away, goals_city, goals_opponent, status, api_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt_update = $pdo->prepare(
                'UPDATE matchs SET opponent=?, competition=?, match_date=?, home_away=?, goals_city=?, goals_opponent=?, status=? WHERE api_id=?'
            );

            foreach ($data['matches'] as $m) {
                $api_status = $m['status'] ?? '';
                // On ignore les matchs annulés/suspendus
                if (in_array($api_status, ['CANCELLED', 'SUSPENDED', 'AWARDED'])) {
                    $skipped++;
                    continue;
                }

                $api_id   = (int)$m['id'];
                $date     = substr($m['utcDate'], 0, 10);
                $is_home  = (int)$m['homeTeam']['id'] === MANCITY_ID;
                $opponent = $is_home ? $m['awayTeam']['name'] : $m['homeTeam']['name'];
                // Nettoyer le nom du club
                $opponent = preg_replace('/ F\.?C\.?$| A\.?F\.?C\.?$/i', '', $opponent);
                $opponent = trim($opponent);

                $comp_code   = $m['competition']['code'] ?? '';
                $competition = $comp_map[$comp_code] ?? $m['competition']['name'];
                $home_away   = $is_home ? 'HOME' : 'AWAY';

                if ($api_status === 'FINISHED') {
                    $goals_city = $is_home
                        ? (int)($m['score']['fullTime']['home'] ?? 0)
                        : (int)($m['score']['fullTime']['away'] ?? 0);
                    $goals_opp  = $is_home
                        ? (int)($m['score']['fullTime']['away'] ?? 0)
                        : (int)($m['score']['fullTime']['home'] ?? 0);
                    $status = 'played';
                } else {
                    $goals_city = 0;
                    $goals_opp  = 0;
                    $status = 'upcoming';
                }

                $stmt_check->execute([$api_id]);
                if ($stmt_check->fetchColumn()) {
                    $stmt_update->execute([$opponent, $competition, $date, $home_away, $goals_city, $goals_opp, $status, $api_id]);
                    $updated++;
                } else {
                    $stmt_insert->execute([$opponent, $competition, $date, $home_away, $goals_city, $goals_opp, $status, $api_id]);
                    $added++;
                }
            }

            file_put_contents(LAST_SYNC_FILE, date('Y-m-d H:i:s'));
            $messages[] = ['success', "$added matchs ajoutés · $updated mis à jour · $skipped ignorés"];
        }
    }
}

$last_sync = file_exists(LAST_SYNC_FILE) ? file_get_contents(LAST_SYNC_FILE) : null;

// Compter matchs joués / à venir
$nb_played   = (int)$pdo->query("SELECT COUNT(*) FROM matchs WHERE match_date <= CURDATE()")->fetchColumn();
$nb_upcoming = (int)$pdo->query("SELECT COUNT(*) FROM matchs WHERE match_date > CURDATE()")->fetchColumn();
?>
<?php require_once '../header.php'; ?>

<h1 class="titre-page">Synchronisation des matchs</h1>
<p class="sous-titre-page">Via l'API <strong>football-data.org</strong> — Manchester City</p>

<div class="carte" style="max-width:640px; margin:0 auto 2rem;">

    <?php foreach ($messages as [$type, $msg]): ?>
        <div style="
            padding:0.75rem 1rem; border-radius:10px; margin-bottom:1rem; font-size:0.88rem;
            background:<?php echo $type === 'success' ? 'rgba(46,204,113,0.1)' : 'rgba(231,76,60,0.1)'; ?>;
            border:1px solid <?php echo $type === 'success' ? 'rgba(46,204,113,0.35)' : 'rgba(231,76,60,0.35)'; ?>;
            color:<?php echo $type === 'success' ? '#2ecc71' : '#e74c3c'; ?>;">
            <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endforeach; ?>

    <!-- État actuel -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1.5rem;">
        <div style="background:rgba(108,171,221,0.07); border:1px solid rgba(108,171,221,0.15); border-radius:12px; padding:0.8rem 1rem; text-align:center;">
            <div style="font-size:1.5rem; font-weight:800; color:var(--bleu-city);"><?php echo $nb_played; ?></div>
            <div style="font-size:0.78rem; color:#8A9BB0; margin-top:2px;">Matchs joués</div>
        </div>
        <div style="background:rgba(201,168,76,0.07); border:1px solid rgba(201,168,76,0.15); border-radius:12px; padding:0.8rem 1rem; text-align:center;">
            <div style="font-size:1.5rem; font-weight:800; color:#C9A84C;"><?php echo $nb_upcoming; ?></div>
            <div style="font-size:0.78rem; color:#8A9BB0; margin-top:2px;">À venir en base</div>
        </div>
    </div>

    <?php if ($last_sync): ?>
        <p style="color:#8A9BB0; font-size:0.8rem; margin-bottom:1.5rem;">
            Dernière synchronisation : <strong style="color:#C8D6E5;"><?php echo htmlspecialchars($last_sync); ?></strong>
        </p>
    <?php endif; ?>

    <?php if (FOOTBALL_API_KEY === ''): ?>
        <div style="background:rgba(201,168,76,0.08); border:1px solid rgba(201,168,76,0.25); border-radius:12px; padding:1rem; margin-bottom:1.5rem; font-size:0.85rem;">
            <strong style="color:#C9A84C;">Clé API requise</strong><br>
            <span style="color:#8A9BB0;">
                1. Inscrivez-vous gratuitement sur
                <strong style="color:#C8D6E5;">football-data.org</strong><br>
                2. Copiez votre clé dans <code style="color:#6CABDD;">includes/admin/sync_matchs.php</code> ligne 12
            </span>
        </div>
    <?php endif; ?>

    <form method="POST">
        <button type="submit" name="sync" class="bouton bouton-principal" style="width:100%; padding:0.8rem;"
            <?php echo FOOTBALL_API_KEY === '' ? 'disabled title="Clé API requise"' : ''; ?>>
            Synchroniser maintenant
        </button>
    </form>

    <p style="color:rgba(138,155,176,0.5); font-size:0.75rem; margin-top:1rem; text-align:center;">
        Données : Premier League · Ligue des Champions · FA Cup · Carabao Cup
    </p>
</div>

<div style="max-width:640px; margin:0 auto;">
    <a href="matchs.php" class="bouton bouton-secondaire" style="font-size:0.82rem; padding:0.4rem 1rem; border-radius:20px;">
        ← Gérer les matchs
    </a>
</div>

<?php require_once '../footer.php'; ?>
