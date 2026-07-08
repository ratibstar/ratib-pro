<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Http\Controllers\Api\V1\AttributeController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\AssetTypeController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\BrandController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\CategoryController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\CategorySchemaController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\HealthController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\MediaServeController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\SignedStorageController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductFamilyController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductMediaController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductRelationshipController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\AdminCompletenessController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\AdminQueueController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\RbacAdminController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ChangeRequestController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\CompletenessController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\JobController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductSeoController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductVersionController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\SearchController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\WorkflowController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\SupplierController;

/** @var \Rateb\PlatformCatalog\Core\Router $router */

$router->get('/health', [HealthController::class, 'health']);
$router->get('/ready', [HealthController::class, 'ready']);

$router->get('/catalog/categories', [CategoryController::class, 'index']);
$router->get('/catalog/categories/{uuid}/attribute-schema', [CategorySchemaController::class, 'show']);
$router->put('/catalog/categories/{uuid}/attribute-schema', [CategorySchemaController::class, 'update']);

$router->get('/catalog/brands', [BrandController::class, 'index']);
$router->get('/catalog/brands/{uuid}', [BrandController::class, 'show']);

$router->get('/catalog/suppliers', [SupplierController::class, 'index']);
$router->get('/catalog/suppliers/{uuid}', [SupplierController::class, 'show']);

$router->get('/catalog/families', [ProductFamilyController::class, 'index']);
$router->get('/catalog/families/{uuid}', [ProductFamilyController::class, 'show']);
$router->get('/catalog/families/{uuid}/products', [ProductController::class, 'listByFamily']);

$router->get('/catalog/attributes', [AttributeController::class, 'index']);
$router->get('/catalog/attributes/{uuid}', [AttributeController::class, 'show']);

$router->get('/catalog/products', [ProductController::class, 'index']);
$router->get('/catalog/products/{uuid}', [ProductController::class, 'show']);
$router->post('/catalog/products', [ProductController::class, 'store']);
$router->put('/catalog/products/{uuid}', [ProductController::class, 'update']);
$router->delete('/catalog/products/{uuid}', [ProductController::class, 'destroy']);

$router->get('/catalog/products/{uuid}/seo', [ProductSeoController::class, 'show']);
$router->put('/catalog/products/{uuid}/seo', [ProductSeoController::class, 'update']);

$router->get('/catalog/products/{uuid}/variants', [ProductRelationshipController::class, 'listVariants']);
$router->post('/catalog/products/{uuid}/variants', [ProductRelationshipController::class, 'storeVariant']);
$router->get('/catalog/products/{uuid}/barcodes', [ProductRelationshipController::class, 'listBarcodes']);
$router->post('/catalog/products/{uuid}/barcodes', [ProductRelationshipController::class, 'storeBarcode']);
$router->delete('/catalog/products/{uuid}/barcodes/{barcodeUuid}', [ProductRelationshipController::class, 'destroyBarcode']);
$router->get('/catalog/products/{uuid}/attributes', [ProductRelationshipController::class, 'listAttributes']);
$router->put('/catalog/products/{uuid}/attributes', [ProductRelationshipController::class, 'replaceAttributes']);
$router->get('/catalog/products/{uuid}/bundle', [ProductRelationshipController::class, 'showBundle']);
$router->put('/catalog/products/{uuid}/bundle', [ProductRelationshipController::class, 'replaceBundle']);
$router->get('/catalog/products/{uuid}/relations', [ProductRelationshipController::class, 'listRelations']);
$router->post('/catalog/products/{uuid}/relations', [ProductRelationshipController::class, 'storeRelation']);

$router->get('/catalog/asset-types', [AssetTypeController::class, 'index']);
$router->get('/catalog/products/{uuid}/images', [ProductMediaController::class, 'listImages']);
$router->post('/catalog/products/{uuid}/images', [ProductMediaController::class, 'storeImage']);
$router->delete('/catalog/products/{uuid}/images/{imageUuid}', [ProductMediaController::class, 'destroyImage']);
$router->get('/catalog/products/{uuid}/files', [ProductMediaController::class, 'listFiles']);
$router->post('/catalog/products/{uuid}/files', [ProductMediaController::class, 'storeFile']);
$router->delete('/catalog/products/{uuid}/files/{fileUuid}', [ProductMediaController::class, 'destroyFile']);
$router->get('/catalog/products/{uuid}/videos', [ProductMediaController::class, 'listVideos']);
$router->post('/catalog/products/{uuid}/videos', [ProductMediaController::class, 'storeVideo']);
$router->get('/catalog/media/{uuid}/{variant}', [MediaServeController::class, 'serveImage']);
$router->get('/catalog/files/{uuid}', [MediaServeController::class, 'serveFile']);
$router->get('/catalog/signed-storage', [SignedStorageController::class, 'serve']);

$router->get('/catalog/search', [SearchController::class, 'search']);
$router->get('/catalog/search/variants', [SearchController::class, 'searchVariants']);
$router->get('/catalog/search/barcode/{barcode}', [SearchController::class, 'barcode']);

$router->get('/catalog/jobs/{job_id}', [JobController::class, 'show']);

$router->post('/catalog/admin/search/reindex', [AdminQueueController::class, 'requestReindex']);
$router->get('/catalog/admin/queue/status', [AdminQueueController::class, 'queueStatus']);
$router->post('/catalog/admin/jobs/{job_id}/replay', [AdminQueueController::class, 'replayJob']);

$router->post('/catalog/products/{uuid}/workflow/submit', [WorkflowController::class, 'submit']);
$router->post('/catalog/products/{uuid}/workflow/approve', [WorkflowController::class, 'approve']);
$router->post('/catalog/products/{uuid}/workflow/reject', [WorkflowController::class, 'reject']);
$router->post('/catalog/products/{uuid}/workflow/publish', [WorkflowController::class, 'publish']);
$router->post('/catalog/products/{uuid}/workflow/archive', [WorkflowController::class, 'archive']);
$router->post('/catalog/products/{uuid}/workflow/restore', [WorkflowController::class, 'restore']);
$router->get('/catalog/products/{uuid}/workflow/history', [WorkflowController::class, 'listHistory']);
$router->get('/catalog/products/{uuid}/workflow/comments', [WorkflowController::class, 'listComments']);
$router->post('/catalog/products/{uuid}/workflow/comments', [WorkflowController::class, 'storeComment']);

$router->get('/catalog/products/{uuid}/versions', [ProductVersionController::class, 'index']);
$router->get('/catalog/products/{uuid}/versions/compare', [ProductVersionController::class, 'compare']);
$router->get('/catalog/products/{uuid}/versions/{version}', [ProductVersionController::class, 'show']);
$router->post('/catalog/products/{uuid}/versions/{version}/restore', [ProductVersionController::class, 'restore']);

$router->get('/catalog/products/{uuid}/completeness', [CompletenessController::class, 'show']);

$router->get('/catalog/change-requests', [ChangeRequestController::class, 'index']);
$router->get('/catalog/change-requests/{uuid}', [ChangeRequestController::class, 'show']);
$router->post('/catalog/change-requests', [ChangeRequestController::class, 'store']);
$router->post('/catalog/change-requests/{uuid}/assign-reviewer', [ChangeRequestController::class, 'assignReviewer']);
$router->post('/catalog/change-requests/{uuid}/approve', [ChangeRequestController::class, 'approve']);
$router->post('/catalog/change-requests/{uuid}/reject', [ChangeRequestController::class, 'reject']);
$router->post('/catalog/change-requests/{uuid}/apply', [ChangeRequestController::class, 'apply']);

$router->get('/catalog/admin/completeness-rules', [AdminCompletenessController::class, 'index']);
$router->put('/catalog/admin/completeness-rules/{code}', [AdminCompletenessController::class, 'update']);

$router->get('/catalog/admin/roles', [RbacAdminController::class, 'listRoles']);
$router->get('/catalog/admin/users/{uuid}/roles', [RbacAdminController::class, 'getUserRoles']);
$router->put('/catalog/admin/users/{uuid}/roles', [RbacAdminController::class, 'assignUserRoles']);
$router->patch('/catalog/admin/roles/{uuid}', [RbacAdminController::class, 'patchRole']);
