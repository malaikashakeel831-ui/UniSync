<?php
// POST {id} -> toggles a task between done / pending (one-click completion)
require __DIR__ . '/../../app/bootstrap.php';
if (!is_post()) abort(405, 'POST only');
$me = require_student();

$in = json_input();
$taskId = (int)($in['id'] ?? 0);
$task = q1('SELECT id, status FROM tasks WHERE id = ? AND user_id = ?', [$taskId, $me['id']]);
if (!$task) abort(404, 'Task not found');

$new = $task['status'] === 'done' ? 'pending' : 'done';
q('UPDATE tasks SET status = ?, completed_at = ? WHERE id = ? AND user_id = ?',
    [$new, $new === 'done' ? date('Y-m-d H:i:s') : null, $taskId, $me['id']]);

json_out(['ok' => true, 'status' => $new]);
