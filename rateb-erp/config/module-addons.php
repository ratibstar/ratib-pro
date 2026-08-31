<?php
declare(strict_types=1);

/**
 * Server-authoritative Module Add-on Commerce catalog.
 *
 * Prices are NOT derived from HTTP input. A zero/empty price is not purchasable.
 * Commercial amounts are unset until configured (fail closed).
 *
 * `enabled` must be true for a slug to be sold. Runtime access is still
 * company.modules → PlanLimitService::companyHasModule() → CompanyModuleMiddleware.
 *
 * @return array<string, array{name:string, monthly:float, yearly:float, enabled:bool}>
 */
return [
    'crm' => [
        'name' => 'CRM',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'pos' => [
        'name' => 'POS',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'hr' => [
        'name' => 'HR',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'recruitment' => [
        'name' => 'Recruitment',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'logistics' => [
        'name' => 'Logistics',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'marketplace' => [
        'name' => 'Marketplace',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'manufacturing' => [
        'name' => 'Manufacturing',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'payroll' => [
        'name' => 'Payroll',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'accounting' => [
        'name' => 'Accounting',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'projects' => [
        'name' => 'Projects',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'quality' => [
        'name' => 'Quality',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'bi' => [
        'name' => 'BI',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
    'website' => [
        'name' => 'Website',
        'monthly' => 0.0,
        'yearly' => 0.0,
        'enabled' => false,
    ],
];
