-- Durable cross-DB link for agency ↔ platform support tickets (replaces message-marker as SoT).
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_support_tickets' AND COLUMN_NAME = 'source_agency_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_support_tickets ADD COLUMN source_agency_id INT UNSIGNED NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_support_tickets' AND COLUMN_NAME = 'source_ticket_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_support_tickets ADD COLUMN source_ticket_id INT UNSIGNED NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_support_tickets' AND INDEX_NAME = 'idx_support_source_agency_ticket');
SET @sql = IF(@idx = 0, 'ALTER TABLE rateb_support_tickets ADD INDEX idx_support_source_agency_ticket (source_agency_id, source_ticket_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
