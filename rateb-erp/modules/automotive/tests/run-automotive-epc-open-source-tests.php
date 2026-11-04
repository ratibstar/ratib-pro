<?php
declare(strict_types=1);

/**
 * Open-source policy checks. No database and no catalog import.
 * Run: php rateb-erp/modules/automotive/tests/run-automotive-epc-open-source-tests.php
 */

require_once dirname(__DIR__) . '/app/Services/AutomotiveOpenSourcePolicy.php';

use Rateb\App\Automotive\Services\AutomotiveOpenSourcePolicy;

$passed = 0;
$failed = 0;
function open_assert(bool $ok, string $label): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

function open_throws(callable $fn, string $code): bool
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return $e->getMessage() === $code;
    }
    return false;
}

$attribution = "Vehicle data by VehiclesDB\nhttps://vehiclesdb.com\n";
$clear = [
    'version' => '2026.10.4',
    'sources' => [
        ['id' => 'nz_nzta', 'license' => 'CC-BY-4.0', 'name' => 'New Zealand Motor Vehicle Register'],
    ],
];
$accepted = false;
try {
    AutomotiveOpenSourcePolicy::assertStaged($clear, $attribution);
    $accepted = true;
} catch (InvalidArgumentException) {
    $accepted = false;
}
open_assert($accepted, 'a staged source with attribution and version is accepted');
open_assert(open_throws(static fn () => AutomotiveOpenSourcePolicy::assertStaged(['sources' => $clear['sources']], $attribution), 'source_version_missing'), 'missing source version is rejected');
open_assert(open_throws(static fn () => AutomotiveOpenSourcePolicy::assertStaged($clear, 'no credit'), 'attribution_missing'), 'missing VehiclesDB attribution is rejected');
open_assert(open_throws(static fn () => AutomotiveOpenSourcePolicy::assertStaged(['version' => '2026.10.4', 'sources' => []], $attribution), 'provenance_missing'), 'missing provenance is rejected');
$duplicate = $clear;
$duplicate['sources'][] = ['id' => 'nz_nzta', 'license' => 'CC-BY-4.0', 'name' => 'Another name'];
open_assert(open_throws(static fn () => AutomotiveOpenSourcePolicy::assertStaged($duplicate, $attribution), 'duplicate_source'), 'duplicate source ids are rejected');
$conflict = $clear;
$conflict['sources'][] = ['id' => 'au_bitre', 'license' => 'CC-BY-3.0-AU', 'name' => 'New Zealand Motor Vehicle Register'];
open_assert(open_throws(static fn () => AutomotiveOpenSourcePolicy::assertStaged($conflict, $attribution), 'conflicting_source'), 'conflicting source names are rejected');
$blocked = $clear;
$blocked['sources'][] = ['id' => 'il_mot', 'license' => 'facts-credit-data.gov.il-terms-of-use', 'name' => 'Israel Ministry of Transport'];
open_assert(open_throws(static fn () => AutomotiveOpenSourcePolicy::assertStaged($blocked, $attribution), 'redistribution_unresolved'), 'Israel upstream blocks import');
$spain = $clear;
$spain['sources'][] = ['id' => 'es_dgt', 'license' => 'Ley-37/2007', 'name' => 'Spanish DGT'];
open_assert(open_throws(static fn () => AutomotiveOpenSourcePolicy::assertStaged($spain, $attribution), 'redistribution_unresolved'), 'Spanish upstream blocks import');
$thai = $clear;
$thai['sources'][] = ['id' => 'th_dlt', 'license' => 'TH-OpenDataCommon', 'name' => 'Thai DLT'];
open_assert(open_throws(static fn () => AutomotiveOpenSourcePolicy::assertStaged($thai, $attribution), 'redistribution_unresolved'), 'Thai upstream blocks import');
open_assert(!in_array('us_fueleconomy', AutomotiveOpenSourcePolicy::BLOCKED_UPSTREAM, true), 'US public-domain fuel-economy source is not in the blocked list');

echo "Open-source checks: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
