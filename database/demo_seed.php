<?php
/**
 * Fills the database with realistic DEMO data for the presentation.
 * Dates are relative to "today", so the demo always looks fresh.
 *
 *   php database/demo_seed.php
 *
 * Creates two students (password for both: Demo@1234):
 *   laraib@demo.com   (Syeda Laraib, BCSF24A002)
 *   khadija@demo.com  (Khadija Shakeel, BCSF24A034)
 * Running it again first deletes these two demo accounts and recreates them.
 */
if (PHP_SAPI !== 'cli') exit("Run from the terminal: php database/demo_seed.php\n");

$config = require __DIR__ . '/../app/config.php';
$GLOBALS['config'] = $config;
date_default_timezone_set($config['timezone']);
require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/services/GpaService.php';
require __DIR__ . '/../app/services/PlannerService.php';
require __DIR__ . '/../app/services/AnalyticsService.php';
require __DIR__ . '/../app/services/NotificationService.php';

$d  = fn(string $rel, string $fmt = 'Y-m-d') => date($fmt, strtotime($rel));
$dt = fn(string $rel) => date('Y-m-d H:i:s', strtotime($rel));

q("DELETE FROM project_groups WHERE owner_id IN (SELECT id FROM users WHERE email IN ('laraib@demo.com','khadija@demo.com'))");
q("DELETE FROM users WHERE email IN ('laraib@demo.com','khadija@demo.com')");

$hash = password_hash('Demo@1234', PASSWORD_DEFAULT);
q("INSERT INTO users (full_name, email, student_code, password_hash, role, created_at, last_login) VALUES (?,?,?,?, 'student', ?, NOW())",
    ['Syeda Laraib', 'laraib@demo.com', 'BCSF24A002', $hash, $dt('-60 days')]);
$laraib = (int)db()->lastInsertId();
q("INSERT INTO users (full_name, email, student_code, password_hash, role, created_at, last_login) VALUES (?,?,?,?, 'student', ?, NOW())",
    ['Khadija Shakeel', 'khadija@demo.com', 'BCSF24A034', $hash, $dt('-58 days')]);
$khadija = (int)db()->lastInsertId();

function seedStudent(int $uid, $d, $dt): array
{
    // previous semester with final grades
    q('INSERT INTO semesters (user_id, name, start_date, end_date, is_current) VALUES (?,?,?,?,0)', [$uid, 'Spring 2026', '2026-02-02', '2026-06-15']);
    $s1 = (int)db()->lastInsertId();
    foreach ([['CS-201', 'Data Structures', 4, 'A-', '#0ea5e9'], ['CS-205', 'Discrete Mathematics', 3, 'B+', '#a855f7'], ['HU-110', 'Communication Skills', 2, 'A', '#14b8a6'], ['MT-210', 'Linear Algebra', 3, 'B', '#f59e0b']] as $c) {
        q('INSERT INTO courses (user_id, semester_id, code, title, credit_hours, instructor, color, final_grade_letter) VALUES (?,?,?,?,?,?,?,?)',
            [$uid, $s1, $c[0], $c[1], $c[2], 'Faculty', $c[4], $c[3]]);
    }
    // current semester
    q('INSERT INTO semesters (user_id, name, start_date, end_date, is_current) VALUES (?,?,?,?,1)', [$uid, 'Fall 2026', '2026-09-07', '2027-01-15']);
    $s2 = (int)db()->lastInsertId();
    $courses = [];
    foreach ([
        ['CS-301', 'Database Systems', 3, 'Dr. Ahmed Raza', '#5b5bd6'],
        ['CS-310', 'Web Engineering', 3, 'Ms. Fatima Noor', '#ff8a4c'],
        ['CS-320', 'Operating Systems', 3, 'Dr. Bilal Khan', '#10b981'],
        ['ST-201', 'Probability & Statistics', 3, 'Dr. Sana Iqbal', '#ef4444'],
        ['HU-220', 'Technical Writing', 2, 'Mr. Usman Ali', '#0ea5e9'],
    ] as $c) {
        q('INSERT INTO courses (user_id, semester_id, code, title, credit_hours, instructor, color) VALUES (?,?,?,?,?,?,?)', [$uid, $s2, $c[0], $c[1], $c[2], $c[3], $c[4]]);
        $courses[$c[0]] = (int)db()->lastInsertId();
    }
    // timetable
    foreach ([
        ['CS-301', 1, '08:30', '10:00', 'Room 204'], ['CS-301', 3, '08:30', '10:00', 'Room 204'],
        ['CS-310', 1, '10:30', '12:00', 'Lab 3'], ['CS-310', 4, '10:30', '12:00', 'Lab 3'],
        ['CS-320', 2, '09:00', '10:30', 'Room 108'], ['CS-320', 4, '13:00', '14:30', 'Room 108'],
        ['ST-201', 2, '11:00', '12:30', 'Room 301'], ['ST-201', 5, '09:00', '10:30', 'Room 301'],
        ['HU-220', 3, '14:00', '15:30', 'Room 112'],
    ] as $t) {
        q('INSERT INTO timetable_slots (user_id, course_id, day_of_week, start_time, end_time, room) VALUES (?,?,?,?,?,?)', [$uid, $courses[$t[0]], $t[1], $t[2], $t[3], $t[4]]);
    }
    // assessments (marks)
    $marks = [
        'CS-301' => [['Quiz 1', 5, 9, 10], ['Quiz 2', 5, 8, 10], ['Assignment 1', 10, 18, 20], ['Midterm', 30, 24, 30], ['Final', 50, null, 50]],
        'CS-310' => [['Assignment 1', 10, 9, 10], ['Project Phase 1', 15, 13, 15], ['Midterm', 25, 20, 25], ['Final Project', 50, null, 100]],
        'CS-320' => [['Quiz 1', 10, 6, 10], ['Midterm', 30, 21, 30], ['Final', 60, null, 60]],
        'ST-201' => [['Quiz 1', 10, 7, 10], ['Assignment', 10, 9, 10], ['Midterm', 30, 19, 30], ['Final', 50, null, 50]],
        'HU-220' => [['Essay', 20, 17, 20], ['Presentation', 20, 18, 20], ['Final', 60, null, 60]],
    ];
    foreach ($marks as $code => $list) foreach ($list as $a) {
        q('INSERT INTO assessments (user_id, course_id, name, weight_percent, marks_obtained, total_marks) VALUES (?,?,?,?,?,?)', [$uid, $courses[$code], $a[0], $a[1], $a[2], $a[3]]);
    }
    // tasks
    $tasks = [
        ['CS-301', 'ER Diagram & Normalisation — Assignment 2', 'assignment', '+1 day 23:59', 'high', 4, 'pending'],
        ['ST-201', 'Quiz 3 — Hypothesis testing', 'quiz', '+2 days 09:00', 'medium', 2, 'pending'],
        ['CS-320', 'Midterm revision: scheduling & deadlocks', 'exam', '+4 days 13:00', 'high', 6, 'in_progress'],
        ['CS-310', 'Semester project — Phase 2 (backend)', 'project', '+6 days 23:59', 'high', 8, 'pending'],
        ['HU-220', 'Research summary (800 words)', 'assignment', '+9 days 17:00', 'low', 3, 'pending'],
        ['CS-301', 'SQL Lab task 4', 'assignment', '-1 day 17:00', 'medium', 2, 'pending'],
        ['CS-310', 'Assignment 1 — Responsive layout', 'assignment', '-12 days 23:59', 'medium', 3, 'done', '-13 days'],
        ['CS-301', 'Quiz 2 preparation', 'quiz', '-9 days 09:00', 'medium', 2, 'done', '-10 days'],
        ['ST-201', 'Assignment — Probability problems', 'assignment', '-6 days 23:59', 'high', 3, 'done', '-5 days'],
        ['CS-320', 'Lab 3 — Process creation in C', 'assignment', '-4 days 23:59', 'medium', 2, 'done', '-4 days 20:00'],
        ['HU-220', 'Presentation slides', 'project', '-3 days 10:00', 'medium', 3, 'done', '-3 days 08:00'],
    ];
    $taskIds = [];
    foreach ($tasks as $t) {
        q('INSERT INTO tasks (user_id, course_id, title, type, due_at, priority, estimated_hours, status, completed_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$uid, $courses[$t[0]], $t[1], $t[2], $dt($t[3]), $t[4], $t[5], $t[6], isset($t[7]) ? $dt($t[7]) : null]);
        $taskIds[] = (int)db()->lastInsertId();
    }
    // availability
    foreach ([1 => 3, 2 => 2.5, 3 => 3, 4 => 2, 5 => 2, 6 => 4, 7 => 3] as $day => $h) {
        q('INSERT INTO study_availability (user_id, day_of_week, hours) VALUES (?,?,?)', [$uid, $day, $h]);
    }
    // study sessions over the past 7 weeks (+ a streak ending today)
    $codes = array_values($courses);
    for ($i = 48; $i >= 0; $i--) {
        if ($i > 6 && mt_rand(0, 100) < 40) continue;
        $mins = [45, 60, 90, 120, 75][mt_rand(0, 4)];
        q('INSERT INTO study_sessions (user_id, course_id, session_date, duration_minutes, note) VALUES (?,?,?,?,?)',
            [$uid, $codes[mt_rand(0, count($codes) - 1)], $d("-$i days"), $mins, 'Self study']);
    }
    q('INSERT INTO study_sessions (user_id, course_id, task_id, session_date, duration_minutes, note) VALUES (?,?,?,?,?,?)',
        [$uid, $courses['CS-320'], $taskIds[2], $d('-1 day'), 120, 'Deadlock chapter']);
    return $courses;
}

mt_srand(42);
seedStudent($laraib, $d, $dt);
seedStudent($khadija, $d, $dt);

// shared group project
q('INSERT INTO project_groups (name, description, owner_id, deadline) VALUES (?,?,?,?)',
    ['UniSync — Web Engineering Project', 'Semester project: a smart university life organizer built with PHP, MySQL, HTML, CSS and JavaScript.', $laraib, $d('+8 days')]);
$g = (int)db()->lastInsertId();
q("INSERT INTO group_members (group_id, user_id, member_role) VALUES (?, ?, 'leader'), (?, ?, 'member')", [$g, $laraib, $g, $khadija]);
foreach ([
    ['Database schema & ER diagram', $laraib, '-5 days', 'done'],
    ['Authentication & security', $laraib, '-2 days', 'done'],
    ['Landing page & UI design', $khadija, '-1 day', 'done'],
    ['GPA planner & what-if calculator', $khadija, '+2 days', 'doing'],
    ['Study planner algorithm', $khadija, '+3 days', 'doing'],
    ['Admin dashboard', $laraib, '+4 days', 'todo'],
    ['Testing & bug fixing', $laraib, '+6 days', 'todo'],
    ['Final report & presentation slides', null, '+7 days', 'todo'],
] as $t) {
    q('INSERT INTO project_tasks (group_id, title, assigned_to, due_date, status, created_by) VALUES (?,?,?,?,?,?)', [$g, $t[0], $t[1], $d($t[2]), $t[3], $laraib]);
}

// a few extra demo sign-ups for the admin chart
foreach ([['Ali Hassan', 'ali@demo.com', 'BCSF24A011', '-2 days'], ['Ayesha Malik', 'ayesha@demo.com', 'BCSF24A019', '-5 days'], ['Hamza Tariq', 'hamza@demo.com', 'BCSF24A027', '-9 days']] as $u) {
    q('DELETE FROM users WHERE email = ?', [$u[1]]);
    q("INSERT INTO users (full_name, email, student_code, password_hash, role, created_at) VALUES (?,?,?,?, 'student', ?)", [$u[0], $u[1], $u[2], $hash, $dt($u[3])]);
}

NotificationService::generate($laraib);
NotificationService::generate($khadija);
NotificationService::add($khadija, '👥 Syeda Laraib added you to the group "UniSync — Web Engineering Project".', 'group_view.php?id=' . $g, false);

echo "Demo data created.\n  laraib@demo.com  / Demo@1234\n  khadija@demo.com / Demo@1234\n  admin@unisync.local / Admin@123\n";
