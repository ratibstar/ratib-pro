<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\EventDispatcher;
use App\Core\FrozenExecutionContext;
use App\Core\IdempotencyService;
use App\Core\WorkflowEngine;
use App\Core\WorkflowExecutionException;
use InvalidArgumentException;
use App\Events\WorkflowExecutionCompleted;
use App\Events\WorkflowExecutionFailed;
use App\Events\WorkflowExecutionStarted;
use App\Repositories\WorkflowStateRepository;
use App\Repositories\WorkflowRepository;
use App\Workflows\WorkerOnboardingWorkflow;
use Throwable;

final class WorkflowService
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly WorkflowRepository $workflowRepository,
        private readonly WorkflowStateRepository $workflowStateRepository,
        private readonly WorkerOnboardingWorkflow $workerOnboardingWorkflow,
        private readonly FrozenExecutionContext $frozenExecutionContext,
        private readonly EventDispatcher $events,
        private readonly IdempotencyService $idempotencyService
    ) {
    }

    /** @param array<string, mixed> $context */
    public function runWorkerOnboarding(array $context): array
    {
        $workflowName = 'WorkerOnboardingWorkflow';
        $mode = $this->frozenExecutionContext->mode;
        $policy = $this->frozenExecutionContext->policy;
        $actor = (string) ($context['actor'] ?? $context['notify_to'] ?? 'system');
        $idempotencyKey = trim((string) ($context['idempotency_key'] ?? ''));
        $context['system_mode'] = $mode;
        $context['actor'] = $actor;
        $context['event_chain'] = [];
        $context['sequence_number'] = 1;

        $idempotency = $this->idempotencyService->acquire($idempotencyKey);
        if ($idempotency['status'] === 'blocked') {
            throw new InvalidArgumentException('Duplicate request in progress. Retry shortly.');
        }
        if ($idempotency['status'] === 'existing' && ($idempotency['workflow_id'] ?? null) !== null) {
            $existingWorkflowId = (int) $idempotency['workflow_id'];
            $existing = $this->workflowRepository->findById($existingWorkflowId);
            $existingContext = is_array($existing['context_json'] ?? null) ? $existing['context_json'] : [];
            $existingStatus = (string) ($existing['status'] ?? '');
            if ($existingStatus === 'failed') {
                $this->idempotencyService->releaseOnFailure($idempotencyKey);
            } else {
                return [
                    'workflow_id' => (string) $existingWorkflowId,
                    'worker_id' => isset($existingContext['worker']['id']) ? (int) $existingContext['worker']['id'] : null,
                ];
            }
        }

        $resumeWorkflowId = (int) ($context['resume_workflow_id'] ?? 0);
        if ($resumeWorkflowId > 0) {
            $state = $this->workflowStateRepository->latestByWorkflowId($resumeWorkflowId);
            if ($state !== null) {
                $stateStatus = (string) ($state['status'] ?? '');
                if ($stateStatus === 'completed') {
                    $existing = $this->workflowRepository->findById($resumeWorkflowId);
                    $existingContext = is_array($existing['context_json'] ?? null) ? $existing['context_json'] : [];
                    return [
                        'workflow_id' => (string) $resumeWorkflowId,
                        'worker_id' => isset($existingContext['worker']['id']) ? (int) $existingContext['worker']['id'] : null,
                    ];
                }
            }
        }

        if ($resumeWorkflowId > 0) {
            $state = $this->workflowStateRepository->latestByWorkflowId($resumeWorkflowId);
            if ($state !== null) {
                $stateContext = is_array($state['context_json'] ?? null) ? $state['context_json'] : [];
                $context = array_merge($stateContext, $context);
                $context['resume_from_step_index'] = (int) ($stateContext['last_successful_step_index'] ?? -1) + 1;
            }
            $workflowId = $resumeWorkflowId;
        } else {
            $workflowId = $this->workflowRepository->start($workflowName, $context);
        }
        $context['workflow_id'] = $workflowId;
        $this->idempotencyService->remember($idempotencyKey, $workflowId);
        $this->events->event(new WorkflowExecutionStarted($workflowId, $workflowName, $mode, $actor, 1, [], gmdate('c')));
        $this->workflowStateRepository->persist($workflowId, '__start__', 'running', $context);

        try {
            $result = $this->engine->run($this->workerOnboardingWorkflow, $context, $policy);
            $this->workflowRepository->complete($workflowId, $result);
            $eventChain = is_array($result['event_chain'] ?? null) ? $result['event_chain'] : [];
            $completedSequence = (int) ($result['sequence_number'] ?? 1) + 1;
            $this->events->event(new WorkflowExecutionCompleted($workflowId, $workflowName, $mode, $actor, $completedSequence, $eventChain, gmdate('c')));
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
                (int) ($exception->context['sequence_number'] ?? 1) + 1,
                $exception->getMessage(),
                $eventChain,
                gmdate('c')
            ));
            $this->workflowRepository->fail(
                $workflowId,
                (string) ($exception->context['failed_step'] ?? 'unknown'),
                $exception->getMessage()
            );
            $this->workflowStateRepository->persist(
                $workflowId,
                (string) ($exception->context['failed_step'] ?? 'unknown'),
                'failed',
                $exception->context,
                $exception->getMessage()
            );
            $this->idempotencyService->releaseOnFailure($idempotencyKey);
            throw $exception;
        } catch (Throwable $exception) {
            $this->events->event(new WorkflowExecutionFailed(
                $workflowId,
                $workflowName,
                $mode,
                $actor,
                (int) ($context['sequence_number'] ?? 1) + 1,
                $exception->getMessage(),
                [],
                gmdate('c')
            ));
            $this->workflowRepository->fail($workflowId, 'unknown', $exception->getMessage());
            $this->workflowStateRepository->persist(
                $workflowId,
                'unknown',
                'failed',
                ['error' => $exception->getMessage(), 'workflow_id' => $workflowId, 'actor' => $actor, 'system_mode' => $mode],
                $exception->getMessage()
            );
            $this->idempotencyService->releaseOnFailure($idempotencyKey);
            throw $exception;
        }
    }
}
