<?php
declare(strict_types=1);

namespace Rateb\App\Payment;

use Rateb\App\Services\AuditService;
use Rateb\App\Services\Logger;

final class PaymentAuditService
{
    public function log(string $action, ?int $transactionId, array $context = []): void
    {
        $safe = $this->redactSecrets($context);
        Logger::info('payment.' . $action, array_merge(['transaction_id' => $transactionId], $safe));
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('payment.' . $action, 'payment_transaction', $transactionId ?? 0, $safe);
        }
    }

    /** @param array<string, mixed> $context */
    private function redactSecrets(array $context): array
    {
        $keys = ['secret', 'secret_key', 'webhook_secret', 'authorization', 'api_key', 'token'];
        foreach ($context as $k => $v) {
            $lk = strtolower((string) $k);
            foreach ($keys as $needle) {
                if (str_contains($lk, $needle)) {
                    $context[$k] = '[REDACTED]';
                    break;
                }
            }
        }

        return $context;
    }
}
