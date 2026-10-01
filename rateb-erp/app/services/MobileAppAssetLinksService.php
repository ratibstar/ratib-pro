<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Android App Links: one SHA-256 (release keystore), many HR packages (unified + sa.rateb.hr.mobile.c*).
 */
final class MobileAppAssetLinksService
{
    /** @return list<string> */
    public function collectHrPackages(): array
    {
        $packages = [(new MobileAppApkService())->appInfo('hr')['package']];
        $dir = rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/') . '/public/downloads/' . MobileAppBrandedService::PUBLIC_DIR;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') ?: [] as $metaFile) {
                $meta = json_decode((string) @file_get_contents($metaFile), true);
                $pkg = is_array($meta) ? trim((string) ($meta['package'] ?? '')) : '';
                if (preg_match('/^sa\.rateb\.hr\.mobile(\.c[1-9][0-9]*)?$/', $pkg)) {
                    $packages[] = $pkg;
                }
            }
        }

        return array_values(array_unique($packages));
    }

    public function refresh(): void
    {
        $fingerprints = $this->readFingerprints();
        if ($fingerprints === []) {
            return;
        }
        $entries = [];
        foreach ($this->collectHrPackages() as $package) {
            $entries[] = [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $package,
                    'sha256_cert_fingerprints' => $fingerprints,
                ],
            ];
        }
        $json = (string) json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        $erpRoot = rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/');
        $roots = array_unique([$erpRoot, dirname($erpRoot)]);
        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }
            foreach (['public/.well-known/assetlinks.json', '.well-known/assetlinks.json'] as $rel) {
                $path = $root . '/' . $rel;
                $parent = dirname($path);
                if (!is_dir($parent)) {
                    @mkdir($parent, 0775, true);
                }
                if (is_dir($parent)) {
                    @file_put_contents($path, $json);
                }
            }
        }
    }

    /** @return list<string> */
    private function readFingerprints(): array
    {
        $env = trim((string) (getenv('RATEB_ANDROID_RELEASE_SHA256') ?: ''));
        if ($env !== '') {
            return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $env) ?: [])));
        }
        $candidates = [
            rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/') . '/public/.well-known/assetlinks.json',
            dirname(rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/')) . '/public/.well-known/assetlinks.json',
        ];
        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($path), true);
            if (!is_array($data)) {
                continue;
            }
            foreach ($data as $row) {
                $fps = is_array($row['target'] ?? null) ? ($row['target']['sha256_cert_fingerprints'] ?? null) : null;
                if (is_array($fps) && $fps !== []) {
                    return array_values(array_filter(array_map('strval', $fps)));
                }
            }
        }

        return [];
    }
}
