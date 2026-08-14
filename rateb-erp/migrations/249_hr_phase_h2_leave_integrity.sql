-- HR Phase H2 — leave attendance ownership + paid snapshot (additive only)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Attendance rows created by leave approval can be reversed safely.
SET @col = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_attendance_records'
      AND COLUMN_NAME = 'leave_request_id'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_attendance_records ADD COLUMN leave_request_id INT UNSIGNED NULL AFTER notes, ADD INDEX idx_att_leave_request (company_id, leave_request_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Snapshot leave_types.paid at approval time (no retroactive type flips on history).
SET @col2 = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_leave_requests'
      AND COLUMN_NAME = 'paid_snapshot'
);
SET @sql2 = IF(@col2 = 0,
    'ALTER TABLE rateb_leave_requests ADD COLUMN paid_snapshot TINYINT(1) NULL AFTER days',
    'SELECT 1');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
