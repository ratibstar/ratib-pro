-- HR Phase K — HireBridge link + employment contracts (additive only)
-- Does NOT alter commercial rateb_contracts / eProc / recruitment_contracts ownership.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Idempotent HireBridge link: one employee per recruitment candidate (NULLs allowed).
SET @col = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_employees'
      AND COLUMN_NAME = 'recruitment_candidate_id'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_employees ADD COLUMN recruitment_candidate_id INT UNSIGNED NULL, ADD UNIQUE KEY uq_emp_recruitment_candidate (company_id, recruitment_candidate_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_hr_employment_contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    contract_no VARCHAR(40) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    salary DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','active','expired','terminated') NOT NULL DEFAULT 'draft',
    alert_days INT UNSIGNED NOT NULL DEFAULT 30,
    recruitment_candidate_id INT UNSIGNED NULL,
    recruitment_contract_id INT UNSIGNED NULL,
    notes TEXT NULL,
    activated_at DATETIME NULL,
    terminated_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hr_emp_contract_no (company_id, contract_no),
    INDEX idx_hr_emp_contract_employee (company_id, employee_id, status),
    INDEX idx_hr_emp_contract_expiry (company_id, status, end_date),
    CONSTRAINT fk_hr_emp_contract_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_hr_emp_contract_employee FOREIGN KEY (employee_id) REFERENCES rateb_employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
