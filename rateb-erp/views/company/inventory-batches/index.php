<?php Rateb\App\Core\View::partial('crud-index', array_merge(get_defined_vars(), [
    'title' => $title ?? __('inventory_batches'),
    'createEnabled' => $createEnabled ?? true,
])); ?>
