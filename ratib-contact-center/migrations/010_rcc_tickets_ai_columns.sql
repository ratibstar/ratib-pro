-- Upgrade rcc_tickets from 003 stub → AI assistant columns (009 CREATE IF NOT EXISTS is a no-op when 003 ran first)
SET NAMES utf8mb4;

ALTER TABLE rcc_tickets
    ADD COLUMN conversation_id BIGINT UNSIGNED NULL AFTER description,
    ADD COLUMN source VARCHAR(40) NULL DEFAULT 'manual' AFTER status,
    ADD COLUMN auto_created TINYINT(1) NOT NULL DEFAULT 0 AFTER source;

CREATE INDEX idx_rcc_ticket_conv ON rcc_tickets (tenant_id, conversation_id);
