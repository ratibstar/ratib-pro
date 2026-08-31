<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Payment\PaymentTransactionRepository;

/**
 * Phase 3 — activate add-on after existing Moyasar webhook finalization.
 * Must never change the webhook HTTP status.
 */
final class ModuleAddonActivationHook
{
    public function __construct(
        private readonly ModuleAddonService $addons = new ModuleAddonService(),
        private readonly PaymentTransactionRepository $transactions = new PaymentTransactionRepository(),
    ) {
    }

    public function afterSuccessfulWebhook(string $rawBody): void
    {
        if (!$this->addons->isEnabled()) {
            return;
        }
        $externalId = $this->externalIdFromPayload($rawBody);
        if ($externalId === '') {
            return;
        }
        $tx = $this->transactions->findByExternalId('moyasar', $externalId);
        if ($tx === null) {
            return;
        }
        $invoiceId = (int) ($tx['invoice_id'] ?? 0);
        $txId = (int) ($tx['id'] ?? 0);
        $txStatus = strtolower(trim((string) ($tx['status'] ?? '')));
        if ($invoiceId < 1 || $txStatus !== 'completed') {
            return;
        }
        $result = $this->addons->activateFromPaidInvoice($invoiceId, $txId > 0 ? $txId : null);
        if (($result['ok'] ?? false) !== true && ($result['code'] ?? '') !== 'ignored' && empty($result['disabled'])) {
            Logger::error('module_addon_webhook_activate_rejected', [
                'invoice_id' => $invoiceId,
                'code' => (string) ($result['code'] ?? ''),
            ]);
        }
    }

    private function externalIdFromPayload(string $rawBody): string
    {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return '';
        }
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;

        return trim((string) ($data['id'] ?? $payload['id'] ?? ''));
    }
}
