<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowReadRepositoryInterface;

final class MysqlWorkflowReadRepository extends BaseRepository implements WorkflowReadRepositoryInterface
{
    protected function table(): string
    {
        return 'workflow_transitions';
    }

    public function findTransition(string $fromStatus, string $action): ?array
    {
        return $this->fetchOne(
            'SELECT from_state, to_state, action, requires_permission
             FROM workflow_transitions
             WHERE from_state = :from_state AND action = :action
             LIMIT 1',
            ['from_state' => $fromStatus, 'action' => $action]
        );
    }

    public function listStates(): array
    {
        return $this->fetchAll(
            'SELECT code, name, is_terminal, sort_order FROM workflow_states ORDER BY sort_order ASC'
        );
    }
}
