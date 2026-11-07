<?php
/**
 * Smart Study Planner (Section 7.3) — explainable, rule-based, no external AI.
 *
 * 1. Fetch pending tasks + available hours per weekday.
 * 2. score = urgency + importance (+2 for exams)
 *      urgency    = 10 / max(days_until_due, 0.5)
 *      importance = high 3, medium 2, low 1
 * 3. Sort by score (highest first).
 * 4. Allocate remaining hours into the earliest days before the deadline,
 *    in blocks of at most 2 hours.
 * 5. If a task does not fit, show an overload warning.
 */
class PlannerService
{
    const MAX_BLOCK = 2.0;
    const HORIZON_DAYS = 30;

    /** Hours per weekday [1..7]. Defaults are used until the student saves their own. */
    public static function availability(int $userId): array
    {
        $hours = [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 2, 6 => 4, 7 => 4];
        $rows = qa('SELECT day_of_week, hours FROM study_availability WHERE user_id = ?', [$userId]);
        foreach ($rows as $r) $hours[(int)$r['day_of_week']] = (float)$r['hours'];
        return $hours;
    }

    public static function hasSavedAvailability(int $userId): bool
    {
        return (int)qv('SELECT COUNT(*) FROM study_availability WHERE user_id = ?', [$userId]) > 0;
    }

    public static function score(array $task, int $now): float
    {
        $days = (strtotime($task['due_at']) - $now) / 86400;
        $urgency = 10 / max($days, 0.5);
        $importance = ['high' => 3, 'medium' => 2, 'low' => 1][$task['priority']] ?? 2;
        $bonus = $task['type'] === 'exam' ? 2 : 0;
        return round($urgency + $importance + $bonus, 2);
    }

    public static function generate(int $userId, ?int $now = null): array
    {
        $now = $now ?? time();
        $today = date('Y-m-d', $now);
        $avail = self::availability($userId);

        $tasks = qa("SELECT t.*, c.code AS course_code, c.color AS course_color,
                        COALESCE((SELECT SUM(s.duration_minutes) FROM study_sessions s
                                  WHERE s.task_id = t.id AND s.user_id = t.user_id), 0) AS logged_minutes
                     FROM tasks t LEFT JOIN courses c ON c.id = t.course_id
                     WHERE t.user_id = ? AND t.status <> 'done'", [$userId]);

        // remaining effort and score for each task
        $ranked = [];
        foreach ($tasks as $t) {
            $remaining = round((float)$t['estimated_hours'] - $t['logged_minutes'] / 60, 1);
            if ($remaining <= 0) continue;
            $t['remaining'] = $remaining;
            $t['score'] = self::score($t, $now);
            $t['overdue'] = strtotime($t['due_at']) < $now;
            $ranked[] = $t;
        }
        usort($ranked, fn($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['due_at'], $b['due_at']));

        // capacity for each day in the horizon
        $lastDue = $today;
        foreach ($ranked as $t) $lastDue = max($lastDue, substr($t['due_at'], 0, 10));
        $end = min($lastDue, date('Y-m-d', strtotime("+" . self::HORIZON_DAYS . " days", $now)));

        $loggedToday = (float)qv('SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id = ? AND session_date = ?', [$userId, $today]) / 60;

        $days = [];
        for ($d = $today; $d <= $end; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $cap = (float)$avail[(int)date('N', strtotime($d))];
            if ($d === $today) $cap = max(0, $cap - $loggedToday);
            $days[$d] = ['capacity' => $cap, 'free' => $cap, 'blocks' => []];
        }

        $warnings = [];
        foreach ($ranked as $t) {
            $left = $t['remaining'];
            // last study day: the due date, or the day before if it is due in the morning
            $lastDay = date('Y-m-d', strtotime($t['due_at']) - 12 * 3600);
            $deadline = $t['overdue'] ? $today : max($today, min($lastDay, $end));

            // pass 1: at most one block per day (spreads work out)
            // pass 2: fill any remaining free time
            for ($pass = 1; $pass <= 2 && $left > 0; $pass++) {
                foreach ($days as $d => &$day) {
                    if ($d > $deadline || $left <= 0) break;
                    while ($left > 0 && $day['free'] >= 0.5) {
                        $h = min(self::MAX_BLOCK, $left, $day['free']);
                        $h = floor($h * 2) / 2;              // half-hour blocks
                        if ($h <= 0) break;
                        $day['blocks'][] = [
                            'task_id' => (int)$t['id'], 'title' => $t['title'], 'type' => $t['type'],
                            'course_id' => $t['course_id'], 'course_code' => $t['course_code'],
                            'color' => $t['course_color'] ?: '#5b5bd6', 'hours' => $h, 'due_at' => $t['due_at'],
                        ];
                        $day['free'] -= $h;
                        $left = round($left - $h, 1);
                        if ($pass === 1) break;
                    }
                }
                unset($day);
            }

            if ($left > 0) {
                $warnings[] = [
                    'task_id' => (int)$t['id'],
                    'message' => $t['overdue']
                        ? sprintf('"%s" is overdue — %s hour(s) still could not fit today.', $t['title'], num($left, 1))
                        : sprintf('Need %s more hour(s) for "%s" before %s. Add study hours or start earlier.',
                            num($left, 1), $t['title'], date('l, d M', strtotime($t['due_at']))),
                ];
            }
        }

        return ['days' => $days, 'ranked' => $ranked, 'warnings' => $warnings, 'availability' => $avail];
    }
}
