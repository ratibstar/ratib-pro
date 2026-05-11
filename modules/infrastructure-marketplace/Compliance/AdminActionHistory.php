<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Compliance;

use Ratib\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;

final class AdminActionHistory
{
    private InfrastructureAuditLogger $audit;

    public function __construct(InfrastructureAuditLogger $audit) {
        $this->audit = $audit;
    }


    /**
     * @param array<string, mixed> $details
     */
    public function record(string $adminActor, string $action, array $details = []): void
    {
        $this->audit->appendImmutable('admin_action_history', [
            'actor' => $adminActor,
            'action' => $action,
            'details' => $details,
        ]);
    }
}

