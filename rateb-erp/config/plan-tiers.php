<?php
declare(strict_types=1);

/**
 * Canonical SaaS plan tiers — limits, pricing, and module bundles.
 * Synced to rateb_plans via migration 148 + MigrationService::repairMarketingPlansCanonicalIfNeeded().
 */
return [
    'starter' => [
        'name' => 'Starter',
        'description' => 'Essential procurement for small clinics',
        'price_monthly' => 1500.00,
        'price_yearly' => 16000.00,
        'max_users' => 5,
        'max_branches' => 3,
        'max_storage_mb' => 512,
        'modules' => [
            'dashboard',
            'procurement',
            'inventory',
            'suppliers',
            'reports',
            'notifications',
        ],
    ],
    'professional' => [
        'name' => 'Professional',
        'description' => 'Full procurement, inventory, contracts, and reporting suite',
        'price_monthly' => 1800.00,
        'price_yearly' => 19999.00,
        'max_users' => 25,
        'max_branches' => 5,
        'max_storage_mb' => 2048,
        'modules' => [
            'dashboard',
            'procurement',
            'inventory',
            'suppliers',
            'assets',
            'contracts',
            'reports',
            'accounting',
            'documents',
            'workflows',
            'hr',
            'branches',
            'notifications',
        ],
    ],
    'enterprise' => [
        'name' => 'Enterprise',
        'description' => 'Complete healthcare ERP with all modules',
        'price_monthly' => 3000.00,
        'price_yearly' => 29999.00,
        'max_users' => 100,
        'max_branches' => 25,
        'max_storage_mb' => 10240,
        'modules' => [
            'dashboard',
            'procurement',
            'inventory',
            'suppliers',
            'assets',
            'contracts',
            'tenders',
            'reports',
            'medical_devices',
            'accounting',
            'documents',
            'workflows',
            'hr',
            'branches',
            'notifications',
            'pos',
        ],
    ],
];
