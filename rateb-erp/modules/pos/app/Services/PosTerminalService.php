<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosTerminal;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Services\Bridge\PosBranchBridgeService;
use Rateb\App\Pos\Support\PosDocumentCodes;
use Rateb\App\Pos\Support\PosFkValidator;

final class PosTerminalService
{
    public function __construct(
        private PosBranchBridgeService $branch = new PosBranchBridgeService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 100, int $offset = 0): array
    {
        if ($companyId < 1) {
            return [];
        }
        TenantContext::setCompanyId($companyId);
        return (new PosTerminal())->all($limit, $offset);
    }

    public function countForCompany(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        TenantContext::setCompanyId($companyId);
        return (new PosTerminal())->count([]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        try {
            return PosFkValidator::assertTerminal($id, $companyId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @param array<string, mixed> $data */
    public function create(int $companyId, array $data): int
    {
        if ($companyId < 1) {
            throw new \RuntimeException(__('select_company_ops'));
        }
        TenantContext::setCompanyId($companyId);
        $data['company_id'] = $companyId;
        $data = $this->branch->stampCreate($data);
        PosFkValidator::validateTerminal($data, $companyId);

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            $data['code'] = (new PosTerminal())->generateDocumentCode(PosDocumentCodes::TERMINAL, 'code');
        } else {
            $data['code'] = $code;
        }
        $data['name'] = trim((string) ($data['name'] ?? ''));
        if ($data['name'] === '') {
            throw new \RuntimeException(__('name') . ': ' . __('invalid_request'));
        }
        $data['status'] = in_array($data['status'] ?? 'active', ['active', 'inactive'], true)
            ? $data['status'] : 'active';

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $id = (new PosTerminal())->create($data);
            $this->audit->log('create', 'pos_terminal', $id, [
                'code' => $data['code'],
                'branch_id' => (int) ($data['branch_id'] ?? 0),
            ]);
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $companyId, array $data): bool
    {
        $existing = PosFkValidator::assertTerminal($id, $companyId);
        TenantContext::setCompanyId($companyId);
        $data['company_id'] = $companyId;
        if (!array_key_exists('branch_id', $data) || empty($data['branch_id'])) {
            $data['branch_id'] = (int) ($existing['branch_id'] ?? 0);
        }
        PosFkValidator::validateTerminal($data, $companyId);

        $payload = [
            'branch_id' => (int) ($data['branch_id'] ?? 0),
            'warehouse_id' => !empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
            'name' => trim((string) ($data['name'] ?? $existing['name'] ?? '')),
            'status' => in_array($data['status'] ?? $existing['status'] ?? 'active', ['active', 'inactive'], true)
                ? ($data['status'] ?? $existing['status']) : 'active',
        ];
        if ($payload['name'] === '') {
            throw new \RuntimeException(__('name') . ': ' . __('invalid_request'));
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $ok = (new PosTerminal())->update($id, $payload);
            $this->audit->log('update', 'pos_terminal', $id, ['old' => $existing, 'new' => $payload]);
            $db->commit();
            return $ok;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id, int $companyId): bool
    {
        $existing = PosFkValidator::assertTerminal($id, $companyId);
        TenantContext::setCompanyId($companyId);

        $openShift = Database::connection()->prepare(
            'SELECT id FROM rateb_pos_shifts WHERE terminal_id = :tid AND status = :st LIMIT 1'
        );
        $openShift->execute(['tid' => $id, 'st' => 'open']);
        if ($openShift->fetchColumn()) {
            throw new \RuntimeException(__('pos_terminal_has_open_shift'));
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $ok = (new PosTerminal())->delete($id);
            $this->audit->log('delete', 'pos_terminal', $id, ['code' => $existing['code'] ?? null]);
            $db->commit();
            return $ok;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
