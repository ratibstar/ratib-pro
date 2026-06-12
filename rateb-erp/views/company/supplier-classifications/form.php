<?php Rateb\App\Core\View::partial('crud-form', [
    'title' => $title ?? '',
    'item' => $item ?? null,
    'fields' => $fields ?? [],
    'routePrefix' => $routePrefix ?? rateb_app_route('supplier-classifications'),
    'csrf' => $csrf,
]); ?>
