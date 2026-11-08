<?php
/**
 * Statistics for the dashboard and analytics page.
 */
class AnalyticsService
{
    public static function taskCounts(int $userId): array
    {
        $r = q1("SELECT
                    SUM(status = 'done') AS done,
                    SUM(status <> 'done') AS pending,
                    SUM(status <> 'done' AND due_at < NOW()) AS overdue,
                    SUM(status = 'done' AND completed_at IS NOT NULL AND completed_at <= due_at) AS on_time,
                    COUNT(*) AS total
                 FROM tasks WHERE user_id = ?", [$userId]);
        $r = array_map('intval', $r ?: []);
        $r['on_time_rate'] = $r['done'] > 0 ? round($r['on_time'] / $r['done'] * 100) : null;
        return $r;
    }

    /** Pending estimated hours and task count per course. */
    public static function workloadByCourse(int $userId): array
    {
        return qa("SELECT COALESCE(c.code, 'General') AS label, COALESCE(c.color, '#94a3b8') AS color,
                          COUNT(t.id) AS tasks, COALESCE(SUM(CASE WHEN t.status <> 'done' THEN t.estimated_hours END), 0) AS hours
                   FROM tasks t LEFT JOIN courses c ON c.id = t.course_id
                   WHERE t.user_id = ?
                   GROUP BY c.id, c.code, c.color ORDER BY hours DESC", [$userId]);
    }

    /** Study hours for each of the last N weeks (Monday based). */
    public static function weeklyStudyHours(int $userId, int $weeks = 8): array
    {
        $out = [];
        $monday = strtotime('monday this week');
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = date('Y-m-d', strtotime("-$i week", $monday));
            $end   = date('Y-m-d', strtotime("$start +6 days"));
            $mins = (int)qv('SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id = ? AND session_date BETWEEN ? AND ?',
                [$userId, $start, $end]);
            $out[] = ['label' => date('d M', strtotime($start)), 'hours' => round($mins / 60, 1)];
        }
        return $out;
    }

    /** Hours studied on each day of the current week. */
    public static function thisWeekDaily(int $userId): array
    {
        $monday = date('Y-m-d', strtotime('monday this week'));
        $rows = qa('SELECT session_date, SUM(duration_minutes) m FROM study_sessions WHERE user_id = ? AND session_date BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY) GROUP BY session_date',
            [$userId, $monday, $monday]);
        $map = [];
        foreach ($rows as $r) $map[$r['session_date']] = round($r['m'] / 60, 1);
        $out = [];
        for ($i = 0; $i < 7; $i++) {
            $d = date('Y-m-d', strtotime("$monday +$i day"));
            $out[] = ['label' => date('D', strtotime($d)), 'hours' => $map[$d] ?? 0];
        }
        return $out;
    }

    /** Consecutive days (ending today or yesterday) with a study session. */
    public static function streak(int $userId): int
    {
        $dates = q('SELECT DISTINCT session_date FROM study_sessions WHERE user_id = ? AND session_date <= CURDATE() ORDER BY session_date DESC LIMIT 400', [$userId])
            ->fetchAll(PDO::FETCH_COLUMN);
        if (!$dates) return 0;
        $expected = date('Y-m-d');
        if ($dates[0] !== $expected) {
            $expected = date('Y-m-d', strtotime('-1 day'));
            if ($dates[0] !== $expected) return 0;
        }
        $streak = 0;
        foreach ($dates as $d) {
            if ($d !== $expected) break;
            $streak++;
            $expected = date('Y-m-d', strtotime("$expected -1 day"));
        }
        return $streak;
    }

    /**
     * Workload indicator for the current week:
     * hours of work due this week vs hours available this week.
     */
    public static function workload(int $userId): array
    {
        $avail = PlannerService::availability($userId);
        $todayN = (int)date('N');
        $availLeft = 0;
        for ($d = $todayN; $d <= 7; $d++) $availLeft += $avail[$d];
        $need = (float)qv("SELECT COALESCE(SUM(estimated_hours),0) FROM tasks WHERE user_id = ? AND status <> 'done'
                           AND due_at <= DATE_ADD(CURDATE(), INTERVAL ? DAY)", [$userId, 8 - $todayN]);
        $ratio = $availLeft > 0 ? $need / $availLeft : ($need > 0 ? 2 : 0);
        if ($ratio >= 0.9)      $level = ['Heavy', 'danger'];
        elseif ($ratio >= 0.5)  $level = ['Moderate', 'warning'];
        else                    $level = ['Light', 'success'];
        return ['need' => $need, 'available' => $availLeft, 'percent' => (int)min(100, round($ratio * 100)),
                'label' => $level[0], 'tone' => $level[1]];
    }
}
