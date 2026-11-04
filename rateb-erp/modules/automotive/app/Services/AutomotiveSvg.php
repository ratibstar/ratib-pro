<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

/** Rejects active SVG content that can execute script. */
final class AutomotiveSvg
{
    public static function sanitize(?string $svg): ?string
    {
        if ($svg === null || trim($svg) === '') {
            return null;
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY|<script|onload=|onerror=|<foreignObject/i', $svg) === 1) {
            throw new \InvalidArgumentException('SVG asset was rejected.');
        }
        return $svg;
    }
}
