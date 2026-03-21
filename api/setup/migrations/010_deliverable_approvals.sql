CREATE TABLE IF NOT EXISTS deliverable_approvals (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deliverable_id INT UNSIGNED NOT NULL,
    client_id      INT UNSIGNED NOT NULL,
    status         ENUM('pending','approved','changes_requested') NOT NULL DEFAULT 'pending',
    comment        TEXT,
    reviewed_at    DATETIME,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_deliverable_client (deliverable_id, client_id),
    INDEX idx_approval_status (status),
    INDEX idx_approval_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
