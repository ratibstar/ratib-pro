<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Conversation;

use Ratib\ContactCenter\App\Application\Services\SoftphoneErpService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\ErpBridge;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\CustomerIdentityRepository;

/**
 * Resolves customer identity from phone, email, ERP ID, or history.
 */
final class IdentityResolver
{
    public function __construct(
        private readonly ?CustomerIdentityRepository $identityRepo = null,
        private readonly ?SoftphoneErpService $erpService = null
    ) {
    }

    /**
     * @return array{customer_id: int|null, contact_id: int|null, confidence: float, matched_by: string, identity: string}
     */
    public function resolve(
        int $tenantId,
        ?string $phone = null,
        ?string $email = null,
        ?int $erpCustomerId = null
    ): array {
        $repo = $this->identityRepo ?? new CustomerIdentityRepository();
        $normalizedPhone = $this->normalizePhone($phone);
        $normalizedEmail = $email !== null ? strtolower(trim($email)) : null;

        if ($normalizedPhone !== '') {
            $mapped = $repo->findByPhone($tenantId, $normalizedPhone);
            if ($mapped !== null) {
                return $this->result($mapped, 0.98, 'phone+map');
            }
        }

        if ($normalizedEmail !== null && $normalizedEmail !== '') {
            $mapped = $repo->findByEmail($tenantId, $normalizedEmail);
            if ($mapped !== null) {
                return $this->result($mapped, 0.96, 'email+map');
            }
        }

        if ($erpCustomerId !== null && $erpCustomerId > 0) {
            $mapped = $repo->findByErpCustomerId($tenantId, $erpCustomerId);
            if ($mapped !== null) {
                return $this->result($mapped, 0.94, 'erp+map');
            }
        }

        $erpProfile = null;
        if ($normalizedPhone !== '') {
            $erpProfile = ($this->erpService ?? new SoftphoneErpService())
                ->customerProfileByPhone($tenantId, $normalizedPhone);
        }

        $contactId = null;
        if (is_array($erpProfile['contact'] ?? null)) {
            $contactId = (int) $erpProfile['contact']['id'];
        } elseif ($normalizedPhone !== '') {
            $contactId = $this->findContactByPhone($tenantId, $normalizedPhone);
        } elseif ($normalizedEmail !== null) {
            $contactId = $this->findContactByEmail($tenantId, $normalizedEmail);
        }

        $identity = $normalizedPhone !== '' ? $normalizedPhone : ($normalizedEmail ?? ('erp:' . ($erpCustomerId ?? 0)));
        $matchedBy = 'phone+erp';
        $confidence = 0.85;

        if ($contactId === null && $erpCustomerId !== null && $erpCustomerId > 0) {
            $company = ErpBridge::companyById($erpCustomerId);
            if ($company !== null) {
                $matchedBy = 'erp_company';
                $confidence = 0.80;
            }
        }

        if ($contactId !== null) {
            $repo->upsert($tenantId, $contactId, $normalizedPhone ?: null, $normalizedEmail, $erpCustomerId, $matchedBy, $confidence);
        }

        return [
            'customer_id' => $contactId,
            'contact_id' => $contactId,
            'confidence' => $confidence,
            'matched_by' => $matchedBy,
            'identity' => $identity,
            'erp_profile' => $erpProfile,
        ];
    }

    /** @param array<string, mixed> $mapped */
    private function result(array $mapped, float $confidence, string $matchedBy): array
    {
        $phone = (string) ($mapped['phone'] ?? '');
        $email = (string) ($mapped['email'] ?? '');
        $identity = $phone !== '' ? $phone : ($email !== '' ? $email : 'contact:' . ($mapped['contact_id'] ?? 0));

        return [
            'customer_id' => isset($mapped['contact_id']) ? (int) $mapped['contact_id'] : null,
            'contact_id' => isset($mapped['contact_id']) ? (int) $mapped['contact_id'] : null,
            'confidence' => (float) ($mapped['confidence'] ?? $confidence),
            'matched_by' => (string) ($mapped['matched_by'] ?? $matchedBy),
            'identity' => $identity,
            'erp_profile' => null,
        ];
    }

    private function normalizePhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function findContactByPhone(int $tenantId, string $digits): ?int
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT id FROM rcc_contacts
                 WHERE tenant_id = :tid AND deleted_at IS NULL
                   AND REPLACE(REPLACE(REPLACE(phone_primary, \'+\', \'\'), \' \', \'\'), \'-\', \'\') LIKE :phone
                 LIMIT 1'
            );
            $stmt->execute(['tid' => $tenantId, 'phone' => '%' . substr($digits, -9)]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function findContactByEmail(int $tenantId, string $email): ?int
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT id FROM rcc_contacts WHERE tenant_id = :tid AND deleted_at IS NULL AND email = :email LIMIT 1'
            );
            $stmt->execute(['tid' => $tenantId, 'email' => $email]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
