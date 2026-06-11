<?php Rateb\App\Core\View::partial('crud-form', get_defined_vars()); ?>
<?php Rateb\App\Core\View::partial('line-items', ['lineItems' => $lineItems ?? []]); ?>
