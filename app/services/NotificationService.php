<?php
/**
 * In-app notifications (Section 7.4).
 * Generated on login / dashboard load, so no cron job is needed.
 */
class NotificationService
{
    public static function add(int $userId, string $message, ?string $link = null, bool $dedupe = true): void
    {
        $message = mb_substr($message, 0, 255);
        if ($dedupe && qv('SELECT 1 FROM notifications WHERE user_id = ? AND message = ? LIMIT 1', [$userId, $message])) {
            return;
        }
        q('INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)', [$userId, $message, $link]);
    }

    /** Check deadlines and create reminders (duplicates are skipped). */
    public static function generate(int $userId): void
    {
        $soon = qa("SELECT id, title, type, due_at FROM tasks WHERE user_id = ? AND status <> 'done'
                    AND due_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)", [$userId]);
        foreach ($soon as $t) {
            self::add($userId, sprintf('⏰ %s "%s" is due within 24 hours (%s).', ucfirst($t['type']), $t['title'], date('D g:i A', strtotime($t['due_at']))),
                'tasks.php?highlight=' . $t['id']);
        }
        $overdue = qa("SELECT id, title, type, due_at FROM tasks WHERE user_id = ? AND status <> 'done' AND due_at < NOW()", [$userId]);
        foreach ($overdue as $t) {
            self::add($userId, sprintf('⚠️ %s "%s" is overdue (was due %s).', ucfirst($t['type']), $t['title'], date('d M', strtotime($t['due_at']))),
                'tasks.php?highlight=' . $t['id']);
        }
        $groupDue = qa("SELECT pt.id, pt.title, pt.group_id, g.name FROM project_tasks pt JOIN project_groups g ON g.id = pt.group_id
                        WHERE pt.assigned_to = ? AND pt.status <> 'done' AND pt.due_date IS NOT NULL AND pt.due_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)", [$userId]);
        foreach ($groupDue as $t) {
            self::add($userId, sprintf('👥 Group task "%s" in %s is due soon.', $t['title'], $t['name']), 'group_view.php?id=' . $t['group_id']);
        }
    }

    public static function unreadCount(int $userId): int
    {
        return (int)qv('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public static function latest(int $userId, int $limit = 8): array
    {
        return qa('SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC, id DESC LIMIT ' . (int)$limit, [$userId]);
    }
}
