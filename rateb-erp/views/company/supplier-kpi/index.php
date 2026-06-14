<?php
use Rateb\App\Services\FormLookupService;

Rateb\App\Core\View::partial('workflow-index', [
    'title' => $title ?? __('supplier_kpi'),
    'entitySlug' => 'supplier-kpi',
    'routePrefix' => rateb_app_route('supplier-kpi'),
    'formFields' => null,
    'items' => $suppliers ?? [],
    'columns' => [
        ['name' => 'code', 'label' => 'record_id', 'type' => 'id'],
        ['name' => 'name', 'label' => 'suppliers'],
        ['name' => 'classification_name', 'label' => 'supplier_classifications'],
        ['name' => 'rating', 'label' => 'rating', 'type' => 'money'],
        ['name' => 'avg_eval', 'label' => 'overall_score', 'type' => 'money'],
        ['name' => 'po_count', 'label' => 'purchase_orders'],
        ['name' => 'performance_kpi', 'label' => 'performance_kpi', 'type' => 'money'],
    ],
    'exportRoute' => $exportRoute ?? rateb_app_url('supplier-kpi/export'),
    'exportEnabled' => $exportEnabled ?? true,
    'csrf' => $csrf,
    'canManage' => false,
]);
