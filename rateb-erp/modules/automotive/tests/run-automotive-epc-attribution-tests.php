<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Services/AutomotiveUsCatalogService.php';

use Rateb\App\Automotive\Services\AutomotiveUsCatalogService;

$passed = 0;
$failed = 0;
function attr(bool $ok, string $label): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

$import = (string) file_get_contents(dirname(__DIR__) . '/app/Services/AutomotiveUsImportService.php');
preg_match("/release\\('epa', 'vehicles'.*?\\n/", $import, $match);
$new = $match[0] ?? '';
attr($new !== '' && !str_contains($new, 'Public domain'), 'new EPA import attribution omits the public-domain claim');
attr(str_contains($new, 'FuelEconomy.gov') && str_contains($new, 'https://fueleconomy.gov/feg/download.shtml'), 'new attribution keeps the source name and URL');

$legacy = 'US DOE/EPA FuelEconomy.gov vehicles.csv. Public domain US government data. MPG is miles per gallon. https://fueleconomy.gov/feg/download.shtml';
$shown = AutomotiveUsCatalogService::attributionText($legacy);
attr(!str_contains($shown, 'Public domain'), 'legacy attribution does not display the public-domain claim');
attr(str_contains($shown, 'FuelEconomy.gov') && str_contains($shown, 'https://fueleconomy.gov/feg/download.shtml'), 'legacy attribution keeps the source name and URL');
attr($legacy !== $shown, 'the filter changes the displayed text without requiring a database update');

$fresh = 'US DOE/EPA FuelEconomy.gov vehicles.csv. https://fueleconomy.gov/feg/download.shtml';
attr(AutomotiveUsCatalogService::attributionText($fresh) === $fresh, 'new attribution is shown unchanged');

$view = (string) file_get_contents(dirname(__DIR__) . '/views/us-catalog/show.php');
attr(str_contains($view, 'attributionText'), 'the detail view filters attribution before display');

echo "passed=$passed failed=$failed\n";
exit($failed > 0 ? 1 : 0);
