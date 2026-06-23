<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Portal;

use Ratib\ContactCenter\App\Application\Services\Billing\InvoiceService;
use Ratib\ContactCenter\App\Application\Services\Crm\CustomerProfileService;
use Ratib\ContactCenter\App\Application\Services\Crm\CustomerTimelineService;
use Ratib\ContactCenter\App\Application\Services\Knowledge\KnowledgeBaseService;
use Ratib\ContactCenter\App\Application\Services\Tickets\TicketSlaService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Security\PortalAuthContext;

final class CustomerPortalService
{
    public function __construct(
        private readonly CustomerProfileService $profiles = new CustomerProfileService(),
        private readonly CustomerTimelineService $timeline = new CustomerTimelineService(),
        private readonly InvoiceService $invoices = new InvoiceService(),
        private readonly KnowledgeBaseService $kb = new KnowledgeBaseService(),
        private readonly TicketSlaService $sla = new TicketSlaService()
    ) {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $tenantId = PortalAuthContext::tenantId();
        $contactId = PortalAuthContext::contactId();
        $openTickets = $this->contactTickets($tenantId, $contactId, 'open');
        $conversations = $this->contactConversations($tenantId, $contactId);
        $invoices = array_filter($this->invoices->list($tenantId), static fn ($i) => in_array($i['status'], ['open', 'paid'], true));
        return [
            'profile' => $this->profiles->contactProfile($tenantId, $contactId),
            'open_tickets' => count($openTickets),
            'conversations' => count($conversations),
            'invoices_open' => count(array_filter($invoices, static fn ($i) => $i['status'] === 'open')),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function tickets(): array
    {
        return $this->contactTickets(PortalAuthContext::tenantId(), PortalAuthContext::contactId());
    }

    /** @return list<array<string, mixed>> */
    public function conversations(): array
    {
        return $this->contactConversations(PortalAuthContext::tenantId(), PortalAuthContext::contactId());
    }

    public function crmProfile(): array
    {
        return $this->profiles->contactProfile(PortalAuthContext::tenantId(), PortalAuthContext::contactId());
    }

    /** @return list<array<string, mixed>> */
    public function timeline(): array
    {
        return $this->timeline->timeline(PortalAuthContext::tenantId(), PortalAuthContext::contactId());
    }

    /** @return list<array<string, mixed>> */
    public function invoices(): array
    {
        return $this->invoices->list(PortalAuthContext::tenantId());
    }

    /** @return list<array<string, mixed>> */
    public function payments(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.* FROM rcc_payments p
             INNER JOIN rcc_invoices i ON i.id = p.invoice_id AND i.tenant_id = p.tenant_id
             WHERE p.tenant_id = :tid ORDER BY p.created_at DESC LIMIT 50'
        );
        $stmt->execute(['tid' => PortalAuthContext::tenantId()]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function recordings(): array
    {
        $contactId = PortalAuthContext::contactId();
        $stmt = Database::connection()->prepare(
            'SELECT id, file_name, duration_seconds, created_at FROM rcc_recordings
             WHERE tenant_id = :tid AND contact_id = :cid ORDER BY created_at DESC LIMIT 50'
        );
        $stmt->execute(['tid' => PortalAuthContext::tenantId(), 'cid' => $contactId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function knowledgeBase(string $query = ''): array
    {
        $tenantId = PortalAuthContext::tenantId();
        return $query !== '' ? $this->kb->search($tenantId, $query) : $this->kb->search($tenantId, 'help', 10);
    }

    /** @return array<string, mixed> */
    public function slaDashboard(): array
    {
        $tenantId = PortalAuthContext::tenantId();
        $tickets = $this->contactTickets($tenantId, PortalAuthContext::contactId());
        $breached = 0;
        foreach ($tickets as $t) {
            $sla = $this->sla->status($tenantId, (int) $t['id']);
            if (($sla['breached'] ?? false) === true) {
                $breached++;
            }
        }
        return ['total_tickets' => count($tickets), 'sla_breached' => $breached];
    }

    /** @return list<array<string, mixed>> */
    private function contactTickets(int $tenantId, int $contactId, ?string $status = null): array
    {
        $sql = 'SELECT id, ticket_no, subject, status, priority, created_at FROM rcc_tickets WHERE tenant_id = :tid AND contact_id = :cid';
        $params = ['tid' => $tenantId, 'cid' => $contactId];
        if ($status !== null) {
            $sql .= ' AND status = :st';
            $params['st'] = $status;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 100';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    private function contactConversations(int $tenantId, int $contactId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.id, c.channel, c.status, c.subject, c.updated_at
             FROM rcc_conversations c
             WHERE c.tenant_id = :tid AND c.contact_id = :cid
             ORDER BY c.updated_at DESC LIMIT 50'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $contactId]);
        return $stmt->fetchAll() ?: [];
    }
}
