<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\EventLogRepository;
use App\Repositories\WorkflowRepository;
use App\Repositories\WorkflowStateRepository;
use InvalidArgumentException;

final class WorkflowTimelineService
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly WorkflowStateRepository $states,
        private readonly EventLogRepository $events
    ) {
    }

    /** @return array<string, mixed> */
    public function getTimeline(int $workflowId): array
    {
        if ($workflowId <= 0) {
            throw new InvalidArgumentException('workflow id is required');
        }
        $wf = $this->workflows->findDetailedById($workflowId);
        if (!is_array($wf)) {
            throw new InvalidArgumentException('Workflow not found');
        }

        $state = $this->states->latestByWorkflowId($workflowId);
        $eventLogs = $this->events->listByWorkflowId($workflowId);
        $context = is_array($wf['context_json'] ?? null) ? $wf['context_json'] : [];
        $eventChain = is_array($context['event_chain'] ?? null) ? $context['event_chain'] : [];

        $steps = $this->buildStepHistory($eventChain, $state, $eventLogs);

        return [
            'workflow' => [
                'id' => (int) $wf['id'],
                'name' => (string) ($wf['name'] ?? ''),
                'status' => (string) ($wf['status'] ?? ''),
                'failed_step' => (string) ($wf['failed_step'] ?? ''),
                'created_at' => (string) ($wf['created_at'] ?? ''),
                'updated_at' => (string) ($wf['updated_at'] ?? ''),
            ],
            'timeline' => [
                'steps' => $steps,
                'event_chain' => $eventChain,
                'latest_state' => [
                    'current_step' => (string) ($state['current_step'] ?? ''),
                    'status' => (string) ($state['status'] ?? ''),
                    'updated_at' => (string) ($state['updated_at'] ?? ''),
                    'error_message' => (string) ($state['error_message'] ?? ''),
                ],
                'replay' => [
                    'context' => $context,
                    'events' => $eventLogs,
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $eventChain
     * @param array<string, mixed>|null $state
     * @param list<array<string, mixed>> $eventLogs
     * @return list<array<string, mixed>>
     */
    private function buildStepHistory(array $eventChain, ?array $state, array $eventLogs): array
    {
        $steps = [];
        foreach ($eventChain as $evt) {
            if (!is_array($evt)) {
                continue;
            }
            $stepName = (string) ($evt['step'] ?? $evt['name'] ?? '');
            if ($stepName === '') {
                continue;
            }
            $status = (string) ($evt['status'] ?? 'completed');
            $steps[] = [
                'step' => $stepName,
                'status' => $status,
                'started_at' => (string) ($evt['started_at'] ?? ''),
                'finished_at' => (string) ($evt['finished_at'] ?? ''),
                'error' => (string) ($evt['error'] ?? ''),
            ];
        }

        if ($steps === [] && is_array($state) && !empty($state['current_step'])) {
            $steps[] = [
                'step' => (string) $state['current_step'],
                'status' => (string) ($state['status'] ?? 'running'),
                'started_at' => '',
                'finished_at' => '',
                'error' => (string) ($state['error_message'] ?? ''),
            ];
        }

        foreach ($eventLogs as $log) {
            $name = (string) ($log['event_name'] ?? '');
            if (!in_array($name, ['WorkflowExecutionStarted', 'WorkflowExecutionCompleted', 'WorkflowExecutionFailed'], true)) {
                continue;
            }
            $payload = is_array($log['payload'] ?? null) ? $log['payload'] : [];
            $steps[] = [
                'step' => $name,
                'status' => strtolower(str_replace('WorkflowExecution', '', $name)),
                'started_at' => (string) ($log['created_at'] ?? ''),
                'finished_at' => (string) ($log['created_at'] ?? ''),
                'error' => (string) ($payload['error'] ?? ''),
            ];
        }

        return $steps;
    }
}
