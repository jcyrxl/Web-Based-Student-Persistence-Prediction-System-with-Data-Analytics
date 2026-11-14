<?php
session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$userId   = trim($_POST['userid'] ?? '');
$password = $_POST['password'] ?? ''; // never trim a password — spaces can be intentional

if ($userId === '' || $password === '') {
    $_SESSION['login_userid'] = $userId;      // keep what was typed, so the field is not reset
    header('Location: index.php?error=empty');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($user && $user['role'] === 'student' && password_verify($password, $user['password_hash'])) {
    // a student can open the system only while he or she is enrolled (not graduated, on leave, dismissed, dropped or shifted)
    $stq = $pdo->prepare("SELECT status FROM students WHERE student_id = ?"); $stq->execute([$user['user_id']]); $stst = $stq->fetchColumn();
    if ($stst !== 'enrolled') {
        $_SESSION['login_userid'] = $userId;
        header('Location: index.php?error=notenrolled');
        exit;
    }
}

if ($user && password_verify($password, $user['password_hash'])) {
    // Success: store session
    $_SESSION['user_id']             = $user['user_id'];
    $_SESSION['full_name']           = $user['full_name'];
    $_SESSION['role']                = $user['role'];
    $_SESSION['must_change_password'] = (bool) $user['must_change_password'];

    // A temporary (default / reset) password forces a stop at Privacy Settings first,
    // regardless of role, before anything else is reachable.
    if ($_SESSION['must_change_password']) {
        header('Location: registrar/privacy_settings.php');
    } elseif ($user['role'] === 'student') {
        header('Location: registrar/student_home.php');
    } elseif ($user['role'] === 'admin') {
        // The Admin's whole job is accounts and class assignments — User Management is home.
        header('Location: registrar/users.php');
    } else {
        header('Location: registrar/dashboard.php');
    }
    exit;
} else {
    $_SESSION['login_userid'] = $userId;      // keep what was typed (the person may be trying a different User ID), so the field is not reset
    header('Location: index.php?error=invalid');
    exit;
}
