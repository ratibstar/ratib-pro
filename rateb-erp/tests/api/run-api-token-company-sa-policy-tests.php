<?php
declare(strict_types=1);

/**
 * Policy: any active company-scoped user may mint/use API tokens.
 * Platform-only accounts (company_id < 1) cannot — including SA.
 *
 * Run: php rateb-erp/tests/api/run-api-token-company-sa-policy-tests.php
 */

$root = dirname(__DIR__, 2);
$apiCtrl = file_get_contents($root . '/app/controllers/Api/ApiController.php');
$tokenSvc = file_get_contents($root . '/app/services/ApiTokenService.php');

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
    !preg_match(
        '/if\s*\(\(int\)\s*\(\$user\[\'is_super_admin\'\].*?===\s*1\)/s',
        $apiCtrl
    ),
    'createToken does not gate on is_super_admin'
);

assertTrue(
    str_contains($apiCtrl, '$companyId < 1')
        && str_contains($apiCtrl, 'Company access denied'),
    'createToken requires company_id > 0'
);

assertTrue(
    !preg_match(
        '/Super admin API tokens disabled|Platform super-admin API tokens disabled|platform_sa_token_disabled/s',
        $apiCtrl
    ),
    'createToken has no SA-specific deny messages'
);

assertTrue(
    !preg_match(
        '/is_super_admin.*?=== 1/s',
        $tokenSvc
    ),
    'validateToken does not reject based on is_super_admin'
);

assertTrue(
    str_contains($tokenSvc, "(int) (\$token['company_id'] ?? 0) < 1"),
    'validateToken rejects tokens without company_id'
);

if ($failed > 0) {
    echo "\nGATE FAIL ($failed)\n";
    exit(1);
}
echo "\nGATE CLEAR\n";
exit(0);
