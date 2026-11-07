<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_student();
$id = (int)$me['id'];

if (is_post()) {
    $action = post('action');
    if ($action === 'availability') {
        $hours = $_POST['hours'] ?? [];
        foreach (DAYS as $n => $d) {
            $h = max(0, min(16, round((float)($hours[$n] ?? 0) * 2) / 2));
            q('INSERT INTO study_availability (user_id, day_of_week, hours) VALUES (?, ?, ?)
               ON DUPLICATE KEY UPDATE hours = VALUES(hours)', [$id, $n, $h]);
        }
        flash('success', 'Study availability saved. Your plan has been regenerated.');
    } elseif ($action === 'log') {
        $taskId = (int)post('task_id') ?: null;
        $courseId = (int)post('course_id') ?: null;
        $minutes = (int)round((float)post('hours') * 60) ?: (int)post('minutes');
        $date = post('session_date') ?: date('Y-m-d');
        $note = mb_substr(post('note'), 0, 200);
        if ($taskId) {
            $t = q1('SELECT id, course_id FROM tasks WHERE id = ? AND user_id = ?', [$taskId, $id]);
            if (!$t) abort(404);
            $courseId = $t['course_id'];
        }
        if ($courseId && !qv('SELECT 1 FROM courses WHERE id = ? AND user_id = ?', [$courseId, $id])) abort(404);
        if ($minutes < 5 || $minutes > 960) flash('danger', 'Session length must be between 5 minutes and 16 hours.');
        elseif (!strtotime($date) || $date > date('Y-m-d')) flash('danger', 'Session date cannot be in the future.');
        else {
            q('INSERT INTO study_sessions (user_id, course_id, task_id, session_date, duration_minutes, note) VALUES (?,?,?,?,?,?)',
                [$id, $courseId, $taskId, $date, $minutes, $note ?: null]);
            flash('success', 'Study session logged (' . round($minutes / 60, 1) . ' h). Keep the streak going! 🔥');
        }
    } elseif ($action === 'delete_session') {
        $st = q('DELETE FROM study_sessions WHERE id = ? AND user_id = ?', [(int)post('id'), $id]);
        if (!$st->rowCount()) abort(404);
        flash('success', 'Session removed.');
    }
    redirect('planner.php');
}

$plan = PlannerService::generate($id);
$saved = PlannerService::hasSavedAvailability($id);
$courses = qa('SELECT id, code FROM courses WHERE user_id = ? ORDER BY code', [$id]);
$openTasks = qa("SELECT id, title FROM tasks WHERE user_id = ? AND status <> 'done' ORDER BY due_at", [$id]);
$sessions = qa('SELECT s.*, c.code, c.color, t.title AS task_title FROM study_sessions s
                LEFT JOIN courses c ON c.id = s.course_id LEFT JOIN tasks t ON t.id = s.task_id
                WHERE s.user_id = ? ORDER BY s.session_date DESC, s.id DESC LIMIT 8', [$id]);
$totalPlanned = 0;
foreach ($plan['days'] as $d) foreach ($d['blocks'] as $b) $totalPlanned += $b['hours'];

$pageTitle = 'Smart Study Planner';
$active = 'planner';
require __DIR__ . '/../app/views/header.php';
?>
<div class="stack">
  <div class="card">
    <div class="card-head">
      <h3 class="card-title"><i class="bi bi-clock-history"></i> Your available study hours</h3>
      <?php if (!$saved): ?><span class="badge badge-medium">Using default hours — save your own</span><?php endif; ?>
    </div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="availability">
      <div class="avail-grid">
        <?php foreach (DAYS as $n => $d): ?>
          <div class="field"><label for="h<?= $n ?>"><?= substr($d, 0, 3) ?></label>
            <input class="input" id="h<?= $n ?>" type="number" name="hours[<?= $n ?>]" min="0" max="16" step="0.5" value="<?= e($plan['availability'][$n]) ?>"></div>
        <?php endforeach; ?>
      </div>
      <div class="space-between mt-2">
        <small>Total: <strong><?= num(array_sum($plan['availability']), 1) ?> h/week</strong> · The planner fills these hours with your most urgent work.</small>
        <button class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Save & regenerate plan</button>
      </div>
    </form>
  </div>

  <?php foreach ($plan['warnings'] as $w): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i><span><strong>Overload:</strong> <?= e($w['message']) ?></span></div>
  <?php endforeach; ?>

  <div class="grid grid-side">
    <div>
      <div class="space-between mb-2"><h2 class="mb-0">Your generated plan</h2><small><?= num($totalPlanned, 1) ?> h scheduled across <?= count(array_filter($plan['days'], fn($d) => $d['blocks'])) ?> day(s)</small></div>
      <?php if (!$plan['ranked']): ?>
        <div class="card empty"><i class="bi bi-emoji-sunglasses"></i><h3>Nothing to plan</h3><p>You have no pending tasks with remaining hours. Add tasks with an estimated time to get a plan.</p>
          <a class="btn btn-primary" href="<?= e(url('tasks.php?new=1')) ?>">Add a task</a></div>
      <?php else: ?>
      <div class="plan-grid">
        <?php foreach ($plan['days'] as $date => $day): if (!$day['blocks'] && $date !== date('Y-m-d')) continue; $isToday = $date === date('Y-m-d'); ?>
          <div class="plan-day <?= $isToday ? 'today' : '' ?>">
            <div class="plan-day-head"><strong><?= $isToday ? 'Today' : e(date('l', strtotime($date))) ?> <small style="font-weight:500"><?= e(date('d M', strtotime($date))) ?></small></strong>
              <small><?= num($day['capacity'] - $day['free'], 1) ?> / <?= num($day['capacity'], 1) ?> h</small></div>
            <?php if (!$day['blocks']): ?><p class="muted mb-0" style="font-size:.86rem">No study blocks today<?= $day['capacity'] <= 0 ? ' (no free hours left)' : '' ?>.</p><?php endif; ?>
            <?php foreach ($day['blocks'] as $b): ?>
              <div class="plan-block" style="--c:<?= e($b['color']) ?>">
                <div class="b-info"><strong title="<?= e($b['title']) ?>"><?= e($b['title']) ?></strong><small><?= e($b['course_code'] ?: 'General') ?> · due <?= e(date('D d M', strtotime($b['due_at']))) ?></small></div>
                <span class="hrs"><?= num($b['hours'], 1) ?>h</span>
                <?php if ($isToday): ?>
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="log"><input type="hidden" name="task_id" value="<?= (int)$b['task_id'] ?>"><input type="hidden" name="hours" value="<?= e($b['hours']) ?>"><input type="hidden" name="note" value="Planned block">
                  <button class="btn btn-success btn-xs" title="Mark block as done (logs a study session)"><i class="bi bi-check-lg"></i> Done</button></form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="stack">
      <div class="card">
        <h3 class="card-title"><i class="bi bi-sort-down"></i> Priority ranking</h3>
        <p class="hint">score = 10 ÷ max(days left, 0.5) + importance (H3 / M2 / L1) + 2 for exams</p>
        <ol style="padding-left:1.1rem;margin:0" class="stack">
          <?php foreach (array_slice($plan['ranked'], 0, 6) as $t): ?>
            <li style="margin-top:.5rem"><div class="space-between" style="gap:.5rem;flex-wrap:nowrap"><span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><strong><?= e($t['title']) ?></strong></span><span class="badge badge-primary"><?= num($t['score'], 1) ?></span></div>
              <small><?= e($t['course_code'] ?: 'General') ?> · <?= num($t['remaining'], 1) ?> h left · <?= $t['overdue'] ? '<span style="color:var(--danger)">overdue</span>' : e(relative_time($t['due_at'])) ?></small></li>
          <?php endforeach; ?>
        </ol>
      </div>

      <div class="card">
        <h3 class="card-title"><i class="bi bi-journal-plus"></i> Log a study session</h3>
        <form method="post" class="form-grid">
          <?= csrf_field() ?><input type="hidden" name="action" value="log">
          <div class="field full"><label>Task (optional)</label><select class="input" name="task_id"><option value="">— none —</option><?php foreach ($openTasks as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['title']) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Course</label><select class="input" name="course_id"><option value="">General</option><?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['code']) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Hours</label><input class="input" type="number" name="hours" step="0.25" min="0.25" max="16" value="1" required></div>
          <div class="field"><label>Date</label><input class="input" type="date" name="session_date" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"></div>
          <div class="field"><label>Note</label><input class="input" name="note" maxlength="200" placeholder="What did you cover?"></div>
          <div class="full"><button class="btn btn-primary btn-block"><i class="bi bi-plus-lg"></i> Log session</button></div>
        </form>
      </div>

      <div class="card">
        <h3 class="card-title"><i class="bi bi-clock"></i> Recent sessions</h3>
        <?php if (!$sessions): ?><p class="muted mb-0">No sessions logged yet.</p><?php endif; ?>
        <ul class="list-plain">
          <?php foreach ($sessions as $s): ?>
            <li><span style="min-width:0"><span class="course-chip" style="--c:<?= e($s['color'] ?: '#94a3b8') ?>"><?= e($s['code'] ?: 'General') ?></span> <small><?= e($s['task_title'] ?: ($s['note'] ?: '')) ?></small><br><small><?= e(fmt_date($s['session_date'], 'D d M')) ?></small></span>
              <span class="row-gap nowrap"><strong><?= num($s['duration_minutes'] / 60, 1) ?>h</strong>
                <form method="post" class="inline-form" data-confirm="Delete this session?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_session"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-soft-danger btn-xs"><i class="bi bi-x"></i></button></form></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../app/views/footer.php';
