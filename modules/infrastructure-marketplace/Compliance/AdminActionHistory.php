<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Compliance;

use Ratib\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;

final class AdminActionHistory
{
    public function __construct(
        private readonly InfrastructureAuditLogger $audit
    ) {}

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

