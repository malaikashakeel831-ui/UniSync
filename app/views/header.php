<?php
/**
 * Shared layout header (sidebar + top bar).
 * Before including set: $pageTitle (string) and $active (menu key).
 */
$me = current_user();
$pageTitle = $pageTitle ?? 'UniSync';
$active = $active ?? '';
$unread = $me ? NotificationService::unreadCount((int)$me['id']) : 0;
$notes  = $me ? NotificationService::latest((int)$me['id'], 6) : [];

$studentNav = [
    'Overview' => [
        'dashboard' => ['dashboard.php', 'bi-grid-1x2', 'Dashboard'],
        'analytics' => ['analytics.php', 'bi-graph-up-arrow', 'Analytics'],
    ],
    'Academics' => [
        'semesters' => ['semesters.php', 'bi-calendar-range', 'Semesters'],
        'courses'   => ['courses.php', 'bi-book', 'Courses'],
        'tasks'     => ['tasks.php', 'bi-check2-square', 'Tasks & Deadlines'],
        'timetable' => ['timetable.php', 'bi-calendar3-week', 'Timetable'],
        'gpa'       => ['gpa.php', 'bi-award', 'GPA Planner'],
    ],
    'Productivity' => [
        'planner' => ['planner.php', 'bi-lightning-charge', 'Study Planner'],
        'groups'  => ['groups.php', 'bi-people', 'Group Projects'],
    ],
];
$adminNav = [
    'Administration' => [
        'admin_dashboard' => ['admin/dashboard.php', 'bi-speedometer2', 'Admin Dashboard'],
        'admin_users'     => ['admin/users.php', 'bi-person-gear', 'Manage Users'],
    ],
];
$nav = ($me && $me['role'] === 'admin') ? $adminNav : $studentNav;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="base-url" content="<?= e(base_url()) ?>">
  <title><?= e($pageTitle) ?> · UniSync</title>
  <link rel="icon" href="<?= e(asset('img/logo.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset('vendor/fonts/fonts.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script>
    // apply saved theme before paint (no flash)
    try { var t = localStorage.getItem('unisync-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
  </script>
</head>
<body class="app">
<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= e(url($me && $me['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php')) ?>">
    <img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="34" height="34">
    <span>Uni<b>Sync</b></span>
  </a>
  <nav class="nav">
    <?php foreach ($nav as $section => $items): ?>
      <div class="nav-section"><?= e($section) ?></div>
      <?php foreach ($items as $key => [$href, $icon, $label]): ?>
        <a href="<?= e(url($href)) ?>" class="nav-link <?= $active === $key ? 'active' : '' ?>">
          <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <div class="nav-section">Account</div>
    <a href="<?= e(url('profile.php')) ?>" class="nav-link <?= $active === 'profile' ? 'active' : '' ?>"><i class="bi bi-person-circle"></i><span>Profile</span></a>
    <form method="post" action="<?= e(url('logout.php')) ?>">
      <?= csrf_field() ?>
      <button class="nav-link as-button" type="submit"><i class="bi bi-box-arrow-left"></i><span>Log out</span></button>
    </form>
  </nav>
  <div class="sidebar-foot">
    <div class="avatar"><?= e(initials($me['full_name'] ?? '?')) ?></div>
    <div class="who">
      <strong><?= e($me['full_name'] ?? '') ?></strong>
      <small><?= e($me['role'] === 'admin' ? 'Administrator' : ($me['student_code'] ?: 'Student')) ?></small>
    </div>
  </div>
</aside>
<div class="sidebar-backdrop" data-close-sidebar></div>

<div class="main">
  <header class="topbar">
    <button class="icon-btn hamburger" data-toggle-sidebar aria-label="Open menu"><i class="bi bi-list"></i></button>
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
    <div class="topbar-actions">
      <button class="icon-btn" data-toggle-theme aria-label="Toggle dark mode" title="Toggle dark mode">
        <i class="bi bi-moon-stars theme-dark-icon"></i><i class="bi bi-sun theme-light-icon"></i>
      </button>
      <div class="dropdown" data-dropdown>
        <button class="icon-btn" data-dropdown-toggle aria-label="Notifications">
          <i class="bi bi-bell"></i>
          <?php if ($unread): ?><span class="badge-dot" data-unread-badge><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
        </button>
        <div class="dropdown-menu notif-menu">
          <div class="dropdown-head">
            <strong>Notifications</strong>
            <?php if ($unread): ?><button class="link-btn" data-mark-all-read>Mark all read</button><?php endif; ?>
          </div>
          <?php if (!$notes): ?>
            <div class="empty-mini"><i class="bi bi-bell-slash"></i> You're all caught up!</div>
          <?php endif; ?>
          <?php foreach ($notes as $n): ?>
            <a class="notif <?= $n['is_read'] ? '' : 'unread' ?>" href="<?= e(url($n['link'] ?: 'notifications.php')) ?>" data-notif-id="<?= (int)$n['id'] ?>">
              <span><?= e($n['message']) ?></span>
              <small><?= e(relative_time($n['created_at'])) ?></small>
            </a>
          <?php endforeach; ?>
          <a class="dropdown-foot" href="<?= e(url('notifications.php')) ?>">View all notifications</a>
        </div>
      </div>
      <a href="<?= e(url('profile.php')) ?>" class="avatar sm" title="Profile"><?= e(initials($me['full_name'] ?? '?')) ?></a>
    </div>
  </header>

  <main class="content">
    <?php foreach (take_flashes() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>" data-autohide>
        <i class="bi <?= $f['type'] === 'success' ? 'bi-check-circle' : ($f['type'] === 'danger' ? 'bi-x-octagon' : 'bi-info-circle') ?>"></i>
        <span><?= e($f['message']) ?></span>
        <button class="alert-close" data-dismiss aria-label="Close">&times;</button>
      </div>
    <?php endforeach; ?>
