<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_student();
$id = (int)$me['id'];

if (is_post() && post('action') === 'create') {
    $name = post('name');
    $desc = post('description');
    $deadline = post('deadline') ?: null;
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        flash('danger', 'Group name must be 2–100 characters.');
        redirect('groups.php');
    }
    db()->beginTransaction();
    q('INSERT INTO project_groups (name, description, owner_id, deadline) VALUES (?, ?, ?, ?)', [$name, $desc ?: null, $id, $deadline]);
    $gid = (int)db()->lastInsertId();
    q("INSERT INTO group_members (group_id, user_id, member_role) VALUES (?, ?, 'leader')", [$gid, $id]);
    db()->commit();
    flash('success', 'Group "' . $name . '" created. Now invite your teammates by email.');
    redirect('group_view.php?id=' . $gid);
}

$groups = qa("SELECT g.*, gm.member_role,
                (SELECT COUNT(*) FROM group_members x WHERE x.group_id = g.id) AS members,
                (SELECT COUNT(*) FROM project_tasks t WHERE t.group_id = g.id) AS total,
                (SELECT COUNT(*) FROM project_tasks t WHERE t.group_id = g.id AND t.status = 'done') AS done,
                (SELECT COUNT(*) FROM project_tasks t WHERE t.group_id = g.id AND t.assigned_to = ? AND t.status <> 'done') AS mine
              FROM project_groups g JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ?
              ORDER BY g.deadline IS NULL, g.deadline, g.created_at DESC", [$id, $id]);

$pageTitle = 'Group Projects';
$active = 'groups';
require __DIR__ . '/../app/views/header.php';
?>
<div class="stack">
  <div class="space-between">
    <p class="muted mb-0">Collaborate with classmates: invite members by their UniSync email, assign tasks and track progress on a Kanban board.</p>
    <button class="btn btn-primary" data-open-modal="groupModal"><i class="bi bi-plus-lg"></i> New group project</button>
  </div>

  <?php if (!$groups): ?>
    <div class="card empty"><i class="bi bi-people"></i><h3>No group projects yet</h3><p>Create a group and invite your teammates to start collaborating.</p>
      <button class="btn btn-primary" data-open-modal="groupModal"><i class="bi bi-plus-lg"></i> Create group</button></div>
  <?php endif; ?>

  <div class="grid grid-3">
    <?php foreach ($groups as $g): $pct = $g['total'] ? round($g['done'] / $g['total'] * 100) : 0; ?>
      <a class="card" href="<?= e(url('group_view.php?id=' . $g['id'])) ?>" style="color:inherit;text-decoration:none">
        <div class="space-between"><span class="badge <?= $g['member_role'] === 'leader' ? 'badge-primary' : 'badge-muted' ?>"><i class="bi <?= $g['member_role'] === 'leader' ? 'bi-star-fill' : 'bi-person' ?>"></i> <?= e(ucfirst($g['member_role'])) ?></span>
          <?php if ($g['deadline']): ?><small class="<?= $g['deadline'] < date('Y-m-d') && $pct < 100 ? '' : '' ?>"><i class="bi bi-flag"></i> <?= e(fmt_date($g['deadline'], 'd M Y')) ?></small><?php endif; ?></div>
        <h3 class="mt-2"><?= e($g['name']) ?></h3>
        <p class="muted" style="font-size:.88rem;min-height:2.6em"><?= e(mb_strimwidth($g['description'] ?: 'No description', 0, 110, '…')) ?></p>
        <div class="space-between mb-0" style="font-size:.82rem"><span><i class="bi bi-people"></i> <?= (int)$g['members'] ?> members</span><span><?= (int)$g['done'] ?>/<?= (int)$g['total'] ?> tasks · <strong><?= $pct ?>%</strong></span></div>
        <div class="progress <?= $pct == 100 ? 'success' : '' ?> mt-1"><span style="width:<?= $pct ?>%"></span></div>
        <?php if ($g['mine']): ?><small class="mt-1" style="display:block;color:var(--accent);font-weight:600"><?= (int)$g['mine'] ?> task(s) assigned to you</small><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<dialog class="modal" id="groupModal">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="modal-head"><h3>New group project</h3><button type="button" class="icon-btn" data-close-modal><i class="bi bi-x-lg"></i></button></div>
    <div class="modal-body stack">
      <div class="field"><label>Project name</label><input class="input" name="name" required maxlength="100" placeholder="e.g. Web Engineering Semester Project"></div>
      <div class="field"><label>Description</label><textarea class="input" name="description" placeholder="What is this project about?"></textarea></div>
      <div class="field"><label>Deadline</label><input class="input" type="date" name="deadline"></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close-modal>Cancel</button><button class="btn btn-primary"><i class="bi bi-check2"></i> Create group</button></div>
  </form>
</dialog>
<?php require __DIR__ . '/../app/views/footer.php';
