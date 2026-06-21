<?php
declare(strict_types=1);

namespace Rateb\App\Services;

final class LegacyHomeContentService
{
    /** @return array<string, mixed>|null */
    public static function context(): ?array
    {
        $root = dirname(RATEB_ROOT);
        $ctxFile = $root . '/includes/rateb-marketing-unified-context.php';
        if (!is_file($ctxFile)) {
            return null;
        }
        require_once $ctxFile;

        return function_exists('rateb_marketing_unified_context')
            ? rateb_marketing_unified_context()
            : null;
    }

    public static function render(string $mode = 'full'): void
    {
        $root = dirname(RATEB_ROOT);
        $ctxFile = $root . '/includes/rateb-marketing-unified-context.php';
        if (!is_file($ctxFile)) {
            return;
        }
        require_once $ctxFile;
        if (function_exists('rateb_marketing_unified_render')) {
            rateb_marketing_unified_render($mode);
        }
    }

    public static function assetOrigin(): string
    {
        $ctx = self::context();

        return $ctx !== null ? rtrim((string) ($ctx['baseUrl'] ?? ''), '/') : rateb_site_origin();
    }
}
