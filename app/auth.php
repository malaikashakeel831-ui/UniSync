<?php
/**
 * Authentication & authorization.
 * Golden rule: never trust an ID sent by the browser. The user id always
 * comes from the server-side session.
 */

function current_user(): ?array
{
    // cached per request, keyed by the session user id (so it refreshes right after login)
    static $cache = [];
    $sid = (int)($_SESSION['user_id'] ?? 0);
    if (!$sid) return null;
    if (!array_key_exists($sid, $cache)) {
        $cache[$sid] = q1('SELECT id, full_name, email, student_code, role, is_active, created_at, last_login
                           FROM users WHERE id = ?', [$sid]);
    }
    return $cache[$sid];
}

function uid(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

/** Protect a page: must be logged in AND account still active. */
function require_login(): array
{
    $u = current_user();
    if (!$u) {
        if (is_api()) abort(401, 'Please log in.');
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
    if (!$u['is_active']) {          // checked on every request
        logout_user();
        session_start();
        flash('danger', 'Your account has been deactivated by the administrator.');
        redirect('login.php');
    }
    return $u;
}

/** Student-only pages (admin cannot see private academic data). */
function require_student(): array
{
    $u = require_login();
    if ($u['role'] !== 'student') {
        redirect('admin/dashboard.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') abort(403, 'This area is for administrators only.');
    return $u;
}

/** Group pages: returns the membership row or stops with 403. */
function require_group_member(int $groupId): array
{
    $m = q1('SELECT gm.member_role, g.* FROM group_members gm
             JOIN project_groups g ON g.id = gm.group_id
             WHERE gm.group_id = ? AND gm.user_id = ?', [$groupId, uid()]);
    if (!$m) abort(403, 'You are not a member of this group.');
    return $m;
}

/**
 * Try to log in. Returns null on success or an error message.
 * Implements brute-force protection with failed_logins / locked_until.
 */
function attempt_login(string $email, string $password): ?string
{
    $cfg = $GLOBALS['config'];
    $u = q1('SELECT * FROM users WHERE email = ?', [$email]);
    $generic = 'Invalid email or password.';

    if (!$u) {
        password_verify($password, '$2y$10$usesomesillystringforsaltxxxxxxxxxxxxxxxxxxxxxxxxxxxx'); // timing
        audit('Failed login (unknown email)');
        return $generic;
    }
    if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
        $mins = (int)ceil((strtotime($u['locked_until']) - time()) / 60);
        return "Too many failed attempts. Account locked for $mins more minute(s).";
    }
    if (!password_verify($password, $u['password_hash'])) {
        $fails = $u['failed_logins'] + 1;
        if ($fails >= $cfg['max_failed_logins']) {
            q('UPDATE users SET failed_logins = 0, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?',
                [$cfg['lockout_minutes'], $u['id']]);
            audit('Account locked after failed logins', (int)$u['id']);
            return 'Too many failed attempts. Your account is locked for ' . $cfg['lockout_minutes'] . ' minutes.';
        }
        q('UPDATE users SET failed_logins = ? WHERE id = ?', [$fails, $u['id']]);
        audit('Failed login', (int)$u['id']);
        $left = $cfg['max_failed_logins'] - $fails;
        return $generic . " ($left attempt" . ($left == 1 ? '' : 's') . ' left)';
    }
    if (!$u['is_active']) {
        return 'Your account has been deactivated. Please contact the administrator.';
    }

    // Success
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    session_regenerate_id(true);           // prevent session fixation
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['role'] = $u['role'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf']);
    q('UPDATE users SET failed_logins = 0, locked_until = NULL, last_login = NOW() WHERE id = ?', [$u['id']]);
    audit('Logged in', (int)$u['id']);
    return null;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Password rule: 8+ chars, at least one letter and one number. */
function password_problem(string $p): ?string
{
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Password must contain letters and numbers.';
    return null;
}
