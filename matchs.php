<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'] ?? 'fan';

$matchs = $pdo->query(
    'SELECT id, opponent, competition, match_date, home_away, goals_city, goals_opponent
     FROM matchs WHERE match_date <= CURDATE() ORDER BY match_date DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$nb_prochains = (int)$pdo->query('SELECT COUNT(*) FROM matchs WHERE match_date > CURDATE()')->fetchColumn();
$total = count($matchs);
?>

<?php require_once 'includes/header.php'; ?>

<h1 class="titre-page">Matchs de Manchester City</h1>
<p class="sous-titre-page">Saison 2025/2026 — <?php echo $total; ?> matchs joués</p>

<div class="search-bar-wrapper">
    <div class="search-bar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M10.5 3C14.6421 3 18 6.35786 18 10.5C18 12.3819 17.3061 14.1017 16.1627 15.4556L20.8536 20.1464C21.0488 20.3417 21.0488 20.6583 20.8536 20.8536C20.6583 21.0488 20.3417 21.0488 20.1464 20.8536L15.4556 16.1627C14.1017 17.3061 12.3819 18 10.5 18C6.35786 18 3 14.6421 3 10.5C3 6.35786 6.35786 3 10.5 3ZM10.5 4C6.91015 4 4 6.91015 4 10.5C4 14.0899 6.91015 17 10.5 17C14.0899 17 17 14.0899 17 10.5C17 6.91015 14.0899 4 10.5 4Z" fill="currentColor"/>
        </svg>
        <input type="text" id="searchInput" placeholder="Adversaire, compétition, résultat, date..." autocomplete="off">
        <button class="search-clear" id="searchClear" title="Effacer">✕</button>
    </div>
</div>
<p class="search-meta" id="searchMeta"></p>

<div style="display:flex; justify-content:flex-end; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:0.5rem;">
    <?php if ($nb_prochains > 0): ?>
        <a href="prochains_matchs.php" class="bouton bouton-secondaire"
           style="padding:0.5rem 1.2rem; font-size:0.85rem; border-radius:20px;">
            Prochains matchs
            <span style="background:rgba(201,168,76,0.2); color:#C9A84C; border-radius:20px;
                         padding:1px 7px; font-size:0.75rem; margin-left:0.4rem; font-weight:700;">
                <?php echo $nb_prochains; ?>
            </span>
        </a>
    <?php endif; ?>
    <?php if ($role === 'staff'): ?>
        <a href="includes/admin/matchs.php" class="bouton bouton-secondaire"
           style="padding:0.5rem 1.2rem; font-size:0.85rem; border-radius:20px;">
            Gérer les matchs
        </a>
    <?php endif; ?>
</div>

<div class="tableau-container">
    <table id="matchsTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Compétition</th>
                <th>Lieu</th>
                <th>Adversaire</th>
                <th>Score</th>
                <th>Résultat</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($matchs as $match): ?>
                <?php
                if ((int)$match['goals_city'] > (int)$match['goals_opponent']) {
                    $couleur = '#2ecc71'; $resultat = 'Victoire';
                } elseif ((int)$match['goals_city'] < (int)$match['goals_opponent']) {
                    $couleur = '#e74c3c'; $resultat = 'Défaite';
                } else {
                    $couleur = 'var(--or)'; $resultat = 'Nul';
                }
                ?>
                <tr class="match-row">
                    <td class="match-date"><?php echo htmlspecialchars($match['match_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="match-comp"><?php echo htmlspecialchars($match['competition'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="match-loc"><?php echo $match['home_away'] === 'HOME' ? 'Domicile' : 'Extérieur'; ?></td>
                    <td class="match-opp"><?php echo htmlspecialchars($match['opponent'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="font-weight:700; color:var(--bleu-city); font-family:'Barlow Condensed',sans-serif; font-size:1.15rem; text-align:center; letter-spacing:1px;">
                        <?php echo (int)$match['goals_city'] . ' — ' . (int)$match['goals_opponent']; ?>
                    </td>
                    <td>
                        <span class="match-res" style="
                            display:inline-block; padding:0.25rem 0.8rem;
                            border-radius:20px; font-size:0.8rem; font-weight:600;
                            background:<?php echo $couleur; ?>22;
                            color:<?php echo $couleur; ?>;
                            border:1px solid <?php echo $couleur; ?>44;">
                            <?php echo $resultat; ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="search-empty" id="searchEmpty">
        <span class="search-empty-icon">🔍</span>
        <p>Aucun match trouvé pour <strong id="searchEmptyTerm"></strong></p>
    </div>
</div>

<div id="pagination" class="pagination"></div>
<p id="paginationInfo" class="pagination-info"></p>

<script src="includes/table-filter.js"></script>
<script>
initTableFilter({ rowClass: 'match-row', perPage: 10, searchFields: ['.match-opp', '.match-comp', '.match-loc', '.match-date', '.match-res'] });
</script>

<?php require_once 'includes/footer.php'; ?>
