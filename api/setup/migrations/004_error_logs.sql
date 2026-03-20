CREATE TABLE IF NOT EXISTS error_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    level      ENUM('error','warning','info') DEFAULT 'error',
    message    TEXT,
    stack      TEXT,
    url        VARCHAR(500),
    user_agent VARCHAR(500),
    user_id    INT,
    user_type  VARCHAR(20),
    context    JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_errorlog_level (level),
    INDEX idx_errorlog_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
