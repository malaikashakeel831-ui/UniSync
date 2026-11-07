<?php
/**
 * Small helper functions used everywhere.
 */

/** Escape output (XSS protection). Use for EVERY value printed into HTML. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Base URL of the public folder, auto-detected. */
function base_url(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $cfg = $GLOBALS['config']['base_url'];
    if ($cfg !== null) return $base = rtrim($cfg, '/');

    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = preg_replace('#/(admin|api)$#', '', $dir);
    $dir = rtrim($dir, '/');
    // When the root .htaccess rewrites "/x.php" -> "/public/x.php", hide "/public"
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (substr($dir, -7) === '/public' && strpos($uri, $dir . '/') !== 0 && $uri !== $dir) {
        $dir = substr($dir, 0, -7);
    }
    return $base = $dir;
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = dirname(__DIR__) . '/public/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . $path) . '?v=' . $v;
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

/** Flash messages (shown once on the next page). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Remember old form input after a validation error. */
function old(string $key, $default = '')
{
    return $_SESSION['old'][$key] ?? $default;
}

function keep_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['current_password'], $data['csrf']);
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

/** Stop and show an error page (403 / 404). Never reveals whether a record exists. */
function abort(int $code, string $message = ''): void
{
    http_response_code($code);
    if (is_api()) {
        json_out(['ok' => false, 'error' => $message ?: 'Error ' . $code], $code);
    }
    $titles = [403 => 'Access denied', 404 => 'Not found', 419 => 'Session expired'];
    $title = $titles[$code] ?? 'Error';
    $message = $message ?: ($code === 404 ? 'The page or record you are looking for does not exist.' : 'You are not allowed to do this.');
    require __DIR__ . '/views/error.php';
    exit;
}

function is_api(): bool
{
    return strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/') !== false;
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/** Read JSON request body (for fetch() calls). */
function json_input(): array
{
    $raw = file_get_contents('php://input');
    $d = json_decode($raw ?: '[]', true);
    return is_array($d) ? $d : [];
}

function post(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Write to the audit log for the admin. */
function audit(string $action, ?int $userId = null): void
{
    try {
        q('INSERT INTO audit_log (user_id, action, ip) VALUES (?, ?, ?)',
            [$userId ?? ($_SESSION['user_id'] ?? null), mb_substr($action, 0, 100), $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    } catch (Throwable $e) { /* never break the page for a log line */ }
}

// ---------- formatting helpers ----------

const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

function fmt_date(?string $d, string $f = 'D, d M Y'): string
{
    return $d ? date($f, strtotime($d)) : '—';
}

function fmt_datetime(?string $d): string
{
    return $d ? date('d M Y, g:i A', strtotime($d)) : '—';
}

/** "in 3 days", "2 hours ago" etc. */
function relative_time(string $datetime): string
{
    $diff = strtotime($datetime) - time();
    $abs = abs($diff);
    if ($abs < 60) return 'just now';
    $units = [86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $secs => $name) {
        if ($abs >= $secs) {
            $n = (int)floor($abs / $secs);
            $s = $n . ' ' . $name . ($n > 1 ? 's' : '');
            return $diff > 0 ? 'in ' . $s : $s . ' ago';
        }
    }
    return '';
}

function num($n, int $dec = 2): string
{
    return number_format((float)$n, $dec);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $i = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1));
    if (count($parts) > 1) $i .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    return $i;
}

function valid_color(string $c): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#5b5bd6';
}

/** Value must be one of the allowed list, otherwise the default. */
function one_of($value, array $allowed, $default)
{
    return in_array($value, $allowed, true) ? $value : $default;
}

const TASK_TYPES = ['assignment', 'quiz', 'exam', 'project', 'other'];
const PRIORITIES = ['low', 'medium', 'high'];
const STATUSES   = ['pending', 'in_progress', 'done'];

function type_icon(string $type): string
{
    return ['assignment' => 'bi-journal-text', 'quiz' => 'bi-patch-question', 'exam' => 'bi-mortarboard',
            'project' => 'bi-kanban', 'other' => 'bi-bookmark'][$type] ?? 'bi-bookmark';
}
