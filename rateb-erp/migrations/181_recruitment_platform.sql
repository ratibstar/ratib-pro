-- RATEB ERP — Phase 15A Enterprise Recruitment Platform (ONLINE)
-- Additive only. Does not alter offline / HR / procurement / POS tables.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Agencies
CREATE TABLE IF NOT EXISTS rateb_recruitment_agencies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    contact_name VARCHAR(150) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    country_code CHAR(2) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_rec_agency_uuid (public_uuid),
    UNIQUE KEY uq_rec_agency_code (company_id, code),
    INDEX idx_rec_agency_company (company_id, deleted_at),
    INDEX idx_rec_agency_branch (company_id, branch_id),
    CONSTRAINT fk_rec_agency_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Candidates (hub entity)
CREATE TABLE IF NOT EXISTS rateb_recruitment_candidates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    agency_id INT UNSIGNED NULL,
    candidate_no VARCHAR(40) NOT NULL,
    full_name VARCHAR(190) NOT NULL,
    full_name_ar VARCHAR(190) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    nationality CHAR(2) NULL,
    gender ENUM('male','female','other','unspecified') NOT NULL DEFAULT 'unspecified',
    date_of_birth DATE NULL,
    national_id VARCHAR(40) NULL,
    job_title_target VARCHAR(150) NULL,
    source VARCHAR(80) NULL,
    recruiter_user_id INT UNSIGNED NULL,
    workflow_status VARCHAR(40) NOT NULL DEFAULT 'draft',
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_rec_cand_uuid (public_uuid),
    UNIQUE KEY uq_rec_cand_no (company_id, candidate_no),
    INDEX idx_rec_cand_company (company_id, deleted_at),
    INDEX idx_rec_cand_status (company_id, workflow_status),
    INDEX idx_rec_cand_agency (agency_id),
    INDEX idx_rec_cand_recruiter (recruiter_user_id),
    INDEX idx_rec_cand_branch (company_id, branch_id),
    CONSTRAINT fk_rec_cand_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_rec_cand_agency FOREIGN KEY (agency_id) REFERENCES rateb_recruitment_agencies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_passports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    passport_no VARCHAR(80) NOT NULL,
    nationality CHAR(2) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    issue_place VARCHAR(120) NULL,
    status ENUM('valid','expired','pending','cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_rec_pass_uuid (public_uuid),
    INDEX idx_rec_pass_cand (company_id, candidate_id, deleted_at),
    CONSTRAINT fk_rec_pass_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_rec_pass_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_visas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    visa_no VARCHAR(80) NULL,
    visa_type VARCHAR(80) NULL,
    destination_country CHAR(2) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    status ENUM('draft','applied','issued','rejected','expired','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_rec_visa_uuid (public_uuid),
    INDEX idx_rec_visa_cand (company_id, candidate_id, deleted_at),
    INDEX idx_rec_visa_status (company_id, status),
    CONSTRAINT fk_rec_visa_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_rec_visa_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_medicals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    clinic_name VARCHAR(190) NULL,
    exam_date DATE NULL,
    result ENUM('pending','fit','unfit','conditional') NOT NULL DEFAULT 'pending',
    expiry_date DATE NULL,
    status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_rec_med_uuid (public_uuid),
    INDEX idx_rec_med_cand (company_id, candidate_id, deleted_at),
    CONSTRAINT fk_rec_med_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_rec_med_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    contract_no VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'SAR',
    status ENUM('draft','pending','signed','cancelled','expired') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_rec_ctr_uuid (public_uuid),
    UNIQUE KEY uq_rec_ctr_no (company_id, contract_no),
    INDEX idx_rec_ctr_cand (company_id, candidate_id, deleted_at),
    CONSTRAINT fk_rec_ctr_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_rec_ctr_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_interviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    candidate_id INT UNSIGNED NOT NULL,
    interviewer_user_id INT UNSIGNED NULL,
    scheduled_at DATETIME NULL,
    location VARCHAR(190) NULL,
    mode ENUM('in_person','phone','video','other') NOT NULL DEFAULT 'in_person',
    result ENUM('pending','passed','failed','no_show','cancelled') NOT NULL DEFAULT 'pending',
    score DECIMAL(5,2) NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_rec_int_uuid (public_uuid),
    INDEX idx_rec_int_cand (company_id, candidate_id, deleted_at),
    INDEX idx_rec_int_sched (company_id, scheduled_at),
    CONSTRAINT fk_rec_int_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_rec_int_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_rec_skill (company_id, name),
    CONSTRAINT fk_rec_skill_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_candidate_skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    level ENUM('basic','intermediate','advanced','expert') NOT NULL DEFAULT 'basic',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rec_cand_skill (candidate_id, skill_id),
    CONSTRAINT fk_rec_cs_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE,
    CONSTRAINT fk_rec_cs_skill FOREIGN KEY (skill_id) REFERENCES rateb_recruitment_skills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_languages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    code VARCHAR(10) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_rec_lang (company_id, name),
    CONSTRAINT fk_rec_lang_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_candidate_languages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    language_id INT UNSIGNED NOT NULL,
    proficiency ENUM('basic','conversational','fluent','native') NOT NULL DEFAULT 'basic',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rec_cand_lang (candidate_id, language_id),
    CONSTRAINT fk_rec_cl_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE,
    CONSTRAINT fk_rec_cl_lang FOREIGN KEY (language_id) REFERENCES rateb_recruitment_languages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_experiences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    employer_name VARCHAR(190) NOT NULL,
    job_title VARCHAR(150) NULL,
    country_code CHAR(2) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    description TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_rec_exp_cand (company_id, candidate_id, deleted_at),
    CONSTRAINT fk_rec_exp_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_educations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    institution VARCHAR(190) NOT NULL,
    degree VARCHAR(120) NULL,
    field_of_study VARCHAR(150) NULL,
    country_code CHAR(2) NULL,
    start_year SMALLINT UNSIGNED NULL,
    end_year SMALLINT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_rec_edu_cand (company_id, candidate_id, deleted_at),
    CONSTRAINT fk_rec_edu_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    visibility ENUM('internal','shared') NOT NULL DEFAULT 'internal',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_rec_notes_cand (company_id, candidate_id, deleted_at),
    CONSTRAINT fk_rec_notes_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    activity_type VARCHAR(64) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NULL,
    meta_json JSON NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rec_act_cand (company_id, candidate_id, created_at),
    CONSTRAINT fk_rec_act_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rec_hist_cand (company_id, candidate_id, created_at),
    CONSTRAINT fk_rec_hist_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_timeline (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NULL,
    related_entity VARCHAR(64) NULL,
    related_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rec_tl_cand (company_id, candidate_id, created_at),
    CONSTRAINT fk_rec_tl_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_recruitment_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    candidate_id INT UNSIGNED NOT NULL,
    assignee_user_id INT UNSIGNED NOT NULL,
    role_label VARCHAR(80) NOT NULL DEFAULT 'recruiter',
    status ENUM('active','completed','revoked') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_rec_asg_uuid (public_uuid),
    INDEX idx_rec_asg_cand (company_id, candidate_id, deleted_at),
    INDEX idx_rec_asg_user (assignee_user_id, status),
    CONSTRAINT fk_rec_asg_cand FOREIGN KEY (candidate_id) REFERENCES rateb_recruitment_candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Recruitment', 'عرض التوظيف', 'recruitment.view', 'recruitment', 'View recruitment records', 'عرض سجلات التوظيف'),
('Create Recruitment', 'إنشاء توظيف', 'recruitment.create', 'recruitment', 'Create candidates and related records', 'إنشاء المرشحين والسجلات المرتبطة'),
('Update Recruitment', 'تحديث التوظيف', 'recruitment.update', 'recruitment', 'Update recruitment records', 'تحديث سجلات التوظيف'),
('Delete Recruitment', 'حذف التوظيف', 'recruitment.delete', 'recruitment', 'Soft-delete recruitment records', 'حذف سجلات التوظيف'),
('Recruitment Interview', 'مقابلات التوظيف', 'recruitment.interview', 'recruitment', 'Manage interviews', 'إدارة المقابلات'),
('Recruitment Visa', 'تأشيرات التوظيف', 'recruitment.visa', 'recruitment', 'Manage visas', 'إدارة التأشيرات'),
('Recruitment Contract', 'عقود التوظيف', 'recruitment.contract', 'recruitment', 'Manage recruitment contracts', 'إدارة عقود التوظيف'),
('Recruitment Medical', 'الفحص الطبي', 'recruitment.medical', 'recruitment', 'Manage medical exams', 'إدارة الفحوصات الطبية'),
('Recruitment Assign', 'إسناد التوظيف', 'recruitment.assign', 'recruitment', 'Assign recruiters', 'إسناد مسؤولي التوظيف'),
('Recruitment Admin', 'إدارة التوظيف', 'recruitment.admin', 'recruitment', 'Full recruitment administration', 'إدارة كاملة للتوظيف'),
('Manage Recruitment', 'إدارة التوظيف', 'recruitment.manage', 'recruitment', 'Manage all recruitment operations', 'إدارة جميع عمليات التوظيف')
ON DUPLICATE KEY UPDATE name = VALUES(name), name_ar = VALUES(name_ar), description = VALUES(description), description_ar = VALUES(description_ar);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'recruitment.view','recruitment.create','recruitment.update','recruitment.delete',
    'recruitment.interview','recruitment.visa','recruitment.contract','recruitment.medical',
    'recruitment.assign','recruitment.admin','recruitment.manage'
)
WHERE r.slug IN ('company-full-access', 'super-admin');
