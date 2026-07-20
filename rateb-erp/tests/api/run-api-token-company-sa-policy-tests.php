<?php
declare(strict_types=1);

/**
 * Policy: ESS/mobile API bearer skips SaaS subscription gate.
 *
 * Run: php rateb-erp/tests/api/run-api-token-company-sa-policy-tests.php
 */

$root = dirname(__DIR__, 2);
$apiCtrl = file_get_contents($root . '/app/controllers/Api/ApiController.php');
$planSvc = file_get_contents($root . '/app/services/PlanLimitService.php');
$mw = file_get_contents($root . '/app/Core/Middleware/Middleware.php');

$failed = 0;
function assertTrue(bool $cond, string $msg): void
{
    global $failed;
    if ($cond) {
        echo "PASS  $msg\n";
        return;
    }
    echo "FAIL  $msg\n";
    $failed++;
}

assertTrue(
    str_contains($planSvc, 'function apiBearerCompanyAllowed')
        && str_contains($planSvc, 'function resolveEssApiCompanyId'),
    'PlanLimitService exposes ESS API company helpers'
);

assertTrue(
    str_contains($apiCtrl, 'resolveEssApiCompanyId')
        && str_contains($apiCtrl, 'apiBearerCompanyAllowed'),
    'createToken uses ESS company helpers'
);

assertTrue(
    !preg_match('/companyAccessAllowed\(\$companyId\)/', $apiCtrl),
    'createToken does not enforce SaaS subscription gate'
);

assertTrue(
    str_contains($mw, 'apiBearerCompanyAllowed($companyId)'),
    'ApiAuthMiddleware uses ESS company helper'
);

assertTrue(
    !preg_match(
        '/Super admin API tokens disabled|Platform super-admin API tokens disabled/s',
        $apiCtrl
    ),
    'createToken has no blanket SA deny message'
);

if ($failed > 0) {
    echo "\nGATE FAIL ($failed)\n";
    exit(1);
}
echo "\nGATE CLEAR\n";
exit(0);
