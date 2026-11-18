<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    destroySession();
    startSecureSession();
    flashSet('success', 'You have been signed out.');
}
redirect(u($_SERVER['REQUEST_METHOD'] === 'POST' ? '/login.php' : '/index.php'));
