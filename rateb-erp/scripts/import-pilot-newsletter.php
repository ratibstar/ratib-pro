<?php
declare(strict_types=1);

/**
 * Pilot list import — maps pilot_companies_email_list.csv (sector, company_ar,
 * company_en, email…) into the existing newsletter importer format (email,name,segment),
 * seeds the sector segments, and routes the rows through
 * CmsNewsletterCampaignService::importCsv (dedup + validation + unsub exclusion).
 *
 * Usage: php scripts/import-pilot-newsletter.php <pilot.csv>
 */

define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/app/services/CmsNewsletterCampaignService.php';
require_once RATEB_ROOT . '/app/services/Logger.php';

use Rateb\App\Core\Database;
use Rateb\App\Services\CmsNewsletterCampaignService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$src = $argv[1] ?? '';
if ($src === '' || !is_file($src)) {
    fwrite(STDERR, "Usage: php scripts/import-pilot-newsletter.php <source.csv>\n");
    exit(1);
}

$handle = fopen($src, 'r');
if ($handle === false) {
    fwrite(STDERR, "Cannot open " . $src . "\n");
    exit(1);
}

$header = null;
$rows = [];
while (($row = fgetcsv($handle)) !== false) {
    if ($header === null) {
        $header = array_map('trim', $row ?: []);
        continue;
    }
    if ($row === null) {
        continue;
    }
    $mapped = [];
    foreach ($header as $i => $key) {
        $mapped[$key] = (string) ($row[$i] ?? '');
    }
    $rows[] = $mapped;
}
fclose($handle);

/** @var list<string> */
$lines = [];
$segments = [];
foreach ($rows as $row) {
    $email = strtolower(trim((string) ($row['email'] ?? '')));
    $name = trim((string) ($row['company_en'] ?? $row['company_ar'] ?? ''));
    $segment = trim((string) ($row['sector'] ?? '')) ?: 'general';
    if ($segment !== 'general' && $segment !== '') {
        $segments[$segment] = true;
    }
    $tmp = fopen('php://temp', 'w');
    fputcsv($tmp, [$email, $name, $segment]);
    rewind($tmp);
    $line = (string) stream_get_contents($tmp);
    fclose($tmp);
    $lines[] = trim($line);
}

$segmentCount = count($segments);
if ($segmentCount > 0) {
    // Keep the campaign segment selector in sync with the imported buckets.
    $db = Database::connection();
    $stmt = $db->prepare(
        'INSERT IGNORE INTO rateb_cms_newsletter_segments (slug, name_en, name_ar, description_en, description_ar)
         VALUES (:slug, :name_en, :name_ar, NULL, NULL)'
    );
    foreach (array_keys($segments) as $slug) {
        $stmt->execute(['slug' => $slug, 'name_en' => $slug, 'name_ar' => $slug]);
    }
}

foreach ($lines as $i => $line) {
    if (mb_ord($line[0] ?? '') === 0xFEFF) {
        $lines[$i] = mb_substr($line, 1);
    }
}

$csvContent = "email,name,segment\n" . implode("\n", $lines) . "\n";
$outPath = dirname($src) . DIRECTORY_SEPARATOR . 'newsletter-pilot-import.csv';
file_put_contents($outPath, $csvContent);

$result = (new CmsNewsletterCampaignService())->importCsv($csvContent);

echo 'input_rows=' . count($rows)
    . ' imported=' . $result['imported']
    . ' skipped=' . $result['skipped']
    . ' segments=' . $segmentCount
    . ' prepared=' . $outPath . PHP_EOL;