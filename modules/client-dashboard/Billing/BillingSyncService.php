<?php
declare(strict_types=1);

final class Ratib_ClientDashboard_BillingSyncService
{
    /**
     * @param array<string, mixed> $billingAdapter
     * @param list<array<string, mixed>> $orders
     * @return array<string, mixed>
     */
    public function synthesize(array $billingAdapter, array $orders): array
    {
        $failed = 0;
        foreach ($orders as $o) {
            if (($o['payment_status'] ?? '') === 'failed') {
                ++$failed;
            }
        }

        return [
            'currency' => (string) ($billingAdapter['currency'] ?? 'SAR'),
            'invoice_count' => $billingAdapter['invoice_count'] ?? null,
            'transaction_count' => $billingAdapter['transaction_count'] ?? null,
            'credits_balance' => $billingAdapter['credits_balance'] ?? null,
            'failed_payment_orders' => $failed,
            'recurring_state' => $failed > 0 ? 'at_risk' : 'nominal',
            'sync_health' => 'partial',
        ];
    }
}
