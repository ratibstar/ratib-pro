-- RATEB Contact Center — 015 quality assurance (Phase 10C)

CREATE TABLE IF NOT EXISTS rcc_qa_forms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NULL,
    channel ENUM('call','chat','email','any') NOT NULL DEFAULT 'any',
    max_score INT UNSIGNED NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_qa_form_code (tenant_id, code),
    CONSTRAINT fk_rcc_qa_form_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_qa_questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    form_id INT UNSIGNED NOT NULL,
    question_key VARCHAR(64) NOT NULL,
    label VARCHAR(255) NOT NULL,
    label_ar VARCHAR(255) NULL,
    weight INT UNSIGNED NOT NULL DEFAULT 10,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_qa_question (tenant_id, form_id, question_key),
    CONSTRAINT fk_rcc_qa_question_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_qa_question_form FOREIGN KEY (form_id) REFERENCES rcc_qa_forms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_qa_reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    form_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    evaluator_user_id INT UNSIGNED NULL,
    channel VARCHAR(32) NOT NULL,
    call_id INT UNSIGNED NULL,
    conversation_id INT UNSIGNED NULL,
    recording_id INT UNSIGNED NULL,
    status ENUM('draft','completed','calibrated') NOT NULL DEFAULT 'draft',
    total_score DECIMAL(6,2) NULL,
    coaching_notes TEXT NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_qa_reviews_agent (tenant_id, agent_id, created_at),
    KEY idx_rcc_qa_reviews_status (tenant_id, status),
    CONSTRAINT fk_rcc_qa_reviews_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_qa_reviews_form FOREIGN KEY (form_id) REFERENCES rcc_qa_forms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_qa_scores (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    review_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    score DECIMAL(6,2) NOT NULL,
    max_score DECIMAL(6,2) NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_qa_score (tenant_id, review_id, question_id),
    CONSTRAINT fk_rcc_qa_scores_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_qa_scores_review FOREIGN KEY (review_id) REFERENCES rcc_qa_reviews (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_qa_scores_question FOREIGN KEY (question_id) REFERENCES rcc_qa_questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_qa_calibrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    form_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    facilitator_user_id INT UNSIGNED NULL,
    scheduled_at TIMESTAMP NULL,
    notes TEXT NULL,
    status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_qa_cal_tenant (tenant_id, scheduled_at),
    CONSTRAINT fk_rcc_qa_cal_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_qa_cal_form FOREIGN KEY (form_id) REFERENCES rcc_qa_forms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.qa.view', 'View QA', 'qa'),
('rcc.qa.evaluate', 'Evaluate Interactions', 'qa'),
('rcc.qa.coach', 'QA Coaching', 'qa'),
('rcc.qa.calibrate', 'QA Calibration', 'qa'),
('rcc.qa.admin', 'QA Administration', 'qa');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.qa.%';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN ('rcc.qa.view', 'rcc.qa.evaluate', 'rcc.qa.coach', 'rcc.qa.calibrate');

INSERT IGNORE INTO rcc_qa_forms (tenant_id, code, name, name_ar, channel, max_score)
SELECT t.id, 'call_standard', 'Standard Call Evaluation', 'تقييم المكالمات', 'call', 100
FROM rcc_tenants t WHERE t.status = 'active';

INSERT IGNORE INTO rcc_qa_questions (tenant_id, form_id, question_key, label, label_ar, weight, sort_order)
SELECT f.tenant_id, f.id, 'greeting', 'Professional greeting', 'تحية مهنية', 20, 10
FROM rcc_qa_forms f WHERE f.code = 'call_standard';

INSERT IGNORE INTO rcc_qa_questions (tenant_id, form_id, question_key, label, label_ar, weight, sort_order)
SELECT f.tenant_id, f.id, 'resolution', 'Issue resolution', 'حل المشكلة', 40, 20
FROM rcc_qa_forms f WHERE f.code = 'call_standard';

INSERT IGNORE INTO rcc_qa_questions (tenant_id, form_id, question_key, label, label_ar, weight, sort_order)
SELECT f.tenant_id, f.id, 'closing', 'Professional closing', 'إغلاق مهني', 20, 30
FROM rcc_qa_forms f WHERE f.code = 'call_standard';
