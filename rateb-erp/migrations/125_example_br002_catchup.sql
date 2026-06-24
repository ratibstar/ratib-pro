-- RATEB ERP — example company Jeddah branch (BR002) + portal user link (UNHEX; deploy-safe)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @ex_cid = (SELECT id FROM rateb_companies WHERE slug = 'example-medical' LIMIT 1);

INSERT INTO rateb_branches (company_id, name, code, address, phone, email, status, is_main)
SELECT @ex_cid,
       CONVERT(UNHEX('D981D8B1D8B920D8ACD8AFD8A9') USING utf8mb4),
       'BR002', CONVERT(UNHEX('D8ACD8AFD8A9202D20D8A7D984D8B1D98AD8A7D8B6') USING utf8mb4), '+966500000102', 'jeddah@example.rateb.sa', 'active', 0
FROM DUAL
WHERE @ex_cid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_branches WHERE company_id = @ex_cid AND code = 'BR002' LIMIT 1);

SET @jed_bid = (SELECT id FROM rateb_branches WHERE company_id = @ex_cid AND code = 'BR002' LIMIT 1);

INSERT INTO rateb_users (company_id, name, email, password, is_super_admin, status, locale)
SELECT @ex_cid, 'Jeddah Branch User', 'branch@example.rateb.sa',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active', 'ar'
FROM DUAL
WHERE @ex_cid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_users WHERE email = 'branch@example.rateb.sa' LIMIT 1);

INSERT INTO rateb_user_roles (user_id, role_id)
SELECT u.id, r.id FROM rateb_users u
JOIN rateb_roles r ON r.slug = 'company-full-access'
WHERE u.email = 'branch@example.rateb.sa'
  AND NOT EXISTS (SELECT 1 FROM rateb_user_roles ur WHERE ur.user_id = u.id AND ur.role_id = r.id);

INSERT INTO rateb_user_branches (user_id, branch_id)
SELECT u.id, @jed_bid FROM rateb_users u
WHERE u.email = 'branch@example.rateb.sa' AND @jed_bid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_user_branches ub WHERE ub.user_id = u.id AND ub.branch_id = @jed_bid);
