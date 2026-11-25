<?php
declare(strict_types=1);

/**
 * Staff accounts and permissions.
 *   admin       — everything, including users, settings, backups, data retention
 *   coordinator — patient requests, forms inbox, directory, reports
 *   clinician   — patient requests (reviews referral decisions), reports
 *   viewer      — reports only (numbers, no personal data)
 */
const ROLES = [
    'admin'       => 'Administrator',
    'coordinator' => 'Patient coordinator',
    'clinician'   => 'Health professional (reviewer)',
    'viewer'      => 'Viewer (reports only)',
];

const PERMISSIONS = [
    'requests.view'      => ['admin', 'coordinator', 'clinician'],
    'requests.update'    => ['admin', 'coordinator', 'clinician'],
    'requests.export'    => ['admin', 'coordinator'],
    'requests.anonymise' => ['admin'],
    'submissions.view'   => ['admin', 'coordinator'],
    'submissions.update' => ['admin', 'coordinator'],
    'directory.manage'   => ['admin', 'coordinator'],
    'reports.view'       => ['admin', 'coordinator', 'clinician', 'viewer'],
    'notifications.view' => ['admin', 'coordinator'],
    'users.manage'       => ['admin'],
    'settings.manage'    => ['admin'],
    'audit.view'         => ['admin'],
];

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_name('IMARA_ADMIN');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    $now = time();
    if (isset($_SESSION['uid'])) {
        $idle = (int) config('session_idle_minutes', 30) * 60;
        $max = (int) config('session_max_hours', 12) * 3600;
        if ($now - (int) ($_SESSION['last_seen'] ?? 0) > $idle || $now - (int) ($_SESSION['login_at'] ?? 0) > $max) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['flash'] = ['type' => 'info', 'msg' => 'You were signed out after a period of inactivity.'];
        } else {
            $_SESSION['last_seen'] = $now;
        }
    }
}

function current_user(): ?array
{
    static $cache = [];
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) {
        return null;
    }
    if (!isset($cache[$uid])) {
        $st = db()->prepare('SELECT id, name, email, role, active, must_change_password FROM users WHERE id = ?');
        $st->execute([(int) $uid]);
        $u = $st->fetch();
        if (!$u || !(int) $u['active']) {
            $_SESSION = [];
            return null;
        }
        $cache[$uid] = $u;
    }
    return $cache[$uid];
}

function can(string $permission, ?array $user = null): bool
{
    $user ??= current_user();
    return $user !== null && in_array($user['role'], PERMISSIONS[$permission] ?? [], true);
}

/** Send security headers for every admin page. */
function admin_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, private');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    header('X-Robots-Tag: noindex, nofollow');
}

/** Gate an admin page. Optionally require a permission. Returns the signed-in user. */
function require_login(?string $permission = null): array
{
    start_secure_session();
    admin_headers();
    $user = current_user();
    if (!$user) {
        $next = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        if (!empty($_SERVER['QUERY_STRING'])) {
            $next .= '?' . $_SERVER['QUERY_STRING'];
        }
        header('Location: login.php?next=' . rawurlencode($next));
        exit;
    }
    if ((int) $user['must_change_password'] === 1 && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'account.php') {
        header('Location: account.php?first=1');
        exit;
    }
    if ($permission && !can($permission, $user)) {
        http_response_code(403);
        render_admin_page('Not allowed', '<div class="card"><h2>Not allowed</h2><p>Your role does not include this page. Ask an administrator if you need access.</p></div>');
        exit;
    }
    return $user;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), $sent)) {
        http_response_code(400);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function login_locked(string $email): bool
{
    $since = time() - (int) config('login_lock_minutes', 15) * 60;
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > ? AND (email = ? OR ip = ?)');
    $st->execute([$since, mb_strtolower($email), client_ip()]);
    return (int) $st->fetchColumn() >= (int) config('login_max_attempts', 5);
}

/** Returns the user on success, or an error string. */
function attempt_login(string $email, string $password): array|string
{
    $email = mb_strtolower(trim($email));
    if (login_locked($email)) {
        audit('login_locked', 'user', null, $email);
        return 'Too many attempts. Please wait ' . (int) config('login_lock_minutes', 15) . ' minutes and try again.';
    }
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    // Always run password_verify so timing does not reveal which emails exist.
    $hash = $u['password_hash'] ?? '$2y$10$I1Ar83E/KwVYyoslJxrvlOeSfoqNFTnaftKqw.cBEWxQDFGxfohhq';
    $ok = password_verify($password, $hash) && $u && (int) $u['active'] === 1;

    db()->prepare('INSERT INTO login_attempts (email, ip, created_at, success) VALUES (?,?,?,?)')
        ->execute([$email, client_ip(), time(), $ok ? 1 : 0]);

    if (!$ok) {
        audit('login_failed', 'user', $u ? (int) $u['id'] : null, $email);
        return 'Email or password is not correct.';
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    $_SESSION['login_at'] = time();
    $_SESSION['last_seen'] = time();
    unset($_SESSION['csrf']);
    db()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([now_utc(), $u['id']]);
    audit('login', 'user', (int) $u['id']);
    return $u;
}

function logout(): void
{
    if (current_user()) {
        audit('logout', 'user', (int) current_user()['id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
    }
    session_destroy();
}

function password_problem(string $pw): ?string
{
    if (mb_strlen($pw) < 10) {
        return 'Use at least 10 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'Use letters and at least one number.';
    }
    return null;
}

function create_user(string $name, string $email, string $password, string $role, bool $mustChange = true): int
{
    if (!isset(ROLES[$role])) {
        throw new InvalidArgumentException('Unknown role');
    }
    db()->prepare('INSERT INTO users (name, email, password_hash, role, active, must_change_password, created_at) VALUES (?,?,?,?,1,?,?)')
        ->execute([trim($name), mb_strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), $role, $mustChange ? 1 : 0, now_utc()]);
    return (int) db()->lastInsertId();
}

/** Shared admin page shell (sidebar + header). */
function render_admin_page(string $title, string $content, array $opts = []): void
{
    $user = current_user();
    require IMARA_APP . '/views/admin-layout.php';
}
