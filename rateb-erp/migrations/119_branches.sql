-- RATEB ERP — company branches (الفروع)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_branches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NULL,
    address VARCHAR(255) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    map_url VARCHAR(500) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_branch_company (company_id),
    INDEX idx_branch_code (company_id, code),
    CONSTRAINT fk_branch_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Branches', 'عرض الفروع', 'branches.view', 'branches', 'View company branch list', 'عرض قائمة فروع الشركة'),
('Manage Branches', 'إدارة الفروع', 'branches.manage', 'branches', 'Create and edit company branches', 'إنشاء وتعديل فروع الشركة')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    name_ar = VALUES(name_ar),
    module = VALUES(module),
    description = VALUES(description),
    description_ar = VALUES(description_ar);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('branches.view', 'branches.manage')
WHERE r.slug IN ('super-admin', 'company-full-access')
ON DUPLICATE KEY UPDATE role_id = role_id;

SET @cid = (SELECT id FROM rateb_companies WHERE slug = 'demo-company' LIMIT 1);

INSERT INTO rateb_branches (company_id, name, code, address, phone, email, map_url, status)
SELECT @cid, CONVERT(UNHEX('D8A7D984D981D8B1D8B9D8A7D984D8B1D8A6D98AD8B3D98A') USING utf8mb4), 'MB001',
       CONVERT(UNHEX('D8A7D984D8B1D98AD8A7D8B6') USING utf8mb4), '0507965705', 'info@ratib.sa', NULL, 'active'
FROM DUAL
WHERE @cid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_branches WHERE company_id = @cid AND code = 'MB001' LIMIT 1);
