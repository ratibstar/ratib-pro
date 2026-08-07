<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmQuotation;
use Rateb\App\Models\CrmQuotationLine;

/**
 * Phase 1 — CRM Sales Quotations (ONLINE).
 * Links leads / opportunities / customers. No invoice/payment workflow yet.
 */
final class CrmQuotationService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = '', ?string $status = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND status = :st';
            $params['st'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (title LIKE :q OR quotation_no LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new CrmQuotation())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_quotations WHERE ' . $where,
            $params
        );
        $items = (new CrmQuotation())->query(
            'SELECT * FROM rateb_crm_quotations WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $companyId = CrmSupport::requireCompanyId();
        $row = (new CrmQuotation())->queryOne(
            'SELECT * FROM rateb_crm_quotations WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function linesFor(int $quotationId): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new CrmQuotationLine())->query(
            'SELECT * FROM rateb_crm_quotation_lines'
            . ' WHERE company_id = :cid AND quotation_id = :qid AND deleted_at IS NULL'
            . ' ORDER BY sort_order ASC, line_no ASC, id ASC',
            ['cid' => $companyId, 'qid' => $quotationId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, quotation_no: string}
     */
    public function create(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $no = trim((string) ($input['quotation_no'] ?? ''));
        if ($no === '') {
            $no = CrmSupport::nextQuotationNo($companyId);
        }

        $qty = max(0.001, (float) ($input['quantity'] ?? 1));
        $unit = max(0.0, (float) ($input['unit_price'] ?? 0));
        $taxRate = max(0.0, (float) ($input['tax_rate'] ?? 0));
        $subtotal = round($qty * $unit, 2);
        $tax = round($subtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $tax, 2);
        $itemName = trim((string) ($input['item_name'] ?? $title));
        if ($itemName === '') {
            $itemName = $title;
        }

        $id = (new CrmQuotation())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => CrmSupport::intOrNull($input['branch_id'] ?? null) ?? CrmSupport::branchId(),
            'quotation_no' => $no,
            'title' => substr($title, 0, 190),
            'lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null),
            'opportunity_id' => CrmSupport::intOrNull($input['opportunity_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            'crm_company_id' => CrmSupport::intOrNull($input['crm_company_id'] ?? null),
            'contact_id' => CrmSupport::intOrNull($input['contact_id'] ?? null),
            'owner_user_id' => CrmSupport::intOrNull($input['owner_user_id'] ?? null) ?? CrmSupport::userId(),
            'status' => 'draft',
            'currency_code' => substr(strtoupper(trim((string) ($input['currency_code'] ?? 'SAR'))), 0, 3) ?: 'SAR',
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => 0,
            'total_amount' => $total,
            'valid_until' => CrmSupport::nullIfEmpty($input['valid_until'] ?? null),
            'notes' => CrmSupport::nullIfEmpty($input['notes'] ?? null),
            'version_no' => max(1, (int) ($input['version_no'] ?? 1)),
            'parent_quotation_id' => CrmSupport::intOrNull($input['parent_quotation_id'] ?? null),
            'root_quotation_id' => CrmSupport::intOrNull($input['root_quotation_id'] ?? null),
            'approval_status' => in_array((string) ($input['approval_status'] ?? 'none'), ['none', 'pending', 'approved', 'rejected'], true)
                ? (string) ($input['approval_status'] ?? 'none') : 'none',
        ], CrmSupport::actorFields(true)));

        (new CrmQuotationLine())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'quotation_id' => (int) $id,
            'line_no' => 1,
            'item_name' => substr($itemName, 0, 190),
            'description' => CrmSupport::nullIfEmpty($input['description'] ?? null),
            'quantity' => $qty,
            'unit_price' => $unit,
            'tax_rate' => $taxRate,
            'line_subtotal' => $subtotal,
            'line_tax' => $tax,
            'line_total' => $total,
            'sort_order' => 0,
        ], CrmSupport::actorFields(true)));

        (new CrmTimelineService())->record(
            'quotation_created',
            'Quotation created: ' . $title,
            null,
            'quotation',
            (int) $id,
            [
                'opportunity_id' => CrmSupport::intOrNull($input['opportunity_id'] ?? null),
                'lead_id' => CrmSupport::intOrNull($input['lead_id'] ?? null),
                'customer_id' => CrmSupport::intOrNull($input['customer_id'] ?? null),
            ]
        );

        return ['id' => (int) $id, 'quotation_no' => $no];
    }

    /**
     * Phase 2 — Invoice conversion is intentionally blocked.
     */
    public function convertToInvoice(int $quotationId): void
    {
        unset($quotationId);
        throw new \RuntimeException('quotation_to_invoice_disabled_phase2');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function statusHistory(int $quotationId): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $rows = (new \Rateb\App\Models\CrmEntityStatusHistory())->query(
            "SELECT * FROM rateb_crm_entity_status_history
             WHERE company_id = :cid AND entity_type = 'quotation' AND entity_id = :eid
             ORDER BY created_at DESC, id DESC LIMIT 100",
            ['cid' => $companyId, 'eid' => $quotationId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Duplicate quotation as a new draft (same content, new number).
     *
     * @return array{id: int, quotation_no: string}
     */
    public function duplicate(int $quotationId): array
    {
        $src = $this->find($quotationId);
        if ($src === null) {
            throw new \RuntimeException('quotation_not_found');
        }
        $lines = $this->linesFor($quotationId);
        $first = $lines[0] ?? [];
        $created = $this->create([
            'title' => (string) ($src['title'] ?? 'Quotation') . ' (copy)',
            'lead_id' => $src['lead_id'] ?? null,
            'opportunity_id' => $src['opportunity_id'] ?? null,
            'customer_id' => $src['customer_id'] ?? null,
            'crm_company_id' => $src['crm_company_id'] ?? null,
            'contact_id' => $src['contact_id'] ?? null,
            'owner_user_id' => $src['owner_user_id'] ?? null,
            'currency_code' => $src['currency_code'] ?? 'SAR',
            'valid_until' => $src['valid_until'] ?? null,
            'notes' => $src['notes'] ?? null,
            'quantity' => $first['quantity'] ?? 1,
            'unit_price' => $first['unit_price'] ?? ($src['total_amount'] ?? 0),
            'tax_rate' => $first['tax_rate'] ?? 0,
            'item_name' => $first['item_name'] ?? ($src['title'] ?? 'Item'),
            'description' => $first['description'] ?? null,
            'parent_quotation_id' => $quotationId,
            'root_quotation_id' => CrmSupport::intOrNull($src['root_quotation_id'] ?? null) ?? $quotationId,
            'version_no' => 1,
            'approval_status' => 'none',
        ]);
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.quotation.duplicate', 'crm_quotation', $created['id'], [
                'source_id' => $quotationId,
            ]);
        }

        return $created;
    }

    /**
     * Create a new version in the same version family.
     *
     * @return array{id: int, quotation_no: string, version_no: int}
     */
    public function createVersion(int $quotationId): array
    {
        $src = $this->find($quotationId);
        if ($src === null) {
            throw new \RuntimeException('quotation_not_found');
        }
        $rootId = CrmSupport::intOrNull($src['root_quotation_id'] ?? null) ?? $quotationId;
        $companyId = CrmSupport::requireCompanyId();
        $maxRow = (new CrmQuotation())->queryOne(
            'SELECT COALESCE(MAX(version_no),0) AS m FROM rateb_crm_quotations
             WHERE company_id = :cid AND deleted_at IS NULL
               AND (id = :root OR root_quotation_id = :root2 OR parent_quotation_id = :root3)',
            ['cid' => $companyId, 'root' => $rootId, 'root2' => $rootId, 'root3' => $rootId]
        );
        $nextVer = (int) ($maxRow['m'] ?? 0) + 1;
        $dup = $this->duplicate($quotationId);
        (new CrmQuotation())->update($dup['id'], array_merge([
            'version_no' => $nextVer,
            'parent_quotation_id' => $quotationId,
            'root_quotation_id' => $rootId,
            'title' => (string) ($src['title'] ?? 'Quotation') . ' v' . $nextVer,
        ], CrmSupport::actorFields(false)));
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.quotation.version', 'crm_quotation', $dup['id'], [
                'source_id' => $quotationId,
                'version_no' => $nextVer,
                'root_quotation_id' => $rootId,
            ]);
        }

        return ['id' => $dup['id'], 'quotation_no' => $dup['quotation_no'], 'version_no' => $nextVer];
    }

    /**
     * @return array{ok: bool, approval_status: string}
     */
    public function submitForApproval(int $quotationId): array
    {
        $quote = $this->find($quotationId);
        if ($quote === null) {
            throw new \RuntimeException('quotation_not_found');
        }
        if ((string) ($quote['status'] ?? '') !== 'draft') {
            throw new \RuntimeException('quotation_must_be_draft_for_approval');
        }
        (new CrmQuotation())->update($quotationId, array_merge([
            'approval_status' => 'pending',
        ], CrmSupport::actorFields(false)));
        (new CrmTimelineService())->record(
            'quotation_approval_pending',
            'Quotation submitted for approval',
            null,
            'quotation',
            $quotationId,
            [
                'lead_id' => CrmSupport::intOrNull($quote['lead_id'] ?? null),
                'opportunity_id' => CrmSupport::intOrNull($quote['opportunity_id'] ?? null),
                'customer_id' => CrmSupport::intOrNull($quote['customer_id'] ?? null),
            ]
        );
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.quotation.approval_submit', 'crm_quotation', $quotationId, [
                'approval_status' => 'pending',
            ]);
        }

        return ['ok' => true, 'approval_status' => 'pending'];
    }

    /**
     * @return array{ok: bool, approval_status: string}
     */
    public function decideApproval(int $quotationId, bool $approve, ?string $reason = null): array
    {
        $quote = $this->find($quotationId);
        if ($quote === null) {
            throw new \RuntimeException('quotation_not_found');
        }
        if ((string) ($quote['approval_status'] ?? '') !== 'pending') {
            throw new \RuntimeException('quotation_not_pending_approval');
        }
        $status = $approve ? 'approved' : 'rejected';
        $patch = array_merge([
            'approval_status' => $status,
            'approved_by' => $approve ? CrmSupport::userId() : null,
            'approved_at' => $approve ? date('Y-m-d H:i:s') : null,
        ], CrmSupport::actorFields(false));
        (new CrmQuotation())->update($quotationId, $patch);
        (new CrmTimelineService())->record(
            'quotation_approval_' . $status,
            'Quotation approval ' . $status,
            $reason,
            'quotation',
            $quotationId,
            [
                'lead_id' => CrmSupport::intOrNull($quote['lead_id'] ?? null),
                'opportunity_id' => CrmSupport::intOrNull($quote['opportunity_id'] ?? null),
                'customer_id' => CrmSupport::intOrNull($quote['customer_id'] ?? null),
            ]
        );
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.quotation.approval_' . $status, 'crm_quotation', $quotationId, [
                'reason' => $reason,
            ]);
        }

        return ['ok' => true, 'approval_status' => $status];
    }

    /** Expire sent/draft quotes past valid_until. @return int */
    public function expireOverdue(): int
    {
        $companyId = CrmSupport::requireCompanyId();
        $today = date('Y-m-d');
        $rows = (new CrmQuotation())->query(
            "SELECT id FROM rateb_crm_quotations
             WHERE company_id = :cid AND deleted_at IS NULL
               AND status = 'sent'
               AND valid_until IS NOT NULL AND valid_until < :today
             LIMIT 100",
            ['cid' => $companyId, 'today' => $today]
        );
        $count = 0;
        $wf = new CrmQuotationWorkflowService();
        foreach (is_array($rows) ? $rows : [] as $row) {
            try {
                $wf->transition((int) $row['id'], CrmQuotationWorkflowService::STATUS_EXPIRED, 'auto_expiry');
                ++$count;
            } catch (\Throwable $e) {
                // skip illegal transitions
            }
        }

        return $count;
    }

    /**
     * @return array{total:int,accepted:int,rejected:int,expired:int,acceptance_rate:float,avg_total:float}
     */
    public function performanceMetrics(): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $row = (new CrmQuotation())->queryOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired,
                    COALESCE(AVG(total_amount),0) AS avg_total
             FROM rateb_crm_quotations WHERE company_id = :cid AND deleted_at IS NULL",
            ['cid' => $companyId]
        );
        $total = (int) ($row['total'] ?? 0);
        $accepted = (int) ($row['accepted'] ?? 0);

        return [
            'total' => $total,
            'accepted' => $accepted,
            'rejected' => (int) ($row['rejected'] ?? 0),
            'expired' => (int) ($row['expired'] ?? 0),
            'acceptance_rate' => $total > 0 ? round(($accepted / $total) * 100, 1) : 0.0,
            'avg_total' => round((float) ($row['avg_total'] ?? 0), 2),
        ];
    }
}
