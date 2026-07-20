-- Phase C ESS hardening — composite indexes for employee-scoped list queries (idempotent)

SET NAMES utf8mb4;

-- rateb_hr_employee_requests (company_id, employee_id, status, id)
SET @idx = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_hr_employee_requests'
      AND INDEX_NAME = 'idx_hr_emp_req_ess_list'
);
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_hr_employee_requests ADD INDEX idx_hr_emp_req_ess_list (company_id, employee_id, status, id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_leave_requests (company_id, employee_id, status, id)
SET @idx = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_leave_requests'
      AND INDEX_NAME = 'idx_leave_req_ess_list'
);
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_leave_requests ADD INDEX idx_leave_req_ess_list (company_id, employee_id, status, id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Supporting ESS notification visibility lookups
SET @idx = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_notifications'
      AND INDEX_NAME = 'idx_notif_ess_visible'
);
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_notifications ADD INDEX idx_notif_ess_visible (company_id, user_id, id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
