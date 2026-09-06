<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/AgencyCommercialLock.php';

use Rateb\App\Core\AgencyCommercialLock;

$failed = 0;
function expect_lock(bool $cond, string $msg): void
{
    global $failed;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failed++;
        return;
    }
    echo "OK: {$msg}\n";
}

expect_lock(AgencyCommercialLock::isAllowListed('/rateb-erp/public/admin/logout') === true, 'logout allowed');
expect_lock(AgencyCommercialLock::isAllowListed('/rateb-erp/public/admin/subscription/renew') === true, 'renew allowed');
expect_lock(AgencyCommercialLock::isAllowListed('/rateb-erp/public/admin') === false, 'dashboard blocked');
expect_lock(AgencyCommercialLock::isAllowListed('/rateb-erp/public/admin/inventory') === false, 'inventory blocked');
expect_lock(AgencyCommercialLock::isAllowListed('/rateb-erp/public/api/v1/products') === false, 'api blocked');

if ($failed > 0) {
    echo "FAILED {$failed}\n";
    exit(1);
}
echo "AgencyCommercialLock tests passed\n";
