<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\CrmCompany;
use Rateb\App\Models\CrmContact;
use Rateb\App\Models\CrmLead;
use Rateb\App\Models\CrmOpportunity;

/**
 * Tenant + branch isolation for CRM offline replay (Phase 17B).
 * Additive — does not alter CRM Online domain services.
 */
final class CrmOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, lead?: array<string, mixed>}
     */
    public function assertLead(int $leadId, array $scope): array
    {
        if ($leadId < 1) {
            return ['ok' => false, 'error' => 'invalid_lead_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new CrmLead())->queryOne(
            'SELECT * FROM rateb_crm_leads WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $leadId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'lead_not_found'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'lead' => $row];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, opportunity?: array<string, mixed>}
     */
    public function assertOpportunity(int $opportunityId, array $scope): array
    {
        if ($opportunityId < 1) {
            return ['ok' => false, 'error' => 'invalid_opportunity_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new CrmOpportunity())->queryOne(
            'SELECT * FROM rateb_crm_opportunities WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $opportunityId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'opportunity_not_found'];
        }

        return ['ok' => true, 'opportunity' => $row];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, contact?: array<string, mixed>}
     */
    public function assertContact(int $contactId, array $scope): array
    {
        if ($contactId < 1) {
            return ['ok' => false, 'error' => 'invalid_contact_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        $row = (new CrmContact())->queryOne(
            'SELECT * FROM rateb_crm_contacts WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $contactId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'contact_not_found'];
        }

        return ['ok' => true, 'contact' => $row];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, company?: array<string, mixed>}
     */
    public function assertCrmCompany(int $crmCompanyId, array $scope): array
    {
        if ($crmCompanyId < 1) {
            return ['ok' => false, 'error' => 'invalid_crm_company_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        $row = (new CrmCompany())->queryOne(
            'SELECT * FROM rateb_crm_companies WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $crmCompanyId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'crm_company_not_found'];
        }

        return ['ok' => true, 'company' => $row];
    }

    public function leadExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        $key = trim($idempotencyKey);
        if ($companyId < 1 || $key === '') {
            return null;
        }
        $marker = '%[offline:' . $key . ']%';
        $row = (new CrmLead())->queryOne(
            'SELECT id FROM rateb_crm_leads
             WHERE company_id = :cid AND deleted_at IS NULL AND notes LIKE :m
             LIMIT 1',
            ['cid' => $companyId, 'm' => $marker]
        );

        return $row !== null ? (int) ($row['id'] ?? 0) : null;
    }
}
