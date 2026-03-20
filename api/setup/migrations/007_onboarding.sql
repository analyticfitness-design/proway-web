-- 007_onboarding.sql — Add onboarding columns to client_profiles
-- The client_profiles table already exists (has id, client_id, password_hash).
-- We add the brand/social/content/onboarding columns.

ALTER TABLE client_profiles
    ADD COLUMN IF NOT EXISTS brand_name      VARCHAR(100)  NULL AFTER password_hash,
    ADD COLUMN IF NOT EXISTS brand_colors     JSON          NULL AFTER brand_name,
    ADD COLUMN IF NOT EXISTS logo_url         VARCHAR(500)  NULL AFTER brand_colors,
    ADD COLUMN IF NOT EXISTS social_accounts  JSON          NULL AFTER logo_url,
    ADD COLUMN IF NOT EXISTS content_prefs    JSON          NULL AFTER social_accounts,
    ADD COLUMN IF NOT EXISTS goals            TEXT          NULL AFTER content_prefs,
    ADD COLUMN IF NOT EXISTS onboarding_done  TINYINT       NOT NULL DEFAULT 0 AFTER goals;
