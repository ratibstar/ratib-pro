<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AccountingSupport;
use Rateb\App\Services\AccountingWorkflowService;
use Rateb\App\Services\JournalService;
use Rateb\App\Services\OpeningBalanceService;
use Rateb\App\Services\RecurringJournalService;

/**
 * Thin Accounting offline replay (Phase 16B) — delegates ONLY to Phase 16A domain services.
 * Tier-1 drafts only. No posting / reverse / period close / payments / ZATCA / bank recon.
 */
final class AccountingOfflineReplayService
{
    /** Offline-safe workflow targets only (never posted/locked/reversed). */
    private const OFFLINE_WORKFLOW_TARGETS = ['draft', 'balanced'];

    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        return [
            'journal.create',
            'journal.update',
            'workflow.transition',
            'recurring.create',
            'opening_balance.create',
            'note.create',
            'accounting.journal.create',
            'accounting.journal.update',
            'accounting.workflow.transition',
        ];
    }

    public function __construct(
        private ?AccountingOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): AccountingOfflineTenantGuard
    {
        return $this->guard ??= new AccountingOfflineTenantGuard();
    }

    private function resolver(): OfflineConflictResolverService
    {
        return $this->resolver ??= new OfflineConflictResolverService();
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array{status: string, error?: string, result?: array<string, mixed>, reason?: string}
     */
    public function replayFromQueueRow(array $queueRow): array
    {
        $decoded = $this->decodePayload($queueRow);
        $action = $this->normalizeAction(
            (string) ($decoded['action'] ?? $queueRow['action'] ?? '')
        );
        $inner = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        unset($inner['branch_id'], $inner['company_id'], $inner['user_id'], $inner['device_id']);
        $idempotencyKey = substr(trim((string) (
            $queueRow['idempotency_key']
            ?? $decoded['client_id']
            ?? $decoded['idempotency_key']
            ?? ''
        )), 0, 64);

        if (!in_array($action, self::deferredActions(), true)) {
            return ['status' => 'skipped', 'error' => 'unknown_accounting_action'];
        }

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isAccountingEnabled()) {
            return ['status' => 'skipped', 'error' => 'accounting_offline_disabled'];
        }
        if (in_array($action, [
            'journal.create', 'journal.update', 'note.create',
            'accounting.journal.create', 'accounting.journal.update',
            'recurring.create', 'opening_balance.create',
        ], true) && !$flags->isAccountingJournalsEnabled()) {
            return ['status' => 'skipped', 'error' => 'accounting_journals_offline_disabled'];
        }
        if (in_array($action, ['workflow.transition', 'accounting.workflow.transition'], true)
            && !$flags->isAccountingWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'accounting_workflow_offline_disabled'];
        }

        try {
            $scope = (new OfflineReplayScopeService())->fromQueueRow($queueRow);
            if ($scope['company_id'] < 1) {
                return ['status' => 'failed', 'error' => 'company_required'];
            }

            TenantContext::setCompanyId($scope['company_id']);
            if ($scope['user_id'] > 0) {
                SessionManager::set('rateb_user_id', $scope['user_id']);
            }

            $result = $this->replay($action, $scope, $inner, $idempotencyKey);

            return ['status' => 'synced', 'result' => $result];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($this->isConflictError($message)) {
                return ['status' => 'conflict', 'error' => $message, 'reason' => $message];
            }

            return ['status' => 'failed', 'error' => $message];
        }
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    public function replay(string $action, array $scope, array $inner, string $idempotencyKey = ''): array
    {
        $action = $this->normalizeAction($action);

        return match ($action) {
            'journal.create', 'accounting.journal.create'
                => $this->journalCreate($scope, $inner, $idempotencyKey),
            'journal.update', 'accounting.journal.update'
                => $this->journalUpdate($scope, $inner, $idempotencyKey),
            'workflow.transition', 'accounting.workflow.transition'
                => $this->workflowTransition($scope, $inner, $idempotencyKey),
            'recurring.create'
                => $this->recurringCreate($scope, $inner),
            'opening_balance.create'
                => $this->openingBalanceCreate($scope, $inner, $idempotencyKey),
            'note.create'
                => $this->noteCreate($scope, $inner),
            default => throw new \RuntimeException('unknown_accounting_action'),
        };
    }

    /**
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed>|null $serverItem
     * @return array<string, mixed>
     */
    public function resolveConflict(array $clientItem, ?array $serverItem): array
    {
        return $this->resolver()->resolveAccounting($clientItem, $serverItem);
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function journalCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->journalExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'idempotent' => true, 'journal_entry_id' => $existing];
            }
        }
        $this->assertLinesAccounts($scope, is_array($inner['lines'] ?? null) ? $inner['lines'] : []);
        $entryDate = trim((string) ($inner['entry_date'] ?? date('Y-m-d')));
        if ($this->guard()->isPeriodClosedForDate($scope['company_id'], $entryDate)) {
            throw new \RuntimeException('period_closed');
        }
        $payload = $inner;
        if ($scope['branch_id'] > 0 && empty($payload['branch_id'])) {
            $payload['branch_id'] = $scope['branch_id'];
        }
        if ($idempotencyKey !== '') {
            $desc = trim((string) ($payload['description'] ?? ''));
            $payload['description'] = trim($desc . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new JournalService())->createDraft($payload);

        return ['ok' => true, 'journal_entry_id' => $created['id']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function journalUpdate(array $scope, array $inner, string $idempotencyKey): array
    {
        $entryId = (int) ($inner['journal_entry_id'] ?? $inner['entry_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertJournal($entryId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['journal'] ?? [];
        $status = strtolower((string) ($server['lifecycle_status'] ?? $server['status'] ?? 'draft'));
        if (in_array($status, ['posted', 'locked', 'reversed', 'void'], true)) {
            throw new \RuntimeException('journal_already_posted');
        }
        if (isset($inner['expected_status']) || isset($inner['expected_lifecycle_status'])) {
            $decision = $this->resolver()->resolveAccounting(
                [
                    'version' => (int) ($inner['version'] ?? 1),
                    'expected_status' => $inner['expected_lifecycle_status'] ?? $inner['expected_status'] ?? null,
                ],
                [
                    'version' => (int) ($inner['server_version'] ?? 0),
                    'status' => $status,
                ]
            );
            if (($decision['action'] ?? '') === 'reject_client') {
                throw new \RuntimeException((string) ($decision['reason'] ?? 'status_changed'));
            }
        }
        $payload = $inner;
        unset(
            $payload['journal_entry_id'],
            $payload['entry_id'],
            $payload['id'],
            $payload['expected_status'],
            $payload['expected_lifecycle_status'],
            $payload['server_version']
        );
        if (isset($payload['lines']) && is_array($payload['lines'])) {
            $this->assertLinesAccounts($scope, $payload['lines']);
        }
        if ($idempotencyKey !== '' && isset($payload['description'])) {
            $payload['description'] = trim((string) $payload['description'] . ' [offline:' . $idempotencyKey . ']');
        }
        (new JournalService())->updateDraft($entryId, $payload);

        return ['ok' => true, 'journal_entry_id' => $entryId, 'updated' => true];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner, string $idempotencyKey): array
    {
        $entryId = (int) ($inner['journal_entry_id'] ?? $inner['entry_id'] ?? 0);
        $assert = $this->guard()->assertJournal($entryId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $to = strtolower(trim((string) ($inner['to_status'] ?? $inner['lifecycle_status'] ?? '')));
        if (!in_array($to, self::OFFLINE_WORKFLOW_TARGETS, true)) {
            // Posting / reverse / lock / archive remain ONLINE ONLY.
            throw new \RuntimeException('accounting_offline_transition_denied');
        }
        $server = $assert['journal'] ?? [];
        $status = strtolower((string) ($server['lifecycle_status'] ?? $server['status'] ?? 'draft'));
        if (in_array($status, ['posted', 'locked', 'reversed', 'void'], true)) {
            throw new \RuntimeException('journal_already_posted');
        }
        $reason = trim((string) ($inner['reason'] ?? ''));
        if ($idempotencyKey !== '') {
            $reason = trim($reason . ' [offline:' . $idempotencyKey . ']');
        }
        $out = (new AccountingWorkflowService())->transition($entryId, $to, $reason !== '' ? $reason : null);

        return array_merge(['ok' => true], $out);
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function recurringCreate(array $scope, array $inner): array
    {
        $lines = is_array($inner['lines'] ?? null) ? $inner['lines'] : [];
        $this->assertLinesAccounts($scope, $lines);
        $payload = $inner;
        if ($scope['branch_id'] > 0 && empty($payload['branch_id'])) {
            $payload['branch_id'] = $scope['branch_id'];
        }
        $created = (new RecurringJournalService())->create($payload);

        return ['ok' => true, 'recurring_journal_id' => $created['id']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function openingBalanceCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->journalExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'idempotent' => true, 'journal_entry_id' => $existing];
            }
        }
        $lines = is_array($inner['lines'] ?? null) ? $inner['lines'] : [];
        $this->assertLinesAccounts($scope, $lines);
        $payload = $inner;
        if ($idempotencyKey !== '') {
            $desc = trim((string) ($payload['description'] ?? 'Opening balances'));
            $payload['description'] = trim($desc . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new OpeningBalanceService())->create($payload);

        return ['ok' => true, 'journal_entry_id' => $created['id'], 'opening_balance' => true];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function noteCreate(array $scope, array $inner): array
    {
        $entryId = (int) ($inner['journal_entry_id'] ?? $inner['entry_id'] ?? 0);
        $assert = $this->guard()->assertJournal($entryId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $body = trim((string) ($inner['body'] ?? $inner['notes'] ?? $inner['note'] ?? ''));
        if ($body === '') {
            throw new \InvalidArgumentException('note_required');
        }
        $server = $assert['journal'] ?? [];
        $status = strtolower((string) ($server['lifecycle_status'] ?? $server['status'] ?? 'draft'));
        if (in_array($status, ['posted', 'locked', 'reversed', 'void'], true)) {
            throw new \RuntimeException('journal_already_posted');
        }
        $desc = trim((string) ($server['description'] ?? ''));
        $newDesc = trim($desc . "\n[note] " . $body);
        (new JournalService())->updateDraft($entryId, [
            'entry_date' => (string) ($server['entry_date'] ?? date('Y-m-d')),
            'description' => $newDesc,
            'description_ar' => (string) ($server['description_ar'] ?? ''),
            'lines' => $this->loadLines($entryId),
        ]);
        AccountingSupport::activity($scope['company_id'], 'journal.note', $body, $entryId);

        return ['ok' => true, 'journal_entry_id' => $entryId, 'note' => true];
    }

    /**
     * @param array<string, mixed> $scope
     * @param list<array<string, mixed>> $lines
     */
    private function assertLinesAccounts(array $scope, array $lines): void
    {
        foreach ($lines as $line) {
            $aid = (int) ($line['account_id'] ?? 0);
            if ($aid < 1) {
                continue;
            }
            $a = $this->guard()->assertAccount($aid, $scope);
            if (!$a['ok']) {
                throw new \RuntimeException((string) ($a['error'] ?? 'account_not_found'));
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function loadLines(int $entryId): array
    {
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare(
            'SELECT account_id, debit, credit, memo, cost_center_id
             FROM rateb_journal_lines WHERE journal_entry_id = :id ORDER BY id ASC'
        );
        $stmt->execute(['id' => $entryId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'account_id' => (int) ($row['account_id'] ?? 0),
                'debit' => (float) ($row['debit'] ?? 0),
                'credit' => (float) ($row['credit'] ?? 0),
                'memo' => (string) ($row['memo'] ?? ''),
                'cost_center_id' => isset($row['cost_center_id']) ? (int) $row['cost_center_id'] : null,
            ];
        }

        return $out;
    }

    private function isConflictError(string $message): bool
    {
        return in_array($message, [
            'server_newer',
            'status_changed',
            'branch_mismatch',
            'tenant_mismatch',
            'period_closed',
            'journal_already_posted',
            'accounting_conflict',
        ], true);
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        $aliases = [
            'create_journal' => 'journal.create',
            'update_journal' => 'journal.update',
            'transition_workflow' => 'workflow.transition',
            'create_recurring' => 'recurring.create',
            'create_opening_balance' => 'opening_balance.create',
            'create_note' => 'note.create',
        ];

        return $aliases[$action] ?? $action;
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array<string, mixed>
     */
    private function decodePayload(array $queueRow): array
    {
        $raw = $queueRow['payload'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return ['action' => $queueRow['action'] ?? null, 'payload' => []];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['action' => $queueRow['action'] ?? null, 'payload' => []];
    }
}
