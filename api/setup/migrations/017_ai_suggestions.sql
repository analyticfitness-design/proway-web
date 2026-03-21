-- 017_ai_suggestions.sql — AI Content Suggestions for fitness LATAM

CREATE TABLE IF NOT EXISTS ai_suggestions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id    INT UNSIGNED NOT NULL,
    prompt_type  VARCHAR(100) NOT NULL DEFAULT 'content_suggestions',
    context_json JSON,
    result_text  MEDIUMTEXT,
    tokens_used  INT NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME,
    INDEX idx_suggestions_client (client_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
