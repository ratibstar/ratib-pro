<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class ArabicNormalizer
{
    public static function normalize(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text) ?? $text;
        $text = str_replace('ـ', '', $text);
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);

        return $text;
    }

    public static function normalizeForSearch(string $text, bool $normalizeTaaMarbuta = false): string
    {
        $normalized = self::normalize($text);
        if ($normalizeTaaMarbuta) {
            $normalized = str_replace('ة', 'ه', $normalized);
        }

        return $normalized;
    }
}
