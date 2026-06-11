<?php Rateb\App\Core\View::partial('crud-index', [
    'title' => $title ?? __('supplier_classifications'),
    'items' => $items ?? [],
    'fields' => $fields ?? [],
    'csrf' => $csrf,
    'routePrefix' => $routePrefix ?? 'company/supplier-classifications',
    'bulkEnabled' => $bulkEnabled ?? true,
    'createEnabled' => $createEnabled ?? true,
    'actionsEnabled' => $actionsEnabled ?? true,
]); ?>
