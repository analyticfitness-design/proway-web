-- Migration 009: WhatsApp Business API notification preferences
-- Created: 2026-03-20

ALTER TABLE clients ADD COLUMN IF NOT EXISTS wa_phone VARCHAR(20) DEFAULT NULL;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS wa_notifications TINYINT DEFAULT 1;
