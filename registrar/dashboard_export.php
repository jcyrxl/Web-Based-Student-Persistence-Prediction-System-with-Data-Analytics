<?php
/**
 * The numbers of the Dashboard as a CSV file (opens in Excel). Program Head: the whole College. Professor: only his or her own classes.
 * One file, a few small tables one under another: how many students need help, where the students are, who stayed, the sections, the absences.
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/roles.php';
require_once __DIR__ . '/../config/academic.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/grades.php';

denyAdmin();
$isProf = isFaculty();
$cur = currentSchoolYear($pdo);
$viewAy = $cur['ay'];
$scopeClassIds = $isProf ? ownedClassIds($pdo, $cur['ay']) : null;
$scopeIds      = $isProf ? scopeStudentIds($pdo, $cur['ay']) : null;

// ---- only the attendance graph (follows the section that is chosen in the dropdown) ----
if (($_GET['part'] ?? '') === 'attendance') {
    $attSec = (string) ($_GET['att_sec'] ?? '');
    $ids = $scopeClassIds;
    if ($attSec !== '') {
        $qi = $pdo->prepare("SELECT id FROM classes WHERE academic_year = ? AND section = ?");
        $qi->execute([$viewAy, $attSec]);
        $ids = array_map('intval', $qi->fetchAll(PDO::FETCH_COLUMN));
        if ($scopeClassIds !== null) { $ids = array_values(array_intersect($ids, $scopeClassIds)); }
    }
    $series = dashboardSeries($pdo, $viewAy, $ids);
    $weeks = [];
    foreach ($series['dates'] as $date => $x) {
        $wk = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        if (!isset($weeks[$wk])) { $weeks[$wk] = ['pts' => 0.0, 'n' => 0, 'who' => []]; }
        $weeks[$wk]['pts'] += $x['pts']; $weeks[$wk]['n'] += $x['n'];
        foreach ($x['who'] as $w) { $weeks[$wk]['who'][] = [$w[0], $w[1], $w[2], $w[3], $date]; }
    }
    ksort($weeks);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-' . preg_replace('/[^A-Za-z0-9-]+/', '_', $attSec !== '' ? $attSec : 'all-sections') . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    $filterLabel = $attSec !== '' ? 'Section ' . $attSec : ($isProf ? 'My classes' : 'All sections');
    fputcsv($out, ['Attendance each week', 'S.Y. ' . $viewAy, $filterLabel, 'Downloaded ' . date('M j, Y g:i A')]);
    fputcsv($out, []);
    fputcsv($out, ['Week of', 'Date', 'Student Name', 'Student ID', 'Mark', 'Subject', 'Section', 'Weekly Attendance Rate (%)']);
    foreach ($weeks as $wk => $x) {
        $rate = $x['n'] ? round(($x['n'] - $x['pts']) / $x['n'] * 100, 1) : 100;
        if (!$x['who']) {
            fputcsv($out, [date('M j, Y', strtotime($wk)), '', '', '', '', '', $attSec !== '' ? $attSec : '', $rate]);
            continue;
        }
        foreach ($x['who'] as $w) {
            fputcsv($out, [date('M j, Y', strtotime($wk)), date('M j, Y', strtotime($w[4])), $w[0], $w[1], ['A' => 'Absent', 'L' => 'Late', 'E' => 'Excused'][$w[2]] ?? $w[2], $w[3], $attSec !== '' ? $attSec : '', $rate]);
        }
    }
    fclose($out); exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="dashboard-' . preg_replace('/[^0-9-]+/', '', $viewAy) . '-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
$gap = function () use ($out) { fputcsv($out, []); };

fputcsv($out, ['Dashboard — School year ' . $viewAy, $isProf ? 'My classes' : 'Whole College', 'Downloaded ' . date('M j, Y g:i A')]);
$gap();

// 1. how many students need help
try {
    $lists = riskLists($pdo, $viewAy, $scopeIds);
    $total = count($lists['High']) + count($lists['Moderate']) + count($lists['Low']);
    fputcsv($out, ['How many students need help']);
    fputcsv($out, ['Risk level', 'Students', 'Percent of the students counted']);
    foreach (['High', 'Moderate', 'Low'] as $lv) { $n = count($lists[$lv]); fputcsv($out, [$lv . ' risk', $n, $total ? round($n / $total * 100, 1) . '%' : '0%']); }
    fputcsv($out, ['All students counted', $total, '100%']);
    $gap();
    fputcsv($out, ['Students at High or Moderate risk']);
    fputcsv($out, ['Student ID', 'Name', 'Section', 'Risk', 'Risk %']);
    foreach (array_merge($lists['High'], $lists['Moderate']) as $r) { fputcsv($out, [$r['id'], $r['name'], $r['section'], $r['level'] ?? '', round((float) $r['prob'])]); }
    $gap();
} catch (Throwable $e) { fputcsv($out, ['(the risk numbers could not be read)']); $gap(); }

// 2. where the students are now (Program Head)
if (!$isProf) {
    try {
        $q = $pdo->prepare("SELECT s.status, COUNT(*) FROM students s WHERE EXISTS (SELECT 1 FROM student_records x WHERE x.student_id = s.student_id AND x.academic_year = ?) GROUP BY s.status");
        $q->execute([$viewAy]);
        $by = $q->fetchAll(PDO::FETCH_KEY_PAIR);
        fputcsv($out, ['Where the students are now']);
        fputcsv($out, ['Status', 'Students']);
        $sum = 0;
        foreach (STUDENT_STATUSES as $k => $lbl) { $n = (int) ($by[$k] ?? 0); $sum += $n; fputcsv($out, [$lbl, $n]); }
        fputcsv($out, ['All students', $sum]);
        $gap();
    } catch (Throwable $e) {}
    // 3. who stayed, school year by school year
    try {
        fputcsv($out, ['Students who continued, school year by school year']);
        fputcsv($out, ['School year', 'Students', 'Continuing', 'Discontinued (DU or HD)', 'Persistence %']);
        foreach (historyToNow($pdo, $viewAy) as $r) {
            $left = count(attritionStudents($pdo, $r['ay']));
            $stay = max(0, (int) $r['total'] - $left);
            fputcsv($out, [$r['ay'] . ($r['now'] ? ' (so far)' : ''), (int) $r['total'], $stay, $left, $r['total'] ? round($stay / $r['total'] * 100, 1) : 0]);
        }
        $gap();
    } catch (Throwable $e) {}
}

// 4. the sections, term by term
try {
    $ts = termStatsBySection($pdo, $viewAy, (int) ($cur['sem'] ?? 1), $scopeClassIds);
    fputcsv($out, ['Average grade of each section']);
    fputcsv($out, ['Section', 'Students', 'Prelim', 'Midterm', 'Pre-Finals', 'Students who need attention']);
    foreach ($ts['sections'] as $sec => $x) {
        fputcsv($out, [$sec, $x['students'], $x['terms'][1] === null ? '' : round($x['terms'][1], 1), $x['terms'][2] === null ? '' : round($x['terms'][2], 1), $x['terms'][3] === null ? '' : round($x['terms'][3], 1), $x['attention']]);
    }
    $gap();
} catch (Throwable $e) {}

// 5. the students with the most absences
try {
    $series = dashboardSeries($pdo, $viewAy, $scopeClassIds);
    fputcsv($out, ['Students with the most absences (absent = 1, late or excused = 0.5)']);
    fputcsv($out, ['Name', 'Student ID', 'Absence points']);
    foreach ($series['absent'] as $a) { fputcsv($out, [$a[0], $a[1], $a[5]]); }
} catch (Throwable $e) {}
fclose($out);
exit;
