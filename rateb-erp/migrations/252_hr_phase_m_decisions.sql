-- HR Phase M — employee decisions (additive only). Disciplinary reuses rateb_hrm_disciplinary_actions.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_hr_decisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    decision_no VARCHAR(40) NOT NULL,
    decision_type VARCHAR(40) NOT NULL,
    effective_date DATE NULL,
    reason TEXT NULL,
    payload_json JSON NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    linked_promotion_id INT UNSIGNED NULL,
    linked_transfer_id INT UNSIGNED NULL,
    linked_disciplinary_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejected_by INT UNSIGNED NULL,
    rejected_at DATETIME NULL,
    executed_by INT UNSIGNED NULL,
    executed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hr_decision_no (company_id, decision_no),
    INDEX idx_hr_decision_company_status (company_id, status),
    INDEX idx_hr_decision_employee (company_id, employee_id),
    INDEX idx_hr_decision_type (company_id, decision_type),
    CONSTRAINT fk_hr_decision_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Soft-link disciplinary rows to ops Employee Master (profile remains required SoT for HRMS table).
SET @col = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_hrm_disciplinary_actions'
      AND COLUMN_NAME = 'legacy_employee_id'
);
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_hrm_disciplinary_actions
        ADD COLUMN legacy_employee_id INT UNSIGNED NULL AFTER employee_profile_id,
        ADD INDEX idx_hrm_disc_legacy_emp (company_id, legacy_employee_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
