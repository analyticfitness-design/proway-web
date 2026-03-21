-- 014_content_slots.sql — Content calendar slot planning
CREATE TABLE IF NOT EXISTS content_slots (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id      INT UNSIGNED NOT NULL,
    project_id     INT UNSIGNED,
    scheduled_date DATE NOT NULL,
    content_type   VARCHAR(100) NOT NULL,
    title          VARCHAR(255),
    description    TEXT,
    status         ENUM('planned','in_production','ready','published','cancelled') NOT NULL DEFAULT 'planned',
    platform       VARCHAR(50),
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slots_client_date (client_id, scheduled_date)
);
