<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Deferred activation: the open-app page remembers (client IP + app → code) so the app links itself
 * on its next launch even when the activation intent never reached it (fresh APK install, opened
 * from the launcher). Codes are not secrets — they are printed on public activation pages.
 */
final class MobileAppPendingActivationService
{
    private const TTL = 1800;

    public function remember(string $code, string $app): void
    {
        $code = MobileAppActivationService::normalize($code);
        $file = $this->file($app);
        if ($code === '' || $file === '') {
            return;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode(['code' => $code, 'at' => time()]), LOCK_EX);
    }

    /** One-time: returns the remembered code for this client and app, then forgets it. */
    public function take(string $app): string
    {
        $file = $this->file($app);
        if ($file === '' || !is_file($file)) {
            return '';
        }
        $row = json_decode((string) @file_get_contents($file), true);
        @unlink($file);
        if (!is_array($row) || (int) ($row['at'] ?? 0) < time() - self::TTL) {
            return '';
        }

        return MobileAppActivationService::normalize((string) ($row['code'] ?? ''));
    }

    private function file(string $app): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '' || !in_array($app, MobileAppApkService::APPS, true)) {
            return '';
        }
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);

        return $root . '/storage/mobile-pending/' . hash('sha256', $ip . '|' . $app) . '.json';
    }
}
