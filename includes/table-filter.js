/**
 * Table filter, search & pagination — shared utility
 *
 * Usage:
 *   initTableFilter({ rowClass: 'player-row', perPage: 15, searchFields: ['.player-name', '.player-pos', '.player-nat'] });
 */
function initTableFilter(opts) {
    const input        = document.getElementById('searchInput');
    const clearBtn     = document.getElementById('searchClear');
    const meta         = document.getElementById('searchMeta');
    const empty        = document.getElementById('searchEmpty');
    const emptyTerm    = document.getElementById('searchEmptyTerm');
    const paginationEl = document.getElementById('pagination');
    const infoEl       = document.getElementById('paginationInfo');

    if (!input) return;

    const PER_PAGE  = opts.perPage || 15;
    const allRows   = Array.from(document.querySelectorAll('.' + opts.rowClass));
    const TOTAL     = allRows.length;
    const fields    = opts.searchFields || [];

    let filteredRows = [...allRows];
    let currentPage  = 1;

    function normalize(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    function applyFilter() {
        var raw    = input.value.trim();
        var filter = normalize(raw);
        clearBtn.classList.toggle('visible', raw.length > 0);

        filteredRows = filter.length === 0
            ? [...allRows]
            : allRows.filter(function(row) {
                for (var i = 0; i < fields.length; i++) {
                    var el = row.querySelector(fields[i]);
                    if (el && normalize(el.textContent).includes(filter)) return true;
                }
                return false;
            });

        currentPage = 1;
        render();
    }

    function render() {
        var count      = filteredRows.length;
        var totalPages = Math.max(1, Math.ceil(count / PER_PAGE));
        if (currentPage > totalPages) currentPage = totalPages;

        var start = (currentPage - 1) * PER_PAGE;
        var end   = start + PER_PAGE;

        allRows.forEach(function(r) { r.style.display = 'none'; });
        filteredRows.forEach(function(r, i) {
            r.style.display = (i >= start && i < end) ? '' : 'none';
        });

        var raw = input.value.trim();
        meta.textContent = raw.length === 0
            ? ''
            : count + ' r\u00e9sultat' + (count > 1 ? 's' : '') + ' sur ' + TOTAL;

        if (count === 0) {
            emptyTerm.textContent = '"' + raw + '"';
            empty.classList.add('visible');
        } else {
            empty.classList.remove('visible');
        }

        renderPagination(totalPages, count);
    }

    function renderPagination(totalPages, count) {
        paginationEl.innerHTML = '';
        if (totalPages <= 1) { infoEl.textContent = ''; return; }

        var shownStart = (currentPage - 1) * PER_PAGE + 1;
        var shownEnd   = Math.min(currentPage * PER_PAGE, count);
        infoEl.textContent = shownStart + '\u2013' + shownEnd + ' sur ' + count + ' \u00b7 Page ' + currentPage + ' / ' + totalPages;

        var prev = makeBtn('\u2190', currentPage === 1);
        prev.addEventListener('click', function() { currentPage--; render(); window.scrollTo({top: 0, behavior: 'smooth'}); });
        paginationEl.appendChild(prev);

        getPageNums(currentPage, totalPages).forEach(function(p) {
            if (p === '...') {
                var dots = document.createElement('span');
                dots.className = 'page-dots';
                dots.textContent = '\u00b7\u00b7\u00b7';
                paginationEl.appendChild(dots);
            } else {
                var btn = makeBtn(p, false);
                if (p === currentPage) btn.classList.add('active');
                btn.addEventListener('click', function() { currentPage = p; render(); window.scrollTo({top: 0, behavior: 'smooth'}); });
                paginationEl.appendChild(btn);
            }
        });

        var next = makeBtn('\u2192', currentPage === totalPages);
        next.addEventListener('click', function() { currentPage++; render(); window.scrollTo({top: 0, behavior: 'smooth'}); });
        paginationEl.appendChild(next);
    }

    function makeBtn(label, disabled) {
        var btn = document.createElement('button');
        btn.className = 'page-btn';
        btn.textContent = label;
        btn.disabled = disabled;
        return btn;
    }

    function getPageNums(current, total) {
        if (total <= 7) return Array.from({length: total}, function(_, i) { return i + 1; });
        var set = new Set([1, total, current, current - 1, current + 1].filter(function(p) { return p >= 1 && p <= total; }));
        var sorted = Array.from(set).sort(function(a, b) { return a - b; });
        var result = [];
        var prev = 0;
        for (var i = 0; i < sorted.length; i++) {
            if (sorted[i] - prev > 1) result.push('...');
            result.push(sorted[i]);
            prev = sorted[i];
        }
        return result;
    }

    input.addEventListener('input', applyFilter);
    clearBtn.addEventListener('click', function() { input.value = ''; input.focus(); applyFilter(); });

    render();
}
