-- RATEB ERP — one-shot production catch-up (idempotent): suppliers + evaluations + inventory link
-- Safe to run multiple times in phpMyAdmin or via MigrationService.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_supplier_classifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    color VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sc_company_slug (company_id, slug),
    CONSTRAINT fk_sc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_suppliers' AND COLUMN_NAME = 'classification_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_suppliers ADD COLUMN classification_id INT UNSIGNED NULL AFTER rating',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_suppliers' AND COLUMN_NAME = 'performance_kpi');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_suppliers ADD COLUMN performance_kpi DECIMAL(5,2) NULL AFTER classification_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'category_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN category_id INT UNSIGNED NULL AFTER category',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'evaluation_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN evaluation_no VARCHAR(20) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND INDEX_NAME = 'uq_se_company_eval_no');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD UNIQUE KEY uq_se_company_eval_no (company_id, evaluation_no)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'score_percent');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN score_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER overall_score',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'rating_tier');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN rating_tier VARCHAR(20) NULL AFTER score_percent',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'evaluator_name');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN evaluator_name VARCHAR(150) NULL AFTER evaluated_by',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'period_start');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN period_start DATE NULL AFTER evaluation_date',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'period_end');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN period_end DATE NULL AFTER period_start',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE rateb_supplier_evaluations
SET score_percent = ROUND(overall_score * 10, 1)
WHERE score_percent = 0 AND overall_score > 0;

UPDATE rateb_supplier_evaluations
SET rating_tier = CASE
    WHEN overall_score >= 9 THEN 'excellent'
    WHEN overall_score >= 7.5 THEN 'very_good'
    WHEN overall_score >= 5 THEN 'good'
    ELSE 'weak'
END
WHERE rating_tier IS NULL OR rating_tier = '';

UPDATE rateb_supplier_evaluations
SET manager_approval = 'approved'
WHERE manager_approval = 'pending' AND status = 'published';
