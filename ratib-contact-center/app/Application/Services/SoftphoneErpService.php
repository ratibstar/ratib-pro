<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\ErpBridge;

/**
 * ERP customer context for active calls (read-only).
 */
final class SoftphoneErpService
{
    /** @return array<string, mixed>|null */
    public function customerProfileByPhone(int $tenantId, string $phone): ?array
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';
        if ($normalized === '') {
            return null;
        }

        $contact = $this->findContact($tenantId, $normalized);
        $tickets = $contact !== null ? $this->recentTickets($tenantId, (int) $contact['id']) : [];

        return [
            'phone' => $phone,
            'contact' => $contact,
            'company' => $contact !== null ? $this->contactCompany($tenantId, $contact) : null,
            'recent_tickets' => $tickets,
            'sla_status' => $this->slaStatusForContact($tenantId, $contact),
        ];
    }

    /** @return array<string, mixed>|null */
    private function findContact(int $tenantId, string $digits): ?array
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT id, full_name, email, phone_primary, contact_type, company_id, status
                 FROM rcc_contacts
                 WHERE tenant_id = :tid AND deleted_at IS NULL
                   AND REPLACE(REPLACE(REPLACE(phone_primary, \'+\', \'\'), \' \', \'\'), \'-\', \'\') LIKE :phone
                 LIMIT 1'
            );
            $stmt->execute(['tid' => $tenantId, 'phone' => '%' . substr($digits, -9)]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @param array<string, mixed> $contact */
    private function contactCompany(int $tenantId, array $contact): ?array
    {
        if (empty($contact['company_id'])) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT id, name, name_ar, phone, email FROM rcc_contact_companies
             WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => (int) $contact['company_id']]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    private function recentTickets(int $tenantId, int $contactId): array
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT ticket_no, subject, status, priority, created_at
                 FROM rcc_tickets
                 WHERE tenant_id = :tid AND contact_id = :cid
                 ORDER BY created_at DESC LIMIT 5'
            );
            $stmt->execute(['tid' => $tenantId, 'cid' => $contactId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @param array<string, mixed>|null $contact */
    private function slaStatusForContact(int $tenantId, ?array $contact): string
    {
        if ($contact === null) {
            return 'unknown';
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rcc_tickets
                 WHERE tenant_id = :tid AND contact_id = :cid AND status IN ('open','in_progress','pending')
                   AND resolution_due IS NOT NULL AND resolution_due < NOW()"
            );
            $stmt->execute(['tid' => $tenantId, 'cid' => (int) $contact['id']]);
            $breached = (int) $stmt->fetchColumn();
            return $breached > 0 ? 'breached' : 'ok';
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }
}
