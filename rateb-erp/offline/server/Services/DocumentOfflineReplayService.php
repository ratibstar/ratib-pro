<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\DmsCheckoutService;
use Rateb\App\Services\DmsCommentService;
use Rateb\App\Services\DmsDocumentService;
use Rateb\App\Services\DmsFolderService;
use Rateb\App\Services\DmsPermissionService;
use Rateb\App\Services\DmsRepositoryService;
use Rateb\App\Services\DmsShareService;
use Rateb\App\Services\DmsVersionService;
use Rateb\App\Services\DocumentTimelineService;
use Rateb\App\Services\DocumentWorkflowService;

/**
 * Thin Documents offline replay (Phase 26B) — delegates ONLY to Phase 26A domain services.
 * Tier-1 drafts only. No delete / upload / attachment / binary / notifications / email / SMS /
 * payments / signature / publish / approve / download.
 */
final class DocumentOfflineReplayService
{
    private const PREFIX = 'documents.';

    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        $bare = [
            'repository.create',
            'repository.update',
            'folder.create',
            'folder.update',
            'document.create',
            'document.update',
            'version.create',
            'checkout.create',
            'share.create',
            'permission.create',
            'comment.create',
            'workflow.transition',
            'note.create',
        ];
        $out = $bare;
        foreach ($bare as $a) {
            $out[] = self::PREFIX . $a;
        }

        return $out;
    }

    public function __construct(
        private ?DocumentOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): DocumentOfflineTenantGuard
    {
        return $this->guard ??= new DocumentOfflineTenantGuard();
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

        if (!in_array($action, self::deferredActions(), true)
            && !in_array($this->normalizeAction($action), self::deferredActions(), true)) {
            return ['status' => 'skipped', 'error' => 'unknown_documents_action'];
        }
        $action = $this->normalizeAction($action);

        if (in_array($action, [
            'delete', 'attachment.create', 'upload', 'payment.create', 'bank_transfer',
            'accounting.post', 'inventory.post', 'notification.send', 'email.send', 'sms.send',
            'government.submit', 'approval.decide', 'signature.create', 'publish', 'approve',
            'download',
        ], true)) {
            return ['status' => 'skipped', 'error' => 'documents_action_rejected'];
        }

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isDocumentsEnabled()) {
            return ['status' => 'skipped', 'error' => 'documents_offline_disabled'];
        }
        if (in_array($action, [
            'repository.create', 'repository.update', 'folder.create', 'folder.update',
        ], true) && !$flags->isDocumentsRepositoriesEnabled()) {
            return ['status' => 'skipped', 'error' => 'documents_repositories_offline_disabled'];
        }
        if ($action === 'workflow.transition' && !$flags->isDocumentsWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'documents_workflow_offline_disabled'];
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
    private function replay(string $action, array $scope, array $inner, string $idempotencyKey): array
    {
        return match ($action) {
            'repository.create' => ['ok' => true, 'repository' => (new DmsRepositoryService())->create($this->stampNotes($inner, $idempotencyKey))],
            'repository.update' => $this->repositoryUpdate($scope, $inner),
            'folder.create' => ['ok' => true, 'folder' => (new DmsFolderService())->create($this->stampNotes($inner, $idempotencyKey))],
            'folder.update' => $this->folderUpdate($scope, $inner),
            'document.create' => $this->documentCreate($scope, $inner, $idempotencyKey),
            'document.update' => $this->documentUpdate($scope, $inner),
            'version.create' => ['ok' => true, 'version' => (new DmsVersionService())->create($this->stampNotes($inner, $idempotencyKey))],
            'checkout.create' => ['ok' => true, 'checkout' => (new DmsCheckoutService())->create($this->stampNotes($inner, $idempotencyKey))],
            'share.create' => ['ok' => true, 'share' => (new DmsShareService())->create($this->stampNotes($inner, $idempotencyKey))],
            'permission.create' => ['ok' => true, 'permission' => (new DmsPermissionService())->grant($this->stampNotes($inner, $idempotencyKey))],
            'comment.create' => $this->commentCreate($inner),
            'workflow.transition' => $this->workflowTransition($scope, $inner),
            'note.create' => $this->noteCreate($inner),
            default => throw new \RuntimeException('unknown_documents_action'),
        };
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function stampNotes(array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey === '') {
            return $inner;
        }
        $notes = trim((string) ($inner['notes'] ?? ''));
        $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');

        return $inner;
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function documentCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->documentExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'document_id' => $existing, 'duplicate_replay' => true];
            }
            $inner = $this->stampNotes($inner, $idempotencyKey);
        }
        $created = (new DmsDocumentService())->create($inner);

        return ['ok' => true, 'document_id' => $created['id'], 'code' => $created['code']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function documentUpdate(array $scope, array $inner): array
    {
        $id = (int) ($inner['document_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertDocument($id, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['document'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['document_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new DmsDocumentService())->update($id, $payload);

        return ['ok' => true, 'document_id' => $id];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function repositoryUpdate(array $scope, array $inner): array
    {
        $id = (int) ($inner['repository_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertRepository($id, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['repository'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['repository_id'], $payload['id'], $payload['expected_status'], $payload['server_version']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new DmsRepositoryService())->update($id, $payload);

        return ['ok' => true, 'repository_id' => $id];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function folderUpdate(array $scope, array $inner): array
    {
        $id = (int) ($inner['folder_id'] ?? $inner['id'] ?? 0);
        if ($id < 1) {
            throw new \RuntimeException('invalid_folder_id');
        }
        $payload = $inner;
        unset($payload['folder_id'], $payload['id'], $payload['expected_status'], $payload['server_version']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new DmsFolderService())->update($id, $payload);

        return ['ok' => true, 'folder_id' => $id];
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function commentCreate(array $inner): array
    {
        (new DmsCommentService())->create($inner);

        return ['ok' => true, 'comment' => true];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner): array
    {
        $entityType = strtolower(trim((string) ($inner['entity_type'] ?? DocumentWorkflowService::ENTITY_DOCUMENT)));
        if ($entityType === '') {
            $entityType = DocumentWorkflowService::ENTITY_DOCUMENT;
        }
        if (!in_array($entityType, DocumentWorkflowService::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_dms_entity_type');
        }
        $id = (int) ($inner['entity_id'] ?? $inner['document_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertDocument($id, $scope);
        if (!($assert['ok'] ?? false)) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $to = trim((string) ($inner['to_status'] ?? $inner['target_status'] ?? ''));
        if ($to === '') {
            throw new \InvalidArgumentException('to_status_required');
        }
        // Offline may only advance early statuses (never approve/publish).
        $allowedOffline = ['draft', 'checked_in', 'review', 'archived'];
        if (!in_array($to, $allowedOffline, true)) {
            throw new \RuntimeException('documents_workflow_offline_denied');
        }
        $reason = isset($inner['reason']) ? (string) $inner['reason'] : null;
        $expectedVersion = isset($inner['expected_version']) ? (int) $inner['expected_version'] : null;

        return (new DocumentWorkflowService())->transition($entityType, $id, $to, $reason, $expectedVersion);
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function noteCreate(array $inner): array
    {
        $title = trim((string) ($inner['title'] ?? $inner['event_type'] ?? 'Offline note'));
        $body = isset($inner['body']) ? (string) $inner['body'] : (isset($inner['notes']) ? (string) $inner['notes'] : null);
        $entityType = isset($inner['entity_type']) ? (string) $inner['entity_type'] : null;
        $entityId = isset($inner['entity_id']) ? (int) $inner['entity_id'] : null;
        (new DocumentTimelineService())->record(
            'offline_note',
            $title !== '' ? $title : 'Offline note',
            $entityType,
            $entityId > 0 ? $entityId : null,
            $body
        );

        return ['ok' => true, 'note' => true];
    }

    /**
     * @param array<string, mixed> $inner
     * @param array<string, mixed> $server
     */
    private function maybeConflict(array $inner, array $server): void
    {
        if (!isset($inner['expected_status'])) {
            return;
        }
        $status = strtolower((string) ($server['workflow_status'] ?? $server['status'] ?? 'draft'));
        $clientItem = [
            'version' => (int) ($inner['version'] ?? $inner['expected_version'] ?? 1),
            'expected_status' => $inner['expected_status'],
        ];
        $serverItem = [
            'version' => (int) ($server['version'] ?? $inner['server_version'] ?? 0),
            'status' => $status,
        ];
        $resolver = $this->resolver();
        if (method_exists($resolver, 'resolveDocuments')) {
            $decision = $resolver->resolveDocuments($clientItem, $serverItem);
        } else {
            $decision = $this->resolveGenericLikeQuality($resolver, $clientItem, $serverItem, $status);
        }
        if (($decision['action'] ?? '') === 'reject_client') {
            throw new \RuntimeException((string) ($decision['reason'] ?? 'status_changed'));
        }
    }

    /**
     * Fallback until OfflineConflictResolverService::resolveDocuments is wired.
     *
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed> $serverItem
     * @return array<string, mixed>
     */
    private function resolveGenericLikeQuality(
        OfflineConflictResolverService $resolver,
        array $clientItem,
        array $serverItem,
        string $serverStatus
    ): array {
        $base = $resolver->resolve($clientItem, $serverItem);
        if (($base['action'] ?? '') === 'reject_client') {
            return $base;
        }
        if (in_array($serverStatus, ['approved', 'published', 'archived'], true)
            && isset($clientItem['expected_status'])
            && (string) $clientItem['expected_status'] !== $serverStatus) {
            return [
                'action' => 'reject_client',
                'item' => $serverItem,
                'reason' => 'status_changed',
            ];
        }
        $expectedStatus = $clientItem['expected_status'] ?? null;
        if ($expectedStatus !== null && ($serverStatus !== '' || array_key_exists('status', $serverItem))) {
            $compare = $serverStatus !== '' ? $serverStatus : (string) ($serverItem['status'] ?? '');
            if ($compare !== (string) $expectedStatus) {
                return [
                    'action' => 'reject_client',
                    'item' => $serverItem,
                    'reason' => 'status_changed',
                ];
            }
        }

        return $base;
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        if (str_starts_with($action, self::PREFIX)) {
            $action = substr($action, strlen(self::PREFIX));
        }

        return $action;
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array<string, mixed>
     */
    private function decodePayload(array $queueRow): array
    {
        $payload = $queueRow['payload'] ?? null;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function isConflictError(string $message): bool
    {
        return in_array($message, [
            'status_changed',
            'server_newer',
            'version_conflict',
            'document_not_editable',
        ], true);
    }
}
