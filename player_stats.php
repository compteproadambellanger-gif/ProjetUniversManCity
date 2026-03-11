<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_joueur = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_joueur <= 0) {
    header('Location: players.php');
    exit;
}

$requete = $pdo->prepare(
    'SELECT id, full_name, shirt_number, position, nationality, photo_url
     FROM players WHERE id = ?'
);
$requete->execute([$id_joueur]);
$joueur = $requete->fetch(PDO::FETCH_ASSOC);

if (!$joueur) {
    header('Location: players.php');
    exit;
}

$requete = $pdo->prepare(
    'SELECT
         m.match_date, m.competition, m.home_away, m.opponent,
         s.titulaire, s.minutes_jouees, s.buts, s.passes_decisives,
         s.note, s.cartons_jaunes, s.cartons_rouges
     FROM player_match_stats s
     JOIN matchs m ON s.match_id = m.id
     WHERE s.player_id = ?
     ORDER BY m.match_date DESC'
);
$requete->execute([$id_joueur]);
$stats_matchs = $requete->fetchAll(PDO::FETCH_ASSOC);

$total_buts = $total_passes = $total_minutes = 0;
$total_jaunes = $total_rouges = $total_notes = $nb_notes = 0;

foreach ($stats_matchs as $stat) {
    $total_buts    += (int)$stat['buts'];
    $total_passes  += (int)$stat['passes_decisives'];
    $total_minutes += (int)$stat['minutes_jouees'];
    $total_jaunes  += (int)$stat['cartons_jaunes'];
    $total_rouges  += (int)$stat['cartons_rouges'];
    if ($stat['note'] !== null) {
        $total_notes += (float)$stat['note'];
        $nb_notes++;
    }
}
$note_moyenne = $nb_notes > 0 ? round($total_notes / $nb_notes, 2) : null;
?>

<?php
$parts_av = preg_split('/\s+/', trim($joueur['full_name']));
$initiales_av = mb_strtoupper(mb_substr($parts_av[0], 0, 1));
if (count($parts_av) > 1) $initiales_av .= mb_strtoupper(mb_substr(end($parts_av), 0, 1));
$pos_av = htmlspecialchars($joueur['position'], ENT_QUOTES, 'UTF-8');
?>
<?php require_once 'includes/header.php'; ?>

<div class="player-stats-header" style="text-align:center; margin-top:3.5rem; margin-bottom:0.5rem;">
    <?php if (!empty($joueur['photo_url']) && file_exists(__DIR__ . '/' . $joueur['photo_url'])): ?>
        <img src="<?php echo htmlspecialchars($joueur['photo_url'], ENT_QUOTES, 'UTF-8'); ?>"
             alt="<?php echo htmlspecialchars($joueur['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
             class="player-photo-lg player-avatar-<?php echo $pos_av; ?>"
             style="margin:0 auto 0.75rem; display:block;">
    <?php else: ?>
        <div class="player-avatar-lg player-avatar-<?php echo $pos_av; ?>" style="margin:0 auto 0.75rem;">
            <?php echo $initiales_av; ?>
        </div>
    <?php endif; ?>
    <h1 class="titre-page" style="margin-top:0;">
        <?php echo htmlspecialchars($joueur['full_name'], ENT_QUOTES, 'UTF-8'); ?>
    </h1>
    <p class="sous-titre-page">
        N°<?php echo (int)$joueur['shirt_number']; ?>
        &nbsp;·&nbsp;
        <?php echo $pos_av; ?>
        &nbsp;·&nbsp;
        <?php echo htmlspecialchars($joueur['nationality'], ENT_QUOTES, 'UTF-8'); ?>
    </p>
</div>

<?php if (empty($stats_matchs)): ?>
    <div class="carte">
        <p style="color:var(--gris-fonce);">
            Aucune statistique enregistrée pour ce joueur pour le moment.
        </p>
    </div>
<?php else: ?>

    <h3 style="color:var(--bleu-city); margin-bottom:1rem;">Résumé de saison</h3>
    <div class="grille-stats">
        <div class="stat-card">
            <span class="stat-valeur"><?php echo count($stats_matchs); ?></span>
            <span class="stat-label">Matchs</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur"><?php echo (int)$total_minutes; ?></span>
            <span class="stat-label">Minutes</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur"><?php echo (int)$total_buts; ?></span>
            <span class="stat-label">Buts</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur"><?php echo (int)$total_passes; ?></span>
            <span class="stat-label">Passes déc.</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur">
                <?php echo $note_moyenne !== null ? $note_moyenne : '-'; ?>
            </span>
            <span class="stat-label">Note moy.</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur" style="color:#e74c3c;">
                <?php echo (int)$total_jaunes; ?>
            </span>
            <span class="stat-label">Cartons jaunes</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur" style="color:#c0392b;">
                <?php echo (int)$total_rouges; ?>
            </span>
            <span class="stat-label">Cartons rouges</span>
        </div>
    </div>

    <?php
    $stats_chronologiques = array_reverse($stats_matchs);
    $labels_matchs = [];
    $donnees_notes = [];
    $donnees_buts = [];
    $donnees_passes = [];
    $donnees_minutes = [];

    foreach ($stats_chronologiques as $stat) {
        $date_courte = date('d/m', strtotime($stat['match_date']));
        $opp = $stat['opponent'] ?? '';
        $opp_trunc = mb_strlen($opp) > 10 ? mb_substr($opp, 0, 10) . '...' : $opp;
        $labels_matchs[] = $date_courte . ' ' . $opp_trunc;
        $donnees_notes[] = $stat['note'] !== null ? (float)$stat['note'] : null;
        $donnees_buts[] = (int)$stat['buts'];
        $donnees_passes[] = (int)$stat['passes_decisives'];
        $donnees_minutes[] = (int)$stat['minutes_jouees'];
    }

    $labels_json = json_encode($labels_matchs);
    $notes_json = json_encode($donnees_notes);
    $buts_json = json_encode($donnees_buts);
    $passes_json = json_encode($donnees_passes);
    $minutes_json = json_encode($donnees_minutes);
    ?>

    <h3 style="color:var(--bleu-city); margin:1.5rem 0 1rem;">Évolution sur la saison</h3>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
        <div class="carte" style="grid-column: 1 / -1;">
            <h4 style="color:var(--bleu-city); margin-bottom:1rem; font-size:0.85rem; text-transform:uppercase;">Notes des matchs</h4>
            <canvas id="graphiqueNotes" height="80"></canvas>
        </div>
        <div class="carte">
            <h4 style="color:var(--bleu-city); margin-bottom:1rem; font-size:0.85rem; text-transform:uppercase;">Buts et Passes</h4>
            <canvas id="graphiqueButsPasses" height="200"></canvas>
        </div>
        <div class="carte">
            <h4 style="color:var(--bleu-city); margin-bottom:1rem; font-size:0.85rem; text-transform:uppercase;">Minutes jouées</h4>
            <canvas id="graphiqueMinutesJouees" height="200"></canvas>
        </div>
    </div>

    <script>
        const labelsMatchs = <?php echo $labels_json; ?>;
        const notesData = <?php echo $notes_json; ?>;
        const butsData = <?php echo $buts_json; ?>;
        const passesData = <?php echo $passes_json; ?>;
        const minutesData = <?php echo $minutes_json; ?>;

        const baseOptions = {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(13,27,46,0.9)',
                    titleColor: '#6CABDD',
                    bodyColor: '#E8EDF2',
                    borderColor: 'rgba(108,171,221,0.3)',
                    borderWidth: 1,
                }
            },
            scales: {
                x: { ticks: { color: '#8A9BB0', font: { size: 10 } }, grid: { color: 'rgba(108,171,221,0.08)' } },
                y: { ticks: { color: '#8A9BB0', font: { size: 10 } }, grid: { color: 'rgba(108,171,221,0.08)' }, beginAtZero: true }
            }
        };

        new Chart(document.getElementById('graphiqueNotes'), {
            type: 'line',
            data: {
                labels: labelsMatchs,
                datasets: [{
                    label: 'Note',
                    data: notesData,
                    borderColor: '#2ecc71',
                    backgroundColor: 'rgba(46,204,113,0.1)',
                    borderWidth: 2,
                    pointBackgroundColor: '#2ecc71',
                    fill: true,
                    tension: 0.3,
                    spanGaps: true
                }]
            },
            options: {
                ...baseOptions,
                scales: {
                    ...baseOptions.scales,
                    y: { ...baseOptions.scales.y, max: 10 }
                }
            }
        });

        new Chart(document.getElementById('graphiqueButsPasses'), {
            type: 'bar',
            data: {
                labels: labelsMatchs,
                datasets: [
                    {
                        label: 'Buts',
                        data: butsData,
                        backgroundColor: 'rgba(108,171,221,0.6)',
                        borderRadius: 4
                    },
                    {
                        label: 'Passes',
                        data: passesData,
                        backgroundColor: 'rgba(241,196,15,0.6)',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                ...baseOptions,
                plugins: { ...baseOptions.plugins, legend: { display: true, labels: { color: '#8A9BB0' } } },
                scales: { x: { stacked: true, ...baseOptions.scales.x }, y: { stacked: true, ...baseOptions.scales.y } }
            }
        });

        new Chart(document.getElementById('graphiqueMinutesJouees'), {
            type: 'bar',
            data: {
                labels: labelsMatchs,
                datasets: [{
                    label: 'Minutes',
                    data: minutesData,
                    backgroundColor: 'rgba(201,168,76,0.4)',
                    borderColor: '#C9A84C',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: baseOptions
        });
    </script>

    <h3 style="color:var(--bleu-city); margin:1.5rem 0 1rem;">Détail par match</h3>
    <div class="tableau-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Compétition</th>
                    <th>Adversaire</th>
                    <th>Lieu</th>
                    <th>Tit.</th>
                    <th>Min.</th>
                    <th>Buts</th>
                    <th>Passes</th>
                    <th>Note</th>
                    <th>J</th>
                    <th>R</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats_matchs as $stat): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($stat['match_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($stat['competition'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($stat['opponent'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $stat['home_away'] === 'HOME' ? 'Domicile' : 'Extérieur'; ?></td>
                        <td><?php echo $stat['titulaire'] ? 'Oui' : 'Non'; ?></td>
                        <td><?php echo (int)$stat['minutes_jouees']; ?></td>
                        <td><?php echo (int)$stat['buts']; ?></td>
                        <td><?php echo (int)$stat['passes_decisives']; ?></td>
                        <td>
                            <?php if ($stat['note'] !== null): ?>
                                <span style="
                                        display:inline-block;
                                        padding:0.2rem 0.6rem;
                                        border-radius:20px;
                                        font-size:0.85rem;
                                        font-weight:600;
                                        background:rgba(108,171,221,0.15);
                                        color:var(--bleu-city);
                                        border:1px solid rgba(108,171,221,0.3);">
                                    <?php echo htmlspecialchars($stat['note'], ENT_QUOTES, 'UTF-8'); ?>/10
                                </span>
                            <?php else: ?>
                                <span style="color:var(--gris-fonce);">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#e74c3c; font-weight:600;">
                            <?php echo (int)$stat['cartons_jaunes']; ?>
                        </td>
                        <td style="color:#c0392b; font-weight:600;">
                            <?php echo (int)$stat['cartons_rouges']; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<div style="margin-top:2rem;">
    <a href="players.php" class="bouton bouton-secondaire">
        Retour à l'effectif
    </a>
</div>

<?php require_once 'includes/footer.php'; ?>
