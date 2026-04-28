<?php
declare(strict_types=1);

namespace App\Core;

use App\Events\WorkflowStepLifecycle;
use App\Repositories\WorkflowStateRepository;
use Throwable;

final class WorkflowEngine
{
    public function __construct(
        private readonly EventDispatcher $events,
        private readonly WorkflowStateRepository $workflowStates
    )
    {
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $modeContext
     * @return array<string, mixed>
     */
    public function run(Workflow $workflow, array $context, array $modeContext = []): array
    {
        $context['execution_mode'] = (string) ($modeContext['mode'] ?? 'commercial');
        $context['policy'] = $modeContext;
        $context['event_chain'] = is_array($context['event_chain'] ?? null) ? $context['event_chain'] : [];
        $context['sequence_number'] = (int) ($context['sequence_number'] ?? 1);
        $workflowId = (int) ($context['workflow_id'] ?? 0);
        if ($workflowId > 0) {
            $state = $this->workflowStates->latestByWorkflowId($workflowId);
            if ($state !== null) {
                $stateStatus = (string) ($state['status'] ?? '');
                $stateContext = is_array($state['context_json'] ?? null) ? $state['context_json'] : [];
                if ($stateStatus === 'completed') {
                    return $stateContext;
                }
                if ($stateStatus === 'running' && !isset($context['resume_from_step_index'])) {
                    if ($this->workflowStates->isRunningStateStale($state)) {
                        $stateContext['stalled_at'] = gmdate('c');
                        $this->workflowStates->persist($workflowId, (string) ($state['current_step'] ?? 'unknown'), 'stalled', $stateContext, 'Detected stalled running workflow.');
                        $context = array_merge($stateContext, $context);
                        $context['resume_from_step_index'] = (int) ($stateContext['last_successful_step_index'] ?? -1) + 1;
                    } else {
                        throw new WorkflowExecutionException('Workflow already running.', $stateContext);
                    }
                }
                if (in_array($stateStatus, ['failed', 'stalled'], true) || isset($context['resume_from_step_index'])) {
                    $context = array_merge($stateContext, $context);
                    $context['resume_from_step_index'] = (int) ($stateContext['last_successful_step_index'] ?? -1) + 1;
                }
            }
        }

        foreach ($workflow->steps() as $stepIndex => $step) {
            $attempt = 0;
            $retryOverride = $modeContext['max_retries_override'] ?? null;
            $configuredRetries = $retryOverride !== null ? (int) $retryOverride : $workflow->maxRetries();
            $maxRetries = max(1, $configuredRetries);
            $optionalSteps = is_array($context['optional_steps'] ?? null) ? $context['optional_steps'] : [];
            $isOptionalStep = in_array($step::class, $optionalSteps, true);
            $resumeIndex = (int) ($context['resume_from_step_index'] ?? 0);

            if ($stepIndex < $resumeIndex) {
                $context['last_successful_step_index'] = $stepIndex;
                continue;
            }

            while (true) {
                try {
                    $entryStarted = [
                        'sequence_number' => ++$context['sequence_number'],
                        'event' => 'step_started',
                        'step' => $step::class,
                        'timestamp' => gmdate('c'),
                    ];
                    $context['event_chain'][] = $entryStarted;
                    $this->events->event(new WorkflowStepLifecycle($context['execution_mode'], $entryStarted));
                    $context = $step->execute($context);
                    $entryCompleted = [
                        'sequence_number' => ++$context['sequence_number'],
                        'event' => 'step_completed',
                        'step' => $step::class,
                        'timestamp' => gmdate('c'),
                    ];
                    $context['event_chain'][] = $entryCompleted;
                    $this->events->event(new WorkflowStepLifecycle($context['execution_mode'], $entryCompleted));
                    $context['last_successful_step'] = $step::class;
                    $context['last_successful_step_index'] = $stepIndex;
                    if (isset($context['workflow_id'])) {
                        $this->workflowStates->persist((int) $context['workflow_id'], $step::class, 'running', $context);
                    }
                    break;
                } catch (Throwable $exception) {
                    $attempt++;
                    $entryFailed = [
                        'sequence_number' => ++$context['sequence_number'],
                        'event' => 'step_failed',
                        'step' => $step::class,
                        'error' => $exception->getMessage(),
                        'timestamp' => gmdate('c'),
                    ];
                    $context['event_chain'][] = $entryFailed;
                    $this->events->event(new WorkflowStepLifecycle($context['execution_mode'], $entryFailed));

                    if (($modeContext['soft_validation'] ?? false) === true && $isOptionalStep) {
                        // Commercial mode allows optional step failures without blocking full workflow.
                        break;
                    }

                    if ($attempt >= $maxRetries) {
                        $context['workflow_error'] = $exception->getMessage();
                        $context['failed_step'] = $step::class;
                        if (isset($context['workflow_id'])) {
                            $this->workflowStates->persist(
                                (int) $context['workflow_id'],
                                $step::class,
                                'failed',
                                $context,
                                $exception->getMessage()
                            );
                        }
                        throw new WorkflowExecutionException($exception->getMessage(), $context, $exception);
                    }
                }
            }
        }

        if (isset($context['workflow_id'])) {
            $this->workflowStates->persist((int) $context['workflow_id'], '__completed__', 'completed', $context);
        }
        return $context;
    }

    public function resume(Workflow $workflow, int $workflowId, array $modeContext = []): array
    {
        $state = $this->workflowStates->latestByWorkflowId($workflowId);
        if ($state === null) {
            throw new WorkflowExecutionException('Workflow state not found for resume.', ['workflow_id' => $workflowId]);
        }
        $context = is_array($state['context_json'] ?? null) ? $state['context_json'] : [];
        $context['workflow_id'] = $workflowId;
        $context['resume_from_step_index'] = (int) ($context['last_successful_step_index'] ?? -1) + 1;
        return $this->run($workflow, $context, $modeContext);
    }
}
