<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_student();
$id = (int)$me['id'];
$scale = GpaService::scale($id);

if (is_post()) {
    $action = post('action');
    $courseId = (int)post('course_id');
    if (!qv('SELECT 1 FROM courses WHERE id = ? AND user_id = ?', [$courseId, $id])) abort(404);
    $aid = (int)post('id', 0);

    if ($action === 'save') {
        $name   = post('name');
        $weight = (float)post('weight_percent');
        $total  = (float)post('total_marks');
        $marks  = post('marks_obtained');
        $marks  = $marks === '' ? null : (float)$marks;

        $usedWeight = (float)qv('SELECT COALESCE(SUM(weight_percent),0) FROM assessments WHERE course_id = ? AND user_id = ? AND id <> ?', [$courseId, $id, $aid]);
        $err = null;
        if (mb_strlen($name) < 1 || mb_strlen($name) > 80) $err = 'Assessment name is required.';
        elseif ($weight <= 0 || $weight > 100) $err = 'Weight must be between 0 and 100%.';
        elseif ($usedWeight + $weight > 100.001) $err = sprintf('Total weight would be %.1f%% — the course can only add up to 100%%.', $usedWeight + $weight);
        elseif ($total <= 0) $err = 'Total marks must be greater than 0.';
        elseif ($marks !== null && ($marks < 0 || $marks > $total)) $err = 'Marks obtained must be between 0 and the total marks.';

        if ($err) flash('danger', $err);
        elseif ($aid) {
            if (!qv('SELECT 1 FROM assessments WHERE id = ? AND user_id = ?', [$aid, $id])) abort(404);
            q('UPDATE assessments SET name=?, weight_percent=?, marks_obtained=?, total_marks=? WHERE id = ? AND user_id = ?', [$name, $weight, $marks, $total, $aid, $id]);
            flash('success', 'Assessment updated.');
        } else {
            q('INSERT INTO assessments (user_id, course_id, name, weight_percent, marks_obtained, total_marks) VALUES (?,?,?,?,?,?)', [$id, $courseId, $name, $weight, $marks, $total]);
            flash('success', 'Assessment added.');
        }
    } elseif ($action === 'delete') {
        $st = q('DELETE FROM assessments WHERE id = ? AND user_id = ?', [$aid, $id]);
        if (!$st->rowCount()) abort(404);
        flash('success', 'Assessment deleted.');
    }
    redirect('gpa.php?course=' . $courseId);
}

$semesters = qa('SELECT * FROM semesters WHERE user_id = ? ORDER BY is_current DESC, start_date DESC, id DESC', [$id]);
$selCourse = null;
if (isset($_GET['course'])) {
    $selCourse = q1('SELECT * FROM courses WHERE id = ? AND user_id = ?', [(int)$_GET['course'], $id]);
    if (!$selCourse) abort(404);
}
$semId = $selCourse ? (int)$selCourse['semester_id'] : (int)($_GET['semester'] ?? ($semesters[0]['id'] ?? 0));
$sem = null;
foreach ($semesters as $s) if ((int)$s['id'] === $semId) $sem = $s;
if ($semId && !$sem) abort(404);

$courses = $sem ? qa('SELECT * FROM courses WHERE semester_id = ? AND user_id = ? ORDER BY code', [$semId, $id]) : [];
if (!$selCourse && $courses) $selCourse = $courses[0];
$semGpa = $sem ? GpaService::gpaFor($courses, $id, $scale) : null;
$cgpa = GpaService::cgpa($id);
$summary = $selCourse ? GpaService::courseSummary($selCourse, $id, $scale) : null;

$editA = null;
if (isset($_GET['edit_assessment'])) {
    $editA = q1('SELECT * FROM assessments WHERE id = ? AND user_id = ?', [(int)$_GET['edit_assessment'], $id]);
    if (!$editA) abort(404);
}

$pageTitle = 'GPA & CGPA Planner';
$active = 'gpa';
require __DIR__ . '/../app/views/header.php';
?>
<?php if (!$semesters): ?>
  <div class="card empty"><i class="bi bi-award"></i><h3>No semesters yet</h3><p>Add a semester and courses to start calculating your GPA.</p>
    <a class="btn btn-primary" href="<?= e(url('semesters.php')) ?>">Add semester</a></div>
<?php else: ?>
<div class="stack">
  <div class="grid grid-3">
    <div class="card stat" style="gap:1.25rem">
      <div class="gpa-ring" style="--p:<?= $cgpa !== null ? $cgpa / 4 * 100 : 0 ?>"><div><div><strong><?= $cgpa !== null ? num($cgpa) : '—' ?></strong><small>CGPA</small></div></div></div>
      <div><h3 class="mb-0">Cumulative GPA</h3><small>All semesters, weighted by credit hours</small></div>
    </div>
    <div class="card stat" style="gap:1.25rem">
      <div class="gpa-ring" style="--p:<?= $semGpa !== null ? $semGpa / 4 * 100 : 0 ?>;--ring:var(--accent)"><div><div><strong><?= $semGpa !== null ? num($semGpa) : '—' ?></strong><small>GPA</small></div></div></div>
      <div><h3 class="mb-0"><?= e($sem['name'] ?? '') ?></h3><small>Semester GPA</small>
        <form method="get" class="mt-1"><select class="input input-sm" name="semester" onchange="this.form.submit()">
          <?php foreach ($semesters as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $semId ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select></form></div>
    </div>
    <div class="card">
      <h3 class="card-title"><i class="bi bi-table"></i> Grading scale</h3>
      <div style="display:flex;flex-wrap:wrap;gap:.35rem">
        <?php foreach ($scale as $g): ?><span class="badge badge-muted" title="≥ <?= num($g['min_percent'], 0) ?>%"><?= e($g['letter']) ?> · <?= num($g['min_percent'], 0) ?>% · <?= num($g['grade_points']) ?></span><?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if (!$courses): ?>
    <div class="card empty"><i class="bi bi-book"></i><h3>No courses in this semester</h3><a class="btn btn-primary" href="<?= e(url('courses.php?semester=' . $semId)) ?>">Add courses</a></div>
  <?php else: ?>
  <div class="card flush">
    <div class="card-head"><h3 class="card-title"><i class="bi bi-collection"></i> Courses in <?= e($sem['name']) ?></h3></div>
    <div class="table-wrap mt-2">
      <table class="table">
        <thead><tr><th>Course</th><th>Credits</th><th>Graded weight</th><th>Percentage</th><th>Grade</th><th>Points</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($courses as $c): $s = GpaService::courseSummary($c, $id, $scale); ?>
          <tr style="<?= (int)$c['id'] === (int)$selCourse['id'] ? 'background:var(--primary-50)' : '' ?>">
            <td><span class="course-chip" style="--c:<?= e($c['color']) ?>"><?= e($c['code']) ?></span> <small><?= e($c['title']) ?></small></td>
            <td><?= num($c['credit_hours'], 1) ?></td>
            <td><div class="progress" style="width:110px"><span style="width:<?= min(100, $s['graded_weight']) ?>%"></span></div><small><?= num($s['graded_weight'], 0) ?>% graded</small></td>
            <td><?= $s['percent'] !== null ? num($s['percent'], 1) . '%' : '—' ?></td>
            <td><strong><?= e($s['letter'] ?? '—') ?></strong><?= !empty($s['manual']) ? ' <small>(manual)</small>' : '' ?></td>
            <td><?= $s['points'] !== null ? num($s['points']) : '—' ?></td>
            <td class="actions"><a class="btn btn-soft btn-xs" href="?course=<?= (int)$c['id'] ?>">Marks & what-if <i class="bi bi-chevron-right"></i></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($selCourse): ?>
  <div class="grid grid-side">
    <div class="card flush">
      <div class="card-head" style="padding-bottom:.75rem">
        <h3 class="card-title"><span class="dot" style="--c:<?= e($selCourse['color']) ?>"></span> <?= e($selCourse['code']) ?> — assessments</h3>
        <small>Type hypothetical marks in the <b>What-if</b> column. Nothing is saved.</small>
      </div>
      <?php if (!$summary['assessments']): ?>
        <div class="empty"><i class="bi bi-ui-checks"></i><h3>No assessments yet</h3><p>Add quizzes, assignments, midterm and final with their weights.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="table" id="whatifTable" data-course="<?= (int)$selCourse['id'] ?>">
          <thead><tr><th>Assessment</th><th>Weight</th><th>Marks</th><th>Score</th><th>What-if</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($summary['assessments'] as $a): ?>
            <tr>
              <td><strong><?= e($a['name']) ?></strong></td>
              <td><?= num($a['weight_percent'], 1) ?>%</td>
              <td class="nowrap"><?= $a['marks_obtained'] !== null ? num($a['marks_obtained'], 1) : '<span class="badge badge-muted">Pending</span>' ?> / <?= num($a['total_marks'], 1) ?></td>
              <td><?= $a['marks_obtained'] !== null ? num($a['marks_obtained'] / $a['total_marks'] * 100, 1) . '%' : '—' ?></td>
              <td><input class="input input-sm whatif-input" type="number" step="0.5" min="0" max="<?= e($a['total_marks']) ?>" data-aid="<?= (int)$a['id'] ?>" placeholder="<?= $a['marks_obtained'] !== null ? e((string)(float)$a['marks_obtained']) : '?' ?>"></td>
              <td class="actions">
                <a class="btn btn-ghost btn-xs" href="?course=<?= (int)$selCourse['id'] ?>&edit_assessment=<?= (int)$a['id'] ?>#aform"><i class="bi bi-pencil"></i></a>
                <form method="post" class="inline-form" data-confirm="Delete this assessment?"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="course_id" value="<?= (int)$selCourse['id'] ?>">
                  <button class="btn btn-soft-danger btn-xs"><i class="bi bi-trash"></i></button></form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <div style="padding:1.1rem 1.35rem;border-top:1px solid var(--border)" class="space-between">
        <div><small>Current result</small><div class="big-num" id="wiPercent"><?= $summary['percent'] !== null ? num($summary['percent'], 1) . '%' : '—' ?></div></div>
        <div class="text-center"><small>Grade</small><div class="big-num" id="wiLetter" style="color:<?= e($selCourse['color']) ?>"><?= e($summary['letter'] ?? '—') ?></div></div>
        <div class="text-right"><small>Grade points</small><div class="big-num" id="wiPoints"><?= $summary['points'] !== null ? num($summary['points']) : '—' ?></div></div>
      </div>
    </div>

    <div class="stack">
      <div class="card">
        <h3 class="card-title"><i class="bi bi-bullseye"></i> Marks needed for a target</h3>
        <p class="hint">Calculates the average you need in the remaining <strong id="wiRemaining"><?= num($summary['remaining_weight'], 0) ?>%</strong> of the course.</p>
        <div class="field"><label>Target grade</label>
          <select class="input" id="targetLetter">
            <?php foreach ($scale as $g): if ((float)$g['grade_points'] <= 0) continue; ?><option value="<?= e($g['letter']) ?>"><?= e($g['letter']) ?> (≥ <?= num($g['min_percent'], 0) ?>%)</option><?php endforeach; ?>
          </select></div>
        <div class="result-box mt-2" id="requiredBox">Choose a target grade.</div>
      </div>

      <div class="card" id="aform">
        <div class="card-head"><h3 class="card-title"><i class="bi <?= $editA ? 'bi-pencil-square' : 'bi-plus-circle' ?>"></i> <?= $editA ? 'Edit assessment' : 'Add assessment' ?></h3>
          <?php if ($editA): ?><a class="btn btn-ghost btn-xs" href="?course=<?= (int)$selCourse['id'] ?>">Cancel</a><?php endif; ?></div>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save"><input type="hidden" name="course_id" value="<?= (int)$selCourse['id'] ?>"><input type="hidden" name="id" value="<?= (int)($editA['id'] ?? 0) ?>">
          <div class="field full"><label>Name</label><input class="input" name="name" required maxlength="80" placeholder="Quiz 1, Midterm, Final…" value="<?= e($editA['name'] ?? '') ?>"></div>
          <div class="field"><label>Weight (%)</label><input class="input" type="number" step="0.5" min="0.5" max="100" name="weight_percent" required value="<?= e($editA['weight_percent'] ?? '') ?>"></div>
          <div class="field"><label>Total marks</label><input class="input" type="number" step="0.5" min="0.5" name="total_marks" required value="<?= e($editA['total_marks'] ?? '') ?>"></div>
          <div class="field full"><label>Marks obtained <small>(leave empty if not graded yet)</small></label><input class="input" type="number" step="0.25" min="0" name="marks_obtained" value="<?= e($editA['marks_obtained'] ?? '') ?>"></div>
          <div class="full"><button class="btn btn-primary btn-block"><i class="bi bi-check2"></i> Save assessment</button>
          <p class="hint mt-1 mb-0">Weights used so far: <?= num($summary['total_weight'], 1) ?>% of 100%.</p></div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php
$pageScript = <<<'JS'
(function(){
  const table = document.getElementById('whatifTable');
  const target = document.getElementById('targetLetter');
  if (!target) return;
  const courseId = table ? Number(table.dataset.course) : Number(new URLSearchParams(location.search).get('course')) || null;
  const box = document.getElementById('requiredBox');
  let timer;
  async function recalc() {
    const marks = {};
    document.querySelectorAll('.whatif-input').forEach(i => { if (i.value !== '') marks[i.dataset.aid] = Number(i.value); });
    try {
      const r = await api('api/whatif.php', { course_id: courseId || CURRENT_COURSE, marks, target_letter: target.value });
      document.getElementById('wiPercent').textContent = r.percent === null ? '—' : r.percent.toFixed(1) + '%';
      document.getElementById('wiLetter').textContent = r.letter || '—';
      document.getElementById('wiPoints').textContent = r.points === null ? '—' : Number(r.points).toFixed(2);
      document.getElementById('wiRemaining').textContent = Math.round(r.remaining_weight) + '%';
      if (r.required) {
        box.className = 'result-box mt-2 ' + (r.required.achievable ? 'ok' : 'bad');
        box.innerHTML = '<strong>' + (r.required.achievable ? '✅ Achievable' : '❌ Not achievable') + '</strong><br>';
        box.append(document.createTextNode(r.required.message));
      }
    } catch (e) { box.textContent = e.message; }
  }
  document.querySelectorAll('.whatif-input').forEach(i => i.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(recalc, 250); }));
  target.addEventListener('change', recalc);
  recalc();
})();
JS;
$pageScript = 'var CURRENT_COURSE=' . (int)($selCourse['id'] ?? 0) . ';' . $pageScript;
require __DIR__ . '/../app/views/footer.php';
