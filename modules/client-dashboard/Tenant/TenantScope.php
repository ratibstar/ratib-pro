<?php
/**
 * Tenant-scoped context derived from session (no new session semantics).
 */
declare(strict_types=1);

final class RATEB_ClientDashboard_TenantScope
{
    /** @var int */
    public $userId;

    /** @var int */
    public $agencyId;

    /** @var int */
    public $countryId;

    public function __construct(int $userId, int $agencyId, int $countryId)
    {
        $this->userId = $userId;
        $this->agencyId = $agencyId;
        $this->countryId = $countryId;
    }

    public static function fromSession(): self
    {
        return new self(
            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0,
            (int) ($_SESSION['agency_id'] ?? 0),
            (int) ($_SESSION['country_id'] ?? 0)
        );
    }

    /**
     * @return array<string, int>
     */
    public function toMeta(): array
    {
        return [
            'user_id' => $this->userId,
            'agency_id' => $this->agencyId,
            'country_id' => $this->countryId,
        ];
    }

    /**
     * Prefix for cache keys / idempotency scoping (string-safe).
     */
    public function isolationKey(): string
    {
        return 'u' . $this->userId . ':a' . $this->agencyId . ':c' . $this->countryId;
    }
}
