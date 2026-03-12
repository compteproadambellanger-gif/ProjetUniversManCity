<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'fan';

$requete = $pdo->prepare('SELECT nom, email, role FROM users WHERE id = ?');
$requete->execute([$userId]);
$utilisateur = $requete->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$requete = $pdo->prepare('UPDATE users SET derniere_activite = NOW() WHERE id = ?');
$requete->execute([$userId]);
?>

<?php require_once 'includes/header.php'; ?>
<?php
$prenoms = explode(' ', $utilisateur['nom']);
$prenom = $prenoms[0];

$messages = [
    "Bon retour parmi nous, <strong>" . htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8') . "</strong> ! Prêt pour une nouvelle journée ?",
    "Bienvenue sur le Dashboard, <strong>" . htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8') . "</strong> ! Que la victoire soit avec vous.",
    "Content de vous revoir, <strong>" . htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8') . "</strong> ! Les Citizens comptent sur vous.",
    "Bonjour <strong>" . htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8') . "</strong> ! Manchester City vous attend.",
    "Ravi de vous voir, <strong>" . htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8') . "</strong> ! Let's go City !",
];

$message_aleatoire = $messages[array_rand($messages)];
?>

<div style="text-align:center; padding: 3rem 0 2rem 0;">
    <p style="
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--blanc);
        margin-bottom: 0.8rem;
        letter-spacing: 1px;">
        <?php echo $message_aleatoire; ?>
    </p>
    <h1 style="
        font-size: 1rem;
        font-weight: 400;
        color: var(--gris-fonce);
        letter-spacing: 3px;
        text-transform: uppercase;
        margin-top: 0.5rem;">
        Tableau de bord — <?php echo htmlspecialchars($utilisateur['role'], ENT_QUOTES, 'UTF-8'); ?>
    </h1>
</div>

<?php if ($role === 'staff'): ?>

    <?php
    $requete = $pdo->query('SELECT COUNT(*) FROM players');
    $total_joueurs = $requete->fetchColumn();

    $requete = $pdo->query('SELECT COUNT(*) FROM matchs');
    $total_matchs = $requete->fetchColumn();

    $requete = $pdo->query('SELECT COUNT(*) FROM matchs WHERE goals_city > goals_opponent');
    $total_victoires = $requete->fetchColumn();

    $requete = $pdo->query('SELECT COUNT(*) FROM matchs WHERE goals_city < goals_opponent');
    $total_defaites = $requete->fetchColumn();

    $requete = $pdo->query('SELECT COUNT(*) FROM matchs WHERE goals_city = goals_opponent');
    $total_nuls = $requete->fetchColumn();

    $requete = $pdo->query('SELECT SUM(goals_city) FROM matchs');
    $total_buts_city = $requete->fetchColumn() ?? 0;

    $requete = $pdo->query(
        'SELECT p.full_name, SUM(s.buts) AS total_buts
             FROM player_match_stats s
             JOIN players p ON s.player_id = p.id
             GROUP BY s.player_id
             ORDER BY total_buts DESC
             LIMIT 1'
    );
    $meilleur_buteur = $requete->fetch(PDO::FETCH_ASSOC);

    $requete = $pdo->query(
        'SELECT p.full_name, SUM(s.passes_decisives) AS total_passes
             FROM player_match_stats s
             JOIN players p ON s.player_id = p.id
             GROUP BY s.player_id
             ORDER BY total_passes DESC
             LIMIT 1'
    );
    $meilleur_passeur = $requete->fetch(PDO::FETCH_ASSOC);

    $requete = $pdo->query(
        'SELECT p.full_name, ROUND(AVG(s.note), 2) AS note_moyenne
             FROM player_match_stats s
             JOIN players p ON s.player_id = p.id
             WHERE s.note IS NOT NULL
             GROUP BY s.player_id
             HAVING COUNT(s.id) >= 1
             ORDER BY note_moyenne DESC
             LIMIT 1'
    );
    $meilleure_note = $requete->fetch(PDO::FETCH_ASSOC);
    ?>

    <h2 class="titre-page" style="font-size:1.4rem; margin-bottom:1rem;">
        Admin / Coach
    </h2>

    <h3 style="color:var(--bleu-city); margin-bottom:1rem;">Résumé de la saison</h3>

    <div class="grille-stats">
        <div class="stat-card">
            <span class="stat-valeur"><?php echo (int)$total_joueurs; ?></span>
            <span class="stat-label">Joueurs</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur"><?php echo (int)$total_matchs; ?></span>
            <span class="stat-label">Matchs joués</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur" style="color:#2ecc71;">
                <?php echo (int)$total_victoires; ?>
            </span>
            <span class="stat-label">Victoires</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur" style="color:var(--or);">
                <?php echo (int)$total_nuls; ?>
            </span>
            <span class="stat-label">Nuls</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur" style="color:#e74c3c;">
                <?php echo (int)$total_defaites; ?>
            </span>
            <span class="stat-label">Défaites</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur"><?php echo (int)$total_buts_city; ?></span>
            <span class="stat-label">Buts marqués</span>
        </div>
    </div>
    <h3 style="color:var(--bleu-city); margin: 1.5rem 0 1rem;">Statistiques de la saison</h3>

    <?php
    $requete = $pdo->query(
        'SELECT p.full_name,
                    SUM(s.buts) AS total_buts,
                    SUM(s.passes_decisives) AS total_passes
            FROM player_match_stats s
            JOIN players p ON s.player_id = p.id
            GROUP BY s.player_id
            ORDER BY total_buts DESC
            LIMIT 5'
    );
    $stats_top = $requete->fetchAll(PDO::FETCH_ASSOC);

    $requete = $pdo->query(
        'SELECT p.full_name,
                    SUM(s.minutes_jouees) AS total_minutes
            FROM player_match_stats s
            JOIN players p ON s.player_id = p.id
            GROUP BY s.player_id
            ORDER BY total_minutes DESC'
    );
    $stats_minutes = $requete->fetchAll(PDO::FETCH_ASSOC);

    $noms_top = [];
    $buts_graphique = [];
    $passes_graphique = [];

    foreach ($stats_top as $sg) {
        $noms_top[] = htmlspecialchars($sg['full_name'], ENT_QUOTES, 'UTF-8');
        $buts_graphique[] = (int)$sg['total_buts'];
        $passes_graphique[] = (int)$sg['total_passes'];
    }

    $noms_minutes = [];
    $minutes_graphique = [];

    foreach ($stats_minutes as $sm) {
        $noms_minutes[] = htmlspecialchars($sm['full_name'], ENT_QUOTES, 'UTF-8');
        $minutes_graphique[] = (int)$sm['total_minutes'];
    }

    $noms_top_json     = json_encode($noms_top);
    $buts_json         = json_encode($buts_graphique);
    $passes_json       = json_encode($passes_graphique);
    $noms_minutes_json = json_encode($noms_minutes);
    $minutes_json      = json_encode($minutes_graphique);
    ?>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:2rem;">

        <div class="carte">
            <h4 style="color:var(--bleu-city); margin-bottom:1rem; font-size:0.85rem;
                   letter-spacing:1px; text-transform:uppercase;">
                Top buteurs
            </h4>
            <canvas id="graphiqueButs" height="200"></canvas>
        </div>

        <div class="carte">
            <h4 style="color:var(--bleu-city); margin-bottom:1rem; font-size:0.85rem;
                   letter-spacing:1px; text-transform:uppercase;">
                Top passeurs
            </h4>
            <canvas id="graphiquePasses" height="200"></canvas>
        </div>

        <div class="carte" style="grid-column: 1 / -1;">
            <h4 style="color:var(--bleu-city); margin-bottom:1rem; font-size:0.85rem;
                   letter-spacing:1px; text-transform:uppercase;">
                Minutes jouées par joueur
            </h4>
            <canvas id="graphiqueMinutes" height="100"></canvas>
        </div>

    </div>

    <script>
        const noms_top = <?php echo $noms_top_json; ?>;
        const buts = <?php echo $buts_json; ?>;
        const passes = <?php echo $passes_json; ?>;
        const noms_minutes = <?php echo $noms_minutes_json; ?>;
        const minutes = <?php echo $minutes_json; ?>;

        const optionsCommunes = {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(13,27,46,0.9)',
                    titleColor: '#6CABDD',
                    bodyColor: '#E8EDF2',
                    borderColor: 'rgba(108,171,221,0.3)',
                    borderWidth: 1,
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#8A9BB0',
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: 'rgba(108,171,221,0.08)'
                    }
                },
                y: {
                    ticks: {
                        color: '#8A9BB0',
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: 'rgba(108,171,221,0.08)'
                    },
                    beginAtZero: true
                }
            }
        };

        new Chart(document.getElementById('graphiqueButs'), {
            type: 'bar',
            data: {
                labels: noms_top,
                datasets: [{
                    data: buts,
                    backgroundColor: 'rgba(108,171,221,0.3)',
                    borderColor: '#6CABDD',
                    borderWidth: 2,
                    borderRadius: 8,
                }]
            },
            options: optionsCommunes
        });

        new Chart(document.getElementById('graphiquePasses'), {
            type: 'bar',
            data: {
                labels: noms_top,
                datasets: [{
                    data: passes,
                    backgroundColor: 'rgba(46,204,113,0.2)',
                    borderColor: '#2ecc71',
                    borderWidth: 2,
                    borderRadius: 8,
                }]
            },
            options: optionsCommunes
        });

        new Chart(document.getElementById('graphiqueMinutes'), {
            type: 'line',
            data: {
                labels: noms_minutes,
                datasets: [{
                    data: minutes,
                    backgroundColor: 'rgba(201,168,76,0.1)',
                    borderColor: '#C9A84C',
                    borderWidth: 2,
                    pointBackgroundColor: '#C9A84C',
                    pointBorderColor: '#C9A84C',
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(13,27,46,0.9)',
                        titleColor: '#C9A84C',
                        bodyColor: '#E8EDF2',
                        borderColor: 'rgba(201,168,76,0.3)',
                        borderWidth: 1,
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#8A9BB0',
                            font: {
                                size: 10
                            },
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: {
                            color: 'rgba(108,171,221,0.08)'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#8A9BB0',
                            font: {
                                size: 11
                            }
                        },
                        grid: {
                            color: 'rgba(108,171,221,0.08)'
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

    <div class="grille-stats" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <div class="stat-card">
            <span class="stat-label">Meilleur buteur</span>
            <span class="stat-valeur" style="font-size:1.1rem; margin-top:0.5rem;">
                <?php if ($meilleur_buteur): ?>
                    <?php echo htmlspecialchars($meilleur_buteur['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <small style="color:var(--gris-fonce); font-size:0.85rem; display:block;">
                        <?php echo (int)$meilleur_buteur['total_buts']; ?> buts
                    </small>
                <?php else: ?>
                    <small style="color:var(--gris-fonce);">Aucune donnée</small>
                <?php endif; ?>
            </span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Meilleur passeur</span>
            <span class="stat-valeur" style="font-size:1.1rem; margin-top:0.5rem;">
                <?php if ($meilleur_passeur): ?>
                    <?php echo htmlspecialchars($meilleur_passeur['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <small style="color:var(--gris-fonce); font-size:0.85rem; display:block;">
                        <?php echo (int)$meilleur_passeur['total_passes']; ?> passes
                    </small>
                <?php else: ?>
                    <small style="color:var(--gris-fonce);">Aucune donnée</small>
                <?php endif; ?>
            </span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Meilleure note</span>
            <span class="stat-valeur" style="font-size:1.1rem; margin-top:0.5rem;">
                <?php if ($meilleure_note): ?>
                    <?php echo htmlspecialchars($meilleure_note['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <small style="color:var(--gris-fonce); font-size:0.85rem; display:block;">
                        <?php echo htmlspecialchars($meilleure_note['note_moyenne'], ENT_QUOTES, 'UTF-8'); ?>/10
                    </small>
                <?php else: ?>
                    <small style="color:var(--gris-fonce);">Aucune donnée</small>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <h3 style="color:var(--bleu-city); margin-bottom:1rem;">Administration</h3>
    <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:2rem;">
        <a href="includes/admin/players.php" class="bouton bouton-secondaire">
            Gérer les joueurs
        </a>
        <a href="includes/admin/matchs.php" class="bouton bouton-secondaire">
            Gérer les matchs
        </a>
        <a href="includes/admin/stats_create.php" class="bouton bouton-secondaire">
            Ajouter des stats
        </a>
    </div>

<?php elseif ($role === 'player'): ?>

    <?php
    $requete = $pdo->prepare(
        'SELECT id, full_name, shirt_number, position, nationality, photo_url,
                valeur_marchande
         FROM players WHERE user_id = ?'
    );
    $requete->execute([$_SESSION['user_id']]);
    $joueur = $requete->fetch(PDO::FETCH_ASSOC);
    ?>

    <h2 class="titre-page" style="font-size:1.4rem; margin-bottom:1rem;">Zone Joueur</h2>

    <?php if (!$joueur): ?>
        <div class="carte" style="max-width:500px; margin:0 auto; text-align:center; padding:2rem;">
            <p style="color:var(--gris-fonce); margin-bottom:1rem;">Aucun profil joueur trouvé pour votre compte.</p>
            <p style="color:var(--gris-fonce); font-size:0.9rem;">Contactez le staff pour lier votre compte.</p>
        </div>
    <?php else: ?>

        <?php
        $requete = $pdo->prepare(
            'SELECT m.match_date, m.competition, m.home_away, m.opponent,
                    m.goals_city, m.goals_opponent,
                    s.titulaire, s.minutes_jouees, s.buts, s.passes_decisives,
                    s.note, s.cartons_jaunes, s.cartons_rouges
             FROM player_match_stats s
             JOIN matchs m ON s.match_id = m.id
             WHERE s.player_id = ?
             ORDER BY m.match_date DESC'
        );
        $requete->execute([$joueur['id']]);
        $stats_matchs = $requete->fetchAll(PDO::FETCH_ASSOC);

        $total_buts = $total_passes = $total_minutes = 0;
        $total_jaunes = $total_rouges = $total_notes = $nb_notes = 0;
        $nb_titulaire = 0;

        foreach ($stats_matchs as $stat) {
            $total_buts    += (int)$stat['buts'];
            $total_passes  += (int)$stat['passes_decisives'];
            $total_minutes += (int)$stat['minutes_jouees'];
            $total_jaunes  += (int)$stat['cartons_jaunes'];
            $total_rouges  += (int)$stat['cartons_rouges'];
            if ($stat['titulaire']) $nb_titulaire++;
            if ($stat['note'] !== null) {
                $total_notes += (float)$stat['note'];
                $nb_notes++;
            }
        }
        $note_moyenne = $nb_notes > 0 ? round($total_notes / $nb_notes, 1) : null;

        // Valeur marchande — historique de carrière
        $valeur_marchande = $joueur['valeur_marchande'];
        $vm_labels = [];
        $vm_data   = [];
        try {
            $rh = $pdo->prepare(
                'SELECT annee, valeur FROM player_market_value_history
                 WHERE player_id = ? ORDER BY annee ASC'
            );
            $rh->execute([$joueur['id']]);
            foreach ($rh->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $vm_labels[] = (string)$row['annee'];
                $vm_data[]   = (float)$row['valeur'];
            }
        } catch (PDOException $e) { /* table pas encore créée */ }

        // Transferts
        $transferts_dash = [];
        try {
            $rt2 = $pdo->prepare(
                'SELECT annee, club_depart, club_arrivee, montant, type_transfert
                 FROM player_transfers WHERE player_id = ? ORDER BY annee ASC'
            );
            $rt2->execute([$joueur['id']]);
            $transferts_dash = $rt2->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { /* table pas encore créée */ }

        $tr_styles_d = [
            'transfer' => ['bg'=>'rgba(108,171,221,0.1)', 'border'=>'rgba(108,171,221,0.35)', 'color'=>'#6CABDD', 'label'=>'Transfert'],
            'free'     => ['bg'=>'rgba(46,204,113,0.08)', 'border'=>'rgba(46,204,113,0.3)',   'color'=>'#2ecc71', 'label'=>'Libre'],
            'loan'     => ['bg'=>'rgba(201,168,76,0.08)', 'border'=>'rgba(201,168,76,0.3)',   'color'=>'#C9A84C', 'label'=>'Prêt'],
            'academy'  => ['bg'=>'rgba(155,89,182,0.08)', 'border'=>'rgba(155,89,182,0.3)',   'color'=>'#9b59b6', 'label'=>'Formation'],
        ];

        $pos_labels = ['GK' => 'Gardien', 'DEF' => 'Défenseur', 'MID' => 'Milieu', 'FWD' => 'Attaquant'];
        $pos_colors = ['GK' => '#f39c12', 'DEF' => '#3498db', 'MID' => '#2ecc71', 'FWD' => '#e74c3c'];
        $pos = $joueur['position'];
        $pos_label = $pos_labels[$pos] ?? $pos;
        $pos_color = $pos_colors[$pos] ?? 'var(--bleu-city)';
        ?>

        <div class="carte" style="display:flex; align-items:center; gap:1.5rem; margin-bottom:1.5rem; flex-wrap:wrap;">
            <?php
            $photo = $joueur['photo_url'] ?? '';
            $initiales = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', $joueur['full_name']))));
            $initiales = substr($initiales, 0, 2);
            ?>
            <?php if ($photo && file_exists($photo)): ?>
                <img src="<?php echo htmlspecialchars($photo, ENT_QUOTES, 'UTF-8'); ?>"
                     style="width:80px;height:80px;border-radius:50%;object-fit:cover;object-position:top;border:3px solid <?php echo $pos_color; ?>;">
            <?php else: ?>
                <div style="width:80px;height:80px;border-radius:50%;background:<?php echo $pos_color; ?>20;
                            border:3px solid <?php echo $pos_color; ?>;display:flex;align-items:center;
                            justify-content:center;font-size:1.6rem;font-weight:800;color:<?php echo $pos_color; ?>;
                            flex-shrink:0;">
                    <?php echo htmlspecialchars($initiales, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.3rem;">
                    <h3 style="color:var(--blanc);font-size:1.3rem;margin:0;">
                        <?php echo htmlspecialchars($joueur['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </h3>
                    <span style="background:<?php echo $pos_color; ?>20;color:<?php echo $pos_color; ?>;
                                 border:1px solid <?php echo $pos_color; ?>40;border-radius:6px;
                                 padding:2px 10px;font-size:0.8rem;font-weight:700;">
                        <?php echo $pos_label; ?>
                    </span>
                </div>
                <p style="color:var(--gris-fonce);margin:0;font-size:0.9rem;">
                    N°<?php echo (int)$joueur['shirt_number']; ?>
                    &nbsp;·&nbsp;
                    <?php echo htmlspecialchars($joueur['nationality'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <div style="margin-top:0.55rem;">
                    <span style="
                        background:rgba(201,168,76,0.12); color:#C9A84C;
                        border:1px solid rgba(201,168,76,0.35); border-radius:20px;
                        padding:3px 14px; font-size:0.8rem; font-weight:700;
                        display:inline-flex; align-items:center; gap:0.35rem;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        <?php if ($valeur_marchande !== null): ?>
                            €<?php echo number_format((float)$valeur_marchande, 0, ',', ' '); ?>&nbsp;M
                        <?php else: ?>
                            <span style="font-weight:400; opacity:0.65; font-style:italic;">Valeur marchande — à venir</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <a href="player_stats.php?id=<?php echo (int)$joueur['id']; ?>"
               class="bouton bouton-secondaire" style="font-size:0.85rem;">
                Voir fiche publique
            </a>
        </div>

        <h3 style="color:var(--bleu-city); margin-bottom:1rem;">Saison 2025/26 — Mes stats</h3>
        <div class="grille-stats">
            <div class="stat-card">
                <span class="stat-valeur"><?php echo count($stats_matchs); ?></span>
                <span class="stat-label">Matchs</span>
            </div>
            <div class="stat-card">
                <span class="stat-valeur"><?php echo $nb_titulaire; ?></span>
                <span class="stat-label">Titularisations</span>
            </div>
            <div class="stat-card">
                <span class="stat-valeur"><?php echo (int)$total_minutes; ?></span>
                <span class="stat-label">Minutes</span>
            </div>
            <div class="stat-card">
                <span class="stat-valeur" style="color:#2ecc71;"><?php echo (int)$total_buts; ?></span>
                <span class="stat-label">Buts</span>
            </div>
            <div class="stat-card">
                <span class="stat-valeur" style="color:var(--bleu-city);"><?php echo (int)$total_passes; ?></span>
                <span class="stat-label">Passes déc.</span>
            </div>
            <div class="stat-card">
                <span class="stat-valeur" style="color:var(--or);">
                    <?php echo $note_moyenne !== null ? $note_moyenne : '-'; ?>
                </span>
                <span class="stat-label">Note moy.</span>
            </div>
            <?php if ($total_jaunes > 0 || $total_rouges > 0): ?>
            <div class="stat-card">
                <span class="stat-valeur" style="color:#f39c12;"><?php echo (int)$total_jaunes; ?></span>
                <span class="stat-label">Cartons J.</span>
            </div>
            <?php endif; ?>
        </div>

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
                    <canvas id="graphiqueValeurMarchandeDashboard" height="80"></canvas>
                    <script>
                    (function() {
                        const vmLabels = <?php echo json_encode($vm_labels); ?>;
                        const vmData   = <?php echo json_encode($vm_data); ?>;
                        const vmCanvas = document.getElementById('graphiqueValeurMarchandeDashboard');
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
                <?php if (empty($transferts_dash)): ?>
                    <p style="color:rgba(138,155,176,0.4); font-size:0.85rem; margin:0; text-align:center; padding:1.5rem 0;">
                        Disponible après migration SQL
                    </p>
                <?php else: ?>
                    <div class="transferts-grid">
                        <?php foreach ($transferts_dash as $t):
                            $c = $tr_styles_d[$t['type_transfert']] ?? $tr_styles_d['transfer'];
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

        <?php if (empty($stats_matchs)): ?>
            <p style="color:var(--gris-fonce);">Aucune statistique enregistrée pour le moment.</p>
        <?php else: ?>
            <div class="tableau-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Adversaire</th>
                            <th>Compétition</th>
                            <th>Résultat</th>
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
                        <?php foreach ($stats_matchs as $stat):
                            $gc = (int)$stat['goals_city'];
                            $go = (int)$stat['goals_opponent'];
                            if ($gc > $go)      { $res = 'V'; $res_color = '#2ecc71'; }
                            elseif ($gc === $go) { $res = 'N'; $res_color = 'var(--or)'; }
                            else                 { $res = 'D'; $res_color = '#e74c3c'; }
                            $note = $stat['note'];
                            if ($note !== null) {
                                $note_color = $note >= 7.5 ? '#2ecc71' : ($note >= 6 ? 'var(--or)' : '#e74c3c');
                            }
                        ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($stat['match_date'])); ?></td>
                                <td><?php echo htmlspecialchars($stat['opponent'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="font-size:0.82rem;color:var(--gris-fonce);">
                                    <?php echo htmlspecialchars($stat['competition'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td>
                                    <span style="font-weight:700;color:<?php echo $res_color; ?>;">
                                        <?php echo $gc; ?>-<?php echo $go; ?>
                                    </span>
                                    <span style="margin-left:4px;font-size:0.75rem;background:<?php echo $res_color; ?>20;
                                                color:<?php echo $res_color; ?>;border-radius:4px;padding:1px 6px;">
                                        <?php echo $res; ?>
                                    </span>
                                </td>
                                <td><?php echo $stat['titulaire'] ? '✓' : '<span style="color:var(--gris-fonce);">—</span>'; ?></td>
                                <td><?php echo (int)$stat['minutes_jouees']; ?>'</td>
                                <td style="font-weight:<?php echo $stat['buts'] > 0 ? '700' : '400'; ?>;
                                           color:<?php echo $stat['buts'] > 0 ? '#2ecc71' : 'inherit'; ?>">
                                    <?php echo (int)$stat['buts']; ?>
                                </td>
                                <td style="font-weight:<?php echo $stat['passes_decisives'] > 0 ? '700' : '400'; ?>;
                                           color:<?php echo $stat['passes_decisives'] > 0 ? 'var(--bleu-city)' : 'inherit'; ?>">
                                    <?php echo (int)$stat['passes_decisives']; ?>
                                </td>
                                <td>
                                    <?php if ($note !== null): ?>
                                        <span style="font-weight:700;color:<?php echo $note_color; ?>;">
                                            <?php echo $note; ?>
                                        </span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?php echo (int)$stat['cartons_jaunes'] ?: '—'; ?></td>
                                <td><?php echo (int)$stat['cartons_rouges'] ?: '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>

<?php else: ?>

    <?php
    $dernier_match = $pdo->query(
        'SELECT * FROM matchs WHERE match_date <= CURDATE() ORDER BY match_date DESC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);

    $derniers_5 = $pdo->query(
        'SELECT goals_city, goals_opponent FROM matchs WHERE match_date <= CURDATE() ORDER BY match_date DESC LIMIT 5'
    )->fetchAll(PDO::FETCH_ASSOC);

    $total_v_fan = (int)$pdo->query('SELECT COUNT(*) FROM matchs WHERE match_date <= CURDATE() AND goals_city > goals_opponent')->fetchColumn();
    $total_n_fan = (int)$pdo->query('SELECT COUNT(*) FROM matchs WHERE match_date <= CURDATE() AND goals_city = goals_opponent')->fetchColumn();
    $total_d_fan = (int)$pdo->query('SELECT COUNT(*) FROM matchs WHERE match_date <= CURDATE() AND goals_city < goals_opponent')->fetchColumn();
    $total_buts_fan = (int)($pdo->query('SELECT SUM(goals_city) FROM matchs WHERE match_date <= CURDATE()')->fetchColumn() ?? 0);
    $total_matchs_fan = $total_v_fan + $total_n_fan + $total_d_fan;

    $top_buteur_fan = $pdo->query(
        'SELECT p.full_name, SUM(s.buts) AS total_buts
         FROM player_match_stats s JOIN players p ON s.player_id = p.id
         GROUP BY s.player_id ORDER BY total_buts DESC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);

    $top_passeur_fan = $pdo->query(
        'SELECT p.full_name, SUM(s.passes_decisives) AS total_passes
         FROM player_match_stats s JOIN players p ON s.player_id = p.id
         GROUP BY s.player_id ORDER BY total_passes DESC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);
    ?>

    <h2 class="titre-page" style="font-size:1.4rem; margin-bottom:2rem;">Zone Supporter</h2>

    <?php if ($dernier_match): ?>
        <?php
        $gc = (int)$dernier_match['goals_city'];
        $go = (int)$dernier_match['goals_opponent'];
        if ($gc > $go)      { $res_label = 'Victoire'; $res_color = '#2ecc71'; }
        elseif ($gc === $go) { $res_label = 'Nul';      $res_color = 'var(--or)'; }
        else                 { $res_label = 'Défaite';  $res_color = '#e74c3c'; }

        $date_fan = date('d/m/Y', strtotime($dernier_match['match_date']));
        $lieu_fan = $dernier_match['home_away'] === 'HOME' ? 'Domicile' : 'Extérieur';

        $youtube_id = null;
        if (!empty($dernier_match['youtube_url'])) {
            preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $dernier_match['youtube_url'], $yt_matches);
            $youtube_id = $yt_matches[1] ?? null;
        }
        ?>

        <h3 style="color:var(--bleu-city); margin-bottom:1rem;">Dernier match</h3>
        <div class="carte fan-dernier-match">
            <div class="fan-match-header">
                <span class="fan-competition"><?php echo htmlspecialchars($dernier_match['competition'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="fan-date"><?php echo $date_fan; ?> · <?php echo $lieu_fan; ?></span>
            </div>
            <div class="fan-score-row">
                <span class="fan-team">Manchester City</span>
                <span class="fan-score"><?php echo $gc; ?> — <?php echo $go; ?></span>
                <span class="fan-team"><?php echo htmlspecialchars($dernier_match['opponent'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <span class="fan-result-badge" style="color:<?php echo $res_color; ?>; border-color:<?php echo $res_color; ?>;">
                <?php echo $res_label; ?>
            </span>
        </div>

        <?php if ($youtube_id): ?>
        <div class="carte" style="margin-top:1.5rem;">
            <h4 style="color:var(--bleu-city); margin-bottom:1rem; font-size:0.85rem; letter-spacing:1px; text-transform:uppercase;">Résumé vidéo</h4>
            <div class="fan-video-wrapper" id="fan-video-container">
                <iframe
                    id="fan-iframe"
                    src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtube_id, ENT_QUOTES, 'UTF-8'); ?>?rel=0"
                    title="Résumé du match"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            </div>
            <div id="fan-video-fallback" style="display:none; text-align:center; padding:1rem 0;">
                <p style="color:var(--gris-fonce); margin-bottom:1rem; font-size:0.9rem;">Cette vidéo ne peut pas être lue ici.</p>
                <a href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($youtube_id, ENT_QUOTES, 'UTF-8'); ?>"
                   target="_blank" rel="noopener" class="bouton bouton-principal">
                    Voir sur YouTube
                </a>
            </div>
        </div>
        <script>
        (function() {
            var iframe = document.getElementById('fan-iframe');
            if (!iframe) return;
            iframe.addEventListener('error', function() {
                document.getElementById('fan-video-container').style.display = 'none';
                document.getElementById('fan-video-fallback').style.display = 'block';
            });
            setTimeout(function() {
                try {
                    var h = iframe.contentWindow.document.body;
                    if (!h) throw new Error();
                } catch(e) {
                }
            }, 3000);
        })();
        </script>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($derniers_5)): ?>
    <h3 style="color:var(--bleu-city); margin: 1.2rem 0 0.8rem;">Forme récente</h3>
    <div class="carte" style="display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap;">
        <span style="color:var(--gris-fonce); font-size:0.82rem; letter-spacing:1px; text-transform:uppercase;">5 derniers matchs</span>
        <div class="fan-forme">
            <?php foreach ($derniers_5 as $fm):
                $fg = (int)$fm['goals_city'];
                $fo = (int)$fm['goals_opponent'];
                if ($fg > $fo)       { $fl = 'V'; $fc = '#2ecc71'; }
                elseif ($fg === $fo)  { $fl = 'N'; $fc = 'var(--or)'; }
                else                  { $fl = 'D'; $fc = '#e74c3c'; }
            ?>
                <span class="fan-forme-badge" style="background:<?php echo $fc; ?>20; color:<?php echo $fc; ?>; border-color:<?php echo $fc; ?>;">
                    <?php echo $fl; ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <h3 style="color:var(--bleu-city); margin: 1.2rem 0 0.8rem;">Saison en cours</h3>
    <div class="grille-stats">
        <div class="stat-card">
            <span class="stat-valeur"><?php echo $total_matchs_fan; ?></span>
            <span class="stat-label">Matchs joués</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur" style="color:#2ecc71;"><?php echo $total_v_fan; ?></span>
            <span class="stat-label">Victoires</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur" style="color:var(--or);"><?php echo $total_n_fan; ?></span>
            <span class="stat-label">Nuls</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur" style="color:#e74c3c;"><?php echo $total_d_fan; ?></span>
            <span class="stat-label">Défaites</span>
        </div>
        <div class="stat-card">
            <span class="stat-valeur"><?php echo $total_buts_fan; ?></span>
            <span class="stat-label">Buts marqués</span>
        </div>
    </div>

    <h3 style="color:var(--bleu-city); margin: 1.2rem 0 0.8rem;">Stars de la saison</h3>
    <div class="grille-stats" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <div class="stat-card">
            <span class="stat-label">Meilleur buteur</span>
            <span class="stat-valeur" style="font-size:1.1rem; margin-top:0.5rem;">
                <?php if ($top_buteur_fan): ?>
                    <?php echo htmlspecialchars($top_buteur_fan['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <small style="color:var(--gris-fonce); font-size:0.85rem; display:block;"><?php echo (int)$top_buteur_fan['total_buts']; ?> buts</small>
                <?php else: ?><small style="color:var(--gris-fonce);">Aucune donnée</small><?php endif; ?>
            </span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Meilleur passeur</span>
            <span class="stat-valeur" style="font-size:1.1rem; margin-top:0.5rem;">
                <?php if ($top_passeur_fan): ?>
                    <?php echo htmlspecialchars($top_passeur_fan['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <small style="color:var(--gris-fonce); font-size:0.85rem; display:block;"><?php echo (int)$top_passeur_fan['total_passes']; ?> passes déc.</small>
                <?php else: ?><small style="color:var(--gris-fonce);">Aucune donnée</small><?php endif; ?>
            </span>
        </div>
    </div>

    <h3 style="color:var(--bleu-city); margin: 1.2rem 0 0.8rem;">Explorer</h3>
    <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:2rem;">
        <a href="players.php" class="bouton bouton-secondaire">Voir l'effectif</a>
        <a href="matchs.php" class="bouton bouton-secondaire">Voir les matchs</a>
        <a href="prochains_matchs.php" class="bouton bouton-secondaire">Prochains matchs</a>
    </div>

<?php endif; ?>
<div style="margin-top: 20rem;"></div>
<?php require_once 'includes/footer.php'; ?>