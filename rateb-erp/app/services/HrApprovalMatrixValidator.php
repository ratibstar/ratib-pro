<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;
use Rateb\App\Models\User;

/**
 * Phase H — configuration validation for HR approval matrices.
 * Does not approve anything; does not mutate domain tables.
 */
final class HrApprovalMatrixValidator
{
    /** @var list<string> */
    public const ALLOWED_APPROVER_TYPES = ['oversight', 'user', 'role'];

    /** @var list<string> */
    public const REQUEST_TYPES = [
        'salary_certificate',
        'end_of_service',
        'experience_letter',
        'other',
        'inquiry',
        'complaint',
    ];

    /**
     * @param list<array<string, mixed>> $stages
     * @return array{ok:bool,errors:list<string>,warnings:list<string>,stages:list<array<string,mixed>>}
     */
    public function validate(
        int $companyId,
        string $sourceKey,
        string $requestType,
        string $name,
        array $stages,
        bool $forActivation = true
    ): array {
        $errors = [];
        $warnings = [];

        if ($companyId < 1) {
            $errors[] = 'invalid_company_id';
        } elseif (!$this->companyExists($companyId)) {
            $errors[] = 'company_not_found';
        }

        if (!in_array($sourceKey, HrApprovalMatrixService::SUPPORTED_SOURCES, true)) {
            $errors[] = 'invalid_source_key';
        }

        $requestType = trim($requestType);
        if ($sourceKey === HrApprovalMatrixService::SOURCE_LEAVE
            || $sourceKey === HrApprovalMatrixService::SOURCE_PERMISSION
        ) {
            if ($requestType !== '') {
                $errors[] = 'request_type_not_allowed_for_source';
            }
        } elseif ($sourceKey === HrApprovalMatrixService::SOURCE_REQUEST && $requestType !== '') {
            if (!in_array($requestType, self::REQUEST_TYPES, true)) {
                $errors[] = 'invalid_request_type';
            }
        }

        if (trim($name) === '') {
            $errors[] = 'name_required';
        }

        $normalized = $this->normalizeStagesStrict($stages, $errors);
        if ($normalized === []) {
            $errors[] = 'stages_required';
        } else {
            $this->validateStageOrders($normalized, $errors);
            $this->validateApprovers($normalized, $companyId, $errors, $warnings);
            $this->warnDuplicateActors($normalized, $warnings);
        }

        if ($forActivation && $normalized !== [] && !$this->hasReachableFinalStage($normalized)) {
            $errors[] = 'unreachable_final_stage';
        }

        $errors = array_values(array_unique($errors));
        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => array_values(array_unique($warnings)),
            'stages' => $normalized,
        ];
    }

    /**
     * @param list<array<string, mixed>> $stages
     * @param list<string> $errors
     * @return list<array{stage_order:int,code:string,name:string,approver_type:string,approver_reference:?string,enabled:bool}>
     */
    private function normalizeStagesStrict(array $stages, array &$errors): array
    {
        $out = [];
        $seenOrders = [];
        $seenCodes = [];
        foreach ($stages as $idx => $stage) {
            if (!is_array($stage)) {
                $errors[] = 'invalid_stage_payload';
                continue;
            }
            $order = (int) ($stage['stage_order'] ?? 0);
            $code = trim((string) ($stage['code'] ?? ''));
            $name = trim((string) ($stage['name'] ?? ''));
            $atype = strtolower(trim((string) ($stage['approver_type'] ?? '')));
            $aref = $stage['approver_reference'] ?? null;
            $aref = $aref !== null && trim((string) $aref) !== '' ? trim((string) $aref) : null;
            $enabled = array_key_exists('enabled', $stage) ? (bool) $stage['enabled'] : true;

            if ($order < 1) {
                $errors[] = 'stage_order_invalid';
            }
            if ($code === '') {
                $errors[] = 'stage_code_required';
            }
            if ($name === '') {
                $errors[] = 'stage_name_required';
            }
            if ($atype === '') {
                $errors[] = 'approver_type_required';
            } elseif (!in_array($atype, self::ALLOWED_APPROVER_TYPES, true)) {
                $errors[] = 'approver_type_rejected:' . $atype;
            }
            if (isset($seenOrders[$order])) {
                $errors[] = 'stage_order_duplicate';
            }
            if ($code !== '' && isset($seenCodes[$code])) {
                $errors[] = 'stage_code_duplicate';
            }
            if (!$enabled) {
                $errors[] = 'disabled_stage_not_allowed_in_matrix';
            }

            if ($order >= 1 && $code !== '' && $name !== '' && in_array($atype, self::ALLOWED_APPROVER_TYPES, true)) {
                $seenOrders[$order] = true;
                $seenCodes[$code] = true;
                $out[] = [
                    'stage_order' => $order,
                    'code' => $code,
                    'name' => $name,
                    'approver_type' => $atype,
                    'approver_reference' => $atype === 'oversight' ? null : $aref,
                    'enabled' => true,
                ];
            }
        }
        usort($out, static fn ($a, $b) => $a['stage_order'] <=> $b['stage_order']);
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $stages
     * @param list<string> $errors
     */
    private function validateStageOrders(array $stages, array &$errors): void
    {
        $n = count($stages);
        for ($i = 0; $i < $n; $i++) {
            $expected = $i + 1;
            if ((int) $stages[$i]['stage_order'] !== $expected) {
                $errors[] = 'stage_order_gap_or_non_contiguous';
                return;
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $stages
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    private function validateApprovers(array $stages, int $companyId, array &$errors, array &$warnings): void
    {
        foreach ($stages as $stage) {
            $type = (string) ($stage['approver_type'] ?? '');
            $ref = $stage['approver_reference'] ?? null;
            if ($type === 'oversight') {
                if ($ref !== null && $ref !== '') {
                    $warnings[] = 'oversight_ignores_approver_reference';
                }
                continue;
            }
            if ($ref === null || $ref === '') {
                $errors[] = 'approver_reference_required:' . $type;
                continue;
            }
            if ($type === 'user') {
                $this->validateUserApprover((int) $ref, $companyId, $errors);
            } elseif ($type === 'role') {
                $this->validateRoleApprover((int) $ref, $companyId, $errors);
            }
        }
    }

    /** @param list<string> $errors */
    private function validateUserApprover(int $userId, int $companyId, array &$errors): void
    {
        if ($userId < 1) {
            $errors[] = 'invalid_user_approver';
            return;
        }
        $user = (new User())->find($userId);
        if (!$user) {
            $errors[] = 'user_approver_not_found';
            return;
        }
        if ((int) ($user['company_id'] ?? 0) !== $companyId) {
            $errors[] = 'user_approver_cross_company';
            return;
        }
        if ((string) ($user['status'] ?? '') !== 'active') {
            $errors[] = 'user_approver_inactive';
            return;
        }
        if (!empty($user['is_super_admin'])) {
            $errors[] = 'user_approver_must_not_be_super_admin_use_oversight';
        }
    }

    /** @param list<string> $errors */
    private function validateRoleApprover(int $roleId, int $companyId, array &$errors): void
    {
        if ($roleId < 1) {
            $errors[] = 'invalid_role_approver';
            return;
        }
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, company_id FROM rateb_roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $roleId]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$role) {
            $errors[] = 'role_approver_not_found';
            return;
        }
        $roleCid = $role['company_id'] ?? null;
        if ($roleCid === null || (int) $roleCid < 1) {
            // Platform-global roles are not company-safe stage actors for HR matrices.
            $errors[] = 'role_approver_not_company_scoped';
            return;
        }
        if ((int) $roleCid !== $companyId) {
            $errors[] = 'role_approver_cross_company';
        }
    }

    /**
     * @param list<array<string, mixed>> $stages
     * @param list<string> $warnings
     */
    private function warnDuplicateActors(array $stages, array &$warnings): void
    {
        $seen = [];
        foreach ($stages as $stage) {
            $type = (string) ($stage['approver_type'] ?? '');
            if ($type === 'oversight') {
                $key = 'oversight';
            } else {
                $key = $type . ':' . (string) ($stage['approver_reference'] ?? '');
            }
            if (isset($seen[$key])) {
                $warnings[] = 'duplicate_approver_across_stages:' . $key;
            }
            $seen[$key] = true;
        }
    }

    /** @param list<array<string, mixed>> $stages */
    private function hasReachableFinalStage(array $stages): bool
    {
        return $stages !== [] && (int) $stages[count($stages) - 1]['stage_order'] === count($stages);
    }

    private function companyExists(int $companyId): bool
    {
        try {
            $db = Database::connection();
            $stmt = $db->prepare('SELECT id FROM rateb_companies WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $companyId]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
