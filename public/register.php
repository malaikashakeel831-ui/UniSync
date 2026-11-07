<?php
require __DIR__ . '/../app/bootstrap.php';
if (current_user()) redirect('dashboard.php');

$errors = [];
if (is_post()) {
    $name  = post('full_name');
    $email = strtolower(post('email'));
    $code  = strtoupper(post('student_code'));
    $pass  = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password_confirm'] ?? '');

    // ---- server-side validation (never rely on JavaScript only) ----
    if (mb_strlen($name) < 3 || mb_strlen($name) > 100) $errors['full_name'] = 'Enter your full name (3–100 characters).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors['email'] = 'Enter a valid email address.';
    if (!preg_match('/^[A-Z0-9\-]{4,30}$/', $code)) $errors['student_code'] = 'Roll number should be 4–30 letters/numbers (e.g. BCSF24A002).';
    if ($p = password_problem($pass)) $errors['password'] = $p;
    if ($pass !== $pass2) $errors['password_confirm'] = 'Passwords do not match.';

    if (!isset($errors['email']) && qv('SELECT 1 FROM users WHERE email = ?', [$email])) $errors['email'] = 'This email is already registered.';
    if (!isset($errors['student_code']) && qv('SELECT 1 FROM users WHERE student_code = ?', [$code])) $errors['student_code'] = 'This roll number is already registered.';

    if (!$errors) {
        // role is ALWAYS 'student' here — any "role" field sent by the browser is ignored
        q('INSERT INTO users (full_name, email, student_code, password_hash, role) VALUES (?, ?, ?, ?, \'student\')',
            [$name, $email, $code, password_hash($pass, PASSWORD_DEFAULT)]);
        $newId = (int)db()->lastInsertId();
        audit('Registered new account', $newId);
        NotificationService::add($newId, '👋 Welcome to UniSync! Start by adding your current semester.', 'semesters.php', false);
        clear_old();
        attempt_login($email, $pass);
        flash('success', 'Welcome to UniSync, ' . $name . '! Your account is ready.');
        redirect('dashboard.php');
    }
    keep_old($_POST);
}
$pageTitle = 'Create account';
require __DIR__ . '/../app/views/guest_header.php';
?>
<div class="auth">
  <?php require __DIR__ . '/../app/views/auth_art.php'; ?>
  <main class="auth-form">
    <div class="auth-box">
      <h1>Create your account</h1>
      <p class="sub">It takes less than a minute. Your data stays private to you.</p>
      <?php if ($errors): ?><div class="alert alert-danger"><i class="bi bi-x-octagon"></i><span>Please fix the highlighted fields.</span></div><?php endif; ?>
      <form method="post" data-validate novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="full_name">Full name</label>
          <div class="input-icon"><i class="bi bi-person"></i><input class="input <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" id="full_name" name="full_name" value="<?= e(old('full_name')) ?>" required maxlength="100" placeholder="e.g. Syeda Laraib"></div>
          <?php if (isset($errors['full_name'])): ?><div class="field-error"><?= e($errors['full_name']) ?></div><?php endif; ?>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <div class="input-icon"><i class="bi bi-envelope"></i><input class="input <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required maxlength="150" placeholder="you@university.edu.pk"></div>
          <?php if (isset($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
        </div>
        <div class="field">
          <label for="student_code">Roll number / Student ID</label>
          <div class="input-icon"><i class="bi bi-person-badge"></i><input class="input <?= isset($errors['student_code']) ? 'is-invalid' : '' ?>" id="student_code" name="student_code" value="<?= e(old('student_code')) ?>" required maxlength="30" placeholder="BCSF24A002"></div>
          <?php if (isset($errors['student_code'])): ?><div class="field-error"><?= e($errors['student_code']) ?></div><?php endif; ?>
        </div>
        <div class="form-grid">
          <div class="field">
            <label for="password">Password</label>
            <input class="input <?= isset($errors['password']) ? 'is-invalid' : '' ?>" type="password" id="password" name="password" required data-pw-meter autocomplete="new-password">
            <div class="pw-meter"><span></span></div>
            <?php if (isset($errors['password'])): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
          </div>
          <div class="field">
            <label for="password_confirm">Confirm password</label>
            <input class="input <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
            <?php if (isset($errors['password_confirm'])): ?><div class="field-error"><?= e($errors['password_confirm']) ?></div><?php endif; ?>
          </div>
        </div>
        <p class="hint">At least 8 characters, with letters and numbers.</p>
        <button class="btn btn-primary btn-block btn-lg" type="submit"><i class="bi bi-person-plus"></i> Create account</button>
      </form>
      <p class="auth-switch">Already have an account? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
    </div>
  </main>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
<?php clear_old(); ?>
