<?php
// POST {id} or {all: true} -> mark notification(s) as read
require __DIR__ . '/../../app/bootstrap.php';
if (!is_post()) abort(405, 'POST only');
$me = require_login();

$in = json_input();
if (!empty($in['all'])) {
    q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$me['id']]);
} else {
    q('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [(int)($in['id'] ?? 0), $me['id']]);
}
json_out(['ok' => true, 'unread' => NotificationService::unreadCount((int)$me['id'])]);
