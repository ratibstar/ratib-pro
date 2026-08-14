<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Employee;
use PDO;

/**
 * Phase P — Saudi HR foundation (GOSI / WPS / employment fields) ONLY.
 *
 * Stores local foundation fields + audit rows.
 * NEVER transmits data externally. external_sent is always forced to 0.
 */
final class HrSaudiComplianceFoundationService
{
    public const CHANNELS = ['gosi', 'wps', 'other'];

    public function schemaReady(): bool
    {
        try {
            return Database::tableExists('rateb_hr_saudi_employment_fields')
                && Database::tableExists('rateb_hr_saudi_integration_audit');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Audit snapshot for certification / ops readiness (no connectors).
     *
     * @return array<string,mixed>
     */
    public function foundationAudit(int $companyId): array
    {
        return [
            'schema_ready' => $this->schemaReady(),
            'company_id' => $companyId,
            'channels' => [
                'gosi' => [
                    'status' => 'foundation_only',
                    'external_send_enabled' => false,
                    'connector' => null,
                ],
                'wps' => [
                    'status' => 'foundation_only',
                    'external_send_enabled' => false,
                    'connector' => null,
                    'bank_transfer' => false,
                ],
            ],
            'employment_fields_table' => 'rateb_hr_saudi_employment_fields',
            'audit_table' => 'rateb_hr_saudi_integration_audit',
            'policy' => 'No external GOSI/WPS transmission without separate approval.',
            'fields_with_data' => $companyId > 0 && $this->schemaReady()
                ? $this->countFields($companyId)
                : 0,
            'audit_rows' => $companyId > 0 && $this->schemaReady()
                ? $this->countAudit($companyId)
                : 0,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getEmployeeFields(int $companyId, int $employeeId): ?array
    {
        if (!$this->schemaReady() || $companyId < 1 || $employeeId < 1) {
            return null;
        }
        $emp = (new Employee())->queryOne(
            'SELECT id FROM rateb_employees WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $employeeId, 'cid' => $companyId]
        );
        if (!$emp) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_hr_saudi_employment_fields
             WHERE company_id = :cid AND employee_id = :eid LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'eid' => $employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Local upsert only — never sends externally.
     *
     * @param array<string,mixed> $input
     * @return array{id:int,external_sent:int}
     */
    public function upsertEmployeeFields(int $companyId, int $employeeId, array $input, int $actorUserId = 0): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        $emp = (new Employee())->queryOne(
            'SELECT id FROM rateb_employees WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $employeeId, 'cid' => $companyId]
        );
        if (!$emp) {
            throw new \RuntimeException(__('access_denied'));
        }

        $fields = [
            'gosi_number' => $this->clip($input['gosi_number'] ?? null, 64),
            'gosi_subscription_status' => $this->clip($input['gosi_subscription_status'] ?? null, 32),
            'wps_iban' => $this->clip($input['wps_iban'] ?? null, 64),
            'wps_bank_code' => $this->clip($input['wps_bank_code'] ?? null, 32),
            'nationality_code' => $this->clip($input['nationality_code'] ?? null, 8),
            'iqama_number' => $this->clip($input['iqama_number'] ?? null, 64),
            'iqama_expiry' => $this->normalizeDate($input['iqama_expiry'] ?? null),
            'mol_contract_number' => $this->clip($input['mol_contract_number'] ?? null, 64),
            'saudi_notes' => $this->clip($input['saudi_notes'] ?? null, 2000),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $existing = $this->getEmployeeFields($companyId, $employeeId);
        $db = Database::connection();
        if ($existing) {
            $sql = 'UPDATE rateb_hr_saudi_employment_fields SET
                gosi_number = :gosi_number,
                gosi_subscription_status = :gosi_subscription_status,
                wps_iban = :wps_iban,
                wps_bank_code = :wps_bank_code,
                nationality_code = :nationality_code,
                iqama_number = :iqama_number,
                iqama_expiry = :iqama_expiry,
                mol_contract_number = :mol_contract_number,
                saudi_notes = :saudi_notes,
                updated_at = :updated_at
             WHERE company_id = :company_id AND employee_id = :employee_id';
            $fields['company_id'] = $companyId;
            $fields['employee_id'] = $employeeId;
            $db->prepare($sql)->execute($fields);
            $id = (int) ($existing['id'] ?? 0);
        } else {
            $fields['company_id'] = $companyId;
            $fields['employee_id'] = $employeeId;
            $fields['created_at'] = date('Y-m-d H:i:s');
            $cols = array_keys($fields);
            $sql = 'INSERT INTO rateb_hr_saudi_employment_fields (' . implode(',', $cols) . ')
                    VALUES (' . implode(',', array_map(static fn ($c) => ':' . $c, $cols)) . ')';
            $db->prepare($sql)->execute($fields);
            $id = (int) $db->lastInsertId();
        }

        $this->writeAudit($companyId, 'other', 'upsert_employment_fields', 'local_saved', 'employee:' . $employeeId, $actorUserId);

        return ['id' => $id, 'external_sent' => 0];
    }

    /**
     * Planned audit row — external_sent forced false.
     */
    public function writeAudit(
        int $companyId,
        string $channel,
        string $action,
        string $status,
        ?string $payloadSummary = null,
        int $actorUserId = 0
    ): int {
        if (!$this->schemaReady() || $companyId < 1) {
            return 0;
        }
        if (!in_array($channel, self::CHANNELS, true)) {
            $channel = 'other';
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO rateb_hr_saudi_integration_audit
             (company_id, channel, action, status, payload_summary, external_sent, created_by, created_at)
             VALUES (:cid, :ch, :act, :st, :ps, 0, :uid, :ca)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'ch' => $channel,
            'act' => mb_substr($action, 0, 64),
            'st' => mb_substr($status, 0, 32),
            'ps' => $payloadSummary !== null ? mb_substr($payloadSummary, 0, 500) : null,
            'uid' => $actorUserId > 0 ? $actorUserId : null,
            'ca' => date('Y-m-d H:i:s'),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    private function countFields(int $companyId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_hr_saudi_employment_fields WHERE company_id = :cid'
        );
        $stmt->execute(['cid' => $companyId]);

        return (int) $stmt->fetchColumn();
    }

    private function countAudit(int $companyId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_hr_saudi_integration_audit WHERE company_id = :cid'
        );
        $stmt->execute(['cid' => $companyId]);

        return (int) $stmt->fetchColumn();
    }

    private function clip(mixed $v, int $max): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }

        return mb_substr($s, 0, $max);
    }

    private function normalizeDate(mixed $raw): ?string
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $s);

        return ($dt && $dt->format('Y-m-d') === $s) ? $s : null;
    }
}
