<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;

/**
 * Phase G/H — HR approval matrix governance overlay (not an approval engine).
 *
 * - No matrix / tables missing / disabled → passthrough (exact pre-G Oversight behavior).
 * - Matrix present → stage progression; domain finalize only on final stage.
 * - Progress binds matrix_id + matrix_version + stages_snapshot_json (versioning).
 * - Phase H: validated draft/activate/deactivate; no silent approver coercion.
 * - Does NOT UPDATE domain status directly; callers invoke existing finalizers.
 * - Does NOT use EAP or Legacy WorkflowService.
 */
final class HrApprovalMatrixService
{
    public const SOURCE_LEAVE = 'hr_leave';
    public const SOURCE_PERMISSION = 'hr_permission';
    public const SOURCE_REQUEST = 'hr_request';
    public const SOURCE_DECISION = 'hr_decision';

    /** @var list<string> */
    public const SUPPORTED_SOURCES = [
        self::SOURCE_LEAVE,
        self::SOURCE_PERMISSION,
        self::SOURCE_REQUEST,
        self::SOURCE_DECISION,
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
            // Phase J: reject must also be stage-authorized (not open to any caller).
            $progress = $this->getOrStartProgress($companyId, $sourceKey, $recordId, $requestType, $matrix, $stages);
            $snapshot = $this->decodeSnapshot((string) ($progress['stages_snapshot_json'] ?? '[]'));
            $currentOrder = (int) ($progress['current_stage_order'] ?? 1);
            $currentStage = $this->stageByOrder($snapshot, $currentOrder) ?? ($stages[0] ?? null);
            if ($currentStage !== null) {
                if (!$this->actorMayAct($currentStage, $actorUserId, $companyId)) {
                    throw new \RuntimeException(__('access_denied'));
                }
                if ($this->isSelfApprovalBlocked($sourceKey, $recordId, $companyId, $currentStage, $actorUserId)) {
                    throw new \RuntimeException(__('access_denied'));
                }
            }
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
        if ($this->isSelfApprovalBlocked($sourceKey, $recordId, $companyId, $currentStage, $actorUserId)) {
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
     * Upsert matrix + replace stages.
     * Phase H: always validates; default persists as DRAFT (enabled=0) unless $activate=true.
     * In-flight progress keeps frozen snapshot when matrix version bumps.
     *
     * @param list<array{stage_order:int,code:string,name:string,approver_type?:string,approver_reference?:?string}> $stages
     * @return array{matrix_id:int,enabled:int,version:int,warnings:list<string>}
     */
    public function saveMatrix(
        int $companyId,
        string $sourceKey,
        string $requestType,
        string $name,
        array $stages,
        ?int $actorUserId = null,
        bool $activate = false
    ): array {
        if (!$this->schemaReady()) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        $requestType = trim($requestType);
        $validation = (new HrApprovalMatrixValidator())->validate(
            $companyId,
            $sourceKey,
            $requestType,
            $name,
            $stages,
            $activate
        );
        if (!$validation['ok']) {
            throw new \RuntimeException('matrix_validation_failed:' . implode(',', $validation['errors']));
        }
        /** @var list<array{stage_order:int,code:string,name:string,approver_type:string,approver_reference:?string}> $normalizedStages */
        $normalizedStages = $validation['stages'];
        $enabled = $activate ? 1 : 0;

        $db = Database::connection();
        $existing = $this->findMatrixRow($companyId, $sourceKey, $requestType);
        if ($existing) {
            $matrixId = (int) $existing['id'];
            $newVersion = (int) ($existing['version'] ?? 1) + 1;
            $stmt = $db->prepare(
                'UPDATE rateb_hr_approval_matrices
                 SET name = :name, enabled = :en, version = :ver, updated_by = :uid
                 WHERE id = :id AND company_id = :cid'
            );
            $stmt->execute([
                'name' => trim($name),
                'en' => $enabled,
                'ver' => $newVersion,
                'uid' => $actorUserId,
                'id' => $matrixId,
                'cid' => $companyId,
            ]);
            $db->prepare(
                'DELETE FROM rateb_hr_approval_matrix_stages WHERE matrix_id = :mid AND company_id = :cid'
            )->execute(['mid' => $matrixId, 'cid' => $companyId]);
            $version = $newVersion;
        } else {
            $stmt = $db->prepare(
                'INSERT INTO rateb_hr_approval_matrices
                    (company_id, source_key, request_type, name, enabled, version, created_by, updated_by)
                 VALUES (:cid, :sk, :rt, :name, :en, 1, :uid, :uid)'
            );
            $stmt->execute([
                'cid' => $companyId,
                'sk' => $sourceKey,
                'rt' => $requestType,
                'name' => trim($name),
                'en' => $enabled,
                'uid' => $actorUserId,
            ]);
            $matrixId = (int) $db->lastInsertId();
            $version = 1;
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

        $this->auditConfig($activate ? 'hr_matrix_save_activate' : 'hr_matrix_save_draft', $matrixId, [
            'company_id' => $companyId,
            'source_key' => $sourceKey,
            'request_type' => $requestType,
            'enabled' => $enabled,
            'version' => $version,
            'stage_count' => count($normalizedStages),
            'warnings' => $validation['warnings'],
        ]);

        return [
            'matrix_id' => $matrixId,
            'enabled' => $enabled,
            'version' => $version,
            'warnings' => $validation['warnings'],
        ];
    }

    /**
     * Activate after validation (DRAFT → ACTIVE). Does not rewrite in-flight snapshots.
     *
     * @return array{matrix_id:int,enabled:int,version:int,warnings:list<string>}
     */
    public function activateMatrix(int $companyId, int $matrixId, ?int $actorUserId = null): array
    {
        $row = $this->requireCompanyMatrix($companyId, $matrixId);
        $stages = $this->loadEnabledStages($matrixId, $companyId);
        $validation = (new HrApprovalMatrixValidator())->validate(
            $companyId,
            (string) $row['source_key'],
            (string) ($row['request_type'] ?? ''),
            (string) ($row['name'] ?? ''),
            $stages,
            true
        );
        if (!$validation['ok']) {
            throw new \RuntimeException('matrix_validation_failed:' . implode(',', $validation['errors']));
        }

        $db = Database::connection();
        $newVersion = (int) ($row['version'] ?? 1);
        // Activation without stage rewrite does not bump version; stage rewrite uses saveMatrix.
        $stmt = $db->prepare(
            'UPDATE rateb_hr_approval_matrices
             SET enabled = 1, updated_by = :uid
             WHERE id = :id AND company_id = :cid'
        );
        $stmt->execute([
            'uid' => $actorUserId,
            'id' => $matrixId,
            'cid' => $companyId,
        ]);

        $this->auditConfig('hr_matrix_activate', $matrixId, [
            'company_id' => $companyId,
            'source_key' => (string) $row['source_key'],
            'request_type' => (string) ($row['request_type'] ?? ''),
            'version' => $newVersion,
            'warnings' => $validation['warnings'],
        ]);

        return [
            'matrix_id' => $matrixId,
            'enabled' => 1,
            'version' => $newVersion,
            'warnings' => $validation['warnings'],
        ];
    }

    /**
     * Safe rollback: disable matrix. In-flight progress keeps frozen snapshot path.
     */
    public function deactivateMatrix(int $companyId, int $matrixId, ?int $actorUserId = null): void
    {
        $row = $this->requireCompanyMatrix($companyId, $matrixId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE rateb_hr_approval_matrices
             SET enabled = 0, updated_by = :uid
             WHERE id = :id AND company_id = :cid'
        );
        $stmt->execute([
            'uid' => $actorUserId,
            'id' => $matrixId,
            'cid' => $companyId,
        ]);
        $this->auditConfig('hr_matrix_deactivate', $matrixId, [
            'company_id' => $companyId,
            'source_key' => (string) $row['source_key'],
            'request_type' => (string) ($row['request_type'] ?? ''),
            'version' => (int) ($row['version'] ?? 0),
            'note' => 'in_flight_progress_keeps_snapshot',
        ]);
    }

    /**
     * Validate without persisting.
     *
     * @param list<array<string, mixed>> $stages
     * @return array{ok:bool,errors:list<string>,warnings:list<string>,stages:list<array<string,mixed>>}
     */
    public function validateMatrixConfig(
        int $companyId,
        string $sourceKey,
        string $requestType,
        string $name,
        array $stages,
        bool $forActivation = true
    ): array {
        return (new HrApprovalMatrixValidator())->validate(
            $companyId,
            $sourceKey,
            trim($requestType),
            $name,
            $stages,
            $forActivation
        );
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

    /**
     * Specific request_type beats wildcard (empty). Only enabled matrices are selectable.
     *
     * @return array<string, mixed>|null
     */
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

    /** @return array<string, mixed> */
    private function requireCompanyMatrix(int $companyId, int $matrixId): array
    {
        if ($companyId < 1 || $matrixId < 1 || !$this->schemaReady()) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_hr_approval_matrices WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $matrixId, 'cid' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \RuntimeException(__('invalid_request'));
        }
        return $row;
    }

    /** @param array<string, mixed> $payload */
    private function auditConfig(string $action, int $matrixId, array $payload): void
    {
        try {
            (new AuditService())->log($action, 'hr_approval_matrix', $matrixId, $payload);
        } catch (\Throwable $e) {
            // Config audit must not block governance writes.
        }
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
        if ($sourceKey === self::SOURCE_REQUEST) {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT request_type FROM rateb_hr_employee_requests
                 WHERE id = :id AND company_id = :cid LIMIT 1'
            );
            $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
            return trim((string) ($stmt->fetchColumn() ?: ''));
        }
        if ($sourceKey === self::SOURCE_DECISION) {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT decision_type FROM rateb_hr_decisions
                 WHERE id = :id AND company_id = :cid LIMIT 1'
            );
            $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
            return trim((string) ($stmt->fetchColumn() ?: ''));
        }

        return '';
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
            // Phase J: oversight stage ≠ open to every company user.
            return $this->actorHasCompanyHrDecideAuthority($actorUserId);
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
        // Unknown types must never silently pass (Phase H).
        return false;
    }

    /**
     * Company-safe decide authority for oversight-type stages / no-matrix passthrough.
     * Platform SA always qualifies. Company actors need hr.manage or hr.oversight.
     */
    public function actorHasCompanyHrDecideAuthority(int $actorUserId = 0): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        if ($actorUserId < 1) {
            $actorUserId = (int) (\Rateb\App\Core\SessionManager::get('rateb_user_id') ?? 0);
        }
        if ($actorUserId < 1) {
            return false;
        }
        if (function_exists('rateb_can')) {
            if (rateb_can('hr.manage') || rateb_can('hr.oversight')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Phase J — whether actor may decide this HR item now (matrix-aware).
     * Does not invent manager hierarchy. Payroll is never matrix-gated here.
     */
    public function canActorDecide(
        string $sourceKey,
        int $recordId,
        int $companyId,
        int $actorUserId,
        string $action = 'approve'
    ): bool {
        $action = $action === 'reject' ? 'reject' : 'approve';
        if (!in_array($sourceKey, self::SUPPORTED_SOURCES, true) || $recordId < 1 || $companyId < 1) {
            return false;
        }
        if ($actorUserId < 1 && !(function_exists('rateb_is_super_admin') && rateb_is_super_admin())) {
            return false;
        }
        try {
            if (!$this->schemaReady()) {
                return $this->actorHasCompanyHrDecideAuthority($actorUserId);
            }
            $requestType = $this->resolveRequestType($sourceKey, $recordId, $companyId);
            $matrix = $this->resolveMatrix($companyId, $sourceKey, $requestType);
            if ($matrix === null) {
                return $this->actorHasCompanyHrDecideAuthority($actorUserId);
            }
            $stages = $this->loadEnabledStages((int) $matrix['id'], $companyId);
            if ($stages === []) {
                return $this->actorHasCompanyHrDecideAuthority($actorUserId);
            }
            $progress = $this->findProgressRow($companyId, $sourceKey, $recordId);
            $snapshot = $progress !== null
                ? $this->decodeSnapshot((string) ($progress['stages_snapshot_json'] ?? '[]'))
                : $this->stagesToSnapshot($stages);
            if ($snapshot === []) {
                return $this->actorHasCompanyHrDecideAuthority($actorUserId);
            }
            $order = (int) ($progress['current_stage_order'] ?? 1);
            $stage = $this->stageByOrder($snapshot, $order) ?? ($snapshot[0] ?? null);
            if ($stage === null) {
                return false;
            }
            if (!$this->actorMayAct($stage, $actorUserId, $companyId)) {
                return false;
            }
            if ($action === 'approve'
                && $this->isSelfApprovalBlocked($sourceKey, $recordId, $companyId, $stage, $actorUserId)
            ) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{
     *   has_matrix:bool,
     *   current_stage_order:?int,
     *   max_stage_order:?int,
     *   stage_name:?string,
     *   stage_code:?string,
     *   approver_type:?string,
     *   last_actor_user_id:?int,
     *   matrix_version:?int
     * }|null
     */
    public function decisionContext(string $sourceKey, int $recordId, int $companyId): ?array
    {
        if (!in_array($sourceKey, self::SUPPORTED_SOURCES, true) || $recordId < 1 || $companyId < 1) {
            return null;
        }
        if (!$this->schemaReady()) {
            return [
                'has_matrix' => false,
                'current_stage_order' => null,
                'max_stage_order' => null,
                'stage_name' => null,
                'stage_code' => null,
                'approver_type' => null,
                'approver_reference' => null,
                'last_actor_user_id' => null,
                'last_action_at' => null,
                'next_stage_name' => null,
                'next_outcome' => 'domain_finalize',
                'matrix_version' => null,
                'progress_status' => 'pending',
                'stages_history' => [],
            ];
        }
        $requestType = $this->resolveRequestType($sourceKey, $recordId, $companyId);
        $matrix = $this->resolveMatrix($companyId, $sourceKey, $requestType);
        if ($matrix === null) {
            return [
                'has_matrix' => false,
                'current_stage_order' => null,
                'max_stage_order' => null,
                'stage_name' => null,
                'stage_code' => null,
                'approver_type' => null,
                'approver_reference' => null,
                'last_actor_user_id' => null,
                'last_action_at' => null,
                'next_stage_name' => null,
                'next_outcome' => 'domain_finalize',
                'matrix_version' => null,
                'progress_status' => 'pending',
                'stages_history' => [],
            ];
        }
        $stages = $this->loadEnabledStages((int) $matrix['id'], $companyId);
        $progress = $this->findProgressRow($companyId, $sourceKey, $recordId);
        $snapshot = $progress !== null
            ? $this->decodeSnapshot((string) ($progress['stages_snapshot_json'] ?? '[]'))
            : $this->stagesToSnapshot($stages);
        $order = (int) ($progress['current_stage_order'] ?? 1);
        $stage = $this->stageByOrder($snapshot, $order);

        $max = $this->maxStageOrder($snapshot);
        $isFinal = $order >= $max;
        $nextStage = !$isFinal ? $this->stageByOrder($snapshot, $order + 1) : null;
        $progressStatus = (string) ($progress['status'] ?? 'in_progress');
        if ($progressStatus === '') {
            $progressStatus = 'in_progress';
        }
        $history = [];
        foreach ($snapshot as $s) {
            $ord = (int) ($s['stage_order'] ?? 0);
            if ($ord < 1) {
                continue;
            }
            $state = 'pending';
            if ($progressStatus === 'completed' || $progressStatus === 'rejected') {
                $state = $ord < $order || ($progressStatus === 'completed' && $ord <= $order) ? $progressStatus : 'pending';
                if ($progressStatus === 'rejected' && $ord === $order) {
                    $state = 'rejected';
                } elseif ($progressStatus === 'rejected' && $ord < $order) {
                    $state = 'done';
                } elseif ($progressStatus === 'completed') {
                    $state = 'done';
                }
            } elseif ($ord < $order) {
                $state = 'done';
            } elseif ($ord === $order) {
                $state = 'current';
            }
            $history[] = [
                'stage_order' => $ord,
                'code' => (string) ($s['code'] ?? ''),
                'name' => (string) ($s['name'] ?? ('stage_' . $ord)),
                'approver_type' => (string) ($s['approver_type'] ?? ''),
                'state' => $state,
            ];
        }

        return [
            'has_matrix' => true,
            'current_stage_order' => $order,
            'max_stage_order' => $max,
            'stage_name' => (string) ($stage['name'] ?? ('stage_' . $order)),
            'stage_code' => (string) ($stage['code'] ?? ''),
            'approver_type' => (string) ($stage['approver_type'] ?? 'oversight'),
            'approver_reference' => (string) ($stage['approver_reference'] ?? ''),
            'last_actor_user_id' => isset($progress['last_actor_user_id'])
                ? (int) $progress['last_actor_user_id']
                : null,
            'last_action_at' => isset($progress['last_action_at'])
                ? (string) $progress['last_action_at']
                : null,
            'next_stage_name' => $nextStage !== null
                ? (string) ($nextStage['name'] ?? ('stage_' . ($order + 1)))
                : null,
            'next_outcome' => $isFinal ? 'domain_finalize' : 'advance_stage',
            'matrix_version' => (int) ($progress['matrix_version'] ?? $matrix['version'] ?? 0),
            'progress_status' => $progressStatus,
            'stages_history' => $history,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findProgressRow(int $companyId, string $sourceKey, int $recordId): ?array
    {
        if ($companyId < 1 || $recordId < 1 || !$this->schemaReady()) {
            return null;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_hr_approval_progress
             WHERE company_id = :cid AND source_key = :sk AND record_id = :rid
             LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sk' => $sourceKey, 'rid' => $recordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param list<array<string, mixed>> $stages
     * @return list<array<string, mixed>>
     */
    private function stagesToSnapshot(array $stages): array
    {
        $out = [];
        foreach ($stages as $s) {
            $out[] = [
                'stage_order' => (int) ($s['stage_order'] ?? 0),
                'code' => (string) ($s['code'] ?? ''),
                'name' => (string) ($s['name'] ?? ''),
                'approver_type' => (string) ($s['approver_type'] ?? 'oversight'),
                'approver_reference' => $s['approver_reference'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Runtime self-approval guard: fixed user stage cannot be the request's employee user (non-SA).
     *
     * @param array<string, mixed> $stage
     */
    private function isSelfApprovalBlocked(
        string $sourceKey,
        int $recordId,
        int $companyId,
        array $stage,
        int $actorUserId
    ): bool {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return false;
        }
        if ((string) ($stage['approver_type'] ?? '') !== 'user' || $actorUserId < 1) {
            return false;
        }
        $requesterUserId = $this->resolveRequesterUserId($sourceKey, $recordId, $companyId);
        if ($requesterUserId < 1) {
            return false;
        }
        return $requesterUserId === $actorUserId;
    }

    private function resolveRequesterUserId(string $sourceKey, int $recordId, int $companyId): int
    {
        if ($sourceKey === self::SOURCE_DECISION) {
            try {
                $db = Database::connection();
                $stmt = $db->prepare(
                    'SELECT created_by FROM rateb_hr_decisions
                     WHERE id = :id AND company_id = :cid LIMIT 1'
                );
                $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
                return (int) ($stmt->fetchColumn() ?: 0);
            } catch (\Throwable $e) {
                return 0;
            }
        }
        $table = match ($sourceKey) {
            self::SOURCE_LEAVE => 'rateb_leave_requests',
            self::SOURCE_PERMISSION => 'rateb_hr_permission_requests',
            self::SOURCE_REQUEST => 'rateb_hr_employee_requests',
            default => '',
        };
        if ($table === '') {
            return 0;
        }
        try {
            $db = Database::connection();
            $sql = 'SELECT e.user_id
                    FROM ' . $table . ' t
                    INNER JOIN rateb_employees e ON e.id = t.employee_id AND e.company_id = t.company_id
                    WHERE t.id = :id AND t.company_id = :cid
                    LIMIT 1';
            $stmt = $db->prepare($sql);
            $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            return 0;
        }
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
}
