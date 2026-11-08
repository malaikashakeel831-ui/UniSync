<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_student();
$id = (int)$me['id'];
$scale = GpaService::scale($id);
$letters = array_column($scale, 'letter');

if (is_post()) {
    $action = post('action');
    $courseId = (int)post('id', 0);

    if ($action === 'save') {
        $semId  = (int)post('semester_id');
        $code   = strtoupper(post('code'));
        $title  = post('title');
        $cr     = (float)post('credit_hours');
        $instr  = post('instructor');
        $color  = valid_color(post('color', '#5b5bd6'));
        $final  = post('final_grade_letter');
        $final  = in_array($final, $letters, true) ? $final : null;

        $err = null;
        if (!qv('SELECT 1 FROM semesters WHERE id = ? AND user_id = ?', [$semId, $id])) $err = 'Please choose one of your semesters.';
        elseif ($code === '' || mb_strlen($code) > 20) $err = 'Course code is required (max 20 characters).';
        elseif (mb_strlen($title) < 2 || mb_strlen($title) > 120) $err = 'Course title must be 2–120 characters.';
        elseif ($cr <= 0 || $cr > 9) $err = 'Credit hours must be between 0.5 and 9.';

        if ($err) {
            flash('danger', $err);
        } elseif ($courseId) {
            if (!qv('SELECT 1 FROM courses WHERE id = ? AND user_id = ?', [$courseId, $id])) abort(404);
            q('UPDATE courses SET semester_id = ?, code = ?, title = ?, credit_hours = ?, instructor = ?, color = ?, final_grade_letter = ?
               WHERE id = ? AND user_id = ?', [$semId, $code, $title, $cr, $instr ?: null, $color, $final, $courseId, $id]);
            flash('success', 'Course updated.');
        } else {
            q('INSERT INTO courses (user_id, semester_id, code, title, credit_hours, instructor, color, final_grade_letter) VALUES (?,?,?,?,?,?,?,?)',
                [$id, $semId, $code, $title, $cr, $instr ?: null, $color, $final]);
            flash('success', "Course $code added.");
        }
    } elseif ($action === 'delete') {
        $st = q('DELETE FROM courses WHERE id = ? AND user_id = ?', [$courseId, $id]);
        if (!$st->rowCount()) abort(404);
        flash('success', 'Course deleted. Its tasks were kept (without a course).');
    }
    redirect('courses.php');
}

$semesters = qa('SELECT * FROM semesters WHERE user_id = ? ORDER BY is_current DESC, start_date DESC, id DESC', [$id]);
$filterSem = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;

$edit = null;
if (isset($_GET['edit'])) {
    $edit = q1('SELECT * FROM courses WHERE id = ? AND user_id = ?', [(int)$_GET['edit'], $id]);
    if (!$edit) abort(404);
}

$courses = qa('SELECT c.*, (SELECT COUNT(*) FROM tasks t WHERE t.course_id = c.id AND t.status <> \'done\') AS open_tasks
               FROM courses c WHERE c.user_id = ? ORDER BY c.code', [$id]);
$bySem = [];
foreach ($courses as $c) $bySem[$c['semester_id']][] = $c;

$palette = ['#5b5bd6', '#ff8a4c', '#10b981', '#0ea5e9', '#ef4444', '#a855f7', '#f59e0b', '#14b8a6', '#ec4899'];
$defaultColor = $palette[count($courses) % count($palette)];

$pageTitle = 'Courses';
$active = 'courses';
require __DIR__ . '/../app/views/header.php';
?>
<?php if (!$semesters): ?>
  <div class="card empty"><i class="bi bi-calendar-plus"></i><h3>Add a semester first</h3><p>Courses belong to a semester.</p>
    <a class="btn btn-primary" href="<?= e(url('semesters.php')) ?>"><i class="bi bi-plus-lg"></i> Add semester</a></div>
<?php else: ?>
<div class="grid grid-side">
  <div>
    <?php foreach ($semesters as $s):
        if ($filterSem && $filterSem !== (int)$s['id']) continue;
        $list = $bySem[$s['id']] ?? []; ?>
      <section class="semester-block">
        <div class="space-between mb-2">
          <h2 class="mb-0"><?= e($s['name']) ?> <?php if ($s['is_current']): ?><span class="badge tag-current">Current</span><?php endif; ?></h2>
          <small><?= count($list) ?> course(s) · <?= num(array_sum(array_column($list, 'credit_hours')), 1) ?> credit hours</small>
        </div>
        <?php if (!$list): ?>
          <div class="card empty" style="padding:1.5rem"><i class="bi bi-book"></i><p class="mb-0">No courses in this semester yet.</p></div>
        <?php endif; ?>
        <div class="grid grid-2">
          <?php foreach ($list as $c): $sum = GpaService::courseSummary($c, $id, $scale); ?>
            <div class="card course-card" style="--c:<?= e($c['color']) ?>">
              <div class="space-between">
                <div><span class="course-chip" style="--c:<?= e($c['color']) ?>"><?= e($c['code']) ?></span>
                  <h3 class="mt-1 mb-0"><?= e($c['title']) ?></h3>
                  <small><i class="bi bi-person"></i> <?= e($c['instructor'] ?: 'Instructor not set') ?></small></div>
                <div class="text-right"><div class="grade-pill"><?= e($sum['letter'] ?? '—') ?></div><small><?= $sum['percent'] !== null ? num($sum['percent'], 1) . '%' : 'no marks' ?></small></div>
              </div>
              <hr style="margin:.85rem 0">
              <div class="space-between">
                <small><?= num($c['credit_hours'], 1) ?> cr · <?= (int)$c['open_tasks'] ?> open task(s)</small>
                <div class="row-gap">
                  <a class="btn btn-ghost btn-xs" href="<?= e(url('gpa.php?course=' . $c['id'])) ?>" title="Marks"><i class="bi bi-award"></i></a>
                  <a class="btn btn-ghost btn-xs" href="<?= e(url('courses.php?edit=' . $c['id'])) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="inline-form" data-confirm="Delete <?= e($c['code']) ?>? Its marks and classes are deleted; tasks are kept."><?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-soft-danger btn-xs" title="Delete"><i class="bi bi-trash"></i></button></form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
    <?php if ($filterSem): ?><p class="mt-2"><a href="<?= e(url('courses.php')) ?>">← Show all semesters</a></p><?php endif; ?>
  </div>

  <div class="card" style="position:sticky;top:84px">
    <div class="card-head"><h3 class="card-title"><i class="bi <?= $edit ? 'bi-pencil-square' : 'bi-plus-circle' ?>"></i> <?= $edit ? 'Edit course' : 'Add course' ?></h3>
      <?php if ($edit): ?><a href="<?= e(url('courses.php')) ?>" class="btn btn-ghost btn-xs">Cancel</a><?php endif; ?></div>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="field"><label>Semester</label>
        <select class="input" name="semester_id" required>
          <?php foreach ($semesters as $s): $sel = $edit ? $edit['semester_id'] == $s['id'] : ($filterSem ? $filterSem == $s['id'] : $s['is_current']); ?>
            <option value="<?= (int)$s['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-grid">
        <div class="field"><label>Code</label><input class="input" name="code" required maxlength="20" placeholder="CS-301" value="<?= e($edit['code'] ?? '') ?>"></div>
        <div class="field"><label>Credit hours</label><input class="input" type="number" step="0.5" min="0.5" max="9" name="credit_hours" required value="<?= e($edit['credit_hours'] ?? '3') ?>"></div>
      </div>
      <div class="field"><label>Title</label><input class="input" name="title" required maxlength="120" placeholder="Database Systems" value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="field"><label>Instructor</label><input class="input" name="instructor" maxlength="100" placeholder="Dr. Ahmed" value="<?= e($edit['instructor'] ?? '') ?>"></div>
      <div class="form-grid">
        <div class="field"><label>Colour</label><input class="input" type="color" name="color" value="<?= e($edit['color'] ?? $defaultColor) ?>"></div>
        <div class="field"><label>Final grade <small>(optional)</small></label>
          <select class="input" name="final_grade_letter">
            <option value="">Calculate from marks</option>
            <?php foreach ($letters as $l): ?><option <?= ($edit['final_grade_letter'] ?? '') === $l ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <button class="btn btn-primary btn-block"><i class="bi bi-check2"></i> <?= $edit ? 'Save changes' : 'Add course' ?></button>
    </form>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../app/views/footer.php';
