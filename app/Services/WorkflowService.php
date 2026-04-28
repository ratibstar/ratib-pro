<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\EventDispatcher;
use App\Core\FrozenExecutionContext;
use App\Core\WorkflowEngine;
use App\Core\WorkflowExecutionException;
use App\Events\WorkflowExecutionCompleted;
use App\Events\WorkflowExecutionFailed;
use App\Events\WorkflowExecutionStarted;
use App\Repositories\WorkflowRepository;
use App\Workflows\WorkerOnboardingWorkflow;
use Throwable;

final class WorkflowService
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly WorkflowRepository $workflowRepository,
        private readonly WorkerOnboardingWorkflow $workerOnboardingWorkflow,
        private readonly FrozenExecutionContext $frozenExecutionContext,
        private readonly EventDispatcher $events
    ) {
    }

    /** @param array<string, mixed> $context */
    public function runWorkerOnboarding(array $context): array
    {
        $workflowName = 'WorkerOnboardingWorkflow';
        $mode = $this->frozenExecutionContext->mode;
        $policy = $this->frozenExecutionContext->policy;
        $actor = (string) ($context['actor'] ?? $context['notify_to'] ?? 'system');
        $context['system_mode'] = $mode;
        $context['actor'] = $actor;
        $context['event_chain'] = [];

        $workflowId = $this->workflowRepository->start($workflowName, $context);
        $this->events->event(new WorkflowExecutionStarted($workflowId, $workflowName, $mode, $actor, [], gmdate('c')));

        try {
            $result = $this->engine->run($this->workerOnboardingWorkflow, $context, $policy);
            $this->workflowRepository->complete($workflowId, $result);
            $eventChain = is_array($result['event_chain'] ?? null) ? $result['event_chain'] : [];
            $this->events->event(new WorkflowExecutionCompleted($workflowId, $workflowName, $mode, $actor, $eventChain, gmdate('c')));
            return [
                'workflow_id' => (string) $workflowId,
                'worker_id' => isset($result['worker']['id']) ? (int) $result['worker']['id'] : null,
            ];
        } catch (WorkflowExecutionException $exception) {
            $eventChain = is_array($exception->context['event_chain'] ?? null) ? $exception->context['event_chain'] : [];
            $this->events->event(new WorkflowExecutionFailed(
                $workflowId,
                $workflowName,
                $mode,
                $actor,
                $exception->getMessage(),
                $eventChain,
                gmdate('c')
            ));
            $this->workflowRepository->fail(
                $workflowId,
                (string) ($exception->context['failed_step'] ?? 'unknown'),
                $exception->getMessage()
            );
            throw $exception;
        } catch (Throwable $exception) {
            $this->events->event(new WorkflowExecutionFailed(
                $workflowId,
                $workflowName,
                $mode,
                $actor,
                $exception->getMessage(),
                [],
                gmdate('c')
            ));
            $this->workflowRepository->fail($workflowId, 'unknown', $exception->getMessage());
            throw $exception;
        }
    }
}
