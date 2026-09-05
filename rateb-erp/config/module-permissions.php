<?php
declare(strict_types=1);

/** Default permission slug enforced per company module when RBAC roles are assigned. */
return [
    'dashboard' => 'dashboard.view',
    'procurement' => 'procurement.manage',
    'inventory' => 'inventory.manage',
    'suppliers' => 'suppliers.manage',
    'assets' => 'assets.manage',
    'contracts' => 'contracts.manage',
    'tenders' => 'tenders.manage',
    'reports' => 'reports.view',
    'medical_devices' => 'device_service.view',
    'accounting' => 'accounting.view',
    'documents' => 'documents.view',
    'workflows' => 'workflows.view',
    'hr' => 'hr.view',
    'branches' => 'branches.view',
    'pos' => 'pos.view',
    'recruitment' => 'recruitment.manage',
    'crm' => 'crm.manage',
    'projects' => 'projects.manage',
    'manufacturing' => 'manufacturing.manage',
    'website' => 'website.view',
    'logistics' => 'logistics.manage',
    'marketplace' => 'marketplace.manage',
    'module_addons' => 'module_addons.manage',
];
