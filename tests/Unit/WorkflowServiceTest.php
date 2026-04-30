<?php
declare(strict_types=1);

use App\Services\WorkflowService;

return [
    'WorkflowService exposes runWorkerOnboarding method' => static function (): void {
        $ref = new ReflectionClass(WorkflowService::class);
        t_assert_true($ref->hasMethod('runWorkerOnboarding'), 'WorkflowService must expose runWorkerOnboarding.');
    },
    'WorkflowService is final for behavior stability' => static function (): void {
        $ref = new ReflectionClass(WorkflowService::class);
        t_assert_true($ref->isFinal(), 'WorkflowService should remain final.');
    },
];
