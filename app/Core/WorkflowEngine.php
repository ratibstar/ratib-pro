<?php
declare(strict_types=1);

namespace App\Core;

use App\Events\WorkflowStepLifecycle;
use Throwable;

final class WorkflowEngine
{
    public function __construct(private readonly EventDispatcher $events)
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

        foreach ($workflow->steps() as $step) {
            $attempt = 0;
            $retryOverride = $modeContext['max_retries_override'] ?? null;
            $configuredRetries = $retryOverride !== null ? (int) $retryOverride : $workflow->maxRetries();
            $maxRetries = max(1, $configuredRetries);
            $optionalSteps = is_array($context['optional_steps'] ?? null) ? $context['optional_steps'] : [];
            $isOptionalStep = in_array($step::class, $optionalSteps, true);

            while (true) {
                try {
                    $entryStarted = [
                        'event' => 'step_started',
                        'step' => $step::class,
                        'timestamp' => gmdate('c'),
                    ];
                    $context['event_chain'][] = $entryStarted;
                    $this->events->event(new WorkflowStepLifecycle($context['execution_mode'], $entryStarted));
                    $context = $step->execute($context);
                    $entryCompleted = [
                        'event' => 'step_completed',
                        'step' => $step::class,
                        'timestamp' => gmdate('c'),
                    ];
                    $context['event_chain'][] = $entryCompleted;
                    $this->events->event(new WorkflowStepLifecycle($context['execution_mode'], $entryCompleted));
                    break;
                } catch (Throwable $exception) {
                    $attempt++;
                    $entryFailed = [
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
                        throw new WorkflowExecutionException($exception->getMessage(), $context, $exception);
                    }
                }
            }
        }

        return $context;
    }
}
