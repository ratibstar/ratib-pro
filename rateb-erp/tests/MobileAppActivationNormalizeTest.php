<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/services/MobileAppActivationService.php';
require_once dirname(__DIR__) . '/app/services/MobileAppApkService.php';
require_once dirname(__DIR__) . '/app/services/MobileAppBrandedService.php';

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
$svc = new MobileAppActivationService();
$handoff = $svc->mobileAppHandoffUrl('VBAK7P38');
$assert(
    str_contains($handoff, '/rateb-erp/public/app-activate/VBAK-7P38')
    && str_contains($handoff, 'setup=1')
    && !str_contains($handoff, 'ratebapp://'),
    'mobile handoff uses canonical app-activate HTTPS'
);
$unifiedCompany = ['id' => 49, 'settings' => '{}'];
$qr = $svc->qrActivationPayload('VBAK7P38', $unifiedCompany);
$assert(
    str_starts_with($qr, 'ratebhr://activate?code=') && str_contains($qr, 'VBAK-7P38'),
    'unified company QR uses ratebhr deep link'
);
$assert($qr !== $handoff, 'QR is not the browser handoff URL');
$intent = $svc->mobileAppIntentUrl('VBAK7P38');
$assert(
    str_contains($intent, 'package=sa.rateb.hr.mobile')
    && str_contains($intent, 'scheme=https')
    && str_contains($intent, '/m/activate/VBAK-7P38')
    && !str_contains($intent, '/app-activate/')
    && !str_contains($intent, 'ratebapp'),
    'android intent uses /m/activate deep link for installed APK (not ratebapp)'
);

exit($fail > 0 ? 1 : 0);
