<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Payment\PaymentConfigService;
use Rateb\App\Payment\PaymentService;
use Rateb\App\Payment\PaymentTransactionRepository;

final class PaymentGatewaysController extends Controller
{
    private PaymentConfigService $config;
    private PaymentTransactionRepository $transactions;

    public function __construct()
    {
        $this->config = new PaymentConfigService();
        $this->transactions = new PaymentTransactionRepository();
    }

    public function index(): void
    {
        $this->guardBilling();
        $this->view('admin/payment-gateways/index', [
            'title' => function_exists('__') ? __('payment_gateways') : 'Payment Gateways',
            'accountingActive' => 'admin',
            'settings' => $this->config->publicSettings(null),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function transactions(): void
    {
        $this->guardBilling();
        $status = trim((string) ($_GET['status'] ?? ''));
        $this->view('admin/payment-gateways/transactions', [
            'title' => function_exists('__') ? __('payment_transactions') : 'Payment Transactions',
            'accountingActive' => 'admin',
            'transactions' => $this->transactions->listRecent(null, $status !== '' ? $status : null),
            'filterStatus' => $status,
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function failed(): void
    {
        $this->guardBilling();
        $this->view('admin/payment-gateways/failed', [
            'title' => function_exists('__') ? __('failed_payments') : 'Failed Payments',
            'accountingActive' => 'admin',
            'transactions' => $this->transactions->listRecent(null, 'failed'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function save(): void
    {
        $this->guardBilling();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url('admin/payment-gateways'));
        }
        $this->config->saveSettings(null, [
            'enabled' => !empty($_POST['enabled']),
            'mode' => (string) ($_POST['mode'] ?? 'sandbox'),
            'publishable_key' => trim((string) ($_POST['publishable_key'] ?? '')),
            'secret_key' => trim((string) ($_POST['secret_key'] ?? '')),
            'webhook_secret' => trim((string) ($_POST['webhook_secret'] ?? '')),
            'callback_url' => trim((string) ($_POST['callback_url'] ?? '')),
            'webhook_url' => trim((string) ($_POST['webhook_url'] ?? '')),
        ]);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url('admin/payment-gateways'));
    }

    public function healthCheck(): void
    {
        $this->guardBilling();
        header('Content-Type: application/json; charset=UTF-8');
        if (!$this->validateCsrf()) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF']);
            return;
        }
        $result = (new PaymentService($this->config))->healthCheck('moyasar', null);
        echo json_encode(['success' => (bool) ($result['ok'] ?? false), 'data' => $result], JSON_UNESCAPED_UNICODE);
    }

    public function refund(): void
    {
        $this->guardBilling();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url('admin/payment-gateways/transactions'));
        }
        $txId = (int) ($_POST['transaction_id'] ?? 0);
        $amount = isset($_POST['amount']) && $_POST['amount'] !== '' ? (float) $_POST['amount'] : null;
        $result = (new PaymentService($this->config))->refund($txId, $amount);
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false)
            ? (__('refund_ok') ?: 'Refund processed')
            : ((string) ($result['error'] ?? 'Refund failed')));
        $this->redirect(rateb_url('admin/payment-gateways/transactions'));
    }

    public function retry(): void
    {
        $this->guardBilling();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url('admin/payment-gateways/failed'));
        }
        $txId = (int) ($_POST['transaction_id'] ?? 0);
        $tx = $this->transactions->findById($txId);
        if ($tx === null) {
            SessionManager::flash('error', 'Transaction not found');
            $this->redirect(rateb_url('admin/payment-gateways/failed'));
        }
        $externalId = (string) ($tx['external_id'] ?? '');
        if ($externalId === '') {
            SessionManager::flash('error', 'Missing external reference');
            $this->redirect(rateb_url('admin/payment-gateways/failed'));
        }
        $result = (new PaymentService($this->config))->confirmPayment(
            (string) ($tx['gateway_slug'] ?? 'moyasar'),
            $externalId,
            'admin_retry',
        );
        SessionManager::flash(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false)
            ? 'Payment confirmed'
            : ((string) ($result['error'] ?? 'Retry failed')));
        $this->redirect(rateb_url('admin/payment-gateways/failed'));
    }

    private function guardBilling(): void
    {
        if (!function_exists('rateb_can') || !rateb_can('billing.manage')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url('admin/invoices'));
        }
    }
}
