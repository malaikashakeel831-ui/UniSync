<?php
/** Simple standalone error page (403 / 404 / 419). Variables: $code, $title, $message */
$pageTitle = $title;
require __DIR__ . '/guest_header.php';
?>
<main class="error-page">
  <div class="error-card">
    <div class="error-code"><?= (int)$code ?></div>
    <h1><?= e($title) ?></h1>
    <p><?= e($message) ?></p>
    <div class="row-gap">
      <a class="btn btn-primary" href="<?= e(url(!empty($_SESSION['user_id']) ? 'dashboard.php' : 'index.php')) ?>"><i class="bi bi-house"></i> Go home</a>
      <a class="btn btn-ghost" href="javascript:history.back()"><i class="bi bi-arrow-left"></i> Go back</a>
    </div>
  </div>
</main>
</body>
</html>
