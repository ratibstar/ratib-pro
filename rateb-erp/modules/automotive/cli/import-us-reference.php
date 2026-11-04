<?php
declare(strict_types=1);

use Rateb\App\Automotive\Services\AutomotiveUsImportService;

$root = dirname(__DIR__, 3);
require_once $root . '/modules/automotive/app/Services/AutomotiveUsImportService.php';

if (getenv('RATEB_EPC_DISPOSABLE_DB') !== 'admin_rateb_epc_phase0' || !str_contains((string) getenv('RATEB_EPC_DSN'), 'dbname=admin_rateb_epc_phase0')) {
    fwrite(STDERR, "ABORT database\n");
    exit(1);
}
$dir = (string) ($argv[1] ?? '');
$mode = (string) ($argv[2] ?? 'dry-run');
if ($dir === '' || !is_dir($dir)) {
    fwrite(STDERR, "Usage: php import-us-reference.php <snapshot-dir> dry-run|import\n");
    exit(1);
}
$pdo = new PDO((string) getenv('RATEB_EPC_DSN'), (string) getenv('RATEB_EPC_USER'), (string) getenv('RATEB_EPC_PASS'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'admin_rateb_epc_phase0') {
    fwrite(STDERR, "ABORT database\n");
    exit(1);
}
$service = new AutomotiveUsImportService($pdo);
if ($mode === 'dry-run') {
    echo json_encode($service->dryRun($dir), JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}
if ($mode !== 'import') {
    fwrite(STDERR, "ABORT mode\n");
    exit(1);
}
$service->import($dir, '2026-11-04');
echo "imported\n";
