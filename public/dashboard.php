<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_student();
$id = (int)$me['id'];

NotificationService::generate($id);

$current = q1('SELECT * FROM semesters WHERE user_id = ? ORDER BY is_current DESC, start_date DESC, id DESC LIMIT 1', [$id]);
$counts  = AnalyticsService::taskCounts($id);
$courseCount = (int)qv('SELECT COUNT(*) FROM courses WHERE user_id = ?' . ($current ? ' AND semester_id = ' . (int)$current['id'] : ''), [$id]);
$semGpa  = $current ? GpaService::semesterGpa((int)$current['id'], $id) : null;
$cgpa    = GpaService::cgpa($id);
$streak  = AnalyticsService::streak($id);
$workload = AnalyticsService::workload($id);
$week    = AnalyticsService::thisWeekDaily($id);
$weekHours = array_sum(array_column($week, 'hours'));
$weekTarget = array_sum(PlannerService::availability($id));

$deadlines = qa("SELECT t.*, c.code, c.color FROM tasks t LEFT JOIN courses c ON c.id = t.course_id
                 WHERE t.user_id = ? AND t.status <> 'done' AND t.due_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                 ORDER BY t.due_at LIMIT 10", [$id]);
$todayClasses = qa('SELECT s.*, c.code, c.title, c.color FROM timetable_slots s JOIN courses c ON c.id = s.course_id
                    WHERE s.user_id = ? AND s.day_of_week = ? ORDER BY s.start_time', [$id, (int)date('N')]);
$groupTasks = qa("SELECT pt.*, g.name AS group_name FROM project_tasks pt JOIN project_groups g ON g.id = pt.group_id
                  WHERE pt.assigned_to = ? AND pt.status <> 'done' ORDER BY pt.due_date IS NULL, pt.due_date LIMIT 4", [$id]);

$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$pageTitle = 'Dashboard';
$active = 'dashboard';
$useCharts = true;
require __DIR__ . '/../app/views/header.php';
?>
<div class="stack">
  <section class="card hero-card">
    <div class="space-between">
      <div>
        <h2><?= e($greet) ?>, <?= e(explode(' ', $me['full_name'])[0]) ?> ✨</h2>
        <p><?= $current ? 'You are in <strong>' . e($current['name']) . '</strong>. ' : 'Add your first semester to get started. ' ?>
           <?= count($deadlines) ? 'You have ' . count($deadlines) . ' deadline(s) in the next 7 days.' : 'No deadlines in the next 7 days — great time to get ahead!' ?></p>
      </div>
      <a class="btn" style="background:rgba(255,255,255,.16);color:#fff" href="<?= e(url('planner.php')) ?>"><i class="bi bi-lightning-charge"></i> Today's study plan</a>
    </div>
    <div class="hero-meta">
      <div><strong><?= $cgpa !== null ? num($cgpa) : '—' ?></strong><small>CGPA</small></div>
      <div><strong><?= $streak ?> day<?= $streak == 1 ? '' : 's' ?> 🔥</strong><small>Study streak</small></div>
      <div><strong><?= num($weekHours, 1) ?> h</strong><small>Studied this week</small></div>
      <div><strong><?= $counts['on_time_rate'] !== null ? $counts['on_time_rate'] . '%' : '—' ?></strong><small>On-time rate</small></div>
    </div>
  </section>

  <section class="grid grid-4">
    <div class="card stat"><div class="stat-icon tone-primary"><i class="bi bi-book"></i></div><div><div class="stat-value"><?= $courseCount ?></div><div class="stat-label"><?= $current ? 'Courses this semester' : 'Total courses' ?></div></div></div>
    <div class="card stat"><div class="stat-icon tone-warning"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-value"><?= $counts['pending'] ?></div><div class="stat-label">Pending tasks<?= $counts['overdue'] ? ' · <span style="color:var(--danger)">' . $counts['overdue'] . ' overdue</span>' : '' ?></div></div></div>
    <div class="card stat"><div class="stat-icon tone-success"><i class="bi bi-check2-circle"></i></div><div><div class="stat-value"><?= $counts['done'] ?></div><div class="stat-label">Completed tasks</div></div></div>
    <div class="card stat"><div class="stat-icon tone-accent"><i class="bi bi-award"></i></div><div><div class="stat-value"><?= $semGpa !== null ? num($semGpa) : '—' ?></div><div class="stat-label">Current semester GPA</div></div></div>
  </section>

  <section class="grid grid-main">
    <div class="card">
      <div class="card-head">
        <h3 class="card-title"><i class="bi bi-alarm"></i> Upcoming deadlines (7 days)</h3>
        <a class="btn btn-soft btn-sm" href="<?= e(url('tasks.php?new=1')) ?>"><i class="bi bi-plus-lg"></i> Add task</a>
      </div>
      <?php if (!$deadlines): ?>
        <div class="empty"><i class="bi bi-cup-hot"></i><h3>Nothing due this week</h3><p>Enjoy the calm, or plan ahead by adding upcoming work.</p></div>
      <?php endif; ?>
      <?php foreach ($deadlines as $t): $over = strtotime($t['due_at']) < time(); ?>
        <div class="deadline <?= $over ? 'overdue' : '' ?>" style="--c:<?= e($t['color'] ?: '#5b5bd6') ?>" data-task-row="<?= (int)$t['id'] ?>">
          <button class="check-toggle" data-task-toggle="<?= (int)$t['id'] ?>" title="Mark as done"><i class="bi bi-check-lg"></i></button>
          <div class="type-ic"><i class="bi <?= type_icon($t['type']) ?>"></i></div>
          <div class="info">
            <strong class="task-title"><?= e($t['title']) ?></strong>
            <small><?= e($t['code'] ?: 'General') ?> · <?= e(ucfirst($t['type'])) ?> · <span class="badge badge-<?= e($t['priority']) ?>"><?= e(ucfirst($t['priority'])) ?></span></small>
          </div>
          <div class="when"><?= $over ? 'Overdue' : e(relative_time($t['due_at'])) ?><br><?= e(date('D, d M g:i A', strtotime($t['due_at']))) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="stack">
      <div class="card">
        <div class="card-head"><h3 class="card-title"><i class="bi bi-speedometer"></i> This week's workload</h3><span class="badge tone-<?= e($workload['tone']) ?>"><?= e($workload['label']) ?></span></div>
        <div class="progress <?= e($workload['tone']) ?>"><span style="width:<?= (int)$workload['percent'] ?>%"></span></div>
        <p class="hint mt-1 mb-0"><?= num($workload['need'], 1) ?> h of work due vs <?= num($workload['available'], 1) ?> h of study time left this week.</p>
      </div>
      <div class="card">
        <div class="card-head"><h3 class="card-title"><i class="bi bi-bar-chart"></i> Study progress</h3><small><?= num($weekHours, 1) ?> / <?= num($weekTarget, 0) ?> h</small></div>
        <div class="chart-box sm" style="height:160px"><canvas id="weekChart"></canvas></div>
      </div>
      <div class="card">
        <div class="card-head"><h3 class="card-title"><i class="bi bi-clock"></i> Today's classes</h3><a class="btn btn-ghost btn-xs" href="<?= e(url('timetable.php')) ?>">Timetable</a></div>
        <?php if (!$todayClasses): ?><p class="muted mb-0">No classes today.</p><?php endif; ?>
        <ul class="list-plain">
          <?php foreach ($todayClasses as $s): ?>
            <li><span class="course-chip" style="--c:<?= e($s['color']) ?>"><?= e($s['code']) ?> <span class="muted" style="font-weight:400"><?= e($s['title']) ?></span></span>
                <small class="nowrap"><?= e(date('g:i', strtotime($s['start_time']))) ?>–<?= e(date('g:i A', strtotime($s['end_time']))) ?><?= $s['room'] ? ' · ' . e($s['room']) : '' ?></small></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php if ($groupTasks): ?>
      <div class="card">
        <div class="card-head"><h3 class="card-title"><i class="bi bi-people"></i> Assigned to you</h3></div>
        <ul class="list-plain">
          <?php foreach ($groupTasks as $g): ?>
            <li style="display:block"><a href="<?= e(url('group_view.php?id=' . $g['group_id'])) ?>"><strong><?= e($g['title']) ?></strong></a><br><small><?= e($g['group_name']) ?><?= $g['due_date'] ? ' · ' . e(fmt_date($g['due_date'], 'd M')) : '' ?></small></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php
$pageScript = 'new Chart(document.getElementById("weekChart"),{type:"bar",data:{labels:' . json_encode(array_column($week, 'label')) . ',datasets:[{label:"Hours",data:' . json_encode(array_column($week, 'hours')) . ',backgroundColor:"#5b5bd6",borderRadius:6,maxBarThickness:26}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}},x:{grid:{display:false}}}}});';
require __DIR__ . '/../app/views/footer.php';
