CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_type  ENUM('admin','client') NOT NULL,
    user_id    INT NOT NULL,
    type       VARCHAR(50) NOT NULL DEFAULT 'system',
    title      VARCHAR(200) NOT NULL,
    message    TEXT,
    link       VARCHAR(500),
    is_read    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_user (user_type, user_id),
    INDEX idx_notif_unread (user_type, user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
