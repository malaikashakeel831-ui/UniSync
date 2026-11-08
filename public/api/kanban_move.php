<?php
// POST {id, status} -> moves a group task to another Kanban column
require __DIR__ . '/../../app/bootstrap.php';
if (!is_post()) abort(405, 'POST only');
require_student();

$in = json_input();
$taskId = (int)($in['id'] ?? 0);
$status = one_of($in['status'] ?? '', ['todo', 'doing', 'done'], null);
if (!$status) abort(422, 'Invalid status');

$task = q1('SELECT id, group_id FROM project_tasks WHERE id = ?', [$taskId]);
if (!$task) abort(404, 'Task not found');
require_group_member((int)$task['group_id']);       // only members may move cards

q('UPDATE project_tasks SET status = ? WHERE id = ?', [$status, $taskId]);

$p = q1("SELECT COUNT(*) total, SUM(status = 'done') done FROM project_tasks WHERE group_id = ?", [$task['group_id']]);
json_out(['ok' => true, 'progress' => $p['total'] ? (int)round($p['done'] / $p['total'] * 100) : 0]);
