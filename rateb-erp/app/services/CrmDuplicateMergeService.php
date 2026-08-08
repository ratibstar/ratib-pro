<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;
use Rateb\App\Models\CrmContact;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmMergeRequest;

/**
 * Phase 9/11 — Duplicate merge with full DB transaction (CRM entities only; no Accounting).
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
     * Execute merge inside a single DB transaction (atomicity).
     * On any failure: full rollback of archive/repoint/status updates.
     * Audit/observability run only after successful commit.
     *
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

        $db = Database::connection();
        $startedTx = false;
        $moved = ['activities' => 0, 'opportunities' => 0, 'notes' => 0];
        $t0 = microtime(true);

        try {
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $startedTx = true;
            }

            if ($entityType === 'lead') {
                $moved['activities'] = $this->repointBulk($db, 'rateb_crm_activities', 'lead_id', $sourceId, $targetId, $companyId);
                $moved['opportunities'] = $this->repointBulk($db, 'rateb_crm_opportunities', 'lead_id', $sourceId, $targetId, $companyId);
                $moved['notes'] = $this->repointBulkOptional($db, 'rateb_crm_notes', 'lead_id', $sourceId, $targetId, $companyId);
                $this->archiveLead($db, $sourceId, $targetId, $companyId);
            } else {
                $moved['activities'] = $this->repointBulk($db, 'rateb_crm_activities', 'contact_id', $sourceId, $targetId, $companyId);
                $this->archiveContact($db, $sourceId, $targetId, $companyId);
            }

            $check = $db->prepare(
                "SELECT id FROM rateb_crm_merge_requests
                 WHERE id = :id AND company_id = :cid AND status = 'pending' LIMIT 1"
            );
            $check->execute(['id' => $mergeId, 'cid' => $companyId]);
            if ($check->fetch(PDO::FETCH_ASSOC) === false) {
                throw new \RuntimeException('merge_not_found');
            }

            $upd = $db->prepare(
                "UPDATE rateb_crm_merge_requests
                 SET status = 'merged', merge_json = :mj, resolved_by = :uid, resolved_at = :at
                 WHERE id = :id AND company_id = :cid AND status = 'pending'"
            );
            $upd->execute([
                'mj' => json_encode($moved, JSON_UNESCAPED_UNICODE),
                'uid' => CrmSupport::userId(),
                'at' => date('Y-m-d H:i:s'),
                'id' => $mergeId,
                'cid' => $companyId,
            ]);
            if ($upd->rowCount() < 1) {
                throw new \RuntimeException('merge_finalize_failed');
            }

            if ($startedTx) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTx && $db->inTransaction()) {
                $db->rollBack();
            }
            CrmObservability::logFailure('crm.merge.execute', $e, 'crm_merge_request', $mergeId);
            throw $e;
        }

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.merge.execute', 'crm_merge_request', $mergeId, [
                'entity_type' => $entityType,
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'moved' => $moved,
                'transactional' => true,
            ]);
        }
        CrmObservability::logTiming('crm.merge.execute', (microtime(true) - $t0) * 1000, true, null, 'crm_merge_request', $mergeId);

        return ['id' => $mergeId, 'status' => 'merged', 'moved' => $moved, 'transactional' => true];
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

    private function repointBulk(PDO $db, string $table, string $column, int $fromId, int $toId, int $companyId): int
    {
        $allowed = [
            'rateb_crm_activities' => ['lead_id', 'contact_id'],
            'rateb_crm_opportunities' => ['lead_id'],
            'rateb_crm_notes' => ['lead_id'],
        ];
        if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
            throw new \InvalidArgumentException('invalid_repoint_target');
        }
        $sql = "UPDATE {$table} SET {$column} = :toId
                WHERE company_id = :cid AND {$column} = :fromId AND deleted_at IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute(['toId' => $toId, 'cid' => $companyId, 'fromId' => $fromId]);

        return max(0, $stmt->rowCount());
    }

    /**
     * Notes table / column may be absent on older tenants — never abort merge.
     */
    private function repointBulkOptional(PDO $db, string $table, string $column, int $fromId, int $toId, int $companyId): int
    {
        try {
            return $this->repointBulk($db, $table, $column, $fromId, $toId, $companyId);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function archiveLead(PDO $db, int $sourceId, int $targetId, int $companyId): void
    {
        $actor = CrmSupport::actorFields(false);
        $sql = "UPDATE rateb_crm_leads
                SET status = 'archived', workflow_status = 'archived', deleted_at = :del,
                    notes = :notes, updated_by = :ub, updated_at = :ua
                WHERE id = :id AND company_id = :cid AND deleted_at IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'del' => date('Y-m-d H:i:s'),
            'notes' => 'Merged into lead #' . $targetId,
            'ub' => $actor['updated_by'] ?? CrmSupport::userId(),
            'ua' => date('Y-m-d H:i:s'),
            'id' => $sourceId,
            'cid' => $companyId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException('lead_archive_failed');
        }
    }

    private function archiveContact(PDO $db, int $sourceId, int $targetId, int $companyId): void
    {
        $actor = CrmSupport::actorFields(false);
        $sql = "UPDATE rateb_crm_contacts
                SET status = 'archived', deleted_at = :del, notes = :notes,
                    updated_by = :ub, updated_at = :ua
                WHERE id = :id AND company_id = :cid AND deleted_at IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'del' => date('Y-m-d H:i:s'),
            'notes' => 'Merged into contact #' . $targetId,
            'ub' => $actor['updated_by'] ?? CrmSupport::userId(),
            'ua' => date('Y-m-d H:i:s'),
            'id' => $sourceId,
            'cid' => $companyId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException('contact_archive_failed');
        }
    }
}
