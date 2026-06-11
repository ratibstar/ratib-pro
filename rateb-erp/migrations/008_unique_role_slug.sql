-- Prevent duplicate platform roles: MySQL allows repeated (NULL, slug) on (company_id, slug)

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

-- fk_roles_company uses uq_roles_company_slug; drop FK before dropping that index
ALTER TABLE rateb_roles DROP FOREIGN KEY fk_roles_company;
ALTER TABLE rateb_roles DROP INDEX uq_roles_company_slug;
ALTER TABLE rateb_roles ADD UNIQUE KEY uq_roles_slug (slug);
ALTER TABLE rateb_roles ADD INDEX idx_roles_company (company_id);
ALTER TABLE rateb_roles ADD CONSTRAINT fk_roles_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE;
