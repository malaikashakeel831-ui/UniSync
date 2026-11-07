<?php
/**
 * Every public page starts with:  require __DIR__ . '/../app/bootstrap.php';
 * It loads config, opens the session securely, and checks CSRF on POST.
 */

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$GLOBALS['config'] = $config;

date_default_timezone_set($config['timezone']);
ini_set('display_errors', $config['debug'] ? '1' : '0');
error_reporting(E_ALL);

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/services/GpaService.php';
require __DIR__ . '/services/PlannerService.php';
require __DIR__ . '/services/AnalyticsService.php';
require __DIR__ . '/services/NotificationService.php';

// ---------- secure session ----------
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('unisync_sid');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,       // JavaScript cannot read the cookie
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_start();

// Session expiry after inactivity
if (!empty($_SESSION['user_id'])) {
    $limit = $config['session_timeout_minutes'] * 60;
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $limit) {
        logout_user();
        session_start();
        flash('warning', 'Your session expired. Please log in again.');
    }
    $_SESSION['last_activity'] = time();
}

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CSRF check for every POST
if (is_post()) {
    verify_csrf();
}
