<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Local QR PNG generation — no external HTTP APIs (Phase D.1 offline).
 */
final class LocalQrRenderer
{
    private static bool $libLoaded = false;

    public static function png(string $payload, int $size = 280): string
    {
        $payload = trim($payload);
        if ($payload === '' || strlen($payload) > 500) {
            return '';
        }
        if (!extension_loaded('gd')) {
            throw new \RuntimeException(
                'Branch Appliance QR PNG requires PHP GD extension.'
            );
        }

        $size = max(120, min(500, $size));
        self::ensureLibrary();

        $matrixPointSize = self::matrixPointSizeForPixels($size);
        $temp = tempnam(sys_get_temp_dir(), 'ratebqr_');
        if ($temp === false) {
            return '';
        }

        try {
            \QRcode::png($payload, $temp, \QR_ECLEVEL_H, $matrixPointSize, 2, false, 0xFFFFFF, 0x000000);
            if (!is_file($temp)) {
                return '';
            }
            $bin = (string) file_get_contents($temp);

            return $bin !== '' ? self::resizePng($bin, $size) : '';
        } finally {
            @unlink($temp);
        }
    }

    /** SVG QR — no GD required. */
    public static function svg(string $payload, int $size = 280): string
    {
        $payload = trim($payload);
        if ($payload === '' || strlen($payload) > 500) {
            return '';
        }
        self::ensureLibrary();
        $size = max(120, min(500, $size));
        ob_start();
        try {
            \QRcode::svg($payload, false, \QR_ECLEVEL_H, self::matrixPointSizeForPixels($size), 2);
        } catch (\Throwable) {
            ob_end_clean();

            return '';
        }
        $svg = (string) ob_get_clean();

        return $svg !== '' ? $svg : '';
    }

    /** Inline admin preview (PNG data URI, else SVG data URI). */
    public static function previewDataUri(string $payload, int $size = 400): string
    {
        try {
            $png = self::png($payload, $size);
            if ($png !== '') {
                return 'data:image/png;base64,' . base64_encode($png);
            }
        } catch (\Throwable) {
            // SVG fallback below
        }
        $svg = self::svg($payload, $size);
        if ($svg !== '') {
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        }

        return '';
    }

    private static function ensureLibrary(): void
    {
        if (self::$libLoaded) {
            return;
        }
        $root = defined('RATEB_ROOT') ? (string) RATEB_ROOT : dirname(__DIR__, 2);
        $cacheDir = $root . '/lib/phpqrcode/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0770, true);
        }
        $qrlib = $root . '/lib/phpqrcode/qrlib.php';
        if (!is_file($qrlib)) {
            throw new \RuntimeException('Local QR library missing: ' . $qrlib);
        }
        require_once $qrlib;
        self::$libLoaded = true;
    }

    private static function matrixPointSizeForPixels(int $targetPx): int
    {
        if ($targetPx <= 180) {
            return 4;
        }
        if ($targetPx <= 280) {
            return 6;
        }
        if ($targetPx <= 360) {
            return 8;
        }

        return 10;
    }

    private static function resizePng(string $png, int $targetPx): string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            return $png;
        }
        $src = @imagecreatefromstring($png);
        if ($src === false) {
            return $png;
        }
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);

            return $png;
        }
        if ($srcW === $targetPx && $srcH === $targetPx) {
            imagedestroy($src);

            return $png;
        }
        $dst = imagecreatetruecolor($targetPx, $targetPx);
        if ($dst === false) {
            imagedestroy($src);

            return $png;
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $white = imagecolorallocatealpha($dst, 255, 255, 255, 0);
        imagefilledrectangle($dst, 0, 0, $targetPx, $targetPx, $white);
        imagecopyresized($dst, $src, 0, 0, 0, 0, $targetPx, $targetPx, $srcW, $srcH);
        imagedestroy($src);
        ob_start();
        imagepng($dst);
        imagedestroy($dst);
        $out = (string) ob_get_clean();

        return $out !== '' ? $out : $png;
    }
}
