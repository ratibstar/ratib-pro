<?php
declare(strict_types=1);

/** Default permission slug enforced per company module when RBAC roles are assigned. */
return [
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
    'notifications' => 'notifications.manage',
];
