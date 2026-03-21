-- 013_content_briefs.sql — Creative brief linked to each project
CREATE TABLE IF NOT EXISTS content_briefs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id      INT UNSIGNED NOT NULL UNIQUE,
    objective       TEXT,
    target_audience TEXT,
    tone            VARCHAR(100),
    key_messages    TEXT,
    references_urls TEXT,
    filming_date    DATE,
    location        VARCHAR(255),
    talent_notes    TEXT,
    special_reqs    TEXT,
    status          ENUM('draft','submitted','approved') NOT NULL DEFAULT 'draft',
    submitted_at    DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
