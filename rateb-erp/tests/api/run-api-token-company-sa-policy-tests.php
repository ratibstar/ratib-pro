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
$mobileSvc = file_get_contents($root . '/app/services/MobileAppConfigService.php');
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
    str_contains($planSvc, 'function essFallbackCompanyId'),
    'PlanLimitService exposes ESS fallback company'
);

assertTrue(
    str_contains($planSvc, 'return $this->essFallbackCompanyId()'),
    'resolveEssApiCompanyId falls back for any user'
);

assertTrue(
    !preg_match('/Company access denied/', $apiCtrl),
    'createToken no longer emits Company access denied'
);

assertTrue(
    str_contains($mobileSvc, "'mobile_active' => true"),
    'Mobile config defaults active when tenant config missing'
);

assertTrue(
    str_contains($mw, 'apiBearerCompanyAllowed($companyId)'),
    'ApiAuthMiddleware uses ESS company helper'
);

if ($failed > 0) {
    echo "\nGATE FAIL ($failed)\n";
    exit(1);
}
echo "\nGATE CLEAR\n";
exit(0);
