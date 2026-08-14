-- Phase R — Saudi HR Compliance readiness (GOSI/WPS local only).
-- NO external connectors. external_sent remains 0.

-- Extend Phase P employment fields (additive columns).
SET @db := DATABASE();

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'rateb_hr_saudi_employment_fields' AND COLUMN_NAME = 'employment_type');
SET @sql := IF(@col = 0, 'ALTER TABLE rateb_hr_saudi_employment_fields ADD COLUMN employment_type VARCHAR(32) NULL AFTER mol_contract_number', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'rateb_hr_saudi_employment_fields' AND COLUMN_NAME = 'saudi_classification');
SET @sql := IF(@col = 0, 'ALTER TABLE rateb_hr_saudi_employment_fields ADD COLUMN saudi_classification VARCHAR(16) NULL AFTER employment_type', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'rateb_hr_saudi_employment_fields' AND COLUMN_NAME = 'gosi_eligible');
SET @sql := IF(@col = 0, 'ALTER TABLE rateb_hr_saudi_employment_fields ADD COLUMN gosi_eligible TINYINT(1) NULL AFTER saudi_classification', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'rateb_hr_saudi_employment_fields' AND COLUMN_NAME = 'housing_allowance');
SET @sql := IF(@col = 0, 'ALTER TABLE rateb_hr_saudi_employment_fields ADD COLUMN housing_allowance DECIMAL(12,2) NULL DEFAULT NULL AFTER gosi_eligible', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'rateb_hr_saudi_employment_fields' AND COLUMN_NAME = 'transport_allowance');
SET @sql := IF(@col = 0, 'ALTER TABLE rateb_hr_saudi_employment_fields ADD COLUMN transport_allowance DECIMAL(12,2) NULL DEFAULT NULL AFTER housing_allowance', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'rateb_hr_saudi_employment_fields' AND COLUMN_NAME = 'other_gosi_allowances');
SET @sql := IF(@col = 0, 'ALTER TABLE rateb_hr_saudi_employment_fields ADD COLUMN other_gosi_allowances DECIMAL(12,2) NULL DEFAULT NULL AFTER transport_allowance', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'rateb_hr_saudi_employment_fields' AND COLUMN_NAME = 'bank_name');
SET @sql := IF(@col = 0, 'ALTER TABLE rateb_hr_saudi_employment_fields ADD COLUMN bank_name VARCHAR(120) NULL AFTER wps_bank_code', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_hr_gosi_period_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  period_year SMALLINT UNSIGNED NOT NULL,
  period_month TINYINT UNSIGNED NOT NULL,
  payroll_period_id INT UNSIGNED NULL,
  employee_id INT UNSIGNED NOT NULL,
  saudi_classification VARCHAR(16) NULL,
  contribution_base DECIMAL(12,2) NOT NULL DEFAULT 0,
  employee_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
  employer_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
  employee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  employer_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  eligible TINYINT(1) NOT NULL DEFAULT 0,
  validation_status VARCHAR(32) NOT NULL DEFAULT 'ok',
  validation_notes VARCHAR(500) NULL,
  external_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hr_gosi_period_emp (company_id, period_year, period_month, employee_id),
  KEY idx_hr_gosi_company_period (company_id, period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_wps_export_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  period_year SMALLINT UNSIGNED NOT NULL,
  period_month TINYINT UNSIGNED NOT NULL,
  payroll_period_id INT UNSIGNED NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ready_local',
  line_count INT UNSIGNED NOT NULL DEFAULT 0,
  ready_count INT UNSIGNED NOT NULL DEFAULT 0,
  exception_count INT UNSIGNED NOT NULL DEFAULT 0,
  external_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_hr_wps_batch_company (company_id, period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_wps_export_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_id BIGINT UNSIGNED NOT NULL,
  company_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  employee_code VARCHAR(40) NULL,
  employee_name VARCHAR(150) NULL,
  national_id VARCHAR(40) NULL,
  iban VARCHAR(64) NULL,
  bank_code VARCHAR(32) NULL,
  basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
  deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  ready TINYINT(1) NOT NULL DEFAULT 0,
  validation_status VARCHAR(32) NOT NULL DEFAULT 'ok',
  validation_notes VARCHAR(500) NULL,
  external_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_hr_wps_line_batch (batch_id),
  KEY idx_hr_wps_line_company (company_id, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
