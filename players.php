<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'] ?? 'fan';

$requete = $pdo->query(
    'SELECT id, full_name, shirt_number, position, nationality, photo_url,
            valeur_marchande
     FROM players
     ORDER BY shirt_number'
);
$joueurs = $requete->fetchAll(PDO::FETCH_ASSOC);
$total = count($joueurs);
?>

<?php require_once 'includes/header.php'; ?>

<h1 class="titre-page">Effectif Manchester City</h1>
<p class="sous-titre-page">Saison 2025/2026 — <?php echo $total; ?> joueurs enregistrés</p>

<div class="search-bar-wrapper">
    <div class="search-bar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M10.5 3C14.6421 3 18 6.35786 18 10.5C18 12.3819 17.3061 14.1017 16.1627 15.4556L20.8536 20.1464C21.0488 20.3417 21.0488 20.6583 20.8536 20.8536C20.6583 21.0488 20.3417 21.0488 20.1464 20.8536L15.4556 16.1627C14.1017 17.3061 12.3819 18 10.5 18C6.35786 18 3 14.6421 3 10.5C3 6.35786 6.35786 3 10.5 3ZM10.5 4C6.91015 4 4 6.91015 4 10.5C4 14.0899 6.91015 17 10.5 17C14.0899 17 17 14.0899 17 10.5C17 6.91015 14.0899 4 10.5 4Z" fill="currentColor"/>
        </svg>
        <input type="text" id="searchInput" placeholder="Nom, poste, nationalité..." autocomplete="off">
        <button class="search-clear" id="searchClear" title="Effacer">✕</button>
    </div>
</div>
<p class="search-meta" id="searchMeta"></p>

<?php if ($role === 'staff'): ?>
    <div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
        <a href="includes/admin/players.php" class="bouton bouton-secondaire"
           style="padding:0.5rem 1.2rem; font-size:0.85rem; border-radius:20px;">
            Gérer les joueurs
        </a>
    </div>
<?php endif; ?>

<div class="tableau-container">
    <table id="playersTable">
        <thead>
            <tr>
                <th style="width:52px;"></th>
                <th>#</th>
                <th>Nom</th>
                <th>Poste</th>
                <th>Nationalité</th>
                <th>Valeur marchande</th>
                <th>Stats</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($joueurs as $joueur): ?>
                <?php
                $parts = preg_split('/\s+/', trim($joueur['full_name']));
                $initiales = mb_strtoupper(mb_substr($parts[0], 0, 1));
                if (count($parts) > 1) $initiales .= mb_strtoupper(mb_substr(end($parts), 0, 1));
                $pos = htmlspecialchars($joueur['position'], ENT_QUOTES, 'UTF-8');
                ?>
                <tr class="player-row">
                    <td class="td-avatar">
                        <?php if (!empty($joueur['photo_url']) && file_exists(__DIR__ . '/' . $joueur['photo_url'])): ?>
                            <img src="<?php echo htmlspecialchars($joueur['photo_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo htmlspecialchars($joueur['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 class="player-photo player-avatar-<?php echo $pos; ?>">
                        <?php else: ?>
                            <div class="player-avatar player-avatar-<?php echo $pos; ?>">
                                <?php echo $initiales; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo (int)$joueur['shirt_number']; ?></td>
                    <td class="player-name"><?php echo htmlspecialchars($joueur['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="player-pos"><?php echo $pos; ?></td>
                    <td class="player-nat"><?php echo htmlspecialchars($joueur['nationality'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php if ($joueur['valeur_marchande'] !== null): ?>
                            <span style="
                                background:rgba(201,168,76,0.12); color:#C9A84C;
                                border:1px solid rgba(201,168,76,0.35); border-radius:20px;
                                padding:3px 12px; font-size:0.8rem; font-weight:700;
                                display:inline-flex; align-items:center; gap:0.3rem; white-space:nowrap;">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                                €<?php echo number_format((float)$joueur['valeur_marchande'], 0, ',', ' '); ?>&nbsp;M
                            </span>
                        <?php else: ?>
                            <span style="color:rgba(138,155,176,0.4); font-size:0.8rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="player_stats.php?id=<?php echo (int)$joueur['id']; ?>"
                           class="bouton bouton-secondaire"
                           style="padding:0.3rem 0.8rem; font-size:0.8rem;">
                            Voir les stats
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="search-empty" id="searchEmpty">
        <span class="search-empty-icon">🔍</span>
        <p>Aucun joueur trouvé pour <strong id="searchEmptyTerm"></strong></p>
    </div>
</div>

<div id="pagination" class="pagination"></div>
<p id="paginationInfo" class="pagination-info"></p>

<script src="includes/table-filter.js"></script>
<script>
initTableFilter({ rowClass: 'player-row', perPage: 15, searchFields: ['.player-name', '.player-pos', '.player-nat'] });
</script>

<?php require_once 'includes/footer.php'; ?>
