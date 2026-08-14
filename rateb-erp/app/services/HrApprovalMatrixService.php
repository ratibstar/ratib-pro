<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;

/**
 * Phase G — HR approval matrix governance overlay (not an approval engine).
 *
 * - No matrix / tables missing → passthrough (exact pre-G Oversight behavior).
 * - Matrix present → stage progression; domain finalize only on final stage.
 * - Progress binds matrix_id + matrix_version + stages_snapshot_json (versioning).
 * - Does NOT UPDATE domain status directly; callers invoke existing finalizers.
 * - Does NOT use EAP or Legacy WorkflowService.
 */
final class HrApprovalMatrixService
{
    public const SOURCE_LEAVE = 'hr_leave';
    public const SOURCE_PERMISSION = 'hr_permission';
    public const SOURCE_REQUEST = 'hr_request';

    /** @var list<string> */
    public const SUPPORTED_SOURCES = [
        self::SOURCE_LEAVE,
        self::SOURCE_PERMISSION,
        self::SOURCE_REQUEST,
    ];

    public const OUTCOME_PASSTHROUGH = 'passthrough';
    public const OUTCOME_STAGE_ADVANCED = 'stage_advanced';
    public const OUTCOME_FINALIZE = 'finalize';

    /**
     * Gate an Oversight approve/reject for HR sources.
     *
     * @return self::OUTCOME_* passthrough|stage_advanced|finalize
     *   - passthrough / finalize → caller MUST run existing domain approve/reject
     *   - stage_advanced → domain stays pending; do NOT finalize
     */
    public function gateOversightDecision(
        string $sourceKey,
        int $recordId,
        int $companyId,
        string $action,
        int $actorUserId
    ): string {
        $action = $action === 'reject' ? 'reject' : 'approve';
        if (!in_array($sourceKey, self::SUPPORTED_SOURCES, true) || $recordId < 1 || $companyId < 1) {
            return self::OUTCOME_PASSTHROUGH;
        }
        if (!$this->schemaReady()) {
            return self::OUTCOME_PASSTHROUGH;
        }

        $requestType = $this->resolveRequestType($sourceKey, $recordId, $companyId);
        $matrix = $this->resolveMatrix($companyId, $sourceKey, $requestType);
        if ($matrix === null) {
            return self::OUTCOME_PASSTHROUGH;
        }

        $stages = $this->loadEnabledStages((int) $matrix['id'], $companyId);
        if ($stages === []) {
            return self::OUTCOME_PASSTHROUGH;
        }

        if ($action === 'reject') {
            $this->markProgressRejected($companyId, $sourceKey, $recordId, $actorUserId, $matrix, $stages, $requestType);
            return self::OUTCOME_FINALIZE;
        }

        $progress = $this->getOrStartProgress($companyId, $sourceKey, $recordId, $requestType, $matrix, $stages);
        $snapshot = $this->decodeSnapshot((string) ($progress['stages_snapshot_json'] ?? '[]'));
        if ($snapshot === []) {
            return self::OUTCOME_PASSTHROUGH;
        }

        $currentOrder = (int) ($progress['current_stage_order'] ?? 1);
        $currentStage = $this->stageByOrder($snapshot, $currentOrder);
        if ($currentStage === null) {
            return self::OUTCOME_PASSTHROUGH;
        }

        if (!$this->actorMayAct($currentStage, $actorUserId, $companyId)) {
            throw new \RuntimeException(__('access_denied'));
        }

        $maxOrder = $this->maxStageOrder($snapshot);
        if ($currentOrder < $maxOrder) {
            $this->advanceStage((int) $progress['id'], $companyId, $currentOrder + 1, $actorUserId);
            return self::OUTCOME_STAGE_ADVANCED;
        }

        $this->completeProgress((int) $progress['id'], $companyId, $actorUserId);
        return self::OUTCOME_FINALIZE;
    }

    /**
     * Clear in-progress governance when Oversight undoes a domain decision.
     */
    public function resetProgress(string $sourceKey, int $recordId, int $companyId): void
    {
        if (!in_array($sourceKey, self::SUPPORTED_SOURCES, true) || !$this->schemaReady()) {
            return;
        }
        if ($recordId < 1 || $companyId < 1) {
            return;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'DELETE FROM rateb_hr_approval_progress
             WHERE company_id = :cid AND source_key = :sk AND record_id = :rid'
        );
        $stmt->execute(['cid' => $companyId, 'sk' => $sourceKey, 'rid' => $recordId]);
    }

    /**
     * @return array{current_stage_order:int,max_stage_order:int,stage_name:string,matrix_version:int}|null
     */
    public function progressSummary(string $sourceKey, int $recordId, int $companyId): ?array
    {
        if (!$this->schemaReady() || $companyId < 1 || $recordId < 1) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT current_stage_order, matrix_version, stages_snapshot_json, status
             FROM rateb_hr_approval_progress
             WHERE company_id = :cid AND source_key = :sk AND record_id = :rid
             LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sk' => $sourceKey, 'rid' => $recordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (string) ($row['status'] ?? '') !== 'in_progress') {
            return null;
        }
        $snapshot = $this->decodeSnapshot((string) ($row['stages_snapshot_json'] ?? '[]'));
        $order = (int) ($row['current_stage_order'] ?? 1);
        $stage = $this->stageByOrder($snapshot, $order);
        return [
            'current_stage_order' => $order,
            'max_stage_order' => $this->maxStageOrder($snapshot),
            'stage_name' => (string) ($stage['name'] ?? ('stage_' . $order)),
            'matrix_version' => (int) ($row['matrix_version'] ?? 0),
        ];
    }

    /**
     * Upsert matrix + replace stages (bumps version when an existing matrix is updated).
     *
     * @param list<array{stage_order:int,code:string,name:string,approver_type?:string,approver_reference?:?string}> $stages
     */
    public function saveMatrix(
        int $companyId,
        string $sourceKey,
        string $requestType,
        string $name,
        array $stages,
        ?int $actorUserId = null
    ): int {
        if ($companyId < 1 || !in_array($sourceKey, self::SUPPORTED_SOURCES, true)) {
            throw new \RuntimeException(__('invalid_request'));
        }
        if (!$this->schemaReady()) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        $requestType = trim($requestType);
        $normalizedStages = $this->normalizeStageInput($stages);
        if ($normalizedStages === []) {
            throw new \RuntimeException(__('invalid_request'));
        }

        $db = Database::connection();
        $existing = $this->findMatrixRow($companyId, $sourceKey, $requestType);
        if ($existing) {
            $matrixId = (int) $existing['id'];
            $newVersion = (int) ($existing['version'] ?? 1) + 1;
            $stmt = $db->prepare(
                'UPDATE rateb_hr_approval_matrices
                 SET name = :name, enabled = 1, version = :ver, updated_by = :uid
                 WHERE id = :id AND company_id = :cid'
            );
            $stmt->execute([
                'name' => $name !== '' ? $name : null,
                'ver' => $newVersion,
                'uid' => $actorUserId,
                'id' => $matrixId,
                'cid' => $companyId,
            ]);
            $db->prepare(
                'DELETE FROM rateb_hr_approval_matrix_stages WHERE matrix_id = :mid AND company_id = :cid'
            )->execute(['mid' => $matrixId, 'cid' => $companyId]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO rateb_hr_approval_matrices
                    (company_id, source_key, request_type, name, enabled, version, created_by, updated_by)
                 VALUES (:cid, :sk, :rt, :name, 1, 1, :uid, :uid)'
            );
            $stmt->execute([
                'cid' => $companyId,
                'sk' => $sourceKey,
                'rt' => $requestType,
                'name' => $name !== '' ? $name : null,
                'uid' => $actorUserId,
            ]);
            $matrixId = (int) $db->lastInsertId();
        }

        $ins = $db->prepare(
            'INSERT INTO rateb_hr_approval_matrix_stages
                (company_id, matrix_id, stage_order, code, name, approver_type, approver_reference, enabled)
             VALUES (:cid, :mid, :ord, :code, :name, :atype, :aref, 1)'
        );
        foreach ($normalizedStages as $stage) {
            $ins->execute([
                'cid' => $companyId,
                'mid' => $matrixId,
                'ord' => $stage['stage_order'],
                'code' => $stage['code'],
                'name' => $stage['name'],
                'atype' => $stage['approver_type'],
                'aref' => $stage['approver_reference'],
            ]);
        }

        return $matrixId;
    }

    public function schemaReady(): bool
    {
        try {
            return Database::tableExists('rateb_hr_approval_matrices')
                && Database::tableExists('rateb_hr_approval_matrix_stages')
                && Database::tableExists('rateb_hr_approval_progress');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    private function resolveMatrix(int $companyId, string $sourceKey, string $requestType): ?array
    {
        $exact = $this->findMatrixRow($companyId, $sourceKey, $requestType);
        if ($exact && (int) ($exact['enabled'] ?? 0) === 1) {
            return $exact;
        }
        if ($requestType !== '') {
            $fallback = $this->findMatrixRow($companyId, $sourceKey, '');
            if ($fallback && (int) ($fallback['enabled'] ?? 0) === 1) {
                return $fallback;
            }
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    private function findMatrixRow(int $companyId, string $sourceKey, string $requestType): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_hr_approval_matrices
             WHERE company_id = :cid AND source_key = :sk AND request_type = :rt
             LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sk' => $sourceKey, 'rt' => $requestType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadEnabledStages(int $matrixId, int $companyId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT stage_order, code, name, approver_type, approver_reference
             FROM rateb_hr_approval_matrix_stages
             WHERE matrix_id = :mid AND company_id = :cid AND enabled = 1
             ORDER BY stage_order ASC, id ASC'
        );
        $stmt->execute(['mid' => $matrixId, 'cid' => $companyId]);
        return array_values($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function resolveRequestType(string $sourceKey, int $recordId, int $companyId): string
    {
        if ($sourceKey !== self::SOURCE_REQUEST) {
            return '';
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT request_type FROM rateb_hr_employee_requests
             WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    /**
     * @param array<string, mixed> $matrix
     * @param list<array<string, mixed>> $stages
     * @return array<string, mixed>
     */
    private function getOrStartProgress(
        int $companyId,
        string $sourceKey,
        int $recordId,
        string $requestType,
        array $matrix,
        array $stages
    ): array {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_hr_approval_progress
             WHERE company_id = :cid AND source_key = :sk AND record_id = :rid
             LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sk' => $sourceKey, 'rid' => $recordId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $status = (string) ($existing['status'] ?? '');
            if ($status === 'completed' || $status === 'rejected') {
                throw new \RuntimeException(__('leave_not_pending'));
            }
            return $existing;
        }

        $snapshot = [];
        foreach ($stages as $stage) {
            $snapshot[] = [
                'stage_order' => (int) ($stage['stage_order'] ?? 0),
                'code' => (string) ($stage['code'] ?? ''),
                'name' => (string) ($stage['name'] ?? ''),
                'approver_type' => (string) ($stage['approver_type'] ?? 'oversight'),
                'approver_reference' => $stage['approver_reference'] !== null
                    ? (string) $stage['approver_reference']
                    : null,
            ];
        }
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException(__('system_error_generic'));
        }

        $ins = $db->prepare(
            'INSERT INTO rateb_hr_approval_progress
                (company_id, matrix_id, matrix_version, source_key, record_id, request_type,
                 current_stage_order, status, stages_snapshot_json, version)
             VALUES
                (:cid, :mid, :mver, :sk, :rid, :rt, 1, \'in_progress\', :snap, 1)'
        );
        $ins->execute([
            'cid' => $companyId,
            'mid' => (int) $matrix['id'],
            'mver' => (int) ($matrix['version'] ?? 1),
            'sk' => $sourceKey,
            'rid' => $recordId,
            'rt' => $requestType,
            'snap' => $json,
        ]);

        $stmt->execute(['cid' => $companyId, 'sk' => $sourceKey, 'rid' => $recordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException(__('system_error_generic'));
        }
        return $row;
    }

    /**
     * @param array<string, mixed> $matrix
     * @param list<array<string, mixed>> $stages
     */
    private function markProgressRejected(
        int $companyId,
        string $sourceKey,
        int $recordId,
        int $actorUserId,
        array $matrix,
        array $stages,
        string $requestType
    ): void {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_hr_approval_progress
             WHERE company_id = :cid AND source_key = :sk AND record_id = :rid LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sk' => $sourceKey, 'rid' => $recordId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $db->prepare(
                'UPDATE rateb_hr_approval_progress
                 SET status = \'rejected\', last_actor_user_id = :uid, last_action_at = NOW(), version = version + 1
                 WHERE id = :id AND company_id = :cid'
            )->execute([
                'uid' => $actorUserId > 0 ? $actorUserId : null,
                'id' => (int) $existing['id'],
                'cid' => $companyId,
            ]);
            return;
        }
        $this->getOrStartProgress($companyId, $sourceKey, $recordId, $requestType, $matrix, $stages);
        $stmt->execute(['cid' => $companyId, 'sk' => $sourceKey, 'rid' => $recordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $db->prepare(
                'UPDATE rateb_hr_approval_progress
                 SET status = \'rejected\', last_actor_user_id = :uid, last_action_at = NOW(), version = version + 1
                 WHERE id = :id AND company_id = :cid'
            )->execute([
                'uid' => $actorUserId > 0 ? $actorUserId : null,
                'id' => (int) $row['id'],
                'cid' => $companyId,
            ]);
        }
    }

    private function advanceStage(int $progressId, int $companyId, int $nextOrder, int $actorUserId): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE rateb_hr_approval_progress
             SET current_stage_order = :ord, last_actor_user_id = :uid, last_action_at = NOW(), version = version + 1
             WHERE id = :id AND company_id = :cid AND status = \'in_progress\''
        );
        $stmt->execute([
            'ord' => $nextOrder,
            'uid' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $progressId,
            'cid' => $companyId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('leave_not_pending'));
        }
    }

    private function completeProgress(int $progressId, int $companyId, int $actorUserId): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE rateb_hr_approval_progress
             SET status = \'completed\', last_actor_user_id = :uid, last_action_at = NOW(), version = version + 1
             WHERE id = :id AND company_id = :cid AND status = \'in_progress\''
        );
        $stmt->execute([
            'uid' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $progressId,
            'cid' => $companyId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('leave_not_pending'));
        }
    }

    /**
     * @param array<string, mixed> $stage
     */
    private function actorMayAct(array $stage, int $actorUserId, int $companyId): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        $type = (string) ($stage['approver_type'] ?? 'oversight');
        if ($type === 'oversight') {
            return true;
        }
        if ($actorUserId < 1) {
            return false;
        }
        $ref = trim((string) ($stage['approver_reference'] ?? ''));
        if ($ref === '') {
            return false;
        }
        if ($type === 'user') {
            return (int) $ref === $actorUserId;
        }
        if ($type === 'role') {
            $roleId = (int) $ref;
            if ($roleId < 1) {
                return false;
            }
            return in_array($roleId, (new AuthorizationService())->getUserRoleIds($actorUserId), true);
        }
        return false;
    }

    /**
     * @return list<array{stage_order:int,code:string,name:string,approver_type:string,approver_reference:?string}>
     */
    private function decodeSnapshot(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'stage_order' => (int) ($row['stage_order'] ?? 0),
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'approver_type' => (string) ($row['approver_type'] ?? 'oversight'),
                'approver_reference' => isset($row['approver_reference']) && $row['approver_reference'] !== null && $row['approver_reference'] !== ''
                    ? (string) $row['approver_reference']
                    : null,
            ];
        }
        usort($out, static fn ($a, $b) => $a['stage_order'] <=> $b['stage_order']);
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $snapshot
     * @return array<string, mixed>|null
     */
    private function stageByOrder(array $snapshot, int $order): ?array
    {
        foreach ($snapshot as $stage) {
            if ((int) ($stage['stage_order'] ?? 0) === $order) {
                return $stage;
            }
        }
        return null;
    }

    /** @param list<array<string, mixed>> $snapshot */
    private function maxStageOrder(array $snapshot): int
    {
        $max = 0;
        foreach ($snapshot as $stage) {
            $max = max($max, (int) ($stage['stage_order'] ?? 0));
        }
        return $max;
    }

    /**
     * @param list<array{stage_order?:int,code?:string,name?:string,approver_type?:string,approver_reference?:?string}> $stages
     * @return list<array{stage_order:int,code:string,name:string,approver_type:string,approver_reference:?string}>
     */
    private function normalizeStageInput(array $stages): array
    {
        $out = [];
        $orders = [];
        foreach ($stages as $stage) {
            $order = (int) ($stage['stage_order'] ?? 0);
            $code = trim((string) ($stage['code'] ?? ''));
            $name = trim((string) ($stage['name'] ?? ''));
            if ($order < 1 || $code === '' || $name === '') {
                continue;
            }
            if (isset($orders[$order])) {
                continue;
            }
            $atype = strtolower(trim((string) ($stage['approver_type'] ?? 'oversight')));
            if (!in_array($atype, ['oversight', 'user', 'role'], true)) {
                $atype = 'oversight';
            }
            $aref = $stage['approver_reference'] ?? null;
            $aref = $aref !== null && trim((string) $aref) !== '' ? trim((string) $aref) : null;
            if ($atype === 'oversight') {
                $aref = null;
            }
            $orders[$order] = true;
            $out[] = [
                'stage_order' => $order,
                'code' => $code,
                'name' => $name,
                'approver_type' => $atype,
                'approver_reference' => $aref,
            ];
        }
        usort($out, static fn ($a, $b) => $a['stage_order'] <=> $b['stage_order']);
        return $out;
    }
}
