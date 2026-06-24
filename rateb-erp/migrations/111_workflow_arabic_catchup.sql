-- RATEB ERP — repair Arabic labels on approval workflows (charset corruption)
SET NAMES utf8mb4;

UPDATE rateb_approval_workflows
SET name = 'اعتماد طلبات الشراء'
WHERE entity_type = 'purchase_request';

UPDATE rateb_approval_workflows
SET name = 'اعتماد أوامر الشراء'
WHERE entity_type = 'purchase_order';

UPDATE rateb_approval_workflow_steps s
INNER JOIN rateb_approval_workflows w ON w.id = s.workflow_id
SET s.label = 'اعتماد طلب الشراء'
WHERE w.entity_type = 'purchase_request';

UPDATE rateb_approval_workflow_steps s
INNER JOIN rateb_approval_workflows w ON w.id = s.workflow_id
SET s.label = 'اعتماد أمر الشراء'
WHERE w.entity_type = 'purchase_order';
