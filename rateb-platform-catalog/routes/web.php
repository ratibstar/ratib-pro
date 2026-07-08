<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Http\Controllers\Admin\AdminPageController;

/** @var \Rateb\PlatformCatalog\Core\Router $router */

$router->get('/', [AdminPageController::class, 'dashboard']);
$router->get('/admin', [AdminPageController::class, 'dashboard']);
$router->get('/admin/products', [AdminPageController::class, 'products']);
$router->get('/admin/categories', [AdminPageController::class, 'categories']);
$router->get('/admin/brands', [AdminPageController::class, 'brands']);
$router->get('/admin/suppliers', [AdminPageController::class, 'suppliers']);
$router->get('/admin/families', [AdminPageController::class, 'families']);
$router->get('/admin/attributes', [AdminPageController::class, 'attributes']);
$router->get('/admin/collections', [AdminPageController::class, 'collections']);
$router->get('/admin/channels', [AdminPageController::class, 'channels']);
$router->get('/admin/pricing', [AdminPageController::class, 'pricing']);
$router->get('/admin/media', [AdminPageController::class, 'media']);
$router->get('/admin/import-export', [AdminPageController::class, 'importExport']);
$router->get('/admin/search', [AdminPageController::class, 'search']);
$router->get('/admin/change-requests', [AdminPageController::class, 'changeRequests']);
$router->get('/admin/workflow', [AdminPageController::class, 'workflow']);
$router->get('/admin/seo', [AdminPageController::class, 'seo']);
$router->get('/admin/versions', [AdminPageController::class, 'versions']);
$router->get('/admin/duplicates', [AdminPageController::class, 'duplicates']);
$router->get('/admin/saved-filters', [AdminPageController::class, 'savedFilters']);
$router->get('/admin/erp-sync', [AdminPageController::class, 'erpSync']);
$router->get('/admin/webhooks', [AdminPageController::class, 'webhooks']);
$router->get('/admin/queue', [AdminPageController::class, 'queue']);
$router->get('/admin/audit-logs', [AdminPageController::class, 'auditLogs']);
$router->get('/admin/health', [AdminPageController::class, 'health']);
$router->get('/admin/settings', [AdminPageController::class, 'settings']);
