<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$lines = file($root . '/pages/home.php');
$chunk = array_slice($lines, 761, 451);
$out = implode('', $chunk);
$out = preg_replace('/rateb_public_marketing_should_render_deep\(\)/', 'true', $out);
$out = preg_replace('/<\?php rateb_marketing_expand_bar_render\([^)]+\); \?>\s*/', '', $out);

$dir = $root . '/includes/marketing-unified';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

file_put_contents(
    $dir . '/sections-body.php',
    "<?php\n/** Ported from pages/home.php (unified marketing at /). Do not edit pages/home.php. */\n" . $out
);

echo 'Wrote ' . strlen($out) . " bytes\n";
