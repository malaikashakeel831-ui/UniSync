<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_student();
$id = (int)$me['id'];

if (is_post()) {
    $action = post('action');
    $taskId = (int)post('id', 0);

    if ($action === 'save') {
        $courseId = (int)post('course_id') ?: null;
        $title  = post('title');
        $type   = one_of(post('type'), TASK_TYPES, 'assignment');
        $due    = post('due_at');
        $prio   = one_of(post('priority'), PRIORITIES, 'medium');
        $hours  = (float)post('estimated_hours', 1);
        $status = one_of(post('status'), STATUSES, 'pending');
        $notes  = post('notes');

        $err = null;
        if ($courseId && !qv('SELECT 1 FROM courses WHERE id = ? AND user_id = ?', [$courseId, $id])) $err = 'Invalid course.';
        elseif (mb_strlen($title) < 2 || mb_strlen($title) > 150) $err = 'Title must be 2–150 characters.';
        elseif (!$due || !strtotime($due)) $err = 'Please choose a valid due date and time.';
        elseif ($hours < 0 || $hours > 200) $err = 'Estimated hours must be between 0 and 200.';

        if ($err) {
            flash('danger', $err);
            redirect('tasks.php' . ($taskId ? '?edit=' . $taskId : '?new=1'));
        }
        $dueAt = date('Y-m-d H:i:s', strtotime($due));
        if ($taskId) {
            $old = q1('SELECT status, completed_at FROM tasks WHERE id = ? AND user_id = ?', [$taskId, $id]);
            if (!$old) abort(404);
            $completedAt = $status === 'done' ? ($old['completed_at'] ?: date('Y-m-d H:i:s')) : null;
            q('UPDATE tasks SET course_id=?, title=?, type=?, due_at=?, priority=?, estimated_hours=?, status=?, completed_at=?, notes=?
               WHERE id = ? AND user_id = ?', [$courseId, $title, $type, $dueAt, $prio, $hours, $status, $completedAt, $notes ?: null, $taskId, $id]);
            flash('success', 'Task updated.');
        } else {
            q('INSERT INTO tasks (user_id, course_id, title, type, due_at, priority, estimated_hours, status, completed_at, notes) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$id, $courseId, $title, $type, $dueAt, $prio, $hours, $status, $status === 'done' ? date('Y-m-d H:i:s') : null, $notes ?: null]);
            flash('success', ucfirst($type) . ' "' . $title . '" added.');
        }
    } elseif ($action === 'delete') {
        $st = q('DELETE FROM tasks WHERE id = ? AND user_id = ?', [$taskId, $id]);
        if (!$st->rowCount()) abort(404);
        flash('success', 'Task deleted.');
    }
    redirect('tasks.php');
}

$courses = qa('SELECT c.id, c.code, c.title, c.color, s.name AS sem FROM courses c JOIN semesters s ON s.id = c.semester_id
               WHERE c.user_id = ? ORDER BY s.is_current DESC, c.code', [$id]);

// ---- filters (validated against allowed values) ----
$fCourse = (int)($_GET['course'] ?? 0);
$fType   = one_of($_GET['type'] ?? '', TASK_TYPES, '');
$fStatus = one_of($_GET['status'] ?? '', array_merge(STATUSES, ['open', 'overdue']), 'open');
$sort    = one_of($_GET['sort'] ?? '', ['due_asc', 'due_desc', 'priority'], 'due_asc');

$where = ['t.user_id = ?'];
$params = [$id];
if ($fCourse) { $where[] = 't.course_id = ?'; $params[] = $fCourse; }
if ($fType)   { $where[] = 't.type = ?'; $params[] = $fType; }
if ($fStatus === 'open')         $where[] = "t.status <> 'done'";
elseif ($fStatus === 'overdue')  $where[] = "t.status <> 'done' AND t.due_at < NOW()";
elseif ($fStatus)              { $where[] = 't.status = ?'; $params[] = $fStatus; }
$order = ['due_asc' => 't.due_at ASC', 'due_desc' => 't.due_at DESC', 'priority' => "FIELD(t.priority,'high','medium','low'), t.due_at"][$sort];

$tasks = qa('SELECT t.*, c.code, c.color FROM tasks t LEFT JOIN courses c ON c.id = t.course_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order, $params);

$edit = null;
if (isset($_GET['edit'])) {
    $edit = q1('SELECT * FROM tasks WHERE id = ? AND user_id = ?', [(int)$_GET['edit'], $id]);
    if (!$edit) abort(404);
}
$openModal = $edit || isset($_GET['new']);
$counts = AnalyticsService::taskCounts($id);

$pageTitle = 'Tasks & Deadlines';
$active = 'tasks';
require __DIR__ . '/../app/views/header.php';
?>
<div class="stack">
  <div class="grid grid-4">
    <a class="card stat" href="?status=open" style="color:inherit;text-decoration:none"><div class="stat-icon tone-warning"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-value"><?= $counts['pending'] ?></div><div class="stat-label">Open</div></div></a>
    <a class="card stat" href="?status=overdue" style="color:inherit;text-decoration:none"><div class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></div><div><div class="stat-value"><?= $counts['overdue'] ?></div><div class="stat-label">Overdue</div></div></a>
    <a class="card stat" href="?status=done" style="color:inherit;text-decoration:none"><div class="stat-icon tone-success"><i class="bi bi-check2-all"></i></div><div><div class="stat-value"><?= $counts['done'] ?></div><div class="stat-label">Completed</div></div></a>
    <div class="card stat"><div class="stat-icon tone-info"><i class="bi bi-stopwatch"></i></div><div><div class="stat-value"><?= $counts['on_time_rate'] !== null ? $counts['on_time_rate'] . '%' : '—' ?></div><div class="stat-label">On-time rate</div></div></div>
  </div>

  <div class="card flush">
    <div class="card-head" style="padding-bottom:1rem">
      <form class="filters" method="get">
        <select class="input input-sm" name="course" onchange="this.form.submit()">
          <option value="">All courses</option>
          <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $fCourse === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?> — <?= e($c['title']) ?></option><?php endforeach; ?>
        </select>
        <select class="input input-sm" name="type" onchange="this.form.submit()">
          <option value="">All types</option>
          <?php foreach (TASK_TYPES as $t): ?><option value="<?= $t ?>" <?= $fType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?>
        </select>
        <select class="input input-sm" name="status" onchange="this.form.submit()">
          <?php foreach (['open' => 'Open (not done)', 'overdue' => 'Overdue', 'pending' => 'Pending', 'in_progress' => 'In progress', 'done' => 'Done'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
        <select class="input input-sm" name="sort" onchange="this.form.submit()">
          <option value="due_asc" <?= $sort === 'due_asc' ? 'selected' : '' ?>>Deadline: soonest</option>
          <option value="due_desc" <?= $sort === 'due_desc' ? 'selected' : '' ?>>Deadline: latest</option>
          <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
        </select>
        <noscript><button class="btn btn-ghost btn-sm">Filter</button></noscript>
      </form>
      <button class="btn btn-primary" data-open-modal="taskModal"><i class="bi bi-plus-lg"></i> New task</button>
    </div>

    <?php if (!$tasks): ?>
      <div class="empty"><i class="bi bi-clipboard-check"></i><h3>No tasks here</h3><p>Try another filter, or add your next assignment, quiz or exam.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th style="width:44px"></th><th>Task</th><th>Course</th><th>Due</th><th>Priority</th><th>Est.</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($tasks as $t):
            $done = $t['status'] === 'done';
            $over = !$done && strtotime($t['due_at']) < time(); ?>
          <tr class="task-row <?= $done ? 'is-done' : '' ?> <?= $over ? 'overdue' : '' ?>" data-task-row="<?= (int)$t['id'] ?>">
            <td><button class="check-toggle <?= $done ? 'checked' : '' ?>" data-task-toggle="<?= (int)$t['id'] ?>" title="Toggle done"><i class="bi bi-check-lg"></i></button></td>
            <td><strong class="task-title"><i class="bi <?= type_icon($t['type']) ?>" style="color:var(--muted)"></i> <?= e($t['title']) ?></strong><br><small><?= e(ucfirst($t['type'])) ?><?= $t['notes'] ? ' · ' . e(mb_strimwidth($t['notes'], 0, 60, '…')) : '' ?></small></td>
            <td><?php if ($t['code']): ?><span class="course-chip" style="--c:<?= e($t['color']) ?>"><?= e($t['code']) ?></span><?php else: ?><small>General</small><?php endif; ?></td>
            <td class="nowrap"><?= e(date('d M, g:i A', strtotime($t['due_at']))) ?><br><small style="<?= $over ? 'color:var(--danger);font-weight:700' : '' ?>"><?= $over ? 'Overdue · ' : '' ?><?= e(relative_time($t['due_at'])) ?></small></td>
            <td><span class="badge badge-<?= e($t['priority']) ?>"><?= e(ucfirst($t['priority'])) ?></span></td>
            <td class="nowrap"><?= num($t['estimated_hours'], 1) ?> h</td>
            <td data-status-label><?= $done ? '<span class="badge badge-success"><i class="bi bi-check2"></i> Done</span>' : ($t['status'] === 'in_progress' ? '<span class="badge badge-primary">In progress</span>' : '<span class="badge badge-muted">Pending</span>') ?></td>
            <td class="actions">
              <a class="btn btn-ghost btn-xs" href="?edit=<?= (int)$t['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
              <form method="post" class="inline-form" data-confirm="Delete this task?"><?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button class="btn btn-soft-danger btn-xs" title="Delete"><i class="bi bi-trash"></i></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<dialog class="modal" id="taskModal" <?= $openModal ? 'data-auto-open' : '' ?>>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="modal-head"><h3><?= $edit ? 'Edit task' : 'New task' ?></h3><button type="button" class="icon-btn" data-close-modal aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body form-grid">
      <div class="field full"><label>Title</label><input class="input" name="title" required maxlength="150" placeholder="e.g. Assignment 2 — ER Diagram" value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="field"><label>Type</label><select class="input" name="type"><?php foreach (TASK_TYPES as $t): ?><option value="<?= $t ?>" <?= ($edit['type'] ?? 'assignment') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Course</label><select class="input" name="course_id"><option value="">General (no course)</option>
        <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($edit['course_id'] ?? $fCourse) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?> — <?= e($c['sem']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Due date & time</label><input class="input" type="datetime-local" name="due_at" required value="<?= e($edit ? date('Y-m-d\TH:i', strtotime($edit['due_at'])) : date('Y-m-d\T23:59', strtotime('+3 days'))) ?>"></div>
      <div class="field"><label>Priority</label><select class="input" name="priority"><?php foreach (PRIORITIES as $p): ?><option value="<?= $p ?>" <?= ($edit['priority'] ?? 'medium') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Estimated hours</label><input class="input" type="number" step="0.5" min="0" max="200" name="estimated_hours" value="<?= e($edit['estimated_hours'] ?? '2') ?>"><span class="hint">Used by the study planner</span></div>
      <div class="field"><label>Status</label><select class="input" name="status"><?php foreach (['pending' => 'Pending', 'in_progress' => 'In progress', 'done' => 'Done'] as $k => $v): ?><option value="<?= $k ?>" <?= ($edit['status'] ?? 'pending') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="field full"><label>Notes</label><textarea class="input" name="notes" placeholder="Chapters, links, requirements…"><?= e($edit['notes'] ?? '') ?></textarea></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close-modal>Cancel</button><button class="btn btn-primary"><i class="bi bi-check2"></i> Save task</button></div>
  </form>
</dialog>
<?php require __DIR__ . '/../app/views/footer.php';
