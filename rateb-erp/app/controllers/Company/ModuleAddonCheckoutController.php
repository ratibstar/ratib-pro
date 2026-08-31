<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\ModuleAddonCheckoutService;

/**
 * Self-serve module add-on checkout. Company ID comes only from the session.
 * Does not activate modules (Phase 3).
 */
final class ModuleAddonCheckoutController extends Controller
{
    public function show(string $slug = ''): void
    {
        $ctx = $this->gate($slug);
        if ($ctx === null) {
            return;
        }
        [$companyId, $slug, $checkout] = $ctx;

        if ($checkout->companyAlreadyHasModule($companyId, $slug)) {
            $this->redirect(rateb_url('admin/billing/modules/' . rawurlencode($slug) . '/status'));
            return;
        }

        $cycles = $checkout->availableCycles($slug);
        if ($cycles === []) {
            $this->notFound();
            return;
        }
        $cycle = $cycles[0];
        $quote = $checkout->quote($slug, $cycle);
        if ($quote === null) {
            $this->notFound();
            return;
        }
        $quotes = [];
        foreach ($cycles as $c) {
            $q = $checkout->quote($slug, $c);
            if ($q !== null) {
                $quotes[$c] = $q;
            }
        }
        $item = $checkout->addons()->catalog()[$slug] ?? ['name' => $slug];

        $this->view('billing/module-checkout', [
            'title' => (string) ($item['name'] ?? $slug),
            'slug' => $slug,
            'moduleName' => (string) ($item['name'] ?? $slug),
            'cycles' => $cycles,
            'quotes' => $quotes,
            'quote' => $quote,
            'csrf' => Csrf::token(),
            'action' => rateb_url('admin/billing/modules/' . rawurlencode($slug)),
            'statusUrl' => rateb_url('admin/billing/modules/' . rawurlencode($slug) . '/status'),
        ], 'main');
    }

    public function purchase(string $slug = ''): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', function_exists('__') ? __('invalid_request') : 'Invalid request');
            $this->redirect(rateb_url('admin/billing/modules/' . rawurlencode(strtolower(trim($slug)))));
            return;
        }

        $ctx = $this->gate($slug);
        if ($ctx === null) {
            return;
        }
        [$companyId, $slug, $checkout] = $ctx;

        $posted = is_array($_POST) ? $_POST : [];
        unset($posted['company_id'], $posted['price'], $posted['amount'], $posted['tax'], $posted['tax_rate'], $posted['total'], $posted['currency']);

        $result = $checkout->startCheckout($companyId, $slug, $posted);
        $code = (string) ($result['code'] ?? '');
        if ($code === 'already_enabled' || $code === 'paid_pending_activation') {
            $this->redirect(rateb_url('admin/billing/modules/' . rawurlencode($slug) . '/status'));
            return;
        }
        if (($result['ok'] ?? false) && !empty($result['redirect_url'])) {
            $this->redirect((string) $result['redirect_url']);
            return;
        }

        SessionManager::flash('error', $this->errorMessage($code));
        $this->redirect(rateb_url('admin/billing/modules/' . rawurlencode($slug)));
    }

    public function status(string $slug = ''): void
    {
        $ctx = $this->gate($slug, false);
        if ($ctx === null) {
            return;
        }
        [$companyId, $slug, $checkout] = $ctx;

        $payload = $checkout->statusPayload($companyId, $slug);
        if (!($payload['ok'] ?? false) && ($payload['code'] ?? '') === 'unknown_module') {
            $this->notFound();
            return;
        }

        $this->view('billing/module-status', [
            'title' => (string) (($payload['module']['name'] ?? $slug)),
            'slug' => $slug,
            'moduleName' => (string) ($payload['module']['name'] ?? $slug),
            'state' => (string) ($payload['state'] ?? 'unavailable'),
            'cycle' => (string) ($payload['cycle'] ?? ''),
            'invoice' => $payload['invoice'] ?? null,
            'paymentStatus' => (string) ($payload['payment_status'] ?? ''),
            'checkoutUrl' => rateb_url('admin/billing/modules/' . rawurlencode($slug)),
        ], 'main');
    }

    /**
     * @return array{0:int,1:string,2:ModuleAddonCheckoutService}|null
     */
    private function gate(string $slug, bool $requirePurchasable = true): ?array
    {
        $checkout = new ModuleAddonCheckoutService();
        if (!$checkout->isEnabled()) {
            $this->notFound();
            return null;
        }
        $companyId = (int) SessionManager::get('rateb_company_id');
        if ($companyId < 1) {
            SessionManager::flash('error', function_exists('__') ? __('access_denied') : 'Access denied');
            $this->redirect(rateb_url('admin'));
            return null;
        }
        $slug = strtolower(trim($slug));
        if ($slug === '' || !isset($checkout->addons()->catalog()[$slug])) {
            $this->notFound();
            return null;
        }
        if ($requirePurchasable && !$checkout->addons()->isPurchasable($slug) && !$checkout->companyAlreadyHasModule($companyId, $slug)) {
            $this->notFound();
            return null;
        }

        return [$companyId, $slug, $checkout];
    }

    private function errorMessage(string $code): string
    {
        return match ($code) {
            'disabled', 'unknown_module', 'not_purchasable' => function_exists('__') ? __('record_not_found') : 'Not available',
            'no_company' => function_exists('__') ? __('access_denied') : 'Access denied',
            'invalid_cycle' => 'Invalid billing cycle',
            'invoice_create_failed' => 'Could not create invoice',
            'payment_init_failed' => 'Payment could not be started',
            default => function_exists('__') ? __('invalid_request') : 'Request failed',
        };
    }
}
