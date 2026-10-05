<?php
declare(strict_types=1);

function rateb_apply_watermark(string $bytes, string $mime): array
{
    if (!function_exists('imagecreatefromstring')) {
        throw new RuntimeException('watermark');
    }
    $image = @imagecreatefromstring($bytes);
    if ($image === false) {
        throw new RuntimeException('watermark');
    }
    $width = imagesx($image);
    $height = imagesy($image);
    $label = 'RATEB AI';
    $background = imagecolorallocatealpha($image, 24, 16, 48, 45);
    $foreground = imagecolorallocate($image, 255, 255, 255);
    $boxWidth = imagefontwidth(3) * strlen($label) + 18;
    $boxHeight = imagefontheight(3) + 12;
    imagefilledrectangle($image, max(0, $width - $boxWidth - 10), max(0, $height - $boxHeight - 10), $width - 8, $height - 8, $background);
    imagestring($image, 3, max(8, $width - $boxWidth), max(8, $height - $boxHeight), $label, $foreground);
    ob_start();
    $saved = false;
    $outMime = $mime;
    if ($mime === 'image/png') {
        $saved = imagepng($image);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        $saved = imagewebp($image, null, 85);
    } elseif ($mime === 'image/gif') {
        $saved = imagegif($image);
    } else {
        $saved = imagejpeg($image, null, 90);
        $outMime = 'image/jpeg';
    }
    $output = ob_get_clean();
    imagedestroy($image);
    if ($saved !== true || !is_string($output) || $output === '') {
        throw new RuntimeException('watermark');
    }
    return ['bytes' => $output, 'mime' => $outMime];
}
