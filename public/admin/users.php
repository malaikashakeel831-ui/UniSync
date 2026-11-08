<?php
require __DIR__ . '/../../app/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    $uid = (int)post('id');
    $u = q1('SELECT id, full_name, role, is_active FROM users WHERE id = ?', [$uid]);
    if (!$u) abort(404);
    if ($u['role'] === 'admin') abort(403, 'Administrator accounts cannot be changed here.');
    $action = post('action');
    if ($action === 'toggle') {
        $new = $u['is_active'] ? 0 : 1;
        q('UPDATE users SET is_active = ? WHERE id = ?', [$new, $uid]);
        audit(($new ? 'Activated' : 'Deactivated') . ' account #' . $uid);
        flash('success', $u['full_name'] . ' has been ' . ($new ? 'activated.' : 'deactivated.'));
    } elseif ($action === 'unlock') {
        q('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?', [$uid]);
        audit('Unlocked account #' . $uid);
        flash('success', $u['full_name'] . ' has been unlocked.');
    }
    redirect('admin/users.php' . (!empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
}

$search = trim((string)($_GET['q'] ?? ''));
$filter = one_of($_GET['f'] ?? '', ['active', 'inactive', 'locked'], '');
$where = ["role = 'student'"];
$params = [];
if ($search !== '') { $where[] = '(full_name LIKE ? OR email LIKE ? OR student_code LIKE ?)'; $like = '%' . $search . '%'; array_push($params, $like, $like, $like); }
if ($filter === 'active') $where[] = 'is_active = 1';
if ($filter === 'inactive') $where[] = 'is_active = 0';
if ($filter === 'locked') $where[] = 'locked_until > NOW()';
$users = qa('SELECT id, full_name, email, student_code, is_active, locked_until, created_at, last_login FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 200', $params);

$pageTitle = 'Manage Users';
$active = 'admin_users';
require __DIR__ . '/../../app/views/header.php';
?>
<div class="card flush">
  <div class="card-head" style="padding-bottom:1rem">
    <form class="filters" method="get">
      <div class="input-icon"><i class="bi bi-search"></i><input class="input input-sm" name="q" value="<?= e($search) ?>" placeholder="Search name, email, roll no." style="min-width:260px"></div>
      <select class="input input-sm" name="f" onchange="this.form.submit()">
        <option value="">All students</option><option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>Deactivated</option><option value="locked" <?= $filter === 'locked' ? 'selected' : '' ?>>Locked</option>
      </select>
      <button class="btn btn-ghost btn-sm">Search</button>
    </form>
    <small><?= count($users) ?> student(s)</small>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Student</th><th>Roll no.</th><th>Joined</th><th>Last login</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $locked = $u['locked_until'] && strtotime($u['locked_until']) > time(); ?>
        <tr>
          <td><span class="member"><span class="avatar sm"><?= e(initials($u['full_name'])) ?></span><span><strong><?= e($u['full_name']) ?></strong><br><small><?= e($u['email']) ?></small></span></span></td>
          <td><?= e($u['student_code']) ?></td>
          <td class="nowrap"><small><?= e(fmt_date($u['created_at'], 'd M Y')) ?></small></td>
          <td class="nowrap"><small><?= $u['last_login'] ? e(relative_time($u['last_login'])) : 'Never' ?></small></td>
          <td><?= $u['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-high">Deactivated</span>' ?> <?= $locked ? '<span class="badge badge-medium"><i class="bi bi-lock"></i> Locked</span>' : '' ?></td>
          <td class="actions">
            <?php if ($locked): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="unlock"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn btn-ghost btn-xs"><i class="bi bi-unlock"></i> Unlock</button></form><?php endif; ?>
            <form method="post" class="inline-form" data-confirm="<?= $u['is_active'] ? 'Deactivate this account? The student will be logged out and cannot log in.' : 'Re-activate this account?' ?>"><?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <?php if ($u['is_active']): ?><button class="btn btn-soft-danger btn-xs"><i class="bi bi-person-slash"></i> Deactivate</button>
              <?php else: ?><button class="btn btn-success btn-xs"><i class="bi bi-person-check"></i> Activate</button><?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="6"><div class="empty"><i class="bi bi-search"></i><p>No students match.</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../../app/views/footer.php';
