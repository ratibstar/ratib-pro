<?php
declare(strict_types=1);

/**
 * Policy: company-scoped API tokens for ESS.
 * - SA without company_id binds DedicatedTenantPolicy::primaryCompanyId
 * - SA skips subscription gate (active company only)
 * - Non-SA still requires companyAccessAllowed
 *
 * Run: php rateb-erp/tests/api/run-api-token-company-sa-policy-tests.php
 */

$root = dirname(__DIR__, 2);
$apiCtrl = file_get_contents($root . '/app/controllers/Api/ApiController.php');
$tokenSvc = file_get_contents($root . '/app/services/ApiTokenService.php');
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
    str_contains($apiCtrl, 'DedicatedTenantPolicy::primaryCompanyId'),
    'createToken binds primary company for SA without company_id'
);

assertTrue(
    str_contains($apiCtrl, 'No company linked')
        && str_contains($apiCtrl, 'code\' => \'no_company\''),
    'createToken returns no_company when unresolved'
);

assertTrue(
    str_contains($apiCtrl, '!$isSa && !(new PlanLimitService())')
        || str_contains($apiCtrl, '!$isSa && !(new PlanLimitService())->companyAccessAllowed'),
    'createToken subscription gate applies only to non-SA'
);

assertTrue(
    str_contains($tokenSvc, '$companyIdOverride'),
    'ApiTokenService accepts companyIdOverride'
);

assertTrue(
    str_contains($mw, '$tokenIsSa')
        && str_contains($mw, 'companyAccessAllowed'),
    'ApiAuthMiddleware SA tokens skip subscription gate'
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
