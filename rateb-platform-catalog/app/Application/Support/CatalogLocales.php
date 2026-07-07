<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class CatalogLocales
{
    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        $root = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : dirname(__DIR__, 3);
        $path = $root . '/config/languages.php';
        if (!is_file($path)) {
            return ['en', 'ar'];
        }

        $config = require $path;

        return is_array($config['supported'] ?? null) ? array_values($config['supported']) : ['en', 'ar'];
    }
}
