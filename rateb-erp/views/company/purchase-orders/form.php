<?php Rateb\App\Core\View::partial('procurement-form', array_merge(get_defined_vars(), [
    'entityType' => 'purchase_order',
    'totalField' => 'total_amount',
])); ?>
