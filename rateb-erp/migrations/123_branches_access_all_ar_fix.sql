-- RATEB ERP — fix Arabic labels for branches.access_all if 122 UPDATE failed
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

UPDATE rateb_permissions SET
    name_ar = CONVERT(UNHEX('D8A7D984D988D8B5D988D98420D984D983D98420D8A7D984D981D8B1D988D8B9') USING utf8mb4),
    description_ar = CONVERT(UNHEX('D8B9D8B1D8B620D988D8A5D8AFD8A7D8B1D8A920D983D98420D981D8B1D988D8B920D8A7D984D8B4D8B1D983D8A9') USING utf8mb4)
WHERE slug = 'branches.access_all';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug = 'branches.access_all'
WHERE r.slug IN ('super-admin', 'company-full-access');
