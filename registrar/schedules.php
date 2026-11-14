<?php
/** Class scheduling has been removed from the Program Head workflow. Term dates remain available separately. */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/roles.php';
if (!canAssignClasses()) {
    header('Location: ' . (isAdmin() ? 'users.php' : 'dashboard.php'));
    exit;
}
header('Location: assignments.php');
exit;
