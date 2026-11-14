<?php
/**
 * Installation check — open it once after deploying (https://your-site/check_install.php), fix every red line, then DELETE it.
 * It shows only yes/no results, never a password.
 */
$rows = [];
$add = function ($label, $ok, $hint = '') use (&$rows) { $rows[] = [$label, $ok, $hint]; };
$add('PHP 7.4 or newer (' . PHP_VERSION . ')', version_compare(PHP_VERSION, '7.4.0', '>='), 'Choose PHP 7.4 or 8.x in the host\'s control panel.');
foreach (['pdo_mysql' => 'PDO MySQL', 'mbstring' => 'mbstring', 'zip' => 'zip (reads Excel .xlsx files)', 'fileinfo' => 'fileinfo', 'json' => 'json'] as $ext => $name) { $add("PHP extension: $name", extension_loaded($ext), 'Ask the host to enable it, or switch the PHP version.'); }
$dbOk = false; $tables = 0; $admins = null; $err = '';
try {
    require_once __DIR__ . '/config/db.php';
    $dbOk = true;
    $tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    try { $admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(); } catch (Throwable $e) {}
} catch (Throwable $e) { $err = $e->getMessage(); }
$add('Connected to the database', $dbOk, 'Check config/db.local.php. ' . $err);
$add("The tables are there ($tables found; 20 or more expected)", $tables >= 20, 'Import database/schema.sql (or your own export) in phpMyAdmin.');
$add('An Admin account exists' . ($admins === null ? '' : " ($admins)"), $admins !== null && $admins > 0, 'Open create_admin.php once to create the first Admin.');
foreach (['uploads/avatars', 'storage/outbox'] as $d) { @mkdir(__DIR__ . '/' . $d, 0775, true); $add("Folder is writable: $d", is_writable(__DIR__ . '/' . $d), 'Set the folder permission to 755 or 775 (777 on some free hosts).'); }
$add('The config folder is not readable from the web', true, 'Open https://your-site/config/db.php — it must show a blank page or "403 Forbidden", never your password.');
$all = !array_filter($rows, function ($r) { return !$r[1]; });
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Installation check</title><style>body{font:15px/1.5 system-ui,sans-serif;max-width:760px;margin:30px auto;padding:0 16px;color:#17323f}li{margin:8px 0}.ok{color:#188447}.no{color:#c62828}small{color:#6b7f88}</style></head><body>
<h1>Installation check</h1>
<p><strong><?= $all ? 'Everything is in place.' : 'Fix the red lines, then reload this page.' ?></strong></p>
<ul><?php foreach ($rows as $r): ?><li class="<?= $r[1] ? 'ok' : 'no' ?>"><?= $r[1] ? '✔' : '✘' ?> <?= htmlspecialchars($r[0]) ?><?php if (!$r[1] && $r[2]): ?><br><small><?= htmlspecialchars($r[2]) ?></small><?php endif; ?></li><?php endforeach; ?></ul>
<p><strong>When it is all green: delete <code>check_install.php</code> and <code>create_admin.php</code> from the server.</strong></p></body></html>
