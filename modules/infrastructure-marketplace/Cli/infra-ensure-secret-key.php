<?php
declare(strict_types=1);

/**
 * Provision RATEB_INFRA_SECRET_KEY on server (config/ or storage/).
 * HTTP: GET https://rateb.sa/modules/infrastructure-marketplace/Cli/infra-ensure-secret-key.php
 */
require_once dirname(__DIR__) . '/bootstrap.php';

use RATEB\InfrastructureMarketplace\Infrastructure\InfraEnvBootstrap;

$result = InfraEnvBootstrap::ensureSecretKeyProvisioned();
$exit = !empty($result['ok']) ? 0 : 1;

echo json_encode(array_merge(['ok' => !empty($result['ok'])], $result), JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($exit);
