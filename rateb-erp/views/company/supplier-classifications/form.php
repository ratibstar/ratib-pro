<?php Rateb\App\Core\View::partial('crud-form', [
    'title' => $title ?? '',
    'item' => $item ?? null,
    'fields' => $fields ?? [],
    'routePrefix' => $routePrefix ?? 'company/supplier-classifications',
    'csrf' => $csrf,
]); ?>
