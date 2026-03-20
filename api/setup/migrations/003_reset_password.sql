-- 003_reset_password.sql
-- Add password reset token fields to clients and admins

ALTER TABLE clients ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL;
ALTER TABLE clients ADD COLUMN reset_expires DATETIME DEFAULT NULL;

ALTER TABLE admins ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL;
ALTER TABLE admins ADD COLUMN reset_expires DATETIME DEFAULT NULL;
