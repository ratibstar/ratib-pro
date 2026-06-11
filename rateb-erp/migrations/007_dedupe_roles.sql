-- Merge permissions and user assignments from duplicate roles, then remove duplicates (same slug)

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT keeper.id, rp.permission_id
FROM rateb_roles dup
INNER JOIN rateb_roles keeper ON keeper.slug = dup.slug AND keeper.id < dup.id
INNER JOIN rateb_role_permissions rp ON rp.role_id = dup.id;

INSERT IGNORE INTO rateb_user_roles (user_id, role_id)
SELECT ur.user_id, keeper.id
FROM rateb_roles dup
INNER JOIN rateb_roles keeper ON keeper.slug = dup.slug AND keeper.id < dup.id
INNER JOIN rateb_user_roles ur ON ur.role_id = dup.id;

DELETE r1 FROM rateb_roles r1
INNER JOIN rateb_roles r2 ON r1.slug = r2.slug AND r1.id > r2.id;
