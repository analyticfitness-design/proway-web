CREATE TABLE IF NOT EXISTS surveys (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id      INT UNSIGNED NOT NULL,
    project_id     INT UNSIGNED NOT NULL,
    deliverable_id INT UNSIGNED,
    type           ENUM('nps','csat') NOT NULL DEFAULT 'nps',
    score          TINYINT UNSIGNED,
    comment        TEXT,
    sent_at        DATETIME,
    responded_at   DATETIME,
    status         ENUM('pending','sent','responded','expired') NOT NULL DEFAULT 'pending',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_surveys_client (client_id, status)
);
