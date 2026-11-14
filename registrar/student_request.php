<?php
/** A student files a Leave of Absence, an Honorable Dismissal or a Shift. It waits for the Program Head, who reads it and confirms it (request_form.php). */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/roles.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/academic.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/student_role.php';

if (!isStudent()) { header('Location: dashboard.php'); exit; }
ensureStudentRole($pdo);
$activePage = 'request';
$sid = (string) $_SESSION['user_id'];
$st = $pdo->prepare("SELECT student_id, full_name, status FROM students WHERE student_id = ?");
$st->execute([$sid]);
$student = $st->fetch();
$cur = currentSchoolYear($pdo);
$types = ['loa', 'hd', 'shifted'];
$flash = null;
$old = ['type' => '', 'ay' => $cur['ay'], 'sem' => ((int) $cur['sem'] === 1 ? '1st Semester' : '2nd Semester'), 'reason' => '', 'other' => '', 'to' => '', 'details' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student) {
    $old = ['type' => $_POST['type'] ?? '', 'ay' => $_POST['ay'] ?? '', 'sem' => $_POST['sem'] ?? '', 'reason' => $_POST['reason'] ?? '', 'other' => trim($_POST['other'] ?? ''), 'to' => trim($_POST['to'] ?? ''), 'details' => trim($_POST['details'] ?? '')];
    try {
        if (!in_array($old['type'], $types, true)) { throw new RuntimeException('Please choose what you are asking for.'); }
        if (!in_array($old['ay'], AY_ENTRY_OPTIONS, true)) { throw new RuntimeException('Please choose the school year.'); }
        if (!in_array($old['sem'], CASE_SEMESTERS, true)) { throw new RuntimeException('Please choose the semester.'); }
        if (!in_array($old['reason'], CASE_TYPES[$old['type']]['reasons'], true)) { throw new RuntimeException('Please choose your reason.'); }
        if ($old['reason'] === 'Others') {
            if ($old['other'] === '') { throw new RuntimeException('Please write your reason, since you chose "Others".'); }
            if (strlen($old['other']) > 255) { throw new RuntimeException('Your reason is too long (255 characters at most).'); }
        } else { $old['other'] = ''; }
        if ($old['type'] === 'shifted' && $old['to'] === '') { throw new RuntimeException('Please write the program you are shifting to.'); }
        if (strlen($old['to']) > 100 || strlen($old['details']) > 500) { throw new RuntimeException('One of your answers is too long.'); }
        $dup = $pdo->prepare("SELECT COUNT(*) FROM student_requests WHERE student_id = ? AND request_type = ? AND status = 'pending'");
        $dup->execute([$sid, $old['type']]);
        if ((int) $dup->fetchColumn() > 0) { throw new RuntimeException('You already filed this kind of request, and it is waiting for the Program Head.'); }
        $pdo->prepare("INSERT INTO student_requests (student_id, request_type, academic_year, semester, reason, reason_other, details, shifted_to, filed_on) VALUES (?,?,?,?,?,?,?,?,CURDATE())")
            ->execute([$sid, $old['type'], $old['ay'], $old['sem'], $old['reason'], $old['other'] !== '' ? $old['other'] : null, $old['details'] !== '' ? $old['details'] : null, $old['type'] === 'shifted' ? $old['to'] : null]);
        $_SESSION['sr_flash'] = 'Your request was sent. The Program Head will read it and confirm it.';
        header('Location: student_home.php#requests'); exit;
    } catch (RuntimeException $e) { $flash = $e->getMessage(); }
    catch (PDOException $e) { $flash = 'Could not send your request. Please try again.'; }
}
$J = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$reasonMap = []; foreach ($types as $k) { $reasonMap[$k] = CASE_TYPES[$k]['reasons']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File a request | Student Persistence Prediction</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css?v=32">
    <script>try { if (localStorage.getItem('theme') === 'dark') { document.documentElement.classList.add('dark'); } } catch (e) {}</script>
    <style>
        .sr-card { max-width: 760px; margin: 0 30px 24px; background: #fff; border-radius: 16px; padding: 24px 26px; box-shadow: 0 6px 22px rgba(20,60,90,.08); }
        .sr-types { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin: 8px 0 18px; }
        .sr-type { display: block; cursor: pointer; border: 2px solid #cfdde3; border-radius: 14px; padding: 14px 16px; transition: .12s; }
        .sr-type input { position: absolute; opacity: 0; }
        .sr-type b { display: block; font-size: 16px; color: #064a5e; } .sr-type span { font-size: 13.5px; color: #55636b; line-height: 1.45; display: block; margin-top: 4px; }
        .sr-type:has(input:checked) { border-color: var(--c); background: #f4fafc; box-shadow: 0 0 0 3px rgba(11,143,163,.15); }
        .sr-card label.f { display: block; font-weight: 600; font-size: 15px; margin: 14px 0 6px; color: #33546a; }
        .sr-card select, .sr-card input[type=text], .sr-card textarea { width: 100%; box-sizing: border-box; padding: 12px 14px; border: 1px solid #cfdde3; border-radius: 10px; font-size: 16px; font-family: inherit; }
        .sr-card .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 600px) { .sr-card .row2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

    <section class="page-header">
        <div>
            <h1>File a request</h1>
            <p>Choose what you need, tell us why, and send it. The Program Head reads it and confirms it. You can see its status in <a href="student_home.php">My classes</a>.</p>
        </div>
    </section>

    <?php if ($flash): ?><div class="alert-banner alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if (!$student): ?><div class="alert-banner alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Your account is not linked to a student record yet. Please tell the Program Head.</div><?php else: ?>

    <form method="POST" class="sr-card" id="srForm">
        <div class="cls-hint" style="margin:0 0 12px;background:#f1f8fb;border-radius:10px;padding:10px 14px;line-height:1.55;"><i class="fa-solid fa-circle-info"></i> This tells the College what you need, so the Program Head can read it. A Leave of Absence or an Honorable Dismissal is still processed by the Office of the University Registrar (the Student Manual 2025: a Leave of Absence needs the recommendation of the College Dean and the approval of the VPAA).</div>
        <strong style="font-size:16px;color:#064a5e;">1. What do you need?</strong>
        <div class="sr-types">
            <?php foreach ($types as $k): $ct = CASE_TYPES[$k]; $help = ['loa' => 'A pause in your studies, for up to one academic year. You stay a student and can come back.', 'hd' => 'You are leaving in good standing, for example to transfer to another school.', 'shifted' => 'You are moving to another program in the University.'][$k]; ?>
                <label class="sr-type" style="--c: <?= $ct['color'] ?>;"><input type="radio" name="type" value="<?= $k ?>" <?= $old['type'] === $k ? 'checked' : '' ?>><b><i class="fa-solid <?= $ct['icon'] ?>" style="color:<?= $ct['color'] ?>"></i> <?= htmlspecialchars($ct['label']) ?></b><span><?= $help ?></span></label>
            <?php endforeach; ?>
        </div>
        <strong style="font-size:16px;color:#064a5e;">2. When?</strong>
        <div class="row2">
            <div><label class="f" for="ay">School year</label><select name="ay" id="ay"><?php foreach (AY_ENTRY_OPTIONS as $o): ?><option <?= $o === $old['ay'] ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
            <div><label class="f" for="sem">Semester</label><select name="sem" id="sem"><?php foreach (CASE_SEMESTERS as $o): ?><option <?= $o === $old['sem'] ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
        </div>
        <div style="margin-top:20px;"><strong style="font-size:16px;color:#064a5e;">3. Why?</strong></div>
        <label class="f" for="reason">Your reason</label><select name="reason" id="reason"></select>
        <div id="otherBox" style="display:none;"><label class="f" for="other">Write your reason</label><input type="text" name="other" id="other" maxlength="255" value="<?= htmlspecialchars($old['other']) ?>"></div>
        <div id="toBox" style="display:none;"><label class="f" for="to">Which program are you shifting to?</label><input type="text" name="to" id="to" maxlength="100" value="<?= htmlspecialchars($old['to']) ?>" placeholder="For example, BS Computer Engineering"></div>
        <label class="f" for="details">Anything else the Program Head should know? <span style="font-weight:400;color:#6b8793;">(optional)</span></label>
        <textarea name="details" id="details" rows="3" maxlength="500"><?= htmlspecialchars($old['details']) ?></textarea>
        <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;">
            <button type="submit" class="btn-primary" style="padding:13px 24px;font-size:16px;"><i class="fa-solid fa-paper-plane"></i> Send my request</button>
            <a href="student_home.php" class="btn-secondary" style="text-decoration:none;padding:13px 22px;font-size:16px;">Cancel</a>
        </div>
    </form>
    <?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
<script>
(function () {
    var R = <?= json_encode($reasonMap, $J) ?>, KEEP = <?= json_encode($old['reason'], $J) ?>;
    var reason = document.getElementById('reason'), other = document.getElementById('otherBox'), to = document.getElementById('toBox');
    function type() { var r = document.querySelector('input[name="type"]:checked'); return r ? r.value : ''; }
    function fill() {
        var list = R[type()] || [];
        reason.innerHTML = '<option value="">' + (list.length ? 'Choose your reason' : 'First choose what you need') + '</option>' + list.map(function (x) { return '<option' + (x === KEEP ? ' selected' : '') + '>' + x + '</option>'; }).join('');
        to.style.display = type() === 'shifted' ? '' : 'none'; show();
    }
    function show() { other.style.display = reason.value === 'Others' ? '' : 'none'; }
    Array.prototype.forEach.call(document.querySelectorAll('input[name="type"]'), function (r) { r.addEventListener('change', function () { KEEP = ''; fill(); }); });
    reason.addEventListener('change', show); fill();
})();
</script>
</body>
</html>
