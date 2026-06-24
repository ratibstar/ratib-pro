-- RATIB Contact Center — link agents, queues, calls to RATIB ERP branch_id
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rcc_agents' AND COLUMN_NAME = 'erp_branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rcc_agents ADD COLUMN erp_branch_id INT UNSIGNED NULL AFTER tenant_id, ADD INDEX idx_rcc_agents_branch (tenant_id, erp_branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rcc_queues' AND COLUMN_NAME = 'erp_branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rcc_queues ADD COLUMN erp_branch_id INT UNSIGNED NULL AFTER tenant_id, ADD INDEX idx_rcc_queues_branch (tenant_id, erp_branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rcc_calls' AND COLUMN_NAME = 'erp_branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rcc_calls ADD COLUMN erp_branch_id INT UNSIGNED NULL AFTER tenant_id, ADD INDEX idx_rcc_calls_branch (tenant_id, erp_branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rcc_tickets' AND COLUMN_NAME = 'erp_branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rcc_tickets ADD COLUMN erp_branch_id INT UNSIGNED NULL AFTER tenant_id, ADD INDEX idx_rcc_tickets_branch (tenant_id, erp_branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rcc_conversations' AND COLUMN_NAME = 'erp_branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rcc_conversations ADD COLUMN erp_branch_id INT UNSIGNED NULL AFTER tenant_id, ADD INDEX idx_rcc_conv_branch (tenant_id, erp_branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
