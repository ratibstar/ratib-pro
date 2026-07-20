<?php
declare(strict_types=1);

/**
 * Policy: platform SA (no company) cannot use API tokens;
 * company-scoped users (even if is_super_admin=1) can.
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
    str_contains($apiCtrl, 'is_super_admin') && str_contains($apiCtrl, '$companyId < 1'),
    'createToken blocks SA only when company_id < 1'
);

assertTrue(
    preg_match(
        '/is_super_admin.*?=== 1\s*&&\s*\$companyId < 1/s',
        $apiCtrl
    ) === 1,
    'createToken uses SA && companyId < 1 gate'
);

assertTrue(
    !preg_match(
        '/if\s*\(\(int\)\s*\(\$user\[\'is_super_admin\'\].*?===\s*1\)\s*\{\s*Response::json\(\[\'success\'\s*=>\s*false,\s*\'message\'\s*=>\s*\'Super admin API tokens disabled\'/s',
        $apiCtrl
    ),
    'createToken no longer blocks all SA unconditionally'
);

assertTrue(
    str_contains($tokenSvc, "is_super_admin") && str_contains($tokenSvc, "company_id"),
    'validateToken considers company_id with SA check'
);

assertTrue(
    preg_match(
        '/is_super_admin.*?=== 1\s*&&\s*\(int\)\s*\(\$token\[\'company_id\'\].*?< 1/s',
        $tokenSvc
    ) === 1,
    'validateToken rejects SA only without company_id'
);

if ($failed > 0) {
    echo "\nGATE FAIL ($failed)\n";
    exit(1);
}
echo "\nGATE CLEAR\n";
exit(0);
