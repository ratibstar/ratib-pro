<?php Rateb\App\Core\View::partial('crud-index', [
    'title' => $title ?? __('supplier_classifications'),
    'items' => $items ?? [],
    'fields' => $fields ?? [],
    'csrf' => $csrf,
    'routePrefix' => $routePrefix ?? rateb_app_route('supplier-classifications'),
    'permissionResource' => $permissionResource ?? 'supplier-classifications',
    'bulkEnabled' => $bulkEnabled ?? true,
    'createEnabled' => $createEnabled ?? true,
    'actionsEnabled' => $actionsEnabled ?? true,
]); ?>
