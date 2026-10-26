<?php
declare(strict_types=1);

/*
 * TEMPORARY diagnostic, not a test: explains the paid-plan watermark-corner pixel count in
 * tests/video-worker.php. It renders the same synthetic videos through the same helpers and
 * reports what the measured corner contains. Remove once the cause is fixed.
 *
 * Env: RATEB_TEST_FFMPEG_NO_DRAWTEXT (pinned 7.1.1 build), RATEB_DIAG_SYSTEM_FFMPEG,
 * RATEB_DIAG_DIR (where frames are saved for the CI artifact). Always exits 0.
 */

require_once __DIR__ . '/../../app/video/runtime.php';
require_once __DIR__ . '/../support/video-inspect.php';

$out = rtrim((string) getenv('RATEB_DIAG_DIR'), '/');
if ($out === '' || !function_exists('imagepng')) {
    echo "DIAG skipped: RATEB_DIAG_DIR unset or GD missing\n";
    exit(0);
}
@mkdir($out, 0700, true);

$builds = [];
foreach (['pinned' => getenv('RATEB_TEST_FFMPEG_NO_DRAWTEXT'), 'system' => getenv('RATEB_DIAG_SYSTEM_FFMPEG')] as $label => $bin) {
    $bin = (string) $bin;
    if ($bin !== '' && is_file($bin) && is_executable($bin)) {
        $builds[$label] = $bin;
        $version = strtok((string) shell_exec(escapeshellarg($bin) . ' -hide_banner -version 2>&1'), "\n");
        echo "DIAG {$label} binary={$bin} {$version}\n";
    } else {
        echo "DIAG {$label} not available\n";
    }
}

/** @return array{0:int,1:int}|null */
function d_size(string $ffmpeg, string $mp4): ?array
{
    $info = (string) shell_exec(escapeshellarg($ffmpeg) . ' -hide_banner -i ' . escapeshellarg($mp4) . ' 2>&1');
    foreach (explode("\n", $info) as $line) {
        if (str_contains($line, 'Stream #') || str_contains($line, 'Duration')) {
            echo '    ' . trim($line) . "\n";
        }
    }
    return preg_match('/Video: .*?, (\d{2,5})x(\d{2,5})/', $info, $m) === 1 ? [(int) $m[1], (int) $m[2]] : null;
}

function d_grab(string $ffmpeg, string $mp4, string $seek, string $vf, string $raw): string
{
    $cmd = escapeshellarg($ffmpeg) . ' -nostdin -v error -y' . ($seek !== '' ? ' -ss ' . $seek : '')
        . ' -i ' . escapeshellarg($mp4) . ' -frames:v 1 -vf ' . escapeshellarg($vf) . ' -f rawvideo ' . escapeshellarg($raw)
        . ' 2> ' . escapeshellarg($raw . '.err');
    $ok = rateb_video_run_ffmpeg($cmd, 60);
    $bytes = $ok ? (string) @file_get_contents($raw) : '';
    if (!$ok) {
        echo '    grab failed: ' . trim((string) @file_get_contents($raw . '.err')) . "\n";
    }
    @unlink($raw);
    @unlink($raw . '.err');
    return $bytes;
}

function d_stats(string $gray): string
{
    $values = unpack('C*', $gray) ?: [];
    if ($values === []) {
        return 'empty';
    }
    $bins = array_fill(0, 8, 0);
    foreach ($values as $v) {
        $bins[$v >> 5]++;
    }
    $bright = count(array_filter($values, static fn (int $v): bool => $v > 200));
    return sprintf('n=%d min=%d max=%d mean=%.1f gt200=%d hist32=[%s]', count($values), min($values), max($values), array_sum($values) / count($values), $bright, implode(',', $bins));
}

function d_png(string $bytes, int $w, int $h, int $channels, string $file, ?array $box = null, int $scale = 1): void
{
    if (strlen($bytes) !== $w * $h * $channels) {
        echo "    png skipped: got " . strlen($bytes) . " bytes for {$w}x{$h}x{$channels}\n";
        return;
    }
    $img = imagecreatetruecolor($w, $h);
    for ($y = 0, $i = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++, $i += $channels) {
            $r = ord($bytes[$i]);
            [$g, $b] = $channels === 3 ? [ord($bytes[$i + 1]), ord($bytes[$i + 2])] : [$r, $r];
            imagesetpixel($img, $x, $y, ($r << 16) | ($g << 8) | $b);
        }
    }
    if ($box !== null) {
        imagerectangle($img, $box[0] - 1, $box[1] - 1, $box[0] + $box[2], $box[1] + $box[3], 0xFF0000);
    }
    if ($scale > 1) {
        $img = imagescale($img, $w * $scale, $h * $scale, IMG_NEAREST_NEIGHBOUR) ?: $img;
    }
    imagepng($img, $file);
}

$sizer = $out . '/mark-size.png';
$markSize = rateb_video_watermark_png($sizer) ? getimagesize($sizer) : false;
@unlink($sizer);
if ($markSize === false) {
    echo "DIAG watermark size unknown\n";
    exit(0);
}
[$mw, $mh] = $markSize;
$crop = 'crop=' . $mw . ':' . $mh . ':iw-' . ($mw + 24) . ':ih-' . ($mh + 24);
echo "DIAG corner {$mw}x{$mh} filter={$crop}\n";

$videos = [];
foreach ($builds as $label => $bin) {
    $dir = $out . '/' . $label;
    @mkdir($dir, 0700, true);
    foreach (['paid' => false, 'free' => true] as $plan => $mark) {
        $render = vt_render($bin, $dir, $mark, true);
        echo "DIAG {$label} render {$plan}: {$render['detail']}\n";
        if ($render['file'] === '') {
            continue;
        }
        $seen = vt_inspect($bin, $render['file'], $dir);
        echo "DIAG {$label} {$plan} vt_inspect " . json_encode($seen) . "\n";
        $videos["{$label}-{$plan}"] = $render['file'];
    }
    if (is_file($dir . '/vt-clip-0.mp4')) {
        $videos["{$label}-clip0"] = $dir . '/vt-clip-0.mp4';
    }
    @copy($dir . '/vt-still.jpg', $out . '/' . $label . '-still.jpg');
}

foreach ($videos as $name => $mp4) {
    foreach ($builds as $decoder => $bin) {
        echo "DIAG video={$name} decoder={$decoder}\n";
        $size = d_size($bin, $mp4);
        foreach (['0.5' => '0.5', 'first' => ''] as $at => $seek) {
            echo "    corner@{$at} " . d_stats(d_grab($bin, $mp4, $seek, $crop . ',format=gray', $out . '/c.raw')) . "\n";
        }
        $rgb = d_grab($bin, $mp4, '0.5', $crop . ',format=rgb24', $out . '/c.raw');
        if ($rgb !== '') {
            $sum = [0, 0, 0];
            foreach (str_split($rgb, 3) as $px) {
                if (strlen($px) === 3) {
                    $sum[0] += ord($px[0]);
                    $sum[1] += ord($px[1]);
                    $sum[2] += ord($px[2]);
                }
            }
            $n = max(1, intdiv(strlen($rgb), 3));
            printf("    corner@0.5 mean_rgb=(%.0f,%.0f,%.0f)\n", $sum[0] / $n, $sum[1] / $n, $sum[2] / $n);
            d_png($rgb, $mw, $mh, 3, "{$out}/{$name}-dec-{$decoder}-corner-x4.png", null, 4);
        }
        if ($size !== null) {
            [$w, $h] = $size;
            $gray = d_grab($bin, $mp4, '0.5', 'format=gray', $out . '/f.raw');
            echo "    frame@0.5 {$w}x{$h} " . d_stats($gray) . "\n";
            $full = d_grab($bin, $mp4, '0.5', 'format=rgb24', $out . '/f.raw');
            d_png($full, $w, $h, 3, "{$out}/{$name}-dec-{$decoder}-frame.png", [$w - ($mw + 24), $h - ($mh + 24), $mw, $mh]);
        }
    }
}

foreach (glob($out . '/*/*.{mp4,wav,png,jpg,txt}', GLOB_BRACE) ?: [] as $file) {
    if (!str_ends_with($file, '.mp4')) {
        @unlink($file);
    }
}
echo "DIAG done\n";
exit(0);
