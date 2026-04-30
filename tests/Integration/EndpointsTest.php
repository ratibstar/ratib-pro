<?php
declare(strict_types=1);

return [
    'API v1 entry points exist' => static function (): void {
        $root = dirname(__DIR__, 2);
        $paths = [
            $root . '/api/v1/index.php',
            $root . '/api/v1/workers/index.php',
            $root . '/api/v1/tracking/index.php',
            $root . '/api/v1/workflows/index.php',
            $root . '/api/v1/alerts/index.php',
        ];
        foreach ($paths as $p) {
            t_assert_true(is_file($p), 'Missing endpoint file: ' . $p);
        }
    },
    'Workflow onboarding endpoint exists' => static function (): void {
        $root = dirname(__DIR__, 2);
        $path = $root . '/public/workflows/worker-onboarding/index.php';
        t_assert_true(is_file($path), 'Workflow onboarding endpoint missing.');
    },
    'Worker platform includes system health route matcher' => static function (): void {
        $root = dirname(__DIR__, 2);
        $content = file_get_contents($root . '/public/worker-platform.php');
        t_assert_true(is_string($content) && str_contains($content, "GET /system/health"), 'Expected system health route mapping.');
    },
];
