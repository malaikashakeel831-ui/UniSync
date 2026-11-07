<?php
/**
 * POST {course_id, marks: {assessmentId: hypotheticalMarks}, target_letter}
 * Re-calculates a course grade with hypothetical marks. NOTHING is saved.
 */
require __DIR__ . '/../../app/bootstrap.php';
if (!is_post()) abort(405, 'POST only');
$me = require_student();
$id = (int)$me['id'];

$in = json_input();
$courseId = (int)($in['course_id'] ?? 0);
if (!qv('SELECT 1 FROM courses WHERE id = ? AND user_id = ?', [$courseId, $id])) abort(404, 'Course not found');

$scale = GpaService::scale($id);
$items = qa('SELECT id, name, weight_percent, marks_obtained, total_marks FROM assessments WHERE course_id = ? AND user_id = ?', [$courseId, $id]);
$hyp = is_array($in['marks'] ?? null) ? $in['marks'] : [];
foreach ($items as &$a) {
    if (array_key_exists((string)$a['id'], $hyp) && $hyp[(string)$a['id']] !== '' && $hyp[(string)$a['id']] !== null) {
        $a['marks_obtained'] = max(0, min((float)$hyp[(string)$a['id']], (float)$a['total_marks']));
    }
}
unset($a);

$r = GpaService::calculate($items, $scale);
$out = ['ok' => true, 'percent' => $r['percent'] !== null ? round($r['percent'], 2) : null, 'letter' => $r['letter'], 'points' => $r['points'],
        'graded_weight' => $r['graded_weight'], 'remaining_weight' => $r['remaining_weight']];

$target = (string)($in['target_letter'] ?? '');
foreach ($scale as $s) {
    if ($s['letter'] === $target) {
        $out['required'] = GpaService::required((float)$s['min_percent'], $r['earned'], $r['remaining_weight']);
        $out['required']['target'] = $target;
    }
}
json_out($out);
