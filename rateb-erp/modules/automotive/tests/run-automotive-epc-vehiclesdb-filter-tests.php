<?php
declare(strict_types=1);

/**
 * VehiclesDB eligibility checks. No database writes.
 * Run: php rateb-erp/modules/automotive/tests/run-automotive-epc-vehiclesdb-filter-tests.php
 */

require_once dirname(__DIR__) . '/app/Services/AutomotiveVehiclesDbFilter.php';
require_once dirname(__DIR__) . '/app/Services/AutomotiveOpenSourcePolicy.php';

use Rateb\App\Automotive\Services\AutomotiveOpenSourcePolicy;
use Rateb\App\Automotive\Services\AutomotiveVehiclesDbFilter;

$passed = 0;
$failed = 0;
function vdb(bool $ok, string $label): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

$licenses = [
    'nl_rdw' => 'CC0-1.0',
    'nz_nzta' => 'CC-BY-4.0',
    'il_mot' => 'facts-credit-data.gov.il-terms-of-use',
    'es_dgt' => 'Ley-37/2007',
    'th_dlt' => 'TH-OpenDataCommon',
    'de_kba_fz10' => 'DL-DE-BY-2.0',
];

$clear = [
    'id' => 'ford/transit',
    'kind' => 'van',
    'make_id' => 'ford',
    'slug' => 'transit',
    'sources' => ['nl_rdw'],
    'availability' => [['country' => 'nl', 'source' => 'nl_rdw', 'evidence' => 'registration']],
];
$ok = AutomotiveVehiclesDbFilter::classify($clear, $licenses);
vdb($ok['eligible'] === true && $ok['licenses'] === ['CC0-1.0'] && $ok['markets'] === ['nl'], 'a source with a known open license is eligible');
vdb(AutomotiveVehiclesDbFilter::recordKey($clear) === 'van:ford/transit', 'identity includes the vehicle type');
vdb(AutomotiveVehiclesDbFilter::recordKey(['id' => 'ford/transit', 'kind' => 'car']) !== AutomotiveVehiclesDbFilter::recordKey($clear), 'the same make and model in another type stay separate');

$mixed = $clear;
$mixed['sources'] = ['nl_rdw', 'il_mot'];
$mixed['availability'][] = ['country' => 'il', 'source' => 'il_mot', 'evidence' => 'registration'];
$mixedResult = AutomotiveVehiclesDbFilter::classify($mixed, $licenses);
vdb($mixedResult['eligible'] === false && $mixedResult['markets'] === [], 'a record that also cites Israel is excluded entirely');

$spain = $clear;
$spain['sources'] = ['es_dgt'];
$spain['availability'] = [['country' => 'es', 'source' => 'es_dgt']];
vdb(AutomotiveVehiclesDbFilter::classify($spain, $licenses)['reason'] === 'redistribution_unresolved', 'Spain is excluded');
$thai = $clear;
$thai['sources'] = ['th_dlt'];
$thai['availability'] = [['country' => 'th', 'source' => 'th_dlt']];
vdb(AutomotiveVehiclesDbFilter::classify($thai, $licenses)['reason'] === 'redistribution_unresolved', 'Thailand is excluded');

$bare = $clear;
unset($bare['sources']);
vdb(AutomotiveVehiclesDbFilter::classify($bare, $licenses)['reason'] === 'provenance_missing', 'missing provenance is excluded');
$unknown = $clear;
$unknown['sources'] = ['mystery'];
$unknown['availability'] = [['country' => 'zz', 'source' => 'mystery']];
vdb(AutomotiveVehiclesDbFilter::classify($unknown, $licenses)['reason'] === 'license_unknown', 'an unknown source is not treated as CC BY');

$germany = $clear;
$germany['sources'] = ['de_kba_fz10'];
$germany['availability'] = [['country' => 'de', 'source' => 'de_kba_fz10']];
vdb(AutomotiveVehiclesDbFilter::classify($germany, $licenses)['licenses'] === ['DL-DE-BY-2.0'], 'Germany keeps its own license reference');

vdb(str_contains(AutomotiveOpenSourcePolicy::VEHICLESDB_ATTRIBUTION, 'VehiclesDB'), 'VehiclesDB attribution text is retained');

$keys = [];
$conflicts = 0;
foreach ([$clear, $clear, ['id' => 'ford/transit', 'kind' => 'car', 'sources' => ['nz_nzta'], 'availability' => [['country' => 'nz', 'source' => 'nz_nzta']]]] as $row) {
    $key = AutomotiveVehiclesDbFilter::recordKey($row);
    if (isset($keys[$key])) {
        $conflicts++;
        continue;
    }
    $keys[$key] = true;
}
vdb(count($keys) === 2 && $conflicts === 1, 'duplicate composite keys are counted and not merged');

echo "VehiclesDB filter checks: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
