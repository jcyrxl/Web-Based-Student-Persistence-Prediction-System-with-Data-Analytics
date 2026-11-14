<?php
/**
 * Pages for long lists: 10 rows to a page, the number of the page shown clearly, and numbers that are not packed together
 * ( ‹  1   2   3   …   44  › ). pageSlice() cuts the rows; pagerHtml() draws the numbers; the rows are numbered from $pg['offset'] + 1.
 */
function pageSlice(array $rows, int $perPage = 10, string $param = 'page'): array {
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = max(1, min($pages, (int) ($_GET[$param] ?? 1)));
    $offset = ($page - 1) * $perPage;
    return ['rows' => array_slice($rows, $offset, $perPage), 'page' => $page, 'pages' => $pages, 'total' => $total, 'offset' => $offset, 'per' => $perPage, 'param' => $param];
}

/** $url = function (int $page): string (the address of a page); $attr = extra attributes of every link (for example 'data-nav'). */
function pagerHtml(array $pg, ?callable $url = null, string $attr = ''): string {
    if ((int) $pg['total'] === 0) { return ''; }
    if ($url === null) {
        $param = $pg['param'];
        $url = function (int $p) use ($param): string { $q = $_GET; $q[$param] = $p; return '?' . http_build_query($q); };
    }
    $from = $pg['offset'] + 1; $to = min($pg['total'], $pg['offset'] + $pg['per']);
    $h = '<nav class="pager" aria-label="Pages of the list"><span class="pager-info">Showing <strong>' . $from . '–' . $to . '</strong> of <strong>' . $pg['total'] . '</strong> · Page <strong>' . $pg['page'] . '</strong> of <strong>' . $pg['pages'] . '</strong></span>';
    if ($pg['pages'] > 1) {
        $h .= '<span class="pager-nums">';
        if ($pg['page'] > 1) { $h .= '<a ' . $attr . ' class="pg-step" href="' . htmlspecialchars($url($pg['page'] - 1)) . '" aria-label="Previous page">‹</a>'; }
        $last = 0;
        for ($i = 1; $i <= $pg['pages']; $i++) {
            if (!($i <= 2 || $i > $pg['pages'] - 1 || abs($i - $pg['page']) <= 1)) { continue; }
            if ($last && $i - $last > 1) { $h .= '<span class="pg-gap">…</span>'; }
            $h .= $i === $pg['page']
                ? '<span class="pg-cur" aria-current="page">' . $i . '</span>'
                : '<a ' . $attr . ' class="pg-n" href="' . htmlspecialchars($url($i)) . '">' . $i . '</a>';
            $last = $i;
        }
        if ($pg['page'] < $pg['pages']) { $h .= '<a ' . $attr . ' class="pg-step" href="' . htmlspecialchars($url($pg['page'] + 1)) . '" aria-label="Next page">›</a>'; }
        $h .= '</span>';
    }
    return $h . '</nav>';
}
