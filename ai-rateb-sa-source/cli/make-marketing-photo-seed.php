<?php
declare(strict_types=1);

/** Build a more photographic poster flat-lay seed (server GD). */
$dir = $argv[1] ?? (dirname(__DIR__) . '/public/assets/image-seeds');
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    fwrite(STDERR, "cannot create seed dir\n");
    exit(1);
}
$w = 768;
$h = 768;
$im = imagecreatetruecolor($w, $h);
imageantialias($im, true);

// Oak desk base with soft vignette.
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $grain = (($x * 17 + $y * 31) % 17) - 8;
        $r = max(0, min(255, 156 + $grain + (int) (($x + $y) / 40)));
        $g = max(0, min(255, 112 + (int) ($grain * 0.7)));
        $b = max(0, min(255, 72 + (int) ($grain * 0.4)));
        $edge = (int) (min($x, $y, $w - $x, $h - $y) / 8);
        $r = max(40, $r - (18 - min(18, $edge)));
        $g = max(30, $g - (14 - min(14, $edge)));
        $b = max(20, $b - (10 - min(10, $edge)));
        imagesetpixel($im, $x, $y, imagecolorallocate($im, $r, $g, $b));
    }
}

$posters = [
    [42, 48, 300, 380, [92, 58, 168], [180, 160, 230]],
    [370, 40, 320, 300, [28, 110, 180], [140, 200, 235]],
    [60, 450, 280, 250, [210, 90, 70], [240, 180, 120]],
    [380, 360, 300, 320, [40, 140, 120], [160, 220, 190]],
    [250, 220, 220, 260, [60, 70, 150], [150, 160, 230]],
];

foreach ($posters as $p) {
    [$x, $y, $bw, $bh, $c1, $c2] = $p;
    // Soft shadow
    $shadow = imagecolorallocatealpha($im, 20, 10, 5, 80);
    imagefilledrectangle($im, $x + 10, $y + 14, $x + $bw + 10, $y + $bh + 14, $shadow);
    // Poster body with vertical gradient
    for ($yy = 0; $yy < $bh; $yy++) {
        $t = $yy / max(1, $bh - 1);
        $r = (int) ($c1[0] * (1 - $t) + $c2[0] * $t);
        $g = (int) ($c1[1] * (1 - $t) + $c2[1] * $t);
        $b = (int) ($c1[2] * (1 - $t) + $c2[2] * $t);
        $col = imagecolorallocate($im, $r, $g, $b);
        imageline($im, $x, $y + $yy, $x + $bw, $y + $yy, $col);
    }
    // Fake photo block + bars (no readable text)
    $panel = imagecolorallocatealpha($im, 255, 255, 255, 70);
    imagefilledrectangle($im, $x + 24, $y + 28, $x + $bw - 24, $y + (int) ($bh * 0.55), $panel);
    $bar = imagecolorallocatealpha($im, 255, 255, 255, 40);
    imagefilledrectangle($im, $x + 24, $y + (int) ($bh * 0.62), $x + $bw - 40, $y + (int) ($bh * 0.68), $bar);
    imagefilledrectangle($im, $x + 24, $y + (int) ($bh * 0.72), $x + $bw - 70, $y + (int) ($bh * 0.76), $bar);
}

$path = rtrim($dir, '/\\') . '/marketing-flatlay.jpg';
imagejpeg($im, $path, 90);
imagedestroy($im);
echo $path . ' ' . filesize($path) . PHP_EOL;
