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
    'SELECT id, full_name, shirt_number, position, nationality, photo_url,
            valeur_marchande
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

// Valeur marchande — historique de carrière
$valeur_marchande = $joueur['valeur_marchande'];
$vm_labels = [];
$vm_data   = [];
try {
    $rh = $pdo->prepare(
        'SELECT annee, valeur FROM player_market_value_history
         WHERE player_id = ? ORDER BY annee ASC'
    );
    $rh->execute([$id_joueur]);
    foreach ($rh->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $vm_labels[] = (string)$row['annee'];
        $vm_data[]   = (float)$row['valeur'];
    }
} catch (PDOException $e) { }

// Transferts
$transferts = [];
try {
    $rt = $pdo->prepare(
        'SELECT annee, club_depart, club_arrivee, montant, type_transfert
         FROM player_transfers WHERE player_id = ? ORDER BY annee ASC'
    );
    $rt->execute([$id_joueur]);
    $transferts = $rt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { }

$tr_styles = [
    'transfer' => ['bg'=>'rgba(108,171,221,0.1)', 'border'=>'rgba(108,171,221,0.35)', 'color'=>'#6CABDD', 'label'=>'Transfert'],
    'free'     => ['bg'=>'rgba(46,204,113,0.08)', 'border'=>'rgba(46,204,113,0.3)',   'color'=>'#2ecc71', 'label'=>'Libre'],
    'loan'     => ['bg'=>'rgba(201,168,76,0.08)', 'border'=>'rgba(201,168,76,0.3)',   'color'=>'#C9A84C', 'label'=>'Prêt'],
    'academy'  => ['bg'=>'rgba(155,89,182,0.08)', 'border'=>'rgba(155,89,182,0.3)',   'color'=>'#9b59b6', 'label'=>'Formation'],
];
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
    <div style="margin-top:0.75rem;">
        <span style="
            background: rgba(201,168,76,0.12);
            color: #C9A84C;
            border: 1px solid rgba(201,168,76,0.35);
            border-radius: 20px;
            padding: 5px 18px;
            font-size: 0.88rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            <?php if ($valeur_marchande !== null): ?>
                Valeur marchande : €<?php echo number_format((float)$valeur_marchande, 0, ',', ' '); ?>&nbsp;M
            <?php else: ?>
                <span style="font-weight:400; opacity:0.7;">Valeur marchande — à venir</span>
            <?php endif; ?>
        </span>
    </div>
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

    <h3 style="color:var(--bleu-city); margin:1.5rem 0 1rem;">Valeur marchande</h3>
    <div style="margin-bottom:2rem;">
        <div class="carte">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
                <h4 style="color:var(--bleu-city); font-size:0.85rem; text-transform:uppercase; letter-spacing:1px; margin:0;">
                    Évolution depuis le début de carrière
                </h4>
                <span style="background:rgba(201,168,76,0.12); color:#C9A84C; border:1px solid rgba(201,168,76,0.35);
                             border-radius:20px; padding:3px 14px; font-size:0.82rem; font-weight:700;">
                    <?php if ($valeur_marchande !== null): ?>
                        Actuel : €<?php echo number_format((float)$valeur_marchande, 0, ',', ' '); ?>&nbsp;M
                    <?php else: ?>
                        <span style="opacity:0.6; font-weight:400; font-style:italic;">Données à venir</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (empty($vm_labels)): ?>
                <div style="height:140px; display:flex; flex-direction:column; align-items:center;
                            justify-content:center; background:rgba(201,168,76,0.03);
                            border-radius:10px; border:1px dashed rgba(201,168,76,0.18);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="rgba(201,168,76,0.35)" stroke-width="1.5" style="margin-bottom:0.6rem;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    <p style="color:rgba(138,155,176,0.55); font-size:0.85rem; margin:0; text-align:center; line-height:1.6;">
                        Historique de valeur marchande<br>
                        <small style="font-size:0.78rem;">Disponible après migration SQL</small>
                    </p>
                </div>
            <?php else: ?>
                <canvas id="graphiqueValeurMarchande" height="80"></canvas>
                <script>
                (function() {
                    const vmLabels = <?php echo json_encode($vm_labels); ?>;
                    const vmData   = <?php echo json_encode($vm_data); ?>;
                    const vmCanvas = document.getElementById('graphiqueValeurMarchande');
                    if (!vmCanvas || !vmLabels.length) return;
                    new Chart(vmCanvas, {
                        type: 'line',
                        data: {
                            labels: vmLabels,
                            datasets: [{
                                label: 'Valeur (M€)',
                                data: vmData,
                                borderColor: '#C9A84C',
                                backgroundColor: function(context) {
                                    const chart = context.chart;
                                    const {ctx: c, chartArea} = chart;
                                    if (!chartArea) return 'rgba(201,168,76,0.08)';
                                    const gradient = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                                    gradient.addColorStop(0, 'rgba(201,168,76,0.30)');
                                    gradient.addColorStop(1, 'rgba(201,168,76,0)');
                                    return gradient;
                                },
                                borderWidth: 2.5,
                                pointBackgroundColor: '#C9A84C',
                                pointBorderColor: '#0D1B2E',
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                pointHoverRadius: 8,
                                fill: true,
                                tension: 0.35,
                                spanGaps: true
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(13,27,46,0.95)',
                                    titleColor: '#C9A84C',
                                    bodyColor: '#E8EDF2',
                                    borderColor: 'rgba(201,168,76,0.3)',
                                    borderWidth: 1,
                                    callbacks: { label: ctx => '  €' + ctx.raw + ' M' }
                                }
                            },
                            scales: {
                                x: { ticks: { color: '#8A9BB0', font: { size: 10 } }, grid: { color: 'rgba(108,171,221,0.06)' } },
                                y: {
                                    ticks: { color: '#8A9BB0', font: { size: 10 }, callback: v => '€' + v + 'M' },
                                    grid: { color: 'rgba(108,171,221,0.06)' },
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>

    <h3 style="color:var(--bleu-city); margin:1.5rem 0 1rem;">Historique des transferts</h3>
    <div style="margin-bottom:2rem;">
        <div class="carte">
            <h4 style="color:var(--bleu-city); font-size:0.85rem; text-transform:uppercase; letter-spacing:1px; margin:0 0 1rem;">
                Carrière — Tous les transferts
            </h4>
            <?php if (empty($transferts)): ?>
                <p style="color:rgba(138,155,176,0.4); font-size:0.85rem; margin:0; text-align:center; padding:1.5rem 0;">
                    Disponible après migration SQL
                </p>
            <?php else: ?>
                <div class="transferts-grid">
                    <?php foreach ($transferts as $t):
                        $c = $tr_styles[$t['type_transfert']] ?? $tr_styles['transfer'];
                    ?>
                        <div class="transfert-bulle" style="background:<?php echo $c['bg']; ?>; border:1px solid <?php echo $c['border']; ?>;">
                            <span style="color:<?php echo $c['color']; ?>; font-weight:700; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px;">
                                <?php echo $c['label']; ?>
                            </span>
                            <span style="color:rgba(138,155,176,0.35);">|</span>
                            <span style="color:#8A9BB0; font-weight:600;"><?php echo (int)$t['annee']; ?></span>
                            <span style="color:#C8D6E5;">
                                <?php echo htmlspecialchars($t['club_depart'], ENT_QUOTES, 'UTF-8'); ?>
                                <span style="color:rgba(138,155,176,0.5);">→</span>
                                <?php echo htmlspecialchars($t['club_arrivee'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if ((float)$t['montant'] > 0): ?>
                                <span style="color:rgba(138,155,176,0.35);">·</span>
                                <span style="color:<?php echo $c['color']; ?>; font-weight:700;">€<?php echo number_format((float)$t['montant'], 1, ',', ''); ?>M</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
