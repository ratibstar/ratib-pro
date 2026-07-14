<?php
declare(strict_types=1);
$j = json_decode((string) file_get_contents('/tmp/rateb-admin-profile.json'), true);
if (!is_array($j)) {
    fwrite(STDERR, "no report\n");
    exit(1);
}

$analyze = static function (array $j, string $phase): void {
    $by = [];
    foreach ($j['sql'] ?? [] as $q) {
        if (($q['phase'] ?? '') !== $phase) {
            continue;
        }
        $c = (string) ($q['caller'] ?? '?');
        if (!isset($by[$c])) {
            $by[$c] = ['n' => 0, 'ms' => 0.0, 'sample' => $q['sql'] ?? ''];
        }
        $by[$c]['n']++;
        $by[$c]['ms'] += (float) ($q['dur_ms'] ?? 0);
    }
    uasort($by, static fn($a, $b) => $b['ms'] <=> $a['ms']);
    echo "=== {$phase} SQL BY CALLER ===\n";
    $i = 0;
    foreach ($by as $c => $v) {
        if ($i++ >= 30) {
            break;
        }
        printf("%7.2fms n=%-4d %s\n         %s\n", $v['ms'], $v['n'], $c, substr((string) $v['sample'], 0, 100));
    }
    echo "\n";
};

$analyze($j, 'layout_main');
$analyze($j, 'controller_admin_metrics');
$analyze($j, 'auth_bootstrap');

// Company lookup stack samples: find first 3 company SELECT callers with full backtrace isn't in report —
// group by fingerprint in layout
$fp = [];
foreach ($j['sql'] ?? [] as $q) {
    if (($q['phase'] ?? '') !== 'layout_main') {
        continue;
    }
    if (!str_contains((string) ($q['sql'] ?? ''), 'rateb_companies WHERE id')) {
        continue;
    }
    $c = (string) ($q['caller'] ?? '?');
    $fp[$c] = ($fp[$c] ?? 0) + 1;
}
arsort($fp);
echo "=== rateb_companies WHERE id callers in layout_main ===\n";
print_r($fp);

echo "\nTOTAL wall_ms=" . ($j['wall_ms'] ?? '?') . " sql=" . ($j['sql_total'] ?? '?') . "\n";
