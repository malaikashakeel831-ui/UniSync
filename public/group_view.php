<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_student();
$id = (int)$me['id'];
$gid = (int)($_GET['id'] ?? 0);
$group = require_group_member($gid);          // 403 for non-members
$isLeader = $group['member_role'] === 'leader';
$back = 'group_view.php?id=' . $gid;

if (is_post()) {
    $action = post('action');

    if ($action === 'add_member') {
        if (!$isLeader) abort(403, 'Only the group leader can add members.');
        $email = strtolower(post('email'));
        $u = q1("SELECT id, full_name, role, is_active FROM users WHERE email = ?", [$email]);
        if (!$u || $u['role'] !== 'student' || !$u['is_active']) flash('danger', 'No active student is registered with that email.');
        elseif (qv('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?', [$gid, $u['id']])) flash('warning', $u['full_name'] . ' is already a member.');
        else {
            q("INSERT INTO group_members (group_id, user_id, member_role) VALUES (?, ?, 'member')", [$gid, $u['id']]);
            NotificationService::add((int)$u['id'], '👥 ' . $me['full_name'] . ' added you to the group "' . $group['name'] . '".', $back, false);
            flash('success', $u['full_name'] . ' was added to the group.');
        }
    } elseif ($action === 'remove_member') {
        $uid = (int)post('user_id');
        if (!$isLeader && $uid !== $id) abort(403);
        if ($uid === (int)$group['owner_id']) { flash('danger', 'The group owner cannot be removed.'); redirect($back); }
        q('DELETE FROM group_members WHERE group_id = ? AND user_id = ?', [$gid, $uid]);
        q('UPDATE project_tasks SET assigned_to = NULL WHERE group_id = ? AND assigned_to = ?', [$gid, $uid]);
        if ($uid === $id) { flash('success', 'You left the group.'); redirect('groups.php'); }
        flash('success', 'Member removed.');
    } elseif ($action === 'add_task' || $action === 'edit_task') {
        $title = post('title');
        $assign = (int)post('assigned_to') ?: null;
        $due = post('due_date') ?: null;
        $status = one_of(post('status', 'todo'), ['todo', 'doing', 'done'], 'todo');
        if ($assign && !qv('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?', [$gid, $assign])) { flash('danger', 'You can only assign tasks to group members.'); redirect($back); }
        if (mb_strlen($title) < 2 || mb_strlen($title) > 150) { flash('danger', 'Task title must be 2–150 characters.'); redirect($back); }
        if ($action === 'add_task') {
            q('INSERT INTO project_tasks (group_id, title, assigned_to, due_date, status, created_by) VALUES (?,?,?,?,?,?)', [$gid, $title, $assign, $due, $status, $id]);
            flash('success', 'Task added to the board.');
        } else {
            $t = q1('SELECT * FROM project_tasks WHERE id = ? AND group_id = ?', [(int)post('id'), $gid]);
            if (!$t) abort(404);
            q('UPDATE project_tasks SET title = ?, assigned_to = ?, due_date = ?, status = ? WHERE id = ?', [$title, $assign, $due, $status, $t['id']]);
            flash('success', 'Task updated.');
        }
        if ($assign && $assign !== $id) {
            NotificationService::add($assign, '📌 New group task for you: "' . $title . '" in ' . $group['name'] . ($due ? ' (due ' . date('d M', strtotime($due)) . ')' : '') . '.', $back, false);
        }
    } elseif ($action === 'move') {
        $status = one_of(post('status'), ['todo', 'doing', 'done'], 'todo');
        q('UPDATE project_tasks SET status = ? WHERE id = ? AND group_id = ?', [$status, (int)post('id'), $gid]);
    } elseif ($action === 'delete_task') {
        $t = q1('SELECT * FROM project_tasks WHERE id = ? AND group_id = ?', [(int)post('id'), $gid]);
        if (!$t) abort(404);
        if (!$isLeader && (int)$t['created_by'] !== $id) abort(403, 'Only the leader or the creator can delete this task.');
        q('DELETE FROM project_tasks WHERE id = ?', [$t['id']]);
        flash('success', 'Task deleted.');
    } elseif ($action === 'delete_group') {
        if ((int)$group['owner_id'] !== $id) abort(403, 'Only the owner can delete the group.');
        q('DELETE FROM project_groups WHERE id = ?', [$gid]);
        flash('success', 'Group deleted.');
        redirect('groups.php');
    } elseif ($action === 'update_group') {
        if (!$isLeader) abort(403);
        $name = post('name');
        if (mb_strlen($name) >= 2 && mb_strlen($name) <= 100) {
            q('UPDATE project_groups SET name = ?, description = ?, deadline = ? WHERE id = ?', [$name, post('description') ?: null, post('deadline') ?: null, $gid]);
            flash('success', 'Group details updated.');
        }
    }
    redirect($back);
}

$members = qa('SELECT u.id, u.full_name, u.email, u.student_code, gm.member_role,
                 (SELECT COUNT(*) FROM project_tasks t WHERE t.group_id = gm.group_id AND t.assigned_to = u.id) AS assigned,
                 (SELECT COUNT(*) FROM project_tasks t WHERE t.group_id = gm.group_id AND t.assigned_to = u.id AND t.status = \'done\') AS done
               FROM group_members gm JOIN users u ON u.id = gm.user_id WHERE gm.group_id = ? ORDER BY gm.member_role = \'leader\' DESC, u.full_name', [$gid]);
$tasks = qa('SELECT t.*, u.full_name AS assignee FROM project_tasks t LEFT JOIN users u ON u.id = t.assigned_to WHERE t.group_id = ? ORDER BY t.due_date IS NULL, t.due_date, t.id', [$gid]);
$cols = ['todo' => ['To Do', 'bi-circle', '#94a3b8'], 'doing' => ['Doing', 'bi-arrow-repeat', '#f59e0b'], 'done' => ['Done', 'bi-check-circle', '#10b981']];
$byStatus = ['todo' => [], 'doing' => [], 'done' => []];
foreach ($tasks as $t) $byStatus[$t['status']][] = $t;
$pct = $tasks ? (int)round(count($byStatus['done']) / count($tasks) * 100) : 0;
$order = array_keys($cols);

$editTask = null;
if (isset($_GET['edit_task'])) {
    $editTask = q1('SELECT * FROM project_tasks WHERE id = ? AND group_id = ?', [(int)$_GET['edit_task'], $gid]);
    if (!$editTask) abort(404);
}

$pageTitle = $group['name'];
$active = 'groups';
require __DIR__ . '/../app/views/header.php';
?>
<div class="stack">
  <div class="card">
    <div class="space-between">
      <div style="min-width:0;flex:1">
        <a href="<?= e(url('groups.php')) ?>" class="muted" style="font-size:.85rem"><i class="bi bi-arrow-left"></i> All groups</a>
        <h2 class="mt-1"><?= e($group['name']) ?></h2>
        <p class="muted mb-0"><?= nl2br(e($group['description'] ?: 'No description')) ?></p>
      </div>
      <div class="text-right">
        <?php if ($group['deadline']): ?><div><small>Deadline</small><br><strong><?= e(fmt_date($group['deadline'])) ?></strong><br><small><?= e(relative_time($group['deadline'] . ' 23:59:59')) ?></small></div><?php endif; ?>
      </div>
    </div>
    <div class="mt-2">
      <div class="space-between" style="font-size:.85rem"><span>Project progress</span><strong data-progress-label><?= $pct ?>%</strong></div>
      <div class="progress mt-1" style="height:12px"><span data-progress-bar style="width:<?= $pct ?>%"></span></div>
    </div>
  </div>

  <div class="space-between">
    <h2 class="mb-0">Kanban board</h2>
    <div class="row-gap"><small class="muted"><i class="bi bi-arrows-move"></i> Drag cards between columns</small><button class="btn btn-primary" data-open-modal="taskModal"><i class="bi bi-plus-lg"></i> Add task</button></div>
  </div>
  <div class="kanban" data-kanban>
    <?php foreach ($cols as $key => [$label, $icon, $color]): $i = array_search($key, $order); ?>
      <div class="kcol" data-status="<?= $key ?>">
        <div class="kcol-head"><span><i class="bi <?= $icon ?>" style="color:<?= $color ?>"></i> <?= $label ?></span><span class="count"><?= count($byStatus[$key]) ?></span></div>
        <div class="kcol-body">
          <?php foreach ($byStatus[$key] as $t): $late = $t['due_date'] && $t['due_date'] < date('Y-m-d') && $key !== 'done'; ?>
            <div class="kcard" draggable="true" data-id="<?= (int)$t['id'] ?>">
              <div class="kcard-title"><?= e($t['title']) ?></div>
              <div class="kcard-meta">
                <span class="member"><?php if ($t['assignee']): ?><span class="avatar xs"><?= e(initials($t['assignee'])) ?></span> <?= e(explode(' ', $t['assignee'])[0]) ?><?php else: ?><i class="bi bi-person-dash"></i> Unassigned<?php endif; ?></span>
                <?php if ($t['due_date']): ?><span style="<?= $late ? 'color:var(--danger);font-weight:700' : '' ?>"><i class="bi bi-calendar-event"></i> <?= e(fmt_date($t['due_date'], 'd M')) ?></span><?php endif; ?>
              </div>
              <div class="kcard-actions">
                <?php if ($i > 0): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="status" value="<?= $order[$i - 1] ?>"><button class="btn btn-ghost btn-xs" title="Move left"><i class="bi bi-chevron-left"></i></button></form><?php endif; ?>
                <?php if ($i < 2): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="status" value="<?= $order[$i + 1] ?>"><button class="btn btn-ghost btn-xs" title="Move right"><i class="bi bi-chevron-right"></i></button></form><?php endif; ?>
                <a class="btn btn-ghost btn-xs" href="?id=<?= $gid ?>&edit_task=<?= (int)$t['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                <?php if ($isLeader || (int)$t['created_by'] === $id): ?>
                <form method="post" class="inline-form" data-confirm="Delete this task?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_task"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn btn-soft-danger btn-xs" title="Delete"><i class="bi bi-trash"></i></button></form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-2">
    <div class="card">
      <div class="card-head"><h3 class="card-title"><i class="bi bi-people"></i> Members (<?= count($members) ?>)</h3></div>
      <ul class="list-plain">
        <?php foreach ($members as $m): ?>
          <li><span class="member"><span class="avatar sm"><?= e(initials($m['full_name'])) ?></span>
              <span><strong><?= e($m['full_name']) ?></strong> <?= $m['member_role'] === 'leader' ? '<span class="badge badge-primary">Leader</span>' : '' ?><br><small><?= e($m['email']) ?> · <?= (int)$m['done'] ?>/<?= (int)$m['assigned'] ?> tasks done</small></span></span>
            <?php if ((int)$m['id'] !== (int)$group['owner_id'] && ($isLeader || (int)$m['id'] === $id)): ?>
              <form method="post" class="inline-form" data-confirm="<?= (int)$m['id'] === $id ? 'Leave this group?' : 'Remove this member?' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="remove_member"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                <button class="btn btn-soft-danger btn-xs"><?= (int)$m['id'] === $id ? 'Leave' : 'Remove' ?></button></form>
            <?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($isLeader): ?>
      <form method="post" class="row-gap mt-2" style="flex-wrap:nowrap"><?= csrf_field() ?><input type="hidden" name="action" value="add_member">
        <input class="input" type="email" name="email" required placeholder="Teammate's registered email">
        <button class="btn btn-primary"><i class="bi bi-person-plus"></i> Add</button></form>
      <?php endif; ?>
    </div>

    <?php if ($isLeader): ?>
    <div class="card">
      <h3 class="card-title"><i class="bi bi-gear"></i> Group settings</h3>
      <form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="update_group">
        <div class="field full"><label>Name</label><input class="input" name="name" value="<?= e($group['name']) ?>" maxlength="100" required></div>
        <div class="field full"><label>Description</label><textarea class="input" name="description"><?= e($group['description']) ?></textarea></div>
        <div class="field"><label>Deadline</label><input class="input" type="date" name="deadline" value="<?= e($group['deadline']) ?>"></div>
        <div class="field" style="justify-content:flex-end"><button class="btn btn-primary">Save</button></div>
      </form>
      <?php if ((int)$group['owner_id'] === $id): ?>
      <form method="post" class="mt-2" data-confirm="Delete this group, all members and tasks? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete_group">
        <button class="btn btn-soft-danger btn-sm"><i class="bi bi-trash"></i> Delete group</button></form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<dialog class="modal" id="taskModal" <?= $editTask ? 'data-auto-open' : '' ?>>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="<?= $editTask ? 'edit_task' : 'add_task' ?>"><input type="hidden" name="id" value="<?= (int)($editTask['id'] ?? 0) ?>">
    <div class="modal-head"><h3><?= $editTask ? 'Edit task' : 'Add task' ?></h3><button type="button" class="icon-btn" data-close-modal><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body form-grid">
      <div class="field full"><label>Title</label><input class="input" name="title" required maxlength="150" value="<?= e($editTask['title'] ?? '') ?>" placeholder="e.g. Design database ER diagram"></div>
      <div class="field"><label>Assign to</label><select class="input" name="assigned_to"><option value="">Unassigned</option>
        <?php foreach ($members as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)($editTask['assigned_to'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Due date</label><input class="input" type="date" name="due_date" value="<?= e($editTask['due_date'] ?? '') ?>"></div>
      <div class="field full"><label>Column</label><select class="input" name="status"><?php foreach ($cols as $k => [$l]): ?><option value="<?= $k ?>" <?= ($editTask['status'] ?? 'todo') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close-modal>Cancel</button><button class="btn btn-primary"><i class="bi bi-check2"></i> Save</button></div>
  </form>
</dialog>
<?php require __DIR__ . '/../app/views/footer.php';
