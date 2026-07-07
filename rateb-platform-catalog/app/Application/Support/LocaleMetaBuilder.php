<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

final class LocaleMetaBuilder
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public static function build(LocaleContext $locale, array $rows, ?int $limit = null, ?int $offset = null): array
    {
        $fallbackUsed = false;
        foreach ($rows as $row) {
            if (isset($row['resolved_language_code']) && (string) $row['resolved_language_code'] !== $locale->locale) {
                $fallbackUsed = true;
                break;
            }
        }

        $meta = [
            'locale' => $locale->locale,
            'locale_fallback_used' => $fallbackUsed,
            'count' => count($rows),
        ];

        if ($limit !== null) {
            $meta['limit'] = $limit;
        }
        if ($offset !== null) {
            $meta['offset'] = $offset;
        }

        return $meta;
    }
}
