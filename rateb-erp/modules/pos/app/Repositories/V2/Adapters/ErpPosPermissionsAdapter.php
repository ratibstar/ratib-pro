<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Pos\PosModule;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PermissionsPortInterface;

/** Collects granted POS permission slugs for the active cashier. */
final class ErpPosPermissionsAdapter implements PosV2PermissionsPortInterface
{
    /** @var list<string> */
    private readonly array $permissionSlugs;

    /**
     * @param list<string>|null $permissionSlugs
     */
    public function __construct(?array $permissionSlugs = null)
    {
        $this->permissionSlugs = $permissionSlugs ?? $this->loadPermissionSlugs();
    }

    public function resolveForUser(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return ['pos.*'];
        }

        $granted = [];
        foreach ($this->permissionSlugs as $slug) {
            if ($slug === '') {
                continue;
            }
            if (function_exists('rateb_can') && rateb_can($slug)) {
                $granted[] = $slug;
            }
        }

        return $granted;
    }

    /** @return list<string> */
    private function loadPermissionSlugs(): array
    {
        $path = PosModule::rootPath() . '/config/v2/register-context.php';
        if (!is_file($path)) {
            return [];
        }

        $config = require $path;
        $slugs = is_array($config) ? ($config['permission_slugs'] ?? []) : [];

        return is_array($slugs) ? array_values(array_filter(array_map('strval', $slugs))) : [];
    }
}
