<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'] ?? 'fan';

$requete = $pdo->query(
    'SELECT id, full_name, shirt_number, position, nationality, photo_url
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

<script>
(function () {
    const input       = document.getElementById('searchInput');
    const clearBtn    = document.getElementById('searchClear');
    const meta        = document.getElementById('searchMeta');
    const empty       = document.getElementById('searchEmpty');
    const emptyTerm   = document.getElementById('searchEmptyTerm');
    const paginationEl = document.getElementById('pagination');
    const infoEl      = document.getElementById('paginationInfo');

    const PER_PAGE  = 15;
    const allRows   = Array.from(document.querySelectorAll('.player-row'));
    const TOTAL     = allRows.length;

    let filteredRows = [...allRows];
    let currentPage  = 1;

    function normalize(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    function applyFilter() {
        const raw    = input.value.trim();
        const filter = normalize(raw);
        clearBtn.classList.toggle('visible', raw.length > 0);

        filteredRows = filter.length === 0
            ? [...allRows]
            : allRows.filter(row => {
                const name = normalize(row.querySelector('.player-name').textContent);
                const pos  = normalize(row.querySelector('.player-pos').textContent);
                const nat  = normalize(row.querySelector('.player-nat').textContent);
                return name.includes(filter) || pos.includes(filter) || nat.includes(filter);
            });

        currentPage = 1;
        render();
    }

    function render() {
        const count      = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(count / PER_PAGE));
        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * PER_PAGE;
        const end   = start + PER_PAGE;

        allRows.forEach(r => r.style.display = 'none');
        filteredRows.forEach((r, i) => {
            r.style.display = (i >= start && i < end) ? '' : 'none';
        });

        const raw = input.value.trim();
        meta.textContent = raw.length === 0
            ? ''
            : `${count} résultat${count > 1 ? 's' : ''} sur ${TOTAL}`;

        if (count === 0) {
            emptyTerm.textContent = `"${raw}"`;
            empty.classList.add('visible');
        } else {
            empty.classList.remove('visible');
        }

        renderPagination(totalPages, count);
    }

    function renderPagination(totalPages, count) {
        paginationEl.innerHTML = '';
        if (totalPages <= 1) { infoEl.textContent = ''; return; }

        const shownStart = (currentPage - 1) * PER_PAGE + 1;
        const shownEnd   = Math.min(currentPage * PER_PAGE, count);
        infoEl.textContent = `${shownStart}–${shownEnd} sur ${count} · Page ${currentPage} / ${totalPages}`;

        const prev = makeBtn('←', currentPage === 1);
        prev.addEventListener('click', () => { currentPage--; render(); window.scrollTo({top: 0, behavior: 'smooth'}); });
        paginationEl.appendChild(prev);

        getPageNums(currentPage, totalPages).forEach(p => {
            if (p === '...') {
                const dots = document.createElement('span');
                dots.className = 'page-dots';
                dots.textContent = '···';
                paginationEl.appendChild(dots);
            } else {
                const btn = makeBtn(p, false);
                if (p === currentPage) btn.classList.add('active');
                btn.addEventListener('click', () => { currentPage = p; render(); window.scrollTo({top: 0, behavior: 'smooth'}); });
                paginationEl.appendChild(btn);
            }
        });

        const next = makeBtn('→', currentPage === totalPages);
        next.addEventListener('click', () => { currentPage++; render(); window.scrollTo({top: 0, behavior: 'smooth'}); });
        paginationEl.appendChild(next);
    }

    function makeBtn(label, disabled) {
        const btn = document.createElement('button');
        btn.className = 'page-btn';
        btn.textContent = label;
        btn.disabled = disabled;
        return btn;
    }

    function getPageNums(current, total) {
        if (total <= 7) return Array.from({length: total}, (_, i) => i + 1);
        const set = new Set([1, total, current, current - 1, current + 1].filter(p => p >= 1 && p <= total));
        const sorted = [...set].sort((a, b) => a - b);
        const result = [];
        let prev = 0;
        for (const p of sorted) {
            if (p - prev > 1) result.push('...');
            result.push(p);
            prev = p;
        }
        return result;
    }

    input.addEventListener('input', applyFilter);
    clearBtn.addEventListener('click', () => { input.value = ''; input.focus(); applyFilter(); });

    render();
})();
</script>

<?php require_once 'includes/footer.php'; ?>
