<?php
declare(strict_types=1);

use App\Repositories\AlertRepository;
use App\Services\AlertService;

return [
    'AlertService normalizes unsupported severity to medium' => static function (): void {
        $pdo = t_db();
        $repo = new AlertRepository($pdo);
        $service = new AlertService($repo);
        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('normalizeSeverity');
        $method->setAccessible(true);
        $value = $method->invoke($service, 'unexpected-level');
        t_assert_same('medium', $value);
    },
    'AlertService high severity score is high band' => static function (): void {
        $pdo = t_db();
        $repo = new AlertRepository($pdo);
        $service = new AlertService($repo);
        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('severityScore');
        $method->setAccessible(true);
        $score = (int) $method->invoke($service, 'high', 'workflow.failed');
        t_assert_true($score >= 90, 'High severity score should be >= 90.');
    },
];
