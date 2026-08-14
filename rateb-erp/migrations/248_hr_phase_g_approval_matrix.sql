-- HR Phase G — Approval matrix config + progress (additive, non-destructive)
-- Domain leave/permission/request tables are NOT altered.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_hr_approval_matrices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    source_key VARCHAR(40) NOT NULL COMMENT 'hr_leave|hr_permission|hr_request',
    request_type VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'empty = all types for source; e.g. salary_certificate',
    name VARCHAR(160) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hr_appr_matrix (company_id, source_key, request_type),
    INDEX idx_hr_appr_matrix_company (company_id, enabled),
    CONSTRAINT fk_hr_appr_matrix_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_approval_matrix_stages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    matrix_id INT UNSIGNED NOT NULL,
    stage_order INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    approver_type ENUM('oversight','user','role') NOT NULL DEFAULT 'oversight',
    approver_reference VARCHAR(80) NULL COMMENT 'user_id or role_id when type is user|role',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hr_appr_stage_order (matrix_id, stage_order),
    UNIQUE KEY uq_hr_appr_stage_code (matrix_id, code),
    INDEX idx_hr_appr_stage_company (company_id, matrix_id),
    CONSTRAINT fk_hr_appr_stage_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_hr_appr_stage_matrix FOREIGN KEY (matrix_id) REFERENCES rateb_hr_approval_matrices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_approval_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    matrix_id INT UNSIGNED NOT NULL,
    matrix_version INT UNSIGNED NOT NULL,
    source_key VARCHAR(40) NOT NULL,
    record_id INT UNSIGNED NOT NULL,
    request_type VARCHAR(80) NOT NULL DEFAULT '',
    current_stage_order INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('in_progress','completed','rejected') NOT NULL DEFAULT 'in_progress',
    stages_snapshot_json JSON NOT NULL COMMENT 'Frozen stages at process start — matrix edits must not alter path',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    last_actor_user_id INT UNSIGNED NULL,
    last_action_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hr_appr_progress_record (company_id, source_key, record_id),
    INDEX idx_hr_appr_progress_matrix (company_id, matrix_id, status),
    CONSTRAINT fk_hr_appr_progress_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_hr_appr_progress_matrix FOREIGN KEY (matrix_id) REFERENCES rateb_hr_approval_matrices(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
