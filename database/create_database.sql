-- Run once as MySQL root on your Linux machine:
--   sudo mysql < database/create_database.sql
-- Change the password below, and put the same one in app/config.local.php

CREATE DATABASE IF NOT EXISTS unisync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'unisync'@'localhost' IDENTIFIED BY 'UniSync@2026';
GRANT ALL PRIVILEGES ON unisync.* TO 'unisync'@'localhost';
FLUSH PRIVILEGES;
