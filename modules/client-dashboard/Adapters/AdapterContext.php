<?php
declare(strict_types=1);

final class RATEB_ClientDashboard_AdapterContext
{
    /** @var mysqli|null */
    public $conn;

    /** @var int */
    public $userId;

    /** @var array<string, mixed> */
    public $tenant;

    /** @var RATEB_ClientDashboard_ObservabilityHub */
    public $obs;

    /**
     * @param array<string, mixed> $tenant
     */
    public function __construct(?mysqli $conn, int $userId, array $tenant, RATEB_ClientDashboard_ObservabilityHub $obs)
    {
        $this->conn = $conn;
        $this->userId = $userId;
        $this->tenant = $tenant;
        $this->obs = $obs;
    }

    public static function fromSession(?mysqli $conn, RATEB_ClientDashboard_ObservabilityHub $obs): self
    {
        $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        $tenant = [
            'agency_id' => (int) ($_SESSION['agency_id'] ?? 0),
            'country_id' => (int) ($_SESSION['country_id'] ?? 0),
        ];

        return new self($conn, $uid, $tenant, $obs);
    }
}
