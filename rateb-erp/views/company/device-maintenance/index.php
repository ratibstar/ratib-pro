<?php
use Rateb\App\Services\FormLookupService;

Rateb\App\Core\View::partial('workflow-index', [
    'title' => $title ?? __('device_maintenance'),
    'entitySlug' => 'device-maintenance',
    'routePrefix' => rateb_app_route('device-maintenance'),
    'formFields' => FormLookupService::deviceMaintenanceFormFields(),
    'formAction' => rateb_app_url('device-maintenance'),
    'items' => $items ?? [],
    'columns' => [
        ['name' => 'service_no', 'label' => 'record_id', 'type' => 'id'],
        ['name' => 'device_name', 'label' => 'medical_devices'],
        ['name' => 'service_date', 'label' => 'service_date'],
        ['name' => 'service_type', 'label' => 'service_type'],
        ['name' => 'provider', 'label' => 'provider'],
        ['name' => 'cost', 'label' => 'cost', 'type' => 'money'],
        ['name' => 'manager_approval', 'label' => 'manager_approval', 'type' => 'status'],
        ['name' => 'notes', 'label' => 'notes', 'type' => 'notes'],
    ],
    'exportRoute' => $exportRoute ?? rateb_app_url('device-maintenance/export'),
    'exportEnabled' => $exportEnabled ?? true,
    'csrf' => $csrf,
    'canManage' => $canManage ?? null,
]);
