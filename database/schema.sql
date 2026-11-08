-- =============================================================
-- UniSync - Smart University Life Organizer
-- Database schema (MySQL 8.0, InnoDB, utf8mb4)
--
-- Local Linux:  mysql -u root -p < database/create_database.sql
--               mysql -u unisync -p unisync < database/schema.sql
-- Hosting:      import this file in phpMyAdmin into your database
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log, password_resets, notifications, project_tasks,
  group_members, project_groups, study_sessions, study_availability,
  assessments, grading_scale, tasks, timetable_slots, courses, semesters, users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  full_name      VARCHAR(100) NOT NULL,
  email          VARCHAR(150) NOT NULL UNIQUE,
  student_code   VARCHAR(30)  UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('student','admin') NOT NULL DEFAULT 'student',
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  failed_logins  INT NOT NULL DEFAULT 0,
  locked_until   DATETIME NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login     DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE semesters (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  name        VARCHAR(60) NOT NULL,
  start_date  DATE NULL,
  end_date    DATE NULL,
  is_current  TINYINT(1) NOT NULL DEFAULT 0,
  INDEX (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE courses (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  user_id             INT NOT NULL,
  semester_id         INT NOT NULL,
  code                VARCHAR(20)  NOT NULL,
  title               VARCHAR(120) NOT NULL,
  credit_hours        DECIMAL(3,1) NOT NULL,
  instructor          VARCHAR(100),
  color               VARCHAR(7) DEFAULT '#5b5bd6',
  final_grade_letter  VARCHAR(3) NULL,
  INDEX (user_id),
  FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE timetable_slots (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  course_id    INT NOT NULL,
  day_of_week  TINYINT NOT NULL,          -- 1 = Monday ... 7 = Sunday
  start_time   TIME NOT NULL,
  end_time     TIME NOT NULL,
  room         VARCHAR(40),
  INDEX (user_id, day_of_week),
  FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL,
  course_id        INT NULL,
  title            VARCHAR(150) NOT NULL,
  type             ENUM('assignment','quiz','exam','project','other') NOT NULL,
  due_at           DATETIME NOT NULL,
  priority         ENUM('low','medium','high') DEFAULT 'medium',
  estimated_hours  DECIMAL(4,1) DEFAULT 1.0,
  status           ENUM('pending','in_progress','done') DEFAULT 'pending',
  completed_at     DATETIME NULL,
  notes            TEXT,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id, due_at),
  FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grading_scale (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NULL,                 -- NULL = system default scale
  letter        VARCHAR(3)   NOT NULL,
  min_percent   DECIMAL(5,2) NOT NULL,
  grade_points  DECIMAL(3,2) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assessments (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  course_id       INT NOT NULL,
  name            VARCHAR(80)  NOT NULL,
  weight_percent  DECIMAL(5,2) NOT NULL,
  marks_obtained  DECIMAL(6,2) NULL,       -- NULL until result is known
  total_marks     DECIMAL(6,2) NOT NULL,
  INDEX (user_id, course_id),
  FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_availability (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  day_of_week  TINYINT NOT NULL,
  hours        DECIMAL(3,1) NOT NULL,
  UNIQUE (user_id, day_of_week),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE study_sessions (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  course_id         INT NULL,
  task_id           INT NULL,
  session_date      DATE NOT NULL,
  duration_minutes  INT NOT NULL,
  note              VARCHAR(200),
  INDEX (user_id, session_date),
  FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
  FOREIGN KEY (task_id)   REFERENCES tasks(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_groups (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  description  TEXT,
  owner_id     INT NOT NULL,
  deadline     DATE NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE group_members (
  group_id     INT NOT NULL,
  user_id      INT NOT NULL,
  member_role  ENUM('leader','member') DEFAULT 'member',
  joined_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, user_id),
  FOREIGN KEY (group_id) REFERENCES project_groups(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_tasks (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  group_id     INT NOT NULL,
  title        VARCHAR(150) NOT NULL,
  assigned_to  INT NULL,
  due_date     DATE NULL,
  status       ENUM('todo','doing','done') DEFAULT 'todo',
  created_by   INT NOT NULL,
  FOREIGN KEY (group_id)    REFERENCES project_groups(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id)          ON DELETE SET NULL,
  FOREIGN KEY (created_by)  REFERENCES users(id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  message     VARCHAR(255) NOT NULL,
  link        VARCHAR(255) NULL,
  is_read     TINYINT(1) DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id, is_read),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NULL,
  action      VARCHAR(100),
  ip          VARCHAR(45),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
