<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmSavedReportFilter;

/** Phase 6 — CSV export + saved report filters. */
final class CrmReportExportService
{
    /**
     * @param array<string, mixed> $filters date_from, date_to, pipeline_id, owner_user_id, report
     * @return array{filename:string,headers:list<string>,rows:list<list<string|int|float|null>>}
     */
    public function build(array $filters = []): array
    {
        $report = strtolower(trim((string) ($filters['report'] ?? 'funnel')));
        $pipelineId = CrmSupport::intOrNull($filters['pipeline_id'] ?? null);
        $dateFrom = CrmSupport::nullIfEmpty($filters['date_from'] ?? null);
        $dateTo = CrmSupport::nullIfEmpty($filters['date_to'] ?? null);

        if ($report === 'performance' || $report === 'rep') {
            $rows = (new CrmAnalyticsService())->repPerformance();
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    $r['owner_user_id'],
                    $r['open_count'],
                    $r['won_count'],
                    $r['lost_count'],
                    $r['open_amount'],
                    $r['won_amount'],
                    $r['win_rate'],
                ];
            }

            return [
                'filename' => 'crm-rep-performance.csv',
                'headers' => ['owner_user_id', 'open_count', 'won_count', 'lost_count', 'open_amount', 'won_amount', 'win_rate'],
                'rows' => $out,
            ];
        }

        if ($report === 'activity') {
            $a = (new CrmActivityIntelligenceService())->analyze(
                CrmSupport::intOrNull($filters['owner_user_id'] ?? null),
                $dateFrom,
                $dateTo
            );

            return [
                'filename' => 'crm-activity-intelligence.csv',
                'headers' => array_keys($a),
                'rows' => [array_values($a)],
            ];
        }

        if ($report === 'velocity') {
            $v = (new CrmAnalyticsService())->pipelineVelocity($pipelineId);

            return [
                'filename' => 'crm-pipeline-velocity.csv',
                'headers' => array_keys($v),
                'rows' => [array_values($v)],
            ];
        }

        // default funnel
        $funnel = (new CrmReportService())->salesFunnel($pipelineId);
        $out = [];
        foreach ($funnel as $r) {
            $out[] = [$r['stage'], $r['count'], $r['amount'], $r['expected_revenue']];
        }

        return [
            'filename' => 'crm-sales-funnel.csv',
            'headers' => ['stage', 'count', 'amount', 'expected_revenue'],
            'rows' => $out,
        ];
    }

    /**
     * Stream CSV to browser and exit.
     *
     * @param array<string, mixed> $filters
     */
    public function streamCsv(array $filters = []): void
    {
        try {
            $policy = (new CrmGovernanceService())->validateExportPolicy();
            if (!$policy['ok']) {
                throw new \RuntimeException('export_policy_blocked:' . implode(',', $policy['violations']));
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // governance table may be absent pre-migrate
        }
        $payload = $this->build($filters);
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.export.csv', 'crm_report', null, [
                'filename' => $payload['filename'],
                'filters' => $filters,
                'row_count' => count($payload['rows']),
            ]);
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $payload['filename'] . '"');
        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new \RuntimeException('csv_stream_failed');
        }
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, $payload['headers']);
        foreach ($payload['rows'] as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    /** @return list<array<string, mixed>> */
    public function listSavedFilters(?int $userId = null, string $reportKey = 'reports'): array
    {
        $uid = $userId ?? CrmSupport::userId();
        if ($uid === null || $uid < 1) {
            return [];
        }
        $rows = (new CrmSavedReportFilter())->query(
            'SELECT * FROM rateb_crm_saved_report_filters
             WHERE company_id = :cid AND user_id = :uid AND report_key = :rk AND deleted_at IS NULL
             ORDER BY updated_at DESC',
            ['cid' => CrmSupport::requireCompanyId(), 'uid' => $uid, 'rk' => $reportKey]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id:int}
     */
    public function saveFilter(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $uid = CrmSupport::userId();
        if ($uid === null || $uid < 1) {
            throw new \RuntimeException('user_required');
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $filters = $input['filters'] ?? $input;
        if (is_array($filters)) {
            $filtersJson = (string) json_encode([
                'report' => $filters['report'] ?? 'funnel',
                'pipeline_id' => $filters['pipeline_id'] ?? null,
                'owner_user_id' => $filters['owner_user_id'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $filtersJson = (string) $filters;
        }
        $id = (new CrmSavedReportFilter())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'user_id' => $uid,
            'name' => substr($name, 0, 160),
            'report_key' => substr(trim((string) ($input['report_key'] ?? 'reports')), 0, 60),
            'filters_json' => $filtersJson,
        ], CrmSupport::actorFields(true)));
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.export.saved_filter', 'crm_saved_report_filter', (int) $id, [
                'name' => $name,
            ]);
        }

        return ['id' => (int) $id];
    }
}
