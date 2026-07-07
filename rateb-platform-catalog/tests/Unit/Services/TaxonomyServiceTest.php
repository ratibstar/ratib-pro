<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Policies\CategoryPolicy;
use Rateb\PlatformCatalog\Tests\Support\ConfigurablePolicyGuard;
use Rateb\PlatformCatalog\Application\Services\CategoryService;
use Rateb\PlatformCatalog\Application\Services\LocaleResolverService;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategoryRepositoryInterface;

catalog_test('LocaleResolverService prefers X-Rateb-Locale header', static function (): void {
    $_GET = [];
    $_SERVER['HTTP_X_RATEB_LOCALE'] = 'en';
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ar';

    $service = new LocaleResolverService();
    $locale = $service->resolveFromRequest();

    catalog_assert_same('en', $locale->locale);

    unset($_SERVER['HTTP_X_RATEB_LOCALE'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);
});

catalog_test('CategoryPolicy denies when guard rejects permission', static function (): void {
    $guard = new ConfigurablePolicyGuard(false);

    $policy = new CategoryPolicy($guard);

    try {
        $policy->viewList();
        throw new RuntimeException('Expected forbidden');
    } catch (RuntimeException $e) {
        catalog_assert_same('Forbidden', $e->getMessage());
        catalog_assert_same(403, $e->getCode());
    }
});

catalog_test('CategoryService returns tree from repository', static function (): void {
    $repo = new class implements CategoryRepositoryInterface {
        public function listFlat(LocaleContext $locale): array
        {
            return [
                [
                    'id' => 1,
                    'uuid' => 'c1',
                    'parent_id' => null,
                    'slug' => 'electronics',
                    'depth' => 0,
                    'path' => '/electronics',
                    'sort_order' => 0,
                    'image_path' => null,
                    'status' => 'active',
                    'name' => 'إلكترونيات',
                    'description' => null,
                    'resolved_language_code' => $locale->locale,
                ],
            ];
        }

        public function findByUuid(string $uuid, LocaleContext $locale): ?array
        {
            return null;
        }

        public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
        {
            return $this->listFlat($locale);
        }

        public function create(array $data): string
        {
            return '';
        }

        public function update(string $uuid, array $data): bool
        {
            return false;
        }

        public function softDelete(string $uuid, ?int $actorId = null): bool
        {
            return false;
        }
    };

    $guard = new ConfigurablePolicyGuard(true);

    $service = new CategoryService($repo, new CategoryPolicy($guard), new LocaleResolverService());
    $result = $service->getTree(new LocaleContext('ar', 'en'));

    catalog_assert_same(1, count($result['items']));
    catalog_assert_same('c1', $result['items'][0]['uuid']);
    catalog_assert_same('ar', $result['meta']['locale']);
});
