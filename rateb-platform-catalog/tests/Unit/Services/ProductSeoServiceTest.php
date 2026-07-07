<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Policies\ProductSeoPolicy;
use Rateb\PlatformCatalog\Tests\Support\ConfigurablePolicyGuard;
use Rateb\PlatformCatalog\Application\Services\AuditEventService;
use Rateb\PlatformCatalog\Application\Services\LocaleResolverService;
use Rateb\PlatformCatalog\Application\Services\ProductSeoService;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Validators\ProductSeoValidator;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoWriteRepositoryInterface;

catalog_test('ProductSeoPolicy enforces edit permission', static function (): void {
    $guard = new ConfigurablePolicyGuard(static fn (string $slug): bool => $slug === 'catalog.products.view');

    (new ProductSeoPolicy($guard))->view();

    try {
        (new ProductSeoPolicy($guard))->update();
        throw new RuntimeException('Expected forbidden');
    } catch (RuntimeException $e) {
        catalog_assert_same(403, $e->getCode());
    }
});

catalog_test('ProductSeoValidator rejects invalid slug format', static function (): void {
    $read = new class implements ProductSeoReadRepositoryInterface {
        public function findByProductUuid(string $productUuid, ?LocaleContext $locale = null): ?array
        {
            return null;
        }

        public function buildSnapshotData(string $productUuid): array
        {
            return ['canonical_url' => null, 'translations' => []];
        }

        public function listTranslationsByLocale(string $productUuid): array
        {
            return [];
        }

        public function slugExistsForLanguage(string $slug, string $languageCode, ?string $excludeProductUuid = null): bool
        {
            return false;
        }
    };

    $validator = new ProductSeoValidator($read);

    try {
        $validator->validate(null, [['language_code' => 'en', 'slug' => 'Bad Slug!']]);
        throw new RuntimeException('Expected validation error');
    } catch (InvalidArgumentException $e) {
        catalog_assert_true(str_contains($e->getMessage(), 'slug'));
    }
});

catalog_test('ProductSeoService upserts SEO and records audit', static function (): void {
    $stored = null;
    $auditAction = null;

    $read = new class($stored) implements ProductSeoReadRepositoryInterface {
        public function __construct(private mixed &$stored)
        {
        }

        public function findByProductUuid(string $productUuid, ?LocaleContext $locale = null): ?array
        {
            return $this->stored;
        }

        public function buildSnapshotData(string $productUuid): array
        {
            return ['canonical_url' => null, 'translations' => []];
        }

        public function listTranslationsByLocale(string $productUuid): array
        {
            return [];
        }

        public function slugExistsForLanguage(string $slug, string $languageCode, ?string $excludeProductUuid = null): bool
        {
            return false;
        }
    };

    $write = new class($stored) implements ProductSeoWriteRepositoryInterface {
        public function __construct(private mixed &$stored)
        {
        }

        public function upsertForProduct(
            string $productUuid,
            ?string $canonicalUrl,
            array $translations,
            ?int $actorId = null
        ): string {
            $this->stored = [
                'uuid' => 'seo-1',
                'product_uuid' => $productUuid,
                'canonical_url' => $canonicalUrl,
                'translations' => $translations,
            ];

            return 'seo-1';
        }

        public function replaceFromSnapshot(string $productUuid, array $seoData, ?int $actorId = null): void
        {
        }
    };

    $productRead = new class implements ProductReadRepositoryInterface {
        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listFiltered(LocaleContext $locale, \Rateb\PlatformCatalog\Application\DTO\ProductListFilter $filter, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return [];
        }

        public function findLockVersion(string $uuid): ?int
        {
            return 1;
        }

        public function findWorkflowMeta(string $uuid): ?array
        {
            return ['id' => 1, 'uuid' => $uuid, 'version_number' => 1, 'lock_version' => 1, 'status' => 'draft'];
        }
    };

    $audit = new AuditEventService(new class($auditAction) implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AuditEventWriteRepositoryInterface {
        public function __construct(private mixed &$auditAction)
        {
        }

        public function append(
            string $entityType,
            string $entityUuid,
            ?int $entityVersion,
            string $action,
            ?int $actorId,
            string $actorType = 'platform_user',
            ?array $before = null,
            ?array $after = null,
            ?string $ipAddress = null
        ): string {
            $this->auditAction = $action;

            return 'evt-1';
        }
    });

    $guard = new ConfigurablePolicyGuard(static fn (): bool => true);
    $service = new ProductSeoService(
        $read,
        $write,
        $productRead,
        new ProductSeoPolicy($guard),
        new ProductSeoValidator($read),
        new LocaleResolverService(),
        $audit,
        new EventDispatcher(),
        null
    );

    $result = $service->upsert('prod-1', [
        'canonical_url' => 'https://example.com/p',
        'translations' => [
            ['language_code' => 'en', 'slug' => 'sample-product', 'seo_title' => 'Title', 'seo_description' => 'Desc'],
        ],
        'actor_id' => 1,
    ]);

    catalog_assert_same('seo-1', $result['item']['uuid']);
    catalog_assert_same('create', $auditAction);
});
