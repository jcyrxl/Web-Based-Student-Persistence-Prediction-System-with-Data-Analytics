<?php
/**
 * Confirm requests — PROGRAM HEAD.
 * The students file their own Leave of Absence, Honorable Dismissal or Shift (student_request.php). Here the Program Head only READS them and confirms:
 * one by one, or all at once. Confirming (with the PIN) means "I have read it": the status of the student changes and the graphs and reports follow.
 * Nothing has to be typed by the Program Head.
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/roles.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/academic.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/student_role.php';

if (!canManageRequests()) { header('Location: ' . (isAdmin() ? 'users.php' : 'dashboard.php')); exit; }
ensureStudentRole($pdo);

$activePage = 'requests';
$flash = null;
if (!empty($_SESSION['req_flash'])) { $flash = $_SESSION['req_flash']; unset($_SESSION['req_flash']); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check = checkCredential($pdo, 'pin', $_POST['credential'] ?? '');
    if (!$check['ok']) {
        if (!empty($check['logout'])) { endSessionAndRedirectLocked(); }
        $flash = ['type' => 'error', 'msg' => $check['error']];
    } else {
        $action = $_POST['action'] ?? '';
        $byName = $_SESSION['full_name'] ?? $_SESSION['user_id'];
        try {
            if ($action === 'confirm_one') {
                $q = $pdo->prepare("SELECT * FROM student_requests WHERE id = ? AND status = 'pending'");
                $q->execute([(int) ($_POST['id'] ?? 0)]);
                $rows = $q->fetchAll();
            } elseif ($action === 'confirm_all') {
                $rows = $pdo->query("SELECT * FROM student_requests WHERE status = 'pending' ORDER BY created_at ASC")->fetchAll();
            } else { $rows = []; }
            if (!$rows) { throw new RuntimeException('There is nothing waiting to be confirmed.'); }
            $pdo->beginTransaction();
            foreach ($rows as $r) { confirmStudentRequest($pdo, $r, $_SESSION['user_id'], $byName); }
            $pdo->commit();
            $_SESSION['req_flash'] = ['type' => 'success', 'msg' => count($rows) === 1 ? 'Confirmed. The status of the student was updated.' : count($rows) . ' requests confirmed. The status of each student was updated.'];
            header('Location: request_form.php'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $flash = ['type' => 'error', 'msg' => $e instanceof RuntimeException ? $e->getMessage() : 'Could not confirm: ' . $e->getMessage()];
        }
    }
}

$pending = [];
$done = [];
try {
    $pending = $pdo->query("SELECT r.*, s.full_name, (SELECT sr.section FROM student_records sr WHERE sr.student_id = s.student_id ORDER BY sr.year_level DESC LIMIT 1) AS section
                            FROM student_requests r INNER JOIN students s ON s.student_id = r.student_id WHERE r.status = 'pending' ORDER BY r.created_at ASC")->fetchAll();
    $done = $pdo->query("SELECT r.*, s.full_name FROM student_requests r INNER JOIN students s ON s.student_id = r.student_id WHERE r.status = 'confirmed' ORDER BY r.confirmed_at DESC LIMIT 15")->fetchAll();
} catch (Throwable $e) {}
$reasonText = function (array $r): string { return $r['reason'] === 'Others' && $r['reason_other'] ? 'Others: ' . $r['reason_other'] : $r['reason']; };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm requests | Student Persistence Prediction</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css?v=32">
    <script>try { if (localStorage.getItem('theme') === 'dark') { document.documentElement.classList.add('dark'); } } catch (e) {}</script>
</head>
<body>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

    <section class="page-header">
        <div>
            <h1>Confirm requests</h1>
            <p>The students file their own <strong>Leave of Absence</strong>, <strong>Honorable Dismissal</strong> and <strong>Shift</strong>. Read each one and confirm it — you do not have to type anything. When you confirm, the status of the student changes and the graphs and reports follow.</p>
        </div>
        <?php if (count($pending) > 1): ?>
            <button type="button" class="btn-primary" id="confirmAll"><i class="fa-solid fa-check-double"></i> Confirm all (<?= count($pending) ?>)</button>
        <?php endif; ?>
    </section>

    <?php if ($flash): ?><div class="alert-banner alert-<?= $flash['type'] ?>"><i class="fa-solid fa-circle-<?= $flash['type'] === 'success' ? 'check' : 'exclamation' ?>"></i> <?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>

    <form method="POST" id="cform" style="display:none;"><input type="hidden" name="action" id="c_action"><input type="hidden" name="id" id="c_id"><input type="hidden" name="credential" id="c_cred"></form>

    <div class="panel" style="margin:0 30px 22px;">
        <div class="panel-header"><div><h2>Waiting for you <span class="term-small">· <?= count($pending) ?></span></h2><p>Every detail the student wrote is shown, so nothing is hidden.</p></div></div>
        <div class="table-container"><table>
            <thead><tr><th class="rn">#</th><th>Student</th><th>Request</th><th>Reason and remarks</th><th>School year · Semester</th><th>Filed</th><th></th></tr></thead>
            <tbody>
                <?php if (!$pending): ?><tr><td colspan="7" style="padding:26px;text-align:center;color:#145a32;"><i class="fa-solid fa-circle-check"></i> Everything is confirmed. New requests appear here when a student files one.</td></tr><?php endif; ?>
                <?php foreach ($pending as $i => $r): $ct = CASE_TYPES[$r['request_type']]; ?>
                    <tr>
                        <td class="rn"><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($r['full_name']) ?></strong><small style="display:block;color:#6b8793;"><?= htmlspecialchars($r['student_id']) ?><?= $r['section'] ? ' · ' . htmlspecialchars($r['section']) : '' ?></small></td>
                        <td><span class="status-pill" style="--c: <?= STATUS_COLORS[$ct['status']] ?? '#64727a' ?>;"><?= htmlspecialchars($ct['label']) ?></span></td>
                        <td style="white-space:normal;max-width:340px;line-height:1.5;"><strong><?= htmlspecialchars($reasonText($r)) ?></strong>
                            <?= $r['shifted_to'] ? '<div>Shifting to: ' . htmlspecialchars($r['shifted_to']) . '</div>' : '' ?>
                            <?= $r['details'] ? '<div style="color:#55636b;">“' . htmlspecialchars($r['details']) . '”</div>' : '' ?></td>
                        <td><?= htmlspecialchars($r['academic_year']) ?><small style="display:block;color:#6b8793;"><?= htmlspecialchars($r['semester']) ?></small></td>
                        <td><?= date('M j, Y', strtotime($r['filed_on'])) ?></td>
                        <td><button type="button" class="btn-primary confirm-one" data-id="<?= (int) $r['id'] ?>" data-name="<?= htmlspecialchars($r['full_name']) ?>" style="padding:9px 16px;"><i class="fa-solid fa-check"></i> Confirm</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>

    <div class="panel" style="margin:0 30px 22px;">
        <div class="panel-header"><div><h2>Confirmed lately</h2><p>The last 15 requests that you confirmed. The full list, with search, is in Reports.</p></div></div>
        <div class="table-container"><table>
            <thead><tr><th class="rn">#</th><th>Student</th><th>Request</th><th>Reason</th><th>Confirmed</th></tr></thead>
            <tbody>
                <?php if (!$done): ?><tr><td colspan="5" style="padding:18px;color:#8a959b;">Nothing confirmed yet.</td></tr><?php endif; ?>
                <?php foreach ($done as $i => $r): $ct = CASE_TYPES[$r['request_type']]; ?>
                    <tr><td class="rn"><?= $i + 1 ?></td><td><strong><?= htmlspecialchars($r['full_name']) ?></strong><small style="display:block;color:#6b8793;"><?= htmlspecialchars($r['student_id']) ?></small></td>
                        <td><span class="status-pill" style="--c: <?= STATUS_COLORS[$ct['status']] ?? '#64727a' ?>;"><?= htmlspecialchars($ct['short']) ?></span></td>
                        <td><?= htmlspecialchars($reasonText($r)) ?></td>
                        <td><?= $r['confirmed_at'] ? date('M j, Y g:i A', strtotime($r['confirmed_at'])) : '' ?><small style="display:block;color:#6b8793;"><?= htmlspecialchars((string) $r['confirmed_by']) ?></small></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
<script>
(function () {
    var form = document.getElementById('cform');
    function ask(action, id, title, message) {
        askSecret({ title: title, message: message, type: 'pin', confirmText: 'Confirm' }).then(function (pin) {
            if (pin === null) return;
            document.getElementById('c_action').value = action; document.getElementById('c_id').value = id || ''; document.getElementById('c_cred').value = pin;
            form.submit();
        });
    }
    Array.prototype.forEach.call(document.querySelectorAll('.confirm-one'), function (b) {
        b.addEventListener('click', function () { ask('confirm_one', b.dataset.id, 'Confirm this request?', 'You have read the request of ' + b.dataset.name + '. Enter your Records PIN to confirm. Their status will change.'); });
    });
    var all = document.getElementById('confirmAll');
    if (all) all.addEventListener('click', function () { ask('confirm_all', '', 'Confirm all the requests?', 'You have read all the requests on this page. Enter your Records PIN to confirm them. The status of each student will change.'); });
})();
</script>
</body>
</html>
