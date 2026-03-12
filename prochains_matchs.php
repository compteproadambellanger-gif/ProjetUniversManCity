<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'] ?? 'fan';

$prochains = $pdo->query(
    'SELECT id, opponent, competition, match_date, home_away
     FROM matchs WHERE match_date > CURDATE() ORDER BY match_date ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$total = count($prochains);
?>

<?php require_once 'includes/header.php'; ?>

<h1 class="titre-page">Prochains matchs</h1>
<p class="sous-titre-page">Saison 2025/2026 — <?php echo $total; ?> match<?php echo $total > 1 ? 's' : ''; ?> à venir</p>

<div class="search-bar-wrapper">
    <div class="search-bar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M10.5 3C14.6421 3 18 6.35786 18 10.5C18 12.3819 17.3061 14.1017 16.1627 15.4556L20.8536 20.1464C21.0488 20.3417 21.0488 20.6583 20.8536 20.8536C20.6583 21.0488 20.3417 21.0488 20.1464 20.8536L15.4556 16.1627C14.1017 17.3061 12.3819 18 10.5 18C6.35786 18 3 14.6421 3 10.5C3 6.35786 6.35786 3 10.5 3ZM10.5 4C6.91015 4 4 6.91015 4 10.5C4 14.0899 6.91015 17 10.5 17C14.0899 17 17 14.0899 17 10.5C17 6.91015 14.0899 4 10.5 4Z" fill="currentColor"/>
        </svg>
        <input type="text" id="searchInput" placeholder="Adversaire, compétition, date..." autocomplete="off">
        <button class="search-clear" id="searchClear" title="Effacer">✕</button>
    </div>
</div>
<p class="search-meta" id="searchMeta"></p>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:0.5rem;">
    <a href="matchs.php" class="bouton bouton-secondaire"
       style="padding:0.5rem 1.2rem; font-size:0.85rem; border-radius:20px;">
        ← Matchs joués
    </a>
    <?php if ($role === 'staff'): ?>
        <a href="includes/admin/matchs.php" class="bouton bouton-secondaire"
           style="padding:0.5rem 1.2rem; font-size:0.85rem; border-radius:20px;">
            Gérer les matchs
        </a>
    <?php endif; ?>
</div>

<?php if (empty($prochains)): ?>
    <div style="text-align:center; padding:4rem 2rem; color:rgba(138,155,176,0.4);">
        <p style="font-size:1rem;">Aucun match à venir enregistré.</p>
        <?php if ($role === 'staff'): ?>
            <a href="includes/admin/sync_matchs.php" class="bouton bouton-secondaire" style="margin-top:1rem; display:inline-block;">
                ⟳ Synchroniser via l'API
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
<div class="tableau-container">
    <table id="prochainsTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>J-</th>
                <th>Compétition</th>
                <th>Lieu</th>
                <th>Adversaire</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $aujourd_hui = new DateTime();
            foreach ($prochains as $match):
                $dateObj = new DateTime($match['match_date']);
                $jours   = (int)$aujourd_hui->diff($dateObj)->days;
                $loc     = $match['home_away'] === 'HOME' ? 'Domicile' : 'Extérieur';
            ?>
                <tr class="match-row">
                    <td class="match-date"><?php echo $dateObj->format('d/m/Y'); ?></td>
                    <td>
                        <span style="
                            background:rgba(201,168,76,0.1); color:#C9A84C;
                            border:1px solid rgba(201,168,76,0.3); border-radius:20px;
                            padding:2px 10px; font-size:0.78rem; font-weight:700;">
                            J-<?php echo $jours; ?>
                        </span>
                    </td>
                    <td class="match-comp"><?php echo htmlspecialchars($match['competition'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="match-loc"><?php echo $loc; ?></td>
                    <td class="match-opp" style="font-weight:600; color:#C8D6E5;">
                        <?php echo htmlspecialchars($match['opponent'], ENT_QUOTES, 'UTF-8'); ?>
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

<script>
(function () {
    const input        = document.getElementById('searchInput');
    const clearBtn     = document.getElementById('searchClear');
    const meta         = document.getElementById('searchMeta');
    const empty        = document.getElementById('searchEmpty');
    const emptyTerm    = document.getElementById('searchEmptyTerm');
    const paginationEl = document.getElementById('pagination');
    const infoEl       = document.getElementById('paginationInfo');

    const PER_PAGE = 15;
    const allRows  = Array.from(document.querySelectorAll('.match-row'));
    const TOTAL    = allRows.length;

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
                const opp  = normalize(row.querySelector('.match-opp').textContent);
                const comp = normalize(row.querySelector('.match-comp').textContent);
                const loc  = normalize(row.querySelector('.match-loc').textContent);
                const date = normalize(row.querySelector('.match-date').textContent);
                return opp.includes(filter) || comp.includes(filter)
                    || loc.includes(filter) || date.includes(filter);
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
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
