<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Marketing;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Payment\PaymentService;
use Rateb\App\Website\Portal\PortalAuthService;
use Rateb\App\Website\Portal\PortalFinanceService;
use Rateb\App\Website\WebsiteContext;

/** Customer invoice online payment — delegates to PaymentService only. */
final class PortalInvoicePaymentController extends Controller
{
    public function pay(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite() || $type !== 'customer') {
            $this->notFound();
            return;
        }
        $user = $this->requireUser($type);
        if ($user === null) {
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::redirect(rateb_url('site/customer/finance'));
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', function_exists('__') ? __('invalid_request') : 'Invalid request');
            Response::redirect(rateb_url('site/customer/finance'));
            return;
        }

        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
        $finance = new PortalFinanceService();
        $invoice = $finance->findInvoice($invoiceId, $user);
        if ($invoice === null) {
            SessionManager::flash('error', function_exists('__') ? __('invoice_not_found') : 'Invoice not found');
            Response::redirect(rateb_url('site/customer/finance'));
            return;
        }

        $companyId = (int) ($invoice['company_id'] ?? 0);
        $service = new PaymentService();
        if (!$service->isGatewayEnabled($companyId)) {
            SessionManager::flash('error', function_exists('__') ? __('payment_gateway_disabled') : 'Online payment is not available');
            Response::redirect(rateb_url('site/customer/finance'));
            return;
        }

        $result = $service->initiate($invoiceId, 'moyasar', $user, $companyId);
        if (!($result['ok'] ?? false) || empty($result['redirect_url'])) {
            SessionManager::flash('error', (string) ($result['error'] ?? 'Payment initiation failed'));
            Response::redirect(rateb_url('site/customer/finance'));
            return;
        }

        Response::redirect((string) $result['redirect_url']);
    }

    public function callback(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite()) {
            return;
        }
        $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
        if ($token === '') {
            SessionManager::flash('error', function_exists('__') ? __('invalid_request') : 'Invalid request');
            Response::redirect(rateb_url('site/' . ($type ?: 'customer') . '/finance'));
            return;
        }

        $result = (new PaymentService())->confirmByCallbackToken($token);
        if ($result['ok'] ?? false) {
            $invoiceId = (int) ($result['invoice_id'] ?? 0);
            Response::redirect(rateb_url('site/customer/finance/payment/success?invoice_id=' . $invoiceId));
            return;
        }

        SessionManager::flash('error', (string) ($result['error'] ?? 'Payment verification failed'));
        Response::redirect(rateb_url('site/customer/finance'));
    }

    public function success(string $type = ''): void
    {
        $type = $this->resolvePortalType($type);
        if (!$this->ensureWebsite()) {
            return;
        }
        $user = $this->requireUser($type ?: 'customer');
        if ($user === null) {
            return;
        }
        $invoiceId = (int) ($_GET['invoice_id'] ?? 0);
        $invoice = (new PortalFinanceService())->findInvoice($invoiceId, $user);

        $this->view('marketing/portals/payment-success', [
            'title' => function_exists('__') ? __('payment_success') : 'Payment Success',
            'portalType' => $type ?: 'customer',
            'portalSection' => 'payment-success',
            'user' => $user,
            'invoice' => $invoice,
            'csrf' => Csrf::token(),
            'isPortalPage' => true,
        ], 'marketing-portals');
    }

    private function ensureWebsite(): bool
    {
        if (!class_exists(WebsiteContext::class)) {
            $this->notFound();
            return false;
        }
        if (WebsiteContext::current() === null) {
            WebsiteContext::bootFromRequest();
        }

        return true;
    }

    private function resolvePortalType(string $type = ''): string
    {
        if (PortalAuthService::isValidType($type)) {
            return $type;
        }
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        if (preg_match('#/(employer|customer|partner)(/|$)#', $path, $m)) {
            return $m[1];
        }

        return 'customer';
    }

    private function requireUser(string $type): ?array
    {
        $user = (new PortalAuthService())->currentUser($type);
        if ($user === null) {
            Response::redirect(rateb_url('site/' . $type . '/login'));

            return null;
        }

        return $user;
    }
}
