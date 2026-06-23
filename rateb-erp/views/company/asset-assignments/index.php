<?php
use Rateb\App\Services\FormLookupService;

Rateb\App\Core\View::partial('workflow-index', [
    'title' => $title ?? __('asset_assignments'),
    'entitySlug' => 'asset-assignments',
    'routePrefix' => rateb_app_route('asset-assignments'),
    'formFields' => FormLookupService::assetAssignmentFormFields(),
    'formAction' => rateb_app_url('asset-assignments'),
    'items' => $items ?? [],
    'columns' => [
        ['name' => 'assignment_no', 'label' => 'record_id', 'type' => 'id'],
        ['name' => 'asset_name', 'label' => 'assets'],
        ['name' => 'assigned_to', 'label' => 'assigned_to'],
        ['name' => 'department', 'label' => 'department'],
        ['name' => 'assigned_at', 'label' => 'assigned_at'],
        ['name' => 'returned_at', 'label' => 'returned_at'],
        ['name' => 'manager_approval', 'label' => 'manager_approval', 'type' => 'status'],
        ['name' => 'notes', 'label' => 'notes', 'type' => 'notes'],
    ],
    'exportRoute' => $exportRoute ?? rateb_app_url('asset-assignments/export'),
    'exportEnabled' => $exportEnabled ?? true,
    'csrf' => $csrf,
    'canManage' => $canManage ?? null,
]);
