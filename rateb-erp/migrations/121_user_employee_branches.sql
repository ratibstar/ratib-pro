-- RATEB ERP — user/employee branch links (phase 2)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_user_branches (
    user_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, branch_id),
    INDEX idx_ub_user (user_id),
    INDEX idx_ub_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_employees' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_employees ADD COLUMN branch_id INT UNSIGNED NULL AFTER department_id, ADD INDEX idx_employee_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE rateb_employees e
INNER JOIN rateb_branches b ON b.company_id = e.company_id AND b.is_main = 1
SET e.branch_id = b.id
WHERE e.branch_id IS NULL;

INSERT INTO rateb_user_branches (user_id, branch_id)
SELECT u.id, b.id
FROM rateb_users u
INNER JOIN rateb_branches b ON b.company_id = u.company_id AND b.is_main = 1
WHERE u.company_id IS NOT NULL
  AND u.is_super_admin = 0
  AND NOT EXISTS (
    SELECT 1 FROM rateb_user_branches ub WHERE ub.user_id = u.id LIMIT 1
  );
