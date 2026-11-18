<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/security.php';

startSecureSession();

const ROLES = ['student', 'teacher', 'admin'];

/* ───────────────────────── session / current user ───────────────────────── */

function uaHash(): string { return hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''); }

function idleLimitSeconds(string $role): int
{
    $min = max(5, (int) setting('session_idle_minutes'));
    if ($role === 'admin') $min = min($min, ADMIN_MAX_IDLE_MINUTES);
    return $min * 60;
}

/** Validates the session on every request; returns the fresh user row or null. */
function sessionUser(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    $user = null;

    _resumeFromCookie();
    if (empty($_SESSION['user_id'])) return null;

    $st = getDB()->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([(int) $_SESSION['user_id']]);
    $row = $st->fetch();

    $bad = !$row
        || $row['status'] !== 'active'
        || (int) $row['session_version'] !== (int) ($_SESSION['sv'] ?? 0)
        || (BIND_SESSION_TO_UA && !hash_equals($_SESSION['ua'] ?? '', uaHash()));
    $now = time();
    $expired = $now - (int) ($_SESSION['last_activity'] ?? 0) > idleLimitSeconds($row['role'] ?? 'student')
            || $now - (int) ($_SESSION['created'] ?? 0) > SESSION_ABSOLUTE_SECONDS;

    if ($bad || $expired) {
        $reason = $expired && !$bad ? 'timeout' : 'revoked';
        _wipeSession();
        startSecureSession();
        $_SESSION['logout_reason'] = $reason;
        return null;
    }

    $_SESSION['last_activity'] = $now;
    $_SESSION['user_name'] = $row['name'];
    $_SESSION['role']      = $row['role'];
    return $user = $row;
}

function isLoggedIn(): bool { return sessionUser() !== null; }
function isAdmin(): bool    { return (sessionUser()['role'] ?? '') === 'admin'; }
function isTeacher(): bool  { return (sessionUser()['role'] ?? '') === 'teacher'; }
/** Teachers and admins: every section unlocked, answer keys visible. */
function isStaff(): bool    { return in_array(sessionUser()['role'] ?? '', ['teacher', 'admin'], true); }

function currentUser(): ?array
{
    $u = sessionUser();
    return $u ? ['id' => (int) $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'],
                 'totp_enabled' => (bool) $u['totp_enabled'], 'must_change_password' => (bool) $u['must_change_password']] : null;
}

function requireLogin(string $redirect = '/login.php', ?string $role = null): void
{
    $u = sessionUser();
    if (!$u) {
        $reason = $_SESSION['logout_reason'] ?? '';
        // A session cookie was sent but holds no login: record it so the cause can be found in the audit log.
        if ($reason === '' && !empty($_COOKIE[session_name()])) {
            auditLog('session.lost', 'page=' . basename($_SERVER['SCRIPT_NAME'] ?? '') . ' ua=' . substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 90));
        }
        redirect(u($redirect) . ($reason ? '?reason=' . $reason : ''));
    }
    if ($role !== null && $u['role'] !== $role) {
        http_response_code(403);
        flashSet('error', 'You do not have permission to open that page.');
        auditLog('access.denied', ($_SERVER['REQUEST_URI'] ?? ''));
        redirect(u('/index.php'));
    }
    // forced password change / mandatory 2FA are enforced everywhere except the account page itself
    $self = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($self !== 'account.php' && $self !== 'logout.php') {
        if ($u['must_change_password']) {
            flashSet('error', 'Please choose a new password before continuing.');
            redirect(u('/account.php'));
        }
        if (!$u['totp_enabled'] && in_array($u['role'], ['admin', 'teacher'], true) && setting('require_2fa_' . $u['role']) === '1') {
            flashSet('error', 'Two-factor authentication is required for your role. Set it up to continue.');
            redirect(u('/account.php#two-factor'));
        }
    }
}

function requireAdmin(): void { requireLogin('/login.php', 'admin'); }
/** Where each role starts after signing in. */
function landingPath(string $role): string
{
    return $role === 'admin' ? '/admin/index.php' : ($role === 'teacher' ? '/teacher/index.php' : '/index.php');
}

/** The teacher panel (lessons, gradebook, announcements) is for teachers only; admins run the platform. */
function requireTeacher(): void { requireLogin('/login.php', 'teacher'); }

/** Re-checks the signed-in user's password before a sensitive change (e.g. deleting a lesson). */
function confirmPassword(string $pw, string $auditAction = 'staff.bad_confirmation'): bool
{
    $u = sessionUser();
    if ($u && $pw !== '' && password_verify($pw, $u['password_hash'])) return true;
    if ($u) recordAttempt($u['email'], false);
    auditLog($auditAction, 'wrong password on a sensitive action');
    return false;
}

function _wipeSession(): void
{
    if (!empty($_COOKIE['remember_token'])) {
        getDB()->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([hash('sha256', $_COOKIE['remember_token'])]);
        setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

function destroySession(): void
{
    if (!empty($_SESSION['user_id'])) auditLog('logout');
    _wipeSession();
}

/* ───────────────────────── login ───────────────────────── */

function completeLogin(array $user, bool $remember): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => (int) $user['id'], 'user_name' => $user['name'], 'role' => $user['role'],
        'sv' => (int) $user['session_version'], 'ua' => uaHash(),
        'created' => time(), 'last_activity' => time(),
    ];
    getDB()->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP, last_login_ip = ? WHERE id = ?")->execute([clientIp(), $user['id']]);
    recordAttempt($user['email'], true);
    clearFailedAttempts($user['email']);
    auditLog('login.success', ($user['totp_enabled'] ? '2FA' : 'password only'), (int) $user['id'], $user['name']);
    // "remember me" is never offered to administrators
    if ($remember && $user['role'] !== 'admin') _createRememberToken((int) $user['id']);
}

/**
 * @return array{status:string, user?:array, retry_after?:int, remaining?:int}
 * status: ok | 2fa | invalid | locked | suspended | throttled
 */
function attemptLogin(string $email, string $password, bool $remember = false): array
{
    $email = strtolower(trim($email));

    if (ipFailures(15) >= 25) return ['status' => 'throttled', 'retry_after' => 900];
    if ($wait = lockoutRemaining($email)) {
        auditLog('login.locked', $email, null, $email);
        return ['status' => 'locked', 'retry_after' => $wait];
    }

    $st = getDB()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $user = $st->fetch();

    // Always run one hash verification so response time doesn't reveal whether the account exists.
    static $dummy = '$2y$10$7AC.l2HJ2V7nXcH4HRmSzetaE16hodXmS2VFbnj9cFAB7nGB1JnxC';
    $valid = password_verify($password, $user['password_hash'] ?? $dummy) && $user;

    if (!$valid) {
        recordAttempt($email, false);
        auditLog('login.failed', $email, $user ? (int) $user['id'] : null, $email);
        $max = max(1, (int) setting('max_failed_logins'));
        $left = $max - count(failedAttemptsByEmail($email));
        if ($left <= 0) return ['status' => 'locked', 'retry_after' => lockoutRemaining($email)];
        return ['status' => 'invalid', 'remaining' => $left];
    }

    if ($user['status'] !== 'active') {
        auditLog('login.suspended', $email, (int) $user['id'], $user['name']);
        return ['status' => 'suspended'];
    }

    // upgrade old hashes transparently
    if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
        getDB()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([hashPassword($password), $user['id']]);
    }

    if ($user['totp_enabled']) {
        session_regenerate_id(true);
        $_SESSION = ['pending_2fa' => ['uid' => (int) $user['id'], 'remember' => $remember, 'time' => time(), 'tries' => 0]];
        return ['status' => '2fa', 'user' => $user];
    }

    completeLogin($user, $remember);
    return ['status' => 'ok', 'user' => $user];
}

/** Verifies a 2FA step. @return bool true when login is now complete */
function verifySecondFactor(string $code): bool
{
    $p = $_SESSION['pending_2fa'] ?? null;
    if (!$p || time() - $p['time'] > 300) { unset($_SESSION['pending_2fa']); return false; }

    $st = getDB()->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $st->execute([$p['uid']]);
    $user = $st->fetch();
    if (!$user) { unset($_SESSION['pending_2fa']); return false; }

    $step = totpVerify((string) $user['totp_secret'], $code, (int) $user['totp_last_step']);
    if ($step !== null) {
        getDB()->prepare("UPDATE users SET totp_last_step = ? WHERE id = ?")->execute([$step, $user['id']]);
    } else {
        // recovery code (single use)
        $codes = json_decode((string) $user['recovery_codes'], true) ?: [];
        $norm = strtoupper(trim($code));
        foreach ($codes as $i => $hash) {
            if (password_verify($norm, $hash)) {
                unset($codes[$i]);
                getDB()->prepare("UPDATE users SET recovery_codes = ? WHERE id = ?")->execute([json_encode(array_values($codes)), $user['id']]);
                auditLog('2fa.recovery_used', '', (int) $user['id'], $user['name']);
                $step = 0;
                break;
            }
        }
    }

    if ($step === null) {
        $_SESSION['pending_2fa']['tries']++;
        recordAttempt($user['email'], false);
        auditLog('2fa.failed', '', (int) $user['id'], $user['name']);
        if ($_SESSION['pending_2fa']['tries'] >= 5 || lockoutRemaining($user['email'])) unset($_SESSION['pending_2fa']);
        return false;
    }

    $remember = (bool) $p['remember'];
    completeLogin($user, $remember);
    return true;
}

/* ───────────────────────── remember-me ───────────────────────── */

function _createRememberToken(int $userId): void
{
    $raw  = bin2hex(random_bytes(32));
    $db   = getDB();
    $db->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND expires_at < CURRENT_TIMESTAMP")->execute([$userId]);
    $db->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
       ->execute([$userId, hash('sha256', $raw), date('Y-m-d H:i:s', time() + 14 * 86400)]);
    setcookie('remember_token', $raw, ['expires' => time() + 14 * 86400, 'path' => '/', 'secure' => isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
}

function _resumeFromCookie(): void
{
    if (!empty($_SESSION['user_id']) || !empty($_SESSION['pending_2fa']) || empty($_COOKIE['remember_token'])) return;

    $hash = hash('sha256', (string) $_COOKIE['remember_token']);
    $st = getDB()->prepare("
        SELECT rt.id AS rt_id, u.* FROM remember_tokens rt JOIN users u ON u.id = rt.user_id
        WHERE rt.token_hash = ? AND rt.expires_at > CURRENT_TIMESTAMP AND u.status = 'active' AND u.role != 'admin' LIMIT 1");
    $st->execute([$hash]);
    $row = $st->fetch();
    if (!$row) return;

    // rotate the token on use
    getDB()->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$row['rt_id']]);
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int) $row['id'], 'user_name' => $row['name'], 'role' => $row['role'],
                 'sv' => (int) $row['session_version'], 'ua' => uaHash(), 'created' => time(), 'last_activity' => time()];
    _createRememberToken((int) $row['id']);
    auditLog('login.remembered', '', (int) $row['id'], $row['name']);
}

/* ───────────────────────── password reset ───────────────────────── */

function createPasswordResetToken(string $email): ?string
{
    $db = getDB();
    $st = $db->prepare("SELECT id, status FROM users WHERE email = ? LIMIT 1");
    $st->execute([strtolower(trim($email))]);
    $user = $st->fetch();
    if (!$user || $user['status'] !== 'active') return null;

    $token = bin2hex(random_bytes(32));
    $db->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
    $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")
       ->execute([$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 1800)]);
    auditLog('password.reset_requested', $email, (int) $user['id'], $email);
    return $token;
}

function validateResetToken(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $st = getDB()->prepare("
        SELECT pr.id AS pr_id, u.* FROM password_resets pr JOIN users u ON u.id = pr.user_id
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > CURRENT_TIMESTAMP LIMIT 1");
    $st->execute([hash('sha256', $token)]);
    return $st->fetch() ?: null;
}

/** Sets a new password and signs the user out of every device. */
function setUserPassword(int $userId, string $newPassword, bool $mustChange = false): void
{
    $db = getDB();
    $db->prepare("UPDATE users SET password_hash = ?, must_change_password = ?, session_version = session_version + 1,
                  password_changed_at = CURRENT_TIMESTAMP WHERE id = ?")
       ->execute([hashPassword($newPassword), $mustChange ? 1 : 0, $userId]);
    $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);
}

function consumeResetToken(string $token, string $newPassword): bool
{
    $row = validateResetToken($token);
    if (!$row) return false;
    setUserPassword((int) $row['id'], $newPassword);
    getDB()->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$row['pr_id']]);
    clearFailedAttempts($row['email']);
    auditLog('password.reset_done', '', (int) $row['id'], $row['name']);
    return true;
}
