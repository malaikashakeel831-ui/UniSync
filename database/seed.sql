-- =============================================================
-- UniSync seed data: default grading scale + administrator
-- Run AFTER schema.sql
-- =============================================================

-- Default grading scale (user_id = NULL means "system default")
INSERT INTO grading_scale (user_id, letter, min_percent, grade_points) VALUES
 (NULL, 'A',  85, 4.00),
 (NULL, 'A-', 80, 3.67),
 (NULL, 'B+', 75, 3.33),
 (NULL, 'B',  70, 3.00),
 (NULL, 'B-', 65, 2.67),
 (NULL, 'C+', 61, 2.33),
 (NULL, 'C',  58, 2.00),
 (NULL, 'C-', 54, 1.67),
 (NULL, 'D',  50, 1.00),
 (NULL, 'F',   0, 0.00);

-- Administrator account (created ONLY here, never via the register form)
-- Email:    admin@unisync.local
-- Password: Admin@123   <-- change it after first login (Profile page)
INSERT INTO users (full_name, email, student_code, password_hash, role, is_active)
VALUES ('System Administrator', 'admin@unisync.local', NULL,
        '$2y$10$vlcXRtkWeY/7BCvvsgpFle9TzZufem/t4U7dKFMKUjcoh1bZXFEQa', 'admin', 1);
