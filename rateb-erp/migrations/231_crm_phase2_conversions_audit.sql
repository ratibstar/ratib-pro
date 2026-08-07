-- Phase 2 — CRM conversion audit + multi-entity status history (additive only).
-- No ALTER/DROP on existing CRM tables.

CREATE TABLE IF NOT EXISTS rateb_crm_entity_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_crm_esh_company (company_id, created_at),
    INDEX idx_crm_esh_entity (company_id, entity_type, entity_id),
    CONSTRAINT fk_crm_esh_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_crm_conversions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_uuid CHAR(36) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    conversion_type VARCHAR(60) NOT NULL,
    from_type VARCHAR(40) NOT NULL,
    from_id INT UNSIGNED NOT NULL,
    to_type VARCHAR(40) NOT NULL,
    to_id INT UNSIGNED NOT NULL,
    meta_json TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_conv_uuid (public_uuid),
    INDEX idx_crm_conv_company (company_id, created_at),
    INDEX idx_crm_conv_from (company_id, from_type, from_id),
    INDEX idx_crm_conv_to (company_id, to_type, to_id),
    CONSTRAINT fk_crm_conv_tenant FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quotation RBAC (additive; existing crm.* remain valid)
INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('CRM Quote View', 'عرض عروض الأسعار', 'crm.quote.view', 'crm', 'View CRM sales quotations', 'عرض عروض أسعار المبيعات'),
('CRM Quote Create', 'إنشاء عروض الأسعار', 'crm.quote.create', 'crm', 'Create CRM sales quotations', 'إنشاء عروض أسعار المبيعات'),
('CRM Quote Update', 'تحديث عروض الأسعار', 'crm.quote.update', 'crm', 'Update / transition CRM quotations', 'تحديث وتحويل حالات عروض الأسعار'),
('CRM Quote Convert', 'تحويل عروض الأسعار', 'crm.quote.convert', 'crm', 'Convert quotation to customer', 'تحويل عرض السعر إلى عميل')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'crm.quote.view', 'crm.quote.create', 'crm.quote.update', 'crm.quote.convert'
)
WHERE r.slug IN ('company-full-access', 'super-admin');
