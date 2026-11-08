<?php
require __DIR__ . '/../app/bootstrap.php';
if ($u = current_user()) redirect($u['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php');

$error = null;
if (is_post()) {
    $email = strtolower(post('email'));
    $error = attempt_login($email, (string)($_POST['password'] ?? ''));
    if ($error === null) {
        $u = current_user();
        if ($u['role'] === 'admin') redirect('admin/dashboard.php');
        NotificationService::generate((int)$u['id']);
        flash('success', 'Welcome back, ' . explode(' ', $u['full_name'])[0] . '!');
        redirect('dashboard.php');
    }
    keep_old(['email' => $email]);
}
$pageTitle = 'Log in';
require __DIR__ . '/../app/views/guest_header.php';
?>
<div class="auth">
  <?php require __DIR__ . '/../app/views/auth_art.php'; ?>
  <main class="auth-form">
    <div class="auth-box">
      <h1>Welcome back 👋</h1>
      <p class="sub">Log in to see what's due and what to study next.</p>
      <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><i class="bi bi-info-circle"></i><span><?= e($f['message']) ?></span></div>
      <?php endforeach; ?>
      <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-x-octagon"></i><span><?= e($error) ?></span></div><?php endif; ?>
      <form method="post" data-validate novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">Email</label>
          <div class="input-icon"><i class="bi bi-envelope"></i><input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="email"></div>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <div class="input-icon"><i class="bi bi-lock"></i><input class="input" type="password" id="password" name="password" required autocomplete="current-password"></div>
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit"><i class="bi bi-box-arrow-in-right"></i> Log in</button>
      </form>
      <p class="auth-switch">New to UniSync? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
    </div>
  </main>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
<?php clear_old(); ?>
