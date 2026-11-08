<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_login();
$id = (int)$me['id'];

if (is_post()) {
    if (post('action') === 'read_all') q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$id]);
    if (post('action') === 'clear') q('DELETE FROM notifications WHERE user_id = ? AND is_read = 1', [$id]);
    redirect('notifications.php');
}
$list = qa('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100', [$id]);

$pageTitle = 'Notifications';
$active = '';
require __DIR__ . '/../app/views/header.php';
?>
<div class="card flush" style="max-width:820px">
  <div class="card-head" style="padding-bottom:1rem">
    <h3 class="card-title"><i class="bi bi-bell"></i> All notifications</h3>
    <div class="row-gap">
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-ghost btn-sm"><i class="bi bi-check2-all"></i> Mark all read</button></form>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn btn-soft-danger btn-sm"><i class="bi bi-trash"></i> Clear read</button></form>
    </div>
  </div>
  <?php if (!$list): ?><div class="empty"><i class="bi bi-bell-slash"></i><h3>No notifications</h3><p>You're all caught up.</p></div><?php endif; ?>
  <?php foreach ($list as $n): ?>
    <a class="notif <?= $n['is_read'] ? '' : 'unread' ?>" style="padding:.9rem 1.35rem" href="<?= e(url($n['link'] ?: 'notifications.php')) ?>" data-notif-id="<?= (int)$n['id'] ?>">
      <span><?= e($n['message']) ?></span><small><?= e(fmt_datetime($n['created_at'])) ?> · <?= e(relative_time($n['created_at'])) ?></small>
    </a>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../app/views/footer.php';
