<?php
/**
 * GPA / CGPA calculations (Section 7.1 and 7.2 of the documentation).
 *
 * course_percent = SUM(obtained/total * weight) / SUM(weight of graded items) * 100
 * Semester GPA   = SUM(points * credit_hours) / SUM(credit_hours)
 */
class GpaService
{
    /** Grading scale for a user (their own rows, else the system default), highest first. */
    public static function scale(int $userId): array
    {
        $rows = qa('SELECT letter, min_percent, grade_points FROM grading_scale WHERE user_id = ? ORDER BY min_percent DESC', [$userId]);
        if (!$rows) {
            $rows = qa('SELECT letter, min_percent, grade_points FROM grading_scale WHERE user_id IS NULL ORDER BY min_percent DESC');
        }
        return $rows;
    }

    /** Map a percentage to [letter, points]. */
    public static function letterFor(float $percent, array $scale): array
    {
        foreach ($scale as $row) {
            if ($percent + 1e-9 >= (float)$row['min_percent']) {
                return [$row['letter'], (float)$row['grade_points']];
            }
        }
        return ['F', 0.0];
    }

    public static function pointsForLetter(string $letter, array $scale): ?float
    {
        foreach ($scale as $row) {
            if (strcasecmp($row['letter'], $letter) === 0) return (float)$row['grade_points'];
        }
        return null;
    }

    /**
     * Calculate a course result from a list of assessments.
     * Each item: weight_percent, marks_obtained (null = not yet graded), total_marks.
     */
    public static function calculate(array $assessments, array $scale): array
    {
        $gradedWeight = 0.0;
        $earned = 0.0;           // weighted marks earned (out of graded weight)
        $totalWeight = 0.0;
        foreach ($assessments as $a) {
            $w = (float)$a['weight_percent'];
            $totalWeight += $w;
            if ($a['marks_obtained'] === null || $a['marks_obtained'] === '' || (float)$a['total_marks'] <= 0) continue;
            $gradedWeight += $w;
            $earned += min((float)$a['marks_obtained'], (float)$a['total_marks']) / (float)$a['total_marks'] * $w;
        }
        $percent = $gradedWeight > 0 ? $earned / $gradedWeight * 100 : null;
        [$letter, $points] = $percent === null ? [null, null] : self::letterFor($percent, $scale);
        return [
            'percent'          => $percent,
            'letter'           => $letter,
            'points'           => $points,
            'earned'           => $earned,
            'graded_weight'    => $gradedWeight,
            'total_weight'     => $totalWeight,
            'remaining_weight' => max(0, 100 - $gradedWeight),
        ];
    }

    /**
     * Required-marks mode: percentage needed in remaining assessments to reach target.
     * needed = (target - earned) / remaining_weight * 100
     */
    public static function required(float $targetPercent, float $earned, float $remainingWeight): array
    {
        if ($remainingWeight <= 0) {
            return ['needed' => null, 'achievable' => $earned >= $targetPercent,
                    'message' => $earned >= $targetPercent ? 'Target already reached — all assessments are graded.' : 'No assessments remaining, the target can no longer be reached.'];
        }
        $needed = ($targetPercent - $earned) / $remainingWeight * 100;
        if ($needed <= 0) {
            return ['needed' => 0, 'achievable' => true, 'message' => 'You have already secured this grade, even with 0 in the remaining work.'];
        }
        if ($needed > 100) {
            return ['needed' => $needed, 'achievable' => false, 'message' => sprintf('You would need %.1f%% in the remaining %.0f%% of the course — this target is no longer achievable.', $needed, $remainingWeight)];
        }
        return ['needed' => $needed, 'achievable' => true, 'message' => sprintf('You need an average of %.1f%% in the remaining %.0f%% of the course.', $needed, $remainingWeight)];
    }

    /** Summary for one course (owned by user). */
    public static function courseSummary(array $course, int $userId, ?array $scale = null): array
    {
        $scale = $scale ?? self::scale($userId);
        $items = qa('SELECT * FROM assessments WHERE course_id = ? AND user_id = ? ORDER BY id', [$course['id'], $userId]);
        $r = self::calculate($items, $scale);
        // a manual final grade overrides the calculation
        if (!empty($course['final_grade_letter'])) {
            $p = self::pointsForLetter($course['final_grade_letter'], $scale);
            if ($p !== null) {
                $r['letter'] = $course['final_grade_letter'];
                $r['points'] = $p;
                $r['manual'] = true;
            }
        }
        $r['assessments'] = $items;
        return $r;
    }

    /** GPA over a list of courses (only courses with a grade count). */
    public static function gpaFor(array $courses, int $userId, ?array $scale = null): ?float
    {
        $scale = $scale ?? self::scale($userId);
        $pts = 0.0; $cr = 0.0;
        foreach ($courses as $c) {
            $s = self::courseSummary($c, $userId, $scale);
            if ($s['points'] === null) continue;
            $pts += $s['points'] * (float)$c['credit_hours'];
            $cr  += (float)$c['credit_hours'];
        }
        return $cr > 0 ? $pts / $cr : null;
    }

    public static function semesterGpa(int $semesterId, int $userId): ?float
    {
        $courses = qa('SELECT * FROM courses WHERE semester_id = ? AND user_id = ?', [$semesterId, $userId]);
        return self::gpaFor($courses, $userId);
    }

    public static function cgpa(int $userId): ?float
    {
        $courses = qa('SELECT * FROM courses WHERE user_id = ?', [$userId]);
        return self::gpaFor($courses, $userId);
    }

    /** GPA for each semester, oldest first (for the trend chart). */
    public static function trend(int $userId): array
    {
        $sems = qa('SELECT * FROM semesters WHERE user_id = ? ORDER BY COALESCE(start_date, \'1970-01-01\'), id', [$userId]);
        $out = [];
        foreach ($sems as $s) {
            $out[] = ['name' => $s['name'], 'gpa' => self::semesterGpa((int)$s['id'], $userId)];
        }
        return $out;
    }
}
