-- 011_analytics.sql — Financial analytics tables for Admin Dashboard PRO

CREATE TABLE IF NOT EXISTS revenue_snapshots (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    month        CHAR(7) NOT NULL,
    mrr          DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_revenue DECIMAL(14,2) NOT NULL DEFAULT 0,
    new_clients  INT NOT NULL DEFAULT 0,
    churned      INT NOT NULL DEFAULT 0,
    total_active INT NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_month (month)
);

CREATE TABLE IF NOT EXISTS churn_events (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED NOT NULL,
    churned_at  DATE NOT NULL,
    reason      VARCHAR(255),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
