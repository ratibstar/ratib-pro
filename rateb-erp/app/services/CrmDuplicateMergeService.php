<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\CrmContact;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmMergeRequest;
use Rateb\App\Models\CrmNote;
use Rateb\App\Models\CrmOpportunity;

/**
 * Phase 9 — Duplicate merge workflow with audit (CRM entities only; no Accounting).
 */
final class CrmDuplicateMergeService
{
    /** @return list<array<string, mixed>> */
    public function listPending(int $limit = 40): array
    {
        $rows = (new CrmMergeRequest())->query(
            "SELECT * FROM rateb_crm_merge_requests
             WHERE company_id = :cid AND status = 'pending'
             ORDER BY id DESC LIMIT " . max(1, min(100, $limit)),
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function suggestLeadDuplicates(int $limit = 20): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmLead())->query(
            "SELECT email, MIN(id) AS keep_id, MAX(id) AS dup_id, COUNT(*) AS cnt
             FROM rateb_crm_leads
             WHERE company_id = :cid AND deleted_at IS NULL AND email IS NOT NULL AND email <> ''
             GROUP BY email HAVING COUNT(*) > 1
             ORDER BY cnt DESC LIMIT " . max(1, min(50, $limit)),
            ['cid' => $companyId]
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $keep = (int) ($r['keep_id'] ?? 0);
            $dup = (int) ($r['dup_id'] ?? 0);
            if ($keep > 0 && $dup > 0 && $keep !== $dup) {
                $out[] = [
                    'entity_type' => 'lead',
                    'target_id' => $keep,
                    'source_id' => $dup,
                    'match' => (string) ($r['email'] ?? ''),
                    'count' => (int) ($r['cnt'] ?? 0),
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function requestMerge(string $entityType, int $sourceId, int $targetId, ?string $reason = null): array
    {
        $entityType = strtolower(trim($entityType));
        if (!in_array($entityType, ['lead', 'contact'], true)) {
            throw new \InvalidArgumentException('unsupported_merge_entity');
        }
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            throw new \InvalidArgumentException('invalid_merge_pair');
        }
        $this->assertEntities($entityType, $sourceId, $targetId);
        $id = (int) (new CrmMergeRequest())->create([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => CrmSupport::requireCompanyId(),
            'entity_type' => $entityType,
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'status' => 'pending',
            'reason' => $reason !== null ? substr(trim($reason), 0, 255) : null,
            'created_by' => CrmSupport::userId(),
        ]);
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.merge.request', 'crm_merge_request', $id, [
                'entity_type' => $entityType,
                'source_id' => $sourceId,
                'target_id' => $targetId,
            ]);
        }
        $row = (new CrmMergeRequest())->queryOne('SELECT * FROM rateb_crm_merge_requests WHERE id = :id LIMIT 1', ['id' => $id]);

        return is_array($row) ? $row : ['id' => $id];
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $mergeId): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $req = (new CrmMergeRequest())->queryOne(
            "SELECT * FROM rateb_crm_merge_requests WHERE id = :id AND company_id = :cid AND status = 'pending' LIMIT 1",
            ['id' => $mergeId, 'cid' => $companyId]
        );
        if (!is_array($req)) {
            throw new \RuntimeException('merge_not_found');
        }
        $entityType = (string) $req['entity_type'];
        $sourceId = (int) $req['source_id'];
        $targetId = (int) $req['target_id'];
        $this->assertEntities($entityType, $sourceId, $targetId);

        $moved = ['activities' => 0, 'opportunities' => 0, 'notes' => 0];
        if ($entityType === 'lead') {
            $moved['activities'] = $this->repoint('rateb_crm_activities', 'lead_id', $sourceId, $targetId);
            $moved['opportunities'] = $this->repoint('rateb_crm_opportunities', 'lead_id', $sourceId, $targetId);
            try {
                $moved['notes'] = $this->repoint('rateb_crm_notes', 'lead_id', $sourceId, $targetId);
            } catch (\Throwable $e) {
                $moved['notes'] = 0;
            }
            (new CrmLead())->update($sourceId, array_merge([
                'status' => 'archived',
                'workflow_status' => 'archived',
                'deleted_at' => date('Y-m-d H:i:s'),
                'notes' => 'Merged into lead #' . $targetId,
            ], CrmSupport::actorFields(false)));
        } else {
            $moved['activities'] = $this->repoint('rateb_crm_activities', 'contact_id', $sourceId, $targetId);
            (new CrmContact())->update($sourceId, array_merge([
                'status' => 'archived',
                'deleted_at' => date('Y-m-d H:i:s'),
                'notes' => 'Merged into contact #' . $targetId,
            ], CrmSupport::actorFields(false)));
        }

        (new CrmMergeRequest())->update($mergeId, [
            'status' => 'merged',
            'merge_json' => json_encode($moved, JSON_UNESCAPED_UNICODE),
            'resolved_by' => CrmSupport::userId(),
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.merge.execute', 'crm_merge_request', $mergeId, [
                'entity_type' => $entityType,
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'moved' => $moved,
            ]);
        }

        return ['id' => $mergeId, 'status' => 'merged', 'moved' => $moved];
    }

    public function reject(int $mergeId, ?string $reason = null): void
    {
        $companyId = CrmSupport::requireCompanyId();
        $req = (new CrmMergeRequest())->queryOne(
            "SELECT id FROM rateb_crm_merge_requests WHERE id = :id AND company_id = :cid AND status = 'pending' LIMIT 1",
            ['id' => $mergeId, 'cid' => $companyId]
        );
        if ($req === null) {
            throw new \RuntimeException('merge_not_found');
        }
        (new CrmMergeRequest())->update($mergeId, [
            'status' => 'rejected',
            'reason' => $reason !== null ? substr(trim($reason), 0, 255) : null,
            'resolved_by' => CrmSupport::userId(),
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.merge.reject', 'crm_merge_request', $mergeId, [
                'reason' => $reason,
            ]);
        }
    }

    private function assertEntities(string $entityType, int $sourceId, int $targetId): void
    {
        $companyId = CrmSupport::requireCompanyId();
        if ($entityType === 'lead') {
            foreach ([$sourceId, $targetId] as $id) {
                $row = (new CrmLead())->queryOne(
                    'SELECT id FROM rateb_crm_leads WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
                    ['id' => $id, 'cid' => $companyId]
                );
                if ($row === null) {
                    throw new \RuntimeException('lead_not_found:' . $id);
                }
            }
            return;
        }
        foreach ([$sourceId, $targetId] as $id) {
            $row = (new CrmContact())->queryOne(
                'SELECT id FROM rateb_crm_contacts WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
                ['id' => $id, 'cid' => $companyId]
            );
            if ($row === null) {
                throw new \RuntimeException('contact_not_found:' . $id);
            }
        }
    }

    private function repoint(string $table, string $column, int $fromId, int $toId): int
    {
        $companyId = CrmSupport::requireCompanyId();
        $allowed = [
            'rateb_crm_activities' => ['lead_id', 'contact_id'],
            'rateb_crm_opportunities' => ['lead_id'],
            'rateb_crm_notes' => ['lead_id'],
        ];
        if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
            return 0;
        }
        $model = match ($table) {
            'rateb_crm_activities' => new CrmActivity(),
            'rateb_crm_opportunities' => new CrmOpportunity(),
            'rateb_crm_notes' => new CrmNote(),
            default => null,
        };
        if ($model === null) {
            return 0;
        }
        $rows = $model->query(
            "SELECT id FROM {$table} WHERE company_id = :cid AND {$column} = :fromId AND deleted_at IS NULL",
            ['cid' => $companyId, 'fromId' => $fromId]
        );
        $n = 0;
        foreach (is_array($rows) ? $rows : [] as $r) {
            $model->update((int) $r['id'], [$column => $toId]);
            ++$n;
        }

        return $n;
    }
}
