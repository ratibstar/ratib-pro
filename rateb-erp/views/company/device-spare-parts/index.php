<?php
use Rateb\App\Services\FormLookupService;

Rateb\App\Core\View::partial('workflow-index', [
    'title' => $title ?? __('device_spare_parts'),
    'entitySlug' => 'device-spare-parts',
    'routePrefix' => rateb_app_route('device-spare-parts'),
    'formFields' => FormLookupService::deviceSparePartsFormFields(),
    'formAction' => rateb_app_url('device-spare-parts'),
    'items' => $items ?? [],
    'columns' => [
        ['name' => 'part_no', 'label' => 'record_id', 'type' => 'id'],
        ['name' => 'device_name', 'label' => 'medical_devices'],
        ['name' => 'part_name', 'label' => 'part_name'],
        ['name' => 'quantity', 'label' => 'quantity'],
        ['name' => 'reorder_level', 'label' => 'reorder_level'],
        ['name' => 'manager_approval', 'label' => 'manager_approval', 'type' => 'status'],
    ],
    'exportRoute' => $exportRoute ?? rateb_app_url('device-spare-parts/export'),
    'exportEnabled' => $exportEnabled ?? true,
    'csrf' => $csrf,
    'canManage' => $canManage ?? null,
]);
