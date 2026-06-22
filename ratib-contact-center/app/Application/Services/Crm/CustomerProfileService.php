<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Crm;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\ErpBridge;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm\CrmAccountRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm\CrmActivityRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm\CrmContactRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm\CrmNoteRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm\CrmTagRepository;

final class CustomerProfileService
{
    public function __construct(
        private readonly CrmAccountRepository $accounts = new CrmAccountRepository(),
        private readonly CrmContactRepository $contacts = new CrmContactRepository(),
        private readonly CrmActivityRepository $activities = new CrmActivityRepository(),
        private readonly CrmNoteRepository $notes = new CrmNoteRepository(),
        private readonly CrmTagRepository $tags = new CrmTagRepository(),
        private readonly RccAuditService $audit = new RccAuditService()
    ) {
    }

    /** @return array<string, mixed> */
    public function accountProfile(int $tenantId, int $accountId): array
    {
        $account = $this->accounts->find($tenantId, $accountId);
        if ($account === null) {
            throw new \RuntimeException('Account not found', 404);
        }
        $contacts = $this->contacts->list($tenantId, $accountId);
        return ['account' => $account, 'contacts' => $contacts, 'contact_count' => count($contacts)];
    }

    /** @return array<string, mixed> */
    public function contactProfile(int $tenantId, int $contactId): array
    {
        $contact = $this->contacts->find($tenantId, $contactId);
        if ($contact === null) {
            throw new \RuntimeException('Contact not found', 404);
        }
        return [
            'contact' => $contact,
            'notes' => $this->notes->list($tenantId, $contactId),
            'tags' => $this->tags->listForContact($tenantId, $contactId),
            'timeline' => $this->activities->timeline($tenantId, $contactId),
            'interactions' => $this->activities->interactionHistory($tenantId, $contactId),
        ];
    }

    /** @param array<string, mixed> $data */
    public function saveAccount(int $tenantId, array $data, ?int $userId): array
    {
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $savedId = $this->accounts->save($tenantId, $data, $id);
        $this->audit->log($tenantId, 'crm.account.save', $userId, 'account', $savedId, $data);
        EventBus::instance()->emit([
            'type' => EventType::CRM_ACCOUNT_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['account_id' => $savedId],
        ]);
        return $this->accounts->find($tenantId, $savedId) ?? [];
    }

    /** @param array<string, mixed> $data */
    public function saveContact(int $tenantId, array $data, ?int $userId): array
    {
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $savedId = $this->contacts->save($tenantId, $data, $id);
        $this->activities->add($tenantId, $savedId, 'contact_updated', 'Contact profile saved', isset($data['account_id']) ? (int) $data['account_id'] : null);
        $this->audit->log($tenantId, 'crm.contact.save', $userId, 'contact', $savedId, $data);
        EventBus::instance()->emit([
            'type' => EventType::CRM_CONTACT_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['contact_id' => $savedId],
        ]);
        return $this->contacts->find($tenantId, $savedId) ?? [];
    }

    /** @return array<string, mixed> */
    public function syncFromErp(int $tenantId, int $erpCompanyId, ?int $userId): array
    {
        $company = ErpBridge::companyById($erpCompanyId);
        if ($company === null) {
            throw new \RuntimeException('ERP company not found', 404);
        }
        $accountId = $this->accounts->save($tenantId, [
            'name' => (string) ($company['name'] ?? 'ERP Company'),
            'email' => $company['email'] ?? null,
            'phone' => $company['phone'] ?? null,
            'erp_company_id' => $erpCompanyId,
            'account_type' => 'company',
        ]);
        $this->audit->log($tenantId, 'crm.erp.sync', $userId, 'account', $accountId, ['erp_company_id' => $erpCompanyId]);
        EventBus::instance()->emit([
            'type' => EventType::CRM_ERP_SYNCED,
            'tenant_id' => $tenantId,
            'payload' => ['account_id' => $accountId, 'erp_company_id' => $erpCompanyId],
        ]);
        return $this->accounts->find($tenantId, $accountId) ?? [];
    }
}
