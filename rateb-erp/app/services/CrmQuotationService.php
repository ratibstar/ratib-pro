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
}
