<?php
declare(strict_types=1);

/**
 * Global AI workflow entry (same folder as ai-lookup.php — always deployed with api/workers).
 */
(function (): void {
    $dir = realpath(__DIR__) ?: __DIR__;
    for ($i = 0; $i < 12; $i++) {
        $handler = $dir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'worker_onboarding_workflow.php';
        $handler = realpath($handler) ?: $handler;
        if (is_file($handler)) {
            require_once $handler;
            ratib_run_worker_onboarding_workflow(__DIR__);
            return;
        }
        $handlerAlt = $dir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'worker_onboarding_workflow.php';
        if (is_file($handlerAlt)) {
            require_once $handlerAlt;
            ratib_run_worker_onboarding_workflow(__DIR__);
            return;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Could not locate includes/worker_onboarding_workflow.php',
        'entry' => __DIR__,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
})();
