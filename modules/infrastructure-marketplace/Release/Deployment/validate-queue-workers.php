<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use Ratib\InfrastructureMarketplace\Verification\QueueWorkerVerifier;

$pdo = DatabaseConnectionFactory::createPdo();
$result = (new QueueWorkerVerifier($pdo))->verify();
echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;

$fail = 0;
foreach ((array) ($result['checks'] ?? []) as $check) {
    if (($check['status'] ?? '') === 'FAIL') {
        $fail++;
    }
}
exit($fail > 0 ? 2 : 0);

