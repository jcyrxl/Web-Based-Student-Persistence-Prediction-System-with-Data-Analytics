<?php
/**
 * The student's own page: the classes and grades (Prelim, Midterm, Final), the attendance (how many late, excused and absent — in a table), and the requests filed.
 * A student sees only his or her own record. The User ID of the account is the student number.
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/roles.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/academic.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/grades.php';
require_once __DIR__ . '/../config/student_role.php';

if (!isStudent()) { header('Location: dashboard.php'); exit; }
ensureStudentRole($pdo);
attendanceNoteReady($pdo, true);
$activePage = 'mine';
$sid = (string) $_SESSION['user_id'];

$st = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
$st->execute([$sid]);
$student = $st->fetch();

$groups = [];
if ($student) {
    $cq = $pdo->prepare("SELECT c.id FROM class_students cs INNER JOIN classes c ON c.id = cs.class_id WHERE cs.student_id = ? AND cs.excluded = 0 ORDER BY c.academic_year DESC, c.semester DESC, c.subject_name ASC");
    $cq->execute([$sid]);
    foreach ($cq->fetchAll(PDO::FETCH_COLUMN) as $cid) {
        $d = classLoadData($pdo, (int) $cid);
        if (!$d) { continue; }
        $groups[$d['class']['academic_year'] . '|' . $d['class']['semester']][] = ['d' => $d, 'r' => classStudentResult($d, $sid)];
    }
}
$requests = [];
try { $rq = $pdo->prepare("SELECT * FROM student_requests WHERE student_id = ? ORDER BY created_at DESC"); $rq->execute([$sid]); $requests = $rq->fetchAll(); } catch (Throwable $e) {}
$statusLabel = $student ? (STUDENT_STATUSES[$student['status']] ?? ucfirst($student['status'])) : '';
$statusColor = $student ? (STATUS_COLORS[$student['status']] ?? '#64727a') : '#64727a';
$g = function ($v) { return $v === null ? '<span style="color:#8a959b;">—</span>' : '<span style="' . ($v < FAILING_GRADE_THRESHOLD ? 'color:#b3261e;font-weight:700;' : '') . '">' . fmtGrade((float) $v) . '%</span>'; };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My classes | Student Persistence Prediction</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css?v=32">
    <script>try { if (localStorage.getItem('theme') === 'dark') { document.documentElement.classList.add('dark'); } } catch (e) {}</script>
</head>
<body>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

    <section class="dash-hero">
        <div class="dash-hero-text">
            <h1>Hello, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h1>
            <p>Your classes, your grades and your attendance — all in one page.</p>
            <?php if ($student): ?><div class="ay-chips"><span class="term-chip" style="background:#fff;color:<?= $statusColor ?>;border:2px solid <?= $statusColor ?>;"><i class="fa-solid fa-circle"></i> Status: <?= htmlspecialchars($statusLabel) ?></span></div><?php endif; ?>
            <div class="dash-export"><a class="btn-primary" style="text-decoration:none;" href="student_request.php"><i class="fa-solid fa-file-circle-plus"></i> File a request (leave, dismissal or shift)</a></div>
        </div>
    </section>

    <?php if (!empty($_SESSION['sr_flash'])): ?><div class="alert-banner alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($_SESSION['sr_flash']) ?></div><?php unset($_SESSION['sr_flash']); endif; ?>
    <?php if (!$student): ?>
        <div class="alert-banner alert-error" style="margin:0 30px;"><i class="fa-solid fa-triangle-exclamation"></i> Your account is not linked to a student record yet. Please tell the Program Head.</div>
    <?php else: ?>

    <div class="panel" style="margin:0 30px 22px;">
        <div class="panel-header"><div><h2>My classes and grades</h2><p>Prelim, Midterm and Pre-Finals, and the final grade once all three are complete. A red grade is below passing (<?= (int) FAILING_GRADE_THRESHOLD ?>).</p></div></div>
        <?php if (!$groups): ?><div class="chart-empty" style="padding:22px;">You are not in a class yet.</div><?php endif; ?>
        <?php foreach ($groups as $gk => $list): [$ay, $sem] = explode('|', $gk); ?>
            <h3 class="fc-title" style="margin:14px 20px 6px;"><?= htmlspecialchars($ay) ?> · <?= (int) $sem === 1 ? '1st' : '2nd' ?> Semester</h3>
            <div class="table-container" style="margin:0 20px;"><table>
                <thead><tr><th class="rn">#</th><th>Subject</th><th>Section</th><th>Professor</th><th>Prelim</th><th>Midterm</th><th>Pre-Finals</th><th>Final grade</th></tr></thead>
                <tbody>
                <?php foreach ($list as $i => $c): $cl = $c['d']['class']; $r = $c['r']; ?>
                    <tr><td class="rn"><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($cl['subject_name']) ?></strong><?= $cl['subject_code'] ? '<small style="display:block;color:#6b8793;">' . htmlspecialchars($cl['subject_code']) . '</small>' : '' ?></td>
                        <td><?= htmlspecialchars($cl['section']) ?></td>
                        <td><?= htmlspecialchars($cl['owner_name'] ?: 'Not assigned yet') ?></td>
                        <?php foreach ([1, 2, 3] as $t): $tm = $r['terms'][$t]; ?><td><?= $g($tm['grade']) ?><?= ($tm['grade'] !== null && !empty($tm['prov'])) ? '<small style="color:#8a959b;"> so far</small>' : '' ?></td><?php endforeach; ?>
                        <td><strong><?= $r['final'] !== null ? $g($r['final']) : '<span style="color:#8a959b;">Not yet</span>' ?></strong></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endforeach; ?>
    </div>

    <div class="panel" style="margin:0 30px 22px;">
        <div class="panel-header"><div><h2>My attendance</h2><p>How many times you were late, excused or absent, in each subject. Point at an E to read why it was excused.</p></div></div>
        <div style="padding:0 20px 16px;">
        <?php if (!$groups): ?><div class="chart-empty" style="padding:22px;">No attendance yet.</div><?php endif; ?>
        <?php foreach ($groups as $gk => $list): [$ay, $sem] = explode('|', $gk); ?>
            <h3 class="fc-title" style="margin:14px 0 6px;"><?= htmlspecialchars($ay) ?> · <?= (int) $sem === 1 ? '1st' : '2nd' ?> Semester</h3>
            <?= attendanceMatrixHtml($list, $sid) ?>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="panel" id="requests" style="margin:0 30px 22px;">
        <div class="panel-header"><div><h2>My requests</h2><p>The requests you filed. The Program Head reads each one and confirms it.</p></div>
            <a class="btn-secondary" style="text-decoration:none;" href="student_request.php"><i class="fa-solid fa-file-circle-plus"></i> File a request</a></div>
        <div class="table-container"><table>
            <thead><tr><th class="rn">#</th><th>Request</th><th>Reason</th><th>School year · Semester</th><th>Filed</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (!$requests): ?><tr><td colspan="6" style="padding:18px;color:#8a959b;">You have not filed a request.</td></tr><?php endif; ?>
                <?php foreach ($requests as $i => $r): $ct = CASE_TYPES[$r['request_type']]; ?>
                    <tr><td class="rn"><?= $i + 1 ?></td>
                        <td><span class="status-pill" style="--c: <?= STATUS_COLORS[$ct['status']] ?? '#64727a' ?>;"><?= htmlspecialchars($ct['label']) ?></span></td>
                        <td><?= htmlspecialchars($r['reason'] === 'Others' && $r['reason_other'] ? 'Others: ' . $r['reason_other'] : $r['reason']) ?></td>
                        <td><?= htmlspecialchars($r['academic_year']) ?><small style="display:block;color:#6b8793;"><?= htmlspecialchars($r['semester']) ?></small></td>
                        <td><?= date('M j, Y', strtotime($r['filed_on'])) ?></td>
                        <td><?php if ($r['status'] === 'confirmed'): ?><span class="badge low-badge"><i class="fa-solid fa-check"></i> Confirmed</span><?php else: ?><span class="badge moderate-badge"><i class="fa-solid fa-hourglass-half"></i> Waiting for the Program Head</span><?php endif; ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
</body>
</html>
