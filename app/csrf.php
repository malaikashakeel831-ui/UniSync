<?php
/**
 * CSRF protection: a random token stored in the session must be sent
 * with every POST request (hidden form field or X-CSRF-Token header).
 */

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

/** Called automatically by bootstrap.php for every POST request. */
function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        abort(419, 'Your session expired or the form was tampered with. Please go back, refresh the page and try again.');
    }
}
