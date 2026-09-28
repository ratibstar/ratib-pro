<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/services/MobileAppActivationService.php';

use Rateb\App\Services\MobileAppActivationService;

$fail = 0;
$assert = static function (bool $ok, string $label) use (&$fail): void {
    if ($ok) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $fail++;
};

$assert(
    MobileAppActivationService::normalize('7EPY-PQUJ') === '7EPYPQUJ',
    'normalize dashed code'
);
$assert(
    MobileAppActivationService::normalize('https://rateb.sa/rateb-erp/public/app-activate/7EPY-PQUJ') === '7EPYPQUJ',
    'normalize activation URL'
);
$assert(
    MobileAppActivationService::normalize('https://rateb.sa/rateb-erp/public/m/activate/7EPY-PQUJ?setup=1') === '7EPYPQUJ',
    'normalize mobile QR activation URL'
);
$assert(
    MobileAppActivationService::format('7EPYPQUJ') === '7EPY-PQUJ',
    'format code'
);
$assert(MobileAppActivationService::normalize('IIII-IIII') === '', 'reject invalid alphabet');
$assert(
    MobileAppActivationService::normalize('VBAK-7P38?setup=1') === 'VBAK7P38',
    'normalize code with query string'
);

exit($fail > 0 ? 1 : 0);
