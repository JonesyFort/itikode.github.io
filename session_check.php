<?php
declare(strict_types=1);
/**
 * Diagnostic page: open http://localhost:8000/session_check.php to see what the
 * server knows about your login session. Safe to delete once login works.
 */
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: text/plain; charset=utf-8');

$dir = session_save_path() ?: sys_get_temp_dir();
$sid = session_id();
$row = fn(string $k, string $v) => print(str_pad($k, 34) . $v . "\n");

echo "ITiKODE session check\n=====================\n";
$row('Time', date('Y-m-d H:i:s'));
$row('Address used', (string) ($_SERVER['HTTP_HOST'] ?? '?'));
$row('Cookie name', session_name());
$row('Cookie sent by browser?', isset($_COOKIE[session_name()]) ? 'yes' : 'NO');
$row('Session id (first 6 chars)', substr($sid, 0, 6));
$row('Logged in?', !empty($_SESSION['user_id']) ? 'yes (user id ' . (int) $_SESSION['user_id'] . ', ' . ($_SESSION['role'] ?? '?') . ')' : 'NO');
$row('Seconds since last activity', isset($_SESSION['last_activity']) ? (string) (time() - (int) $_SESSION['last_activity']) : '-');
$row('Session folder', $dir);
$row('Folder writable?', is_writable($dir) ? 'yes' : 'NO');
$row('Session file exists?', is_file($dir . '/sess_' . $sid) ? 'yes' : 'no');
$row('Bound to browser (UA)?', BIND_SESSION_TO_UA ? 'yes' : 'no');
$row('Browser (User-Agent)', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 110));
$row('Server port', (string) ($_SERVER['SERVER_PORT'] ?? '?'));
$row('PHP version', PHP_VERSION);
$row('Idle limit (seconds)', (string) idleLimitSeconds('student'));
echo "\nTip: log in, open this page, then tap a lesson, and open this page again.\n";
