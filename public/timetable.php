<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_student();
$id = (int)$me['id'];

if (is_post()) {
    $action = post('action');
    if ($action === 'add') {
        $courseId = (int)post('course_id');
        $day = (int)post('day_of_week');
        $start = post('start_time');
        $end = post('end_time');
        $room = post('room');
        $err = null;
        if (!qv('SELECT 1 FROM courses WHERE id = ? AND user_id = ?', [$courseId, $id])) $err = 'Choose one of your courses.';
        elseif ($day < 1 || $day > 7) $err = 'Invalid day.';
        elseif (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $end <= $start) $err = 'End time must be after start time.';
        elseif (qv('SELECT 1 FROM timetable_slots WHERE user_id = ? AND day_of_week = ? AND start_time < ? AND end_time > ?', [$id, $day, $end . ':00', $start . ':00']))
            $err = 'This class overlaps another class on ' . DAYS[$day] . '.';
        if ($err) flash('danger', $err);
        else {
            q('INSERT INTO timetable_slots (user_id, course_id, day_of_week, start_time, end_time, room) VALUES (?,?,?,?,?,?)', [$id, $courseId, $day, $start, $end, $room ?: null]);
            flash('success', 'Class added to your timetable.');
        }
    } elseif ($action === 'delete') {
        $st = q('DELETE FROM timetable_slots WHERE id = ? AND user_id = ?', [(int)post('id'), $id]);
        if (!$st->rowCount()) abort(404);
        flash('success', 'Class removed.');
    }
    redirect('timetable.php' . (isset($_GET['m']) ? '?m=' . urlencode($_GET['m']) : ''));
}

$courses = qa('SELECT c.* FROM courses c JOIN semesters s ON s.id = c.semester_id WHERE c.user_id = ? ORDER BY s.is_current DESC, c.code', [$id]);
$slots = qa('SELECT s.*, c.code, c.title, c.color FROM timetable_slots s JOIN courses c ON c.id = s.course_id WHERE s.user_id = ? ORDER BY s.start_time', [$id]);

// grid range
$minH = 8; $maxH = 17;
foreach ($slots as $s) {
    $minH = min($minH, (int)substr($s['start_time'], 0, 2));
    $maxH = max($maxH, (int)ceil(((int)substr($s['end_time'], 0, 2) * 60 + (int)substr($s['end_time'], 3, 2)) / 60));
}
$rowH = 56;
$byDay = [];
foreach ($slots as $s) $byDay[(int)$s['day_of_week']][] = $s;
$todayN = (int)date('N');

// ---- month calendar ----
$m = preg_match('/^\d{4}-\d{2}$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
$first = strtotime($m . '-01');
$gridStart = strtotime('-' . ((int)date('N', $first) - 1) . ' days', $first);
$gridEnd = strtotime('+41 days', $gridStart);
$tasks = qa('SELECT t.id, t.title, t.type, t.status, t.due_at, c.color, c.code FROM tasks t LEFT JOIN courses c ON c.id = t.course_id
             WHERE t.user_id = ? AND DATE(t.due_at) BETWEEN ? AND ? ORDER BY t.due_at', [$id, date('Y-m-d', $gridStart), date('Y-m-d', $gridEnd)]);
$groupTasks = qa('SELECT pt.id, pt.title, pt.status, pt.due_date, g.name FROM project_tasks pt JOIN project_groups g ON g.id = pt.group_id
                  WHERE pt.assigned_to = ? AND pt.due_date BETWEEN ? AND ?', [$id, date('Y-m-d', $gridStart), date('Y-m-d', $gridEnd)]);
$byDate = [];
foreach ($tasks as $t) $byDate[substr($t['due_at'], 0, 10)][] = $t;
foreach ($groupTasks as $t) $byDate[$t['due_date']][] = ['title' => '👥 ' . $t['title'], 'status' => $t['status'] === 'done' ? 'done' : 'pending', 'color' => '#a855f7', 'type' => 'project', 'due_at' => $t['due_date'] . ' 23:59:00'];

$pageTitle = 'Timetable & Calendar';
$active = 'timetable';
require __DIR__ . '/../app/views/header.php';
?>
<div class="stack">
  <div class="stack">
    <div class="card flush">
      <div class="card-head" style="padding-bottom:1rem"><h3 class="card-title"><i class="bi bi-calendar3-week"></i> Weekly class timetable</h3><small><?= count($slots) ?> class(es) per week</small></div>
      <div class="table-wrap">
        <div class="tt">
          <div class="tt-head"></div>
          <?php foreach (DAYS as $n => $d): ?><div class="tt-head <?= $n === $todayN ? 'today' : '' ?>"><?= substr($d, 0, 3) ?></div><?php endforeach; ?>
          <div class="tt-times" style="padding-top:.5em">
            <?php for ($h = $minH; $h < $maxH; $h++): ?><div class="tt-time"><?= date('g A', mktime($h, 0)) ?></div><?php endfor; ?>
          </div>
          <?php foreach (DAYS as $n => $d): ?>
            <div class="tt-col <?= $n === $todayN ? 'today' : '' ?>" style="height:<?= ($maxH - $minH) * $rowH ?>px">
              <?php foreach ($byDay[$n] ?? [] as $s):
                  $start = (int)substr($s['start_time'], 0, 2) * 60 + (int)substr($s['start_time'], 3, 2);
                  $end = (int)substr($s['end_time'], 0, 2) * 60 + (int)substr($s['end_time'], 3, 2);
                  $top = ($start - $minH * 60) / 60 * $rowH;
                  $height = max(28, ($end - $start) / 60 * $rowH - 4); ?>
                <div class="tt-slot" style="--c:<?= e($s['color']) ?>;top:<?= $top + 2 ?>px;height:<?= $height ?>px" title="<?= e($s['title']) ?>">
                  <strong><?= e($s['code']) ?></strong>
                  <?= e(date('g:i', strtotime($s['start_time']))) ?>–<?= e(date('g:i', strtotime($s['end_time']))) ?><?= $s['room'] ? '<br><i class="bi bi-geo-alt"></i> ' . e($s['room']) : '' ?>
                  <form method="post" data-confirm="Remove this class from the timetable?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="x" title="Remove">✕</button></form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <h3 class="card-title"><i class="bi bi-plus-circle"></i> Add a class</h3>
      <?php if (!$courses): ?>
        <p class="muted">Add courses first.</p><a class="btn btn-primary" href="<?= e(url('courses.php')) ?>">Add course</a>
      <?php else: ?>
      <form method="post" class="tt-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="field"><label>Course</label><select class="input" name="course_id" required>
          <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['code']) ?> — <?= e($c['title']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Day</label><select class="input" name="day_of_week">
          <?php foreach (DAYS as $n => $d): ?><option value="<?= $n ?>"><?= $d ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Starts</label><input class="input" type="time" name="start_time" value="09:00" required></div>
        <div class="field"><label>Ends</label><input class="input" type="time" name="end_time" value="10:30" required></div>
        <div class="field"><label>Room</label><input class="input" name="room" maxlength="40" placeholder="e.g. Lab 3 / Room 204"></div>
        <div class="field" style="justify-content:flex-end"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add to timetable</button></div>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <h3 class="card-title"><i class="bi bi-calendar-month"></i> <?= e(date('F Y', $first)) ?></h3>
      <div class="row-gap">
        <span class="badge badge-high">Heavy day = 2+ deadlines</span>
        <a class="btn btn-ghost btn-sm" href="?m=<?= date('Y-m', strtotime('-1 month', $first)) ?>"><i class="bi bi-chevron-left"></i></a>
        <a class="btn btn-ghost btn-sm" href="?m=<?= date('Y-m') ?>">Today</a>
        <a class="btn btn-ghost btn-sm" href="?m=<?= date('Y-m', strtotime('+1 month', $first)) ?>"><i class="bi bi-chevron-right"></i></a>
      </div>
    </div>
    <div class="table-wrap">
      <div class="cal" style="min-width:640px">
        <?php foreach (DAYS as $d): ?><div class="cal-dow"><?= substr($d, 0, 3) ?></div><?php endforeach; ?>
        <?php for ($i = 0; $i < 42; $i++):
            $ts = strtotime("+$i days", $gridStart);
            $date = date('Y-m-d', $ts);
            $items = $byDate[$date] ?? [];
            $deadlines = count(array_filter($items, fn($x) => $x['status'] !== 'done'));
            $classes = count($byDay[(int)date('N', $ts)] ?? []);
            $cls = [];
            if (date('Y-m', $ts) !== $m) $cls[] = 'other';
            if ($date === date('Y-m-d')) $cls[] = 'today';
            if ($deadlines >= 2) $cls[] = 'heavy'; ?>
          <div class="cal-day <?= implode(' ', $cls) ?>">
            <span class="cal-num"><?= date('j', $ts) ?></span>
            <?php if ($classes): ?><span class="cal-item class"><i class="bi bi-easel"></i> <?= $classes ?> class<?= $classes > 1 ? 'es' : '' ?></span><?php endif; ?>
            <?php foreach (array_slice($items, 0, 3) as $t): ?>
              <span class="cal-item <?= $t['status'] === 'done' ? 'done' : '' ?>" style="--c:<?= e($t['color'] ?: '#5b5bd6') ?>" title="<?= e($t['title']) ?>"><?= e($t['title']) ?></span>
            <?php endforeach; ?>
            <?php if (count($items) > 3): ?><small>+<?= count($items) - 3 ?> more</small><?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../app/views/footer.php';
