-- RATEB ERP — POS reward reversal idempotency (NULL-safe unique key)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_reward_reversals' AND COLUMN_NAME = 'reference_id_key');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_reward_reversals
        ADD COLUMN reference_id_key INT UNSIGNED GENERATED ALWAYS AS (COALESCE(reference_id, 0)) STORED AFTER reference_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_reward_reversals' AND INDEX_NAME = 'uq_pos_reward_rev');
SET @sql = IF(@idx > 0,
    'ALTER TABLE rateb_pos_reward_reversals DROP INDEX uq_pos_reward_rev',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_reward_reversals' AND INDEX_NAME = 'uq_pos_reward_rev_key');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_pos_reward_reversals ADD UNIQUE KEY uq_pos_reward_rev_key (return_order_id, reversal_kind, reference_id_key)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
