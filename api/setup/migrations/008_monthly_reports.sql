-- Migration 008: Monthly progress reports
-- Created: 2026-03-20

CREATE TABLE IF NOT EXISTS monthly_reports (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       INT NOT NULL,
    year            SMALLINT NOT NULL,
    month           TINYINT NOT NULL,
    recommendations TEXT,
    pdf_path        VARCHAR(500),
    generated_at    TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_client_period (client_id, year, month)
);
