<?php
/** Term dates — PROGRAM HEAD. The last day of Prelim, Midterm and Pre-Finals. After that day, the term is closed for the professors (they can look, not change). */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/roles.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/academic.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/grades.php';

if (!canAssignClasses()) { header('Location: ' . (isAdmin() ? 'users.php' : 'dashboard.php')); exit; }
$activePage = 'assignments';
$cur = currentSchoolYear($pdo);
$ay = $cur['ay']; $sem = (int) $cur['sem'];
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check = checkCredential($pdo, 'pin', $_POST['credential'] ?? '');
    if (!$check['ok']) {
        if (!empty($check['logout'])) { endSessionAndRedirectLocked(); }
        $flash = ['type' => 'error', 'msg' => $check['error']];
    } else {
        $ok = true; $vals = [];
        foreach ([1, 2, 3] as $t) {
            $v = trim((string) ($_POST['end' . $t] ?? ''));
            if ($v !== '') {
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $v, new DateTimeZone('Asia/Manila'));
                if (!$parsed || $parsed->format('Y-m-d') !== $v) { $ok = false; }
            }
            $vals[$t] = $v;
        }
        if ($ok && $vals[1] !== '' && $vals[2] !== '' && $vals[1] >= $vals[2]) { $ok = false; }
        if ($ok && $vals[2] !== '' && $vals[3] !== '' && $vals[2] >= $vals[3]) { $ok = false; }
        if (!$ok) { $flash = ['type' => 'error', 'msg' => 'Please check the dates: enter valid calendar dates and make sure each term ends after the one before it.']; }
        else {
            try {
                // Save all three dates as one operation so the Faculty lock checks never see a half-saved set.
                $pdo->beginTransaction();
                $save = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $remove = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = ?");
                foreach ($vals as $t => $v) {
                    $key = 'term_end_' . $ay . '_' . $sem . '_' . $t;
                    if ($v === '') { $remove->execute([$key]); }
                    else { $save->execute([$key, $v]); }
                }
                $pdo->commit();
                // Read the saved values back before reporting success; these exact keys are what Faculty grading uses.
                $ends = termEndDates($pdo, $ay, $sem);
                foreach ($vals as $t => $v) {
                    if (($ends[$t] ?? null) !== ($v === '' ? null : $v)) { throw new RuntimeException('The saved term dates could not be verified. Please try again.'); }
                }
                $flash = ['type' => 'success', 'msg' => 'Term dates saved. Faculty grading will automatically lock for each term after its last day.'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $flash = ['type' => 'error', 'msg' => 'The term dates could not be saved. Please try again.'];
            }
        }
    }
}
$ends = termEndDates($pdo, $ay, $sem);
$names = [1 => 'Prelim', 2 => 'Midterm', 3 => 'Pre-Finals'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Term dates | Student Persistence Prediction</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css?v=32">
    <script>try { if (localStorage.getItem('theme') === 'dark') { document.documentElement.classList.add('dark'); } } catch (e) {}</script>
</head>
<body>
<?php include __DIR__ . '/includes/layout_top.php'; ?>
    <section class="page-header">
        <div>
            <h1>Term dates <small class="term-small"><?= htmlspecialchars($ay) ?> · <?= $sem === 1 ? '1st' : '2nd' ?> Semester</small></h1>
            <p>Set the <strong>last day</strong> of each term. A professor can record only <strong>during</strong> the term: from the day after the term before it ended, up to its last day. After that, the term is closed — it can be looked at, not changed. To <strong>reopen</strong> a term, move its date later. Leave a date empty and that term has no deadline.</p>
        </div>
        <a class="btn-secondary" style="text-decoration:none;" href="assignments.php"><i class="fa-solid fa-arrow-left"></i> Class Assignments</a>
    </section>
    <?php if ($flash): ?><div class="alert-banner alert-<?= $flash['type'] ?>"><i class="fa-solid fa-circle-<?= $flash['type'] === 'success' ? 'check' : 'exclamation' ?>"></i> <?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
    <form method="POST" id="tdForm" class="panel" style="margin:0 30px 22px;max-width:640px;padding:22px 26px;">
        <?php foreach ($names as $t => $n): $info = [1 => 'Prelim', 2 => 'Midterm', 3 => 'Pre-Finals']; ?>
            <label style="display:block;font-weight:600;margin:12px 0 6px;color:#33546a;">Last day of <?= $n ?></label>
            <input type="date" name="end<?= $t ?>" value="<?= htmlspecialchars((string) ($ends[$t] ?? '')) ?>" style="padding:12px 14px;border:1px solid #cfdde3;border-radius:10px;font-size:16px;">
            <?php $ci = termOpenInfo($pdo, ['academic_year' => $ay, 'semester' => $sem], $t); ?>
            <span style="margin-left:10px;font-size:14px;color:<?= $ci['open'] ? '#145a32' : '#b3261e' ?>;"><?= $ci['open'] ? 'Open now' : ($ci['state'] === 'closed' ? 'Closed' : 'Not open yet') ?></span>
        <?php endforeach; ?>
        <input type="hidden" name="credential" id="td_cred">
        <div style="margin-top:22px;"><button type="submit" class="btn-primary" style="padding:12px 24px;font-size:16px;"><i class="fa-solid fa-floppy-disk"></i> Save the dates</button></div>
    </form>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
<script>
(function () {
    var f = document.getElementById('tdForm'), asked = false;
    f.addEventListener('submit', function (e) {
        if (asked) return; e.preventDefault();
        askSecret({ title: 'Save the term dates?', message: 'Enter your Records PIN. A term whose date has passed will be closed for the professors.', type: 'pin', confirmText: 'Save' }).then(function (pin) {
            if (pin === null) return; document.getElementById('td_cred').value = pin; asked = true; HTMLFormElement.prototype.submit.call(f);
        });
    });
})();
</script>
</body>
</html>
