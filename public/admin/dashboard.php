<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

// Admin sees COUNTS ONLY — never any student's courses, grades or tasks.
$stats = q1("SELECT
    SUM(role = 'student') AS students,
    SUM(role = 'student' AND is_active = 1) AS active,
    SUM(role = 'student' AND is_active = 0) AS inactive,
    SUM(role = 'student' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS new7,
    SUM(role = 'student' AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS active7
  FROM users");
$stats = array_map('intval', $stats);
$totals = [
    'Semesters' => (int)qv('SELECT COUNT(*) FROM semesters'),
    'Courses'   => (int)qv('SELECT COUNT(*) FROM courses'),
    'Tasks'     => (int)qv('SELECT COUNT(*) FROM tasks'),
    'Groups'    => (int)qv('SELECT COUNT(*) FROM project_groups'),
];
$signups = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $signups[date('d M', strtotime($d))] = 0;
}
foreach (qa("SELECT DATE(created_at) d, COUNT(*) n FROM users WHERE role = 'student' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at)") as $r) {
    $signups[date('d M', strtotime($r['d']))] = (int)$r['n'];
}
$logs = qa('SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 12');
$recent = qa("SELECT id, full_name, email, created_at FROM users WHERE role = 'student' ORDER BY created_at DESC LIMIT 5");

$pageTitle = 'Admin Dashboard';
$active = 'admin_dashboard';
$useCharts = true;
require __DIR__ . '/../../app/views/header.php';
?>
<div class="stack">
  <div class="alert alert-info"><i class="bi bi-shield-check"></i><span>Privacy by design: administrators see account information and system-wide counts only — never a student's courses, grades or tasks.</span></div>
  <div class="grid grid-4">
    <div class="card stat"><div class="stat-icon tone-primary"><i class="bi bi-people"></i></div><div><div class="stat-value"><?= $stats['students'] ?></div><div class="stat-label">Total students</div></div></div>
    <div class="card stat"><div class="stat-icon tone-success"><i class="bi bi-person-check"></i></div><div><div class="stat-value"><?= $stats['active'] ?></div><div class="stat-label">Active accounts</div></div></div>
    <div class="card stat"><div class="stat-icon tone-accent"><i class="bi bi-person-plus"></i></div><div><div class="stat-value"><?= $stats['new7'] ?></div><div class="stat-label">New sign-ups (7 days)</div></div></div>
    <div class="card stat"><div class="stat-icon tone-danger"><i class="bi bi-person-slash"></i></div><div><div class="stat-value"><?= $stats['inactive'] ?></div><div class="stat-label">Deactivated</div></div></div>
  </div>
  <div class="grid grid-main">
    <div class="card"><h3 class="card-title"><i class="bi bi-graph-up"></i> Sign-ups (last 14 days)</h3><div class="chart-box"><canvas id="cSign"></canvas></div></div>
    <div class="card">
      <h3 class="card-title"><i class="bi bi-database"></i> System totals</h3>
      <ul class="list-plain">
        <?php foreach ($totals as $k => $v): ?><li><span><?= e($k) ?></span><strong><?= $v ?></strong></li><?php endforeach; ?>
        <li><span>Students active this week</span><strong><?= $stats['active7'] ?></strong></li>
      </ul>
    </div>
  </div>
  <div class="grid grid-2">
    <div class="card flush">
      <div class="card-head"><h3 class="card-title"><i class="bi bi-person-lines-fill"></i> Newest students</h3><a class="btn btn-soft btn-xs" href="<?= e(url('admin/users.php')) ?>">Manage users</a></div>
      <div class="table-wrap mt-2"><table class="table"><tbody>
        <?php foreach ($recent as $u): ?><tr><td><strong><?= e($u['full_name']) ?></strong><br><small><?= e($u['email']) ?></small></td><td class="text-right"><small><?= e(relative_time($u['created_at'])) ?></small></td></tr><?php endforeach; ?>
        <?php if (!$recent): ?><tr><td class="muted">No students yet.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <div class="card flush">
      <div class="card-head"><h3 class="card-title"><i class="bi bi-activity"></i> Recent activity (audit log)</h3></div>
      <div class="table-wrap mt-2"><table class="table"><tbody>
        <?php foreach ($logs as $l): ?><tr><td><?= e($l['action']) ?><br><small><?= e($l['full_name'] ?: 'Guest') ?> · <?= e($l['ip']) ?></small></td><td class="text-right nowrap"><small><?= e(relative_time($l['created_at'])) ?></small></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div>
  </div>
</div>
<?php
$pageScript = 'new Chart(cSign,{type:"bar",data:{labels:' . json_encode(array_keys($signups)) . ',datasets:[{label:"Sign-ups",data:' . json_encode(array_values($signups)) . ',backgroundColor:"#5b5bd6",borderRadius:6,maxBarThickness:28}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}},x:{grid:{display:false}}}}});';
require __DIR__ . '/../../app/views/footer.php';
