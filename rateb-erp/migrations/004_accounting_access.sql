-- RATEB ERP — Accounting module + Access control permissions
SET NAMES utf8mb4;

INSERT INTO rateb_permissions (name, slug, module, description) VALUES
('Manage Access Control', 'access.manage', 'access', 'Full users, roles, permissions control'),
('View Accounting', 'accounting.view', 'accounting', 'View chart of accounts and journals'),
('Manage Accounting', 'accounting.manage', 'accounting', 'Manage chart of accounts and journal entries'),
('Post Journal Entries', 'accounting.post', 'accounting', 'Post and void journal entries')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('access.manage', 'accounting.view', 'accounting.manage', 'accounting.post')
WHERE r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO rateb_roles (company_id, name, slug, description, is_system) VALUES
(NULL, 'Accountant', 'accountant', 'Accounting and reports access', 1),
(NULL, 'Access Manager', 'access-manager', 'Users and roles management', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('accounting.view', 'accounting.manage', 'accounting.post', 'reports.view', 'dashboard.view')
WHERE r.slug = 'accountant'
ON DUPLICATE KEY UPDATE role_id = role_id;

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('access.manage', 'users.manage', 'roles.manage', 'permissions.manage', 'dashboard.view')
WHERE r.slug = 'access-manager'
ON DUPLICATE KEY UPDATE role_id = role_id;

CREATE TABLE IF NOT EXISTS rateb_chart_of_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(200) NOT NULL,
    name_ar VARCHAR(200) NULL,
    account_type ENUM('asset','liability','equity','revenue','expense') NOT NULL DEFAULT 'asset',
    parent_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_coa_company_code (company_id, code),
    INDEX idx_coa_company (company_id),
    CONSTRAINT fk_coa_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_journal_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    entry_no VARCHAR(50) NOT NULL,
    entry_date DATE NOT NULL,
    description VARCHAR(500) NOT NULL,
    description_ar VARCHAR(500) NULL,
    source_type ENUM('manual','invoice','payment','purchase_order','subscription') NOT NULL DEFAULT 'manual',
    source_id INT UNSIGNED NULL,
    status ENUM('draft','posted','void') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    posted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_journal_company_no (company_id, entry_no),
    INDEX idx_journal_company (company_id),
    INDEX idx_journal_source (source_type, source_id),
    CONSTRAINT fk_journal_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_journal_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    memo VARCHAR(255) NULL,
    INDEX idx_jl_entry (journal_entry_id),
    INDEX idx_jl_account (account_id),
    CONSTRAINT fk_jl_entry FOREIGN KEY (journal_entry_id) REFERENCES rateb_journal_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_jl_account FOREIGN KEY (account_id) REFERENCES rateb_chart_of_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE rateb_plans SET modules = '["procurement","inventory","suppliers","assets","contracts","tenders","reports","medical_devices","accounting"]' WHERE slug = 'enterprise';
UPDATE rateb_plans SET modules = '["procurement","inventory","suppliers","assets","contracts","reports","accounting"]' WHERE slug = 'professional';
