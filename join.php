<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/classes.php';

requireLogin('/login.php');
$user = currentUser();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(u('/index.php'));
verifyCsrf();

if (($user['role'] ?? '') !== 'student') {
    flashSet('error', 'Only student accounts can join a class.');
    redirect(u('/index.php'));
}

// allow a handful of wrong codes per ten minutes
$win = $_SESSION['join_fail'] ?? ['n' => 0, 't' => time()];
if (time() - (int) $win['t'] > 600) $win = ['n' => 0, 't' => time()];
if ((int) $win['n'] >= 8) {
    flashSet('error', 'Too many wrong codes. Please wait a few minutes and try again.');
    redirect(u('/index.php'));
}

$class = classByCode((string) ($_POST['code'] ?? ''));
if (!$class || $class['archived'] || !$class['joining_open']) {
    $win['n'] = (int) $win['n'] + 1;
    $_SESSION['join_fail'] = $win;
    flashSet('error', 'That class code did not work. Check it with your teacher.');
    redirect(u('/index.php'));
}

$added = addToClass((int) $class['id'], (int) $user['id']);
if ($added) auditLog('class.joined', $class['name'], (int) $user['id'], (string) $user['name']);
flashSet('success', $added ? 'You joined ' . $class['name'] . '.' : 'You are already in ' . $class['name'] . '.');
redirect(u('/index.php'));
