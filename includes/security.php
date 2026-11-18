<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

/* ───────────────────────── paths & output helpers ───────────────────────── */

/** URL prefix of the app (empty when the app is the web root). */
function appBase(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $root = realpath(dirname(__DIR__)) ?: '';
    $doc  = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $base = ($doc !== '' && str_starts_with($root, $doc)) ? str_replace('\\', '/', substr($root, strlen($doc))) : '';
    return rtrim($base, '/');
}

function u(string $path): string { return appBase() . $path; }

function e(?string $str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function clientIp(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function isHttps(): bool    { return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; }

function flashSet(string $key, string $msg): void { $_SESSION['flash'][$key] = $msg; }
function flashGet(string $key): string
{
    $msg = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $msg;
}

/* ───────────────────────── settings ───────────────────────── */

function setting(string $key): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = DEFAULT_SETTINGS;
        foreach (getDB()->query("SELECT key, value FROM settings")->fetchAll() as $r) $cache[$r['key']] = $r['value'];
    }
    return $cache[$key] ?? (DEFAULT_SETTINGS[$key] ?? '');
}

function settingSet(string $key, string $value): void
{
    getDB()->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value")
           ->execute([$key, $value]);
}

/* ───────────────────────── headers & session ───────────────────────── */

function sendSecurityHeaders(): void
{
    if (headers_sent()) return;
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('Cache-Control: no-store, max-age=0');
    if (isHttps()) header('Strict-Transport-Security: max-age=15552000');
}

function startSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;
    sendSecurityHeaders();
    // One cookie name per port: browsers share cookies between ports on the same host (localhost),
    // so two apps on localhost:8000 and localhost:8080 must not overwrite each other's session.
    session_name('ITIKODE_SID_' . preg_replace('/\D/', '', (string) ($_SERVER['SERVER_PORT'] ?? '80')));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => isHttps(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

/* ───────────────────────── CSRF ───────────────────────── */

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

/** Call at the top of every POST handler. */
function verifyCsrf(): void
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals(csrfToken(), $sent)) {
        http_response_code(419);
        echo '<!doctype html><meta charset="utf-8"><title>Session expired</title>'
           . '<body style="font-family:system-ui;background:#E6F0DC;display:grid;place-items:center;min-height:100vh">'
           . '<div style="background:#fff;border:2px solid #55883B;border-radius:16px;padding:28px;max-width:380px;text-align:center">'
           . '<h1 style="color:#2D4A1C;font-size:18px">Your form expired</h1>'
           . '<p style="color:#3A5228;font-size:14px">For your security the request was blocked. Go back, refresh the page and try again.</p>'
           . '<a href="javascript:history.back()" style="color:#55883B;font-weight:700">Go back</a></div></body>';
        exit;
    }
}

/* ───────────────────────── passwords ───────────────────────── */

function hashPassword(string $pw): string
{
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    return password_hash($pw, $algo);
}

const COMMON_PASSWORDS = ['password', 'password1', 'password123', '12345678', '123456789', 'qwerty123', 'iloveyou', 'admin123',
    'letmein123', 'welcome123', 'student123', 'teacher123', 'abc12345', 'python123', 'itikode123'];

/** @return string[] list of problems (empty = acceptable) */
function passwordProblems(string $pw, string $role = 'student', string $email = '', string $name = ''): array
{
    $min = in_array($role, ['admin', 'teacher'], true) ? 12 : 8;
    $p = [];
    if (strlen($pw) < $min) $p[] = "Use at least $min characters.";
    if (strlen($pw) > 128)  $p[] = 'Use 128 characters or fewer.';
    $classes = (preg_match('/[a-z]/', $pw) ? 1 : 0) + (preg_match('/[A-Z]/', $pw) ? 1 : 0)
             + (preg_match('/\d/', $pw) ? 1 : 0) + (preg_match('/[^a-zA-Z0-9]/', $pw) ? 1 : 0);
    if ($classes < 3) $p[] = 'Mix at least three of: lowercase, UPPERCASE, numbers, symbols.';
    $low = strtolower($pw);
    if (in_array($low, COMMON_PASSWORDS, true)) $p[] = 'That password is too common.';
    $local = strtolower(explode('@', $email)[0] ?? '');
    if (strlen($local) >= 4 && str_contains($low, $local)) $p[] = 'Do not include your e-mail name in the password.';
    return $p;
}

/* ───────────────────────── audit log & throttling ───────────────────────── */

function auditLog(string $action, string $detail = '', ?int $userId = null, ?string $actor = null): void
{
    $uid = $userId ?? ($_SESSION['user_id'] ?? null);
    $actor ??= $_SESSION['user_name'] ?? '';
    getDB()->prepare("INSERT INTO audit_log (user_id, actor, action, detail, ip) VALUES (?,?,?,?,?)")
           ->execute([$uid, $actor, $action, mb_substr($detail, 0, 500), clientIp()]);
}

function recordAttempt(string $email, bool $success): void
{
    getDB()->prepare("INSERT INTO login_attempts (email, ip, success, created_at) VALUES (?,?,?,?)")
           ->execute([strtolower($email), clientIp(), $success ? 1 : 0, time()]);
}

function failedAttemptsByEmail(string $email): array
{
    $window = max(1, (int) setting('lockout_minutes')) * 60;
    $st = getDB()->prepare("SELECT created_at FROM login_attempts WHERE email = ? AND success = 0 AND created_at > ? ORDER BY created_at DESC");
    $st->execute([strtolower($email), time() - $window]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Seconds the e-mail stays locked out (0 = not locked). */
function lockoutRemaining(string $email): int
{
    $max   = max(1, (int) setting('max_failed_logins'));
    $times = failedAttemptsByEmail($email);
    if (count($times) < $max) return 0;
    $window = max(1, (int) setting('lockout_minutes')) * 60;
    return max(0, $times[$max - 1] + $window - time());
}

function ipFailures(int $minutes = 15): int
{
    $st = getDB()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > ?");
    $st->execute([clientIp(), time() - $minutes * 60]);
    return (int) $st->fetchColumn();
}

function clearFailedAttempts(string $email): void
{
    getDB()->prepare("DELETE FROM login_attempts WHERE email = ? AND success = 0")->execute([strtolower($email)]);
}

/* ───────────────────────── TOTP two-factor (RFC 6238) ───────────────────────── */

const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function totpNewSecret(): string
{
    $bytes = random_bytes(20); $bits = '';
    foreach (str_split($bytes) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= B32[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function base32Decode(string $s): string
{
    $s = strtoupper(preg_replace('/[^A-Z2-7]/', '', $s));
    $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin((int) strpos(B32, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function totpCode(string $secret, int $step): string
{
    $hash = hash_hmac('sha1', pack('N*', 0, $step), base32Decode($secret), true);
    $o = ord($hash[19]) & 0x0F;
    $n = ((ord($hash[$o]) & 0x7F) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** @return int|null the matched time-step, or null when the code is wrong */
function totpVerify(string $secret, string $code, int $minStep = 0): ?int
{
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/', $code)) return null;
    $now = intdiv(time(), 30);
    for ($d = -1; $d <= 1; $d++) {
        $step = $now + $d;
        if ($step > $minStep && hash_equals(totpCode($secret, $step), $code)) return $step;
    }
    return null;
}

function totpUri(string $secret, string $email): string
{
    $issuer = setting('site_name');
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
}

/** @return array{0:string[],1:string} plain codes (show once) and JSON of their hashes */
function newRecoveryCodes(int $n = 8): array
{
    $plain = $hashes = [];
    for ($i = 0; $i < $n; $i++) {
        $c = strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));
        $plain[] = $c; $hashes[] = password_hash($c, PASSWORD_BCRYPT);
    }
    return [$plain, json_encode($hashes)];
}
