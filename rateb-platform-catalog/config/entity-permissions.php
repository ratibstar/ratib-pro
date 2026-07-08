<?php

declare(strict_types=1);

/**
 * Permission slug registry — seeded into platform_permissions (§12).
 * Architecture reference: PHASE-1-ARCHITECTURE.md v1.3.1
 */
return [
    'catalog.dashboard.view' => ['module' => 'dashboard', 'description' => 'View catalog dashboard'],
    'catalog.products.view' => ['module' => 'products', 'description' => 'View products'],
    'catalog.products.create' => ['module' => 'products', 'description' => 'Create products'],
    'catalog.products.edit' => ['module' => 'products', 'description' => 'Edit products'],
    'catalog.products.delete' => ['module' => 'products', 'description' => 'Delete products'],
    'catalog.products.publish' => ['module' => 'products', 'description' => 'Publish products'],
    'catalog.categories.manage' => ['module' => 'categories', 'description' => 'Manage categories'],
    'catalog.brands.manage' => ['module' => 'brands', 'description' => 'Manage brands'],
    'catalog.suppliers.manage' => ['module' => 'suppliers', 'description' => 'Manage suppliers'],
    'catalog.families.manage' => ['module' => 'families', 'description' => 'Manage product families'],
    'catalog.attributes.manage' => ['module' => 'attributes', 'description' => 'Manage attributes'],
    'catalog.variants.manage' => ['module' => 'products', 'description' => 'Manage variants'],
    'catalog.bundles.manage' => ['module' => 'products', 'description' => 'Manage bundles'],
    'catalog.relations.manage' => ['module' => 'products', 'description' => 'Manage product relations'],
    'catalog.media.upload' => ['module' => 'products', 'description' => 'Upload media'],
    'catalog.files.upload' => ['module' => 'products', 'description' => 'Upload files'],
    'catalog.asset_types.manage' => ['module' => 'products', 'description' => 'Manage asset types'],
    'catalog.rbac.manage' => ['module' => 'rbac', 'description' => 'Manage RBAC'],
    'catalog.completeness.manage' => ['module' => 'completeness', 'description' => 'Manage completeness rules'],
    'catalog.category_schemas.manage' => ['module' => 'categories', 'description' => 'Manage category attribute schemas'],
    'catalog.search.manage' => ['module' => 'search', 'description' => 'Manage search indexes'],
    'catalog.workflow.submit' => ['module' => 'workflow', 'description' => 'Submit products for review'],
    'catalog.workflow.approve' => ['module' => 'workflow', 'description' => 'Approve or reject products'],
    'catalog.workflow.publish' => ['module' => 'workflow', 'description' => 'Publish or archive products'],
    'catalog.workflow.comment' => ['module' => 'workflow', 'description' => 'Add workflow comments'],
    'catalog.change_requests.submit' => ['module' => 'change_requests', 'description' => 'Submit change requests'],
    'catalog.change_requests.review' => ['module' => 'change_requests', 'description' => 'Review change requests'],
    'catalog.change_requests.apply' => ['module' => 'change_requests', 'description' => 'Apply approved change requests'],
    'catalog.import.export' => ['module' => 'import', 'description' => 'Export catalog data'],
    'catalog.import.csv' => ['module' => 'import', 'description' => 'Import catalog data via CSV staging'],
    'catalog.import.excel' => ['module' => 'import', 'description' => 'Import catalog data via Excel'],
    'catalog.import.ftp' => ['module' => 'import', 'description' => 'Import catalog data via FTP'],
    'catalog.import.history' => ['module' => 'import', 'description' => 'View import batch history and previews'],
    'catalog.sync.view' => ['module' => 'sync', 'description' => 'View ERP sync payloads'],
    'catalog.collections.manage' => ['module' => 'collections', 'description' => 'Manage collections'],
    'catalog.channels.manage' => ['module' => 'channels', 'description' => 'Manage channels'],
    'catalog.duplicates.view' => ['module' => 'duplicates', 'description' => 'View duplicate groups'],
    'catalog.duplicates.resolve' => ['module' => 'duplicates', 'description' => 'Resolve duplicate groups'],
    'catalog.duplicate_rules.manage' => ['module' => 'duplicates', 'description' => 'Manage duplicate detection rules'],
    'catalog.saved_filters.manage' => ['module' => 'saved_filters', 'description' => 'Manage saved filters'],
    'catalog.webhooks.manage' => ['module' => 'webhooks', 'description' => 'Manage webhook subscriptions'],
    'catalog.pricing.manage' => ['module' => 'pricing', 'description' => 'Manage product prices'],
    'catalog.bulk.manage' => ['module' => 'bulk', 'description' => 'Manage bulk publish, archive, and export jobs'],
];
