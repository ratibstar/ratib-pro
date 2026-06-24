-- RATEB ERP — repair Arabic labels on approval workflows (UNHEX — CI/deploy-safe)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

ALTER TABLE rateb_approval_workflows CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE rateb_approval_workflow_steps CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

UPDATE rateb_approval_workflows SET name = CONVERT(UNHEX('D8A7D8B9D8AAD985D8A7D8AF20D8B7D984D8A8D8A7D8AA20D8A7D984D8B4D8B1D8A7D8A1') USING utf8mb4) WHERE entity_type = 'purchase_request';
UPDATE rateb_approval_workflows SET name = CONVERT(UNHEX('D8A7D8B9D8AAD985D8A7D8AF20D8A3D988D8A7D985D8B120D8A7D984D8B4D8B1D8A7D8A1') USING utf8mb4) WHERE entity_type = 'purchase_order';
UPDATE rateb_approval_workflow_steps s INNER JOIN rateb_approval_workflows w ON w.id = s.workflow_id SET s.label = CONVERT(UNHEX('D8A7D8B9D8AAD985D8A7D8AF20D8B7D984D8A820D8A7D984D8B4D8B1D8A7D8A1') USING utf8mb4) WHERE w.entity_type = 'purchase_request';
UPDATE rateb_approval_workflow_steps s INNER JOIN rateb_approval_workflows w ON w.id = s.workflow_id SET s.label = CONVERT(UNHEX('D8A7D8B9D8AAD985D8A7D8AF20D8A3D985D8B120D8A7D984D8B4D8B1D8A7D8A1') USING utf8mb4) WHERE w.entity_type = 'purchase_order';
