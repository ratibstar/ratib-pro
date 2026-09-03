<?php
declare(strict_types=1);

use Rateb\App\Core\View;

View::partial('billing/addon-lock-board', [
    'rows' => $rows ?? [],
    'context' => $context ?? [],
    'csrf' => $csrf ?? '',
    'action' => $action ?? rateb_url('admin/billing/addon-locks'),
    'returnTo' => $returnTo ?? 'locks',
    'companies' => $companies ?? [],
    'pickedCompanyId' => $pickedCompanyId ?? 0,
]);
