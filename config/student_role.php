<?php
/**
 * The Student user.
 *  - One row in users: role 'student', the User ID is the student number (so the account is linked to the student record).
 *  - The requests a student files (Leave of Absence, Honorable Dismissal, Shifted) wait in student_requests until the Program Head confirms them.
 *    Confirming = the Program Head has read it: the request becomes a case (student_cases) and the status of the student changes,
 *    so the graphs and the reports follow.
 */
require_once __DIR__ . '/academic.php';

/** Makes sure the database knows the role and the table (done once; never inside a transaction because ALTER ends it). */
function ensureStudentRole(PDO $pdo): void {
    static $done = false;
    if ($done) { return; }
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
        $type = (string) ($col['Type'] ?? '');
        if ($type !== '' && stripos($type, "'student'") === false) {
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('faculty','program_head','admin','student') NOT NULL DEFAULT 'faculty'");
        }
    } catch (Throwable $e) {}
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS student_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(20) NOT NULL,
            request_type ENUM('loa','hd','shifted') NOT NULL,
            academic_year VARCHAR(20) NOT NULL,
            semester VARCHAR(20) NOT NULL,
            reason VARCHAR(80) NOT NULL,
            reason_other VARCHAR(255) DEFAULT NULL,
            details VARCHAR(500) DEFAULT NULL,
            shifted_to VARCHAR(100) DEFAULT NULL,
            filed_on DATE NOT NULL,
            status ENUM('pending','confirmed') NOT NULL DEFAULT 'pending',
            confirmed_by VARCHAR(150) DEFAULT NULL,
            confirmed_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_student (student_id)
        )");
    } catch (Throwable $e) {}
}

function pendingRequestCount(PDO $pdo): int {
    try { return (int) $pdo->query("SELECT COUNT(*) FROM student_requests WHERE status = 'pending'")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

/** The request is read and confirmed: it becomes a case, and the status of the student follows. Call it inside a transaction. */
function confirmStudentRequest(PDO $pdo, array $req, string $byUser, string $byName): void {
    $type = $req['request_type'];
    if (!isset(CASE_TYPES[$type])) { throw new RuntimeException('Unknown kind of request.'); }
    $pdo->prepare("INSERT INTO student_cases (student_id, case_type, academic_year, semester, reason, reason_other, details, shifted_to, filed_on, recorded_by, recorded_by_user)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE reason = VALUES(reason), reason_other = VALUES(reason_other), details = VALUES(details), shifted_to = VALUES(shifted_to), filed_on = VALUES(filed_on), returned_on = NULL")
        ->execute([$req['student_id'], $type, $req['academic_year'], $req['semester'], $req['reason'], $req['reason_other'], $req['details'], $req['shifted_to'], $req['filed_on'], $byName, $byUser]);
    $pdo->prepare("UPDATE students SET status = ? WHERE student_id = ?")->execute([CASE_TYPES[$type]['status'], $req['student_id']]);
    $pdo->prepare("UPDATE student_requests SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?")->execute([$byName, (int) $req['id']]);
}

const REQUEST_STATUS_LABEL = ['pending' => 'Waiting for the Program Head', 'confirmed' => 'Confirmed'];


/** The schedule of a class (for example "Mon & Wed 8:00-9:30 · Room 301"), written by the Program Head and shown to the students. */
function ensureClassSchedule(PDO $pdo): bool {
    static $ok = null;
    if ($ok !== null) { return $ok; }
    try {
        $has = $pdo->query("SHOW COLUMNS FROM classes LIKE 'schedule'")->fetch() !== false;
        if (!$has) { $pdo->exec("ALTER TABLE classes ADD COLUMN schedule VARCHAR(80) NULL"); $has = $pdo->query("SHOW COLUMNS FROM classes LIKE 'schedule'")->fetch() !== false; }
        return $ok = $has;
    } catch (Throwable $e) { return $ok = false; }
}
