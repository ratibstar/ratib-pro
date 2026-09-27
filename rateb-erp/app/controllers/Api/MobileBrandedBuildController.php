<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Services\MobileAppApkService;
use Rateb\App\Services\MobileAppBrandedService;

/**
 * Build specs for GitHub Actions (scripts/ci-build-branded-from-api.ps1).
 * Auth: header X-Rateb-Build-Secret = RATEB_MOBILE_BUILD_SECRET.
 */
final class MobileBrandedBuildController extends Controller
{
    public function specs(): void
    {
        header('Cache-Control: no-store');
        $secret = rateb_mobile_build_secret();
        $given = trim((string) ($_SERVER['HTTP_X_RATEB_BUILD_SECRET'] ?? ''));
        if ($secret === '' || $given === '' || !hash_equals($secret, $given)) {
            Response::json(['success' => false, 'code' => 'unauthorized'], 401);
            return;
        }
        $app = (string) ($_GET['app'] ?? 'all');
        $mode = (string) ($_GET['mode'] ?? 'actionable');
        $actionable = $mode !== 'all';
        $filter = $app === 'all' ? '' : MobileAppApkService::normalizeApp($app);
        $items = (new MobileAppBrandedService())->listBuildQueue($filter === '' ? null : $filter, $actionable);
        $specs = [];
        foreach ($items as $row) {
            $spec = $row['spec'];
            if (!is_array($spec) || (string) ($spec['key'] ?? '') === '') {
                continue;
            }
            $specs[] = $spec + [
                'company_id' => $row['company_id'],
                'company_name' => $row['company_name'],
                'state' => $row['state'],
            ];
        }
        Response::json([
            'success' => true,
            'platform_versions' => (new MobileAppBrandedService())->sharedVersionCodes(),
            'specs' => $specs,
        ]);
    }
}
