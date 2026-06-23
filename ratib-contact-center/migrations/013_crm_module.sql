-- RATIB Contact Center — 013 CRM module (Phase 10A)

CREATE TABLE IF NOT EXISTS rcc_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    account_no VARCHAR(32) NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NULL,
    account_type ENUM('company','individual','partner','government') NOT NULL DEFAULT 'company',
    industry VARCHAR(64) NULL,
    tier VARCHAR(32) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    erp_company_id INT UNSIGNED NULL,
    billing_address TEXT NULL,
    owner_agent_id INT UNSIGNED NULL,
    status ENUM('active','inactive','prospect') NOT NULL DEFAULT 'active',
    metadata_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_accounts_no (tenant_id, account_no),
    KEY idx_rcc_accounts_tenant (tenant_id),
    KEY idx_rcc_accounts_erp (tenant_id, erp_company_id),
    CONSTRAINT fk_rcc_accounts_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rcc_contacts
    ADD COLUMN account_id INT UNSIGNED NULL;

ALTER TABLE rcc_contacts
    ADD KEY idx_rcc_contacts_account (tenant_id, account_id);

CREATE TABLE IF NOT EXISTS rcc_contact_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NULL,
    author_user_id INT UNSIGNED NULL,
    body TEXT NOT NULL,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_contact_notes_contact (tenant_id, contact_id, created_at),
    CONSTRAINT fk_rcc_contact_notes_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_contact_notes_contact FOREIGN KEY (contact_id) REFERENCES rcc_contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_contact_tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    tag VARCHAR(64) NOT NULL,
    color VARCHAR(16) NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_contact_tag (tenant_id, contact_id, tag),
    KEY idx_rcc_contact_tags_tenant (tenant_id, tag),
    CONSTRAINT fk_rcc_contact_tags_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_contact_tags_contact FOREIGN KEY (contact_id) REFERENCES rcc_contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_contact_activities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NULL,
    activity_type VARCHAR(64) NOT NULL,
    channel VARCHAR(32) NULL,
    reference_type VARCHAR(32) NULL,
    reference_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    payload_json JSON NULL,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_contact_act_contact (tenant_id, contact_id, occurred_at),
    KEY idx_rcc_contact_act_type (tenant_id, activity_type, occurred_at),
    CONSTRAINT fk_rcc_contact_act_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_contact_act_contact FOREIGN KEY (contact_id) REFERENCES rcc_contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_contact_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    storage_path VARCHAR(512) NOT NULL,
    uploaded_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_contact_docs_contact (tenant_id, contact_id),
    CONSTRAINT fk_rcc_contact_docs_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_contact_docs_contact FOREIGN KEY (contact_id) REFERENCES rcc_contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_contact_custom_fields (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(64) NOT NULL,
    label VARCHAR(128) NOT NULL,
    label_ar VARCHAR(128) NULL,
    field_type ENUM('text','number','date','boolean','select') NOT NULL DEFAULT 'text',
    options_json JSON NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_contact_cf_key (tenant_id, field_key),
    CONSTRAINT fk_rcc_contact_cf_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_contact_custom_values (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    value_text TEXT NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_contact_cf_val (tenant_id, contact_id, field_id),
    CONSTRAINT fk_rcc_contact_cf_val_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_contact_cf_val_contact FOREIGN KEY (contact_id) REFERENCES rcc_contacts (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_contact_cf_val_field FOREIGN KEY (field_id) REFERENCES rcc_contact_custom_fields (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.crm.view', 'View CRM', 'crm'),
('rcc.crm.accounts', 'Manage Accounts', 'crm'),
('rcc.crm.contacts', 'Manage Contacts', 'crm'),
('rcc.crm.notes', 'Manage Contact Notes', 'crm'),
('rcc.crm.tags', 'Manage Contact Tags', 'crm'),
('rcc.crm.documents', 'Manage Contact Documents', 'crm'),
('rcc.crm.sync', 'ERP CRM Sync', 'crm');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.crm.%';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN (
    'rcc.crm.view', 'rcc.crm.contacts', 'rcc.crm.notes', 'rcc.crm.tags'
);

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 3, id FROM rcc_permissions WHERE slug IN ('rcc.crm.view', 'rcc.crm.contacts', 'rcc.crm.notes');
