<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

/**
 * Phase 11 — dry-run POS sync payload validation.
 *
 * Does NOT create invoices, deduct inventory, post accounting, or mark synced.
 */
final class PosSyncValidateService
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @return array{accepted: bool, conflicts: list<array<string, mixed>>, warnings: list<array<string, mixed>>, dry_run: bool, mode: string}
     */
    public function validate(array $payload, array $context = []): array
    {
        $conflicts = [];
        $warnings = [];

        $deviceId = trim((string) ($payload['device_id'] ?? ''));
        $installationId = trim((string) ($payload['installation_id'] ?? ''));
        $syncKey = trim((string) ($payload['sync_key'] ?? ''));
        $saleId = trim((string) ($payload['sale_id'] ?? ''));
        $createdAt = trim((string) ($payload['created_at'] ?? ''));
        $lines = $payload['lines'] ?? null;
        $totals = $payload['totals'] ?? null;
        $reservations = $payload['reservations'] ?? [];

        if ($deviceId === '') {
            $conflicts[] = $this->issue('missing_device_id', 'device_id is required');
        }
        if ($installationId === '') {
            $warnings[] = $this->issue('missing_installation_id', 'installation_id recommended');
        }
        if ($syncKey === '') {
            $conflicts[] = $this->issue('missing_sync_key', 'sync_key is required');
        }
        if ($saleId === '') {
            $conflicts[] = $this->issue('missing_sale_id', 'sale_id is required');
        }
        if ($createdAt === '') {
            $conflicts[] = $this->issue('missing_created_at', 'created_at is required');
        }
        if (!is_array($lines) || $lines === []) {
            $conflicts[] = $this->issue('missing_lines', 'lines[] is required');
        } else {
            foreach ($lines as $idx => $line) {
                if (!is_array($line)) {
                    $conflicts[] = $this->issue('invalid_line', 'Line ' . $idx . ' must be an object');
                    continue;
                }
                if (trim((string) ($line['product_id'] ?? '')) === '') {
                    $conflicts[] = $this->issue('missing_product_id', 'Line ' . $idx . ' missing product_id');
                }
                $qty = $line['qty'] ?? null;
                if (!is_numeric($qty) || (float) $qty <= 0) {
                    $conflicts[] = $this->issue('invalid_qty', 'Line ' . $idx . ' has invalid qty');
                }
            }
        }
        if (!is_array($totals)) {
            $conflicts[] = $this->issue('missing_totals', 'totals is required');
        } else {
            if (!isset($totals['total']) || !is_numeric($totals['total'])) {
                $conflicts[] = $this->issue('invalid_totals', 'totals.total must be numeric');
            }
        }
        if (!is_array($reservations)) {
            $warnings[] = $this->issue('invalid_reservations', 'reservations should be an array');
            $reservations = [];
        }

        $companyId = (int) ($context['company_id'] ?? 0);
        if ($companyId < 1) {
            $warnings[] = $this->issue('company_unbound', 'Company context unbound on token');
        }

        /* Explicit dry-run boundary — no side effects. */
        return [
            'accepted' => $conflicts === [],
            'conflicts' => $conflicts,
            'warnings' => $warnings,
            'dry_run' => true,
            'mode' => 'DRY_RUN_ONLY',
            'inventory_deducted' => false,
            'accounting_posted' => false,
            'invoice_created' => false,
            'marked_synced' => false,
            'echo' => [
                'sale_id' => $saleId !== '' ? $saleId : null,
                'sync_key' => $syncKey !== '' ? $syncKey : null,
                'device_id' => $deviceId !== '' ? $deviceId : null,
                'line_count' => is_array($lines) ? count($lines) : 0,
                'reservation_count' => count($reservations),
            ],
        ];
    }

    /** @return array{code: string, message: string} */
    private function issue(string $code, string $message): array
    {
        return [
            'code' => $code,
            'message' => $message,
        ];
    }
}
