<?php
declare(strict_types=1);

$dir = $argv[1] ?? (dirname(__DIR__) . '/public/assets/image-seeds');
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    fwrite(STDERR, "cannot create seed dir\n");
    exit(1);
}
$w = 512;
$h = 512;
$im = imagecreatetruecolor($w, $h);
$wood = imagecolorallocate($im, 140, 98, 62);
imagefilledrectangle($im, 0, 0, $w, $h, $wood);
$grain = imagecolorallocate($im, 120, 82, 50);
for ($y = 0; $y < $h; $y += 7) {
    imageline($im, 0, $y, $w, $y + 2, $grain);
}
$posters = [
    [210, 70, 70],
    [50, 120, 200],
    [230, 170, 40],
    [60, 160, 120],
    [160, 80, 180],
    [40, 90, 150],
    [220, 100, 60],
    [90, 90, 90],
];
$boxes = [
    [40, 40, 220, 280],
    [250, 30, 230, 260],
    [40, 300, 200, 180],
    [260, 300, 210, 170],
    [120, 120, 180, 200],
    [300, 140, 160, 180],
];
$white = imagecolorallocate($im, 245, 245, 245);
foreach ($boxes as $i => $box) {
    [$x, $y, $bw, $bh] = $box;
    $c = $posters[$i % count($posters)];
    $col = imagecolorallocate($im, $c[0], $c[1], $c[2]);
    imagefilledrectangle($im, $x, $y, $x + $bw, $y + $bh, $col);
    imagefilledrectangle($im, $x + 18, $y + 24, $x + $bw - 18, $y + 48, $white);
    imagefilledrectangle($im, $x + 18, $y + 60, $x + $bw - 40, $y + 72, $white);
    imagefilledrectangle($im, $x + 18, $y + 80, $x + $bw - 50, $y + 90, $white);
}
$path = $dir . '/marketing-flatlay.png';
imagepng($im, $path);
imagedestroy($im);
echo $path . ' ' . filesize($path) . PHP_EOL;
