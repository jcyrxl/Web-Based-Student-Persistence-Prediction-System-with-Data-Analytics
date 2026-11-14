<?php
// The Dashboard. Program Head: the whole College. Professor: only his or her own classes and students.
// A compact grid: the three things to see first, the attendance trend, and "See more graphs" for the rest.
// Every graph is a LINE or a donut you can tap: a window opens with the data behind it (each student opens a preview — no page change).
// Expects: $pdo, $viewAy, $isHistory, $cur, $isProf, $scopeClassIds (null = all), $scopeIds (null = all).
require_once __DIR__ . '/../../config/charts.php';
$popupDataRendered = true;
$tl = TERM_LABELS;

if ($isProf && !$scopeClassIds) {
    echo '<div class="chart-empty" style="margin:0 24px 20px;padding:24px;text-align:center;"><i class="fa-solid fa-chalkboard-user"></i> No class has been assigned to you for ' . htmlspecialchars($viewAy) . ' yet. The Administrator assigns the subjects you handle — they will appear here and under <strong>My Classes</strong>.</div>';
    return;
}
$popArr = function (string $title, string $sub, array $rows, ?string $more = null, ?string $moreLabel = null): array {
    return ['t' => $title, 's' => $sub, 'r' => array_slice($rows, 0, 80), 'm' => $more, 'ml' => $moreLabel];
};
$pop = function (string $title, string $sub, array $rows, ?string $more = null, ?string $moreLabel = null) use ($popArr): string {
    return htmlspecialchars(json_encode($popArr($title, $sub, $rows, $more, $moreLabel), JSON_UNESCAPED_UNICODE), ENT_QUOTES);
};
$ord = ['1st', '2nd', '3rd', '4th'];
$ptsFmt = function (float $p): string { return fmtPoints($p); };

// ---------------- the numbers ----------------
$lists  = riskLists($pdo, $viewAy, $scopeIds);
$risk   = ['High' => count($lists['High']), 'Moderate' => count($lists['Moderate']), 'Low' => count($lists['Low'])];
$risk['total'] = $risk['High'] + $risk['Moderate'] + $risk['Low'];
$riskSum = $risk['total'];
$loa     = $isProf ? null : loaStatsForYear($pdo, $viewAy);
$popupCases = $loa ? $loa['cases'] : [];
$series  = dashboardSeries($pdo, $viewAy, $scopeClassIds);
$ts      = termStatsBySection($pdo, $viewAy, (int) ($cur['sem'] ?? 1), $scopeClassIds);
$mySums = [];
if ($isProf) { foreach ($scopeClassIds as $cid) { $s = classSummary($pdo, $cid); if ($s) { $mySums[] = $s; } } }
$riskRows = function (array $items) { return array_map(function ($r) { return ['n' => $r['name'], 'i' => $r['id'], 'd' => round($r['prob']) . '% · ' . $r['section']]; }, $items); };
$riskPop = function (string $level) use ($lists, $pop, $riskRows) {
    $rows = $riskRows($lists[$level]);
    return $pop($level . ' risk', count($rows) . ' student' . (count($rows) === 1 ? '' : 's') . ' — predicted from grades and attendance. Tap a name to look at the record.', $rows);
};
$palette = ['#e5534b', '#f0b429', '#2f9bb5', '#6d5bb8', '#1a9b6f', '#d9734e'];
?>
<div id="popupData" hidden data-cases="<?= htmlspecialchars(json_encode($popupCases, JSON_UNESCAPED_UNICODE)) ?>"></div>

<!-- ============ ROW 1: the three things to see first ============ -->
<section class="dgrid">

    <div class="gcard g-teal span-4">
        <div class="gcard-top"><span class="gcard-title">How Many Students Need Help<?= $isProf ? ' — My Students' : '' ?></span><i class="fa-solid fa-triangle-exclamation gcard-ico"></i></div>
        <span class="basis"><i class="fa-solid fa-circle-info"></i> Point at a color to see its number · click it to see the students</span>
        <?php if ($riskSum > 0): ?>
            <div class="gcard-donut">
                <?= svgDonut([
                    ['label' => 'High',     'value' => $risk['High'],     'color' => '#e5534b', 'pop' => $riskPop('High')],
                    ['label' => 'Moderate', 'value' => $risk['Moderate'], 'color' => '#f0b429', 'pop' => $riskPop('Moderate')],
                    ['label' => 'Low',      'value' => $risk['Low'],      'color' => '#2fb26a', 'pop' => $riskPop('Low')],
                ], 150, 20, (string) $risk['total'], $isProf ? 'my students' : 'enrolled students') ?>
                <ul class="gcard-legend">
                    <?php foreach (['High' => '#e5534b', 'Moderate' => '#f0b429', 'Low' => '#2fb26a'] as $lvl => $col): ?>
                        <li><a href="#" data-pop="<?= $riskPop($lvl) ?>" data-n="<?= (int) $risk[$lvl] ?>" data-lbl="<?= $lvl ?>"><i style="background:<?= $col ?>"></i><?= $lvl ?> risk <strong><?= $risk[$lvl] ?></strong></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="gcard-empty">No risk scores yet — they appear as soon as grades or attendance are recorded.</div>
        <?php endif; ?>
        <div class="gcard-foot"><?= $isProf ? 'Enrolled students in my ' . count($scopeClassIds) . ' class' . (count($scopeClassIds) === 1 ? '' : 'es') . ' · ' : 'Enrolled students of ' ?><?= htmlspecialchars($viewAy) ?> · <?= $risk['total'] ?> counted</div>
    </div>

    <?php
    // ---- the sections that need attention, term by term (a line for each) ----
    $curT = max(1, (int) $ts['current']);
    $secAll = $ts['sections'];
    $secStudents = function (string $sec) use ($lists, $riskRows) {
        $rows = [];
        foreach (['High', 'Moderate', 'Low'] as $lv) { foreach ($lists[$lv] as $r) { if ($r['section'] === $sec) { $rows[] = ['n' => $r['name'], 'i' => $r['id'], 'd' => $lv . ' ' . round($r['prob']) . '%']; } } }
        return $rows;
    };
    $serA = []; $allV = []; $i = 0;
    foreach (array_slice($secAll, 0, 6, true) as $sec => $x) {
        $vals = []; $pops = [];
        foreach ([1, 2, 3] as $t) {
            $v = ($t <= $curT) ? $x['terms'][$t] : null;
            $vals[] = $v === null ? null : round($v, 1);
            if ($v !== null) { $allV[] = $v; }
            $pops[] = $v === null ? '' : $pop($sec . ' · ' . $tl[$t], 'Average ' . fmtGrade($v) . '% — ' . $x['students'] . ' students, ' . $x['attention'] . ' need attention. Sorted by risk; tap a name.', $secStudents($sec));
        }
        $serA[] = ['name' => $sec, 'color' => $palette[$i % 6], 'values' => $vals, 'pops' => $pops]; $i++;
    }
    $minV = $allV ? min($allV) : 70;
    $ymin = (int) (floor(($minV - 5) / 10) * 10); if ($ymin < 50) { $ymin = (int) max(0, floor($ymin / 20) * 20); } $ymin = min($ymin, 70);
    $maxV = $allV ? max($allV) : 100;
    $ymaxA = (int) min(100, max((int) ceil($maxV / 10) * 10, 80));          // the chart goes up only as far as the highest average (never below 80, so the passing line is seen)
    $ticksA = $ymin >= 50 ? ($ymaxA - $ymin) / 10 : ($ymaxA - $ymin) / 20;
    if ($ticksA < 2) { $ticksA = 2; }
    $allSecRows = [];
    foreach ($secAll as $sec => $x) {
        $d = [];
        foreach ([1, 2, 3] as $t) { if ($t <= $curT && $x['terms'][$t] !== null) { $d[] = substr($tl[$t], 0, 3) . ' ' . fmtGrade($x['terms'][$t]); } }
        $allSecRows[] = ['n' => $sec, 'i' => '', 'd' => implode(' · ', $d) . ($x['attention'] ? ' · ' . $x['attention'] . ' need attention' : ''),
                         'p' => $popArr($sec, $x['students'] . ' students — sorted by risk. Tap a name to look at the record.', $secStudents($sec))];
    }
    ?>
    <div class="dcard span-5">
        <div class="dcard-head"><div><h2>Sections with the lowest grades</h2><p>The average grade of each section, term by term. The red line is the passing grade. Click a point to see its students.</p></div>
            <?php if (count($secAll) > 6): ?><button type="button" class="seeall" data-pop="<?= $pop('All ' . count($secAll) . ' sections', 'Worst first. Tap a section to see its students.', $allSecRows) ?>">See all <?= count($secAll) ?></button><?php endif; ?></div>
        <?php if ($ts['current'] > 0 && $serA): ?>
            <?= svgLineChart(['labels' => array_values($tl), 'series' => $serA, 'min' => $ymin, 'max' => $ymaxA, 'ticks' => $ticksA, 'unit' => '%', 'width' => 560, 'height' => 250,
                              'ref' => [['value' => FAILING_GRADE_THRESHOLD, 'label' => 'passing ' . (int) FAILING_GRADE_THRESHOLD . '%', 'color' => '#b3261e']]]) ?>
        <?php else: ?><div class="gcard-empty" style="margin:auto 0;">Nothing has been recorded yet this semester. The sections appear here as grades come in.</div><?php endif; ?>
    </div>

    <?php if ($isProf):
        usort($mySums, function ($a, $b) { return ($b['attention'] <=> $a['attention']); });
        $allClassRows = array_map(function ($c) { $cl = $c['d']['class']; return ['n' => $cl['subject_name'], 'i' => '', 'd' => $cl['section'] . ' · ' . $c['attention'] . ' need attention', 'h' => 'class.php?id=' . (int) $cl['id']]; }, $mySums); ?>
    <div class="gcard g-ocean span-3" style="grid-column: span 3;">
        <div class="gcard-top"><span class="gcard-title">My Classes</span><i class="fa-solid fa-triangle-exclamation gcard-ico"></i></div>
        <span class="basis"><i class="fa-solid fa-user-check"></i> Students who need attention</span>
        <div class="rrows">
            <?php foreach (array_slice($mySums, 0, 5) as $c): $cl = $c['d']['class']; ?>
                <a class="rrow" href="class.php?id=<?= (int) $cl['id'] ?>" style="grid-template-columns: 1fr auto;">
                    <span class="rl"><?= htmlspecialchars($cl['subject_name']) ?> <small style="opacity:.8;">· <?= htmlspecialchars($cl['section']) ?></small></span>
                    <b><?= $c['attention'] ?><?= ($c['drop'] + $c['warn']) ? ' <i class="fa-solid fa-user-clock"></i>' : '' ?></b>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="gcard-foot"><button type="button" class="seeall" style="background:rgba(255,255,255,.18);color:#fff;border-color:rgba(255,255,255,.45);" data-pop="<?= $pop('My ' . count($mySums) . ' classes', 'The number is how many students need attention. Tap a class to record.', $allClassRows) ?>">See all <?= count($mySums) ?> classes</button></div>
    </div>
    <?php else: ?>
    <div class="gcard g-ocean span-3" style="grid-column: span 3;">
        <div class="gcard-top"><span class="gcard-title">Why Students Asked for Leave</span><i class="fa-solid fa-comment-dots gcard-ico"></i></div>
        <span class="basis"><i class="fa-solid fa-envelope-open-text"></i> From the requests of the students</span>
        <?php if ($loa['reasons']): $maxR = max($loa['reasons']); ?>
            <div class="rrows">
                <?php foreach (array_slice($loa['reasons'], 0, 5, true) as $reasonName => $n): ?>
                    <button type="button" class="rrow" onclick='openReasonModal(<?= jsonAttr($reasonName) ?>)' title="See the students">
                        <span class="rl"><?= htmlspecialchars($reasonName) ?></span><span class="rt"><i style="width:<?= max(6, (int) round($n / $maxR * 100)) ?>%"></i></span><b><?= $n ?></b>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php else: ?><div class="gcard-empty">No reasons yet — they come from the students' letters.</div><?php endif; ?>
        <div class="gcard-foot">Tap a reason to see who wrote it.</div>
    </div>
    <?php endif; ?>
</section>

<!-- ============ WHERE THE STUDENTS ARE NOW (bar graph) and the 1st semester next to the 2nd semester ============ -->
<?php if (!$isProf):
    $stRows = [];
    try {
        $sq = $pdo->prepare("SELECT s.student_id, s.full_name, s.status, (SELECT r2.section FROM student_records r2 WHERE r2.student_id = s.student_id AND r2.academic_year = ? LIMIT 1) AS section
                             FROM students s WHERE EXISTS (SELECT 1 FROM student_records x WHERE x.student_id = s.student_id AND x.academic_year = ?) ORDER BY s.full_name ASC");
        $sq->execute([$viewAy, $viewAy]);
        $stRows = $sq->fetchAll();
    } catch (Throwable $e) { $stRows = []; }
    $stBy = []; foreach (array_keys(STUDENT_STATUSES) as $k) { $stBy[$k] = []; }
    foreach ($stRows as $r) { if (isset($stBy[$r['status']])) { $stBy[$r['status']][] = ['n' => $r['full_name'], 'i' => $r['student_id'], 'd' => (string) ($r['section'] ?: '')]; } }
    $stShort = ['enrolled' => 'Enrolled', 'loa' => 'LOA', 'hd' => 'HD', 'dropped' => 'DU', 'shifted' => 'Shifted', 'graduated' => 'Graduated'];
    $stItems = []; $stKeys = [];
    foreach (STUDENT_STATUSES as $k => $lbl) {
        $n = count($stBy[$k]);
        $popTxt = $pop($lbl, $n . ' student' . ($n === 1 ? '' : 's') . ' · ' . syLabel($viewAy) . '. Click a name to see the record.', $stBy[$k]);
        $stItems[] = ['label' => $stShort[$k] ?? $lbl, 'value' => $n, 'color' => STATUS_COLORS[$k] ?? '#64727a', 'pop' => $popTxt];
        $stKeys[] = [$lbl, $n, STATUS_COLORS[$k] ?? '#64727a', $popTxt];
    }
    // why the students asked for a leave or a dismissal, 1st semester and 2nd semester
    $rsCmp = [];
    try {
        $rq = $pdo->prepare("SELECT reason, semester, COUNT(*) AS n FROM student_cases WHERE case_type IN ('loa', 'hd') AND academic_year = ? GROUP BY reason, semester");
        $rq->execute([$viewAy]);
        foreach ($rq->fetchAll() as $r) {
            $k = $r['reason'] === 'Others' ? 'Other reasons' : $r['reason'];
            if (!isset($rsCmp[$k])) { $rsCmp[$k] = ['first' => 0, 'second' => 0]; }
            if ($r['semester'] === '1st Semester') { $rsCmp[$k]['first'] += (int) $r['n']; }
            elseif ($r['semester'] === '2nd Semester') { $rsCmp[$k]['second'] += (int) $r['n']; }
        }
    } catch (Throwable $e) { $rsCmp = []; }
    uasort($rsCmp, function ($a, $b) { return ($b['first'] + $b['second']) <=> ($a['first'] + $a['second']); });
    $rsShown = array_slice($rsCmp, 0, 5, true);
    // Compact labels are used only on the chart; the full reason remains in the data behind it.
    $rsChart = [];
    $rsShortLabels = [
        'Academic difficulties' => 'Academic',
        'Mental health / emotional wellness' => 'Mental health',
        'Relocation / moved' => 'Relocation',
        'Financial difficulties' => 'Financial',
        'Family problems' => 'Family',
        'Health problems' => 'Health',
        'Personal reasons' => 'Personal',
        'Other reasons' => 'Other',
    ];
    foreach ($rsShown as $reason => $vals) { $rsChart[$rsShortLabels[$reason] ?? $reason] = $vals; }
    $rsTot1 = array_sum(array_column($rsCmp, 'first')); $rsTot2 = array_sum(array_column($rsCmp, 'second'));
?>
<section class="dgrid">
    <div class="dcard span-6 status-card">
        <div class="dcard-head"><div><h2>Where the students are now</h2><p><?= count($stRows) ?> students of <?= htmlspecialchars(syLabel($viewAy)) ?>, by status. Click a bar to see the students.</p></div></div>
        <?= svgStatBars($stItems, 190) ?>
        <div class="stat-keys">
            <?php foreach ($stKeys as $kk): ?><a href="#" class="stat-key" style="--c:<?= $kk[2] ?>" data-pop="<?= $kk[3] ?>"><i></i><?= htmlspecialchars($kk[0]) ?> <b><?= $kk[1] ?></b></a><?php endforeach; ?>
        </div>
    </div>
    <div class="dcard span-6">
        <div class="dcard-head"><div><h2>1st semester and 2nd semester</h2>
            <p>Why students asked for a Leave of Absence (LOA) or an Honorable Dismissal (HD), <?= htmlspecialchars(syLabel($viewAy)) ?>.</p></div></div>
        <?php if (!$rsShown): ?><div class="chart-empty" style="padding:30px 10px;">No request has been confirmed yet for <?= htmlspecialchars(syLabel($viewAy)) ?>.</div>
        <?php else: ?>
            <div class="cmp-wrap"><?= svgGroupedBars($rsChart, ['first' => ['label' => '1st semester', 'color' => '#2f80ed'], 'second' => ['label' => '2nd semester', 'color' => '#e85d9a']], 'Reasons for a leave or a dismissal, 1st semester and 2nd semester', true, true) ?></div>
            <div class="stat-keys"><span class="stat-key" style="--c:#2f80ed"><i></i>1st semester <b><?= $rsTot1 ?></b></span><span class="stat-key" style="--c:#e85d9a"><i></i>2nd semester <b><?= $rsTot2 ?></b></span></div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ THE STUDENTS WHO ARE ON TRACK (Low risk), by section ============ -->
<?php
$trackSec = [];
foreach (['High', 'Moderate', 'Low'] as $lv) { foreach ($lists[$lv] as $r) { $sx = $r['section'] ?: 'No section'; if (!isset($trackSec[$sx])) { $trackSec[$sx] = ['all' => 0, 'low' => 0, 'rows' => []]; } $trackSec[$sx]['all']++; if ($lv === 'Low') { $trackSec[$sx]['low']++; $trackSec[$sx]['rows'][] = ['n' => $r['name'], 'i' => $r['id'], 'd' => 'Low risk ' . round($r['prob']) . '%']; } } }
uasort($trackSec, function ($a, $b) { return (($b['low'] / max(1, $b['all'])) <=> ($a['low'] / max(1, $a['all']))) ?: ($b['all'] <=> $a['all']); });
$trackItems = []; foreach (array_slice($trackSec, 0, 6, true) as $sx => $t) { $trackItems[] = ['label' => $sx, 'sub' => $t['low'] . ' of ' . $t['all'], 'value' => $t['all'] ? round($t['low'] / $t['all'] * 100, 1) : 0, 'color' => RISK_COLORS['Low'], 'pop' => $pop('On track · ' . $sx, $t['low'] . ' of ' . $t['all'] . ' students are at low risk.', $t['rows'])]; }
$lowAll = (int) $risk['Low']; $allCnt = max(1, (int) $risk['total']);
?>
<?php if ($trackItems): ?>
<section class="dgrid">
    <div class="dcard span-4 track-card">
        <div class="dcard-head"><div><h2>Students on track</h2><p>Low risk: doing well in grades and attendance.</p></div></div>
        <a href="#" class="track-big" data-pop="<?= $riskPop('Low') ?>"><b><?= $lowAll ?></b><span>of <?= (int) $risk['total'] ?> students<br><strong><?= round($lowAll / $allCnt * 100) ?>%</strong> are on track</span></a>
    </div>
    <div class="dcard span-8">
        <div class="dcard-head"><div><h2>Sections with the most students on track</h2><p>The percent of each section that is at low risk. Click a bar to see the students.</p></div></div>
        <?= svgStatBars($trackItems, 190, '%', 100) ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ ROW 2: attendance ============ -->
<?php
// the attendance graph and the list of absences can be sorted by section (the dropdown)
$attSec = (string) ($_GET['att_sec'] ?? ''); $secOptions = [];
try {
    $qs = $pdo->prepare("SELECT DISTINCT section FROM classes WHERE academic_year = ? AND section <> '' ORDER BY section ASC");
    $qs->execute([$viewAy]);
    $secOptions = $qs->fetchAll(PDO::FETCH_COLUMN);
    if ($isProf && $scopeClassIds) {
        $in = implode(',', array_map('intval', $scopeClassIds));
        $secOptions = $pdo->query("SELECT DISTINCT section FROM classes WHERE id IN ($in) AND section <> '' ORDER BY section ASC")->fetchAll(PDO::FETCH_COLUMN);
    }
    if ($attSec !== '' && in_array($attSec, $secOptions, true)) {
        $qi = $pdo->prepare("SELECT id FROM classes WHERE academic_year = ? AND section = ?");
        $qi->execute([$viewAy, $attSec]);
        $secIds = array_map('intval', $qi->fetchAll(PDO::FETCH_COLUMN));
        if ($scopeClassIds !== null) { $secIds = array_values(array_intersect($secIds, $scopeClassIds)); }
        $series = dashboardSeries($pdo, $viewAy, $secIds);
    } else { $attSec = ''; }
} catch (Throwable $e) { $attSec = ''; }
$weeks = [];
foreach ($series['dates'] as $date => $x) {
    $wk = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    if (!isset($weeks[$wk])) { $weeks[$wk] = ['pts' => 0.0, 'n' => 0, 'who' => []]; }
    $weeks[$wk]['pts'] += $x['pts']; $weeks[$wk]['n'] += $x['n'];
    foreach ($x['who'] as $w) { $weeks[$wk]['who'][] = [$w[0], $w[1], $w[2], $w[3], $date]; }
}
ksort($weeks);
$wLabels = []; $wVals = []; $wPops = []; $wCols = []; $wMin = 100;
foreach ($weeks as $wk => $x) {
    $rate = $x['n'] ? ($x['n'] - $x['pts']) / $x['n'] * 100 : 100; $wMin = min($wMin, $rate);
    $rows = array_map(function ($w) { return ['n' => $w[0], 'i' => $w[1], 'd' => ['A' => 'Absent', 'L' => 'Late', 'E' => 'Excused'][$w[2]] . ' · ' . $w[3] . ' · ' . date('M j', strtotime($w[4]))]; }, $x['who']);
    $wLabels[] = date('M j', strtotime($wk)); $wVals[] = round($rate, 1); $wCols[] = $rate < 80 ? '#e5534b' : null;
    $wPops[] = $pop('Week of ' . date('F j, Y', strtotime($wk)), 'Attendance ' . round($rate) . '% — ' . count($rows) . ' absent, late or excused mark' . (count($rows) === 1 ? '' : 's') . ' this week. Tap a name.', $rows);
}
$wymin = (int) max(0, floor(($wMin - 5) / 10) * 10); if ($wymin > 70) { $wymin = 70; }
$absAll = array_map(function ($a) { return ['n' => $a[0], 'i' => $a[1], 'd' => fmtPoints($a[5]) . ' · ' . $a[2] . ' · ' . $a[3]]; }, $series['absent']);
?>
<section class="dgrid" id="attendance">
    <div class="dcard span-7" id="attCard">
        <div class="dcard-head"><div><h2>Attendance each week<?= $attSec !== '' ? ' — ' . htmlspecialchars($attSec) : '' ?></h2>
            <div class="chart-actions no-print"><a class="btn-secondary" href="dashboard_export.php?part=attendance&att_sec=<?= urlencode($attSec) ?>"><i class="fa-solid fa-file-csv"></i> Download (CSV)</a> <button type="button" class="btn-secondary" data-print-card><i class="fa-solid fa-file-pdf"></i> Save as PDF</button></div>
            <form method="get" action="#attendance" class="att-filter"><label>Show <select name="att_sec" onchange="this.form.submit()" aria-label="Show the attendance of one section"><option value="">All sections (<?= count($secOptions) ?>)</option><?php foreach ($secOptions as $so): ?><option value="<?= htmlspecialchars($so) ?>" <?= $so === $attSec ? 'selected' : '' ?>><?= htmlspecialchars($so) ?></option><?php endforeach; ?></select></label></form><p>The percent of students who came to class each week. A week under the red 80% line has a ring. Click a point to see who was absent.</p></div></div>
        <?php if (!$weeks): ?><div class="gcard-empty" style="margin:auto 0;">No attendance has been recorded yet.</div>
        <?php else: ?>
            <?= svgLineChart(['labels' => $wLabels, 'series' => [['name' => 'Attendance', 'color' => '#2f9bb5', 'values' => $wVals, 'pops' => $wPops, 'colors' => $wCols]], 'min' => $wymin, 'max' => 100, 'ticks' => (100 - $wymin) / 10, 'unit' => '%', 'width' => 800, 'height' => 250,
                              'ref' => [['value' => 80, 'label' => '80%', 'color' => '#e5534b']]]) ?>
        <?php endif; ?>
    </div>
    <div class="dcard span-5">
        <div class="dcard-head"><div><h2>Students with the most absences</h2><p>An absence counts 1; late or excused counts ½. At 3, the student is marked Dropped Unofficially (DU).</p></div>
            <?php if (count($series['absent']) > 6): ?><button type="button" class="seeall" data-pop="<?= $pop('Everyone with absences', 'Absence points from the start of the school year. Tap a name.', $absAll) ?>">See all <?= count($series['absent']) ?></button><?php endif; ?></div>
        <?php if (!$series['absent']): ?><div class="gcard-empty" style="margin:auto 0;"><?= $series['dates'] ? 'Nobody has been absent.' : 'No attendance has been recorded yet.' ?></div><?php endif; ?>
        <div class="mini-list">
            <?php foreach (array_slice($series['absent'], 0, 6) as $a): $cl = $a[5] >= ABSENCE_DROP_AT ? 'drop' : ($a[5] >= ABSENCE_ALERT_AT ? 'warn' : ''); ?>
                <a class="mini-row" href="student.php?id=<?= urlencode($a[1]) ?>"><span class="who"><strong><?= htmlspecialchars($a[0]) ?></strong><small><?= htmlspecialchars($a[2]) ?> · <?= htmlspecialchars($a[3]) ?></small></span><span class="pts <?= $cl ?>"><?= $ptsFmt($a[5]) ?></span></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="dgrid">
    <?php if (!$isProf):
        $hn = historyToNow($pdo, $viewAy);
        $pItems = []; $cItems = [];
        foreach ($hn as $r) {
            $leftRows = attritionStudents($pdo, $r['ay']);
            $left = count($leftRows);
            $stay = max(0, (int) $r['total'] - $left);
            $rows = array_map(function ($x) { return ['n' => $x['full_name'], 'i' => $x['student_id'], 'd' => ($x['case_type'] === 'du' ? 'Dropped Unofficially (DU)' : 'Honorable Dismissal (HD)') . ' · ' . ($x['reason'] === 'Others' && $x['reason_other'] ? $x['reason_other'] : $x['reason'])]; }, $leftRows);
            $popTxt = $pop('Students who discontinued, ' . syLabel($r['ay']), $stay . ' of ' . $r['total'] . ' students continued; ' . $left . ' discontinued (DU or HD).', $rows);
            $pct = $r['total'] ? round($stay / $r['total'] * 100, 1) : 0.0;
            $pItems[] = ['label' => syLabel($r['ay']), 'sub' => $r['now'] ? 'so far' : '', 'value' => $pct, 'color' => '#1c8a4a', 'pop' => $popTxt];
            $cItems[] = ['label' => syLabel($r['ay']), 'sub' => 'of ' . $r['total'] . ' students', 'value' => $stay, 'color' => '#2fb26a', 'pop' => $popTxt];
        } ?>
    <div class="dcard span-6 retention-card">
        <div class="dcard-head"><div><h2>Persistence rate, school year by school year</h2><p>The percent of students who continued in the program. Click a bar to see the students who discontinued.</p></div></div>
        <?= svgStatBars($pItems, 200, '%', 100) ?>
    </div>
    <div class="dcard span-6 retention-card">
        <div class="dcard-head"><div><h2>Students who continued</h2><p>How many students continued in each school year, out of all the students of that year.</p></div></div>
        <?= svgStatBars($cItems, 200) ?>
    </div>
    <?php else: $attn = attentionList($pdo, $scopeClassIds, 40);
        $attRows = array_map(function ($a) { return ['n' => $a['name'], 'i' => $a['sid'], 'd' => $a['subject'] . ' · ' . implode(' · ', $a['reasons'])]; }, $attn); ?>
    <div class="dcard span-12">
        <div class="dcard-head"><div><h2>Students who need attention</h2><p>In my classes — a failing term (even before it is finished), scores below 50%, or 2 absences.</p></div>
            <?php if (count($attn) > 6): ?><button type="button" class="seeall" data-pop="<?= $pop('Students who need attention', 'In my classes. Tap a name.', $attRows) ?>">See all <?= count($attn) ?></button><?php endif; ?></div>
        <?php if (!$attn): ?><div class="gcard-empty" style="margin:auto 0;">Nobody needs attention right now.</div><?php endif; ?>
        <div class="mini-list"><?php foreach (array_slice($attn, 0, 6) as $a): ?>
            <a class="mini-row" href="student.php?id=<?= urlencode($a['sid']) ?>"><span class="who"><strong><?= htmlspecialchars($a['name']) ?></strong><small><?= htmlspecialchars($a['subject']) ?> · <?= htmlspecialchars(implode(' · ', $a['reasons'])) ?></small></span><span class="pts warn"><?= count($a['reasons']) ?></span></a>
        <?php endforeach; ?></div>
    </div>
    <?php endif; ?>
</section>
