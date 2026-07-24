<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosSyncAcceptance;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Throwable;

/**
 * Phase 12 — server acceptance layer (WAITING_COMMIT only).
 *
 * Does NOT create invoices, deduct inventory, post accounting, or mark synced.
 */
final class PosSyncAcceptanceService
{
    public const STATUS_WAITING_COMMIT = 'WAITING_COMMIT';

    private ?PosSyncAcceptance $model = null;

    public function __construct(
        private ?PosSyncValidateService $validator = null,
        private ?PosAuditBridgeService $audit = null,
    ) {
        $this->validator = $validator ?? new PosSyncValidateService();
        $this->audit = $audit ?? new PosAuditBridgeService();
    }

    private function model(): PosSyncAcceptance
    {
        return $this->model ??= new PosSyncAcceptance();
    }

    public function isAvailable(): bool
    {
        return Database::liveTableHasColumn('rateb_pos_sync_acceptances', 'server_sync_id');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function accept(array $payload, array $context = []): array
    {
        $started = hrtime(true);
        $companyId = (int) ($context['company_id'] ?? TenantContext::companyId() ?? 0);

        $this->audit->log('SYNC_RECEIVED', 'pos.sync_acceptance', null, [
            'company_id' => $companyId,
            'sync_key' => $payload['sync_key'] ?? null,
            'sale_id' => $payload['sale_id'] ?? null,
            'device_id' => $payload['device_id'] ?? null,
        ]);

        if ($companyId < 1) {
            $this->audit->log('SYNC_REJECTED', 'pos.sync_acceptance', null, [
                'reason' => 'missing_company_id',
            ]);

            return $this->rejectResponse([
                ['code' => 'missing_company_id', 'message' => 'company_id required'],
            ], [], $started, 'missing_company_id');
        }

        if (!$this->isAvailable()) {
            $this->audit->log('SYNC_REJECTED', 'pos.sync_acceptance', null, [
                'reason' => 'migration_required',
                'company_id' => $companyId,
            ]);

            return $this->rejectResponse([
                ['code' => 'migration_required', 'message' => 'Acceptance storage unavailable'],
            ], [], $started, 'migration_required', 503);
        }

        $validation = $this->validator->validate($payload, ['company_id' => $companyId]);
        $conflicts = $validation['conflicts'] ?? [];
        $warnings = $validation['warnings'] ?? [];

        if (($validation['accepted'] ?? false) !== true || $conflicts !== []) {
            $this->audit->log('SYNC_VALIDATION_FAILED', 'pos.sync_acceptance', null, [
                'company_id' => $companyId,
                'sync_key' => $payload['sync_key'] ?? null,
                'conflicts' => $conflicts,
            ]);
            $this->audit->log('SYNC_REJECTED', 'pos.sync_acceptance', null, [
                'company_id' => $companyId,
                'reason' => 'validation_failed',
            ]);

            return $this->rejectResponse($conflicts, $warnings, $started, 'validation_failed');
        }

        $syncKey = trim((string) ($payload['sync_key'] ?? ''));
        $existing = $this->findBySyncKey($companyId, $syncKey);
        if ($existing !== null) {
            $serverSyncId = (string) ($existing['server_sync_id'] ?? '');
            $this->audit->log('SYNC_DUPLICATE', 'pos.sync_acceptance', (int) ($existing['id'] ?? 0) ?: null, [
                'company_id' => $companyId,
                'sync_key' => $syncKey,
                'server_sync_id' => $serverSyncId,
            ]);

            return [
                'accepted' => true,
                'already_processed' => true,
                'server_sync_id' => $serverSyncId,
                'warnings' => $warnings,
                'conflicts' => [],
                'waiting_commit' => true,
                'status' => self::STATUS_WAITING_COMMIT,
                'inventory_deducted' => false,
                'accounting_posted' => false,
                'invoice_created' => false,
                'marked_synced' => false,
                'processing_ms' => $this->elapsedMs($started),
                'http_status' => 200,
            ];
        }

        $serverSyncId = 'psa_' . bin2hex(random_bytes(12));
        $now = date('Y-m-d H:i:s');
        $row = [
            'server_sync_id' => $serverSyncId,
            'company_id' => $companyId,
            'sync_key' => $syncKey,
            'sale_id' => trim((string) ($payload['sale_id'] ?? '')),
            'device_id' => trim((string) ($payload['device_id'] ?? '')),
            'installation_id' => trim((string) ($payload['installation_id'] ?? '')) ?: null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => self::STATUS_WAITING_COMMIT,
            'accepted_at' => $now,
        ];

        try {
            $id = $this->model()->create($row);
        } catch (Throwable $e) {
            /* Race on unique sync_key — treat as duplicate. */
            $existing = $this->findBySyncKey($companyId, $syncKey);
            if ($existing !== null) {
                $serverSyncId = (string) ($existing['server_sync_id'] ?? '');
                $this->audit->log('SYNC_DUPLICATE', 'pos.sync_acceptance', (int) ($existing['id'] ?? 0) ?: null, [
                    'company_id' => $companyId,
                    'sync_key' => $syncKey,
                    'server_sync_id' => $serverSyncId,
                    'race' => true,
                ]);

                return [
                    'accepted' => true,
                    'already_processed' => true,
                    'server_sync_id' => $serverSyncId,
                    'warnings' => $warnings,
                    'conflicts' => [],
                    'waiting_commit' => true,
                    'status' => self::STATUS_WAITING_COMMIT,
                    'inventory_deducted' => false,
                    'accounting_posted' => false,
                    'invoice_created' => false,
                    'marked_synced' => false,
                    'processing_ms' => $this->elapsedMs($started),
                    'http_status' => 200,
                ];
            }

            $this->audit->log('SYNC_REJECTED', 'pos.sync_acceptance', null, [
                'company_id' => $companyId,
                'reason' => 'store_failed',
                'error' => $e->getMessage(),
            ]);

            return $this->rejectResponse([
                ['code' => 'store_failed', 'message' => 'Unable to store acceptance'],
            ], $warnings, $started, 'store_failed', 500);
        }

        $this->audit->log('SYNC_ACCEPTED', 'pos.sync_acceptance', $id > 0 ? $id : null, [
            'company_id' => $companyId,
            'sync_key' => $syncKey,
            'server_sync_id' => $serverSyncId,
            'status' => self::STATUS_WAITING_COMMIT,
        ]);

        return [
            'accepted' => true,
            'already_processed' => false,
            'server_sync_id' => $serverSyncId,
            'warnings' => $warnings,
            'conflicts' => [],
            'waiting_commit' => true,
            'status' => self::STATUS_WAITING_COMMIT,
            'inventory_deducted' => false,
            'accounting_posted' => false,
            'invoice_created' => false,
            'marked_synced' => false,
            'processing_ms' => $this->elapsedMs($started),
            'http_status' => 200,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findBySyncKey(int $companyId, string $syncKey): ?array
    {
        if ($companyId < 1 || $syncKey === '' || !$this->isAvailable()) {
            return null;
        }

        return $this->model()->queryOne(
            'SELECT id, server_sync_id, company_id, sync_key, sale_id, device_id, status, created_at, accepted_at
             FROM rateb_pos_sync_acceptances
             WHERE company_id = :cid AND sync_key = :sk
             LIMIT 1',
            ['cid' => $companyId, 'sk' => $syncKey]
        );
    }

    /**
     * @param list<array<string, mixed>> $conflicts
     * @param list<array<string, mixed>> $warnings
     * @return array<string, mixed>
     */
    private function rejectResponse(
        array $conflicts,
        array $warnings,
        int|float $started,
        string $reason,
        int $httpStatus = 422
    ): array {
        return [
            'accepted' => false,
            'already_processed' => false,
            'server_sync_id' => null,
            'warnings' => $warnings,
            'conflicts' => $conflicts,
            'waiting_commit' => false,
            'status' => null,
            'reason' => $reason,
            'inventory_deducted' => false,
            'accounting_posted' => false,
            'invoice_created' => false,
            'marked_synced' => false,
            'processing_ms' => $this->elapsedMs($started),
            'http_status' => $httpStatus,
        ];
    }

    private function elapsedMs(int|float $started): float
    {
        return round((hrtime(true) - $started) / 1_000_000, 2);
    }
}
