<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_student();
$id = (int)$me['id'];

$counts   = AnalyticsService::taskCounts($id);
$workload = AnalyticsService::workloadByCourse($id);
$weekly   = AnalyticsService::weeklyStudyHours($id, 8);
$trend    = array_values(array_filter(GpaService::trend($id), fn($t) => $t['gpa'] !== null));
$streak   = AnalyticsService::streak($id);
$byType   = qa("SELECT type, COUNT(*) n FROM tasks WHERE user_id = ? GROUP BY type", [$id]);
$totalStudy = (int)qv('SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id = ?', [$id]);
$cgpa = GpaService::cgpa($id);

$pageTitle = 'Performance Analytics';
$active = 'analytics';
$useCharts = true;
require __DIR__ . '/../app/views/header.php';
?>
<div class="stack">
  <div class="grid grid-4">
    <div class="card stat"><div class="stat-icon tone-success"><i class="bi bi-stopwatch"></i></div><div><div class="stat-value"><?= $counts['on_time_rate'] !== null ? $counts['on_time_rate'] . '%' : '—' ?></div><div class="stat-label">On-time completion rate</div></div></div>
    <div class="card stat"><div class="stat-icon tone-primary"><i class="bi bi-hourglass"></i></div><div><div class="stat-value"><?= num($totalStudy / 60, 1) ?> h</div><div class="stat-label">Total study time logged</div></div></div>
    <div class="card stat"><div class="stat-icon tone-accent"><i class="bi bi-fire"></i></div><div><div class="stat-value"><?= $streak ?></div><div class="stat-label">Day study streak</div></div></div>
    <div class="card stat"><div class="stat-icon tone-info"><i class="bi bi-award"></i></div><div><div class="stat-value"><?= $cgpa !== null ? num($cgpa) : '—' ?></div><div class="stat-label">CGPA</div></div></div>
  </div>

  <?php if (!$counts['total'] && !$totalStudy): ?>
    <div class="card empty"><i class="bi bi-graph-up"></i><h3>Not enough data yet</h3><p>Add tasks, log study sessions and enter marks — your charts will appear here.</p></div>
  <?php endif; ?>

  <div class="grid grid-3">
    <div class="card"><h3 class="card-title"><i class="bi bi-pie-chart"></i> Completed vs pending</h3><div class="chart-box sm"><canvas id="cDone"></canvas></div></div>
    <div class="card" style="grid-column:span 2"><h3 class="card-title"><i class="bi bi-bar-chart-steps"></i> Workload by course</h3><div class="chart-box sm"><canvas id="cWork"></canvas></div></div>
  </div>
  <div class="grid grid-2">
    <div class="card"><h3 class="card-title"><i class="bi bi-activity"></i> Weekly study hours (last 8 weeks)</h3><div class="chart-box"><canvas id="cWeek"></canvas></div></div>
    <div class="card"><h3 class="card-title"><i class="bi bi-graph-up-arrow"></i> GPA trend across semesters</h3>
      <?php if (!$trend): ?><div class="empty"><i class="bi bi-graph-up"></i><p>Enter marks in the GPA planner to see your trend.</p></div><?php else: ?>
      <div class="chart-box"><canvas id="cGpa"></canvas></div><?php endif; ?></div>
  </div>
  <div class="card"><h3 class="card-title"><i class="bi bi-diagram-3"></i> Tasks by type</h3><div class="chart-box sm"><canvas id="cType"></canvas></div></div>
</div>
<?php
$data = [
    'done' => [$counts['done'], $counts['pending'] - $counts['overdue'], $counts['overdue']],
    'work' => ['labels' => array_column($workload, 'label'), 'hours' => array_map('floatval', array_column($workload, 'hours')), 'tasks' => array_map('intval', array_column($workload, 'tasks')), 'colors' => array_column($workload, 'color')],
    'week' => ['labels' => array_column($weekly, 'label'), 'hours' => array_column($weekly, 'hours')],
    'gpa'  => ['labels' => array_column($trend, 'name'), 'values' => array_map(fn($t) => round($t['gpa'], 2), $trend)],
    'type' => ['labels' => array_map('ucfirst', array_column($byType, 'type')), 'values' => array_map('intval', array_column($byType, 'n'))],
];
$pageScript = 'const D=' . json_encode($data) . ';' . <<<'JS'
const pal = ['#5b5bd6','#ff8a4c','#10b981','#0ea5e9','#ef4444','#a855f7','#f59e0b'];
new Chart(cDone,{type:'doughnut',data:{labels:['Completed','Pending','Overdue'],datasets:[{data:D.done,backgroundColor:['#10b981','#5b5bd6','#ef4444'],borderWidth:0}]},options:{maintainAspectRatio:false,cutout:'68%',plugins:{legend:{position:'bottom'}}}});
new Chart(cWork,{type:'bar',data:{labels:D.work.labels,datasets:[{label:'Pending hours',data:D.work.hours,backgroundColor:D.work.colors,borderRadius:8,maxBarThickness:44},{label:'Total tasks',data:D.work.tasks,backgroundColor:'rgba(148,163,184,.35)',borderRadius:8,maxBarThickness:44}]},options:{maintainAspectRatio:false,scales:{y:{beginAtZero:true},x:{grid:{display:false}}}}});
new Chart(cWeek,{type:'line',data:{labels:D.week.labels,datasets:[{label:'Hours studied',data:D.week.hours,borderColor:'#5b5bd6',backgroundColor:'rgba(91,91,214,.15)',fill:true,tension:.35,pointRadius:4,pointBackgroundColor:'#5b5bd6'}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
if (window.cGpa) new Chart(cGpa,{type:'line',data:{labels:D.gpa.labels,datasets:[{label:'Semester GPA',data:D.gpa.values,borderColor:'#ff8a4c',backgroundColor:'#ff8a4c',tension:.3,pointRadius:6}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{min:0,max:4}}}});
new Chart(cType,{type:'bar',data:{labels:D.type.labels,datasets:[{label:'Tasks',data:D.type.values,backgroundColor:pal,borderRadius:8,maxBarThickness:40}]},options:{indexAxis:'y',maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{stepSize:1}},y:{grid:{display:false}}}}});
JS;
require __DIR__ . '/../app/views/footer.php';
