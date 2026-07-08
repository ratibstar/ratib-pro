<?php

declare(strict_types=1);

/**
 * Enterprise Admin Panel navigation.
 * Each item is shown when the actor has any of the listed permissions.
 *
 * @return list<array{key:string,route:string,icon:string,permissions:list<string>,group:string}>
 */
return [
    [
        'key' => 'dashboard',
        'route' => '/admin',
        'icon' => 'bi-speedometer2',
        'permissions' => ['catalog.dashboard.view', 'catalog.products.view'],
        'group' => 'overview',
    ],
    [
        'key' => 'products',
        'route' => '/admin/products',
        'icon' => 'bi-box-seam',
        'permissions' => ['catalog.products.view', 'catalog.products.create', 'catalog.products.edit'],
        'group' => 'catalog',
    ],
    [
        'key' => 'categories',
        'route' => '/admin/categories',
        'icon' => 'bi-diagram-3',
        'permissions' => ['catalog.categories.manage', 'catalog.category_schemas.manage'],
        'group' => 'catalog',
    ],
    [
        'key' => 'brands',
        'route' => '/admin/brands',
        'icon' => 'bi-award',
        'permissions' => ['catalog.brands.manage'],
        'group' => 'catalog',
    ],
    [
        'key' => 'suppliers',
        'route' => '/admin/suppliers',
        'icon' => 'bi-truck',
        'permissions' => ['catalog.suppliers.manage'],
        'group' => 'catalog',
    ],
    [
        'key' => 'families',
        'route' => '/admin/families',
        'icon' => 'bi-collection',
        'permissions' => ['catalog.families.manage'],
        'group' => 'catalog',
    ],
    [
        'key' => 'attributes',
        'route' => '/admin/attributes',
        'icon' => 'bi-sliders',
        'permissions' => ['catalog.attributes.manage'],
        'group' => 'catalog',
    ],
    [
        'key' => 'collections',
        'route' => '/admin/collections',
        'icon' => 'bi-grid-3x3-gap',
        'permissions' => ['catalog.collections.manage'],
        'group' => 'catalog',
    ],
    [
        'key' => 'channels',
        'route' => '/admin/channels',
        'icon' => 'bi-broadcast',
        'permissions' => ['catalog.channels.manage'],
        'group' => 'commerce',
    ],
    [
        'key' => 'pricing',
        'route' => '/admin/pricing',
        'icon' => 'bi-currency-exchange',
        'permissions' => ['catalog.pricing.manage'],
        'group' => 'commerce',
    ],
    [
        'key' => 'media',
        'route' => '/admin/media',
        'icon' => 'bi-images',
        'permissions' => ['catalog.media.upload', 'catalog.files.upload'],
        'group' => 'commerce',
    ],
    [
        'key' => 'import_export',
        'route' => '/admin/import-export',
        'icon' => 'bi-arrow-left-right',
        'permissions' => [
            'catalog.import.csv',
            'catalog.import.excel',
            'catalog.import.export',
            'catalog.import.history',
            'catalog.bulk.manage',
        ],
        'group' => 'operations',
    ],
    [
        'key' => 'search',
        'route' => '/admin/search',
        'icon' => 'bi-search',
        'permissions' => ['catalog.products.view', 'catalog.search.manage'],
        'group' => 'operations',
    ],
    [
        'key' => 'change_requests',
        'route' => '/admin/change-requests',
        'icon' => 'bi-journal-check',
        'permissions' => [
            'catalog.change_requests.submit',
            'catalog.change_requests.review',
            'catalog.change_requests.apply',
        ],
        'group' => 'governance',
    ],
    [
        'key' => 'workflow',
        'route' => '/admin/workflow',
        'icon' => 'bi-diagram-2',
        'permissions' => [
            'catalog.workflow.submit',
            'catalog.workflow.approve',
            'catalog.workflow.publish',
            'catalog.workflow.comment',
        ],
        'group' => 'governance',
    ],
    [
        'key' => 'seo',
        'route' => '/admin/seo',
        'icon' => 'bi-globe2',
        'permissions' => ['catalog.products.edit', 'catalog.products.view'],
        'group' => 'governance',
    ],
    [
        'key' => 'versions',
        'route' => '/admin/versions',
        'icon' => 'bi-clock-history',
        'permissions' => ['catalog.products.view', 'catalog.products.edit'],
        'group' => 'governance',
    ],
    [
        'key' => 'duplicates',
        'route' => '/admin/duplicates',
        'icon' => 'bi-files',
        'permissions' => ['catalog.duplicates.view', 'catalog.duplicates.resolve', 'catalog.duplicate_rules.manage'],
        'group' => 'governance',
    ],
    [
        'key' => 'saved_filters',
        'route' => '/admin/saved-filters',
        'icon' => 'bi-funnel',
        'permissions' => ['catalog.saved_filters.manage'],
        'group' => 'operations',
    ],
    [
        'key' => 'erp_sync',
        'route' => '/admin/erp-sync',
        'icon' => 'bi-arrow-repeat',
        'permissions' => ['catalog.sync.view'],
        'group' => 'integrations',
    ],
    [
        'key' => 'webhooks',
        'route' => '/admin/webhooks',
        'icon' => 'bi-link-45deg',
        'permissions' => ['catalog.webhooks.manage'],
        'group' => 'integrations',
    ],
    [
        'key' => 'queue',
        'route' => '/admin/queue',
        'icon' => 'bi-hourglass-split',
        'permissions' => ['catalog.search.manage', 'catalog.bulk.manage'],
        'group' => 'integrations',
    ],
    [
        'key' => 'audit_logs',
        'route' => '/admin/audit-logs',
        'icon' => 'bi-shield-lock',
        'permissions' => ['catalog.dashboard.view', 'catalog.rbac.manage', 'catalog.products.view'],
        'group' => 'system',
    ],
    [
        'key' => 'health',
        'route' => '/admin/health',
        'icon' => 'bi-heart-pulse',
        'permissions' => ['catalog.dashboard.view', 'catalog.search.manage'],
        'group' => 'system',
    ],
    [
        'key' => 'settings',
        'route' => '/admin/settings',
        'icon' => 'bi-gear',
        'permissions' => ['catalog.rbac.manage', 'catalog.completeness.manage'],
        'group' => 'system',
    ],
];
