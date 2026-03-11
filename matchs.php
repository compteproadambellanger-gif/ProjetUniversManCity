<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'] ?? 'fan';

$requete = $pdo->query(
    'SELECT id, opponent, competition, match_date, home_away, goals_city, goals_opponent
     FROM matchs
     ORDER BY match_date DESC'
);
$matchs = $requete->fetchAll(PDO::FETCH_ASSOC);
$total  = count($matchs);
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

<?php if ($role === 'staff'): ?>
    <div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
        <a href="includes/admin/matchs.php" class="bouton bouton-secondaire"
           style="padding:0.5rem 1.2rem; font-size:0.85rem; border-radius:20px;">
            Gérer les matchs
        </a>
    </div>
<?php endif; ?>

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

<script>
(function () {
    const input        = document.getElementById('searchInput');
    const clearBtn     = document.getElementById('searchClear');
    const meta         = document.getElementById('searchMeta');
    const empty        = document.getElementById('searchEmpty');
    const emptyTerm    = document.getElementById('searchEmptyTerm');
    const paginationEl = document.getElementById('pagination');
    const infoEl       = document.getElementById('paginationInfo');

    const PER_PAGE = 10;
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
                const res  = normalize(row.querySelector('.match-res').textContent);
                return opp.includes(filter) || comp.includes(filter)
                    || loc.includes(filter) || date.includes(filter)
                    || res.includes(filter);
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
