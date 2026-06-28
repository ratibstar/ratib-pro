<?php
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
if (rateb_is_super_admin() || rateb_can('access.manage')) {
    Rateb\App\Core\View::partial('accounting-permissions-note');
}
Rateb\App\Core\View::partial('accounting-dashboard', [
    'dash' => $dash ?? [],
    'trial' => $trial ?? [],
    'isAdmin' => false,
    'canPost' => $canPost ?? rateb_can_post_entity('accounting'),
    'csrf' => $csrf ?? '',
    'selectedCompanyId' => $selectedCompanyId ?? 0,
]);
