-- HR Phase O — succession planning (additive only). Org/analytics reuse existing tables.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_hr_critical_positions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL,
    department_id INT UNSIGNED NULL,
    job_title_id INT UNSIGNED NULL,
    current_employee_id INT UNSIGNED NULL,
    is_critical TINYINT(1) NOT NULL DEFAULT 1,
    skill_gap_notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hr_crit_pos_code (company_id, code),
    INDEX idx_hr_crit_pos_company (company_id, status),
    INDEX idx_hr_crit_pos_dept (company_id, department_id),
    INDEX idx_hr_crit_pos_emp (company_id, current_employee_id),
    CONSTRAINT fk_hr_crit_pos_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_succession_candidates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    critical_position_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    readiness VARCHAR(20) NOT NULL DEFAULT 'developing',
    rank_order INT UNSIGNED NOT NULL DEFAULT 1,
    skill_gap_notes TEXT NULL,
    notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hr_succ_cand (company_id, critical_position_id, employee_id),
    INDEX idx_hr_succ_pos (company_id, critical_position_id),
    INDEX idx_hr_succ_emp (company_id, employee_id),
    CONSTRAINT fk_hr_succ_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_hr_succ_position FOREIGN KEY (critical_position_id) REFERENCES rateb_hr_critical_positions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
