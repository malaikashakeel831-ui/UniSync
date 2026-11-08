<?php
require __DIR__ . '/../app/bootstrap.php';
$me = require_student();
$id = (int)$me['id'];

if (is_post()) {
    $action = post('action');
    $semId = (int)post('id', 0);

    if ($action === 'save') {
        $name = post('name');
        $start = post('start_date') ?: null;
        $end = post('end_date') ?: null;
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            flash('danger', 'Semester name must be 2–60 characters.');
        } elseif ($start && $end && $end < $start) {
            flash('danger', 'End date cannot be before start date.');
        } else {
            if ($semId && !qv('SELECT 1 FROM semesters WHERE id = ? AND user_id = ?', [$semId, $id])) abort(404);
            if ($isCurrent) q('UPDATE semesters SET is_current = 0 WHERE user_id = ?', [$id]);
            if ($semId) {
                // ownership check is also part of the WHERE clause
                q('UPDATE semesters SET name = ?, start_date = ?, end_date = ?, is_current = ? WHERE id = ? AND user_id = ?',
                    [$name, $start, $end, $isCurrent, $semId, $id]);
                flash('success', 'Semester updated.');
            } else {
                // first semester becomes current automatically
                if (!qv('SELECT 1 FROM semesters WHERE user_id = ?', [$id])) $isCurrent = 1;
                q('INSERT INTO semesters (user_id, name, start_date, end_date, is_current) VALUES (?, ?, ?, ?, ?)', [$id, $name, $start, $end, $isCurrent]);
                flash('success', 'Semester "' . $name . '" added. Now add your courses!');
            }
        }
    } elseif ($action === 'current') {
        if (!qv('SELECT 1 FROM semesters WHERE id = ? AND user_id = ?', [$semId, $id])) abort(404);
        q('UPDATE semesters SET is_current = (id = ?) WHERE user_id = ?', [$semId, $id]);
        flash('success', 'Current semester changed.');
    } elseif ($action === 'delete') {
        $st = q('DELETE FROM semesters WHERE id = ? AND user_id = ?', [$semId, $id]);
        if (!$st->rowCount()) abort(404);
        flash('success', 'Semester and its courses were deleted.');
    }
    redirect('semesters.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = q1('SELECT * FROM semesters WHERE id = ? AND user_id = ?', [(int)$_GET['edit'], $id]);
    if (!$edit) abort(404);
}

$semesters = qa('SELECT s.*, (SELECT COUNT(*) FROM courses c WHERE c.semester_id = s.id) AS courses,
                        (SELECT COALESCE(SUM(credit_hours),0) FROM courses c WHERE c.semester_id = s.id) AS credits
                 FROM semesters s WHERE s.user_id = ? ORDER BY s.is_current DESC, s.start_date DESC, s.id DESC', [$id]);

$pageTitle = 'Semesters';
$active = 'semesters';
require __DIR__ . '/../app/views/header.php';
?>
<div class="grid grid-side">
  <div class="card flush">
    <div class="card-head"><h3 class="card-title"><i class="bi bi-calendar-range"></i> Your semesters</h3></div>
    <?php if (!$semesters): ?>
      <div class="empty"><i class="bi bi-calendar-plus"></i><h3>No semesters yet</h3><p>Add your current semester using the form to begin.</p></div>
    <?php else: ?>
    <div class="table-wrap mt-2">
      <table class="table">
        <thead><tr><th>Semester</th><th>Duration</th><th>Courses</th><th>GPA</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($semesters as $s): $g = GpaService::semesterGpa((int)$s['id'], $id); ?>
          <tr>
            <td><strong><?= e($s['name']) ?></strong> <?php if ($s['is_current']): ?><span class="badge tag-current">Current</span><?php endif; ?></td>
            <td class="nowrap"><small><?= e(fmt_date($s['start_date'], 'd M Y')) ?> – <?= e(fmt_date($s['end_date'], 'd M Y')) ?></small></td>
            <td><?= (int)$s['courses'] ?> <small>(<?= num($s['credits'], 1) ?> cr)</small></td>
            <td><strong><?= $g !== null ? num($g) : '—' ?></strong></td>
            <td class="actions">
              <a class="btn btn-ghost btn-xs" href="<?= e(url('courses.php?semester=' . $s['id'])) ?>"><i class="bi bi-book"></i> Courses</a>
              <?php if (!$s['is_current']): ?>
              <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="current"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button class="btn btn-soft btn-xs" title="Set as current"><i class="bi bi-star"></i></button></form>
              <?php endif; ?>
              <a class="btn btn-ghost btn-xs" href="<?= e(url('semesters.php?edit=' . $s['id'])) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
              <form method="post" class="inline-form" data-confirm="Delete this semester AND all its courses, marks and classes?"><?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button class="btn btn-soft-danger btn-xs" title="Delete"><i class="bi bi-trash"></i></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h3 class="card-title"><i class="bi <?= $edit ? 'bi-pencil-square' : 'bi-plus-circle' ?>"></i> <?= $edit ? 'Edit semester' : 'Add semester' ?></h3>
      <?php if ($edit): ?><a href="<?= e(url('semesters.php')) ?>" class="btn btn-ghost btn-xs">Cancel</a><?php endif; ?></div>
    <form method="post" class="stack" style="--gap:.8rem">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="field"><label>Name</label><input class="input" name="name" required maxlength="60" placeholder="e.g. Fall 2026" value="<?= e($edit['name'] ?? '') ?>"></div>
      <div class="form-grid">
        <div class="field"><label>Start date</label><input class="input" type="date" name="start_date" value="<?= e($edit['start_date'] ?? '') ?>"></div>
        <div class="field"><label>End date</label><input class="input" type="date" name="end_date" value="<?= e($edit['end_date'] ?? '') ?>"></div>
      </div>
      <label class="check"><input type="checkbox" name="is_current" <?= !empty($edit['is_current']) || !$semesters ? 'checked' : '' ?>> This is my current semester</label>
      <button class="btn btn-primary btn-block"><i class="bi bi-check2"></i> <?= $edit ? 'Save changes' : 'Add semester' ?></button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../app/views/footer.php';
