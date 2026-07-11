<?php

declare(strict_types=1);

/** Generate ERP PWA PNG icons. Run: php offline/scripts/generate-erp-pwa-icons.php */

$root = dirname(__DIR__, 2);
$dir = $root . '/public/assets/pwa';
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "Cannot create {$dir}\n");
    exit(1);
}

$svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
  <rect width="512" height="512" fill="#0f1117"/>
  <rect x="92" y="92" width="328" height="328" rx="48" fill="#3b82f6"/>
  <text x="256" y="310" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="180" font-weight="700" fill="#ffffff">R</text>
</svg>
SVG;
file_put_contents($dir . '/erp-icon.svg', $svg);

function rateb_write_png(string $path, int $size): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    $im = imagecreatetruecolor($size, $size);
    if ($im === false) {
        return false;
    }
    $bg = imagecolorallocate($im, 15, 17, 23);
    $fg = imagecolorallocate($im, 59, 130, 246);
    $tx = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $size - 1, $size - 1, $bg);
    $pad = (int) round($size * 0.18);
    imagefilledrectangle($im, $pad, $pad, $size - $pad, $size - $pad, $fg);
    $font = 5;
    $text = 'R';
    $tw = imagefontwidth($font) * strlen($text);
    $th = imagefontheight($font);
    imagestring($im, $font, (int) (($size - $tw) / 2), (int) (($size - $th) / 2), $text, $tx);
    $ok = imagepng($im, $path);
    imagedestroy($im);

    return (bool) $ok;
}

$ok192 = rateb_write_png($dir . '/erp-icon-192.png', 192);
$ok512 = rateb_write_png($dir . '/erp-icon-512.png', 512);

if (!$ok192 || !$ok512) {
    // Minimal solid PNG (1x1) as build fallback when GD is unavailable.
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    if ($bytes === false) {
        fwrite(STDERR, "PNG fallback decode failed\n");
        exit(1);
    }
    if (!$ok192) {
        file_put_contents($dir . '/erp-icon-192.png', $bytes);
    }
    if (!$ok512) {
        file_put_contents($dir . '/erp-icon-512.png', $bytes);
    }
    echo "Wrote icons (GD unavailable — placeholder PNG)\n";
} else {
    echo "Wrote GD icons to {$dir}\n";
}
