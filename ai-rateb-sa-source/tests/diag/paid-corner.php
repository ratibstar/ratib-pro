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

/*
 * Stage isolation: the same still through decode, scale, zoompan, the full clip filter, and
 * libx264, each build's luma plane compared with the system build's at that stage.
 */
function d_raw(string $ffmpeg, string $input, string $vf, int $frames, string $raw): string
{
    $cmd = escapeshellarg($ffmpeg) . ' -nostdin -v error -y -i ' . escapeshellarg($input)
        . ($vf !== '' ? ' -vf ' . escapeshellarg($vf) : '') . ' -frames:v ' . $frames . ' -f rawvideo ' . escapeshellarg($raw)
        . ' 2> ' . escapeshellarg($raw . '.err');
    $ok = rateb_video_run_ffmpeg($cmd, 120);
    $bytes = $ok ? (string) @file_get_contents($raw) : '';
    if (!$ok) {
        echo '    raw failed: ' . trim((string) @file_get_contents($raw . '.err')) . "\n";
    }
    @unlink($raw);
    @unlink($raw . '.err');
    return $bytes;
}

/** Luma of the last of $frames frames, or '' with the bytes-per-pixel reported. */
function d_luma(string $bytes, int $w, int $h, int $frames): string
{
    $frame = intdiv(strlen($bytes), max(1, $frames));
    printf('    bytes=%d per_px=%.2f', strlen($bytes), $frame / ($w * $h));
    return $frame >= $w * $h && $frame * $frames === strlen($bytes) ? substr($bytes, ($frames - 1) * $frame, $w * $h) : '';
}

/** Mean |row - next row| (sampled), which stays near 0 for flat colour fields and soars for stripes. */
function d_stripe(string $y, int $w, int $h): float
{
    $sum = 0;
    $n = 0;
    for ($r = 0; $r + 1 < $h; $r += 2) {
        for ($c = 0; $c < $w; $c += 4) {
            $sum += abs(ord($y[$r * $w + $c]) - ord($y[($r + 1) * $w + $c]));
            $n++;
        }
    }
    return $n > 0 ? $sum / $n : -1.0;
}

function d_mean(string $y): float
{
    $sum = 0;
    foreach (count_chars($y, 1) as $value => $count) {
        $sum += $value * $count;
    }
    return $sum / max(1, strlen($y));
}

/** @return array{0:float,1:float} mean absolute difference and % of samples differing by more than 32 */
function d_diff(string $a, string $b): array
{
    if ($a === '' || strlen($a) !== strlen($b)) {
        return [-1.0, -1.0];
    }
    $sum = 0;
    $big = 0;
    $n = 0;
    for ($i = 0, $len = strlen($a); $i < $len; $i += 3) {
        $d = abs(ord($a[$i]) - ord($b[$i]));
        $sum += $d;
        $big += $d > 32 ? 1 : 0;
        $n++;
    }
    return [$sum / max(1, $n), 100 * $big / max(1, $n)];
}

$still = is_file($out . '/pinned-still.jpg') ? $out . '/pinned-still.jpg' : $out . '/system-still.jpg';
if (isset($builds['pinned'], $builds['system']) && is_file($still)) {
    $zoom = rateb_video_zoompan_filter(0, 25);
    $stages = [
        'decode' => ['', 1, 360, 640],
        'scale' => ['scale=1440:2560', 1, 1440, 2560],
        'zoompan' => [substr($zoom, 0, (int) strrpos($zoom, ',format=')), 13, 720, 1280],
        'clip_filter' => [$zoom, 13, 720, 1280],
        'libx264' => ['', 13, 720, 1280],
    ];
    $gd = imagecreatefromjpeg($still);
    $ref = '';
    for ($r = 0; $r < 640; $r++) {
        for ($c = 0; $c < 360; $c++) {
            $px = imagecolorat($gd, $c, $r);
            $ref .= chr((int) round(0.299 * (($px >> 16) & 255) + 0.587 * (($px >> 8) & 255) + 0.114 * ($px & 255)));
        }
    }
    $first = '';
    foreach ($stages as $stage => [$vf, $frames, $w, $h]) {
        $luma = [];
        foreach (['system', 'pinned'] as $label) {
            echo "DIAG stage={$stage} build={$label} vf=" . ($vf === '' ? '(none)' : $vf) . "\n";
            if ($stage === 'libx264') {
                $clip = $out . '/stage-' . $label . '.mp4';
                if (!rateb_video_run_ffmpeg(rateb_video_clip_command($builds[$label], $still, 0, 25, $clip), 120)) {
                    echo "    clip render failed\n";
                    continue;
                }
                $bytes = d_raw($builds['system'], $clip, '', $frames, $out . '/stage.raw');
                @unlink($clip);
            } else {
                $bytes = d_raw($builds[$label], $still, $vf, $frames, $out . '/stage.raw');
            }
            $y = d_luma($bytes, $w, $h, $frames);
            $luma[$label] = $y;
            if ($y === '') {
                echo " (luma unreadable)\n";
                continue;
            }
            printf(" %dx%d ymean=%.1f stripe=%.2f", $w, $h, d_mean($y), d_stripe($y, $w, $h));
            if ($stage === 'decode') {
                [$mean, $big] = d_diff($y, $ref);
                printf(' vs_gd_luma=%.2f gt32_pct=%.2f', $mean, $big);
            }
            echo "\n";
            d_png($y, $w, $h, 1, "{$out}/stage-{$stage}-{$label}-luma.png");
        }
        [$mean, $big] = d_diff($luma['pinned'] ?? '', $luma['system'] ?? '');
        printf("DIAG stage=%s pinned_vs_system mean_abs=%.2f gt32_pct=%.2f\n", $stage, $mean, $big);
        if ($first === '' && ($mean < 0 || $mean > 4 || $big > 0.5)) {
            $first = $stage;
        }
    }
    echo 'DIAG first divergent stage: ' . ($first === '' ? 'none' : $first) . "\n";
}

foreach (glob($out . '/*/*.{mp4,wav,png,jpg,txt}', GLOB_BRACE) ?: [] as $file) {
    if (!str_ends_with($file, '.mp4')) {
        @unlink($file);
    }
}
echo "DIAG done\n";
exit(0);
