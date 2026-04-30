<?php
declare(strict_types=1);

use App\Core\EventDispatcher;
use App\Core\Workflow;
use App\Core\WorkflowEngine;
use App\Core\WorkflowExecutionException;
use App\Repositories\WorkflowStateRepository;

final class TestStep implements \App\Core\Contracts\WorkflowStepInterface
{
    public function __construct(private string $name, private bool $shouldFail = false)
    {
    }

    public function execute(array $context): array
    {
        $context['steps'][] = $this->name;
        if ($this->shouldFail) {
            throw new RuntimeException('forced-failure:' . $this->name);
        }
        return $context;
    }
}

final class TestWorkflow extends Workflow
{
    /** @param list<\App\Core\Contracts\WorkflowStepInterface> $steps */
    public function __construct(private array $steps)
    {
    }

    public function steps(): array
    {
        return $this->steps;
    }
}

return [
    'Workflow normal execution completes all steps' => static function (): void {
        $engine = new WorkflowEngine(new EventDispatcher(), new WorkflowStateRepository(t_db()));
        $workflow = new TestWorkflow([new TestStep('a'), new TestStep('b')]);
        $result = $engine->run($workflow, []);
        t_assert_same(['a', 'b'], $result['steps'] ?? []);
    },
    'Workflow failure mid-step throws execution exception' => static function (): void {
        $engine = new WorkflowEngine(new EventDispatcher(), new WorkflowStateRepository(t_db()));
        $workflow = new TestWorkflow([new TestStep('a'), new TestStep('b', true)]);
        $thrown = false;
        try {
            $engine->run($workflow, [], ['max_retries_override' => 1]);
        } catch (WorkflowExecutionException) {
            $thrown = true;
        }
        t_assert_true($thrown, 'Expected WorkflowExecutionException on failing step.');
    },
    'Workflow resume simulation starts from requested step index' => static function (): void {
        $engine = new WorkflowEngine(new EventDispatcher(), new WorkflowStateRepository(t_db()));
        $workflow = new TestWorkflow([new TestStep('a'), new TestStep('b'), new TestStep('c')]);
        $result = $engine->run($workflow, ['resume_from_step_index' => 1]);
        t_assert_same(['b', 'c'], $result['steps'] ?? []);
    },
    'Duplicate idempotency key simulation stays blocked' => static function (): void {
        $locks = [];
        $acquire = static function (string $key) use (&$locks): string {
            if ($key === '') {
                return 'none';
            }
            if (isset($locks[$key]) && $locks[$key] === true) {
                return 'blocked';
            }
            $locks[$key] = true;
            return 'acquired';
        };
        t_assert_same('acquired', $acquire('same-idempotency-key'));
        t_assert_same('blocked', $acquire('same-idempotency-key'));
    },
];
