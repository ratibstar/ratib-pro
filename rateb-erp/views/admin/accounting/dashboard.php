<?php
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'admin']);
Rateb\App\Core\View::partial('accounting-dashboard', [
    'dash' => $dash ?? [],
    'trial' => $trial ?? [],
    'isAdmin' => true,
    'canPost' => true,
    'csrf' => $csrf ?? '',
    'selectedCompanyId' => 0,
]);
