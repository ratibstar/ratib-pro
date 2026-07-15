<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

/** Phase WEBSITE-05 — On-disk theme package (never rewritten by tenant customize). */
final class ThemePackage
{
    private ThemeManifest $manifest;

    public function __construct(
        private readonly string $rootPath,
        ThemeManifest $manifest,
    ) {
        $this->manifest = $manifest;
    }

    public static function themesRoot(): string
    {
        $base = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 3);

        return rtrim(str_replace('\\', '/', $base), '/') . '/themes';
    }

    public static function load(string $slug): self
    {
        $slug = preg_replace('/[^a-z0-9\-]+/', '', strtolower($slug)) ?: '';
        if ($slug === '') {
            throw new \InvalidArgumentException('Invalid theme slug');
        }
        $root = self::themesRoot() . '/' . $slug;
        $manifestFile = $root . '/manifest.json';
        if (!is_file($manifestFile)) {
            throw new \RuntimeException('Theme package not found: ' . $slug);
        }
        $json = file_get_contents($manifestFile);
        if ($json === false) {
            throw new \RuntimeException('Cannot read theme manifest: ' . $slug);
        }
        $manifest = ThemeManifest::fromJson($json);
        if ($manifest->slug() !== $slug) {
            throw new \RuntimeException('Theme slug mismatch for package ' . $slug);
        }

        return new self($root, $manifest);
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function manifest(): ThemeManifest
    {
        return $this->manifest;
    }

    public function assetAbsolute(string $relative): ?string
    {
        $relative = str_replace('\\', '/', $relative);
        $relative = ltrim($relative, '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }
        $full = $this->rootPath . '/' . $relative;
        if (!is_file($full)) {
            return null;
        }
        $realRoot = realpath($this->rootPath);
        $realFile = realpath($full);
        if ($realRoot === false || $realFile === false || !str_starts_with($realFile, $realRoot)) {
            return null;
        }

        return $realFile;
    }

    /** @return list<string> */
    public static function discoverSlugs(): array
    {
        $root = self::themesRoot();
        if (!is_dir($root)) {
            return [];
        }
        $slugs = [];
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_file($root . '/' . $entry . '/manifest.json')) {
                $slugs[] = $entry;
            }
        }
        sort($slugs);

        return $slugs;
    }
}
