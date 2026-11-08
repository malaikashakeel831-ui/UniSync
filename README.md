# UniSync — Smart University Life Organizer

A secure, multi-user web app that keeps a student's whole semester in one dashboard: courses, timetable, deadlines, GPA/CGPA, a smart study planner, group projects (Kanban) and analytics.

**Team:** Syeda Laraib (BCSF24A002) · Khadija Shakeel (BCSF24A034)
**Stack:** PHP 8 · MySQL 8 · HTML5 · CSS3 · JavaScript (ES6) · Chart.js · Apache on Linux

## Features
| Module | What it does |
|---|---|
| Authentication | Register/login, `password_hash`, session regeneration, 5-attempt lockout (15 min), CSRF on every POST, profile & password change |
| Dashboard | GPA/CGPA, pending/completed/overdue counts, 7-day deadlines, workload meter, study streak, today's classes, notifications bell |
| Semesters & Courses | CRUD, current semester, credit hours, instructor, colour per course |
| Tasks & Deadlines | Assignment/quiz/exam/project, priority, estimated hours, filters, sort, one-click AJAX completion, overdue highlighting |
| Timetable & Calendar | Weekly class grid + month calendar with "heavy day" (2+ deadlines) marking |
| GPA Planner | Weighted assessments → %, letter, points; semester GPA; CGPA; live what-if; marks needed for a target grade |
| Smart Study Planner | Priority score = urgency + importance (+2 exams), fills free hours in ≤2 h blocks, overload warnings, log sessions |
| Group Projects | Invite by email, drag-and-drop Kanban (To Do / Doing / Done), assignment, progress bar, members-only access |
| Analytics | Chart.js doughnut, bar, line charts; on-time completion rate |
| Notifications | Due in 24 h, overdue, new group assignment (no cron needed) |
| Admin | Totals, sign-ups chart, audit log, activate/deactivate/unlock users — **no access to students' academic data** |

## Quick start (Ubuntu / WSL Ubuntu, LAMP: Linux + Apache + MySQL + PHP)
```bash
sudo apt update && sudo apt install -y apache2 mysql-server php libapache2-mod-php php-mysql php-mbstring git
sudo mysql < database/create_database.sql
mysql -u unisync -p'UniSync@2026' unisync < database/schema.sql
mysql -u unisync -p'UniSync@2026' unisync < database/seed.sql
cp app/config.local.example.php app/config.local.php
php database/demo_seed.php          # optional demo data
php -S localhost:8000 -t public     # quick test, or configure Apache (docs/GUIDE.md)
```

**Logins:** admin `admin@unisync.local` / `Admin@123` · demo students `laraib@demo.com`, `khadija@demo.com` / `Demo@1234`

## Folder structure
```
unisync/
├── public/            ← the ONLY web-accessible folder (Apache DocumentRoot)
│   ├── *.php          pages (dashboard, tasks, gpa, planner, groups …)
│   ├── admin/         admin dashboard & user management
│   ├── api/           JSON endpoints used by fetch() (AJAX)
│   └── assets/        css, js, img, vendor (Chart.js, icons, font — works offline)
├── app/               config, db, auth, csrf, helpers, services/, views/  (blocked from web)
├── database/          create_database.sql, schema.sql, seed.sql, demo_seed.php
└── docs/GUIDE.md      full setup, GitHub team workflow and deployment guide
```

## Security
Prepared statements (emulation off) · output escaping with `e()` · CSRF tokens (`hash_equals`) · HttpOnly + SameSite cookies · `session_regenerate_id(true)` · lockout · every private query uses `WHERE id = ? AND user_id = ?` with the id from the session · POST-only destructive actions · `is_active` checked on each request · admin created only via SQL seed.

Full documentation: [`docs/GUIDE.md`](docs/GUIDE.md)
