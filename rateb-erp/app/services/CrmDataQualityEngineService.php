<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmDataQualityIssue;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmQualitySnapshot;
use Rateb\App\Models\Customer;

/** Phase 8 — Advanced data quality engine (extends Phase 7 governance scan). */
final class CrmDataQualityEngineService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(bool $liveScan = false): array
    {
        // Phase 10: prefer last snapshot on GET paths to avoid scanning 400+ rows per page load.
        if (!$liveScan) {
            $trend = $this->qualityTrend(1);
            $latest = $trend[0] ?? null;
            if (is_array($latest)) {
                return [
                    'completeness_score' => (float) ($latest['completeness_score'] ?? 0),
                    'quality_score' => (float) ($latest['quality_score'] ?? 0),
                    'open_issues' => $this->countOpen(),
                    'resolved_issues' => $this->countResolved(),
                    'duplicates' => (int) ($latest['duplicate_count'] ?? 0),
                    'missing' => (int) ($latest['missing_count'] ?? 0),
                    'ownership' => (int) ($latest['ownership_gaps'] ?? 0),
                    'by_severity' => $this->bySeverity(),
                    'trend' => $this->qualityTrend(20),
                    'resolution' => $this->resolutionTracking(20),
                    'source' => 'snapshot',
                    'duplicate_rules' => (new CrmGovernanceService())->setting('duplicate_rules', [
                        'match_email' => true,
                        'match_phone' => true,
                        'match_company_name' => true,
                    ]),
                ];
            }
        }

        $scan = $this->runScan(false);
        $scores = $this->computeScores($scan);

        return [
            'completeness_score' => $scores['completeness_score'],
            'quality_score' => $scores['quality_score'],
            'open_issues' => $this->countOpen(),
            'resolved_issues' => $this->countResolved(),
            'duplicates' => $scan['duplicates'],
            'missing' => $scan['missing'],
            'ownership' => $scan['ownership'],
            'by_severity' => $this->bySeverity(),
            'trend' => $this->qualityTrend(20),
            'resolution' => $this->resolutionTracking(20),
            'source' => 'live_scan',
            'duplicate_rules' => (new CrmGovernanceService())->setting('duplicate_rules', [
                'match_email' => true,
                'match_phone' => true,
                'match_company_name' => true,
            ]),
        ];
    }

    /**
     * @return array{created:int,duplicates:int,missing:int,ownership:int,scanned:int}
     */
    public function runScan(bool $persist = true): array
    {
        $base = (new CrmGovernanceService())->runDataQualityScan($persist);
        $companyId = CrmSupport::requireCompanyId();
        $rules = (new CrmGovernanceService())->setting('duplicate_rules', [
            'match_email' => true,
            'match_phone' => true,
            'match_company_name' => true,
        ]);
        $extraDupes = 0;
        $created = (int) ($base['created'] ?? 0);

        if (!empty($rules['match_phone'])) {
            $phoneDupes = (new CrmLead())->query(
                "SELECT phone, COUNT(*) AS cnt FROM rateb_crm_leads
                 WHERE company_id = :cid AND deleted_at IS NULL AND phone IS NOT NULL AND phone <> ''
                 GROUP BY phone HAVING COUNT(*) > 1 LIMIT 30",
                ['cid' => $companyId]
            );
            foreach (is_array($phoneDupes) ? $phoneDupes : [] as $d) {
                $extraDupes += (int) ($d['cnt'] ?? 0);
                if ($persist) {
                    $sample = (new CrmLead())->queryOne(
                        'SELECT id FROM rateb_crm_leads WHERE company_id = :cid AND phone = :p AND deleted_at IS NULL LIMIT 1',
                        ['cid' => $companyId, 'p' => (string) $d['phone']]
                    );
                    if ($sample) {
                        $created += $this->upsertIssue(
                            'lead',
                            (int) $sample['id'],
                            'duplicate_phone',
                            'medium',
                            'Duplicate lead phone: ' . $d['phone'],
                            ['phone' => $d['phone'], 'count' => $d['cnt']]
                        ) ? 1 : 0;
                    }
                }
            }
        }

        if (!empty($rules['match_company_name'])) {
            $nameDupes = (new Customer())->query(
                "SELECT name, COUNT(*) AS cnt FROM rateb_customers
                 WHERE company_id = :cid AND name IS NOT NULL AND name <> ''
                 GROUP BY name HAVING COUNT(*) > 1 LIMIT 20",
                ['cid' => $companyId]
            );
            foreach (is_array($nameDupes) ? $nameDupes : [] as $d) {
                $extraDupes += (int) ($d['cnt'] ?? 0);
                if ($persist) {
                    $sample = (new Customer())->queryOne(
                        'SELECT id FROM rateb_customers WHERE company_id = :cid AND name = :n LIMIT 1',
                        ['cid' => $companyId, 'n' => (string) $d['name']]
                    );
                    if ($sample) {
                        $created += $this->upsertIssue(
                            'customer',
                            (int) $sample['id'],
                            'duplicate_name',
                            'low',
                            'Duplicate customer name: ' . $d['name'],
                            ['name' => $d['name'], 'count' => $d['cnt']]
                        ) ? 1 : 0;
                    }
                }
            }
        }

        $result = [
            'created' => $created,
            'duplicates' => (int) ($base['duplicates'] ?? 0) + $extraDupes,
            'missing' => (int) ($base['missing'] ?? 0),
            'ownership' => (int) ($base['ownership'] ?? 0),
            'scanned' => 1,
        ];

        if ($persist) {
            $this->recordSnapshot($result);
        }

        return $result;
    }

    /**
     * @return array{completeness_score:float,quality_score:float}
     */
    public function computeScores(?array $scan = null): array
    {
        $scan = $scan ?? $this->runScan(false);
        $companyId = CrmSupport::requireCompanyId();
        $oppTotal = (int) ((new CrmOpportunity())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_opportunities WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        )['c'] ?? 0);
        $leadTotal = (int) ((new CrmLead())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_leads WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        )['c'] ?? 0);
        $denom = max(1, $oppTotal + $leadTotal);
        $issues = (int) ($scan['missing'] ?? 0) + (int) ($scan['ownership'] ?? 0) + (int) ($scan['duplicates'] ?? 0);
        $completeness = max(0, min(100, 100 - (($scan['missing'] ?? 0) / $denom) * 100));
        $quality = max(0, min(100, 100 - ($issues / $denom) * 50));

        return [
            'completeness_score' => round((float) $completeness, 2),
            'quality_score' => round((float) $quality, 2),
        ];
    }

    /**
     * @param array{duplicates:int,missing:int,ownership:int} $scan
     */
    public function recordSnapshot(array $scan): int
    {
        $scores = $this->computeScores($scan);
        $id = (int) (new CrmQualitySnapshot())->create([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => CrmSupport::requireCompanyId(),
            'completeness_score' => $scores['completeness_score'],
            'quality_score' => $scores['quality_score'],
            'open_issues' => $this->countOpen(),
            'resolved_issues' => $this->countResolved(),
            'duplicate_count' => (int) ($scan['duplicates'] ?? 0),
            'missing_count' => (int) ($scan['missing'] ?? 0),
            'ownership_gaps' => (int) ($scan['ownership'] ?? 0),
            'meta_json' => json_encode($scan, JSON_UNESCAPED_UNICODE),
            'created_by' => CrmSupport::userId(),
        ]);
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.governance.scan', 'crm_quality_snapshot', $id, $scores);
        }

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function qualityTrend(int $limit = 20): array
    {
        $rows = (new CrmQualitySnapshot())->query(
            'SELECT * FROM rateb_crm_quality_snapshots
             WHERE company_id = :cid ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? array_reverse($rows) : [];
    }

    /** @return list<array<string, mixed>> */
    public function resolutionTracking(int $limit = 30): array
    {
        $rows = (new CrmDataQualityIssue())->query(
            "SELECT id, entity_type, entity_id, issue_code, severity, status, resolution_note, resolved_by, resolved_at, created_at
             FROM rateb_crm_data_quality_issues
             WHERE company_id = :cid AND status = 'resolved'
             ORDER BY resolved_at DESC LIMIT " . max(1, min(100, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
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
            'resolution_note' => $note !== null ? substr(trim($note), 0, 255) : null,
            'meta_json' => $note !== null ? json_encode(['note' => $note], JSON_UNESCAPED_UNICODE) : null,
        ]);
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.governance.fix', 'crm_data_quality_issue', $issueId, [
                'note' => $note,
                'engine' => 'phase8',
            ]);
        }
    }

    private function countOpen(): int
    {
        $row = (new CrmDataQualityIssue())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_data_quality_issues WHERE company_id = :cid AND status = 'open'",
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return (int) ($row['c'] ?? 0);
    }

    private function countResolved(): int
    {
        $row = (new CrmDataQualityIssue())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_crm_data_quality_issues WHERE company_id = :cid AND status = 'resolved'",
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @return array<string, int> */
    private function bySeverity(): array
    {
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
    }

    /** @param array<string, mixed> $meta */
    private function upsertIssue(string $entityType, int $entityId, string $code, string $severity, string $message, array $meta): bool
    {
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
            'issue_code' => $code,
            'severity' => $severity,
            'message' => substr($message, 0, 255),
            'status' => 'open',
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_by' => CrmSupport::userId(),
        ]);

        return true;
    }
}
