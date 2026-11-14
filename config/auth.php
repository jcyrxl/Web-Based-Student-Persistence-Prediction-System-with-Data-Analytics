<?php
/**
 * Include this at the very top of every protected registrar page.
 * Starts the session and kicks the user back to login if not authenticated.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// An Admin-triggered password reset forces the user to set a new password
// before they can reach anything else in the system.
if (!empty($_SESSION['must_change_password']) && basename($_SERVER['SCRIPT_NAME']) !== 'privacy_settings.php') {
    header('Location: privacy_settings.php');
    exit;
}

// A student sees only his or her own pages (classes, grades, attendance, requests, profile and privacy settings) — never the pages of the staff.
if (($_SESSION['role'] ?? '') === 'student') {
    $studentPages = ['student_home.php', 'student_request.php', 'profile.php', 'privacy_settings.php', 'logout.php', 'send_code.php', 'verify.php'];
    if (!in_array(basename($_SERVER['SCRIPT_NAME']), $studentPages, true)) { header('Location: student_home.php'); exit; }
}

