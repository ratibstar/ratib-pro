<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

use Rateb\App\Website\WebsiteBlockRegistry;

/** Phase WEBSITE-05 — Validate package before install/import. */
final class ThemeValidator
{
    /**
     * @return array{ok:bool,errors:list<string>,warnings:list<string>}
     */
    public function validatePackage(ThemePackage $package): array
    {
        $errors = [];
        $warnings = [];
        $m = $package->manifest();

        if ($m->slug() === '' || !preg_match('/^[a-z0-9\-]+$/', $m->slug())) {
            $errors[] = 'Invalid or missing slug';
        }
        if ($m->version() === '') {
            $errors[] = 'Missing version';
        }
        if ($m->engine() !== ThemeManifest::ENGINE && $m->engine() !== 'website') {
            $warnings[] = 'Unexpected engine: ' . $m->engine();
        }
        if (version_compare($m->engineMin(), ThemeManifest::ENGINE_MIN, '>')) {
            $errors[] = 'Theme requires engine_min ' . $m->engineMin() . ' (have ' . ThemeManifest::ENGINE_MIN . ')';
        }
        if ($m->nameEn() === '') {
            $errors[] = 'Missing name_en';
        }

        foreach ($m->blocks() as $block) {
            if (!WebsiteBlockRegistry::isValid($block)) {
                $errors[] = 'Unsupported block type: ' . $block;
            }
        }

        foreach ($m->assets() as $asset) {
            $path = (string) ($asset['path'] ?? '');
            if ($path === '') {
                $errors[] = 'Asset missing path';
                continue;
            }
            if ($package->assetAbsolute($path) === null) {
                $errors[] = 'Missing asset file: ' . $path;
            }
        }

        $demo = $m->demo();
        if (isset($demo['pages']) && !is_array($demo['pages'])) {
            $errors[] = 'demo.pages must be an array';
        }
        if (isset($demo['forms']) && !is_array($demo['forms'])) {
            $errors[] = 'demo.forms must be an array';
        }

        return ['ok' => $errors === [], 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param array<string, mixed> $payload import/export envelope
     * @return array{ok:bool,errors:list<string>,warnings:list<string>}
     */
    public function validateImportPayload(array $payload): array
    {
        $errors = [];
        $warnings = [];
        if (($payload['format'] ?? '') !== 'rateb-theme-package-v1') {
            $errors[] = 'Unsupported import format';
        }
        $manifest = $payload['manifest'] ?? null;
        if (!is_array($manifest)) {
            $errors[] = 'Import missing manifest';

            return ['ok' => false, 'errors' => $errors, 'warnings' => $warnings];
        }
        try {
            $m = ThemeManifest::fromArray($manifest);
            if ($m->slug() === '') {
                $errors[] = 'Import manifest slug missing';
            }
            foreach ($m->blocks() as $block) {
                if (!WebsiteBlockRegistry::isValid($block)) {
                    $errors[] = 'Unsupported block in import: ' . $block;
                }
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
        $assets = $payload['assets'] ?? [];
        if (!is_array($assets)) {
            $warnings[] = 'Import assets must be an array';
        }

        return ['ok' => $errors === [], 'errors' => $errors, 'warnings' => $warnings];
    }
}
