<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Search;

final class SearchAdapterFactory
{
    public static function create(): SearchAdapterInterface
    {
        if (self::isTestingEnvironment()) {
            return new InMemorySearchAdapter();
        }

        $config = self::config();
        $adapter = strtolower((string) ($config['SEARCH_ADAPTER'] ?? 'meilisearch'));

        return match ($adapter) {
            'opensearch' => new OpenSearchAdapter(),
            'memory' => new InMemorySearchAdapter(),
            default => new MeilisearchAdapter(
                (string) ($config['MEILISEARCH_HOST'] ?? ''),
                isset($config['MEILISEARCH_API_KEY']) ? (string) $config['MEILISEARCH_API_KEY'] : null
            ),
        };
    }

    public static function isTestingEnvironment(): bool
    {
        if (defined('RATEB_CATALOG_TESTING') && RATEB_CATALOG_TESTING) {
            return true;
        }

        $env = strtolower((string) (getenv('APP_ENV') ?: getenv('RATEB_CATALOG_APP_ENV') ?: ''));

        return in_array($env, ['test', 'testing'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        $path = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/config/search.php' : dirname(__DIR__, 3) . '/config/search.php';
        if (!is_file($path)) {
            return [];
        }

        $config = require $path;

        return is_array($config) ? $config : [];
    }
}
