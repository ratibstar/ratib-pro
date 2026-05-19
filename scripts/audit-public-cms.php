<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/site-content.php';
require dirname(__DIR__) . '/includes/site-content-home-data.php';
require dirname(__DIR__) . '/includes/ratib-public-cms.php';
require dirname(__DIR__) . '/includes/ratib-operational-proof-data.php';

$def = ratib_site_content_defaults_home();
$groups = ratib_site_content_public_editor_groups();

$editorKeys = [];
$dups = [];
foreach ($groups as $g) {
    foreach ($g['fields'] ?? [] as $f) {
        if (!isset($f['key'])) {
            continue;
        }
        $k = (string) $f['key'];
        if (isset($editorKeys[$k])) {
            $dups[] = $k;
        }
        $editorKeys[$k] = ($editorKeys[$k] ?? 0) + 1;
    }
    if (!empty($g['repeat']) && is_array($g['repeat'])) {
        $r = $g['repeat'];
        for ($i = (int) ($r['from'] ?? 1); $i <= (int) ($r['to'] ?? 1); $i++) {
            foreach ($r['fields'] ?? [] as $sf) {
                $k = (string) $r['prefix'] . '.' . $i . (string) ($sf['suffix'] ?? '');
                if (isset($editorKeys[$k])) {
                    $dups[] = $k;
                }
                $editorKeys[$k] = ($editorKeys[$k] ?? 0) + 1;
            }
        }
    }
}

$missingFromDef = array_diff(array_keys($editorKeys), array_keys($def));
$defaultsNotInEditor = [];
foreach (array_keys($def) as $k) {
    if (preg_match('/^(home\.|profile\.|arch\.|trust\.|proc\.|public\.|opproof\.)/', $k) && !isset($editorKeys[$k])) {
        $defaultsNotInEditor[] = $k;
    }
}

$missingImages = [];
foreach ($def as $k => $v) {
    $v = trim((string) $v);
    if ($v === '') {
        continue;
    }
    if (str_starts_with($v, 'scmedia:')) {
        $name = substr($v, 8);
        $fs = dirname(__DIR__) . '/uploads/ratib_cms_media/' . $name;
        if (!is_file($fs)) {
            $missingImages[] = "$k => $v";
        }
        continue;
    }
    if (preg_match('/\.(png|jpe?g|webp|gif)$/i', $v) && !preg_match('#^https?://#i', $v)) {
        if (!is_file(dirname(__DIR__) . '/' . ltrim($v, '/'))) {
            $missingImages[] = "$k => $v";
        }
    }
}

$cfg = ratib_operational_proof_config('');
echo "Editor keys: " . count($editorKeys) . PHP_EOL;
echo "Defaults: " . count($def) . PHP_EOL;
echo "Duplicate editor keys: " . count(array_unique($dups)) . PHP_EOL;
echo "Editor keys missing from defaults: " . count($missingFromDef) . PHP_EOL;
echo "Public defaults missing from editor: " . count($defaultsNotInEditor) . PHP_EOL;
echo "Missing image files: " . count($missingImages) . PHP_EOL;

if ($dups) {
    echo PHP_EOL . "Duplicates:" . PHP_EOL;
    foreach (array_unique($dups) as $d) {
        echo "  $d" . PHP_EOL;
    }
}
if ($missingFromDef) {
    echo PHP_EOL . "Missing defaults:" . PHP_EOL;
    foreach ($missingFromDef as $m) {
        echo "  $m" . PHP_EOL;
    }
}
if ($missingImages) {
    echo PHP_EOL . "Missing images:" . PHP_EOL;
    foreach ($missingImages as $m) {
        echo "  $m" . PHP_EOL;
    }
}
if ($defaultsNotInEditor && ($argc < 2 || $argv[1] !== '--quiet')) {
    echo PHP_EOL . "Defaults not in flat editor (may use slots/repeat):" . PHP_EOL;
    foreach ($defaultsNotInEditor as $m) {
        echo "  $m" . PHP_EOL;
    }
}

echo PHP_EOL . "Government screenshot URLs:" . PHP_EOL;
foreach ($cfg['government']['screenshots'] as $s) {
    echo '  ' . ($s['title'] ?? '') . ': ' . ($s['src'] ?? '') . PHP_EOL;
}
