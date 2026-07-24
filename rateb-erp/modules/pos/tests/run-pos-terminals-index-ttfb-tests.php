<?php

declare(strict_types=1);

/**
 * Regression: POS terminals index must not mutate warehouses on GET.
 *
 * Usage: php modules/pos/tests/run-pos-terminals-index-ttfb-tests.php
 */

$root = dirname(__DIR__, 3);
$fail = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $fail;
    if ($ok) {
        echo 'PASS: ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
        return;
    }
    $fail++;
    echo 'FAIL: ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
}

$lookup = (string) file_get_contents($root . '/app/services/FormLookupService.php');
$crud = (string) file_get_contents($root . '/views/components/crud-index.php');
$wh = (string) file_get_contents($root . '/app/services/WarehouseService.php');
$terminalsIndex = (string) file_get_contents($root . '/modules/pos/views/terminals/index.php');
$devicesCtrl = (string) file_get_contents($root . '/modules/pos/app/Controllers/PosDevicesController.php');
$settingsCtrl = (string) file_get_contents($root . '/modules/pos/app/Controllers/PosSettingsController.php');

assert_true(
    'valueLabelMapForIds exists (read-only index path)',
    str_contains($lookup, 'function valueLabelMapForIds')
);
assert_true(
    'warehouseLabelsByIds exists',
    str_contains($lookup, 'function warehouseLabelsByIds')
);
$whLabelsFn = '';
if (preg_match('/private function warehouseLabelsByIds\(array \$ids\): array\s*\{([\s\S]*?)\n    \}\n\n    \/\*\*/', $lookup, $m)) {
    $whLabelsFn = $m[1];
} elseif (preg_match('/private function warehouseLabelsByIds\(array \$ids\): array\s*\{([\s\S]*?)\n    \}\n\n    \/\*\*\n     \* Read-only branch/', $lookup, $m2)) {
    $whLabelsFn = $m2[1];
} else {
    // Fallback: slice between warehouseLabelsByIds and branchLabelsByIds
    $start = strpos($lookup, 'function warehouseLabelsByIds');
    $end = strpos($lookup, 'function branchLabelsByIds');
    if ($start !== false && $end !== false && $end > $start) {
        $whLabelsFn = substr($lookup, $start, $end - $start);
    }
}

assert_true(
    'warehouseLabelsByIds body extracted',
    $whLabelsFn !== '',
    'len=' . strlen($whLabelsFn)
);
assert_true(
    'warehouseLabelsByIds has no ensureDefaultWarehouse',
    $whLabelsFn !== '' && !str_contains($whLabelsFn, 'ensureDefaultWarehouse')
);
assert_true(
    'warehouseLabelsByIds has no GET_LOCK',
    $whLabelsFn !== '' && !str_contains($whLabelsFn, 'GET_LOCK')
);
assert_true(
    'warehouseLabelsByIds has no INSERT/backfill',
    $whLabelsFn !== ''
    && !str_contains($whLabelsFn, 'INSERT')
    && !str_contains($whLabelsFn, 'backfill')
);
assert_true(
    'warehouseLabelsByIds uses WHERE id IN',
    $whLabelsFn !== '' && str_contains($whLabelsFn, 'id IN (')
);
assert_true(
    'warehouseOptions still ensures default for forms',
    (bool) preg_match('/function warehouseOptions\([\s\S]*?ensureDefaultWarehouse[\s\S]*?\n    \}/', $lookup)
);
assert_true(
    'WarehouseService::ensureDefaultWarehouse still present for setup flows',
    str_contains($wh, 'function ensureDefaultWarehouse')
    && str_contains($wh, 'GET_LOCK')
);
assert_true(
    'crud-index uses valueLabelMapForIds (not full valueLabelMap for FK columns)',
    str_contains($crud, 'valueLabelMapForIds')
    && !preg_match('/\$fkLabelMaps\[.*?\]\s*=\s*\$lookupSvc->valueLabelMap\(/', $crud)
);
assert_true(
    'resolveFkLabel warehouses avoids full valueLabelMap ensure path',
    (bool) preg_match(
        '/function resolveFkLabel\([\s\S]*?warehouses[\s\S]*?warehouseLabelsByIds[\s\S]*?valueLabelMap\(/',
        $lookup
    ) || (bool) preg_match(
        '/function resolveFkLabel\([\s\S]*?lookup === \'warehouses\'[\s\S]*?fetchFkLabelDirect/',
        $lookup
    )
);
assert_true(
    'terminals index still uses crud-index',
    str_contains($terminalsIndex, "partial('crud-index'")
);
assert_true(
    'devices controller does not call ensureDefaultWarehouse',
    !str_contains($devicesCtrl, 'ensureDefaultWarehouse')
);
assert_true(
    'settings controller does not call ensureDefaultWarehouse',
    !str_contains($settingsCtrl, 'ensureDefaultWarehouse')
);

// Optional live DB only when explicitly requested (avoid session noise on static run).
$liveRan = false;
if ((getenv('POS_V2_INTEGRATION_SEED') === '1' || getenv('POS_V2_TEST_DB') === '1')
    && getenv('POS_TERMINALS_TTFB_LIVE') === '1') {
    require_once __DIR__ . '/pos-v2-test-bootstrap.php';
    require_once __DIR__ . '/PosV2IntegrationFixture.php';
    $fx = PosV2IntegrationFixture::loadOrNull();
    if ($fx !== null) {
        $liveRan = true;
        $fx->bootstrapRuntime();
        $svc = new Rateb\App\Services\FormLookupService();
        $map = $svc->valueLabelMapForIds('warehouses', [1, 2, 999999]);
        assert_true('live valueLabelMapForIds returns array', is_array($map));
        $whId = (new Rateb\App\Services\WarehouseService())->ensureDefaultWarehouse($fx->companyId);
        assert_true('live ensureDefaultWarehouse still works for setup', $whId > 0, 'id=' . $whId);
        // After ensure, read-only map must still work without requiring another ensure call path.
        $map2 = $svc->valueLabelMapForIds('warehouses', [$whId]);
        assert_true(
            'live read-only map resolves ensured warehouse id',
            isset($map2[(string) $whId]) && $map2[(string) $whId] !== '',
            (string) ($map2[(string) $whId] ?? '')
        );
    }
}

if (!$liveRan) {
    echo 'SKIP: live DB probe (set POS_V2_INTEGRATION_SEED=1 and POS_TERMINALS_TTFB_LIVE=1)' . PHP_EOL;
}

echo PHP_EOL . ($fail === 0 ? 'ALL PASSED' : "FAILED {$fail}") . PHP_EOL;
exit($fail > 0 ? 1 : 0);
