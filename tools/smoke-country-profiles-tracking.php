<?php
declare(strict_types=1);

/**
 * Static smoke checks for country-profile + tracking integrations.
 * Usage: php tools/smoke-country-profiles-tracking.php
 */

$root = dirname(__DIR__);
$failures = [];
$passes = [];

function checkContains(string $path, array $needles, array &$failures, array &$passes): void
{
    if (!is_file($path)) {
        $failures[] = "Missing file: {$path}";
        return;
    }
    $txt = (string) file_get_contents($path);
    foreach ($needles as $n) {
        if (strpos($txt, $n) === false) {
            $failures[] = "Missing snippet in {$path}: {$n}";
        } else {
            $passes[] = "OK {$path} contains: {$n}";
        }
    }
}

checkContains(
    $root . '/control-panel/api/control/country-profiles.php',
    ['Unknown requirement field', 'control_country_profiles_audit', "action === 'export'", "action === 'preview'", "postAction === 'import'"],
    $failures,
    $passes
);

checkContains(
    $root . '/api/workers/core/create.php',
    ['ratib_enforce_country_requirements($data, null);'],
    $failures,
    $passes
);
checkContains(
    $root . '/api/workers/core/update.php',
    ['ratib_enforce_country_requirements($data, $oldWorker);'],
    $failures,
    $passes
);
checkContains(
    $root . '/js/worker/worker-form.js',
    ['getCountrySpecificRequirements', 'applyCountrySpecificRequirements', 'updateCountryRequirementsPanelLive'],
    $failures,
    $passes
);
checkContains(
    $root . '/control-panel/js/control/tracking-map.js',
    ['trackingFilterSearch', 'q: (document.getElementById(\'trackingFilterSearch\')'],
    $failures,
    $passes
);
checkContains(
    $root . '/admin/assets/dashboard.js',
    ['worker-tracking.php?action=latest&limit=200', '&q=', 'worker_identity'],
    $failures,
    $passes
);
checkContains(
    $root . '/control-panel/js/control/government.js',
    ['inspFilterSearch', 'qp.q = s.value.trim()'],
    $failures,
    $passes
);

echo "Smoke checks: " . count($passes) . " passed, " . count($failures) . " failed" . PHP_EOL;
if (!empty($failures)) {
    foreach ($failures as $f) echo "[FAIL] {$f}" . PHP_EOL;
    exit(1);
}
foreach ($passes as $p) echo "[OK] {$p}" . PHP_EOL;
exit(0);

