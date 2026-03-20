-- Migration 005: Social media metrics tracking tables
-- Created: 2026-03-20

CREATE TABLE IF NOT EXISTS social_profiles (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    client_id       INT NOT NULL,
    platform        ENUM('instagram','tiktok') NOT NULL,
    username        VARCHAR(100) NOT NULL,
    display_name    VARCHAR(200),
    profile_pic_url TEXT,
    followers       INT DEFAULT 0,
    following       INT DEFAULT 0,
    posts_count     INT DEFAULT 0,
    bio             TEXT,
    is_active       TINYINT DEFAULT 1,
    last_synced_at  TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_platform_user (platform, username)
);

CREATE TABLE IF NOT EXISTS social_posts (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    profile_id      INT NOT NULL,
    external_id     VARCHAR(100),
    post_type       ENUM('reel','post','story','video','carousel') DEFAULT 'post',
    caption         TEXT,
    thumbnail_url   VARCHAR(500),
    permalink       VARCHAR(500),
    is_proway       TINYINT DEFAULT 0,
    posted_at       DATETIME,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_external (profile_id, external_id)
);

CREATE TABLE IF NOT EXISTS social_metrics_daily (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    profile_id      INT NOT NULL,
    post_id         INT NULL,
    date            DATE NOT NULL,
    followers       INT,
    likes           INT,
    comments        INT,
    shares          INT,
    views           INT,
    reach           INT,
    engagement_rate DECIMAL(5,2),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_profile_date (profile_id, post_id, date)
);
