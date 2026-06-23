<?php
use Rateb\App\Services\FormLookupService;

Rateb\App\Core\View::partial('workflow-index', [
    'title' => $title ?? __('asset_maintenance'),
    'entitySlug' => 'asset-maintenance',
    'routePrefix' => rateb_app_route('asset-maintenance'),
    'formFields' => FormLookupService::assetMaintenanceFormFields(),
    'formAction' => rateb_app_url('asset-maintenance'),
    'items' => $items ?? [],
    'columns' => [
        ['name' => 'maintenance_no', 'label' => 'record_id', 'type' => 'id'],
        ['name' => 'asset_name', 'label' => 'assets'],
        ['name' => 'maintenance_type', 'label' => 'maintenance_type'],
        ['name' => 'scheduled_date', 'label' => 'scheduled_date'],
        ['name' => 'completed_date', 'label' => 'completed_date'],
        ['name' => 'cost', 'label' => 'cost', 'type' => 'money'],
        ['name' => 'status', 'label' => 'status', 'type' => 'status'],
        ['name' => 'manager_approval', 'label' => 'manager_approval', 'type' => 'status'],
        ['name' => 'notes', 'label' => 'notes', 'type' => 'notes'],
    ],
    'exportRoute' => $exportRoute ?? rateb_app_url('asset-maintenance/export'),
    'exportEnabled' => $exportEnabled ?? true,
    'csrf' => $csrf,
    'canManage' => $canManage ?? null,
]);
