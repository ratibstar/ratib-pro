-- Demo company portal user (unified /login — not super admin)
SET NAMES utf8mb4;

SET @cid = (SELECT id FROM rateb_companies WHERE slug = 'demo-company' LIMIT 1);
SET @cid = IFNULL(@cid, (SELECT id FROM rateb_companies WHERE status = 'active' ORDER BY id LIMIT 1));

INSERT INTO rateb_users (company_id, name, email, password, is_super_admin, status, locale)
SELECT @cid, 'مستخدم الشركة التجريبية', 'company@demo.rateb.sa',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'active', 'ar'
FROM DUAL
WHERE @cid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_users WHERE email = 'company@demo.rateb.sa' LIMIT 1);

INSERT INTO rateb_user_roles (user_id, role_id)
SELECT u.id, r.id FROM rateb_users u
JOIN rateb_roles r ON r.slug = 'company-full-access'
WHERE u.email = 'company@demo.rateb.sa'
  AND NOT EXISTS (SELECT 1 FROM rateb_user_roles ur WHERE ur.user_id = u.id LIMIT 1)
ON DUPLICATE KEY UPDATE user_id = user_id;
