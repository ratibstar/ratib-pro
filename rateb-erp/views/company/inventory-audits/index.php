<?php
Rateb\App\Core\View::partial('workflow-index', [
    'title' => $title ?? __('inventory_audits'),
    'entitySlug' => 'inventory-audits',
    'routePrefix' => rateb_app_route('inventory-audits'),
    'createUrl' => rateb_app_url('inventory-audits/create'),
    'formFields' => null,
    'items' => $items ?? [],
    'columns' => [
        ['name' => 'audit_no', 'label' => 'record_id', 'type' => 'id'],
        ['name' => 'audit_date', 'label' => 'audit_date'],
        ['name' => 'status', 'label' => 'status'],
        ['name' => 'created_at', 'label' => 'created_at'],
        ['name' => 'id', 'label' => 'actions', 'type' => 'action_link', 'url' => rateb_app_route('inventory-audits') . '/{id}', 'text' => 'view'],
    ],
    'exportRoute' => $exportRoute ?? rateb_app_url('inventory-audits/export'),
    'exportEnabled' => $exportEnabled ?? true,
    'csrf' => $csrf,
    'canManage' => $canManage ?? null,
]);
?>
