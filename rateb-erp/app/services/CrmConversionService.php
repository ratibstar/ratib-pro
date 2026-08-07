<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmConversion;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmQuotation;
use Rateb\App\Models\Customer;

/**
 * Phase 2 — Formal CRM sales conversions with audit trail.
 * Lead → Opportunity → Quotation → Customer. No invoice posting.
 */
final class CrmConversionService
{
    /**
     * @param array<string, mixed> $input
     * @return array{opportunity_id: int, opportunity_no: string}
     */
    public function leadToOpportunity(int $leadId, array $input = []): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $lead = CrmSupport::assertLead($leadId, $companyId);
        $name = trim((string) ($input['name'] ?? ($lead['title'] ?? 'Opportunity')));
        $created = (new OpportunityService())->create(array_merge($input, [
            'name' => $name,
            'lead_id' => $leadId,
            'customer_id' => CrmSupport::intOrNull($lead['customer_id'] ?? null),
            'crm_company_id' => CrmSupport::intOrNull($lead['crm_company_id'] ?? null),
            'contact_id' => CrmSupport::intOrNull($lead['contact_id'] ?? null),
            'amount' => $input['amount'] ?? ($lead['estimated_value'] ?? 0),
            'currency_code' => $input['currency_code'] ?? ($lead['currency_code'] ?? null),
            'owner_user_id' => $input['owner_user_id'] ?? ($lead['owner_user_id'] ?? null),
        ]));

        $this->logConversion('lead_to_opportunity', 'lead', $leadId, 'opportunity', (int) $created['id'], [
            'lead_no' => $lead['lead_no'] ?? null,
            'opportunity_no' => $created['opportunity_no'],
        ]);

        (new CrmTimelineService())->record(
            'conversion_lead_opportunity',
            'Converted lead to opportunity ' . $created['opportunity_no'],
            null,
            'opportunity',
            (int) $created['id'],
            ['lead_id' => $leadId, 'opportunity_id' => (int) $created['id']]
        );

        return ['opportunity_id' => (int) $created['id'], 'opportunity_no' => $created['opportunity_no']];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{quotation_id: int, quotation_no: string}
     */
    public function opportunityToQuotation(int $opportunityId, array $input = []): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $opp = (new OpportunityService())->find($opportunityId);
        if ($opp === null) {
            throw new \RuntimeException('opportunity_not_found');
        }
        $title = trim((string) ($input['title'] ?? ($opp['name'] ?? 'Quotation')));
        $created = (new CrmQuotationService())->create(array_merge($input, [
            'title' => $title,
            'opportunity_id' => $opportunityId,
            'lead_id' => CrmSupport::intOrNull($opp['lead_id'] ?? null),
            'customer_id' => CrmSupport::intOrNull($opp['customer_id'] ?? null),
            'crm_company_id' => CrmSupport::intOrNull($opp['crm_company_id'] ?? null),
            'contact_id' => CrmSupport::intOrNull($opp['contact_id'] ?? null),
            'unit_price' => $input['unit_price'] ?? ($opp['amount'] ?? 0),
            'quantity' => $input['quantity'] ?? 1,
            'currency_code' => $input['currency_code'] ?? ($opp['currency_code'] ?? 'SAR'),
            'owner_user_id' => $input['owner_user_id'] ?? ($opp['owner_user_id'] ?? null),
            'item_name' => $input['item_name'] ?? $title,
        ]));

        $this->logConversion('opportunity_to_quotation', 'opportunity', $opportunityId, 'quotation', (int) $created['id'], [
            'opportunity_no' => $opp['opportunity_no'] ?? null,
            'quotation_no' => $created['quotation_no'],
        ]);

        (new CrmTimelineService())->record(
            'conversion_opportunity_quotation',
            'Converted opportunity to quotation ' . $created['quotation_no'],
            null,
            'quotation',
            (int) $created['id'],
            [
                'lead_id' => CrmSupport::intOrNull($opp['lead_id'] ?? null),
                'opportunity_id' => $opportunityId,
            ]
        );

        return ['quotation_id' => (int) $created['id'], 'quotation_no' => $created['quotation_no']];
    }

    /**
     * Create/link ERP customer from an accepted quotation (or explicit convert).
     * Does NOT create invoices.
     *
     * @param array<string, mixed> $input
     * @return array{customer_id: int, code: string}
     */
    public function quotationToCustomer(int $quotationId, array $input = []): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $quote = (new CrmQuotationService())->find($quotationId);
        if ($quote === null) {
            throw new \RuntimeException('quotation_not_found');
        }
        $status = (string) ($quote['status'] ?? 'draft');
        if ($status !== CrmQuotationWorkflowService::STATUS_ACCEPTED
            && empty($input['force'])) {
            throw new \RuntimeException('quotation_must_be_accepted');
        }

        $existingCustomerId = CrmSupport::intOrNull($quote['customer_id'] ?? null);
        if ($existingCustomerId !== null && $existingCustomerId > 0) {
            $this->linkCustomerAcross($quote, $existingCustomerId);
            $this->logConversion('quotation_to_customer', 'quotation', $quotationId, 'customer', $existingCustomerId, [
                'reused' => true,
            ]);

            return ['customer_id' => $existingCustomerId, 'code' => ''];
        }

        $name = trim((string) ($input['name'] ?? ($quote['title'] ?? 'Customer')));
        if ($name === '') {
            $name = 'Customer';
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = 'CU-' . date('Y') . '-' . str_pad((string) (random_int(1, 99999)), 5, '0', STR_PAD_LEFT);
        }

        $customerId = (int) (new Customer())->create([
            'company_id' => $companyId,
            'branch_id' => CrmSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'phone' => CrmSupport::nullIfEmpty($input['phone'] ?? null),
            'email' => CrmSupport::nullIfEmpty($input['email'] ?? null),
            'is_active' => 1,
            'crm_lifecycle_stage' => 'opportunity',
            'crm_owner_user_id' => CrmSupport::intOrNull($quote['owner_user_id'] ?? null) ?? CrmSupport::userId(),
            'notes' => 'Created from CRM quotation ' . ($quote['quotation_no'] ?? $quotationId),
        ]);

        try {
            (new CrmLifecycleService())->transition($customerId, 'customer', 'quotation_to_customer', [
                'quotation_id' => $quotationId,
            ]);
            (new CrmSalesTeamService())->applyOwnershipRules($customerId, 'customer');
        } catch (\Throwable $e) {
            // lifecycle best-effort when columns/tables not yet migrated
        }

        $this->linkCustomerAcross($quote, $customerId);
        $this->logConversion('quotation_to_customer', 'quotation', $quotationId, 'customer', $customerId, [
            'quotation_no' => $quote['quotation_no'] ?? null,
            'customer_code' => $code,
        ]);

        (new CrmTimelineService())->record(
            'conversion_quotation_customer',
            'Converted quotation to customer #' . $customerId,
            null,
            'customer',
            $customerId,
            [
                'lead_id' => CrmSupport::intOrNull($quote['lead_id'] ?? null),
                'opportunity_id' => CrmSupport::intOrNull($quote['opportunity_id'] ?? null),
                'customer_id' => $customerId,
            ]
        );

        (new CrmRevenueTrackingService())->record(
            'customer_from_quote',
            (float) ($quote['total_amount'] ?? 0),
            (string) ($quote['currency_code'] ?? 'SAR'),
            [
                'lead_id' => CrmSupport::intOrNull($quote['lead_id'] ?? null),
                'opportunity_id' => CrmSupport::intOrNull($quote['opportunity_id'] ?? null),
                'quotation_id' => $quotationId,
                'customer_id' => $customerId,
            ],
            ['customer_code' => $code]
        );

        return ['customer_id' => $customerId, 'code' => $code];
    }

    /**
     * @param array<string, mixed> $quote
     */
    private function linkCustomerAcross(array $quote, int $customerId): void
    {
        $qid = (int) ($quote['id'] ?? 0);
        if ($qid > 0) {
            (new CrmQuotation())->update($qid, array_merge([
                'customer_id' => $customerId,
            ], CrmSupport::actorFields(false)));
        }
        $oppId = CrmSupport::intOrNull($quote['opportunity_id'] ?? null);
        if ($oppId !== null) {
            (new CrmOpportunity())->update($oppId, array_merge([
                'customer_id' => $customerId,
            ], CrmSupport::actorFields(false)));
        }
        $leadId = CrmSupport::intOrNull($quote['lead_id'] ?? null);
        if ($leadId !== null) {
            (new CrmLead())->update($leadId, array_merge([
                'customer_id' => $customerId,
            ], CrmSupport::actorFields(false)));
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function logConversion(
        string $type,
        string $fromType,
        int $fromId,
        string $toType,
        int $toId,
        array $meta = []
    ): void {
        $companyId = CrmSupport::requireCompanyId();
        (new CrmConversion())->create([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'conversion_type' => substr($type, 0, 60),
            'from_type' => substr($fromType, 0, 40),
            'from_id' => $fromId,
            'to_type' => substr($toType, 0, 40),
            'to_id' => $toId,
            'meta_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_by' => CrmSupport::userId(),
        ]);

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.conversion.' . $type, $toType, $toId, [
                'from_type' => $fromType,
                'from_id' => $fromId,
                'meta' => $meta,
            ]);
        }
    }
}
