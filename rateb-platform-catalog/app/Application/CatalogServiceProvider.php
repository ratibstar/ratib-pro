<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application;

use Rateb\PlatformCatalog\Application\Contracts\StructuredLoggerInterface;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Support\GatewayTrustConfig;
use Rateb\PlatformCatalog\Application\Support\PlatformIdentityResolver;
use Rateb\PlatformCatalog\Application\Policies\AssetTypePolicy;
use Rateb\PlatformCatalog\Application\Policies\BulkPolicy;
use Rateb\PlatformCatalog\Application\Policies\ChannelPolicy;
use Rateb\PlatformCatalog\Application\Policies\CollectionPolicy;
use Rateb\PlatformCatalog\Application\Policies\DuplicatePolicy;
use Rateb\PlatformCatalog\Application\Policies\ErpSyncPolicy;
use Rateb\PlatformCatalog\Application\Policies\ImportPolicy;
use Rateb\PlatformCatalog\Application\Policies\PricingPolicy;
use Rateb\PlatformCatalog\Application\Policies\SavedFilterPolicy;
use Rateb\PlatformCatalog\Application\Policies\WebhookPolicy;
use Rateb\PlatformCatalog\Application\Policies\AttributePolicy;
use Rateb\PlatformCatalog\Application\Policies\ChangeRequestPolicy;
use Rateb\PlatformCatalog\Application\Policies\CompletenessPolicy;
use Rateb\PlatformCatalog\Application\Policies\CategoryPolicy;
use Rateb\PlatformCatalog\Application\Policies\FilePolicy;
use Rateb\PlatformCatalog\Application\Policies\MediaPolicy;
use Rateb\PlatformCatalog\Application\Listeners\IntegrationOutboxListener;
use Rateb\PlatformCatalog\Application\Listeners\SearchIndexingListener;
use Rateb\PlatformCatalog\Application\Policies\CategorySchemaPolicy;
use Rateb\PlatformCatalog\Application\Policies\SessionRbacPolicyGuard;
use Rateb\PlatformCatalog\Application\Policies\QueueAdminPolicy;
use Rateb\PlatformCatalog\Application\Policies\RbacAdminPolicy;
use Rateb\PlatformCatalog\Application\Policies\SearchPolicy;
use Rateb\PlatformCatalog\Application\Policies\PolicyGuardInterface;
use Rateb\PlatformCatalog\Application\Policies\ProductAttributePolicy;
use Rateb\PlatformCatalog\Application\Policies\ProductBarcodePolicy;
use Rateb\PlatformCatalog\Application\Policies\ProductBundlePolicy;
use Rateb\PlatformCatalog\Application\Policies\ProductFamilyPolicy;
use Rateb\PlatformCatalog\Application\Policies\ProductPolicy;
use Rateb\PlatformCatalog\Application\Policies\ProductSeoPolicy;
use Rateb\PlatformCatalog\Application\Policies\ProductRelationPolicy;
use Rateb\PlatformCatalog\Application\Policies\ProductVariantPolicy;
use Rateb\PlatformCatalog\Application\Policies\SupplierPolicy;
use Rateb\PlatformCatalog\Application\Policies\WorkflowPolicy;
use Rateb\PlatformCatalog\Application\Policies\VideoPolicy;
use Rateb\PlatformCatalog\Application\Services\BulkService;
use Rateb\PlatformCatalog\Application\Services\ChannelService;
use Rateb\PlatformCatalog\Application\Services\CollectionService;
use Rateb\PlatformCatalog\Application\Services\DuplicateService;
use Rateb\PlatformCatalog\Application\Services\ErpSyncService;
use Rateb\PlatformCatalog\Application\Services\ImportService;
use Rateb\PlatformCatalog\Application\Services\IntegrationOutboxService;
use Rateb\PlatformCatalog\Application\Services\PricingService;
use Rateb\PlatformCatalog\Application\Services\SavedFilterService;
use Rateb\PlatformCatalog\Application\Services\WebhookService;
use Rateb\PlatformCatalog\Application\Services\AssetTypeService;
use Rateb\PlatformCatalog\Application\Services\AttributeService;
use Rateb\PlatformCatalog\Application\Services\BrandService;
use Rateb\PlatformCatalog\Application\Services\CategoryService;
use Rateb\PlatformCatalog\Application\Services\CategorySchemaService;
use Rateb\PlatformCatalog\Application\Services\ChangeRequestService;
use Rateb\PlatformCatalog\Application\Services\CompletenessService;
use Rateb\PlatformCatalog\Application\Services\ConcurrencyService;
use Rateb\PlatformCatalog\Application\Services\FileService;
use Rateb\PlatformCatalog\Application\Services\HealthService;
use Rateb\PlatformCatalog\Application\Services\LocaleResolverService;
use Rateb\PlatformCatalog\Application\Services\MediaService;
use Rateb\PlatformCatalog\Application\Services\MigrationService;
use Rateb\PlatformCatalog\Application\Services\ProductAttributeService;
use Rateb\PlatformCatalog\Application\Services\ProductBarcodeService;
use Rateb\PlatformCatalog\Application\Services\ProductBundleService;
use Rateb\PlatformCatalog\Application\Services\ProductFamilyService;
use Rateb\PlatformCatalog\Application\Services\ProductRelationService;
use Rateb\PlatformCatalog\Application\Services\JobProcessorService;
use Rateb\PlatformCatalog\Application\Services\QueueService;
use Rateb\PlatformCatalog\Application\Services\QueueWorkerService;
use Rateb\PlatformCatalog\Application\Services\SchedulerService;
use Rateb\PlatformCatalog\Application\Services\SearchAdminService;
use Rateb\PlatformCatalog\Application\Services\SearchIndexerService;
use Rateb\PlatformCatalog\Application\Services\SearchQueryService;
use Rateb\PlatformCatalog\Application\Services\AuditEventService;
use Rateb\PlatformCatalog\Application\Services\ProductSnapshotBuilder;
use Rateb\PlatformCatalog\Application\Services\ProductSeoService;
use Rateb\PlatformCatalog\Application\Services\ProductService;
use Rateb\PlatformCatalog\Application\Services\RbacAdminService;
use Rateb\PlatformCatalog\Application\Services\RbacService;
use Rateb\PlatformCatalog\Application\Services\ScheduledPublishService;
use Rateb\PlatformCatalog\Application\Services\ProductVersionService;
use Rateb\PlatformCatalog\Application\Services\ProductVariantService;
use Rateb\PlatformCatalog\Application\Services\SupplierService;
use Rateb\PlatformCatalog\Application\Services\VideoService;
use Rateb\PlatformCatalog\Application\Validators\ProductSeoValidator;
use Rateb\PlatformCatalog\Application\Validators\RbacAdminValidator;
use Rateb\PlatformCatalog\Application\Validators\SkuBarcodeUniquenessValidator;
use Rateb\PlatformCatalog\Application\Validators\UploadValidator;
use Rateb\PlatformCatalog\Application\Services\WorkflowCommentService;
use Rateb\PlatformCatalog\Application\Services\WorkflowService;
use Rateb\PlatformCatalog\Core\Container;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\BulkController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ChannelController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\CollectionController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\DuplicateController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ErpSyncController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ImportController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\PricingController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\SavedFilterController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\WebhookController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\AssetTypeController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\AttributeController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\CategoryController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\CategorySchemaController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\BrandController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\AdminCompletenessController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ChangeRequestController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\CompletenessController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductRelationshipController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\SupplierController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\AdminQueueController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\HealthController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\JobController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\SearchController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\SignedStorageController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\MediaServeController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductFamilyController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductMediaController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductSeoController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\ProductVersionController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\RbacAdminController;
use Rateb\PlatformCatalog\Http\Controllers\Api\V1\WorkflowController;
use Rateb\PlatformCatalog\Http\Middleware\CorrelationIdMiddleware;
use Rateb\PlatformCatalog\Http\Middleware\IdempotencyMiddleware;
use Rateb\PlatformCatalog\Http\Middleware\RateLimitMiddleware;
use Rateb\PlatformCatalog\Infrastructure\Logging\FileStructuredLogger;
use Rateb\PlatformCatalog\Infrastructure\Http\HttpClientInterface;
use Rateb\PlatformCatalog\Infrastructure\Http\NativeHttpClient;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations\MigrationRunner;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\BarcodeUniquenessReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SkuUniquenessReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ImportBatchReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ImportBatchWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WebhookSubscriptionReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WebhookSubscriptionWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WebhookDeliveryWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CollectionReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CollectionWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChannelReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChannelWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductPriceReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductPriceWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\DuplicateReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\DuplicateWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SavedFilterReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SavedFilterWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ErpSyncReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IntegrationOutboxReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IntegrationOutboxWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\MediaJobReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\MediaJobWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlBarcodeUniquenessReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlSkuUniquenessReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlImportBatchReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlImportBatchWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlWebhookSubscriptionReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlWebhookSubscriptionWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlWebhookDeliveryWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlCollectionReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlCollectionWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlChannelReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlChannelWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductPriceReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductPriceWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlDuplicateReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlDuplicateWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlSavedFilterReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlSavedFilterWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlErpSyncReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIntegrationOutboxReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIntegrationOutboxWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlMediaJobReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlMediaJobWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AssetTypeReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AssetTypeWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AttributeReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AttributeWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\BrandRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategoryRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductAttributeReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductAttributeWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBarcodeReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBarcodeWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBundleReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBundleWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFamilyReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFamilyWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFileReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductFileWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductImageReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductImageWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexQueueReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexQueueWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SearchIndexReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductRelationReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductRelationWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductTranslationReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductTranslationWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVariantReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVariantWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVideoReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVideoWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AuditEventWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategorySchemaWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessDataReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotRestoreRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotGraphReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotGraphWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacAdminReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacAdminWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ScheduledPublishReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ScheduledPublishWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategorySchemaReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChangeRequestWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChangeRequestReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessRuleReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessRuleWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductCompletenessReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductCompletenessWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVersionReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVersionWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlAttributeReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlAssetTypeReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlAssetTypeWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlAttributeWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlBrandRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlCategoryRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductAttributeReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductAttributeWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductBarcodeReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductBarcodeWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductBundleReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductBundleWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductFamilyReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductFamilyWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductFileReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductFileWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductImageReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlJobQueueReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlJobQueueWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlSearchIndexQueueReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlSearchIndexQueueWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlSearchIndexReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductImageWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSeoReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSeoRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSeoWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductRelationReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductRelationWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductTranslationReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductTranslationWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductVariantReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductVariantWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductVideoReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductVideoWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SupplierRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlAuditEventWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlCategorySchemaWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlCompletenessDataReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSnapshotRestoreRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductWorkflowReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlRbacAdminReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlRbacAdminWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlRbacReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSnapshotGraphReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSnapshotGraphWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlScheduledPublishReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlScheduledPublishWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlCategorySchemaReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlChangeRequestReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlChangeRequestWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlCompletenessRuleReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlCompletenessRuleWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductCompletenessReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductCompletenessWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductVersionReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductVersionWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductWorkflowWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlWorkflowCommentReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlWorkflowCommentWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlWorkflowReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlSupplierRepository;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\CleanupJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\BulkPublishJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\BulkArchiveJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\ExportChunkJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\ImportChunkJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\OutboxDispatchJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\DuplicateScanJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\WebhookDispatchJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\HealthJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\MediaProcessJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\SearchFullReindexJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\SearchReindexJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\Handlers\VariantReindexJobHandler;
use Rateb\PlatformCatalog\Infrastructure\Queue\QueueAdapterFactory;
use Rateb\PlatformCatalog\Infrastructure\Queue\QueueAdapterInterface;
use Rateb\PlatformCatalog\Infrastructure\Search\SearchAdapterFactory;
use Rateb\PlatformCatalog\Infrastructure\Search\SearchAdapterInterface;
use Rateb\PlatformCatalog\Infrastructure\Storage\SignedUrlGenerator;
use Rateb\PlatformCatalog\Infrastructure\Storage\SignedUrlVerifier;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterFactory;
use Rateb\PlatformCatalog\Infrastructure\Storage\StorageAdapterInterface;

final class CatalogServiceProvider
{
    public static function register(Container $container): void
    {
        $container->set(EventDispatcher::class, static fn (): EventDispatcher => new EventDispatcher());
        $container->set(MigrationRunner::class, static fn (): MigrationRunner => new MigrationRunner());
        $container->set(MigrationService::class, static fn (Container $c): MigrationService => new MigrationService(
            $c->get(MigrationRunner::class)
        ));
        $container->set(HealthService::class, static fn (): HealthService => new HealthService());
        $container->set(LocaleResolverService::class, static fn (): LocaleResolverService => new LocaleResolverService());
        $container->set(ConcurrencyService::class, static fn (): ConcurrencyService => new ConcurrencyService());
        $container->set(RbacReadRepositoryInterface::class, static fn (): RbacReadRepositoryInterface => new MysqlRbacReadRepository());
        $container->set(RbacAdminReadRepositoryInterface::class, static fn (): RbacAdminReadRepositoryInterface => new MysqlRbacAdminReadRepository());
        $container->set(RbacAdminWriteRepositoryInterface::class, static fn (): RbacAdminWriteRepositoryInterface => new MysqlRbacAdminWriteRepository());
        $container->set(ScheduledPublishReadRepositoryInterface::class, static fn (): ScheduledPublishReadRepositoryInterface => new MysqlScheduledPublishReadRepository());
        $container->set(ScheduledPublishWriteRepositoryInterface::class, static fn (): ScheduledPublishWriteRepositoryInterface => new MysqlScheduledPublishWriteRepository());
        $container->set(ProductSnapshotGraphReadRepositoryInterface::class, static fn (Container $c): ProductSnapshotGraphReadRepositoryInterface => new MysqlProductSnapshotGraphReadRepository(
            null,
            null,
            $c->get(ProductVariantReadRepositoryInterface::class),
            $c->get(ProductBarcodeReadRepositoryInterface::class),
            $c->get(ProductBundleReadRepositoryInterface::class),
            $c->get(ProductImageReadRepositoryInterface::class),
            $c->get(ProductFileReadRepositoryInterface::class),
            $c->get(ProductVideoReadRepositoryInterface::class)
        ));
        $container->set(ProductSnapshotGraphWriteRepositoryInterface::class, static fn (): ProductSnapshotGraphWriteRepositoryInterface => new MysqlProductSnapshotGraphWriteRepository());
        $container->set(GatewayTrustConfig::class, static fn (): GatewayTrustConfig => new GatewayTrustConfig());
        $container->set(PlatformIdentityResolver::class, static fn (Container $c): PlatformIdentityResolver => new PlatformIdentityResolver(
            $c->get(RbacService::class),
            $c->get(GatewayTrustConfig::class)
        ));
        $container->set(StructuredLoggerInterface::class, static function (): StructuredLoggerInterface {
            $storageRoot = defined('RATEB_PLATFORM_CATALOG_STORAGE_PATH')
                ? (string) RATEB_PLATFORM_CATALOG_STORAGE_PATH
                : (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/storage' : dirname(__DIR__, 2) . '/storage');

            return new FileStructuredLogger($storageRoot . '/logs/catalog.log');
        });
        $container->set(RbacService::class, static fn (Container $c): RbacService => new RbacService(
            $c->get(RbacReadRepositoryInterface::class)
        ));
        $container->set(PolicyGuardInterface::class, static fn (Container $c): PolicyGuardInterface => new SessionRbacPolicyGuard(
            $c->get(PlatformIdentityResolver::class),
            $c->get(RbacService::class)
        ));
        $container->set(AuditEventWriteRepositoryInterface::class, static fn (): AuditEventWriteRepositoryInterface => new MysqlAuditEventWriteRepository());
        $container->set(AuditEventService::class, static fn (Container $c): AuditEventService => new AuditEventService(
            $c->get(AuditEventWriteRepositoryInterface::class)
        ));
        $container->set(StorageAdapterInterface::class, static fn (): StorageAdapterInterface => StorageAdapterFactory::create());
        $container->set(HttpClientInterface::class, static fn (): HttpClientInterface => new NativeHttpClient());
        $container->set(SignedUrlGenerator::class, static function (): SignedUrlGenerator {
            $cdnBase = defined('RATEB_PLATFORM_CATALOG_CDN_BASE')
                ? (string) RATEB_PLATFORM_CATALOG_CDN_BASE
                : '';

            return new SignedUrlGenerator(
                (string) (getenv('SIGNED_URL_SECRET') ?: ''),
                $cdnBase
            );
        });
        $container->set(SignedUrlVerifier::class, static fn (Container $c): SignedUrlVerifier => new SignedUrlVerifier(
            $c->get(SignedUrlGenerator::class)
        ));
        $container->set(IdempotencyReadRepositoryInterface::class, static fn (): IdempotencyReadRepositoryInterface => new MysqlIdempotencyReadRepository());
        $container->set(IdempotencyWriteRepositoryInterface::class, static fn (): IdempotencyWriteRepositoryInterface => new MysqlIdempotencyWriteRepository());
        $container->set(UploadValidator::class, static fn (Container $c): UploadValidator => new UploadValidator(
            $c->get(AssetTypeReadRepositoryInterface::class)
        ));
        $container->set(IdempotencyMiddleware::class, static fn (Container $c): IdempotencyMiddleware => new IdempotencyMiddleware(
            $c->get(IdempotencyReadRepositoryInterface::class),
            $c->get(IdempotencyWriteRepositoryInterface::class)
        ));

        $container->set(CategoryRepositoryInterface::class, static fn (): CategoryRepositoryInterface => new MysqlCategoryRepository());
        $container->set(BrandRepositoryInterface::class, static fn (): BrandRepositoryInterface => new MysqlBrandRepository());
        $container->set(SupplierRepositoryInterface::class, static fn (): SupplierRepositoryInterface => new MysqlSupplierRepository());

        $container->set(ProductFamilyReadRepositoryInterface::class, static fn (): ProductFamilyReadRepositoryInterface => new MysqlProductFamilyReadRepository());
        $container->set(ProductFamilyWriteRepositoryInterface::class, static fn (): ProductFamilyWriteRepositoryInterface => new MysqlProductFamilyWriteRepository());
        $container->set(AttributeReadRepositoryInterface::class, static fn (): AttributeReadRepositoryInterface => new MysqlAttributeReadRepository());
        $container->set(AttributeWriteRepositoryInterface::class, static fn (): AttributeWriteRepositoryInterface => new MysqlAttributeWriteRepository());

        $container->set(ProductReadRepositoryInterface::class, static fn (): ProductReadRepositoryInterface => new MysqlProductReadRepository());
        $container->set(ProductTranslationReadRepositoryInterface::class, static fn (): ProductTranslationReadRepositoryInterface => new MysqlProductTranslationReadRepository());
        $container->set(ProductTranslationWriteRepositoryInterface::class, static fn (): ProductTranslationWriteRepositoryInterface => new MysqlProductTranslationWriteRepository());
        $container->set(ProductWriteRepositoryInterface::class, static fn (Container $c): ProductWriteRepositoryInterface => new MysqlProductWriteRepository(
            null,
            null,
            $c->get(ProductTranslationWriteRepositoryInterface::class)
        ));

        $container->set(ProductBarcodeReadRepositoryInterface::class, static fn (): ProductBarcodeReadRepositoryInterface => new MysqlProductBarcodeReadRepository());
        $container->set(ProductBarcodeWriteRepositoryInterface::class, static fn (): ProductBarcodeWriteRepositoryInterface => new MysqlProductBarcodeWriteRepository());
        $container->set(ProductVariantReadRepositoryInterface::class, static fn (): ProductVariantReadRepositoryInterface => new MysqlProductVariantReadRepository());
        $container->set(ProductVariantWriteRepositoryInterface::class, static fn (): ProductVariantWriteRepositoryInterface => new MysqlProductVariantWriteRepository());
        $container->set(ProductAttributeReadRepositoryInterface::class, static fn (): ProductAttributeReadRepositoryInterface => new MysqlProductAttributeReadRepository());
        $container->set(ProductAttributeWriteRepositoryInterface::class, static fn (): ProductAttributeWriteRepositoryInterface => new MysqlProductAttributeWriteRepository());
        $container->set(ProductBundleReadRepositoryInterface::class, static fn (): ProductBundleReadRepositoryInterface => new MysqlProductBundleReadRepository());
        $container->set(ProductBundleWriteRepositoryInterface::class, static fn (Container $c): ProductBundleWriteRepositoryInterface => new MysqlProductBundleWriteRepository(
            null,
            null,
            $c->get(ProductBundleReadRepositoryInterface::class)
        ));
        $container->set(ProductRelationReadRepositoryInterface::class, static fn (): ProductRelationReadRepositoryInterface => new MysqlProductRelationReadRepository());
        $container->set(ProductRelationWriteRepositoryInterface::class, static fn (): ProductRelationWriteRepositoryInterface => new MysqlProductRelationWriteRepository());

        $container->set(AssetTypeReadRepositoryInterface::class, static fn (): AssetTypeReadRepositoryInterface => new MysqlAssetTypeReadRepository());
        $container->set(AssetTypeWriteRepositoryInterface::class, static fn (): AssetTypeWriteRepositoryInterface => new MysqlAssetTypeWriteRepository());
        $container->set(ProductImageReadRepositoryInterface::class, static fn (): ProductImageReadRepositoryInterface => new MysqlProductImageReadRepository());
        $container->set(ProductImageWriteRepositoryInterface::class, static fn (): ProductImageWriteRepositoryInterface => new MysqlProductImageWriteRepository());
        $container->set(ProductFileReadRepositoryInterface::class, static fn (): ProductFileReadRepositoryInterface => new MysqlProductFileReadRepository());
        $container->set(ProductFileWriteRepositoryInterface::class, static fn (): ProductFileWriteRepositoryInterface => new MysqlProductFileWriteRepository());
        $container->set(ProductVideoReadRepositoryInterface::class, static fn (): ProductVideoReadRepositoryInterface => new MysqlProductVideoReadRepository());
        $container->set(ProductVideoWriteRepositoryInterface::class, static fn (): ProductVideoWriteRepositoryInterface => new MysqlProductVideoWriteRepository());

        $container->set(JobQueueReadRepositoryInterface::class, static fn (): JobQueueReadRepositoryInterface => new MysqlJobQueueReadRepository());
        $container->set(JobQueueWriteRepositoryInterface::class, static fn (): JobQueueWriteRepositoryInterface => new MysqlJobQueueWriteRepository());
        $container->set(SearchIndexReadRepositoryInterface::class, static fn (): SearchIndexReadRepositoryInterface => new MysqlSearchIndexReadRepository());
        $container->set(SearchIndexQueueReadRepositoryInterface::class, static fn (): SearchIndexQueueReadRepositoryInterface => new MysqlSearchIndexQueueReadRepository());
        $container->set(SearchIndexQueueWriteRepositoryInterface::class, static fn (): SearchIndexQueueWriteRepositoryInterface => new MysqlSearchIndexQueueWriteRepository());
        $container->set(SearchAdapterInterface::class, static fn (): SearchAdapterInterface => SearchAdapterFactory::create());
        $container->set(QueueAdapterInterface::class, static fn (Container $c): QueueAdapterInterface => QueueAdapterFactory::create(
            $c->get(JobQueueWriteRepositoryInterface::class)
        ));

        $container->set(ProductSeoReadRepositoryInterface::class, static fn (): ProductSeoReadRepositoryInterface => new MysqlProductSeoReadRepository());
        $container->set(ProductSeoWriteRepositoryInterface::class, static fn (): ProductSeoWriteRepositoryInterface => new MysqlProductSeoWriteRepository());
        $container->set(ProductSeoRepositoryInterface::class, static fn (): ProductSeoRepositoryInterface => new MysqlProductSeoRepository());

        $container->set(WorkflowReadRepositoryInterface::class, static fn (): WorkflowReadRepositoryInterface => new MysqlWorkflowReadRepository());
        $container->set(ProductWorkflowReadRepositoryInterface::class, static fn (): ProductWorkflowReadRepositoryInterface => new MysqlProductWorkflowReadRepository());
        $container->set(ProductWorkflowWriteRepositoryInterface::class, static fn (): ProductWorkflowWriteRepositoryInterface => new MysqlProductWorkflowWriteRepository());
        $container->set(WorkflowCommentReadRepositoryInterface::class, static fn (): WorkflowCommentReadRepositoryInterface => new MysqlWorkflowCommentReadRepository());
        $container->set(WorkflowCommentWriteRepositoryInterface::class, static fn (): WorkflowCommentWriteRepositoryInterface => new MysqlWorkflowCommentWriteRepository());
        $container->set(ProductVersionReadRepositoryInterface::class, static fn (): ProductVersionReadRepositoryInterface => new MysqlProductVersionReadRepository());
        $container->set(ProductVersionWriteRepositoryInterface::class, static fn (): ProductVersionWriteRepositoryInterface => new MysqlProductVersionWriteRepository());
        $container->set(ChangeRequestReadRepositoryInterface::class, static fn (): ChangeRequestReadRepositoryInterface => new MysqlChangeRequestReadRepository());
        $container->set(ChangeRequestWriteRepositoryInterface::class, static fn (): ChangeRequestWriteRepositoryInterface => new MysqlChangeRequestWriteRepository());
        $container->set(CompletenessRuleReadRepositoryInterface::class, static fn (): CompletenessRuleReadRepositoryInterface => new MysqlCompletenessRuleReadRepository());
        $container->set(CompletenessRuleWriteRepositoryInterface::class, static fn (): CompletenessRuleWriteRepositoryInterface => new MysqlCompletenessRuleWriteRepository());
        $container->set(ProductCompletenessReadRepositoryInterface::class, static fn (): ProductCompletenessReadRepositoryInterface => new MysqlProductCompletenessReadRepository());
        $container->set(ProductCompletenessWriteRepositoryInterface::class, static fn (): ProductCompletenessWriteRepositoryInterface => new MysqlProductCompletenessWriteRepository());
        $container->set(CompletenessDataReadRepositoryInterface::class, static fn (): CompletenessDataReadRepositoryInterface => new MysqlCompletenessDataReadRepository());
        $container->set(CategorySchemaReadRepositoryInterface::class, static fn (): CategorySchemaReadRepositoryInterface => new MysqlCategorySchemaReadRepository());
        $container->set(CategorySchemaWriteRepositoryInterface::class, static fn (): CategorySchemaWriteRepositoryInterface => new MysqlCategorySchemaWriteRepository());

        $container->set(SkuUniquenessReadRepositoryInterface::class, static fn (): SkuUniquenessReadRepositoryInterface => new MysqlSkuUniquenessReadRepository());
        $container->set(BarcodeUniquenessReadRepositoryInterface::class, static fn (): BarcodeUniquenessReadRepositoryInterface => new MysqlBarcodeUniquenessReadRepository());
        $container->set(ImportBatchReadRepositoryInterface::class, static fn (): ImportBatchReadRepositoryInterface => new MysqlImportBatchReadRepository());
        $container->set(ImportBatchWriteRepositoryInterface::class, static fn (): ImportBatchWriteRepositoryInterface => new MysqlImportBatchWriteRepository());
        $container->set(WebhookSubscriptionReadRepositoryInterface::class, static fn (): WebhookSubscriptionReadRepositoryInterface => new MysqlWebhookSubscriptionReadRepository());
        $container->set(WebhookSubscriptionWriteRepositoryInterface::class, static fn (): WebhookSubscriptionWriteRepositoryInterface => new MysqlWebhookSubscriptionWriteRepository());
        $container->set(WebhookDeliveryWriteRepositoryInterface::class, static fn (): WebhookDeliveryWriteRepositoryInterface => new MysqlWebhookDeliveryWriteRepository());
        $container->set(CollectionReadRepositoryInterface::class, static fn (): CollectionReadRepositoryInterface => new MysqlCollectionReadRepository());
        $container->set(CollectionWriteRepositoryInterface::class, static fn (): CollectionWriteRepositoryInterface => new MysqlCollectionWriteRepository());
        $container->set(ChannelReadRepositoryInterface::class, static fn (): ChannelReadRepositoryInterface => new MysqlChannelReadRepository());
        $container->set(ChannelWriteRepositoryInterface::class, static fn (): ChannelWriteRepositoryInterface => new MysqlChannelWriteRepository());
        $container->set(ProductPriceReadRepositoryInterface::class, static fn (): ProductPriceReadRepositoryInterface => new MysqlProductPriceReadRepository());
        $container->set(ProductPriceWriteRepositoryInterface::class, static fn (): ProductPriceWriteRepositoryInterface => new MysqlProductPriceWriteRepository());
        $container->set(DuplicateReadRepositoryInterface::class, static fn (): DuplicateReadRepositoryInterface => new MysqlDuplicateReadRepository());
        $container->set(DuplicateWriteRepositoryInterface::class, static fn (): DuplicateWriteRepositoryInterface => new MysqlDuplicateWriteRepository());
        $container->set(SavedFilterReadRepositoryInterface::class, static fn (): SavedFilterReadRepositoryInterface => new MysqlSavedFilterReadRepository());
        $container->set(SavedFilterWriteRepositoryInterface::class, static fn (): SavedFilterWriteRepositoryInterface => new MysqlSavedFilterWriteRepository());
        $container->set(ErpSyncReadRepositoryInterface::class, static fn (): ErpSyncReadRepositoryInterface => new MysqlErpSyncReadRepository());
        $container->set(IntegrationOutboxReadRepositoryInterface::class, static fn (): IntegrationOutboxReadRepositoryInterface => new MysqlIntegrationOutboxReadRepository());
        $container->set(IntegrationOutboxWriteRepositoryInterface::class, static fn (): IntegrationOutboxWriteRepositoryInterface => new MysqlIntegrationOutboxWriteRepository());
        $container->set(MediaJobReadRepositoryInterface::class, static fn (): MediaJobReadRepositoryInterface => new MysqlMediaJobReadRepository());
        $container->set(MediaJobWriteRepositoryInterface::class, static fn (): MediaJobWriteRepositoryInterface => new MysqlMediaJobWriteRepository());

        $container->set(ProductSnapshotRestoreRepositoryInterface::class, static fn (Container $c): ProductSnapshotRestoreRepositoryInterface => new MysqlProductSnapshotRestoreRepository(
            null,
            null,
            $c->get(ProductTranslationWriteRepositoryInterface::class),
            $c->get(ProductAttributeWriteRepositoryInterface::class),
            $c->get(ProductRelationWriteRepositoryInterface::class),
            $c->get(ProductSeoWriteRepositoryInterface::class),
            $c->get(ProductSnapshotGraphWriteRepositoryInterface::class)
        ));
        $container->set(ProductSnapshotBuilder::class, static fn (Container $c): ProductSnapshotBuilder => new ProductSnapshotBuilder(
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(ProductTranslationReadRepositoryInterface::class),
            $c->get(ProductAttributeReadRepositoryInterface::class),
            $c->get(ProductRelationReadRepositoryInterface::class),
            $c->get(ProductSeoReadRepositoryInterface::class),
            $c->get(CompletenessDataReadRepositoryInterface::class),
            $c->get(ProductSnapshotGraphReadRepositoryInterface::class)
        ));

        $container->set(CategoryPolicy::class, static fn (Container $c): CategoryPolicy => new CategoryPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(BrandPolicy::class, static fn (Container $c): BrandPolicy => new BrandPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(SupplierPolicy::class, static fn (Container $c): SupplierPolicy => new SupplierPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ProductFamilyPolicy::class, static fn (Container $c): ProductFamilyPolicy => new ProductFamilyPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(AttributePolicy::class, static fn (Container $c): AttributePolicy => new AttributePolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ProductPolicy::class, static fn (Container $c): ProductPolicy => new ProductPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ProductVariantPolicy::class, static fn (Container $c): ProductVariantPolicy => new ProductVariantPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ProductBarcodePolicy::class, static fn (Container $c): ProductBarcodePolicy => new ProductBarcodePolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ProductAttributePolicy::class, static fn (Container $c): ProductAttributePolicy => new ProductAttributePolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ProductBundlePolicy::class, static fn (Container $c): ProductBundlePolicy => new ProductBundlePolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ProductRelationPolicy::class, static fn (Container $c): ProductRelationPolicy => new ProductRelationPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(AssetTypePolicy::class, static fn (Container $c): AssetTypePolicy => new AssetTypePolicy($c->get(PolicyGuardInterface::class)));
        $container->set(MediaPolicy::class, static fn (Container $c): MediaPolicy => new MediaPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(FilePolicy::class, static fn (Container $c): FilePolicy => new FilePolicy($c->get(PolicyGuardInterface::class)));
        $container->set(VideoPolicy::class, static fn (Container $c): VideoPolicy => new VideoPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(SearchPolicy::class, static fn (Container $c): SearchPolicy => new SearchPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(QueueAdminPolicy::class, static fn (Container $c): QueueAdminPolicy => new QueueAdminPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ProductSeoPolicy::class, static fn (Container $c): ProductSeoPolicy => new ProductSeoPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(WorkflowPolicy::class, static fn (Container $c): WorkflowPolicy => new WorkflowPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ChangeRequestPolicy::class, static fn (Container $c): ChangeRequestPolicy => new ChangeRequestPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(CompletenessPolicy::class, static fn (Container $c): CompletenessPolicy => new CompletenessPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(CategorySchemaPolicy::class, static fn (Container $c): CategorySchemaPolicy => new CategorySchemaPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(RbacAdminPolicy::class, static fn (Container $c): RbacAdminPolicy => new RbacAdminPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ImportPolicy::class, static fn (Container $c): ImportPolicy => new ImportPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(WebhookPolicy::class, static fn (Container $c): WebhookPolicy => new WebhookPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(CollectionPolicy::class, static fn (Container $c): CollectionPolicy => new CollectionPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ChannelPolicy::class, static fn (Container $c): ChannelPolicy => new ChannelPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(PricingPolicy::class, static fn (Container $c): PricingPolicy => new PricingPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(DuplicatePolicy::class, static fn (Container $c): DuplicatePolicy => new DuplicatePolicy($c->get(PolicyGuardInterface::class)));
        $container->set(SavedFilterPolicy::class, static fn (Container $c): SavedFilterPolicy => new SavedFilterPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(ErpSyncPolicy::class, static fn (Container $c): ErpSyncPolicy => new ErpSyncPolicy($c->get(PolicyGuardInterface::class)));
        $container->set(BulkPolicy::class, static fn (Container $c): BulkPolicy => new BulkPolicy($c->get(PolicyGuardInterface::class)));

        $container->set(SkuBarcodeUniquenessValidator::class, static fn (Container $c): SkuBarcodeUniquenessValidator => new SkuBarcodeUniquenessValidator(
            $c->get(SkuUniquenessReadRepositoryInterface::class),
            $c->get(BarcodeUniquenessReadRepositoryInterface::class)
        ));

        $container->set(CategoryService::class, static fn (Container $c): CategoryService => new CategoryService(
            $c->get(CategoryRepositoryInterface::class),
            $c->get(CategoryPolicy::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(BrandService::class, static fn (Container $c): BrandService => new BrandService(
            $c->get(BrandRepositoryInterface::class),
            $c->get(BrandPolicy::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(SupplierService::class, static fn (Container $c): SupplierService => new SupplierService(
            $c->get(SupplierRepositoryInterface::class),
            $c->get(SupplierPolicy::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(ProductFamilyService::class, static fn (Container $c): ProductFamilyService => new ProductFamilyService(
            $c->get(ProductFamilyReadRepositoryInterface::class),
            $c->get(ProductFamilyPolicy::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(AttributeService::class, static fn (Container $c): AttributeService => new AttributeService(
            $c->get(AttributeReadRepositoryInterface::class),
            $c->get(AttributePolicy::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(ProductService::class, static fn (Container $c): ProductService => new ProductService(
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(ProductWriteRepositoryInterface::class),
            $c->get(ProductPolicy::class),
            $c->get(LocaleResolverService::class),
            $c->get(ConcurrencyService::class),
            $c->get(EventDispatcher::class),
            $c->get(CompletenessService::class),
            $c->get(AuditEventService::class)
        ));
        $container->set(ProductVariantService::class, static fn (Container $c): ProductVariantService => new ProductVariantService(
            $c->get(ProductVariantReadRepositoryInterface::class),
            $c->get(ProductVariantWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(ProductVariantPolicy::class),
            $c->get(LocaleResolverService::class),
            $c->get(EventDispatcher::class)
        ));
        $container->set(ProductBarcodeService::class, static fn (Container $c): ProductBarcodeService => new ProductBarcodeService(
            $c->get(ProductBarcodeReadRepositoryInterface::class),
            $c->get(ProductBarcodeWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(ProductBarcodePolicy::class),
            $c->get(LocaleResolverService::class),
            $c->get(EventDispatcher::class)
        ));
        $container->set(ProductAttributeService::class, static fn (Container $c): ProductAttributeService => new ProductAttributeService(
            $c->get(ProductAttributeReadRepositoryInterface::class),
            $c->get(ProductAttributeWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(ProductAttributePolicy::class),
            $c->get(LocaleResolverService::class),
            $c->get(EventDispatcher::class)
        ));
        $container->set(ProductBundleService::class, static fn (Container $c): ProductBundleService => new ProductBundleService(
            $c->get(ProductBundleReadRepositoryInterface::class),
            $c->get(ProductBundleWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(ProductBundlePolicy::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(ProductRelationService::class, static fn (Container $c): ProductRelationService => new ProductRelationService(
            $c->get(ProductRelationReadRepositoryInterface::class),
            $c->get(ProductRelationWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(ProductRelationPolicy::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(AssetTypeService::class, static fn (Container $c): AssetTypeService => new AssetTypeService(
            $c->get(AssetTypeReadRepositoryInterface::class),
            $c->get(AssetTypePolicy::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(MediaService::class, static fn (Container $c): MediaService => new MediaService(
            $c->get(ProductImageReadRepositoryInterface::class),
            $c->get(ProductImageWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(StorageAdapterInterface::class),
            $c->get(MediaPolicy::class),
            $c->get(LocaleResolverService::class),
            $c->get(EventDispatcher::class),
            $c->get(UploadValidator::class)
        ));
        $container->set(FileService::class, static fn (Container $c): FileService => new FileService(
            $c->get(ProductFileReadRepositoryInterface::class),
            $c->get(ProductFileWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(StorageAdapterInterface::class),
            $c->get(FilePolicy::class),
            $c->get(LocaleResolverService::class),
            $c->get(EventDispatcher::class),
            $c->get(UploadValidator::class)
        ));
        $container->set(VideoService::class, static fn (Container $c): VideoService => new VideoService(
            $c->get(ProductVideoReadRepositoryInterface::class),
            $c->get(ProductVideoWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(StorageAdapterInterface::class),
            $c->get(VideoPolicy::class),
            $c->get(LocaleResolverService::class),
            $c->get(EventDispatcher::class),
            $c->get(UploadValidator::class)
        ));

        $container->set(CategorySchemaService::class, static fn (Container $c): CategorySchemaService => new CategorySchemaService(
            $c->get(CategorySchemaReadRepositoryInterface::class),
            $c->get(CategorySchemaWriteRepositoryInterface::class),
            $c->get(CompletenessDataReadRepositoryInterface::class),
            $c->get(CategorySchemaPolicy::class),
            $c->get(AuditEventService::class)
        ));
        $container->set(CompletenessService::class, static fn (Container $c): CompletenessService => new CompletenessService(
            $c->get(CompletenessRuleReadRepositoryInterface::class),
            $c->get(CompletenessRuleWriteRepositoryInterface::class),
            $c->get(ProductCompletenessReadRepositoryInterface::class),
            $c->get(ProductCompletenessWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(CompletenessDataReadRepositoryInterface::class),
            $c->get(CategorySchemaService::class),
            $c->get(CompletenessPolicy::class),
            $c->get(LocaleResolverService::class),
            $c->get(EventDispatcher::class),
            $c->get(AuditEventService::class)
        ));
        $container->set(ProductSeoValidator::class, static fn (Container $c): ProductSeoValidator => new ProductSeoValidator(
            $c->get(ProductSeoReadRepositoryInterface::class)
        ));
        $container->set(ProductSeoService::class, static fn (Container $c): ProductSeoService => new ProductSeoService(
            $c->get(ProductSeoReadRepositoryInterface::class),
            $c->get(ProductSeoWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(ProductSeoPolicy::class),
            $c->get(ProductSeoValidator::class),
            $c->get(LocaleResolverService::class),
            $c->get(AuditEventService::class),
            $c->get(EventDispatcher::class),
            $c->get(CompletenessService::class)
        ));
        $container->set(WorkflowCommentService::class, static fn (Container $c): WorkflowCommentService => new WorkflowCommentService(
            $c->get(WorkflowCommentReadRepositoryInterface::class),
            $c->get(WorkflowCommentWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(WorkflowPolicy::class),
            $c->get(AuditEventService::class)
        ));
        $container->set(ProductVersionService::class, static fn (Container $c): ProductVersionService => new ProductVersionService(
            $c->get(ProductVersionReadRepositoryInterface::class),
            $c->get(ProductVersionWriteRepositoryInterface::class),
            $c->get(ProductSnapshotRestoreRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(ProductSnapshotBuilder::class),
            $c->get(ProductPolicy::class),
            $c->get(ConcurrencyService::class),
            $c->get(AuditEventService::class),
            $c->get(LocaleResolverService::class),
            $c->get(EventDispatcher::class)
        ));
        $container->set(WorkflowService::class, static fn (Container $c): WorkflowService => new WorkflowService(
            $c->get(WorkflowReadRepositoryInterface::class),
            $c->get(ProductWorkflowWriteRepositoryInterface::class),
            $c->get(ProductWorkflowReadRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(WorkflowCommentService::class),
            $c->get(CompletenessService::class),
            $c->get(ProductSnapshotBuilder::class),
            $c->get(WorkflowPolicy::class),
            $c->get(ConcurrencyService::class),
            $c->get(AuditEventService::class),
            $c->get(EventDispatcher::class)
        ));
        $container->set(ChangeRequestService::class, static fn (Container $c): ChangeRequestService => new ChangeRequestService(
            $c->get(ChangeRequestReadRepositoryInterface::class),
            $c->get(ChangeRequestWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(ProductSnapshotBuilder::class),
            $c->get(WorkflowCommentService::class),
            $c->get(WorkflowCommentReadRepositoryInterface::class),
            $c->get(ChangeRequestPolicy::class),
            $c->get(ConcurrencyService::class),
            $c->get(AuditEventService::class),
            $c->get(LocaleResolverService::class),
            $c->get(EventDispatcher::class)
        ));

        $container->set(SearchIndexerService::class, static fn (Container $c): SearchIndexerService => new SearchIndexerService(
            $c->get(SearchAdapterInterface::class),
            $c->get(SearchIndexReadRepositoryInterface::class),
            $c->get(SearchIndexQueueReadRepositoryInterface::class),
            $c->get(SearchIndexQueueWriteRepositoryInterface::class)
        ));
        $container->set(SearchQueryService::class, static fn (Container $c): SearchQueryService => new SearchQueryService(
            $c->get(SearchAdapterInterface::class),
            $c->get(SearchPolicy::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(QueueService::class, static fn (Container $c): QueueService => new QueueService(
            $c->get(QueueAdapterInterface::class),
            $c->get(JobQueueReadRepositoryInterface::class),
            $c->get(JobQueueWriteRepositoryInterface::class),
            $c->get(QueueAdminPolicy::class)
        ));
        $container->set(IntegrationOutboxService::class, static fn (Container $c): IntegrationOutboxService => new IntegrationOutboxService(
            $c->get(IntegrationOutboxWriteRepositoryInterface::class),
            $c->get(IntegrationOutboxReadRepositoryInterface::class),
            $c->get(WebhookSubscriptionReadRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(QueueService::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(ImportService::class, static fn (Container $c): ImportService => new ImportService(
            $c->get(ImportBatchReadRepositoryInterface::class),
            $c->get(ImportBatchWriteRepositoryInterface::class),
            $c->get(ImportPolicy::class),
            $c->get(QueueService::class),
            $c->get(SkuBarcodeUniquenessValidator::class),
            $c->get(PlatformIdentityResolver::class)
        ));
        $container->set(WebhookService::class, static fn (Container $c): WebhookService => new WebhookService(
            $c->get(WebhookSubscriptionReadRepositoryInterface::class),
            $c->get(WebhookSubscriptionWriteRepositoryInterface::class),
            $c->get(WebhookPolicy::class)
        ));
        $container->set(CollectionService::class, static fn (Container $c): CollectionService => new CollectionService(
            $c->get(CollectionReadRepositoryInterface::class),
            $c->get(CollectionWriteRepositoryInterface::class),
            $c->get(CollectionPolicy::class),
            $c->get(LocaleResolverService::class),
            $c->get(PlatformIdentityResolver::class)
        ));
        $container->set(ChannelService::class, static fn (Container $c): ChannelService => new ChannelService(
            $c->get(ChannelReadRepositoryInterface::class),
            $c->get(ChannelWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(ChannelPolicy::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(PricingService::class, static fn (Container $c): PricingService => new PricingService(
            $c->get(ProductPriceReadRepositoryInterface::class),
            $c->get(ProductPriceWriteRepositoryInterface::class),
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(PricingPolicy::class),
            $c->get(LocaleResolverService::class)
        ));
        $container->set(DuplicateService::class, static fn (Container $c): DuplicateService => new DuplicateService(
            $c->get(DuplicateReadRepositoryInterface::class),
            $c->get(DuplicateWriteRepositoryInterface::class),
            $c->get(DuplicatePolicy::class),
            $c->get(QueueService::class),
            $c->get(PlatformIdentityResolver::class)
        ));
        $container->set(SavedFilterService::class, static fn (Container $c): SavedFilterService => new SavedFilterService(
            $c->get(SavedFilterReadRepositoryInterface::class),
            $c->get(SavedFilterWriteRepositoryInterface::class),
            $c->get(SavedFilterPolicy::class),
            $c->get(PlatformIdentityResolver::class)
        ));
        $container->set(ErpSyncService::class, static fn (Container $c): ErpSyncService => new ErpSyncService(
            $c->get(ErpSyncReadRepositoryInterface::class),
            $c->get(ErpSyncPolicy::class)
        ));
        $container->set(BulkService::class, static fn (Container $c): BulkService => new BulkService(
            $c->get(BulkPolicy::class),
            $c->get(ImportPolicy::class),
            $c->get(QueueService::class),
            $c->get(ImportService::class)
        ));
        $container->set(SearchAdminService::class, static fn (Container $c): SearchAdminService => new SearchAdminService(
            $c->get(QueueService::class),
            $c->get(SearchPolicy::class)
        ));
        $container->set(SchedulerService::class, static fn (Container $c): SchedulerService => new SchedulerService(
            $c->get(QueueService::class),
            $c->get(ScheduledPublishService::class)
        ));
        $container->set(ScheduledPublishService::class, static fn (Container $c): ScheduledPublishService => new ScheduledPublishService(
            $c->get(ScheduledPublishReadRepositoryInterface::class),
            $c->get(ScheduledPublishWriteRepositoryInterface::class),
            $c->get(WorkflowService::class),
            $c->get(CompletenessService::class),
            $c->get(AuditEventService::class),
            $c->get(EventDispatcher::class),
            $c->get(StructuredLoggerInterface::class)
        ));
        $container->set(SearchReindexJobHandler::class, static fn (Container $c): SearchReindexJobHandler => new SearchReindexJobHandler(
            $c->get(SearchIndexerService::class)
        ));
        $container->set(VariantReindexJobHandler::class, static fn (Container $c): VariantReindexJobHandler => new VariantReindexJobHandler(
            $c->get(SearchIndexerService::class)
        ));
        $container->set(SearchFullReindexJobHandler::class, static fn (Container $c): SearchFullReindexJobHandler => new SearchFullReindexJobHandler(
            $c->get(SearchIndexerService::class)
        ));
        $container->set(MediaProcessJobHandler::class, static fn (Container $c): MediaProcessJobHandler => new MediaProcessJobHandler(
            $c->get(MediaJobReadRepositoryInterface::class),
            $c->get(MediaJobWriteRepositoryInterface::class),
            $c->get(ProductImageReadRepositoryInterface::class),
            $c->get(StorageAdapterInterface::class)
        ));
        $container->set(ExportChunkJobHandler::class, static fn (Container $c): ExportChunkJobHandler => new ExportChunkJobHandler(
            $c->get(ProductReadRepositoryInterface::class),
            $c->get(LocaleResolverService::class),
            $c->get(StorageAdapterInterface::class)
        ));
        $container->set(CleanupJobHandler::class, static fn (Container $c): CleanupJobHandler => new CleanupJobHandler(
            $c->get(IdempotencyReadRepositoryInterface::class),
            $c->get(IntegrationOutboxWriteRepositoryInterface::class)
        ));
        $container->set(ImportChunkJobHandler::class, static fn (Container $c): ImportChunkJobHandler => new ImportChunkJobHandler(
            $c->get(ImportBatchReadRepositoryInterface::class),
            $c->get(ImportBatchWriteRepositoryInterface::class),
            $c->get(ProductWriteRepositoryInterface::class),
            $c->get(EventDispatcher::class)
        ));
        $container->set(WebhookDispatchJobHandler::class, static fn (Container $c): WebhookDispatchJobHandler => new WebhookDispatchJobHandler(
            $c->get(WebhookDeliveryWriteRepositoryInterface::class),
            $c->get(IntegrationOutboxWriteRepositoryInterface::class),
            $c->get(HttpClientInterface::class)
        ));
        $container->set(BulkPublishJobHandler::class, static fn (Container $c): BulkPublishJobHandler => new BulkPublishJobHandler(
            $c->get(WorkflowService::class),
            $c->get(ProductReadRepositoryInterface::class)
        ));
        $container->set(BulkArchiveJobHandler::class, static fn (Container $c): BulkArchiveJobHandler => new BulkArchiveJobHandler(
            $c->get(WorkflowService::class),
            $c->get(ProductReadRepositoryInterface::class)
        ));
        $container->set(OutboxDispatchJobHandler::class, static fn (Container $c): OutboxDispatchJobHandler => new OutboxDispatchJobHandler(
            $c->get(IntegrationOutboxService::class)
        ));
        $container->set(DuplicateScanJobHandler::class, static fn (Container $c): DuplicateScanJobHandler => new DuplicateScanJobHandler(
            $c->get(DuplicateReadRepositoryInterface::class),
            $c->get(DuplicateWriteRepositoryInterface::class)
        ));
        $container->set(HealthJobHandler::class, static fn (Container $c): HealthJobHandler => new HealthJobHandler(
            $c->get(SearchAdapterInterface::class)
        ));
        $container->set(JobProcessorService::class, static function (Container $c): JobProcessorService {
            $processor = new JobProcessorService($c->get(QueueAdapterInterface::class));
            $processor->registerHandler($c->get(SearchReindexJobHandler::class));
            $processor->registerHandler($c->get(VariantReindexJobHandler::class));
            $processor->registerHandler($c->get(SearchFullReindexJobHandler::class));
            $processor->registerHandler($c->get(MediaProcessJobHandler::class));
            $processor->registerHandler($c->get(ExportChunkJobHandler::class));
            $processor->registerHandler($c->get(CleanupJobHandler::class));
            $processor->registerHandler($c->get(ImportChunkJobHandler::class));
            $processor->registerHandler($c->get(BulkPublishJobHandler::class));
            $processor->registerHandler($c->get(BulkArchiveJobHandler::class));
            $processor->registerHandler($c->get(WebhookDispatchJobHandler::class));
            $processor->registerHandler($c->get(OutboxDispatchJobHandler::class));
            $processor->registerHandler($c->get(DuplicateScanJobHandler::class));
            $processor->registerHandler($c->get(HealthJobHandler::class));

            return $processor;
        });
        $container->set(SearchIndexingListener::class, static fn (Container $c): SearchIndexingListener => new SearchIndexingListener(
            $c->get(SearchIndexerService::class),
            $c->get(QueueService::class)
        ));
        $container->set(IntegrationOutboxListener::class, static fn (Container $c): IntegrationOutboxListener => new IntegrationOutboxListener(
            $c->get(IntegrationOutboxService::class)
        ));
        $container->set(CorrelationIdMiddleware::class, static fn (): CorrelationIdMiddleware => new CorrelationIdMiddleware());
        $container->set(RateLimitMiddleware::class, static fn (Container $c): RateLimitMiddleware => new RateLimitMiddleware(
            $c->get(PlatformIdentityResolver::class)
        ));
        $container->set(QueueWorkerService::class, static fn (Container $c): QueueWorkerService => new QueueWorkerService(
            $c->get(JobQueueWriteRepositoryInterface::class),
            $c->get(JobProcessorService::class)
        ));

        $container->set(HealthController::class, static fn (Container $c): HealthController => new HealthController($c->get(HealthService::class)));
        $container->set(CategoryController::class, static fn (Container $c): CategoryController => new CategoryController($c->get(CategoryService::class)));
        $container->set(CategorySchemaController::class, static fn (Container $c): CategorySchemaController => new CategorySchemaController(
            $c->get(CategorySchemaService::class)
        ));
        $container->set(BrandController::class, static fn (Container $c): BrandController => new BrandController($c->get(BrandService::class)));
        $container->set(SupplierController::class, static fn (Container $c): SupplierController => new SupplierController($c->get(SupplierService::class)));
        $container->set(ProductFamilyController::class, static fn (Container $c): ProductFamilyController => new ProductFamilyController($c->get(ProductFamilyService::class)));
        $container->set(AttributeController::class, static fn (Container $c): AttributeController => new AttributeController($c->get(AttributeService::class)));
        $container->set(ProductController::class, static fn (Container $c): ProductController => new ProductController(
            $c->get(ProductService::class),
            $c->get(ConcurrencyService::class)
        ));
        $container->set(ProductRelationshipController::class, static fn (Container $c): ProductRelationshipController => new ProductRelationshipController(
            $c->get(ProductVariantService::class),
            $c->get(ProductBarcodeService::class),
            $c->get(ProductAttributeService::class),
            $c->get(ProductBundleService::class),
            $c->get(ProductRelationService::class)
        ));
        $container->set(AssetTypeController::class, static fn (Container $c): AssetTypeController => new AssetTypeController($c->get(AssetTypeService::class)));
        $container->set(ProductMediaController::class, static fn (Container $c): ProductMediaController => new ProductMediaController(
            $c->get(MediaService::class),
            $c->get(FileService::class),
            $c->get(VideoService::class)
        ));
        $container->set(MediaServeController::class, static fn (Container $c): MediaServeController => new MediaServeController(
            $c->get(MediaService::class),
            $c->get(FileService::class)
        ));
        $container->set(SignedStorageController::class, static fn (Container $c): SignedStorageController => new SignedStorageController(
            $c->get(StorageAdapterInterface::class),
            $c->get(SignedUrlVerifier::class)
        ));
        $container->set(SearchController::class, static fn (Container $c): SearchController => new SearchController(
            $c->get(SearchQueryService::class)
        ));
        $container->set(JobController::class, static fn (Container $c): JobController => new JobController(
            $c->get(QueueService::class)
        ));
        $container->set(AdminQueueController::class, static fn (Container $c): AdminQueueController => new AdminQueueController(
            $c->get(QueueService::class),
            $c->get(SearchAdminService::class)
        ));
        $container->set(WorkflowController::class, static fn (Container $c): WorkflowController => new WorkflowController(
            $c->get(WorkflowService::class),
            $c->get(WorkflowCommentService::class)
        ));
        $container->set(ProductSeoController::class, static fn (Container $c): ProductSeoController => new ProductSeoController(
            $c->get(ProductSeoService::class)
        ));
        $container->set(ProductVersionController::class, static fn (Container $c): ProductVersionController => new ProductVersionController(
            $c->get(ProductVersionService::class)
        ));
        $container->set(CompletenessController::class, static fn (Container $c): CompletenessController => new CompletenessController(
            $c->get(CompletenessService::class)
        ));
        $container->set(ChangeRequestController::class, static fn (Container $c): ChangeRequestController => new ChangeRequestController(
            $c->get(ChangeRequestService::class)
        ));
        $container->set(AdminCompletenessController::class, static fn (Container $c): AdminCompletenessController => new AdminCompletenessController(
            $c->get(CompletenessService::class)
        ));
        $container->set(RbacAdminValidator::class, static fn (Container $c): RbacAdminValidator => new RbacAdminValidator(
            $c->get(RbacAdminReadRepositoryInterface::class)
        ));
        $container->set(RbacAdminService::class, static fn (Container $c): RbacAdminService => new RbacAdminService(
            $c->get(RbacAdminReadRepositoryInterface::class),
            $c->get(RbacAdminWriteRepositoryInterface::class),
            $c->get(RbacAdminPolicy::class),
            $c->get(RbacAdminValidator::class),
            $c->get(AuditEventService::class)
        ));
        $container->set(RbacAdminController::class, static fn (Container $c): RbacAdminController => new RbacAdminController(
            $c->get(RbacAdminService::class)
        ));
        $container->set(ImportController::class, static fn (Container $c): ImportController => new ImportController($c->get(ImportService::class)));
        $container->set(WebhookController::class, static fn (Container $c): WebhookController => new WebhookController($c->get(WebhookService::class)));
        $container->set(CollectionController::class, static fn (Container $c): CollectionController => new CollectionController($c->get(CollectionService::class)));
        $container->set(ChannelController::class, static fn (Container $c): ChannelController => new ChannelController($c->get(ChannelService::class)));
        $container->set(PricingController::class, static fn (Container $c): PricingController => new PricingController($c->get(PricingService::class)));
        $container->set(DuplicateController::class, static fn (Container $c): DuplicateController => new DuplicateController($c->get(DuplicateService::class)));
        $container->set(SavedFilterController::class, static fn (Container $c): SavedFilterController => new SavedFilterController($c->get(SavedFilterService::class)));
        $container->set(ErpSyncController::class, static fn (Container $c): ErpSyncController => new ErpSyncController($c->get(ErpSyncService::class)));
        $container->set(BulkController::class, static fn (Container $c): BulkController => new BulkController($c->get(BulkService::class)));

        SearchIndexingListener::register(
            $container->get(EventDispatcher::class),
            $container->get(SearchIndexingListener::class)
        );
        IntegrationOutboxListener::register(
            $container->get(EventDispatcher::class),
            $container->get(IntegrationOutboxListener::class)
        );
    }
}
