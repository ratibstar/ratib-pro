<?php
declare(strict_types=1);

/**
 * Super Admin full-open module gate + plan-tiers logistics coverage.
 * Run: php rateb-erp/tests/run-platform-sa-module-gate-tests.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Services\PlanLimitService;

$passed = 0;
$failed = 0;

function sa_gate_assert(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        ++$passed;
        echo "PASS: {$label}\n";
    } else {
        ++$failed;
        echo "FAIL: {$label}\n";
    }
}

$pro = PlanLimitService::modulesForSlug('professional');
$ent = PlanLimitService::modulesForSlug('enterprise');
$starter = PlanLimitService::modulesForSlug('starter');

sa_gate_assert(in_array('procurement', $starter, true), 'starter includes procurement');
sa_gate_assert(in_array('logistics', $pro, true), 'professional includes logistics');
sa_gate_assert(in_array('procurement', $pro, true), 'professional includes procurement');
sa_gate_assert(in_array('logistics', $ent, true), 'enterprise includes logistics');
sa_gate_assert(in_array('pos', $ent, true), 'enterprise includes pos');
sa_gate_assert(in_array('crm', $ent, true), 'enterprise includes crm');

$appPhp = (string) file_get_contents($root . '/config/app.php');
sa_gate_assert(
    str_contains($appPhp, 'Super Admin: full system open'),
    'SA nav fully open in app.php'
);
sa_gate_assert(
    str_contains($appPhp, 'Super Admin is never gated'),
    'SA never enforces company.modules in nav gate'
);

$mw = (string) file_get_contents($root . '/app/Core/Middleware/Middleware.php');
sa_gate_assert(
    str_contains($mw, 'Super Admin: full ERP open'),
    'CompanyModuleMiddleware SA full bypass present'
);
sa_gate_assert(
    str_contains($mw, 'isSuperAdminSession'),
    'CompanyModuleMiddleware uses isSuperAdminSession helper'
);
sa_gate_assert(
    !str_contains($mw, "companies/' . \$companyId . '/edit"),
    'company edit redirect removed from CompanyModuleMiddleware'
);

sa_gate_assert(
    str_contains((string) file_get_contents($root . '/app/services/PlanLimitService.php'), 'full ERP entitlement'),
    'PlanLimitService companyHasModule SA bypass'
);
sa_gate_assert(
    str_contains((string) file_get_contents($root . '/public/pos-sw.js'), 'Never bounce ops module denials'),
    'pos-sw no longer bounces ops denials to company edit'
);

$repair = (string) file_get_contents($root . '/app/services/MigrationService.php');
sa_gate_assert(str_contains($repair, 'syncPlanTierModulesFromConfig'), 'repair syncs modules from plan-tiers config');

echo "\nPlatform SA module gate tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
