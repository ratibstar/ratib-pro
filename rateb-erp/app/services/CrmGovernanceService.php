<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmAutomationRule;
use Rateb\App\Models\CrmDataQualityIssue;
use Rateb\App\Models\CrmGovernanceSetting;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\Customer;

/** Phase 7 — CRM governance, data quality, health dashboard. */
final class CrmGovernanceService
{
    /**
     * @return array<string, mixed>
     */
    public function healthDashboard(bool $liveScan = false): array
    {
        // Phase 10: avoid full scan on every GET; use open-issue aggregates unless liveScan.
        if (!$liveScan) {
            $by = $this->issuesBySeverity();
            $open = $this->countOpenIssues();
            $scan = [
                'created' => 0,
                'duplicates' => (int) ($by['medium'] ?? 0),
                'missing' => (int) ($by['medium'] ?? 0) + (int) ($by['low'] ?? 0),
                'ownership' => (int) ($by['high'] ?? 0),
            ];

            return [
                'open_issues' => $open,
                'by_severity' => $by,
                'duplicate_candidates' => 0,
                'missing_fields' => 0,
                'ownership_gaps' => 0,
                'settings' => $this->listSettings(),
                'score' => max(0, 100 - min(100, $open * 2)),
                'source' => 'issue_counts',
            ];
        }
        $scan = $this->runDataQualityScan(false);

        return [
            'open_issues' => $scan['created'] + $this->countOpenIssues(),
            'by_severity' => $this->issuesBySeverity(),
            'duplicate_candidates' => $scan['duplicates'],
            'missing_fields' => $scan['missing'],
            'ownership_gaps' => $scan['ownership'],
            'settings' => $this->listSettings(),
            'score' => $this->governanceScore($scan),
            'source' => 'live_scan',
        ];
    }

    /**
     * @return array{created:int,duplicates:int,missing:int,ownership:int}
     */
    public function runDataQualityScan(bool $persist = true): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $required = $this->setting('required_fields', [
            'lead' => ['title', 'owner_user_id'],
            'opportunity' => ['name', 'owner_user_id', 'amount', 'stage_id'],
            'customer' => ['name'],
        ]);
        $created = 0;
        $missing = 0;
        $ownership = 0;
        $duplicates = 0;

        $leads = (new CrmLead())->query(
            'SELECT id, title, owner_user_id, email, phone FROM rateb_crm_leads
             WHERE company_id = :cid AND deleted_at IS NULL ORDER BY id DESC LIMIT 200',
            ['cid' => $companyId]
        );
        foreach (is_array($leads) ? $leads : [] as $lead) {
            foreach (($required['lead'] ?? []) as $field) {
                if ($this->isBlank($lead[$field] ?? null)) {
                    ++$missing;
                    if ($persist) {
                        $created += $this->upsertIssue('lead', (int) $lead['id'], 'missing_field', 'medium', 'Missing required field: ' . $field, ['field' => $field]) ? 1 : 0;
                    }
                }
            }
            if ($this->isBlank($lead['owner_user_id'] ?? null)) {
                ++$ownership;
                if ($persist) {
                    $created += $this->upsertIssue('lead', (int) $lead['id'], 'ownership_missing', 'high', 'Lead has no owner', []) ? 1 : 0;
                }
            }
        }

        $opps = (new CrmOpportunity())->query(
            'SELECT id, name, owner_user_id, amount, stage_id FROM rateb_crm_opportunities
             WHERE company_id = :cid AND deleted_at IS NULL ORDER BY id DESC LIMIT 200',
            ['cid' => $companyId]
        );
        foreach (is_array($opps) ? $opps : [] as $opp) {
            foreach (($required['opportunity'] ?? []) as $field) {
                if ($this->isBlank($opp[$field] ?? null)) {
                    ++$missing;
                    if ($persist) {
                        $created += $this->upsertIssue('opportunity', (int) $opp['id'], 'missing_field', 'medium', 'Missing required field: ' . $field, ['field' => $field]) ? 1 : 0;
                    }
                }
            }
            if ($this->isBlank($opp['owner_user_id'] ?? null)) {
                ++$ownership;
                if ($persist) {
                    $created += $this->upsertIssue('opportunity', (int) $opp['id'], 'ownership_missing', 'high', 'Opportunity has no owner', []) ? 1 : 0;
                }
            }
        }

        // Duplicate detection: same email/phone on leads
        $dupRows = (new CrmLead())->query(
            "SELECT email, COUNT(*) AS cnt
             FROM rateb_crm_leads
             WHERE company_id = :cid AND deleted_at IS NULL AND email IS NOT NULL AND email <> ''
             GROUP BY email HAVING COUNT(*) > 1
             LIMIT 30",
            ['cid' => $companyId]
        );
        foreach (is_array($dupRows) ? $dupRows : [] as $d) {
            $duplicates += (int) ($d['cnt'] ?? 0);
            if ($persist) {
                $sample = (new CrmLead())->queryOne(
                    'SELECT id FROM rateb_crm_leads WHERE company_id = :cid AND email = :em AND deleted_at IS NULL LIMIT 1',
                    ['cid' => $companyId, 'em' => (string) $d['email']]
                );
                if ($sample) {
                    $created += $this->upsertIssue('lead', (int) $sample['id'], 'duplicate_email', 'medium', 'Duplicate lead email: ' . $d['email'], ['email' => $d['email'], 'count' => $d['cnt']]) ? 1 : 0;
                }
            }
        }

        if ($persist && class_exists(AuditService::class)) {
            (new AuditService())->log('crm.governance.scan', 'crm_governance', null, [
                'created' => $created,
                'missing' => $missing,
                'ownership' => $ownership,
                'duplicates' => $duplicates,
            ]);
        }

        return [
            'created' => $created,
            'duplicates' => $duplicates,
            'missing' => $missing,
            'ownership' => $ownership,
        ];
    }

    public function resolveIssue(int $issueId, ?string $note = null): void
    {
        $companyId = CrmSupport::requireCompanyId();
        $row = (new CrmDataQualityIssue())->queryOne(
            'SELECT id FROM rateb_crm_data_quality_issues WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $issueId, 'cid' => $companyId]
        );
        if ($row === null) {
            throw new \RuntimeException('issue_not_found');
        }
        (new CrmDataQualityIssue())->update($issueId, [
            'status' => 'resolved',
            'resolved_by' => CrmSupport::userId(),
            'resolved_at' => date('Y-m-d H:i:s'),
            'meta_json' => $note !== null ? json_encode(['note' => $note], JSON_UNESCAPED_UNICODE) : null,
        ]);
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.governance.fix', 'crm_data_quality_issue', $issueId, [
                'note' => $note,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function listOpenIssues(int $limit = 50): array
    {
        $rows = (new CrmDataQualityIssue())->query(
            "SELECT * FROM rateb_crm_data_quality_issues
             WHERE company_id = :cid AND status = 'open'
             ORDER BY FIELD(severity,'high','medium','low'), id DESC
             LIMIT " . max(1, min(100, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listSettings(): array
    {
        $rows = (new CrmGovernanceSetting())->query(
            'SELECT * FROM rateb_crm_governance_settings
             WHERE company_id = :cid AND deleted_at IS NULL ORDER BY setting_key ASC',
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Build a governance setting payload from normal form fields (no raw JSON UI).
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function buildSettingFromRequest(string $key, array $post): array
    {
        $key = trim($key);
        $flag = static function (array $post, string $name): bool {
            return !empty($post[$name]);
        };
        $int = static function (array $post, string $name, int $default, int $min = 0, int $max = 100000): int {
            $v = isset($post[$name]) ? (int) $post[$name] : $default;

            return max($min, min($max, $v));
        };

        return match ($key) {
            'automation_governance' => [
                'require_condition_json' => $flag($post, 'require_condition_json'),
                'max_always_rules' => $int($post, 'max_always_rules', 3, 0, 99),
            ],
            'automation_safety' => [
                'notification_cooldown_hours' => $int($post, 'notification_cooldown_hours', 24, 1, 720),
                'run_lock_minutes' => $int($post, 'run_lock_minutes', 10, 1, 1440),
                'max_notifies_per_run' => $int($post, 'max_notifies_per_run', 100, 1, 10000),
                'include_legacy_in_revops' => $flag($post, 'include_legacy_in_revops'),
                'block_always_rules_over_max' => $flag($post, 'block_always_rules_over_max'),
            ],
            'duplicate_rules' => [
                'match_email' => $flag($post, 'match_email'),
                'match_phone' => $flag($post, 'match_phone'),
                'match_company_name' => $flag($post, 'match_company_name'),
            ],
            'export_policy' => [
                'allow_csv' => $flag($post, 'allow_csv'),
                'audit_required' => $flag($post, 'audit_required'),
                'require_permission' => substr(trim((string) ($post['require_permission'] ?? 'crm.export.manage')), 0, 80),
            ],
            default => throw new \InvalidArgumentException('unsupported_setting_key'),
        };
    }

    /**
     * @param array<string, mixed>|string $value
     */
    public function saveSetting(string $key, $value): void
    {
        $companyId = CrmSupport::requireCompanyId();
        $key = substr(trim($key), 0, 60);
        if ($key === '') {
            throw new \InvalidArgumentException('setting_key_required');
        }
        $json = is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        $existing = (new CrmGovernanceSetting())->queryOne(
            'SELECT id FROM rateb_crm_governance_settings
             WHERE company_id = :cid AND setting_key = :k AND deleted_at IS NULL LIMIT 1',
            ['cid' => $companyId, 'k' => $key]
        );
        if ($existing) {
            (new CrmGovernanceSetting())->update((int) $existing['id'], array_merge([
                'setting_json' => $json,
            ], CrmSupport::actorFields(false)));
            $id = (int) $existing['id'];
        } else {
            $id = (int) (new CrmGovernanceSetting())->create(array_merge([
                'public_uuid' => CrmSupport::uuidV4(),
                'company_id' => $companyId,
                'setting_key' => $key,
                'setting_json' => $json,
            ], CrmSupport::actorFields(true)));
        }
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.governance.config', 'crm_governance_setting', $id, [
                'setting_key' => $key,
            ]);
        }
    }

    /**
     * @return array{ok:bool,violations:list<string>}
     */
    public function validateExportPolicy(): array
    {
        $policy = $this->setting('export_policy', [
            'allow_csv' => true,
            'require_permission' => 'crm.export.manage',
            'audit_required' => true,
        ]);
        $violations = [];
        if (empty($policy['allow_csv'])) {
            $violations[] = 'csv_export_disabled';
        }
        $required = trim((string) ($policy['require_permission'] ?? 'crm.export.manage'));
        if ($required !== '' && function_exists('rateb_can')) {
            $ok = rateb_can($required)
                || rateb_can('crm.reports.export')
                || rateb_can('crm.admin')
                || rateb_can('crm.manage');
            if (!$ok) {
                $violations[] = 'missing_permission:' . $required;
            }
        }

        return ['ok' => $violations === [], 'violations' => $violations];
    }

    /**
     * @return array{ok:bool,always_rules:int,max_always_rules:int}
     */
    public function automationGovernanceCheck(): array
    {
        $gov = $this->setting('automation_governance', [
            'require_condition_json' => true,
            'max_always_rules' => 3,
        ]);
        $rules = (new CrmAutomationRule())->query(
            'SELECT id, condition_json, is_enabled FROM rateb_crm_automation_rules
             WHERE company_id = :cid AND deleted_at IS NULL AND is_enabled = 1',
            ['cid' => CrmSupport::requireCompanyId()]
        );
        $always = 0;
        foreach (is_array($rules) ? $rules : [] as $r) {
            $cond = json_decode((string) ($r['condition_json'] ?? '{}'), true);
            $type = is_array($cond) ? (string) ($cond['type'] ?? 'always') : 'always';
            if ($type === 'always') {
                ++$always;
            }
        }
        $max = (int) ($gov['max_always_rules'] ?? 3);

        return [
            'ok' => $always <= $max,
            'always_rules' => $always,
            'max_always_rules' => $max,
        ];
    }

    /**
     * @param array<string, mixed> $default
     * @return array<string, mixed>
     */
    public function setting(string $key, array $default = []): array
    {
        try {
            $row = (new CrmGovernanceSetting())->queryOne(
                'SELECT setting_json FROM rateb_crm_governance_settings
                 WHERE company_id = :cid AND setting_key = :k AND deleted_at IS NULL LIMIT 1',
                ['cid' => CrmSupport::requireCompanyId(), 'k' => $key]
            );
            if (!is_array($row)) {
                return $default;
            }
            $decoded = json_decode((string) ($row['setting_json'] ?? ''), true);

            return is_array($decoded) ? $decoded : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function upsertIssue(
        string $entityType,
        int $entityId,
        string $code,
        string $severity,
        string $message,
        array $meta
    ): bool {
        $companyId = CrmSupport::requireCompanyId();
        $existing = (new CrmDataQualityIssue())->queryOne(
            "SELECT id FROM rateb_crm_data_quality_issues
             WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid
               AND issue_code = :code AND status = 'open' LIMIT 1",
            ['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId, 'code' => $code]
        );
        if ($existing) {
            return false;
        }
        (new CrmDataQualityIssue())->create([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'issue_code' => substr($code, 0, 60),
            'severity' => $severity,
            'message' => substr($message, 0, 255),
            'status' => 'open',
            'meta_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_by' => CrmSupport::userId(),
        ]);

        return true;
    }

    private function isBlank(mixed $v): bool
    {
        if ($v === null) {
            return true;
        }
        if (is_string($v) && trim($v) === '') {
            return true;
        }
        if (is_numeric($v) && (float) $v === 0.0 && (string) $v === '0') {
            // amount 0 may be invalid for opportunities — treat as blank only for owner/stage ids
            return false;
        }

        return false;
    }

    private function countOpenIssues(): int
    {
        try {
            return (int) (((new CrmDataQualityIssue())->queryOne(
                "SELECT COUNT(*) AS c FROM rateb_crm_data_quality_issues WHERE company_id = :cid AND status = 'open'",
                ['cid' => CrmSupport::requireCompanyId()]
            )['c'] ?? 0));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** @return array<string,int> */
    private function issuesBySeverity(): array
    {
        try {
            $rows = (new CrmDataQualityIssue())->query(
                "SELECT severity, COUNT(*) AS c FROM rateb_crm_data_quality_issues
                 WHERE company_id = :cid AND status = 'open' GROUP BY severity",
                ['cid' => CrmSupport::requireCompanyId()]
            );
            $out = ['high' => 0, 'medium' => 0, 'low' => 0];
            foreach (is_array($rows) ? $rows : [] as $r) {
                $out[(string) ($r['severity'] ?? 'medium')] = (int) ($r['c'] ?? 0);
            }

            return $out;
        } catch (\Throwable $e) {
            return ['high' => 0, 'medium' => 0, 'low' => 0];
        }
    }

    /**
     * @param array{missing:int,ownership:int,duplicates:int} $scan
     */
    private function governanceScore(array $scan): int
    {
        $penalty = min(70, ((int) $scan['missing'] * 1) + ((int) $scan['ownership'] * 3) + ((int) $scan['duplicates'] * 2));

        return max(0, 100 - $penalty);
    }
}
