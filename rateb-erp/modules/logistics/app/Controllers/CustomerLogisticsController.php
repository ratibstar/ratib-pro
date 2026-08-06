<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\LogisticsModule;
use Rateb\App\Logistics\Services\CustomerTrackingService;
use Rateb\App\Services\CmsService;
use Rateb\App\Website\Portal\PortalAuthService;

/** Customer portal shipment tracking (/site/customer/logistics). */
final class CustomerLogisticsController extends Controller
{
    public function __construct(private CustomerTrackingService $tracking = new CustomerTrackingService())
    {
    }

    public function index(): void
    {
        $user = $this->requireCustomer();
        if ($user === null) {
            return;
        }

        $trackingNumber = trim((string) ($this->input('tracking', '') ?: $this->input('q', '')));
        $result = null;
        if ($trackingNumber !== '') {
            $companyId = (int) ($user['company_id'] ?? 0);
            $customerId = (int) ($user['erp_customer_id'] ?? 0);
            TenantContext::setCompanyId($companyId);
            $result = $this->tracking->trackByNumber(
                $companyId,
                $trackingNumber,
                $customerId > 0 ? $customerId : null
            );
        }

        $this->renderPortal([
            'user' => $user,
            'trackingNumber' => $trackingNumber,
            'result' => $result,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function requireCustomer(): ?array
    {
        $user = (new PortalAuthService())->currentUser(PortalAuthService::TYPE_CUSTOMER);
        if ($user === null) {
            Response::redirect(rateb_url('site/customer/login'));

            return null;
        }

        return $user;
    }

    /** @param array<string, mixed> $extra */
    private function renderPortal(array $extra): void
    {
        $cms = new CmsService();
        $title = __('logistics_portal_title');
        $data = array_merge([
            'title' => $title,
            'portalType' => 'customer',
            'portalSection' => 'logistics',
            'meta' => $cms->metaTags('customer-portal', $title),
            'menuItems' => $cms->menuItems(),
            'theme' => $cms->theme(),
            'analytics' => $cms->analytics(),
            'csrf' => Csrf::token(),
            'isPortalPage' => true,
        ], $extra);

        $viewFile = LogisticsModule::viewsPath() . '/portal/track.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Logistics portal view missing';

            return;
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $viewFile;
            $pageContent = (string) ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            throw $e;
        }
        include RATEB_VIEWS_PATH . '/layouts/marketing-portals.php';
    }
}
