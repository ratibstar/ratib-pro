<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

use Rateb\PlatformCatalog\Application\Policies\PolicyGuardInterface;
use Rateb\PlatformCatalog\Application\Services\RbacService;

final class AdminNavigation
{
    public function __construct(
        private readonly PolicyGuardInterface $guard,
        private readonly RbacService $rbacService
    ) {
    }

    /**
     * @return list<array{key:string,route:string,icon:string,permissions:list<string>,group:string,label:string}>
     */
    public function visibleItems(string $locale): array
    {
        $navFile = (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : dirname(__DIR__, 3)) . '/config/admin-nav.php';
        /** @var list<array{key:string,route:string,icon:string,permissions:list<string>,group:string}> $items */
        $items = is_file($navFile) ? require $navFile : [];

        $permissions = $this->currentPermissions();
        $filterByRbac = $this->guard->isAuthenticated();
        $visible = [];

        foreach ($items as $item) {
            if ($filterByRbac && !$this->allowsAny($item['permissions'], $permissions)) {
                continue;
            }

            $visible[] = [
                'key' => $item['key'],
                'route' => $item['route'],
                'icon' => $item['icon'],
                'permissions' => $item['permissions'],
                'group' => $item['group'],
                'label' => catalog__('nav_' . $item['key'], $locale),
            ];
        }

        return $visible;
    }

    /**
     * @return list<string>
     */
    public function currentPermissions(): array
    {
        $userId = $this->guard->actorId();
        if ($userId === null) {
            return [];
        }

        return $this->rbacService->listPermissionsForUser($userId);
    }

    public function canAccessPage(string $pageKey): bool
    {
        $navFile = (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : dirname(__DIR__, 3)) . '/config/admin-nav.php';
        /** @var list<array{key:string,route:string,icon:string,permissions:list<string>,group:string}> $items */
        $items = is_file($navFile) ? require $navFile : [];
        $permissions = $this->currentPermissions();

        foreach ($items as $item) {
            if ($item['key'] !== $pageKey) {
                continue;
            }

            return $this->allowsAny($item['permissions'], $permissions);
        }

        return false;
    }

    /**
     * @param list<string> $required
     * @param list<string> $owned
     */
    private function allowsAny(array $required, array $owned): bool
    {
        if ($required === []) {
            return true;
        }

        foreach ($required as $slug) {
            if (in_array($slug, $owned, true)) {
                return true;
            }
        }

        return false;
    }
}
