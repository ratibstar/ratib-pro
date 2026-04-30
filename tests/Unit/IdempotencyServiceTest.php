<?php
declare(strict_types=1);

use App\Core\IdempotencyService;
use App\Repositories\WorkflowRepository;

return [
    'IdempotencyService handles empty key acquire' => static function (): void {
        $pdo = t_db();
        $repo = new WorkflowRepository($pdo);
        $service = new IdempotencyService($repo);
        $result = $service->acquire('');
        t_assert_same('none', $result['status'] ?? null);
        t_assert_same(null, $result['workflow_id'] ?? null);
    },
    'IdempotencyService resolve empty key returns null' => static function (): void {
        $pdo = t_db();
        $repo = new WorkflowRepository($pdo);
        $service = new IdempotencyService($repo);
        t_assert_same(null, $service->resolveWorkflowId(''));
    },
];
