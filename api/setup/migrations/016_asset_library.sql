-- 016_asset_library.sql — Asset Library: tags and thumbnail support

CREATE TABLE IF NOT EXISTS asset_tags (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deliverable_tags (
    deliverable_id INT UNSIGNED NOT NULL,
    tag_id         INT UNSIGNED NOT NULL,
    PRIMARY KEY (deliverable_id, tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE deliverables ADD COLUMN thumbnail_url VARCHAR(500) DEFAULT NULL;
