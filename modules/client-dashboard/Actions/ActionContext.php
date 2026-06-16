<?php
declare(strict_types=1);

final class RATEB_ClientDashboard_Action_Context
{
    /** @var mysqli|null */
    public $conn;

    /** @var int */
    public $userId;

    /** @var string */
    public $verb;

    /** @var string */
    public $targetId;

    /** @var array<string, mixed> */
    public $input;

    /** @var RATEB_ClientDashboard_ObservabilityHub */
    public $obs;

    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        ?mysqli $conn,
        int $userId,
        string $verb,
        string $targetId,
        array $input,
        RATEB_ClientDashboard_ObservabilityHub $obs
    ) {
        $this->conn = $conn;
        $this->userId = $userId;
        $this->verb = $verb;
        $this->targetId = $targetId;
        $this->input = $input;
        $this->obs = $obs;
    }
}
