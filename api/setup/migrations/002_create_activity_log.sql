CREATE TABLE IF NOT EXISTS activity_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    user_type   ENUM('admin','client','system') NOT NULL DEFAULT 'system',
    user_id     INT,
    action      VARCHAR(50) NOT NULL,
    description VARCHAR(500) NOT NULL,
    metadata    JSON,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actlog_project (project_id),
    INDEX idx_actlog_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
