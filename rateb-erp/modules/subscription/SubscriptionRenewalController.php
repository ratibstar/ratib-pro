<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;

/**
 * Placeholder renewal / subscription status pages (no payment logic).
 */
final class SubscriptionRenewalController extends Controller
{
    public function renew(): void
    {
        $this->renderStatusPage('renew', 'Subscription expired');
    }

    public function invoices(): void
    {
        $this->renderStatusPage('invoices', 'Subscription invoices');
    }

    public function paymentStatus(): void
    {
        $this->renderStatusPage('payment-status', 'Payment status');
    }

    public function support(): void
    {
        $this->renderStatusPage('support', 'Support');
    }

    private function renderStatusPage(string $page, string $title): void
    {
        $companyId = (int) (TenantContext::companyId()
            ?? SessionManager::get('rateb_company_id', 0)
            ?? 0);
        if ($companyId > 0) {
            SubscriptionBootstrap::bindForCompany($companyId);
        }
        $ctx = subscription();

        $companyName = '';
        if ($companyId > 0) {
            try {
                $row = (new \Rateb\App\Services\PlanLimitService())->getCompanyRow($companyId);
                $companyName = is_array($row) ? (string) ($row['name'] ?? '') : '';
            } catch (\Throwable $e) {
                $companyName = '';
            }
        }

        $viewFile = SubscriptionModule::rootPath() . '/views/renew.php';
        if (!is_file($viewFile)) {
            $this->notFound();
            return;
        }

        $data = [
            'title' => $title,
            'page' => $page,
            'companyName' => $companyName,
            'companyId' => $companyId,
            'context' => $ctx,
            'expiryDate' => $ctx?->expirationDate(),
            'graceEndDate' => $ctx?->graceEndDate(),
            'status' => $ctx?->status() ?? 'UNKNOWN',
            'enforcementEnabled' => SubscriptionEnforcementGate::isEnabled(),
        ];

        extract($data, EXTR_SKIP);
        $pageContent = static function () use ($viewFile, $data): string {
            extract($data, EXTR_SKIP);
            ob_start();
            include $viewFile;
            return (string) ob_get_clean();
        };

        $layoutFile = RATEB_VIEWS_PATH . '/layouts/main.php';
        if (is_file($layoutFile)) {
            $pageContent = $pageContent();
            include $layoutFile;
            return;
        }

        echo $pageContent();
    }
}
