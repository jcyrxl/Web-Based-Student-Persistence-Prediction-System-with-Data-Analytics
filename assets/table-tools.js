/* Long lists: a search box, pages of 10 rows, and numbered rows. Mark a table with the attribute data-searchable. */
(function () {
    var PER = 10;
    function build(tb) {
        var body = tb.tBodies[0]; if (!body) return;
        var rows = Array.prototype.slice.call(body.rows).filter(function (r) { return r.cells.length > 2 && !r.classList.contains('tot-row'); });
        var fixed = Array.prototype.slice.call(body.rows).filter(function (r) { return r.classList.contains('tot-row'); });
        if (rows.length < 2) return;
        var host = tb.closest('.table-container, .table-wrap') || tb;
        var tools = document.createElement('div'); tools.className = 'tbl-tools';
        tools.innerHTML = '<label class="tbl-find"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search by name, number, section or status" aria-label="Search the list"></label><span class="tbl-count"></span>';
        host.parentNode.insertBefore(tools, host);
        var nav = document.createElement('nav'); nav.className = 'pager'; nav.setAttribute('aria-label', 'Pages of the list');
        host.parentNode.insertBefore(nav, host.nextSibling);
        var input = tools.querySelector('input'), count = tools.querySelector('.tbl-count'), page = 1, found = rows;
        function pages() { return Math.max(1, Math.ceil(found.length / PER)); }
        function render() {
            var q = input.value.trim().toLowerCase();
            found = rows.filter(function (r) { return !q || r.innerText.toLowerCase().indexOf(q) !== -1; });
            if (page > pages()) page = pages();
            var from = (page - 1) * PER, to = from + PER;
            rows.forEach(function (r) { r.style.display = 'none'; });
            found.forEach(function (r, i) { if (i >= from && i < to) { r.style.display = ''; var n = r.querySelector('td.rn'); if (n) n.textContent = i + 1; } });
            fixed.forEach(function (r) { r.style.display = q ? 'none' : ''; });
            count.textContent = q ? found.length + ' of ' + rows.length + ' found' : rows.length + ' in the list';
            var h = '<span class="pager-info">' + (found.length ? 'Showing <strong>' + (from + 1) + '–' + Math.min(found.length, to) + '</strong> of <strong>' + found.length + '</strong> · Page <strong>' + page + '</strong> of <strong>' + pages() + '</strong>' : 'Nothing found') + '</span>';
            if (pages() > 1) {
                h += '<span class="pager-nums">';
                if (page > 1) h += '<a href="#" class="pg-step" data-p="' + (page - 1) + '" aria-label="Previous page">‹</a>';
                var last = 0;
                for (var i = 1; i <= pages(); i++) {
                    if (!(i <= 2 || i > pages() - 1 || Math.abs(i - page) <= 1)) continue;
                    if (last && i - last > 1) h += '<span class="pg-gap">…</span>';
                    h += i === page ? '<span class="pg-cur" aria-current="page">' + i + '</span>' : '<a href="#" class="pg-n" data-p="' + i + '">' + i + '</a>';
                    last = i;
                }
                if (page < pages()) h += '<a href="#" class="pg-step" data-p="' + (page + 1) + '" aria-label="Next page">›</a>';
                h += '</span>';
            }
            nav.innerHTML = h;
        }
        nav.addEventListener('click', function (e) { var a = e.target.closest('a[data-p]'); if (!a) return; e.preventDefault(); page = parseInt(a.getAttribute('data-p'), 10); render(); });
        input.addEventListener('input', function () { page = 1; render(); });
        render();
    }
    function init() { Array.prototype.forEach.call(document.querySelectorAll('table[data-searchable]'), build); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
