<?php
declare(strict_types=1);

final class Ratib_ClientDashboard_BillingAdapter
{
    /**
     * @return array<string, mixed>
     */
    public function fetchNormalized(Ratib_ClientDashboard_AdapterContext $ctx): array
    {
        $base = [
            'currency' => 'SAR',
            'invoice_count' => null,
            'unpaid_invoice_hint' => null,
            'transaction_count' => null,
            'credits_balance' => null,
            'next_renewal_hint' => null,
        ];

        try {
            $conn = $ctx->conn;
            if (!$conn instanceof mysqli) {
                $ctx->obs->recordAdapter('billing', true, 'no_connection', []);

                return $base;
            }
            $inv = $this->safeCount($conn, 'accounting_invoices');
            if ($inv !== null) {
                $base['invoice_count'] = $inv;
            }
            $tx = $this->safeCount($conn, 'financial_transactions');
            if ($tx !== null) {
                $base['transaction_count'] = $tx;
            }
            $ctx->obs->recordAdapter('billing', true, null, ['invoice_count' => $base['invoice_count']]);
        } catch (Throwable $e) {
            $ctx->obs->recordAdapter('billing', false, $e->getMessage());
        }

        return $base;
    }

    private function safeCount(mysqli $conn, string $table): ?int
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($table === '') {
            return null;
        }
        $chk = @$conn->query("SHOW TABLES LIKE '{$table}'");
        if (!$chk || $chk->num_rows === 0) {
            return null;
        }
        $r = @$conn->query("SELECT COUNT(*) AS c FROM `{$table}`");
        if (!$r) {
            return null;
        }
        $row = $r->fetch_assoc();

        return isset($row['c']) ? (int) $row['c'] : null;
    }
}
