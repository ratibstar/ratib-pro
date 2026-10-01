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
$noBrandedCompany = ['id' => 49, 'settings' => '{}'];
$qr = $svc->qrActivationPayload('VBAK7P38', $noBrandedCompany);
$assert(
    str_contains($qr, '/m/activate/VBAK-7P38')
    && str_contains($qr, 'setup=1')
    && !str_contains($qr, 'ratebhr://')
    && !str_contains($qr, '/rateb-erp/public/app-activate/'),
    'company without branded key still uses /m/activate until dedicated slot exists'
);
$brandedCompany = [
    'id' => 49,
    'settings' => json_encode(['mobile_branded' => ['hr' => 'hr-49-abcdef0123']], JSON_THROW_ON_ERROR),
];
$qrBranded = $svc->qrActivationPayload('VBAK7P38', $brandedCompany);
$assert(
    str_contains($qrBranded, 'ratebhr://activate?code=VBAK-7P38')
    && !str_contains($qrBranded, '/m/activate/')
    && !str_contains($qrBranded, '/app-activate/'),
    'dedicated company QR opens the app directly (ratebhr scheme)'
);
$intent = $svc->mobileAppIntentUrl('VBAK7P38');
$assert(
    str_contains($intent, 'package=sa.rateb.hr.mobile')
    && str_contains($intent, 'scheme=https')
    && str_contains($intent, '/m/activate/VBAK-7P38')
    && !str_contains($intent, '/app-activate/')
    && !str_contains($intent, 'ratebapp'),
    'android intent uses /m/activate deep link for installed APK (not ratebapp)'
);
$handoffIntent = $svc->mobileAppHandoffIntentUrl('VBAK7P38');
$assert(
    str_contains($handoffIntent, 'package=sa.rateb.hr.mobile')
    && str_contains($handoffIntent, 'scheme=ratebhr')
    && str_contains($handoffIntent, 'code=VBAK-7P38')
    && !str_contains($handoffIntent, 'ratebapp'),
    'android handoff green button uses ratebhr://activate?code='
);

exit($fail > 0 ? 1 : 0);
