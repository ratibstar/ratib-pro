<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\Crm\CustomerActivityService;
use Ratib\ContactCenter\App\Application\Services\Crm\CustomerProfileService;
use Ratib\ContactCenter\App\Application\Services\Crm\CustomerTagService;
use Ratib\ContactCenter\App\Application\Services\Crm\CustomerTimelineService;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Core\TenantContext;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm\CrmAccountRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm\CrmContactRepository;

final class CrmApiController
{
    public function __construct(
        private readonly CustomerProfileService $profiles = new CustomerProfileService(),
        private readonly CustomerTimelineService $timeline = new CustomerTimelineService(),
        private readonly CustomerTagService $tags = new CustomerTagService(),
        private readonly CustomerActivityService $activities = new CustomerActivityService(),
        private readonly CrmAccountRepository $accounts = new CrmAccountRepository(),
        private readonly CrmContactRepository $contacts = new CrmContactRepository()
    ) {
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            RealtimeOrchestrator::boot();
            $action = (string) ($_GET['action'] ?? '');
            $input = array_merge($this->parseJsonBody(), $_GET);
            echo json_encode($this->handleAction($action, $input), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string, mixed> */
    public function handleAction(string $action, array $input): array
    {
        AuthContext::requirePermission('rcc.crm.view');
        $tenantId = $this->resolveTenantId($input);
        $userId = AuthContext::userId();
        TenantContext::set($tenantId);

        return match ($action) {
            'accounts_list' => $this->requirePerm('rcc.crm.accounts') ?: $this->ok([
                'accounts' => $this->accounts->list($tenantId, isset($input['q']) ? (string) $input['q'] : null),
            ]),
            'account_get' => $this->requirePerm('rcc.crm.accounts') ?: $this->ok(
                $this->profiles->accountProfile($tenantId, (int) ($input['account_id'] ?? 0))
            ),
            'account_save' => $this->runPerm('rcc.crm.accounts', fn () => $this->ok([
                'account' => $this->profiles->saveAccount($tenantId, $input, $userId),
            ])),
            'contacts_list' => $this->requirePerm('rcc.crm.contacts') ?: $this->ok([
                'contacts' => $this->contacts->list(
                    $tenantId,
                    isset($input['account_id']) ? (int) $input['account_id'] : null,
                    isset($input['q']) ? (string) $input['q'] : null
                ),
            ]),
            'contact_get' => $this->requirePerm('rcc.crm.contacts') ?: $this->ok(
                $this->profiles->contactProfile($tenantId, (int) ($input['contact_id'] ?? 0))
            ),
            'contact_save' => $this->runPerm('rcc.crm.contacts', fn () => $this->ok([
                'contact' => $this->profiles->saveContact($tenantId, $input, $userId),
            ])),
            'timeline' => $this->ok(['timeline' => $this->timeline->timeline($tenantId, (int) ($input['contact_id'] ?? 0))]),
            'interactions' => $this->ok(['interactions' => $this->timeline->interactions($tenantId, (int) ($input['contact_id'] ?? 0))]),
            'note_add' => $this->runPerm('rcc.crm.notes', fn () => $this->ok([
                'note_id' => $this->activities->addNote($tenantId, (int) ($input['contact_id'] ?? 0), (string) ($input['body'] ?? ''), $userId),
            ])),
            'notes_list' => $this->requirePerm('rcc.crm.notes') ?: $this->ok([
                'notes' => $this->activities->listNotes($tenantId, (int) ($input['contact_id'] ?? 0)),
            ]),
            'tag_add' => $this->runPerm('rcc.crm.tags', function () use ($tenantId, $input, $userId) {
                $this->tags->add($tenantId, (int) ($input['contact_id'] ?? 0), (string) ($input['tag'] ?? ''), $userId, isset($input['color']) ? (string) $input['color'] : null);
                return $this->ok(['added' => true]);
            }),
            'tag_remove' => $this->runPerm('rcc.crm.tags', fn () => $this->ok([
                'removed' => $this->tags->remove($tenantId, (int) ($input['contact_id'] ?? 0), (string) ($input['tag'] ?? ''), $userId),
            ])),
            'tags_list' => $this->ok(['tags' => $this->tags->list($tenantId, (int) ($input['contact_id'] ?? 0))]),
            'documents_list' => $this->requirePerm('rcc.crm.documents') ?: $this->ok([
                'documents' => $this->activities->listDocuments($tenantId, (int) ($input['contact_id'] ?? 0)),
            ]),
            'erp_sync' => $this->runPerm('rcc.crm.sync', fn () => $this->ok([
                'account' => $this->profiles->syncFromErp($tenantId, (int) ($input['erp_company_id'] ?? 0), $userId),
            ])),
            'tenants_list' => $this->requirePerm('rcc.tenants.manage') ?: $this->ok(['tenants' => $this->listTenants()]),
            default => ['ok' => false, 'error' => 'Unknown action: ' . $action],
        };
    }

    /** @param array<string, mixed> $input */
    private function resolveTenantId(array $input): int
    {
        $tenantId = AuthContext::tenantId();
        if (AuthContext::can('rcc.tenants.manage')) {
            $requested = (int) ($input['tenant_id'] ?? 0);
            if ($requested > 0) {
                return $requested;
            }
        }
        return $tenantId;
    }

    /** @return array<string, mixed>|null */
    private function requirePerm(string $perm): ?array
    {
        if (!AuthContext::can($perm)) {
            return ['ok' => false, 'error' => 'Permission denied: ' . $perm];
        }
        return null;
    }

    /** @return array<string, mixed> */
    private function runPerm(string $perm, callable $fn): array
    {
        $denied = $this->requirePerm($perm);
        return $denied ?? $fn();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function ok(array $data): array
    {
        return ['ok' => true] + $data;
    }

    /** @return list<array<string, mixed>> */
    private function listTenants(): array
    {
        $stmt = \Ratib\ContactCenter\App\Core\Database::connection()->query(
            "SELECT id, code, name, name_ar, status FROM rcc_tenants WHERE status = 'active' ORDER BY name"
        );
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    /** @return array<string, mixed> */
    private function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
