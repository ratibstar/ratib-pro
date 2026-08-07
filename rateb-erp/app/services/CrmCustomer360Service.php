<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmActivity;
use Rateb\App\Models\CrmCall;
use Rateb\App\Models\CrmCompany;
use Rateb\App\Models\CrmContact;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmMeeting;
use Rateb\App\Models\CrmOpportunity;
use Rateb\App\Models\CrmQuotation;
use Rateb\App\Models\CrmTask;
use Rateb\App\Models\Customer;

/**
 * Phase 3 — Customer 360 aggregate (read-only).
 * Payments/Invoices are link refs only — no AccountingService usage.
 */
final class CrmCustomer360Service
{
    /**
     * @return array<string, mixed>
     */
    public function assemble(int $customerId): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $customer = (new Customer())->queryOne(
            'SELECT id, code, name, name_ar, phone, email, is_active, notes, branch_id
             FROM rateb_customers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $customerId, 'cid' => $companyId]
        );
        if (!is_array($customer)) {
            throw new \RuntimeException('customer_not_found');
        }

        $crmCompanies = (new CrmCompany())->query(
            'SELECT * FROM rateb_crm_companies
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
             ORDER BY name ASC LIMIT 50',
            ['cid' => $companyId, 'cuid' => $customerId]
        );
        $contacts = (new CrmContact())->query(
            'SELECT * FROM rateb_crm_contacts
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
             ORDER BY is_primary DESC, full_name ASC LIMIT 50',
            ['cid' => $companyId, 'cuid' => $customerId]
        );
        $leads = (new CrmLead())->query(
            'SELECT id, lead_no, title, workflow_status, estimated_value, owner_user_id, updated_at
             FROM rateb_crm_leads
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
             ORDER BY updated_at DESC LIMIT 50',
            ['cid' => $companyId, 'cuid' => $customerId]
        );
        $opportunities = (new CrmOpportunity())->query(
            'SELECT id, opportunity_no, name, amount, probability_percent, workflow_status, stage_id, owner_user_id,
                    ROUND(amount * probability_percent / 100, 2) AS expected_revenue
             FROM rateb_crm_opportunities
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
             ORDER BY updated_at DESC LIMIT 50',
            ['cid' => $companyId, 'cuid' => $customerId]
        );
        $quotations = (new CrmQuotation())->query(
            'SELECT id, quotation_no, title, status, total_amount, currency_code, valid_until, updated_at
             FROM rateb_crm_quotations
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
             ORDER BY updated_at DESC LIMIT 50',
            ['cid' => $companyId, 'cuid' => $customerId]
        );
        $activities = (new CrmActivity())->query(
            'SELECT id, activity_type, subject, activity_at, due_at, priority, status, owner_user_id
             FROM rateb_crm_activities
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
             ORDER BY COALESCE(activity_at, created_at) DESC LIMIT 50',
            ['cid' => $companyId, 'cuid' => $customerId]
        );
        $tasks = (new CrmTask())->query(
            'SELECT id, subject, due_at, reminder_at, priority, status, owner_user_id, completed_at
             FROM rateb_crm_tasks
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
             ORDER BY COALESCE(due_at, created_at) DESC LIMIT 50',
            ['cid' => $companyId, 'cuid' => $customerId]
        );
        $calls = (new CrmCall())->query(
            'SELECT id, subject, called_at, direction, status, owner_user_id
             FROM rateb_crm_calls
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
             ORDER BY called_at DESC LIMIT 30',
            ['cid' => $companyId, 'cuid' => $customerId]
        );
        $meetings = (new CrmMeeting())->query(
            'SELECT id, subject, starts_at, status, owner_user_id
             FROM rateb_crm_meetings
             WHERE company_id = :cid AND customer_id = :cuid AND deleted_at IS NULL
             ORDER BY starts_at DESC LIMIT 30',
            ['cid' => $companyId, 'cuid' => $customerId]
        );

        // Link-only refs — never call AccountingService.
        $invoiceLinks = $this->safeLinks(
            'SELECT id, invoice_no AS label, status, total_amount AS amount
             FROM rateb_invoices WHERE company_id = :cid AND customer_id = :cuid
             ORDER BY id DESC LIMIT 20',
            ['cid' => $companyId, 'cuid' => $customerId],
            'invoices'
        );
        if ($invoiceLinks === []) {
            $invoiceLinks = $this->safeLinks(
                'SELECT id, invoice_no AS label, status, total_amount AS amount
                 FROM rateb_invoices WHERE company_id = :cid AND buyer_legal_name = :nm
                 ORDER BY id DESC LIMIT 20',
                ['cid' => $companyId, 'nm' => (string) ($customer['name'] ?? '')],
                'invoices'
            );
        }
        $paymentLinks = $this->safeLinks(
            'SELECT id, COALESCE(reference_no, CAST(id AS CHAR)) AS label, status, amount
             FROM rateb_payments WHERE company_id = :cid AND customer_id = :cuid
             ORDER BY id DESC LIMIT 20',
            ['cid' => $companyId, 'cuid' => $customerId],
            'payments'
        );

        return [
            'customer' => $customer,
            'crm_companies' => is_array($crmCompanies) ? $crmCompanies : [],
            'contacts' => is_array($contacts) ? $contacts : [],
            'leads' => is_array($leads) ? $leads : [],
            'opportunities' => is_array($opportunities) ? $opportunities : [],
            'quotations' => is_array($quotations) ? $quotations : [],
            'activities' => is_array($activities) ? $activities : [],
            'tasks' => is_array($tasks) ? $tasks : [],
            'calls' => is_array($calls) ? $calls : [],
            'meetings' => is_array($meetings) ? $meetings : [],
            'timeline' => (new CrmTimelineService())->listForCustomer($customerId, 60),
            'invoice_links' => $invoiceLinks,
            'payment_links' => $paymentLinks,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array{id:int,label:string,status:string,amount:string,href:?string}>
     */
    private function safeLinks(string $sql, array $params, string $route): array
    {
        try {
            $rows = (new Customer())->query($sql, $params);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            $out[] = [
                'id' => $id,
                'label' => (string) ($row['label'] ?? ('#' . $id)),
                'status' => (string) ($row['status'] ?? ''),
                'amount' => (string) ($row['amount'] ?? ''),
                'href' => (function_exists('rateb_url') && function_exists('rateb_app_route') && $id > 0)
                    ? rateb_url(rateb_app_route($route) . '/' . $id)
                    : null,
            ];
        }

        return $out;
    }
}
