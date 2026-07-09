-- RATEB ERP — POS biometric credentials, supervisor approval, permission slugs
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('POS Inventory Adjust', 'تعديل مخزون نقطة البيع', 'pos.inventory.adjust', 'pos', 'Adjust stock from POS register', 'تعديل المخزون من شاشة نقطة البيع'),
('POS Supervisor Approve', 'اعتماد مشرف نقطة البيع', 'pos.supervisor.approve', 'pos', 'Supervisor biometric approval for sensitive POS actions', 'اعتماد بصمة المشرف للإجراءات الحساسة في نقطة البيع')
ON DUPLICATE KEY UPDATE name = VALUES(name), name_ar = VALUES(name_ar), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug IN ('pos.inventory.adjust', 'pos.supervisor.approve')
WHERE r.slug IN ('pos_supervisor', 'pos_manager', 'super-admin', 'company-full-access', 'branch_manager');

CREATE TABLE IF NOT EXISTS rateb_webauthn_credentials (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    credential_id VARBINARY(512) NOT NULL,
    public_key TEXT NOT NULL,
    sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_used DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_webauthn_credential (credential_id),
    KEY idx_webauthn_user (user_id),
    CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES rateb_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_face_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    template_data MEDIUMTEXT NOT NULL,
    confidence_threshold DECIMAL(5,4) NOT NULL DEFAULT 0.8500,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_face_user (user_id),
    CONSTRAINT fk_face_user FOREIGN KEY (user_id) REFERENCES rateb_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_approval_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    register_session_id BIGINT UNSIGNED NULL,
    action_type VARCHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pos_approval_company (company_id, status, created_at),
    KEY idx_pos_approval_requester (requested_by),
    CONSTRAINT fk_pos_approval_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_approval_requester FOREIGN KEY (requested_by) REFERENCES rateb_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_approval_grants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    supervisor_user_id INT UNSIGNED NOT NULL,
    biometric_method VARCHAR(32) NOT NULL DEFAULT 'webauthn',
    approval_token CHAR(64) NOT NULL,
    verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pos_approval_token (approval_token),
    KEY idx_pos_approval_grant_request (request_id),
    CONSTRAINT fk_pos_grant_request FOREIGN KEY (request_id) REFERENCES rateb_pos_approval_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_grant_supervisor FOREIGN KEY (supervisor_user_id) REFERENCES rateb_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
