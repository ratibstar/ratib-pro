<?php
/**
 * Read resolved public-site CMS values (ratib_site_content keys).
 */
declare(strict_types=1);

if (!function_exists('ratib_public_cms_flat')) {
    /**
     * @return array<string, string>
     */
    function ratib_public_cms_flat(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        if (function_exists('ratib_site_content_home_flat')) {
            $cache = ratib_site_content_home_flat();

            return $cache;
        }
        if (function_exists('ratib_site_content_defaults_home')) {
            $cache = ratib_site_content_defaults_home();

            return $cache;
        }
        $cache = [];

        return $cache;
    }
}

if (!function_exists('ratib_public_cms')) {
    function ratib_public_cms(string $key, string $default = ''): string
    {
        $flat = ratib_public_cms_flat();
        $v = trim((string) ($flat[$key] ?? ''));

        return $v !== '' ? $v : $default;
    }
}

if (!function_exists('ratib_public_cms_lines')) {
    /**
     * @param list<string> $defaultLines
     *
     * @return list<string>
     */
    function ratib_public_cms_lines(string $key, array $defaultLines): array
    {
        $raw = ratib_public_cms($key, '');
        if ($raw === '') {
            return $defaultLines;
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $l): bool => $l !== ''));
    }
}

if (!function_exists('ratib_public_bundled_asset_url')) {
    /** Absolute URL for a site-relative asset with cache-busting mtime. */
    function ratib_public_bundled_asset_url(string $baseUrl, string $relPath): string
    {
        $rel = ltrim(str_replace('\\', '/', $relPath), '/');
        $fs = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!function_exists('ratib_site_content_asset_url')) {
            require_once __DIR__ . '/site-content.php';
        }
        if (function_exists('ratib_site_content_asset_url')) {
            return ratib_site_content_asset_url($baseUrl, $rel, $rel, $fs);
        }
        $v = is_file($fs) ? (int) filemtime($fs) : 1;

        return rtrim($baseUrl, '/') . '/' . $rel . '?v=' . $v;
    }
}

if (!function_exists('ratib_public_cms_image')) {
    function ratib_public_cms_image(string $baseUrl, string $key, string $fallbackRel): string
    {
        $stored = ratib_public_cms($key, '');
        $fallbackFs = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($fallbackRel, '/'));
        if (!function_exists('ratib_site_content_asset_url')) {
            require_once __DIR__ . '/site-content.php';
        }
        if (function_exists('ratib_site_content_asset_url')) {
            return ratib_site_content_asset_url($baseUrl, $stored, ltrim($fallbackRel, '/'), $fallbackFs);
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($fallbackRel, '/');
    }
}

if (!function_exists('ratib_public_cms_image_or')) {
    /**
     * Primary CMS image key; if empty, uses secondary key (avoids duplicate uploads).
     */
    function ratib_public_cms_image_or(string $baseUrl, string $primaryKey, string $secondaryKey, string $fallbackRel): string
    {
        $flat = ratib_public_cms_flat();
        $stored = trim((string) ($flat[$primaryKey] ?? ''));
        $legacyGeneric = 'assets/images/about-ratib-command.png';
        if ($stored === $legacyGeneric && $secondaryKey !== '') {
            $secondaryStored = trim((string) ($flat[$secondaryKey] ?? ''));
            if ($secondaryStored !== '' && $secondaryStored !== $legacyGeneric) {
                $stored = $secondaryStored;
            } elseif ($secondaryStored === '') {
                $stored = '';
            }
        }
        if ($stored === '' && $secondaryKey !== '') {
            $stored = trim((string) ($flat[$secondaryKey] ?? ''));
        }
        $fallbackFs = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($fallbackRel, '/'));
        if (!function_exists('ratib_site_content_asset_url')) {
            require_once __DIR__ . '/site-content.php';
        }
        if (function_exists('ratib_site_content_asset_url')) {
            return ratib_site_content_asset_url($baseUrl, $stored, ltrim($fallbackRel, '/'), $fallbackFs);
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($fallbackRel, '/');
    }
}
