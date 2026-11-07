<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_login();
$id = (int)$me['id'];

if (is_post()) {
    $action = post('action');
    if ($action === 'profile') {
        $name = post('full_name');
        $code = strtoupper(post('student_code'));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) flash('danger', 'Name must be 3–100 characters.');
        elseif ($me['role'] === 'student' && !preg_match('/^[A-Z0-9\-]{4,30}$/', $code)) flash('danger', 'Invalid roll number.');
        elseif ($me['role'] === 'student' && qv('SELECT 1 FROM users WHERE student_code = ? AND id <> ?', [$code, $id])) flash('danger', 'That roll number is used by another account.');
        else {
            q('UPDATE users SET full_name = ?, student_code = ? WHERE id = ?', [$name, $me['role'] === 'student' ? $code : null, $id]);
            flash('success', 'Profile updated.');
        }
    } elseif ($action === 'password') {
        $row = q1('SELECT password_hash FROM users WHERE id = ?', [$id]);
        $new = (string)($_POST['password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), $row['password_hash'])) flash('danger', 'Your current password is incorrect.');
        elseif ($p = password_problem($new)) flash('danger', $p);
        elseif ($new !== ($_POST['password_confirm'] ?? '')) flash('danger', 'New passwords do not match.');
        else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
            session_regenerate_id(true);
            audit('Changed password');
            flash('success', 'Password changed successfully.');
        }
    }
    redirect('profile.php');
}

$stats = $me['role'] === 'student' ? [
    'Semesters' => qv('SELECT COUNT(*) FROM semesters WHERE user_id = ?', [$id]),
    'Courses'   => qv('SELECT COUNT(*) FROM courses WHERE user_id = ?', [$id]),
    'Tasks'     => qv('SELECT COUNT(*) FROM tasks WHERE user_id = ?', [$id]),
    'Groups'    => qv('SELECT COUNT(*) FROM group_members WHERE user_id = ?', [$id]),
] : [];

$pageTitle = 'Profile';
$active = 'profile';
require __DIR__ . '/../app/views/header.php';
?>
<div class="grid grid-side">
  <div class="stack">
    <div class="card">
      <h3 class="card-title"><i class="bi bi-person"></i> Personal information</h3>
      <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="field"><label>Full name</label><input class="input" name="full_name" value="<?= e($me['full_name']) ?>" required maxlength="100"></div>
        <div class="field"><label>Email <small>(cannot be changed)</small></label><input class="input" value="<?= e($me['email']) ?>" disabled></div>
        <?php if ($me['role'] === 'student'): ?>
        <div class="field"><label>Roll number</label><input class="input" name="student_code" value="<?= e($me['student_code']) ?>" required maxlength="30"></div>
        <?php endif; ?>
        <div class="field full"><button class="btn btn-primary"><i class="bi bi-check2"></i> Save profile</button></div>
      </form>
    </div>
    <div class="card">
      <h3 class="card-title"><i class="bi bi-shield-lock"></i> Change password</h3>
      <form method="post" class="form-grid" data-validate novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="field full"><label>Current password</label><input class="input" type="password" name="current_password" required autocomplete="current-password"></div>
        <div class="field"><label>New password</label><input class="input" type="password" name="password" required data-pw-meter autocomplete="new-password"><div class="pw-meter"><span></span></div></div>
        <div class="field"><label>Confirm new password</label><input class="input" type="password" name="password_confirm" required autocomplete="new-password"></div>
        <div class="field full"><button class="btn btn-primary"><i class="bi bi-key"></i> Update password</button></div>
      </form>
    </div>
  </div>
  <div class="card text-center">
    <div class="avatar" style="width:84px;height:84px;font-size:1.7rem;margin:0 auto 1rem"><?= e(initials($me['full_name'])) ?></div>
    <h2 class="mb-0"><?= e($me['full_name']) ?></h2>
    <p class="muted"><?= e($me['email']) ?></p>
    <span class="badge badge-primary"><?= e($me['role'] === 'admin' ? 'Administrator' : 'Student · ' . $me['student_code']) ?></span>
    <hr>
    <dl class="kv" style="text-align:left">
      <dt>Member since</dt><dd><?= e(fmt_date($me['created_at'], 'd M Y')) ?></dd>
      <dt>Last login</dt><dd><?= e(fmt_datetime($me['last_login'])) ?></dd>
      <?php foreach ($stats as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= (int)$v ?></dd><?php endforeach; ?>
    </dl>
  </div>
</div>
<?php require __DIR__ . '/../app/views/footer.php';
