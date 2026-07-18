<?php
/** Classify Bootstrap eager-require files: single-class (autoloadable) vs multi-class bags. */
$base = dirname(__DIR__, 2);
$src = file_get_contents($base . '/app/Core/Bootstrap.php');
if (!preg_match('/foreach \(\[(.*?)\] as \$bundle\)/s', $src, $m)) {
    fwrite(STDERR, "foreach block not found\n");
    exit(1);
}
preg_match_all("/'(\\/app\\/[^']+)'/", $m[1], $paths);
$single = [];
$multi = [];
$missing = [];
$noclass = [];
foreach ($paths[1] as $rel) {
    $f = $base . $rel;
    if (!is_file($f)) {
        $missing[] = $rel;
        continue;
    }
    $c = file_get_contents($f);
    preg_match_all('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', $c, $cm);
    $classes = $cm[1] ?? [];
    $bn = basename($f, '.php');
    if (count($classes) === 0) {
        $noclass[] = $rel;
        continue;
    }
    if (count($classes) === 1 && strcasecmp($classes[0], $bn) === 0) {
        $single[] = $rel;
    } else {
        $multi[] = [
            'file' => $rel,
            'n' => count($classes),
            'classes' => $classes,
        ];
    }
}
echo json_encode([
    'single_count' => count($single),
    'multi_count' => count($multi),
    'missing_count' => count($missing),
    'noclass_count' => count($noclass),
    'single' => $single,
    'multi' => array_map(static function ($row) {
        return [
            'file' => $row['file'],
            'n' => $row['n'],
            'sample' => array_slice($row['classes'], 0, 8),
        ];
    }, $multi),
    'missing' => $missing,
    'noclass' => $noclass,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
